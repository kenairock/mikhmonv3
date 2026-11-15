<?php
/*
 *  Mikhmon admin accounts (admin role only; enforced in include/security.php).
 */
if (!isset($_SESSION["mikhmon"]) || !function_exists('mikhmon_can') || !mikhmon_can('admin')) {
  header("Location:./admin.php?id=login");
  exit;
}

$acctmsg = '';
$accterr = '';
$dbok = mikhmon_db() !== null;
$roledesc = array(
  'admin' => 'Everything: router sessions & settings, logo, voucher templates, reboot/shutdown, accounts, audit log.',
  'operator' => 'Hotspot users, profiles, IP bindings, hosts, cookies, vouchers & quick print, reports. No settings or accounts.',
  'viewer' => 'Read-only: dashboards, lists, logs and reports.',
);

if ($dbok && isset($_POST['acct_action'])) {
  $act = mikhmon_input($_POST, 'acct_action');
  $uid = (int) mikhmon_input($_POST, 'uid');
  $target = $uid > 0 ? mikhmon_user_by_id($uid) : null;
  $tname = $target ? (string) $target['username'] : '';
  $self = $target && (int) $target['id'] === (int) $_SESSION['mikhmon_uid'];

  if ($act === 'create') {
    $nu = mikhmon_input($_POST, 'new_username');
    $np = mikhmon_input($_POST, 'new_password');
    $nr = mikhmon_input($_POST, 'new_role');
    $e = mikhmon_user_create($nu, $np, $nr);
    if ($e === '') {
      $acctmsg = 'Account ' . $nu . ' created.';
      mikhmon_audit('account.create', $nu, 'role=' . $nr);
    } else {
      $accterr = $e;
      mikhmon_audit('account.create', $nu, $e, 'failed');
    }
  } elseif (!$target) {
    $accterr = 'Account not found.';
  } elseif ($act === 'update') {
    $nr = mikhmon_input($_POST, 'role');
    $na = mikhmon_input($_POST, 'active') === '1';
    if ($self && ($nr !== 'admin' || !$na)) {
      $e = 'You cannot remove your own admin access. Ask another admin.';
    } else {
      $e = mikhmon_user_update($target['id'], $nr, $na);
    }
    $detail = 'role ' . $target['role'] . '->' . $nr . ', active ' . (int) $target['active'] . '->' . (int) $na;
    if ($e === '') {
      $acctmsg = 'Account ' . $tname . ' updated.';
      mikhmon_audit('account.update', $tname, $detail);
    } else {
      $accterr = $e;
      mikhmon_audit('account.update', $tname, $detail . ': ' . $e, 'failed');
    }
  } elseif ($act === 'password') {
    $e = mikhmon_user_set_password($target['id'], mikhmon_input($_POST, 'reset_password'));
    if ($e === '') {
      $acctmsg = 'Password for ' . $tname . ' changed.';
      mikhmon_audit('account.password-reset', $tname);
    } else {
      $accterr = $e;
      mikhmon_audit('account.password-reset', $tname, $e, 'failed');
    }
  } elseif ($act === 'delete') {
    $e = $self ? 'You cannot delete your own account.' : mikhmon_user_delete($target['id']);
    if ($e === '') {
      $acctmsg = 'Account ' . $tname . ' deleted.';
      mikhmon_audit('account.delete', $tname, 'role=' . $target['role']);
    } else {
      $accterr = $e;
      mikhmon_audit('account.delete', $tname, $e, 'failed');
    }
  }
}

$accounts = $dbok ? mikhmon_users_list() : array();
$fmt = function ($ts) {
  return $ts ? date('Y-m-d H:i', (int) $ts) : '-';
};
?>
<div class="row">
<div class="col-12">
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-users"></i> Admin Accounts &nbsp; | &nbsp;
      <a href="./admin.php?id=audit"><i class="fa fa-history"></i> Login &amp; audit history</a></h3>
  </div>
  <div class="card-body">
  <?php if (!$dbok) { ?>
    <div class="alert bg-danger" role="alert" style="margin-bottom:10px;"><i class="fa fa-warning"></i>
      The account database is not available. Enable the <b>pdo_sqlite</b> PHP extension; until then only the single login from config.php works.</div>
  <?php } else { ?>
    <?php if ($acctmsg !== '') { ?><div class="alert bg-success" role="alert" style="margin-bottom:10px;"><i class="fa fa-check"></i> <?= esc($acctmsg) ?></div><?php } ?>
    <?php if ($accterr !== '') { ?><div class="alert bg-danger" role="alert" style="margin-bottom:10px;"><i class="fa fa-ban"></i> <?= esc($accterr) ?></div><?php } ?>

    <div style="overflow-x:auto;">
    <table class="table table-bordered table-hover text-nowrap">
      <thead>
        <tr><th>Username</th><th>Role / status</th><th>Reset password</th><th>Last login</th><th>Created</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($accounts as $a) {
        $isself = (int) $a['id'] === (int) $_SESSION['mikhmon_uid']; ?>
        <tr>
          <td><i class="fa fa-user"></i> <?= esc($a['username']) ?><?= $isself ? ' <i>(you)</i>' : '' ?></td>
          <td>
            <form method="post" action="./admin.php?id=accounts" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="acct_action" value="update">
              <input type="hidden" name="uid" value="<?= (int) $a['id'] ?>">
              <select name="role" class="form-control" style="width:auto; display:inline-block;">
                <?php foreach (array_keys(mikhmon_roles()) as $r) { ?>
                  <option value="<?= esc($r) ?>" <?= $a['role'] === $r ? 'selected' : '' ?>><?= esc(ucfirst($r)) ?></option>
                <?php } ?>
              </select>
              <label style="margin:0 6px;"><input type="checkbox" name="active" value="1" <?= (int) $a['active'] === 1 ? 'checked' : '' ?>> active</label>
              <button type="submit" class="btn bg-primary" title="Save role and status"><i class="fa fa-save"></i></button>
            </form>
          </td>
          <td>
            <form method="post" action="./admin.php?id=accounts" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="acct_action" value="password">
              <input type="hidden" name="uid" value="<?= (int) $a['id'] ?>">
              <input type="password" name="reset_password" class="form-control" style="width:150px; display:inline-block;" placeholder="new password" autocomplete="new-password" minlength="8" required>
              <button type="submit" class="btn bg-warning" title="Set password"><i class="fa fa-key"></i></button>
            </form>
          </td>
          <td><?= esc($fmt($a['last_login_at'])) ?></td>
          <td><?= esc($fmt($a['created_at'])) ?></td>
          <td>
            <?php if (!$isself) { ?>
            <form method="post" action="./admin.php?id=accounts" style="display:inline;" onsubmit="return confirm('Delete account <?= esc(str_replace(array("'", '\\'), '', $a['username'])) ?>?');">
              <?= csrf_field() ?>
              <input type="hidden" name="acct_action" value="delete">
              <input type="hidden" name="uid" value="<?= (int) $a['id'] ?>">
              <button type="submit" class="btn bg-danger" title="Delete account"><i class="fa fa-trash"></i></button>
            </form>
            <?php } ?>
          </td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
    </div>

    <div class="card" style="margin-top:15px;">
      <div class="card-header"><h3 class="card-title"><i class="fa fa-user-plus"></i> Add account</h3></div>
      <div class="card-body">
        <form method="post" action="./admin.php?id=accounts" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="acct_action" value="create">
          <table class="table table-sm">
            <tr><td class="align-middle">Username</td><td><input class="form-control" type="text" name="new_username" pattern="[A-Za-z0-9._@-]{3,32}" title="3-32 characters: letters, numbers, . _ @ -" required></td></tr>
            <tr><td class="align-middle">Password</td><td><input class="form-control" type="password" name="new_password" minlength="8" autocomplete="new-password" required></td></tr>
            <tr><td class="align-middle">Role</td><td>
              <select class="form-control" name="new_role">
                <option value="operator">Operator</option>
                <option value="viewer">Viewer</option>
                <option value="admin">Admin</option>
              </select></td></tr>
            <tr><td></td><td><button type="submit" class="btn bg-primary"><i class="fa fa-plus"></i> Create</button></td></tr>
          </table>
        </form>
        <table class="table table-sm">
          <?php foreach ($roledesc as $r => $d) { ?>
            <tr><td><b><?= esc(ucfirst($r)) ?></b></td><td><?= esc($d) ?></td></tr>
          <?php } ?>
        </table>
      </div>
    </div>
  <?php } ?>
  </div>
</div>
</div>
</div>
