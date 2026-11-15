<?php
/*
 *  Emergency tool: turn off two-factor authentication for one account from the command line.
 *
 *  For the case where an admin lost both the phone and the recovery codes, and no other admin can
 *  use Accounts -> reset. The account must set 2FA up again at its next sign-in (admins) or may
 *  turn it on under My account (other roles).
 *
 *    php deploy/reset-2fa.php <username>
 *
 *  It must see the same database as the web site. If the site was given MIKHMON_DB, set it here too:
 *    set MIKHMON_DB=E:\Applications\nginx-1.30.6\php-data\mikhmon.sqlite      (Windows)
 *    MIKHMON_DB=/var/lib/mikhmon/mikhmon.sqlite php deploy/reset-2fa.php name  (Linux)
 *  and MIKHMON_SECRET_FILE likewise if it was set. Needs the pdo_sqlite extension.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if ($argc !== 2 || $argv[1] === '' || $argv[1][0] === '-') {
    fwrite(STDERR, "usage: php deploy/reset-2fa.php <username>\n");
    exit(2);
}

$_SERVER['SCRIPT_FILENAME'] = __FILE__;
require_once __DIR__ . '/../include/security.php';

$db = mikhmon_db();
if (!$db) {
    fwrite(STDERR, "Cannot open the account database (" . mikhmon_db_path() . "). Is pdo_sqlite enabled?\n");
    exit(1);
}
$user = mikhmon_user_by_name($argv[1]);
if (!$user) {
    fwrite(STDERR, "No account named '" . $argv[1] . "' in " . mikhmon_db_path() . "\n");
    exit(1);
}
mikhmon_2fa_disable($user['id']);
mikhmon_audit_write($db, 'cli', '', 'account.2fa-reset', $user['username'], 'from the command line', 'ok');
echo "Two-factor authentication reset for " . $user['username'] . ".\n";
echo mikhmon_2fa_required($user) ? "They must set it up again at the next sign-in.\n" : "They can turn it on again under My account.\n";
