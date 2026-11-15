<?php
/*
 *  "My account": every logged-in user can change their own password here.
 */
if (!isset($_SESSION["mikhmon"])) {
  header("Location:./admin.php?id=login");
  exit;
}

$ownmsg = '';
$ownerr = '';
$me = (mikhmon_db() && !empty($_SESSION['mikhmon_uid'])) ? mikhmon_user_by_id($_SESSION['mikhmon_uid']) : null;

if ($me && isset($_POST['change_password'])) {
  $cur = mikhmon_input($_POST, 'current_password');
  $new = mikhmon_input($_POST, 'new_password');
  $rep = mikhmon_input($_POST, 'repeat_password');
  $ignored = false;
  if (!mikhmon_password_matches($cur, $me['password_hash'], $ignored)) {
    $ownerr = 'Current password is wrong.';
    mikhmon_audit('account.password-own', $me['username'], 'wrong current password', 'failed');
  } elseif ($new !== $rep) {
    $ownerr = 'The new passwords do not match.';
  } else {
    $ownerr = mikhmon_user_set_password($me['id'], $new);
    if ($ownerr === '') {
      $ownmsg = 'Password changed.';
      mikhmon_audit('account.password-own', $me['username']);
      // drop other sessions' tokens for this browser session
      session_regenerate_id(true);
      $me = mikhmon_user_by_id($me['id']);
    } else {
      mikhmon_audit('account.password-own', $me['username'], $ownerr, 'failed');
    }
  }
}
$ignored = false;
$isdefault = $me && strtolower($me['username']) === 'mikhmon' && mikhmon_password_matches('1234', $me['password_hash'], $ignored);
?>
<div class="row">
<div class="col-6">
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-user-circle"></i> My account</h3>
  </div>
  <div class="card-body">
  <?php if (!$me) { ?>
    <div class="alert bg-danger" role="alert" style="margin-bottom:10px;"><i class="fa fa-warning"></i>
      Account database not available (enable pdo_sqlite). The legacy admin login can be changed under Admin Settings.</div>
  <?php } else { ?>
    <?php if ($isdefault) { ?><div class="alert bg-danger" role="alert" style="margin-bottom:10px;"><i class="fa fa-warning"></i> You are using the default password 1234.</div><?php } ?>
    <?php if ($ownmsg !== '') { ?><div class="alert bg-success" role="alert" style="margin-bottom:10px;"><i class="fa fa-check"></i> <?= esc($ownmsg) ?></div><?php } ?>
    <?php if ($ownerr !== '') { ?><div class="alert bg-danger" role="alert" style="margin-bottom:10px;"><i class="fa fa-ban"></i> <?= esc($ownerr) ?></div><?php } ?>
    <table class="table table-sm">
      <tr><td>Username</td><td><b><?= esc($me['username']) ?></b></td></tr>
      <tr><td>Role</td><td><?= esc(ucfirst($me['role'])) ?></td></tr>
      <tr><td>Last login</td><td><?= esc($me['last_login_at'] ? date('Y-m-d H:i', (int) $me['last_login_at']) : '-') ?></td></tr>
    </table>
    <form method="post" action="./admin.php?id=account" autocomplete="off">
      <?= csrf_field() ?>
      <table class="table table-sm">
        <tr><td class="align-middle">Current password</td><td><input class="form-control" type="password" name="current_password" autocomplete="current-password" required></td></tr>
        <tr><td class="align-middle">New password</td><td><input class="form-control" type="password" name="new_password" minlength="8" autocomplete="new-password" required></td></tr>
        <tr><td class="align-middle">Repeat new password</td><td><input class="form-control" type="password" name="repeat_password" minlength="8" autocomplete="new-password" required></td></tr>
        <tr><td></td><td><button type="submit" name="change_password" value="1" class="btn bg-primary"><i class="fa fa-key"></i> Change password</button></td></tr>
      </table>
    </form>
  <?php } ?>
  </div>
</div>
</div>
</div>
