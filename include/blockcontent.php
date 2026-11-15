<?php
/*
 *  Blocked content per hotspot user profile (YouTube, TikTok, online games ...).
 *
 *  How it is built on the router (RouterOS firewall, no DNS changes):
 *
 *    - The profile gets  address-list = mikhmon-block-<profile>, so RouterOS puts every logged-in
 *      user of that profile into that list.
 *    - One chain per category, e.g. "mikhmon-block-youtube", holds rules that reject TLS connections
 *      by server name (tls-host=*.googlevideo.com ...). HTTPS apps send that name in clear text when
 *      they connect, so this works without decrypting anything and without touching DNS.
 *    - "mikhmon-block-quic" drops UDP 443 (QUIC hides the name from tls-host; apps fall back to TCP).
 *    - At the top of the forward chain, jump rules send traffic from mikhmon-block-<profile> into the
 *      chosen category chains. Comments ("mikhmon-block profile=<p> cat=<c>") are the only state:
 *      Mikhmon reads its settings back from the router.
 *
 *  Limits (shown on the Blocked content page): a domain list is a best effort and game/app owners
 *  change domains; connections that are already open stay open until they close; a fasttrack rule
 *  above these rules would let traffic skip them.
 */
if (basename($_SERVER['SCRIPT_FILENAME']) == 'blockcontent.php') {
    header("Location:../");
    exit;
}

if (!function_exists('mikhmon_block_catalog')) {

define('MIKHMON_BLOCK_PREFIX', 'mikhmon-block');

/** Categories and the server names that identify them (tls-host patterns; * matches any prefix). */
function mikhmon_block_catalog()
{
    return array(
        'youtube' => array('label' => 'YouTube', 'icon' => 'fa-youtube-play', 'hosts' => array(
            'youtube.com', '*.youtube.com', 'youtu.be', '*.googlevideo.com', '*.ytimg.com', 'youtubei.googleapis.com',
            'youtube.googleapis.com', 'youtube-nocookie.com', '*.youtube-nocookie.com', 'm.youtube.com')),
        'tiktok' => array('label' => 'TikTok', 'icon' => 'fa-music', 'hosts' => array(
            'tiktok.com', '*.tiktok.com', '*.tiktokv.com', '*.tiktokcdn.com', '*.tiktokcdn-us.com', '*.byteoversea.com',
            '*.ibytedtos.com', '*.ibyteimg.com', '*.muscdn.com', '*.musical.ly')),
        'mlbb' => array('label' => 'Mobile Legends', 'icon' => 'fa-gamepad', 'hosts' => array(
            'mobilelegends.com', '*.mobilelegends.com', 'moonton.com', '*.moonton.com', '*.youngjoygame.com')),
        'wildrift' => array('label' => 'Wild Rift / Riot Games', 'icon' => 'fa-gamepad', 'hosts' => array(
            'riotgames.com', '*.riotgames.com', 'leagueoflegends.com', '*.leagueoflegends.com', '*.riotcdn.net', '*.pvp.net')),
        'facebook' => array('label' => 'Facebook / Messenger', 'icon' => 'fa-facebook', 'hosts' => array(
            'facebook.com', '*.facebook.com', '*.fbcdn.net', 'messenger.com', '*.messenger.com', 'fb.com', '*.fb.com', '*.facebook.net')),
        'custom' => array('label' => 'Custom list', 'icon' => 'fa-list', 'hosts' => null), // edited on the Blocked content page
    );
}

function mikhmon_block_chain($cat)
{
    return MIKHMON_BLOCK_PREFIX . '-' . $cat;
}

function mikhmon_block_list_name($profile)
{
    return MIKHMON_BLOCK_PREFIX . '-' . $profile;
}

/** Normalise one typed domain into a tls-host pattern, or '' when it is not a plausible host name. */
function mikhmon_block_clean_host($host)
{
    $h = strtolower(trim((string) $host));
    $h = preg_replace('#^[a-z]+://#', '', $h);   // pasted URL
    $h = preg_replace('#[/:].*$#', '', $h);       // path or port
    $h = rtrim($h, '.');
    if (strpos($h, '.') === 0) {
        $h = '*' . $h;                            // ".example.com" -> "*.example.com"
    }
    return preg_match('/^(\*\.)?([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{1,62}$/', $h) ? $h : '';
}

/** Turn a list into the patterns to use: example.com also gets *.example.com. */
function mikhmon_block_expand($hosts)
{
    $out = array();
    foreach ($hosts as $h) {
        $h = mikhmon_block_clean_host($h);
        if ($h === '') {
            continue;
        }
        $out[$h] = true;
        if (strpos($h, '*.') !== 0) {
            $out['*.' . $h] = true;
        }
    }
    return array_keys($out);
}

/** All mikhmon-block filter rules on the router. */
function mikhmon_block_rules($API)
{
    $rules = $API->comm('/ip/firewall/filter/print');
    $out = array();
    foreach ((array) $rules as $r) {
        if (isset($r['comment']) && strpos($r['comment'], MIKHMON_BLOCK_PREFIX) === 0) {
            $out[] = $r;
        }
    }
    return $out;
}

/** Custom domains currently on the router. */
function mikhmon_block_custom_hosts($API, $rules = null)
{
    $rules = $rules === null ? mikhmon_block_rules($API) : $rules;
    $hosts = array();
    foreach ($rules as $r) {
        if ($r['chain'] === mikhmon_block_chain('custom') && !empty($r['tls-host'])) {
            $hosts[] = $r['tls-host'];
        }
    }
    return $hosts;
}

/** Categories blocked per profile: array(profile => array(cat, ...)). */
function mikhmon_block_profiles($API, $rules = null)
{
    $rules = $rules === null ? mikhmon_block_rules($API) : $rules;
    $map = array();
    foreach ($rules as $r) {
        if (preg_match('/^' . MIKHMON_BLOCK_PREFIX . ' profile=(\S+) cat=(\S+)$/', $r['comment'], $m) && $m[2] !== 'quic') {
            $map[$m[1]][] = $m[2];
        }
    }
    return $map;
}

/** Make a category chain hold exactly $hosts (rebuilt only when it differs). */
function mikhmon_block_sync_chain($API, $chain, $hosts, $rules)
{
    $have = array();
    $ids = array();
    foreach ($rules as $r) {
        if ($r['chain'] === $chain) {
            $ids[] = $r['.id'];
            if (!empty($r['tls-host'])) {
                $have[] = $r['tls-host'];
            }
        }
    }
    $want = $hosts;
    sort($have);
    sort($want);
    if ($have === $want && count($ids) === count($want)) {
        return;
    }
    if ($ids) {
        $API->comm('/ip/firewall/filter/remove', array('.id' => implode(',', $ids)));
    }
    foreach ($hosts as $h) {
        $API->comm('/ip/firewall/filter/add', array(
            'chain' => $chain, 'protocol' => 'tcp', 'tls-host' => $h,
            'action' => 'reject', 'reject-with' => 'tcp-reset',
            'comment' => MIKHMON_BLOCK_PREFIX . ' host',
        ));
    }
}

function mikhmon_block_sync_quic($API, $rules)
{
    foreach ($rules as $r) {
        if ($r['chain'] === mikhmon_block_chain('quic')) {
            return;
        }
    }
    $API->comm('/ip/firewall/filter/add', array(
        'chain' => mikhmon_block_chain('quic'), 'protocol' => 'udp', 'dst-port' => '443', 'action' => 'drop',
        'comment' => MIKHMON_BLOCK_PREFIX . ' quic (forces apps back to TCP, where the server name is visible)',
    ));
}

/** First rule of the forward chain, so jump rules can be placed above everything else. */
function mikhmon_block_first_forward($API)
{
    $rules = $API->comm('/ip/firewall/filter/print', array('?chain' => 'forward'));
    foreach ((array) $rules as $r) {
        if (isset($r['.id']) && (!isset($r['dynamic']) || $r['dynamic'] !== 'true')) {
            return $r['.id'];
        }
    }
    return '';
}

/**
 * Apply the categories chosen for one profile. $oldName is the profile's previous name when it was
 * renamed. Returns '' or a warning to show.
 */
function mikhmon_block_apply_profile($API, $profile, $cats, $oldName = '')
{
    $catalog = mikhmon_block_catalog();
    $cats = array_values(array_intersect(array_keys($catalog), (array) $cats));
    $rules = mikhmon_block_rules($API);

    // drop this profile's jump rules (under the old and the new name), then add what is wanted now
    $remove = array();
    foreach ($rules as $r) {
        if (preg_match('/^' . MIKHMON_BLOCK_PREFIX . ' profile=(\S+) cat=/', $r['comment'], $m)
            && ($m[1] === $profile || ($oldName !== '' && $m[1] === $oldName))) {
            $remove[] = $r['.id'];
        }
    }
    if ($remove) {
        $API->comm('/ip/firewall/filter/remove', array('.id' => implode(',', $remove)));
    }

    // the profile's address list: set ours, but never overwrite one the admin uses for something else
    $warning = '';
    $prof = $API->comm('/ip/hotspot/user/profile/print', array('?name' => $profile));
    $pid = isset($prof[0]['.id']) ? $prof[0]['.id'] : '';
    $current = isset($prof[0]['address-list']) ? (string) $prof[0]['address-list'] : '';
    $ours = $current === '' || strpos($current, MIKHMON_BLOCK_PREFIX . '-') === 0;
    if ($pid !== '' && $ours) {
        $API->comm('/ip/hotspot/user/profile/set', array('.id' => $pid, 'address-list' => $cats ? mikhmon_block_list_name($profile) : ''));
    } elseif ($cats) {
        $warning = 'The profile already uses the address list "' . $current . '", so blocked content cannot be applied to it. Clear that address list in Winbox first.';
        $cats = array();
    }
    if (!$cats) {
        return $warning;
    }

    $rules = mikhmon_block_rules($API);
    foreach ($cats as $c) {
        $hosts = $c === 'custom' ? mikhmon_block_custom_hosts($API, $rules) : mikhmon_block_expand($catalog[$c]['hosts']);
        if ($c !== 'custom') {
            mikhmon_block_sync_chain($API, mikhmon_block_chain($c), $hosts, $rules);
        }
    }
    mikhmon_block_sync_quic($API, $rules);

    $list = mikhmon_block_list_name($profile);
    foreach (array_merge(array('quic'), $cats) as $c) {
        $args = array(
            'chain' => 'forward', 'src-address-list' => $list, 'action' => 'jump', 'jump-target' => mikhmon_block_chain($c),
            'comment' => MIKHMON_BLOCK_PREFIX . ' profile=' . $profile . ' cat=' . $c,
        );
        $first = mikhmon_block_first_forward($API);
        if ($first !== '') {
            $args['place-before'] = $first;
        }
        $API->comm('/ip/firewall/filter/add', $args);
    }
    if (in_array('custom', $cats, true) && !mikhmon_block_custom_hosts($API)) {
        $warning = 'The custom list is empty: add domains on the Blocked content page.';
    }
    return $warning;
}

/** Profile deleted: remove its jump rules. */
function mikhmon_block_forget_profile($API, $profile)
{
    $ids = array();
    foreach (mikhmon_block_rules($API) as $r) {
        if (strpos($r['comment'], MIKHMON_BLOCK_PREFIX . ' profile=' . $profile . ' cat=') === 0) {
            $ids[] = $r['.id'];
        }
    }
    if ($ids) {
        $API->comm('/ip/firewall/filter/remove', array('.id' => implode(',', $ids)));
    }
}

/** Replace the custom list. Returns array(saved patterns, rejected inputs). */
function mikhmon_block_save_custom($API, $text)
{
    $bad = array();
    $raw = preg_split('/[\s,;]+/', (string) $text, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($raw as $h) {
        if (mikhmon_block_clean_host($h) === '') {
            $bad[] = $h;
        }
    }
    $hosts = array_slice(mikhmon_block_expand($raw), 0, 400);
    mikhmon_block_sync_chain($API, mikhmon_block_chain('custom'), $hosts, mikhmon_block_rules($API));
    return array($hosts, $bad);
}

/** True when a fasttrack rule could let connections skip the blocking rules. */
function mikhmon_block_fasttrack_active($API)
{
    foreach ((array) $API->comm('/ip/firewall/filter/print', array('?action' => 'fasttrack-connection')) as $r) {
        if (isset($r['disabled']) && $r['disabled'] !== 'true') {
            return true;
        }
    }
    return false;
}

/** Profile form: the checkbox row. */
function mikhmon_block_form_row($selected)
{
    $html = "  <tr>\n    <td class=\"align-middle\">Blocked content</td><td>\n";
    foreach (mikhmon_block_catalog() as $k => $c) {
        $html .= '      <label style="display:inline-block;margin:2px 12px 2px 0;"><input type="checkbox" name="block[]" value="' . esc($k) . '"'
            . (in_array($k, (array) $selected, true) ? ' checked' : '') . '> <i class="fa ' . esc($c['icon']) . '"></i> ' . esc($c['label']) . "</label>\n";
    }
    $html .= '      <div class="muted" style="font-size:12px;">Applies to every user of this profile. <a href="./?hotspot=blocking&amp;session='
        . esc(rawurlencode(isset($GLOBALS['session']) ? $GLOBALS['session'] : '')) . "\">How it works / custom list</a></div>\n    </td>\n  </tr>\n";
    return $html;
}

/** Profile form: the chosen categories from POST. */
function mikhmon_block_posted()
{
    $v = isset($_POST['block']) && is_array($_POST['block']) ? $_POST['block'] : array();
    return array_values(array_intersect(array_keys(mikhmon_block_catalog()), array_map('strval', $v)));
}

}
