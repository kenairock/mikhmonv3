<?php
/*
 *  Security helpers for Mikhmon.
 *
 *  - Session cookie hardening (HttpOnly, SameSite=Strict)
 *  - CSRF token for POST forms + same-origin check for state-changing GETs
 *  - Output escaping helpers
 *  - Session (router) name validation
 *  - Safe writer for include/config.php (values are exported as PHP literals,
 *    never concatenated into code)
 */
if (basename($_SERVER['SCRIPT_FILENAME']) == 'security.php') {
    header("Location:../");
    exit;
}

if (!defined('MIKHMON_SECURITY')) {
define('MIKHMON_SECURITY', true);

// First line of include/config.php: stop the file being run as a page.
define('MIKHMON_CONFIG_GUARD', 'if (basename($_SERVER["SCRIPT_FILENAME"]) == "config.php") { header("Location:./"); exit; }');

// ---------------------------------------------------------------------------
// Session cookie
// ---------------------------------------------------------------------------

/**
 * Must be called before session_start(). Applies to the cookie whenever PHP
 * (re)sends it: on a new session and on session_regenerate_id().
 */
function mikhmon_session_cookie_params()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    $secure = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(array(
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ));
    } else {
        session_set_cookie_params(0, '/; samesite=Strict', '', $secure, true);
    }
}

// ---------------------------------------------------------------------------
// Escaping
// ---------------------------------------------------------------------------

/** Escape for HTML text and quoted attribute values. */
function esc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Encode as a JavaScript string literal (quotes included), safe inside <script>. */
function esc_js($value)
{
    return json_encode((string) $value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}

/** Read a request value as a string ('' when missing or not a scalar). */
function mikhmon_input($source, $key, $default = '')
{
    if (!isset($source[$key]) || !is_scalar($source[$key])) {
        return $default;
    }
    return (string) $source[$key];
}

// ---------------------------------------------------------------------------
// Theme (look and feel)
// ---------------------------------------------------------------------------

/**
 * Default look for visitors who have not picked one in the Appearance menu.
 * The old server-side theme names map onto a mode and an accent (css/mikhmon.css).
 * Returns array(mode, accent).
 */
function mikhmon_theme_defaults($theme)
{
    $map = array(
        'dark'  => array('dark', 'blue'),
        'light' => array('light', 'blue'),
        'blue'  => array('light', 'blue'),
        'green' => array('light', 'green'),
        'pink'  => array('light', 'pink'),
    );
    return isset($map[$theme]) ? $map[$theme] : array('auto', 'blue');
}

/** Attributes for the <html> tag (no-JavaScript fallback; the script below refines them). */
function mikhmon_theme_html_attrs($theme)
{
    list($mode, $accent) = mikhmon_theme_defaults($theme);
    return ' data-mode="' . esc($mode) . '" data-theme="' . ($mode === 'dark' ? 'dark' : 'light') . '" data-accent="' . esc($accent) . '"';
}

/**
 * Inline script for <head>: applies the browser's saved mode/accent (localStorage) before
 * first paint, so there is no flash. js/mikhmon-shell.js wires up the Appearance controls.
 */
function mikhmon_theme_head_script()
{
    return "<script>
(function () {
"
        . "  var d = document.documentElement, m, a;
"
        . "  try { m = localStorage.getItem('mikhmon.mode'); a = localStorage.getItem('mikhmon.accent'); } catch (e) {}
"
        . "  m = (m === 'auto' || m === 'light' || m === 'dark') ? m : d.getAttribute('data-mode');
"
        . "  a = (a === 'blue' || a === 'green' || a === 'pink' || a === 'slate') ? a : d.getAttribute('data-accent');
"
        . "  var dark = m === 'dark' || (m === 'auto' && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
"
        . "  d.setAttribute('data-mode', m); d.setAttribute('data-theme', dark ? 'dark' : 'light'); d.setAttribute('data-accent', a);
"
        . "})();
</script>";
}

// ---------------------------------------------------------------------------
// RouterOS date / time zone compatibility
// ---------------------------------------------------------------------------

/**
 * RouterOS 6 reports dates as "nov/15/2026"; RouterOS 7 (7.25 checked) as "2026-11-15".
 * Mikhmon's reports, voucher expiry and live income all use the first form, so convert.
 * Anything that is not an ISO date is returned unchanged.
 */
function mikhmon_router_date($date)
{
    $date = (string) $date;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
        $months = array('jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec');
        $i = (int) $m[2] - 1;
        if (isset($months[$i])) {
            return $months[$i] . '/' . $m[3] . '/' . $m[1];
        }
    }
    return $date;
}

/**
 * Time zone name reported by the router, if PHP knows it. Routers set to a manual offset report
 * "manual" (and others report names PHP lacks); those return '' so callers keep PHP's own zone.
 */
function mikhmon_router_timezone($name)
{
    $name = (string) $name;
    return ($name !== '' && in_array($name, timezone_identifiers_list(), true)) ? $name : '';
}

// ---------------------------------------------------------------------------
// Per-router state
// ---------------------------------------------------------------------------
// Mikhmon can manage several routers from one browser session (even in two tabs). Anything
// that belongs to ONE router - report caches, list filters, the router's time zone - must not
// be shared, or router A would show router B's numbers. It is kept in
// $_SESSION['rstate'][<router session name>][<key>].

function mikhmon_state_get($router, $key, $default = '')
{
    $router = (string) $router;
    return isset($_SESSION['rstate'][$router]) && array_key_exists($key, $_SESSION['rstate'][$router])
        ? $_SESSION['rstate'][$router][$key]
        : $default;
}

function mikhmon_state_set($router, $key, $value)
{
    $_SESSION['rstate'][(string) $router][$key] = $value;
}

function mikhmon_state_has($router, $key)
{
    return isset($_SESSION['rstate'][(string) $router]) && array_key_exists($key, $_SESSION['rstate'][(string) $router]);
}

/** Forget everything stored for one router (when it is deleted). */
function mikhmon_state_clear($router)
{
    unset($_SESSION['rstate'][(string) $router]);
}

/** Use this router's own time zone for date()/strtotime() when it is known and valid. */
function mikhmon_apply_router_timezone($router)
{
    $tz = (string) mikhmon_state_get($router, 'timezone', '');
    if ($tz !== '' && mikhmon_router_timezone($tz) !== '') {
        date_default_timezone_set($tz);
    }
}

// ---------------------------------------------------------------------------
// Session (router) names
// ---------------------------------------------------------------------------

function mikhmon_valid_session_name($name)
{
    return is_string($name) && preg_match('/^[A-Za-z0-9._-]{1,64}$/', $name) === 1;
}

/** Returns $_GET['session'] if it is a valid name, otherwise an empty string. */
function mikhmon_session_param()
{
    $s = mikhmon_input($_GET, 'session');
    return mikhmon_valid_session_name($s) ? $s : '';
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

function csrf_token()
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        if (function_exists('random_bytes')) {
            $raw = random_bytes(32);
        } else {
            $raw = openssl_random_pseudo_bytes(32);
        }
        $_SESSION['csrf_token'] = bin2hex($raw);
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to place inside every POST form. */
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . esc(csrf_token()) . '">';
}

function csrf_valid()
{
    $sent = '';
    if (isset($_POST['csrf_token'])) {
        $sent = $_POST['csrf_token'];
    } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    return is_string($sent)
        && !empty($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

/**
 * True when the browser tells us the request was initiated by another site.
 * Uses Sec-Fetch-Site (cannot be suppressed by the initiating page) and falls
 * back to Origin / Referer for older browsers.
 */
function mikhmon_is_cross_site_request()
{
    if (!empty($_SERVER['HTTP_SEC_FETCH_SITE'])) {
        $site = strtolower($_SERVER['HTTP_SEC_FETCH_SITE']);
        return !($site === 'same-origin' || $site === 'none');
    }
    $source = '';
    if (!empty($_SERVER['HTTP_ORIGIN'])) {
        $source = $_SERVER['HTTP_ORIGIN'];
    } elseif (!empty($_SERVER['HTTP_REFERER'])) {
        $source = $_SERVER['HTTP_REFERER'];
    }
    if ($source === '') {
        return false;
    }
    $host = parse_url($source, PHP_URL_HOST);
    if (!$host) {
        return true; // e.g. "Origin: null"
    }
    $port = parse_url($source, PHP_URL_PORT);
    $origin = strtolower($host . ($port ? ':' . $port : ''));
    $self = strtolower(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
    $selfNoDefaultPort = preg_replace('/:(80|443)$/', '', $self);
    return !($origin === $self || $origin === $selfNoDefaultPort);
}

/**
 * URL path of the Mikhmon root ("/" or "/mikhmon/"), derived from the running
 * script so links work from sub-folders (voucher/, dashboard/ ...) too.
 */
function mikhmon_base_url()
{
    $root = str_replace('\\', '/', (string) realpath(dirname(__DIR__)));
    $file = str_replace('\\', '/', (string) realpath(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : ''));
    $name = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']) : '/';
    if ($root !== '' && $file !== '' && stripos($file, $root . '/') === 0) {
        $rel = substr($file, strlen($root)); // e.g. /voucher/print.php
        if (strlen($name) >= strlen($rel) && strcasecmp(substr($name, -strlen($rel)), $rel) === 0) {
            return substr($name, 0, strlen($name) - strlen($rel)) . '/';
        }
    }
    return rtrim(str_replace('\\', '/', dirname($name)), '/') . '/';
}

function mikhmon_deny($message)
{
    if (!headers_sent()) {
        http_response_code(403);
    }
    echo '<div style="font-family:sans-serif;padding:20px;"><h3>Request blocked</h3><p>' . esc($message)
        . '</p><p><a href="' . esc(mikhmon_base_url()) . 'admin.php?id=sessions">Continue</a></p></div>';
    exit;
}

/**
 * Call right after session_start() in entry points (admin.php, index.php, ...).
 * - Every POST must carry a valid CSRF token.
 * - GET requests coming from another site may only open harmless pages.
 */
function mikhmon_guard_request()
{
    if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST') {
        if (!csrf_valid()) {
            mikhmon_deny('Invalid or expired form token. Please reload the page and try again.');
        }
    }

    if (mikhmon_is_cross_site_request() && !empty($_GET)) {
        $harmless = true;
        foreach ($_GET as $k => $v) {
            if ($k === 'session') {
                continue;
            }
            if ($k === 'id' && in_array($v, array('login', 'sessions'), true)) {
                continue;
            }
            $harmless = false;
            break;
        }
        if (!$harmless) {
            mikhmon_deny('Cross-site request rejected. Open Mikhmon directly and repeat the action.');
        }
    }

    // A malformed session name is never legitimate; refuse it outright so it
    // cannot be reflected into HTML / JavaScript anywhere.
    if (isset($_GET['session']) && $_GET['session'] !== '' && !mikhmon_valid_session_name($_GET['session'])) {
        mikhmon_deny('Invalid session name.');
    }

    // Query values are RouterOS ids, names, dates and comments. None of them
    // legitimately contain HTML/JS metacharacters, and many pages echo them
    // back, so refuse those characters centrally (defence against reflected XSS).
    foreach ($_GET as $k => $v) {
        if (!is_string($v) || !mikhmon_safe_query_value((string) $k) || !mikhmon_safe_query_value($v)) {
            mikhmon_deny('Invalid characters in request.');
        }
    }

    // Logged in: make sure the account still exists / is active, then enforce
    // the role needed for this request and record state-changing requests.
    if (isset($_SESSION['mikhmon'])) {
        mikhmon_refresh_session_user();
        $need = mikhmon_required_role();
        $action = mikhmon_request_action();
        if (!mikhmon_can($need)) {
            if ($action) {
                mikhmon_audit($action['action'], $action['target'], $action['details'], 'denied');
            } else {
                mikhmon_audit('view', mikhmon_request_page(), 'requires ' . $need, 'denied');
            }
            mikhmon_deny('Your role (' . mikhmon_current_role() . ') is not allowed to do this. Required: ' . $need . '.');
        }
        if ($action) {
            mikhmon_audit($action['action'], $action['target'], $action['details']);
        }
    }
}

// ---------------------------------------------------------------------------
// Roles
// ---------------------------------------------------------------------------

function mikhmon_current_role()
{
    return isset($_SESSION['mikhmon_role']) && is_string($_SESSION['mikhmon_role']) ? $_SESSION['mikhmon_role'] : '';
}

/** True when the logged-in user has at least $role (viewer < operator < admin). */
function mikhmon_can($role)
{
    return isset($_SESSION['mikhmon']) && mikhmon_role_level(mikhmon_current_role()) >= mikhmon_role_level($role);
}

/** Store the authenticated account in the session (call right after login). */
function mikhmon_start_user_session($user)
{
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
    $_SESSION['mikhmon'] = (string) $user['username'];
    $_SESSION['mikhmon_uid'] = (int) $user['id'];
    $_SESSION['mikhmon_role'] = (string) $user['role'];
}

/**
 * Re-read the account on every request so that role changes, disabling or
 * deleting an account take effect immediately. Sessions created before
 * accounts existed are sent back to the login page once.
 */
function mikhmon_refresh_session_user()
{
    $db = mikhmon_db();
    if (!$db) {
        // no SQLite: single legacy admin
        $_SESSION['mikhmon_role'] = 'admin';
        return;
    }
    $uid = isset($_SESSION['mikhmon_uid']) ? (int) $_SESSION['mikhmon_uid'] : 0;
    $user = $uid > 0 ? mikhmon_user_by_id($uid) : null;
    if (!$user || (int) $user['active'] !== 1 || strcasecmp((string) $user['username'], (string) $_SESSION['mikhmon']) !== 0) {
        $_SESSION = array();
        session_regenerate_id(true);
        echo "<script>window.top.location=" . esc_js(mikhmon_base_url() . 'admin.php?id=login') . ";</script>";
        exit;
    }
    $_SESSION['mikhmon'] = (string) $user['username'];
    $_SESSION['mikhmon_role'] = (string) $user['role'];
}

/** Query keys that change router state, and the role each needs. */
function mikhmon_action_keys()
{
    return array(
        // admin
        'setlang' => 'admin', 'set-theme' => 'admin',
        'enable-scheduler' => 'admin', 'disable-scheduler' => 'admin', 'remove-scheduler' => 'admin',
        // operator
        'remove-user-active' => 'operator', 'remove-host' => 'operator', 'remove-cookie' => 'operator',
        'remove-ip-binding' => 'operator', 'enable-ip-binding' => 'operator', 'disable-ip-binding' => 'operator',
        'remove-hotspot-user' => 'operator', 'remove-hotspot-users' => 'operator', 'remove-user-profile' => 'operator',
        'reset-hotspot-user' => 'operator', 'remove-hotspot-user-by-comment' => 'operator',
        'remove-hotspot-user-expired' => 'operator', 'enable-hotspot-user' => 'operator', 'disable-hotspot-user' => 'operator',
        'remove-report' => 'operator', 'remove-pactive' => 'operator', 'remove-pppsecret' => 'operator',
        'enable-pppsecret' => 'operator', 'disable-pppsecret' => 'operator', 'remove-pprofile' => 'operator',
        'mac' => 'operator', 'remove' => 'operator',
    );
}

/** admin.php ?id= values that need the admin role. */
function mikhmon_admin_page_ids()
{
    return array('settings', 'uplogo', 'remove-logo', 'editor', 'reboot', 'shutdown', 'remove-session', 'accounts', 'audit');
}

function mikhmon_script_name()
{
    return strtolower(basename(isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : ''));
}

function mikhmon_is_post()
{
    return isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST';
}

/**
 * Minimum role for the current request.
 *   viewer   - look at dashboards, lists, reports
 *   operator - change hotspot users / profiles / bindings, vouchers, reports
 *   admin    - router sessions & settings, logo, templates, reboot, accounts, audit
 */
function mikhmon_required_role()
{
    $g = $_GET;
    $script = mikhmon_script_name();
    $id = isset($g['id']) ? (string) $g['id'] : '';
    $get = function ($k) use ($g) {
        return isset($g[$k]) ? (string) $g[$k] : '';
    };

    if ($script === 'admin.php') {
        if (in_array($id, mikhmon_admin_page_ids(), true) || $get('router') !== '') {
            return 'admin';
        }
        if (mikhmon_is_post() && !in_array($id, array('login', 'account'), true)) {
            return 'admin';
        }
    }
    if ($script === 'index.php' && in_array($get('hotspot'), array('template-editor', 'uplogo'), true)) {
        return 'admin';
    }
    $need = 'viewer';
    foreach (mikhmon_action_keys() as $key => $role) {
        if ($get($key) !== '' && mikhmon_role_level($role) > mikhmon_role_level($need)) {
            $need = $role;
        }
    }
    if ($need !== 'viewer') {
        return $need;
    }
    if ($script === 'quickuser.php') {
        return 'operator'; // creates a hotspot user
    }
    if ($get('hotspot') === 'add-user' || in_array($get('hotspot-user'), array('add', 'generate'), true)
        || $get('user-profile') === 'add' || in_array($get('ppp'), array('addsecret', 'add-profile'), true)) {
        return 'operator';
    }
    if (mikhmon_is_post()) {
        return ($script === 'admin.php' && in_array($id, array('login', 'account'), true)) ? 'viewer' : 'operator';
    }
    return 'viewer';
}

/**
 * Describe the current request for the audit log when it changes something
 * (POST, or a GET carrying an action key). Returns null for read-only requests.
 * Password fields and template bodies are never written to the log.
 */
/** Short description of the requested page, e.g. "index.php?hotspot=users". */
function mikhmon_request_page()
{
    $g = $_GET;
    $script = mikhmon_script_name();
    $page = $script . (isset($g['id']) ? '?id=' . $g['id'] : '');
    foreach (array('hotspot', 'hotspot-user', 'user-profile', 'report', 'ppp', 'system', 'template') as $k) {
        if (isset($g[$k]) && is_string($g[$k]) && $g[$k] !== '') {
            $page .= ($page === $script ? '?' : '&') . $k . '=' . $g[$k];
        }
    }
    return $page;
}

function mikhmon_request_action()
{
    $g = $_GET;
    $script = mikhmon_script_name();
    $page = mikhmon_request_page();

    if (mikhmon_is_post()) {
        if ($script === 'admin.php' && isset($g['id']) && in_array($g['id'], array('login', 'accounts', 'account'), true)) {
            return null; // logged explicitly with better detail
        }
        $fields = array();
        foreach ($_POST as $k => $v) {
            $k = (string) $k;
            if ($k === 'csrf_token' || stripos($k, 'pass') !== false) {
                continue;
            }
            if ($k === 'editor') {
                $fields[] = 'editor=(' . strlen(is_string($v) ? $v : '') . ' bytes)';
                continue;
            }
            $fields[] = $k . '=' . (is_scalar($v) ? substr((string) $v, 0, 80) : '[...]');
        }
        return array('action' => 'post ' . $page, 'target' => '', 'details' => implode(', ', $fields));
    }

    foreach (mikhmon_action_keys() as $key => $role) {
        if (isset($g[$key]) && $g[$key] !== '') {
            return array('action' => $key, 'target' => (string) $g[$key], 'details' => $page);
        }
    }
    if ($script === 'admin.php' && isset($g['id'])) {
        if ($g['id'] === 'remove-session') {
            return array('action' => 'remove-session', 'target' => isset($g['session']) ? (string) $g['session'] : '', 'details' => '');
        }
        if ($g['id'] === 'remove-logo') {
            return array('action' => 'remove-logo', 'target' => isset($g['logo']) ? (string) $g['logo'] : '', 'details' => '');
        }
        if ($g['id'] === 'settings' && isset($g['router']) && $g['router'] !== '') {
            return array('action' => 'add-router', 'target' => (string) $g['router'], 'details' => '');
        }
    }
    if ($script === 'quickuser.php') {
        return array('action' => 'quick-print-user', 'target' => isset($g['quickprint']) ? (string) $g['quickprint'] : '', 'details' => '');
    }
    return null;
}

/** True when a query key/value contains no HTML/JS metacharacters or control characters. */
function mikhmon_safe_query_value($value)
{
    return preg_match('/[<>"\'`\\\\\x00-\x1F\x7F]/', $value) !== 1;
}

// ---------------------------------------------------------------------------
// Config writer
// ---------------------------------------------------------------------------

function mikhmon_config_path()
{
    return __DIR__ . '/config.php';
}

/** Export one config entry as a single line (other code parses config.php line by line). */
function mikhmon_config_line($key, $values)
{
    $parts = array();
    $i = 0;
    foreach (array_values($values) as $v) {
        // Values are single-line by design; drop CR/LF so each entry stays on one line.
        $lit = var_export(str_replace(array("\r", "\n"), '', (string) $v), true);
        $parts[] = ($i === 0 ? "'1'=>" : '') . $lit;
        $i++;
    }
    return '$data[' . var_export((string) $key, true) . '] = array (' . implode(',', $parts) . ');';
}

/**
 * Rewrite include/config.php from the $data array. Keys must be valid session
 * names (or "mikhmon"); every value is emitted as an escaped PHP string literal.
 */
function mikhmon_save_config($data)
{
    // Header uses double quotes only: menu.php reads the session name as the
    // first single-quoted token of each line.
    $out = "<?php \n" . MIKHMON_CONFIG_GUARD . " \n";
    foreach ($data as $key => $values) {
        if (!is_array($values)) {
            continue;
        }
        if ($key !== 'mikhmon' && !mikhmon_valid_session_name((string) $key)) {
            continue;
        }
        $out .= mikhmon_config_line($key, $values) . "\n";
    }
    $ok = file_put_contents(mikhmon_config_path(), $out, LOCK_EX) !== false;
    if ($ok && function_exists('opcache_invalidate')) {
        @opcache_invalidate(mikhmon_config_path(), true);
    }
    return $ok;
}

/** Write a tiny "<?php $var = 'value'; ?>" file safely. */
function mikhmon_write_php_var($path, $vars)
{
    $code = '<?php';
    foreach ($vars as $name => $value) {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            continue;
        }
        $code .= ' $' . $name . '=' . var_export((string) $value, true) . ';';
    }
    $code .= '?>';
    $ok = file_put_contents($path, $code, LOCK_EX) !== false;
    if ($ok && function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
    }
    return $ok;
}

// ---------------------------------------------------------------------------
// Secret encryption (router API passwords, voucher temp data)
// ---------------------------------------------------------------------------

/**
 * 32-byte key kept in a PHP file that only returns the key (so it prints
 * nothing if it is ever requested over HTTP). Created on first use.
 * Override the location with the MIKHMON_SECRET_FILE environment variable.
 * Returns false when the key cannot be loaded or created.
 */
function mikhmon_secret_key()
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $env = getenv('MIKHMON_SECRET_FILE');
    $file = ($env !== false && $env !== '') ? $env : __DIR__ . '/secret.key.php';

    $load = function () use ($file) {
        if (!is_file($file)) {
            return false;
        }
        $hex = include $file;
        return (is_string($hex) && strlen($hex) === 64 && ctype_xdigit($hex)) ? hex2bin($hex) : false;
    };

    $k = $load();
    if ($k === false && !is_file($file)) {
        $hex = bin2hex(random_bytes(32));
        $code = "<?php\n// Mikhmon encryption key. Keep it secret, back it up together with config.php,\n"
            . "// and never commit it. Losing it means re-entering the router passwords.\n"
            . "return '" . $hex . "';\n";
        $fh = @fopen($file, 'x'); // fails if another request created it first
        if ($fh) {
            fwrite($fh, $code);
            fclose($fh);
            @chmod($file, 0600);
        }
        $k = $load();
    }
    if ($k === false) {
        error_log('Mikhmon: encryption key unavailable (' . $file . '); falling back to legacy encoding.');
    }
    return $key = $k;
}

/** Original Mikhmon reversible encoding; only kept to read old values. */
function mikhmon_legacy_encrypt($string, $key = 128)
{
    $string = (string) $string;
    $key = (string) $key;
    $result = '';
    for ($i = 0, $k = strlen($string); $i < $k; $i++) {
        $char = substr($string, $i, 1);
        $keychar = substr($key, ($i % strlen($key)) - 1, 1);
        $result .= chr(ord($char) + ord($keychar));
    }
    return base64_encode($result);
}

function mikhmon_legacy_decrypt($string, $key = 128)
{
    $string = base64_decode((string) $string);
    $key = (string) $key;
    $result = '';
    for ($i = 0, $k = strlen($string); $i < $k; $i++) {
        $char = substr($string, $i, 1);
        $keychar = substr($key, ($i % strlen($key)) - 1, 1);
        $result .= chr(ord($char) - ord($keychar));
    }
    return $result;
}

/** AES-256-GCM; output "v2:" . base64(iv . tag . ciphertext). */
function mikhmon_encrypt($plain)
{
    $plain = (string) $plain;
    $key = mikhmon_secret_key();
    if ($key !== false && function_exists('openssl_encrypt')) {
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'mikhmon');
        if ($ct !== false) {
            return 'v2:' . base64_encode($iv . $tag . $ct);
        }
    }
    return mikhmon_legacy_encrypt($plain);
}

function mikhmon_decrypt($stored)
{
    $stored = (string) $stored;
    if (strncmp($stored, 'v2:', 3) !== 0) {
        return mikhmon_legacy_decrypt($stored);
    }
    $raw = base64_decode(substr($stored, 3), true);
    $key = mikhmon_secret_key();
    if ($raw === false || strlen($raw) < 28 || $key === false || !function_exists('openssl_decrypt')) {
        return '';
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'mikhmon');
    return $plain === false ? '' : $plain;
}

function mikhmon_is_encrypted_v2($stored)
{
    return strncmp((string) $stored, 'v2:', 3) === 0;
}

// ---------------------------------------------------------------------------
// Admin password
// ---------------------------------------------------------------------------

function mikhmon_is_password_hash($stored)
{
    $stored = (string) $stored;
    return strncmp($stored, '$2y$', 4) === 0 || strncmp($stored, '$argon2', 7) === 0;
}

/**
 * Check admin credentials. Supports password_hash() values and the legacy
 * reversible encoding; $needsUpgrade is set when the stored value should be
 * replaced with a fresh hash.
 */
function mikhmon_verify_admin($user, $pass, $useradm, $passadm, &$needsUpgrade = false)
{
    $needsUpgrade = false;
    $useradm = (string) $useradm;
    $passadm = (string) $passadm;
    if (!is_string($user) || !is_string($pass) || $useradm === '' || $passadm === '' || $pass === '') {
        return false;
    }
    $userOk = hash_equals($useradm, $user);
    if (mikhmon_is_password_hash($passadm)) {
        $passOk = password_verify($pass, $passadm);
        $needsUpgrade = $passOk && password_needs_rehash($passadm, PASSWORD_DEFAULT);
    } else {
        $plain = mikhmon_legacy_decrypt($passadm);
        $passOk = $plain !== '' && hash_equals($plain, $pass);
        $needsUpgrade = $passOk;
    }
    return $userOk && $passOk;
}

/** True while the shipped mikhmon / 1234 login is still in use. */
function mikhmon_is_default_admin($useradm, $passadm)
{
    $ignored = false;
    return mikhmon_verify_admin('mikhmon', '1234', $useradm, $passadm, $ignored);
}

/**
 * After a successful login: hash the admin password and move router
 * passwords from the legacy encoding to AES-GCM.
 */
function mikhmon_upgrade_stored_secrets(&$data, $adminPlain, $adminNeedsUpgrade)
{
    $changed = false;
    if ($adminNeedsUpgrade && isset($data['mikhmon'][1])) {
        $data['mikhmon'][2] = 'mikhmon>|>' . password_hash((string) $adminPlain, PASSWORD_DEFAULT);
        $changed = true;
    }
    if (mikhmon_secret_key() !== false) {
        foreach ($data as $name => $entry) {
            if ($name === 'mikhmon' || !is_array($entry) || !isset($entry[3])) {
                continue;
            }
            $parts = explode('#|#', (string) $entry[3], 2);
            if (count($parts) === 2 && $parts[1] !== '' && !mikhmon_is_encrypted_v2($parts[1])) {
                $data[$name][3] = $parts[0] . '#|#' . mikhmon_encrypt(mikhmon_legacy_decrypt($parts[1]));
                $changed = true;
            }
        }
    }
    if ($changed) {
        mikhmon_save_config($data);
    }
    return $changed;
}

// ---------------------------------------------------------------------------
// Rate limiting (file based, per client IP)
// ---------------------------------------------------------------------------

function mikhmon_rate_file()
{
    return __DIR__ . '/.ratelimit.json';
}

/**
 * Run $fn with the decoded state under an exclusive lock and save the result.
 * Fails open (returns null) if the state file cannot be used.
 */
function mikhmon_rate_locked($fn)
{
    $fh = @fopen(mikhmon_rate_file(), 'c+');
    if (!$fh) {
        return null;
    }
    $result = null;
    if (flock($fh, LOCK_EX)) {
        $json = stream_get_contents($fh);
        $state = json_decode($json === false ? '' : $json, true);
        if (!is_array($state)) {
            $state = array();
        }
        $result = $fn($state);
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($state));
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
    return $result;
}

function mikhmon_client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
}

/** Seconds until $bucket is allowed again for this client (0 = allowed). */
function mikhmon_rate_wait($bucket, $max, $window)
{
    $id = $bucket . '|' . mikhmon_client_ip();
    $now = time();
    $wait = mikhmon_rate_locked(function (&$state) use ($id, $max, $window, $now) {
        foreach ($state as $k => $hits) {
            $state[$k] = array_values(array_filter((array) $hits, function ($t) use ($now, $window) {
                return $t > $now - $window;
            }));
            if (!$state[$k]) {
                unset($state[$k]);
            }
        }
        $hits = isset($state[$id]) ? $state[$id] : array();
        return count($hits) >= $max ? max(1, min($hits) + $window - $now) : 0;
    });
    return (int) $wait;
}

function mikhmon_rate_hit($bucket)
{
    $id = $bucket . '|' . mikhmon_client_ip();
    $now = time();
    mikhmon_rate_locked(function (&$state) use ($id, $now) {
        $state[$id][] = $now;
    });
}

function mikhmon_rate_clear($bucket)
{
    $id = $bucket . '|' . mikhmon_client_ip();
    mikhmon_rate_locked(function (&$state) use ($id) {
        unset($state[$id]);
    });
}

} // MIKHMON_SECURITY

require_once __DIR__ . '/db.php';
