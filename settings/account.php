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
// ---- two-factor authentication ------------------------------------------------------------
$newcodes = null;
if ($me && isset($_POST['twofa_action'])) {
  $act = mikhmon_input($_POST, 'twofa_action');
  $enabled = mikhmon_2fa_enabled($me);
  if ($act === 'enable' && !$enabled) {
    mikhmon_2fa_begin_self_enrol($me['id']);
    echo "<script>window.location='./admin.php?id=2fa'</script>";
    return;
  } elseif (in_array($act, array('regenerate', 'disable'), true) && $enabled) {
    $ignored = false;
    $method = '';
    if (!mikhmon_password_matches(mikhmon_input($_POST, 'twofa_password'), $me['password_hash'], $ignored)) {
      $ownerr = 'Current password is wrong.';
    } elseif ($act === 'disable' && mikhmon_2fa_required($me)) {
      $ownerr = 'Admin accounts cannot turn two-factor authentication off.';
    } else {
      $ownerr = mikhmon_2fa_check($me, mikhmon_input($_POST, 'twofa_code'), false, $method);
    }
    if ($ownerr === '') {
      if ($act === 'regenerate') {
        $newcodes = mikhmon_recovery_regenerate($me['id']);
        $ownmsg = 'New recovery codes created. The old ones no longer work.';
        mikhmon_audit('account.2fa-recovery-new', $me['username']);
      } else {
        mikhmon_2fa_disable($me['id']);
        $ownmsg = 'Two-factor authentication turned off.';
        mikhmon_audit('account.2fa-disable', $me['username'], 'by the user');
      }
      $me = mikhmon_user_by_id($me['id']);
    } else {
      mikhmon_audit($act === 'disable' ? 'account.2fa-disable' : 'account.2fa-recovery-new', $me['username'], $ownerr, 'failed');
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

<?php if ($me) {
  $tfaOn = mikhmon_2fa_enabled($me);
  $tfaMust = mikhmon_2fa_required($me); ?>
<div class="col-6">
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-shield"></i> Two-factor authentication</h3>
  </div>
  <div class="card-body">
    <p style="margin-top:0;">Status:
      <?php if ($tfaOn) { ?><b class="text-green">On</b> &nbsp; <span class="muted"><?= (int) mikhmon_recovery_remaining($me['id']) ?> recovery codes left</span>
      <?php } else { ?><b class="text-red">Off</b><?php } ?>
      <?= $tfaMust ? ' &nbsp; <span class="muted">(required for admins)</span>' : '' ?></p>

    <?php if ($newcodes !== null) { ?>
      <p>Save these recovery codes. Each works once, and they are not shown again.</p>
      <pre style="font-size:16px;line-height:1.8;text-align:center;background:var(--surface-2,#f3f5f7);border-radius:8px;padding:12px;user-select:all;"><?php foreach ($newcodes as $c) { echo esc($c) . "\n"; } ?></pre>
    <?php } ?>

    <?php if (!$tfaOn) { ?>
      <p>Adds a 6-digit code from an authenticator app to your sign-in.</p>
      <form method="post" action="./admin.php?id=account"><?= csrf_field() ?>
        <input type="hidden" name="twofa_action" value="enable">
        <button type="submit" class="btn bg-primary"><i class="fa fa-shield"></i> Set up</button>
      </form>
    <?php } else { ?>
      <form method="post" action="./admin.php?id=account" autocomplete="off"><?= csrf_field() ?>
        <table class="table table-sm">
          <tr><td class="align-middle">Password</td><td><input class="form-control" type="password" name="twofa_password" autocomplete="current-password" required></td></tr>
          <tr><td class="align-middle">Code from app</td><td><input class="form-control" type="text" name="twofa_code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required></td></tr>
          <tr><td></td><td>
            <button type="submit" name="twofa_action" value="regenerate" class="btn bg-warning"><i class="fa fa-refresh"></i> New recovery codes</button>
            <?php if (!$tfaMust) { ?><button type="submit" name="twofa_action" value="disable" class="btn bg-danger" onclick="return confirm('Turn two-factor authentication off?');"><i class="fa fa-times"></i> Turn off</button><?php } ?>
          </td></tr>
        </table>
      </form>
    <?php } ?>
  </div>
</div>
</div>
<?php } ?>
</div>
