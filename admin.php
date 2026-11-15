<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
include_once('./include/security.php');
mikhmon_session_cookie_params();
session_start();
// hide all error
error_reporting(0);
mikhmon_guard_request();

ob_start("ob_gzhandler");

// check url
$url = $_SERVER['REQUEST_URI'];

// load session MikroTik
$session = mikhmon_session_param();
$id = $_GET['id'];
$c = $_GET['c'];
$router = $_GET['router'];
$logo = $_GET['logo'];

$ids = array(
  "editor",
  "uplogo",
  "settings",
);

// lang
include('./lang/isocodelang.php');
include('./include/lang.php');
include('./lang/'.$langid.'.php');

// quick bt
include('./include/quickbt.php');

// theme
include('./include/theme.php');
include('./settings/settheme.php');
include('./settings/setlang.php');
if ($_SESSION['theme'] == "") {
    $theme = $theme;
    $themecolor = $themecolor;
  } else {
    $theme = $_SESSION['theme'];
    $themecolor = $_SESSION['themecolor'];
}


// load config
include_once('./include/headhtml.php');
include('./include/config.php');
include('./include/readcfg.php');

include_once('./lib/routeros_api.class.php');
include_once('./lib/formatbytesbites.php');
?>
    
<?php
if ($id == "login" || substr($url, -1) == "p") {

  if (isset($_POST['login'])) {
    $user = mikhmon_input($_POST, 'user');
    $pass = mikhmon_input($_POST, 'pass');
    // 5 failed attempts per IP within 15 minutes, then locked for the rest of the window
    $wait = mikhmon_rate_wait('login', 5, 900);
    $auth = ($wait > 0) ? null : mikhmon_authenticate($user, $pass, $useradm, $passadm);
    if ($wait > 0) {
      mikhmon_log_login($user, false, 'rate limited');
      $error = '<div class="alert bg-danger" role="alert"><i class="fa fa-ban"></i> Too many failed logins. Try again in ' . ceil($wait / 60) . ' minute(s).</div>';
    } elseif ($auth['ok']) {
      mikhmon_rate_clear('login');
      mikhmon_start_user_session($auth['user']);
      mikhmon_log_login($auth['user']['username'], true);
      mikhmon_audit('login', $auth['user']['username'], 'role=' . $auth['user']['role']);
      // the database bootstrap may have rewritten config.php: reload before saving it again
      $data = mikhmon_load_config_data();
      // re-encrypt router passwords stored in the old format (and, without SQLite, hash the admin password)
      mikhmon_upgrade_stored_secrets($data, $pass, !empty($auth['user']['legacy_upgrade']));

        echo "<script>window.location='./admin.php?id=sessions'</script>";

    } else {
      mikhmon_rate_hit('login');
      mikhmon_log_login($user, false, $auth['reason']);
      usleep(random_int(300000, 700000));
      $error = '<div class="alert bg-danger" role="alert"><i class="fa fa-ban"></i> '
        . ($auth['reason'] === 'database unavailable' ? 'Account database unavailable. Check that pdo_sqlite is enabled.' : 'Invalid username or password.') . '</div>';
    }
  }
  

  include_once('./include/login.php');
} elseif (!isset($_SESSION["mikhmon"])) {
  echo "<script>window.location='./admin.php?id=login'</script>";
} elseif (substr($url, -1) == "/" || substr($url, -4) == ".php") {
  echo "<script>window.location='./admin.php?id=sessions'</script>";

} elseif ($id == "sessions") {
  $_SESSION["connect"] = "";
  include_once('./include/menu.php');
  include_once('./settings/sessions.php');
  /*echo '
  <script type="text/javascript">
    document.getElementById("sessname").onkeypress = function(e) {
    var chr = String.fromCharCode(e.which);
    if (" _!@#$%^&*()+=;|?,~".indexOf(chr) >= 0)
        return false;
    };
    </script>';*/
} elseif ($id == "settings" && !empty($session) || $id == "settings" && !empty($router)) {
  include_once('./include/menu.php');
  include_once('./settings/settings.php');
  echo '
  <script type="text/javascript">
    document.getElementById("sessname").onkeypress = function(e) {
    var chr = String.fromCharCode(e.which);
    if (" _!@#$%^&*()+=;|?,~".indexOf(chr) >= 0)
        return false;
    };
    </script>';
} elseif ($id == "connect"  && !empty($session)) {
  ini_set("max_execution_time",5);  
  include_once('./include/menu.php');
  $API = new RouterosAPI();
  $API->debug = false;
  if ($API->connect($iphost, $userhost, decrypt($passwdhost))){
    $_SESSION["connect"] = "<b class='text-green'>Connected</b>";
    echo "<script>window.location='./?session=" . $session . "'</script>";
  } else {
    $_SESSION["connect"] = "<b class='text-red'>Not Connected</b>";
    $nl = '\n';
    if ($currency == in_array($currency, $cekindo['indo'])) {
      echo "<script>alert('Mikhmon not connected!".$nl."Silakan periksa kembali IP, User, Password dan port API harus enable.".$nl."Jika menggunakan koneksi VPN, pastikan VPN tersebut terkoneksi.')</script>";
    }else{
      echo "<script>alert('Mikhmon not connected!".$nl."Please check the IP, User, Password and port API must be enabled.')</script>";
    }
    if($c == "settings"){
      echo "<script>window.location='./admin.php?id=settings&session=" . $session . "'</script>";
    }else{
      echo "<script>window.location='./admin.php?id=sessions'</script>";
    }
  }
} elseif ($id == "uplogo"  && !empty($session)) {
  include_once('./include/menu.php');
  include_once('./settings/uplogo.php');
} elseif ($id == "reboot"  && !empty($session)) {
  include_once('./process/reboot.php');
} elseif ($id == "shutdown"  && !empty($session)) {
  include_once('./process/shutdown.php');
} elseif ($id == "remove-session" && $session != "") {
  include_once('./include/menu.php');
  if ($session !== "mikhmon" && isset($data[$session])) {
    unset($data[$session]);
    mikhmon_save_config($data);
  }
  echo "<script>window.location='./admin.php?id=sessions'</script>";
} elseif ($id == "about") {
  include_once('./include/menu.php');
  include_once('./include/about.php');
} elseif ($id == "accounts") {
  include_once('./include/menu.php');
  include_once('./settings/accounts.php');
} elseif ($id == "account") {
  include_once('./include/menu.php');
  include_once('./settings/account.php');
} elseif ($id == "audit") {
  include_once('./include/menu.php');
  include_once('./settings/audit.php');
} elseif ($id == "logout") {
  mikhmon_audit('logout', isset($_SESSION['mikhmon']) ? $_SESSION['mikhmon'] : '');
  include_once('./include/menu.php');
  echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Logout...</b>";
  session_destroy();
  echo "<script>window.location='./admin.php?id=login'</script>";
} elseif ($id == "remove-logo" && $logo != ""  && !empty($session)) {
  include_once('./include/menu.php');
  $logopath = "./img/";
  $logoname = basename((string)$logo);
  if ($logoname === $logo && preg_match('/^logo-[A-Za-z0-9._-]+\.png$/', $logoname) && is_file($logopath . $logoname)) {
    unlink($logopath . $logoname);
  }
  echo "<script>window.location='./admin.php?id=uplogo&session=" . $session . "'</script>";
} elseif ($id == "editor"  && !empty($session)) {
  include_once('./include/menu.php');
  include_once('./settings/vouchereditor.php');
} elseif (empty($id)) {
  echo "<script>window.location='./admin.php?id=sessions'</script>";
} elseif(in_array($id, $ids) && empty($session)){
	echo "<script>window.location='./admin.php?id=sessions'</script>";
}
?>
<script src="js/mikhmon-shell.js"></script>
<script src="js/mikhmon.js?t=<?= str_replace(" ","_",date("Y-m-d H:i:s")); ?>"></script>
<?php include('./include/info.php'); ?>
</body>
</html>

