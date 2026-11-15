<?php
/*
 *  Blocked content: what each profile blocks, the custom domain list, and how it works.
 *  Included by index.php (?hotspot=blocking). Editing the custom list needs the admin role
 *  (enforced in include/security.php).
 */
if (!isset($_SESSION["mikhmon"]) || !isset($API)) {
  header("Location:../admin.php?id=login");
  exit;
}

$bcMsg = '';
$bcErr = '';
if (isset($_POST['custom_hosts']) && mikhmon_can('admin')) {
  list($saved, $bad) = mikhmon_block_save_custom($API, mikhmon_input($_POST, 'custom_hosts'));
  $bcMsg = 'Custom list saved (' . count($saved) . ' patterns).';
  if ($bad) {
    $bcErr = 'Ignored, not a domain name: ' . implode(' ', array_slice($bad, 0, 20));
  }
  mikhmon_audit('blocking.custom-list', '', count($saved) . ' patterns' . ($bad ? ', ' . count($bad) . ' ignored' : ''));
}

$bcCatalog = mikhmon_block_catalog();
$bcRules = mikhmon_block_rules($API);
$bcProfiles = mikhmon_block_profiles($API, $bcRules);
$bcCustom = mikhmon_block_custom_hosts($API, $bcRules);
$bcFast = mikhmon_block_fasttrack_active($API);

// hits per category chain (packets rejected) - shows whether a list is actually catching anything
$bcHits = array();
foreach ($bcRules as $r) {
  if (preg_match('/^' . MIKHMON_BLOCK_PREFIX . '-(\w+)$/', $r['chain'], $m)) {
    $bcHits[$m[1]] = (isset($bcHits[$m[1]]) ? $bcHits[$m[1]] : 0) + (int) (isset($r['packets']) ? $r['packets'] : 0);
  }
}
// show the custom list without the automatic *. twins
$bcCustomShow = array();
foreach ($bcCustom as $h) {
  if (strpos($h, '*.') === 0 && in_array(substr($h, 2), $bcCustom, true)) {
    continue;
  }
  $bcCustomShow[] = $h;
}
?>
<div class="row">
<div class="col-8">
<div class="card">
  <div class="card-header"><h3><i class="fa fa-ban"></i> Blocked content</h3></div>
  <div class="card-body">
    <?php if ($bcMsg !== '') { ?><div class="alert bg-success" role="alert" style="margin-bottom:10px;"><i class="fa fa-check"></i> <?= esc($bcMsg) ?></div><?php } ?>
    <?php if ($bcErr !== '') { ?><div class="alert bg-warning" role="alert" style="margin-bottom:10px;"><i class="fa fa-warning"></i> <?= esc($bcErr) ?></div><?php } ?>
    <?php if ($bcFast) { ?>
      <div class="alert bg-warning" role="alert" style="margin-bottom:10px;"><i class="fa fa-warning"></i>
        This router has a <b>fasttrack</b> rule. Fasttracked connections skip the firewall, so blocking may miss some of them.
        If blocking does not work, disable that rule (IP &rarr; Firewall &rarr; Filter Rules) or limit it to non-hotspot traffic.</div>
    <?php } ?>

    <p style="margin-top:0;">Choose what to block in each <a href="./?hotspot=user-profiles&amp;session=<?= esc(rawurlencode($session)) ?>">user profile</a>
      (edit a profile &rarr; <i>Blocked content</i>). It applies to every user logged in with that profile.</p>

    <div style="overflow-x:auto;">
    <table class="table table-bordered table-hover">
      <thead><tr><th>Profile</th><th>Blocked</th></tr></thead>
      <tbody>
      <?php if (!$bcProfiles) { ?>
        <tr><td colspan="2" class="muted">No profile blocks anything yet.</td></tr>
      <?php } ?>
      <?php foreach ($bcProfiles as $p => $cats) { ?>
        <tr><td><?= esc($p) ?></td><td><?php foreach ($cats as $c) { echo '<span style="margin-right:10px;white-space:nowrap;"><i class="fa ' . esc(isset($bcCatalog[$c]) ? $bcCatalog[$c]['icon'] : 'fa-ban') . '"></i> ' . esc(isset($bcCatalog[$c]) ? $bcCatalog[$c]['label'] : $c) . '</span>'; } ?></td></tr>
      <?php } ?>
      </tbody>
    </table>
    </div>

    <h3 style="margin-top:18px;"><i class="fa fa-list"></i> Lists</h3>
    <div style="overflow-x:auto;">
    <table class="table table-bordered">
      <thead><tr><th>Category</th><th>Blocked so far</th><th>Server names</th></tr></thead>
      <tbody>
      <?php foreach ($bcCatalog as $k => $c) {
        $hosts = $k === 'custom' ? $bcCustomShow : $c['hosts']; ?>
        <tr>
          <td style="white-space:nowrap;"><i class="fa <?= esc($c['icon']) ?>"></i> <?= esc($c['label']) ?></td>
          <td style="text-align:right;"><?= isset($bcHits[$k]) ? (int) $bcHits[$k] . ' packets' : '<span class="muted">-</span>' ?></td>
          <td style="font-family:var(--mono);font-size:12px;"><?= $hosts ? esc(implode('  ', $hosts)) : '<span class="muted">(empty)</span>' ?></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
    </div>

    <?php if (mikhmon_can('admin')) { ?>
    <h3 style="margin-top:18px;"><i class="fa fa-pencil"></i> Custom list</h3>
    <form method="post" action="./?hotspot=blocking&amp;session=<?= esc(rawurlencode($session)) ?>"><?= csrf_field() ?>
      <textarea class="form-control" name="custom_hosts" rows="6" style="font-family:var(--mono);" placeholder="one domain per line, e.g.&#10;roblox.com&#10;garena.com"><?= esc(implode("\n", $bcCustomShow)) ?></textarea>
      <p class="muted" style="font-size:12px;">A domain also blocks all its sub-domains (roblox.com blocks www.roblox.com). Up to 400 entries. Used by profiles that tick <i>Custom list</i>.</p>
      <button type="submit" class="btn bg-primary"><i class="fa fa-save"></i> Save custom list</button>
    </form>
    <?php } ?>
  </div>
</div>
</div>

<div class="col-4">
<div class="card">
  <div class="card-header"><h3><i class="fa fa-book"></i> How it works</h3></div>
  <div class="card-body">
    <p style="margin-top:0;">Apps send the name of the server they connect to (for example <i>googlevideo.com</i>) when they open a secure connection.
      The router rejects connections to blocked names for users of the chosen profiles. Nothing is decrypted and DNS is not changed.</p>
    <p>QUIC (UDP 443) is also blocked for those users, so apps fall back to connections where the name is visible.</p>
    <p><b>Good to know</b></p>
    <ul style="padding-left:18px;">
      <li>Lists are a best effort. Apps and games change their servers; if something still works, find its name in <i>IP &rarr; DNS &rarr; Cache</i> on the router and add it to the custom list.</li>
      <li>Connections already open keep working until they close (log the user out to cut them).</li>
      <li>Games may keep playing an ongoing match; blocking stops the login and matchmaking.</li>
      <li>Rules are named <i>mikhmon-block...</i> in IP &rarr; Firewall; do not edit them in Winbox.</li>
    </ul>
  </div>
</div>
</div>
</div>
