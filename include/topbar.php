<?php
/*
 *  Top bar, shared by the admin pages (admin.php) and the router pages (index.php).
 *  Included from include/menu.php, so it sees the same variables ($id, $session, $mpage,
 *  $didleto, $hotspotname, $data, language strings ...).
 *
 *  Element ids/classes below are used by js/mikhmon.js and must stay:
 *    #brand (exact text "MIKHMON", must stay visible), #timer, #logout,
 *    .connect (router switcher), .slang (language select), #openNav
 */
if (basename($_SERVER['SCRIPT_FILENAME']) == 'topbar.php') {
  header("Location:../");
  exit;
}

$tbAdminPage = ($id != "");
$tbBrandHref = $tbAdminPage ? 'javascript:void(0)' : './?session=' . rawurlencode($session);
$tbLogoutHref = $tbAdminPage ? './admin.php?id=logout' : './?hotspot=logout&session=' . rawurlencode($session);
$tbUser = isset($_SESSION['mikhmon']) ? (string) $_SESSION['mikhmon'] : '';
$tbRole = function_exists('mikhmon_current_role') ? mikhmon_current_role() : '';
?>
<header id="navbar" class="navbar">
  <button id="openNav" class="nav-btn" type="button" aria-label="Menu" aria-controls="sidenav" aria-expanded="false"><i class="fa fa-bars"></i></button>
  <a id="brand" href="<?= esc($tbBrandHref) ?>">MIKHMON</a>
  <span id="cpage"><?= esc($mpage) ?></span>
  <span class="grow"></span>

  <?php if (!$tbAdminPage) { ?>
  <select class="connect optfa ses" aria-label="Router">
    <option id="MikhmonSession" value="<?= esc($session); ?>"><?= esc($hotspotname); ?></option>
    <?php
    foreach (array_keys($data) as $sesname) {
      $sesname = (string) $sesname;
      if ($sesname == "" || $sesname == "mikhmon" || !mikhmon_valid_session_name($sesname)) {
        continue;
      }
      echo '<option value="' . esc($sesname) . '">' . esc($sesname) . ($sesname == $session ? ' &#x2666;' : '') . '</option>';
    }
    ?>
  </select>
  <?php } ?>

  <span class="timer" title="Idle Timeout" style="<?= esc($didleto); ?>"><i class="fa fa-clock-o"></i><span id="timer"></span></span>

  <details class="popover">
    <summary class="nav-btn" aria-label="Appearance" title="Appearance"><i class="fa fa-adjust"></i></summary>
    <div class="popover-panel">
      <div>
        <label>Mode</label>
        <span class="seg" role="group" aria-label="Colour mode">
          <button type="button" data-set-mode="light" aria-pressed="false" aria-label="Light"><i class="fa fa-sun-o"></i></button>
          <button type="button" data-set-mode="auto" aria-pressed="false" aria-label="Auto (follow system)"><i class="fa fa-adjust"></i></button>
          <button type="button" data-set-mode="dark" aria-pressed="false" aria-label="Dark"><i class="fa fa-moon-o"></i></button>
        </span>
      </div>
      <div>
        <label>Accent</label>
        <span class="dots" role="group" aria-label="Accent colour">
          <button type="button" class="dot" data-set-accent="blue" style="background:#0f7ab8" aria-pressed="false" aria-label="Blue"></button>
          <button type="button" class="dot" data-set-accent="green" style="background:#2f9a5b" aria-pressed="false" aria-label="Green"></button>
          <button type="button" class="dot" data-set-accent="pink" style="background:#d63a76" aria-pressed="false" aria-label="Pink"></button>
          <button type="button" class="dot" data-set-accent="slate" style="background:#556579" aria-pressed="false" aria-label="Slate"></button>
        </span>
      </div>
      <?php if ($tbAdminPage && mikhmon_can('admin')) { ?>
      <div>
        <label><?= esc($_language_label ?? 'Language') ?></label>
        <select class="slang form-control">
          <option><?= esc($language) ?></option>
          <?php
          foreach (glob('lang/*') as $langfile) {
            if (!is_file($langfile)) {
              continue;
            }
            $langcode = substr(explode("/", $langfile)[1], 0, -4);
            if ($langcode == "isocodelang") {
              continue;
            }
            echo '<option value="' . esc($url) . '&setlang=' . esc($langcode) . '">' . esc($isocodelang[$langcode]) . '</option>';
          }
          ?>
        </select>
      </div>
      <?php } ?>
    </div>
  </details>

  <span class="user-chip hide-sm" title="<?= esc($tbUser) ?>"><i class="fa fa-user-circle"></i><span class="uname"><?= esc($tbUser) ?></span><?php if ($tbRole !== '') { ?><span class="badge role"><?= esc(ucfirst($tbRole)) ?></span><?php } ?></span>
  <a id="logout" href="<?= esc($tbLogoutHref) ?>" title="<?= esc($_logout) ?>"><i class="fa fa-sign-out"></i><span class="ltext"><?= esc($_logout) ?></span></a>
</header>
<div id="scrim" class="scrim"></div>
