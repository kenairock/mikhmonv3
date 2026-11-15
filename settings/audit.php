<?php
/*
 *  Login history and audit log viewer (admin role only; enforced in include/security.php).
 */
if (!isset($_SESSION["mikhmon"]) || !function_exists('mikhmon_can') || !mikhmon_can('admin')) {
  header("Location:./admin.php?id=login");
  exit;
}

$logtab = mikhmon_input($_GET, 'log') === 'audit' ? 'audit' : 'login';
$filters = array(
  'user' => mikhmon_input($_GET, 'user'),
  'action' => mikhmon_input($_GET, 'action'),
  'success' => mikhmon_input($_GET, 'success'),
  'from' => mikhmon_input($_GET, 'from'),
  'to' => mikhmon_input($_GET, 'to'),
);
$logpage = max(1, (int) mikhmon_input($_GET, 'page', '1'));
$perpage = 50;
list($logrows, $logtotal) = mikhmon_log_query($logtab === 'audit' ? 'audit_log' : 'login_log', $filters, $logpage, $perpage);
$pages = max(1, (int) ceil($logtotal / $perpage));

$qs = function ($over) use ($logtab, $filters, $logpage) {
  $q = array_merge(array('id' => 'audit', 'log' => $logtab, 'page' => $logpage), array_filter($filters, 'strlen'), $over);
  return './admin.php?' . http_build_query($q);
};
$fmt = function ($ts) {
  return date('Y-m-d H:i:s', (int) $ts);
};
?>
<div class="row">
<div class="col-12">
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-history"></i> History &nbsp; | &nbsp;
      <a href="<?= esc($qs(array('log' => 'login', 'page' => 1, 'action' => null))) ?>" <?= $logtab === 'login' ? 'style="font-weight:bold;"' : '' ?>>Logins</a> &nbsp; | &nbsp;
      <a href="<?= esc($qs(array('log' => 'audit', 'page' => 1, 'success' => null))) ?>" <?= $logtab === 'audit' ? 'style="font-weight:bold;"' : '' ?>>Audit log</a> &nbsp; | &nbsp;
      <a href="./admin.php?id=accounts"><i class="fa fa-users"></i> Accounts</a></h3>
  </div>
  <div class="card-body">
  <?php if (mikhmon_db() === null) { ?>
    <div class="alert bg-danger" role="alert" style="margin-bottom:10px;"><i class="fa fa-warning"></i> Database not available (enable pdo_sqlite); nothing is being recorded.</div>
  <?php } else { ?>
    <form method="get" action="./admin.php" style="margin-bottom:10px;">
      <input type="hidden" name="id" value="audit">
      <input type="hidden" name="log" value="<?= esc($logtab) ?>">
      <input class="form-control" style="width:160px; display:inline-block;" type="text" name="user" placeholder="user" value="<?= esc($filters['user']) ?>">
      <?php if ($logtab === 'audit') { ?>
        <input class="form-control" style="width:200px; display:inline-block;" type="text" name="action" placeholder="action (e.g. remove, account)" value="<?= esc($filters['action']) ?>">
      <?php } else { ?>
        <select class="form-control" style="width:130px; display:inline-block;" name="success">
          <option value="">all results</option>
          <option value="1" <?= $filters['success'] === '1' ? 'selected' : '' ?>>success</option>
          <option value="0" <?= $filters['success'] === '0' ? 'selected' : '' ?>>failed</option>
        </select>
      <?php } ?>
      <input class="form-control" style="width:150px; display:inline-block;" type="date" name="from" value="<?= esc($filters['from']) ?>" title="from">
      <input class="form-control" style="width:150px; display:inline-block;" type="date" name="to" value="<?= esc($filters['to']) ?>" title="to">
      <button class="btn bg-primary" type="submit"><i class="fa fa-search"></i> Filter</button>
      <a class="btn" href="./admin.php?id=audit&amp;log=<?= esc($logtab) ?>">Reset</a>
      <span style="margin-left:10px;"><?= (int) $logtotal ?> entries</span>
    </form>

    <div style="overflow-x:auto;">
    <table class="table table-bordered table-hover" style="table-layout:auto;">
    <?php if ($logtab === 'login') { ?>
      <thead><tr><th>Time</th><th>User</th><th>Result</th><th>Reason</th><th>IP</th><th>Browser</th></tr></thead>
      <tbody>
      <?php foreach ($logrows as $r) { ?>
        <tr>
          <td style="white-space:nowrap;"><?= esc($fmt($r['ts'])) ?></td>
          <td><?= esc($r['username']) ?></td>
          <td><?= (int) $r['success'] === 1 ? '<span class="text-green">success</span>' : '<span class="text-red">failed</span>' ?></td>
          <td><?= esc($r['reason']) ?></td>
          <td><?= esc($r['ip']) ?></td>
          <td title="<?= esc($r['user_agent']) ?>"><?= esc(substr((string) $r['user_agent'], 0, 60)) ?></td>
        </tr>
      <?php } ?>
      </tbody>
    <?php } else { ?>
      <thead><tr><th>Time</th><th>User</th><th>Role</th><th>Router</th><th>Action</th><th>Target</th><th>Details</th><th>Result</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($logrows as $r) { ?>
        <tr>
          <td style="white-space:nowrap;"><?= esc($fmt($r['ts'])) ?></td>
          <td><?= esc($r['username']) ?></td>
          <td><?= esc($r['role']) ?></td>
          <td><?= esc($r['router']) ?></td>
          <td style="min-width:170px; overflow-wrap:anywhere;"><?= esc($r['action']) ?></td>
          <td style="word-break:break-word;"><?= esc($r['target']) ?></td>
          <td style="white-space:normal; min-width:200px; max-width:360px; word-break:break-word;"><?= esc($r['details']) ?></td>
          <td><?= $r['result'] === 'ok' ? '<span class="text-green">ok</span>' : '<span class="text-red">' . esc($r['result']) . '</span>' ?></td>
          <td><?= esc($r['ip']) ?></td>
        </tr>
      <?php } ?>
      </tbody>
    <?php } ?>
    </table>
    </div>

    <?php if ($pages > 1) { ?>
    <div>
      <?php if ($logpage > 1) { ?><a class="btn" href="<?= esc($qs(array('page' => $logpage - 1))) ?>">&laquo; Newer</a><?php } ?>
      <span>Page <?= (int) $logpage ?> / <?= (int) $pages ?></span>
      <?php if ($logpage < $pages) { ?><a class="btn" href="<?= esc($qs(array('page' => $logpage + 1))) ?>">Older &raquo;</a><?php } ?>
    </div>
    <?php } ?>
    <p class="text-muted" style="margin-top:10px;">Entries older than <?= (int) MIKHMON_LOG_RETENTION_DAYS ?> days are removed automatically. Passwords and template contents are never logged.</p>
  <?php } ?>
  </div>
</div>
</div>
</div>
