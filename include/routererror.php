<?php
/*
 *  Full-page message shown when a router cannot be reached or rejects the API login,
 *  instead of an empty, half-rendered page (and instead of making the browser wait).
 *
 *  Used by index.php right after RouterosAPI::connect() fails.
 */
if (basename($_SERVER['SCRIPT_FILENAME']) == 'routererror.php') {
  header("Location:../");
  exit;
}

/**
 * @param RouterosAPI $API     the client after a failed connect() ($API->fail / $API->fail_text say why)
 * @param string      $session router session name
 * @param string      $iphost  router address from the config
 */
function mikhmon_router_error_page($API, $session, $iphost)
{
  global $theme;
  $login = ($API->fail === 'login');
  $title = $login ? 'The router rejected the login' : "Can't reach the router";
  $port = (int) $API->port;

  if (!headers_sent()) {
    http_response_code(503);
    header('Retry-After: 10');
    header('Cache-Control: no-store');
  }
  ?>
<!DOCTYPE html>
<html lang="en"<?= mikhmon_theme_html_attrs($theme) ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MIKHMON - <?= esc($title) ?></title>
<?= mikhmon_theme_head_script() ?>
<link rel="stylesheet" href="css/fontawesome/css/all.min.css">
<link rel="stylesheet" href="css/fontawesome/css/v4-shims.min.css">
<link rel="stylesheet" href="css/mikhmon.css">
<link rel="icon" href="./img/favicon.png" />
</head>
<body>
<div class="login-box" style="width:min(560px,94%);">
  <div class="card">
    <div class="card-body login-card">
      <div class="login-logo">
        <span class="mark" style="background:var(--bad-bg);color:var(--bad);"><i class="fa fa-plug"></i></span>
        <span class="login-title" style="font-size:18px;letter-spacing:0;"><?= esc($title) ?></span>
        <span class="muted"><?= $login ? 'Mikhmon reached the router, but it refused the user name or password.' : 'Mikhmon could not open the RouterOS API on this router.' ?></span>
      </div>

      <table class="table table-sm" style="margin-bottom:12px;">
        <tr><td class="muted" style="width:90px;">Router</td><td><b><?= esc($session) ?></b></td></tr>
        <tr><td class="muted">Address</td><td><?= esc($iphost) ?>:<?= $port ?></td></tr>
        <tr><td class="muted">Reason</td><td><?= esc($API->fail_text !== '' ? $API->fail_text : 'No answer from the router.') ?></td></tr>
      </table>

      <b>What to check</b>
      <ul style="margin:6px 0 16px; padding-left:20px; color:var(--muted);">
      <?php if ($login) { ?>
        <li>The API user name and password in this router's settings.</li>
        <li>That user needs the <i>api</i> policy (and read/write to manage hotspot users).</li>
        <li>Repeated wrong logins can lock the account on the router.</li>
      <?php } else { ?>
        <li>The router is powered on and reachable from the Mikhmon server (try <i>ping <?= esc($iphost) ?></i>).</li>
        <li>The API service is enabled: in Winbox open <i>IP &rarr; Services</i>, service <i>api</i>, port <?= $port ?>, and make sure its <i>Available From</i> list allows this server.</li>
        <li>No firewall rule blocks port <?= $port ?> between the two.</li>
        <li>The address in this router's settings is correct.</li>
      <?php } ?>
      </ul>

      <div style="display:flex; flex-wrap:wrap; gap:8px;">
        <a class="btn bg-primary" href="<?= esc($_SERVER['REQUEST_URI']) ?>"><i class="fa fa-refresh"></i> Retry</a>
        <a class="btn" href="./admin.php?id=sessions"><i class="fa fa-server"></i> Router list</a>
        <?php if (function_exists('mikhmon_can') && mikhmon_can('admin')) { ?>
        <a class="btn" href="./admin.php?id=settings&amp;session=<?= esc(rawurlencode($session)) ?>"><i class="fa fa-gear"></i> Router settings</a>
        <?php } ?>
        <a class="btn" href="./?hotspot=logout&amp;session=<?= esc(rawurlencode($session)) ?>"><i class="fa fa-sign-out"></i> Logout</a>
      </div>
    </div>
  </div>
</div>
<script src="js/mikhmon-shell.js"></script>
</body>
</html>
<?php
}
