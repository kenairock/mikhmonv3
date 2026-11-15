<?php
/*
 *  "Disconnect the oldest device" for hotspot user profiles.
 *
 *  RouterOS on its own refuses a login once a user already has `shared-users` sessions. With this
 *  option the profile is saved with shared-users = limit + 1, and a block in the profile's on-login
 *  script cuts the account back to `limit` sessions right after each login by disconnecting the
 *  session with the longest uptime (and deleting its login cookie, so that device does not log
 *  straight back in and push someone else out).
 *
 *  The block is self-describing ({:local mkdev N; ... :local mkend 1}), so Mikhmon can read the
 *  real limit back from the router and remove the block again.
 */
if (basename($_SERVER['SCRIPT_FILENAME']) == 'devicelimit.php') {
    header("Location:../");
    exit;
}

if (!function_exists('mikhmon_kick_block')) {

/** RouterOS script block that keeps at most $limit sessions per user, dropping the oldest. */
function mikhmon_kick_block($limit)
{
    $limit = max(1, (int) $limit);
    return '{:local mkdev ' . $limit . '; :local mkn 0; :local mkids [/ip hotspot active find where user=$user];'
        . ' :while ([:len $mkids] > $mkdev and $mkn < 10) do={'
        . ' :local mkold ""; :local mkup 0s; :local mkfound false;'
        . ' :foreach a in=$mkids do={ :local u [/ip hotspot active get $a uptime];'
        . ' :if ([/ip hotspot active get $a mac-address] != $"mac-address" and (!$mkfound or $u > $mkup)) do={ :set mkold $a; :set mkup $u; :set mkfound true } };'
        . ' :if ($mkfound) do={'
        . ' :local om [/ip hotspot active get $mkold mac-address]; :local oa [/ip hotspot active get $mkold address];'
        . ' /ip hotspot active remove $mkold;'
        . ' :do { /ip hotspot cookie remove [find where user=$user and mac-address=$om] } on-error={};'
        . ' :log info ("mikhmon: " . $user . " is limited to " . $mkdev . " devices, disconnected the oldest (" . $om . " " . $oa . ")");'
        . ' :set mkn ($mkn + 1) } else={ :set mkn 10 };'
        . ' :set mkids [/ip hotspot active find where user=$user] }; :local mkend 1}';
}

/** Remove a previously added block (and the separator before it). */
function mikhmon_kick_strip($onlogin)
{
    return (string) preg_replace('/(;\s*)?\{:local mkdev \d+;.*?:local mkend 1\}/s', '', (string) $onlogin);
}

/** Insert the block right after the ":put (...)" header that Mikhmon reads its settings from. */
function mikhmon_kick_add($onlogin, $limit)
{
    $onlogin = mikhmon_kick_strip($onlogin);
    $block = mikhmon_kick_block($limit);
    if (preg_match('/^:put \([^)]*\)/', $onlogin, $m)) {
        return $m[0] . '; ' . $block . substr($onlogin, strlen($m[0]));
    }
    return $onlogin === '' ? $block : $block . '; ' . $onlogin;
}

/**
 * Read a profile as Mikhmon shows it.
 * Returns array('limit' => devices per user as the operator set it, 'kick' => bool).
 */
function mikhmon_profile_devices($sharedUsers, $onlogin)
{
    if (preg_match('/\{:local mkdev (\d+);/', (string) $onlogin, $m)) {
        return array('limit' => (int) $m[1], 'kick' => true);
    }
    return array('limit' => (string) $sharedUsers, 'kick' => false);
}

/**
 * Apply the form choice. $sharedUsers is what the operator typed, $kick the "when full" choice.
 * Returns array(shared-users value for the router, on-login script).
 */
function mikhmon_profile_apply_devices($sharedUsers, $kick, $onlogin)
{
    $n = trim((string) $sharedUsers);
    if ($kick && ctype_digit($n) && (int) $n >= 1) {
        return array((string) ((int) $n + 1), mikhmon_kick_add($onlogin, (int) $n));
    }
    return array($n, mikhmon_kick_strip($onlogin));
}

}
