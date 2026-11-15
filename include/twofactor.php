<?php
/*
 *  Second sign-in step (admin.php?id=2fa).
 *
 *    verify - the account has two-factor authentication: ask for the 6-digit code (or a recovery code)
 *    enroll - an admin has none yet (or a user chose to turn it on): show the QR code, check one code,
 *             then show the recovery codes once
 *
 *  The password was already checked in admin.php; the user is only half signed in
 *  ($_SESSION['mikhmon_2fa']) until the code is right.
 */
if (basename($_SERVER['SCRIPT_FILENAME']) == 'twofactor.php') {
  header("Location:../");
  exit;
}

$tfa = isset($_SESSION['mikhmon_2fa']) && is_array($_SESSION['mikhmon_2fa']) ? $_SESSION['mikhmon_2fa'] : null;
$tfaError = '';
$tfaDone = null; // recovery codes after a successful enrolment

$tfaUser = null;
if ($tfa && $tfa['exp'] >= time()) {
  $tfaUser = mikhmon_user_by_id($tfa['uid']);
  if ($tfaUser && (int) $tfaUser['active'] !== 1) {
    $tfaUser = null;
  }
  if ($tfaUser && !empty($tfa['self']) && (empty($_SESSION['mikhmon_uid']) || (int) $_SESSION['mikhmon_uid'] !== (int) $tfaUser['id'])) {
    $tfaUser = null;
  }
}
if (!$tfaUser) {
  unset($_SESSION['mikhmon_2fa']);
  echo "<script>window.location='./admin.php?id=" . (isset($_SESSION['mikhmon']) ? 'account' : 'login') . "'</script>";
  return;
}
$tfaSelf = !empty($tfa['self']);
$tfaBack = $tfaSelf ? './admin.php?id=account' : './admin.php?id=login';

// the secret of an enrolment lives in the session only until the first right code
if ($tfa['mode'] === 'enroll' && $tfa['secret'] === '') {
  $tfa['secret'] = mikhmon_totp_new_secret();
  $_SESSION['mikhmon_2fa'] = $tfa;
}

if (isset($_POST['tfa_code'])) {
  $code = mikhmon_input($_POST, 'tfa_code');
  $wait = mikhmon_rate_wait('2fa', 8, 900);
  if ($wait > 0) {
    mikhmon_log_login($tfaUser['username'], false, '2fa rate limited');
    $tfaError = 'Too many wrong codes. Try again in ' . ceil($wait / 60) . ' minute(s).';
  } else {
    $method = '';
    $problem = '';
    if ($tfa['mode'] === 'enroll') {
      $step = 0;
      if (mikhmon_totp_verify($tfa['secret'], $code, 0, $step)) {
        $method = 'totp';
      } else {
        $problem = 'That code is not valid. Check the time on your phone and try again.';
      }
    } else {
      $problem = mikhmon_2fa_check($tfaUser, $code, true, $method);
    }

    if ($problem === '') {
      mikhmon_rate_clear('2fa');
      if ($tfa['mode'] === 'enroll') {
        mikhmon_2fa_enable($tfaUser['id'], $tfa['secret'], $step);
        $tfaDone = mikhmon_recovery_regenerate($tfaUser['id']);
      }
      unset($_SESSION['mikhmon_2fa']);
      if (!$tfaSelf) {
        mikhmon_start_user_session($tfaUser);
        mikhmon_log_login($tfaUser['username'], true, $method === 'recovery' ? '2fa recovery code' : '');
        mikhmon_audit('login', $tfaUser['username'], 'role=' . $tfaUser['role'] . ', 2fa=' . $method);
      }
      if ($tfa['mode'] === 'enroll') {
        mikhmon_audit('account.2fa-enable', $tfaUser['username'], $tfaSelf ? 'by the user' : 'required at login');
      } elseif ($method === 'recovery') {
        mikhmon_audit('account.2fa-recovery-used', $tfaUser['username'], mikhmon_recovery_remaining($tfaUser['id']) . ' codes left');
      }
      if ($tfaDone === null) {
        echo "<script>window.location='./admin.php?id=sessions'</script>";
        return;
      }
    } else {
      mikhmon_rate_hit('2fa');
      $tfa['tries']++;
      $_SESSION['mikhmon_2fa'] = $tfa;
      mikhmon_log_login($tfaUser['username'], false, '2fa failed');
      usleep(random_int(200000, 500000));
      $tfaError = $problem;
      if ($tfa['tries'] >= MIKHMON_2FA_MAX_TRIES) {
        unset($_SESSION['mikhmon_2fa']);
        $tfaError = 'Too many wrong codes. Sign in again.';
        $tfa = null;
      }
    }
  }
}

$tfaAlert = $tfaError === '' ? '' : '<div class="alert bg-danger" role="alert"><i class="fa fa-ban"></i> ' . esc($tfaError) . '</div>';
?>
<div class="login-box" style="width:min(460px,94%);">
  <div class="card">
    <div class="card-body login-card">
<?php if ($tfaDone !== null) { ?>
      <div class="login-logo">
        <span class="mark" style="background:var(--ok-bg,#e6f6ec);color:var(--ok,#1a7f45);"><i class="fa fa-shield"></i></span>
        <span class="login-title" style="font-size:18px;letter-spacing:0;">Two-factor authentication is on</span>
        <span class="muted">Save these recovery codes somewhere safe. Each works once if you lose your phone. They are not shown again.</span>
      </div>
      <pre id="rc" style="font-size:16px;line-height:1.8;text-align:center;background:var(--surface-2,#f3f5f7);border-radius:8px;padding:12px;user-select:all;"><?php foreach ($tfaDone as $c) { echo esc($c) . "\n"; } ?></pre>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button type="button" class="btn" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
        <a class="btn bg-primary" href="<?= $tfaSelf ? './admin.php?id=account' : './admin.php?id=sessions' ?>"><i class="fa fa-check"></i> I saved them, continue</a>
      </div>
<?php } elseif ($tfa === null) { ?>
      <div class="login-logo">
        <span class="mark"><i class="fa fa-wifi"></i></span>
        <span class="login-title">MIKHMON</span>
      </div>
      <?= $tfaAlert ?>
      <a class="btn bg-primary" href="./admin.php?id=login">Back to sign in</a>
<?php } elseif ($tfa['mode'] === 'enroll') { ?>
      <div class="login-logo">
        <span class="mark"><i class="fa fa-shield"></i></span>
        <span class="login-title" style="font-size:18px;letter-spacing:0;">Set up two-factor authentication</span>
        <span class="muted"><?= $tfaSelf ? 'Add a second step to your sign-in.' : 'Admin accounts must use it. This is a one-time setup.' ?></span>
      </div>
      <?= $tfaAlert ?>
      <ol style="padding-left:20px;margin:0 0 12px;">
        <li>Install an authenticator app (Google Authenticator, Microsoft Authenticator, Aegis, 1Password, Bitwarden...).</li>
        <li>Scan this code, or type the key by hand.</li>
        <li>Enter the 6-digit code the app shows.</li>
      </ol>
      <div style="text-align:center;margin-bottom:8px;"><?= mikhmon_totp_qr_img($tfaUser['username'], $tfa['secret']) ?></div>
      <p style="text-align:center;margin:0 0 12px;"><span class="muted">Key</span><br><code style="font-size:14px;user-select:all;"><?= esc(mikhmon_totp_pretty($tfa['secret'])) ?></code></p>
      <form autocomplete="off" action="./admin.php?id=2fa" method="post"><?= csrf_field() ?>
        <div class="field">
          <label for="_tfa">6-digit code</label>
          <input class="form-control" type="text" name="tfa_code" id="_tfa" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" placeholder="123456" required autofocus autocomplete="one-time-code">
        </div>
        <input class="btn-login bg-primary pointer" type="submit" value="Turn on and continue">
      </form>
      <p style="text-align:center;margin:12px 0 0;"><a href="<?= esc($tfaBack) ?>"><?= $tfaSelf ? 'Cancel' : 'Cancel and sign out' ?></a></p>
<?php } else { ?>
      <div class="login-logo">
        <span class="mark"><i class="fa fa-shield"></i></span>
        <span class="login-title" style="font-size:18px;letter-spacing:0;">Two-factor authentication</span>
        <span class="muted">Hello <b><?= esc($tfaUser['username']) ?></b>. Enter the 6-digit code from your authenticator app.</span>
      </div>
      <?= $tfaAlert ?>
      <form autocomplete="off" action="./admin.php?id=2fa" method="post"><?= csrf_field() ?>
        <div class="field">
          <label for="_tfa">Code</label>
          <input class="form-control" type="text" name="tfa_code" id="_tfa" inputmode="text" maxlength="20" placeholder="123456" required autofocus autocomplete="one-time-code">
          <span class="muted" style="font-size:12px;">Lost your phone? Type one of your recovery codes instead.</span>
        </div>
        <input class="btn-login bg-primary pointer" type="submit" value="Verify">
      </form>
      <p style="text-align:center;margin:12px 0 0;"><a href="./admin.php?id=login">Cancel</a></p>
<?php } ?>
    </div>
  </div>
</div>
</body>
</html>
