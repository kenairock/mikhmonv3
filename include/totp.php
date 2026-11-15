<?php
/*
 *  Two-factor authentication: time-based one-time passwords (RFC 6238) and recovery codes.
 *
 *  Works with any authenticator app (Google Authenticator, Microsoft Authenticator, Aegis,
 *  1Password, Bitwarden ...). Nothing leaves the server: the secret is generated here, shown once
 *  as a QR code drawn by include/qrcode.php, and stored encrypted (AES-256-GCM, secret.key.php).
 *
 *  Policy: the admin role MUST use it (enrolment is forced at the next login); other roles may
 *  turn it on under "My account".
 */
if (basename($_SERVER['SCRIPT_FILENAME']) == 'totp.php') {
    header("Location:../");
    exit;
}

require_once __DIR__ . '/qrcode.php';

if (!defined('MIKHMON_TOTP')) {
define('MIKHMON_TOTP', true);

define('MIKHMON_2FA_PENDING_SECONDS', 600);   // time allowed to finish the second step after the password
define('MIKHMON_2FA_MAX_TRIES', 5);           // wrong codes per sign-in attempt
define('MIKHMON_2FA_LOCK_AFTER', 10);         // consecutive wrong codes on one account before it is paused
define('MIKHMON_2FA_LOCK_SECONDS', 900);
define('MIKHMON_RECOVERY_CODES', 8);

// ---------------------------------------------------------------------------
// TOTP primitives
// ---------------------------------------------------------------------------

function mikhmon_b32_encode($bin)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split((string) $bin) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function mikhmon_b32_decode($text)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $text = strtoupper(preg_replace('/[\s=-]+/', '', (string) $text));
    $bits = '';
    for ($i = 0, $n = strlen($text); $i < $n; $i++) {
        $v = strpos($alphabet, $text[$i]);
        if ($v === false) {
            return '';
        }
        $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

/** New random secret (20 bytes) as Base32 text, the form authenticator apps expect. */
function mikhmon_totp_new_secret()
{
    return mikhmon_b32_encode(random_bytes(20));
}

/** The 6-digit code for a 30-second step. */
function mikhmon_totp_code($secretBin, $step)
{
    $hash = hash_hmac('sha1', pack('J', (int) $step), $secretBin, true);
    $offset = ord($hash[19]) & 0x0F;
    $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
    return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Check a typed code against a Base32 secret. Accepts the previous, current and next step
 * (clock drift) but never a step at or before $lastStep, so a code works only once.
 * On success $usedStep holds the step that matched.
 */
function mikhmon_totp_verify($secretB32, $input, $lastStep, &$usedStep)
{
    $usedStep = 0;
    $input = preg_replace('/\s+/', '', (string) $input);
    $secret = mikhmon_b32_decode($secretB32);
    if ($secret === '' || preg_match('/^\d{6}$/', $input) !== 1) {
        return false;
    }
    $now = intdiv(time(), 30);
    $ok = false;
    for ($s = $now - 1; $s <= $now + 1; $s++) {
        $match = hash_equals(mikhmon_totp_code($secret, $s), $input);
        if ($match && $s > (int) $lastStep) {
            $ok = true;
            $usedStep = $s;
        }
    }
    return $ok;
}

/** otpauth:// link for the QR code. */
function mikhmon_totp_uri($username, $secretB32)
{
    $issuer = 'Mikhmon';
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $username)
        . '?secret=' . $secretB32 . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}

/** <img> tag with the enrolment QR code (inline PNG, drawn locally). */
function mikhmon_totp_qr_img($username, $secretB32)
{
    $png = mikhmon_qr_png(mikhmon_totp_uri($username, $secretB32), 'M', 5, 4);
    if ($png === false) {
        return '';
    }
    return '<img alt="QR code for your authenticator app" width="220" height="220" '
        . 'style="background:#fff;border:1px solid var(--border,#ccc);border-radius:8px;image-rendering:pixelated;" '
        . 'src="data:image/png;base64,' . base64_encode($png) . '">';
}

/** Show the secret in groups of four so it can be typed by hand. */
function mikhmon_totp_pretty($secretB32)
{
    return trim(chunk_split($secretB32, 4, ' '));
}

// ---------------------------------------------------------------------------
// Per-account state
// ---------------------------------------------------------------------------

function mikhmon_2fa_enabled($user)
{
    return is_array($user) && !empty($user['totp_enabled']) && !empty($user['totp_secret']);
}

/** The admin role may not sign in without it. */
function mikhmon_2fa_required($user)
{
    return is_array($user) && isset($user['role']) && $user['role'] === 'admin';
}

/** Turn it on for an account (after the user proved they can generate a code). */
function mikhmon_2fa_enable($userId, $secretB32, $step)
{
    $db = mikhmon_db();
    if (!$db) {
        return false;
    }
    $st = $db->prepare('UPDATE users SET totp_secret = ?, totp_enabled = 1, totp_last_step = ?, totp_fails = 0, totp_locked_until = 0, updated_at = ? WHERE id = ?');
    $st->execute(array(mikhmon_encrypt($secretB32), (int) $step, time(), (int) $userId));
    return true;
}

/** Turn it off and forget the secret and recovery codes (the user must enrol again to use it). */
function mikhmon_2fa_disable($userId)
{
    $db = mikhmon_db();
    if (!$db) {
        return false;
    }
    $st = $db->prepare('UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_last_step = 0, totp_fails = 0, totp_locked_until = 0, updated_at = ? WHERE id = ?');
    $st->execute(array(time(), (int) $userId));
    $db->prepare('DELETE FROM recovery_codes WHERE user_id = ?')->execute(array((int) $userId));
    return true;
}

function mikhmon_recovery_hash($code)
{
    $key = mikhmon_secret_key();
    return hash_hmac('sha256', 'recovery|' . $code, $key === false ? 'mikhmon' : $key);
}

function mikhmon_recovery_normalize($text)
{
    return strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string) $text));
}

/** Replace all recovery codes; returns the new codes in plain text (shown to the user once). */
function mikhmon_recovery_regenerate($userId)
{
    $db = mikhmon_db();
    if (!$db) {
        return array();
    }
    $codes = array();
    $db->beginTransaction();
    $db->prepare('DELETE FROM recovery_codes WHERE user_id = ?')->execute(array((int) $userId));
    $ins = $db->prepare('INSERT INTO recovery_codes (user_id, code_hash) VALUES (?, ?)');
    for ($i = 0; $i < MIKHMON_RECOVERY_CODES; $i++) {
        $hex = bin2hex(random_bytes(6));            // 48 bits
        $codes[] = substr($hex, 0, 6) . '-' . substr($hex, 6);
        $ins->execute(array((int) $userId, mikhmon_recovery_hash($hex)));
    }
    $db->commit();
    return $codes;
}

function mikhmon_recovery_remaining($userId)
{
    $db = mikhmon_db();
    if (!$db) {
        return 0;
    }
    $st = $db->prepare('SELECT COUNT(*) FROM recovery_codes WHERE user_id = ? AND used_at IS NULL');
    $st->execute(array((int) $userId));
    return (int) $st->fetchColumn();
}

/** Use up a recovery code. True only the first time a valid code is presented. */
function mikhmon_recovery_consume($userId, $input)
{
    $db = mikhmon_db();
    $hex = mikhmon_recovery_normalize($input);
    if (!$db || strlen($hex) !== 12) {
        return false;
    }
    $st = $db->prepare('UPDATE recovery_codes SET used_at = ? WHERE user_id = ? AND code_hash = ? AND used_at IS NULL');
    $st->execute(array(time(), (int) $userId, mikhmon_recovery_hash($hex)));
    return $st->rowCount() === 1;
}

/**
 * Check the second factor for an account that has it enabled.
 *
 * @param array  $user           users row
 * @param string $input          what was typed: a 6-digit code, or (if allowed) a recovery code
 * @param bool   $allowRecovery  accept a recovery code too
 * @param string $method         set to 'totp' or 'recovery' on success
 * @return string '' on success, otherwise a message for the user
 */
function mikhmon_2fa_check($user, $input, $allowRecovery, &$method)
{
    $method = '';
    $db = mikhmon_db();
    if (!$db || !mikhmon_2fa_enabled($user)) {
        return 'Two-factor authentication is not set up for this account.';
    }
    $fresh = mikhmon_user_by_id($user['id']);
    if (!$fresh) {
        return 'Account not found.';
    }
    if ((int) $fresh['totp_locked_until'] > time()) {
        return 'Too many wrong codes on this account. Try again in ' . max(1, (int) ceil(((int) $fresh['totp_locked_until'] - time()) / 60)) . ' minute(s).';
    }

    $ok = false;
    $typed = preg_replace('/\s+/', '', (string) $input);
    if (preg_match('/^\d{6}$/', $typed) === 1) {
        $step = 0;
        if (mikhmon_totp_verify(mikhmon_decrypt($fresh['totp_secret']), $typed, $fresh['totp_last_step'], $step)) {
            // compare-and-set so the same code cannot be used by two requests at once
            $st = $db->prepare('UPDATE users SET totp_last_step = ? WHERE id = ? AND totp_last_step < ?');
            $st->execute(array($step, (int) $fresh['id'], $step));
            $ok = $st->rowCount() === 1;
            $method = 'totp';
        }
    } elseif ($allowRecovery && mikhmon_recovery_consume($fresh['id'], $typed)) {
        $ok = true;
        $method = 'recovery';
    }

    if ($ok) {
        $db->prepare('UPDATE users SET totp_fails = 0, totp_locked_until = 0 WHERE id = ?')->execute(array((int) $fresh['id']));
        return '';
    }
    $method = '';
    $fails = (int) $fresh['totp_fails'] + 1;
    $lock = $fails >= MIKHMON_2FA_LOCK_AFTER ? time() + MIKHMON_2FA_LOCK_SECONDS : 0;
    $db->prepare('UPDATE users SET totp_fails = ?, totp_locked_until = ? WHERE id = ?')
        ->execute(array($lock ? 0 : $fails, $lock, (int) $fresh['id']));
    return 'That code is not valid. Check the time on your phone and try again.';
}

// ---------------------------------------------------------------------------
// Sign-in: the half-finished state between password and code
// ---------------------------------------------------------------------------

/**
 * Called after a correct password. Sets up the pending state and returns true when a second
 * step is needed ('verify' for enrolled accounts, 'enroll' for admins who have no 2FA yet).
 */
function mikhmon_2fa_begin_login($user)
{
    if (empty($user['id']) || !mikhmon_db()) {
        return false; // single legacy login without SQLite cannot store it
    }
    if (mikhmon_2fa_enabled($user)) {
        $mode = 'verify';
    } elseif (mikhmon_2fa_required($user)) {
        $mode = 'enroll';
    } else {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['mikhmon_2fa'] = array(
        'uid' => (int) $user['id'], 'mode' => $mode, 'exp' => time() + MIKHMON_2FA_PENDING_SECONDS,
        'tries' => 0, 'secret' => '', 'self' => false,
    );
    return true;
}

/** A signed-in user without 2FA chooses to turn it on (My account). */
function mikhmon_2fa_begin_self_enrol($userId)
{
    $_SESSION['mikhmon_2fa'] = array(
        'uid' => (int) $userId, 'mode' => 'enroll', 'exp' => time() + MIKHMON_2FA_PENDING_SECONDS,
        'tries' => 0, 'secret' => '', 'self' => true,
    );
}

}
