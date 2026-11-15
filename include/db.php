<?php
/*
 *  SQLite storage for Mikhmon: admin accounts (roles), login history, audit log.
 *
 *  Database file location (first match wins):
 *    1. MIKHMON_DB server/FastCGI parameter or environment variable
 *    2. include/mikhmon.sqlite (blocked from the web by the nginx config)
 *
 *  Requires the pdo_sqlite extension. Without it Mikhmon falls back to the
 *  single admin login stored in include/config.php (as before).
 */
if (basename($_SERVER['SCRIPT_FILENAME']) == 'db.php') {
    header("Location:../");
    exit;
}

if (!defined('MIKHMON_DB_LAYER')) {
define('MIKHMON_DB_LAYER', true);

define('MIKHMON_DB_SCHEMA_VERSION', 2);
// Login history and audit entries older than this are pruned automatically.
define('MIKHMON_LOG_RETENTION_DAYS', 365);

/** Roles, lowest to highest. */
function mikhmon_roles()
{
    return array('viewer' => 1, 'operator' => 2, 'admin' => 3);
}

function mikhmon_role_level($role)
{
    $roles = mikhmon_roles();
    return isset($roles[$role]) ? $roles[$role] : 0;
}

function mikhmon_db_path()
{
    if (!empty($_SERVER['MIKHMON_DB']) && is_string($_SERVER['MIKHMON_DB'])) {
        return $_SERVER['MIKHMON_DB'];
    }
    $env = getenv('MIKHMON_DB');
    if ($env !== false && $env !== '') {
        return $env;
    }
    return __DIR__ . '/mikhmon.sqlite';
}

function mikhmon_db_available()
{
    return class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true);
}

/** Shared PDO handle, or null when SQLite is not usable. */
function mikhmon_db()
{
    static $pdo = null;
    static $failed = false;
    if ($pdo !== null) {
        return $pdo;
    }
    if ($failed) {
        return null;
    }
    if (!mikhmon_db_available()) {
        $failed = true;
        error_log('Mikhmon: pdo_sqlite is not enabled; using the single admin login from config.php.');
        return null;
    }
    try {
        $db = new PDO('sqlite:' . mikhmon_db_path(), null, null, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ));
        $db->exec('PRAGMA busy_timeout = 5000');
        $db->exec('PRAGMA journal_mode = WAL');
        $db->exec('PRAGMA foreign_keys = ON');
        mikhmon_db_migrate($db);
        $pdo = $db;
        mikhmon_db_bootstrap_admin($pdo);
        return $pdo;
    } catch (Throwable $e) {
        $failed = true;
        error_log('Mikhmon: cannot open database ' . mikhmon_db_path() . ': ' . $e->getMessage());
        return null;
    }
}

function mikhmon_db_migrate(PDO $db)
{
    $version = (int) $db->query('PRAGMA user_version')->fetchColumn();
    if ($version >= MIKHMON_DB_SCHEMA_VERSION) {
        return;
    }
    $db->beginTransaction();
    if ($version < 1) {
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT    NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT    NOT NULL,
            role          TEXT    NOT NULL CHECK (role IN ('admin', 'operator', 'viewer')),
            active        INTEGER NOT NULL DEFAULT 1,
            created_at    INTEGER NOT NULL,
            updated_at    INTEGER NOT NULL,
            last_login_at INTEGER
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS login_log (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            ts         INTEGER NOT NULL,
            username   TEXT    NOT NULL,
            success    INTEGER NOT NULL,
            reason     TEXT,
            ip         TEXT,
            user_agent TEXT
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS audit_log (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            ts       INTEGER NOT NULL,
            username TEXT    NOT NULL,
            role     TEXT,
            ip       TEXT,
            router   TEXT,
            action   TEXT    NOT NULL,
            target   TEXT,
            details  TEXT,
            result   TEXT    NOT NULL DEFAULT 'ok'
        )");
        $db->exec('CREATE INDEX IF NOT EXISTS idx_login_ts ON login_log (ts)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_login_user ON login_log (username)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_audit_ts ON audit_log (ts)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_log (username)');
    }
    if ($version < 2) {
        // two-factor authentication (TOTP) + one-time recovery codes
        $db->exec('ALTER TABLE users ADD COLUMN totp_secret TEXT');
        $db->exec('ALTER TABLE users ADD COLUMN totp_enabled INTEGER NOT NULL DEFAULT 0');
        $db->exec('ALTER TABLE users ADD COLUMN totp_last_step INTEGER NOT NULL DEFAULT 0');
        $db->exec('ALTER TABLE users ADD COLUMN totp_fails INTEGER NOT NULL DEFAULT 0');
        $db->exec('ALTER TABLE users ADD COLUMN totp_locked_until INTEGER NOT NULL DEFAULT 0');
        $db->exec("CREATE TABLE IF NOT EXISTS recovery_codes (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id   INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
            code_hash TEXT    NOT NULL,
            used_at   INTEGER
        )");
        $db->exec('CREATE INDEX IF NOT EXISTS idx_recovery_user ON recovery_codes (user_id)');
    }
    $db->exec('PRAGMA user_version = ' . (int) MIKHMON_DB_SCHEMA_VERSION);
    $db->commit();
}

/** Marker written into config.php once its admin login has moved into the database. */
function mikhmon_config_migrated_marker()
{
    return '!moved-to-database';
}

function mikhmon_load_config_data()
{
    $data = array();
    if (is_file(mikhmon_config_path())) {
        include mikhmon_config_path();
    }
    return $data;
}

/**
 * First run: create the initial admin account from the login stored in
 * config.php (keeps the current username/password, including the default
 * mikhmon / 1234), then disable that config login so it cannot be used as a
 * second, unmanaged way in.
 */
function mikhmon_db_bootstrap_admin(PDO $db)
{
    if ((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
        return;
    }
    $data = mikhmon_load_config_data();
    $user = isset($data['mikhmon'][1]) ? (explode('<|<', (string) $data['mikhmon'][1])[1] ?? '') : '';
    $pass = isset($data['mikhmon'][2]) ? (explode('>|>', (string) $data['mikhmon'][2])[1] ?? '') : '';
    if ($user === '' || $pass === '' || $pass === mikhmon_config_migrated_marker()) {
        $user = 'mikhmon';
        $pass = mikhmon_legacy_encrypt('1234');
    }
    $now = time();
    // INSERT OR IGNORE: two first requests at the same time must not both fail
    $st = $db->prepare('INSERT OR IGNORE INTO users (username, password_hash, role, active, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?)');
    // the stored value may still be the legacy encoding; it is re-hashed on first login
    $st->execute(array($user, $pass, 'admin', $now, $now));
    if ($st->rowCount() === 0) {
        return;
    }

    if (isset($data['mikhmon'])) {
        $data['mikhmon'] = array(1 => 'mikhmon<|<' . $user, 'mikhmon>|>' . mikhmon_config_migrated_marker());
        mikhmon_save_config($data);
    }
    mikhmon_audit_write($db, 'system', 'admin', 'account.bootstrap', $user, 'initial admin account created from config.php', 'ok');
}

// ---------------------------------------------------------------------------
// Accounts
// ---------------------------------------------------------------------------

function mikhmon_valid_username($name)
{
    return is_string($name) && preg_match('/^[A-Za-z0-9._@-]{3,32}$/', $name) === 1;
}

/** Returns an error message, or '' when the password is acceptable. */
function mikhmon_password_problem($password)
{
    if (!is_string($password) || strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (strlen($password) > 128) {
        return 'Password is too long (max 128 characters).';
    }
    return '';
}

function mikhmon_user_by_name($username)
{
    $db = mikhmon_db();
    if (!$db) {
        return null;
    }
    $st = $db->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute(array((string) $username));
    $row = $st->fetch();
    return $row ?: null;
}

function mikhmon_user_by_id($id)
{
    $db = mikhmon_db();
    if (!$db) {
        return null;
    }
    $st = $db->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute(array((int) $id));
    $row = $st->fetch();
    return $row ?: null;
}

function mikhmon_users_list()
{
    $db = mikhmon_db();
    return $db ? $db->query('SELECT * FROM users ORDER BY username COLLATE NOCASE')->fetchAll() : array();
}

function mikhmon_active_admin_count($excludeId = 0)
{
    $db = mikhmon_db();
    if (!$db) {
        return 0;
    }
    $st = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND id <> ?");
    $st->execute(array((int) $excludeId));
    return (int) $st->fetchColumn();
}

/** Returns '' on success or an error message. */
function mikhmon_user_create($username, $password, $role)
{
    $db = mikhmon_db();
    if (!$db) {
        return 'Database unavailable.';
    }
    if (!mikhmon_valid_username($username)) {
        return 'Username must be 3-32 characters: letters, numbers, . _ @ -';
    }
    if (!mikhmon_role_level($role)) {
        return 'Unknown role.';
    }
    $problem = mikhmon_password_problem($password);
    if ($problem !== '') {
        return $problem;
    }
    if (mikhmon_user_by_name($username)) {
        return 'That username already exists.';
    }
    $now = time();
    $st = $db->prepare('INSERT INTO users (username, password_hash, role, active, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?)');
    $st->execute(array($username, password_hash($password, PASSWORD_DEFAULT), $role, $now, $now));
    return '';
}

/** Change role and/or active flag. Refuses to remove the last active admin. */
function mikhmon_user_update($id, $role, $active)
{
    $db = mikhmon_db();
    $user = mikhmon_user_by_id($id);
    if (!$db || !$user) {
        return 'Account not found.';
    }
    if (!mikhmon_role_level($role)) {
        return 'Unknown role.';
    }
    $active = $active ? 1 : 0;
    $losesAdmin = $user['role'] === 'admin' && (int) $user['active'] === 1 && ($role !== 'admin' || !$active);
    if ($losesAdmin && mikhmon_active_admin_count((int) $user['id']) === 0) {
        return 'There must always be at least one active admin.';
    }
    $st = $db->prepare('UPDATE users SET role = ?, active = ?, updated_at = ? WHERE id = ?');
    $st->execute(array($role, $active, time(), (int) $id));
    return '';
}

function mikhmon_user_set_password($id, $password)
{
    $db = mikhmon_db();
    if (!$db || !mikhmon_user_by_id($id)) {
        return 'Account not found.';
    }
    $problem = mikhmon_password_problem($password);
    if ($problem !== '') {
        return $problem;
    }
    $st = $db->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?');
    $st->execute(array(password_hash($password, PASSWORD_DEFAULT), time(), (int) $id));
    return '';
}

function mikhmon_user_delete($id)
{
    $db = mikhmon_db();
    $user = mikhmon_user_by_id($id);
    if (!$db || !$user) {
        return 'Account not found.';
    }
    if ($user['role'] === 'admin' && (int) $user['active'] === 1 && mikhmon_active_admin_count((int) $user['id']) === 0) {
        return 'There must always be at least one active admin.';
    }
    $st = $db->prepare('DELETE FROM users WHERE id = ?');
    $st->execute(array((int) $id));
    return '';
}

/** Verify a password against a stored hash or legacy-encoded value. */
function mikhmon_password_matches($password, $stored, &$needsRehash = false)
{
    $needsRehash = false;
    $stored = (string) $stored;
    if (!is_string($password) || $password === '' || $stored === '' || $stored === mikhmon_config_migrated_marker()) {
        return false;
    }
    if (mikhmon_is_password_hash($stored)) {
        $ok = password_verify($password, $stored);
        $needsRehash = $ok && password_needs_rehash($stored, PASSWORD_DEFAULT);
        return $ok;
    }
    $plain = mikhmon_legacy_decrypt($stored);
    $ok = $plain !== '' && hash_equals($plain, $password);
    $needsRehash = $ok;
    return $ok;
}

/**
 * Check a login. Returns array('ok' => bool, 'user' => row|null, 'reason' => string).
 * Uses the database when available, otherwise the legacy config.php login.
 */
function mikhmon_authenticate($username, $password, $legacyUser, $legacyPass)
{
    $fail = function ($reason) {
        return array('ok' => false, 'user' => null, 'reason' => $reason);
    };
    if (!is_string($username) || !is_string($password) || $username === '' || $password === '') {
        return $fail('empty credentials');
    }
    $db = mikhmon_db();
    if (!$db) {
        // no SQLite: single admin from config.php (old behaviour)
        $upgrade = false;
        if ((string) $legacyPass !== mikhmon_config_migrated_marker()
            && (string) $legacyUser !== '' && hash_equals((string) $legacyUser, $username)
            && mikhmon_password_matches($password, $legacyPass, $upgrade)) {
            return array('ok' => true, 'user' => array('id' => 0, 'username' => $username, 'role' => 'admin', 'active' => 1, 'legacy_upgrade' => $upgrade), 'reason' => '');
        }
        return $fail((string) $legacyPass === mikhmon_config_migrated_marker() ? 'database unavailable' : 'bad credentials');
    }
    $user = mikhmon_user_by_name($username);
    $rehash = false;
    if (!$user) {
        password_hash($password, PASSWORD_DEFAULT); // same cost as a real check, so timing doesn't reveal valid usernames
        return $fail('unknown user');
    }
    if (!mikhmon_password_matches($password, $user['password_hash'], $rehash)) {
        return $fail('bad password');
    }
    if ((int) $user['active'] !== 1) {
        return $fail('account disabled');
    }
    $now = time();
    if ($rehash) {
        $st = $db->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?');
        $st->execute(array(password_hash($password, PASSWORD_DEFAULT), $now, (int) $user['id']));
    }
    $st = $db->prepare('UPDATE users SET last_login_at = ? WHERE id = ?');
    $st->execute(array($now, (int) $user['id']));
    $user['last_login_at'] = $now;
    return array('ok' => true, 'user' => $user, 'reason' => '');
}

// ---------------------------------------------------------------------------
// Login history & audit log
// ---------------------------------------------------------------------------

function mikhmon_user_agent()
{
    return isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
}

function mikhmon_log_login($username, $success, $reason = '')
{
    $db = mikhmon_db();
    if (!$db) {
        return;
    }
    try {
        $st = $db->prepare('INSERT INTO login_log (ts, username, success, reason, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
        $st->execute(array(time(), substr((string) $username, 0, 64), $success ? 1 : 0, (string) $reason, mikhmon_client_ip(), mikhmon_user_agent()));
        mikhmon_log_prune($db);
    } catch (Throwable $e) {
        error_log('Mikhmon: login log failed: ' . $e->getMessage());
    }
}

function mikhmon_audit_write(PDO $db, $username, $role, $action, $target, $details, $result)
{
    $router = isset($_GET['session']) && mikhmon_valid_session_name($_GET['session']) ? $_GET['session'] : '';
    $st = $db->prepare('INSERT INTO audit_log (ts, username, role, ip, router, action, target, details, result) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $st->execute(array(
        time(),
        substr((string) $username, 0, 64),
        (string) $role,
        mikhmon_client_ip(),
        $router,
        substr((string) $action, 0, 100),
        substr((string) $target, 0, 255),
        substr((string) $details, 0, 2000),
        (string) $result,
    ));
}

/** Record an action by the logged-in user. */
function mikhmon_audit($action, $target = '', $details = '', $result = 'ok')
{
    $db = mikhmon_db();
    if (!$db) {
        return;
    }
    try {
        $user = isset($_SESSION['mikhmon']) ? (string) $_SESSION['mikhmon'] : '-';
        $role = isset($_SESSION['mikhmon_role']) ? (string) $_SESSION['mikhmon_role'] : '';
        mikhmon_audit_write($db, $user, $role, $action, $target, $details, $result);
        mikhmon_log_prune($db);
    } catch (Throwable $e) {
        error_log('Mikhmon: audit log failed: ' . $e->getMessage());
    }
}

/** Occasionally drop entries older than the retention period. */
function mikhmon_log_prune(PDO $db)
{
    if (random_int(1, 100) !== 1) {
        return;
    }
    $cut = time() - MIKHMON_LOG_RETENTION_DAYS * 86400;
    $db->prepare('DELETE FROM login_log WHERE ts < ?')->execute(array($cut));
    $db->prepare('DELETE FROM audit_log WHERE ts < ?')->execute(array($cut));
}

/**
 * Paged query of login_log or audit_log.
 * $filters: user, action (audit only), success ('1'/'0', login only), from/to (Y-m-d).
 * Returns array(rows, total).
 */
function mikhmon_log_query($table, $filters, $page, $perPage = 50)
{
    $db = mikhmon_db();
    if (!$db || !in_array($table, array('login_log', 'audit_log'), true)) {
        return array(array(), 0);
    }
    $where = array();
    $args = array();
    if (!empty($filters['user'])) {
        $where[] = 'username LIKE ?';
        $args[] = '%' . $filters['user'] . '%';
    }
    if ($table === 'audit_log' && !empty($filters['action'])) {
        $where[] = 'action LIKE ?';
        $args[] = '%' . $filters['action'] . '%';
    }
    if ($table === 'login_log' && isset($filters['success']) && ($filters['success'] === '1' || $filters['success'] === '0')) {
        $where[] = 'success = ?';
        $args[] = (int) $filters['success'];
    }
    foreach (array('from' => '>=', 'to' => '<') as $key => $op) {
        if (!empty($filters[$key]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$key])) {
            $ts = strtotime($filters[$key] . ' 00:00:00');
            if ($key === 'to') {
                $ts += 86400;
            }
            $where[] = 'ts ' . $op . ' ?';
            $args[] = $ts;
        }
    }
    $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $st = $db->prepare('SELECT COUNT(*) FROM ' . $table . $sqlWhere);
    $st->execute($args);
    $total = (int) $st->fetchColumn();
    $page = max(1, (int) $page);
    $st = $db->prepare('SELECT * FROM ' . $table . $sqlWhere . ' ORDER BY id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage));
    $st->execute($args);
    return array($st->fetchAll(), $total);
}

} // MIKHMON_DB_LAYER
