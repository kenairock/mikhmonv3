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
session_start();
// hide all error
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {

  include ('./include/version.php');

  $btnmenuactive = "font-weight: bold;background-color: #f9f9f9; color: #000000";
  if ($hotspot == "dashboard" || substr(end(explode("/", $url)), 0, 8) == "?session") {
    $shome = "active";
    $mpage = $_dashboard;
  } elseif ($hotspot == "quick-print" || $hotspot == "list-quick-print") {
    $squick = "active";
    $mpage = $_quick_print;   
  } elseif ($hotspot == "users" || $userbyprofile != "" || $hotspot == "export-users" || $removehotspotuserbycomment != "" || $removehotspotuser != "" || $removehotspotusers != "" || $disablehotspotuser || $enablehotspotuser != "") {
    $susersl = "active";
    $susers = "active";
    $mpage = $_users;
    $umenu = "menu-open";
  } elseif ($hotspotuser == "add") {
    $sadduser = "active";
    $mpage = $_users;
    $susers = "active";
    $umenu = "menu-open";
  } elseif ($hotspotuser == "generate") {
    $sgenuser = "active";
    $mpage = $_users;
    $susers = "active";
    $umenu = "menu-open";
  } elseif ($userbyname != ""  || $resethotspotuser != "") {
    $susers = "active";
    $mpage = $_users;
    $umenu = "menu-open";
  } elseif ($hotspot == "user-profiles") {
    $suserprofiles = "active";
    $suserprof = "active";
    $mpage = $_user_profile;
    $upmenu = "menu-open";
  } elseif ($hotspot == "active" || $removeuseractive != "") {
    $sactive = "active";
    $mpage = $_hotspot_active;
    $hamenu = "menu-open";
  } elseif ($hotspot == "hosts" || $hotspot == "hostp" || $hotspot == "hosta" || $removehost != "") {
    $shosts = "active";
    $mpage = $_hosts;
    $hmenu = "menu-open";
  } elseif ($hotspot == "dhcp-leases") {
    $slease = "active";
    $mpage = $_dhcp_leases;
  } elseif ($minterface == "traffic-monitor") {
    $strafficmonitor = "active";
    $mpage = $_traffic_monitor;  
  } elseif ($hotspot == "ipbinding" || $hotspot == "binding" || $removeipbinding != "" || $enableipbinding != "" || $disableipbinding != "") {
    $sipbind = "active";
    $mpage = $_ip_bindings;
    $ibmenu = "menu-open";
  } elseif ($hotspot == "template-editor") {
    $ssett = "active";
    $teditor = "active";
    $mpage = $_template_editor;
    $settmenu = "menu-open";
  } elseif ($hotspot == "uplogo") {
    $ssett = "active";
    $uplogo = "active";
    $mpage = $_upload_logo;
    $settmenu = "menu-open";
  } elseif ($hotspot == "cookies" || $removecookie != "") {
    $scookies = "active";
    $mpage = $_hotspot_cookies;
    $cmenu = "menu-open";
  } elseif ($hotspot == "log") {
    $log = "active";
    $slog = "active";
    $mpage = $_hotspot_log;
    $lmenu = "menu-open";
  } elseif ($report == "userlog") {
    $log = "active";
    $sulog = "active";
    $mpage = $_user_log;
    $lmenu = "menu-open";
  } elseif ($ppp == "secrets" || $ppp == "addsecret" || $enablesecr != "" || $disablesecr != "" || $removesecr != "" || $secretbyname != "") {
    $mppp = "active";
    $ssecrets = "active";
    $mpage = $_ppp_secrets;
    $pppmenu = "menu-open";
  } elseif ($ppp == "profiles" || $removepprofile != "" || $ppp == "add-profile" || $ppp == "edit-profile"  ) {
    $mppp = "active";
    $spprofile = "active";
    $mpage = $_ppp_profiles;
    $pppmenu = "menu-open";
  } elseif ($ppp == "active" || $removepactive != "") {
    $mppp = "active";
    $spactive = "active";
    $mpage = $_ppp_active;
    $pppmenu = "menu-open";
  } elseif ($sys == "scheduler" || $enablesch != "" || $disablesch != "" || $removesch != "") {
    $sysmenu = "active";
    $ssch = "active";
    $mpage = $_system_scheduler;
    $schmenu = "menu-open";
  } elseif ($report == "selling" || $report == "resume-report") {
    $sselling = "active";
    $mpage = $_report;
  } elseif ($userprofile == "add") {
    $suserprof = "active";
    $sadduserprof = "active";
    $mpage = $_user_profile;
    $upmenu = "menu-open";
  } elseif ($userprofilebyname != "") {
    $suserprof = "active";
    $mpage = $_user_profile;
    $upmenu = "menu-open";
  } elseif ($hotspot == "users-by-profile") {
    $susersbp = "active";
    $mpage = $_vouchers;
  } elseif ($userbyname != "") {
    $mpage = $_users;
    $susers = "active";
  } elseif ($hotspot == "about") {
    $mpage = $_about;
    $sabout = "active";
  } elseif ($id == "sessions" || $id == "remove" || $router == "new") {
    $ssesslist = "active";
    $mpage = $_admin_settings;
  } elseif ($id == "settings" && $session == "new") {
    $snsettings = "active";
    $mpage = $_add_router;
  } elseif ($id == "settings" || $id == "connect") {
    $ssettings = "active";
    $mpage = $_session_settings;
  } elseif ($id == "about") {
    $sabout = "active";
    $mpage = $_about;
  } elseif ($id == "uplogo") {
    $suplogo = "active";
    $mpage = $_upload_logo;
  } elseif ($id == "editor") {
    $seditor = "active";
    $mpage = $_template_editor;
  }
}

if($idleto != "disable"){
  $didleto = 'display:block;';
}else{
  $didleto = 'display:none;';
}
?>
<span style="display:none;" id="idto"><?= $idleto ;?></span>


<?php if ($id != "") { ?>

<?php include('./include/topbar.php'); ?>

<nav id="sidenav" class="sidenav" aria-label="Main">
<?php if (($id == "settings" && $session == "new") || $id == "settings" && $router == "new") {
}else if ($id == "settings" || $id == "editor"|| $id == "uplogo" || $id == "connect"){
?>  
  <div class="router-card"><span class="muted">Router</span><b><?= esc($session); ?></b></div>
  <a class="connect menu <?= $shome; ?>" id="<?= $session; ?>&c=settings"><i class='fa fa-tachometer'></i> <?= $_dashboard ?></a>
  <?php if (mikhmon_can('admin')) { ?> <a  href="./admin.php?id=settings&session=<?= $session; ?>" class="menu <?= $ssettings; ?>" title="Mikhmon Settings"><i class='fa fa-gear'></i> <?= $_session_settings ?></a> <?php } ?>
  <?php if (mikhmon_can('admin')) { ?> <a href="./admin.php?id=uplogo&session=<?= $session; ?>" class="menu <?= $suplogo; ?>"><i class="fa fa-upload"></i> <?= $_upload_logo ?></a> <?php } ?>
  <?php if (mikhmon_can('admin')) { ?> <a href="./admin.php?id=editor&template=default&session=<?= $session; ?>" class="menu <?= $seditor; ?>"><i class="fa fa-edit"></i> <?= $_template_editor ?></a> <?php } ?>
  <div class="menu spa"></div>
<?php 
} ?>  
  <a href="./admin.php?id=sessions" class="menu <?= $ssesslist; ?>"><i class="fa fa-gear"></i> <?= $_admin_settings ?></a>
  <a href="./admin.php?id=account" class="menu <?= ($id == "account") ? "active" : ""; ?>"><i class="fa fa-key"></i> My account</a>
  <?php if (mikhmon_can('admin')) { ?>
  <a href="./admin.php?id=accounts" class="menu <?= ($id == "accounts") ? "active" : ""; ?>"><i class="fa fa-users"></i> Accounts</a>
  <a href="./admin.php?id=audit" class="menu <?= ($id == "audit") ? "active" : ""; ?>"><i class="fa fa-history"></i> Login &amp; audit history</a>
  <?php } ?>
  <?php if (mikhmon_can('admin')) { ?> <a href="./admin.php?id=settings&router=new-<?= rand(1111,9999) ?>" class="menu <?= $snsettings ?>"><i class="fa fa-plus"></i> <?= $_add_router ?></a> <?php } ?>
  <a href="./admin.php?id=about" class="menu <?= $sabout; ?>"><i class="fa fa-info-circle"></i> <?= $_about ?></a>

</nav>

<script>
$(document).ready(function(){
  $(".connect").click(function(){
    notify("<?= $_connecting ?>");
    connect(this.id)
  });
  $(".slang").change(function(){
    notify("<?= $_loading ?>");
    stheme(this.value)
  });
});
</script>
<div id="notify"><div class="message"></div></div>
<div id="temp"></div>
<?php 
include('./info.php');
} else { ?>

<?php include('./include/topbar.php'); ?>

<nav id="sidenav" class="sidenav" aria-label="Main">
  <div class="router-card"><span class="muted">Router</span><b><?= esc($identity); ?></b></div>
  <a href="./?session=<?= $session; ?>" class="menu <?= $shome; ?>"><i class="fa fa-dashboard"></i> <?= $_dashboard ?></a>
  <!--hotspot-->
  <div class="dropdown-btn <?= $susers . $suserprof . $sactive . $shosts . $sipbind . $scookies; ?>"><i class="fa fa-wifi"></i> Hotspot
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $umenu . $upmenu . $hamenu . $hmenu . $ibmenu . $cmenu; ?>">
   <!--users--> 
  <div class="dropdown-btn <?= $susers; ?>"><i class="fa fa-users"></i> <?= $_users ?>
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $umenu; ?>">
    <a href="./?hotspot=users&profile=all&session=<?= $session; ?>" class="<?= $susersl; ?>"> <i class="fa fa-list"></i> <?= $_user_list ?> </a>
    <?php if (mikhmon_can('operator')) { ?> <a href="./?hotspot-user=add&session=<?= $session; ?>" class="<?= $sadduser; ?>"> <i class="fa fa-user-plus"></i> <?= $_add_user ?> </a> <?php } ?>
    <?php if (mikhmon_can('operator')) { ?> <a href="./?hotspot-user=generate&session=<?= $session; ?>" class="<?= $sgenuser; ?>"> <i class="fa fa-user-plus"></i> <?= $_generate ?> </a>         <?php } ?>
  </div>
  <!--profile-->
  <div class="dropdown-btn <?= $suserprof; ?>"><i class="fa fa-pie-chart"></i>  <?= $_user_profile ?>
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $upmenu; ?>">
    <a href="./?hotspot=user-profiles&session=<?= $session; ?>" class=" <?= $suserprofiles; ?>"> <i class="fa fa-list"></i> <?= $_user_profile_list ?> </a>
    <?php if (mikhmon_can('operator')) { ?> <a href="./?user-profile=add&session=<?= $session; ?>" class=" <?= $sadduserprof; ?>"> <i class="fa fa-plus-square"></i> <?= $_add_user_profile ?> </a> <?php } ?>

  </div>
  <!--active-->
  <a href="./?hotspot=active&session=<?= $session; ?>" class="menu <?= $sactive; ?>"><i class="fa fa-wifi"></i> <?= $_hotspot_active ?></a>
  <!--hosts-->
  <a href="./?hotspot=hosts&session=<?= $session; ?>" class="menu <?= $shosts; ?>"><i class="fa fa-laptop"></i> <?= $_hosts ?></a>
  <!--ip bindings-->
  <a href="./?hotspot=ipbinding&session=<?= $session; ?>" class="menu <?= $sipbind; ?>"><i class="fa fa-address-book"></i> <?= $_ip_bindings ?></a>
  <!--cookies-->
   <a href="./?hotspot=cookies&session=<?= $session; ?>" class="menu <?= $scookies; ?>"><i class="fa fa-hourglass"></i> <?= $_hotspot_cookies ?></a>
  </div>
  <!--quick print-->
  <?php if (mikhmon_can('operator')) { ?> <a href="./?hotspot=quick-print&session=<?= $session; ?>" class="menu <?= $squick; ?>"> <i class="fa fa-print"></i> <?= $_quick_print ?> </a> <?php } ?>
  <!--vouchers-->
  <a href="./?hotspot=users-by-profile&session=<?= $session; ?>" class="menu <?= $susersbp; ?>"> <i class="fa fa-ticket"></i> <?= $_vouchers ?> </a>
   <!--log-->
  <div class="dropdown-btn <?= $log; ?>"><i class="fa fa-align-justify"></i> <?= $_log ?>
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $lmenu; ?>">
    <a href="./?hotspot=log&session=<?= $session; ?>" class="<?= $slog; ?>"> <i class="fa fa-wifi"></i> <?= $_hotspot_log ?> </a>
    <a href="./?report=userlog&idbl=<?= strtolower(date("M")) . date("Y"); ?>&session=<?= $session; ?>" class=" <?= $sulog; ?>"> <i class="fa fa-users"></i> <?= $_user_log ?> </a>
  </div>
  <!--system-->
  <div class="dropdown-btn <?= $sysmenu; ?>"><i class="fa fa-gear"></i> <?= $_system ?>
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $schmenu; ?>">
    <a href="./?system=scheduler&session=<?= $session; ?>" class="<?= $ssch; ?>"> <i class="fa fa-clock-o"></i> <?= $_system_scheduler ?> </a>
    <?php if (mikhmon_can('admin')) { ?> <a href="./admin.php?id=reboot&session=<?= $session; ?>" class=""> <i class="fa fa-power-off"></i> <?= $_system_reboot ?> </a>             <?php } ?>
    <?php if (mikhmon_can('admin')) { ?> <a href="./admin.php?id=shutdown&session=<?= $session; ?>" class=""> <i class="fa fa-power-off"></i> <?= $_system_off ?> </a>  <?php } ?>
  </div>
  <!--dhcp leases-->
  <a href="./?hotspot=dhcp-leases&session=<?= $session; ?>" class="menu <?= $slease; ?>"><i class="fa fa-sitemap"></i> <?= $_dhcp_leases ?></a>
  <!--traffic monitor-->
  <a href="./?interface=traffic-monitor&session=<?= $session; ?>" class="menu <?= $strafficmonitor; ?>"><i class="fa fa-area-chart"></i> <?= $_traffic_monitor ?></a>
  <!--report-->
  <a href="./?report=selling&idbl=<?= strtolower(date("M")) . date("Y"); ?>&session=<?= $session; ?>" class="menu <?= $sselling; ?>"><i class="fa fa-money"></i> <?= $_report ?></a>
  <!--settings-->
  <div class="dropdown-btn <?= $ssett; ?>"><i class="fa fa-gear"></i> <?= $_settings ?> 
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $settmenu; ?>">
  <?php if (mikhmon_can('admin')) { ?> <a href="./admin.php?id=settings&session=<?= $session; ?>" class="menu "> <i class="fa fa-gear"></i> <?= $_session_settings ?> </a> <?php } ?>
  <a href="./admin.php?id=sessions" class="menu "> <i class="fa fa-gear"></i> <?= $_admin_settings ?> </a>
  <a href="./admin.php?id=account" class="menu <?= ($id == "account") ? "active" : ""; ?>"><i class="fa fa-key"></i> My account</a>
  <?php if (mikhmon_can('admin')) { ?>
  <a href="./admin.php?id=accounts" class="menu <?= ($id == "accounts") ? "active" : ""; ?>"><i class="fa fa-users"></i> Accounts</a>
  <a href="./admin.php?id=audit" class="menu <?= ($id == "audit") ? "active" : ""; ?>"><i class="fa fa-history"></i> Login &amp; audit history</a>
  <?php } ?>
  <?php if (mikhmon_can('admin')) { ?> <a href="./?hotspot=uplogo&session=<?= $session; ?>" class="menu <?= $uplogo; ?>"> <i class="fa fa-upload"></i> <?= $_upload_logo ?> </a> <?php } ?>
  <?php if (mikhmon_can('admin')) { ?> <a href="./?hotspot=template-editor&template=default&session=<?= $session; ?>" class="menu <?= $teditor; ?>"> <i class="fa fa-edit"></i> <?= $_template_editor ?> </a>           <?php } ?>
  </div>
  <!--about-->
  <a href="./?hotspot=about&session=<?= $session; ?>" class="menu <?= $sabout; ?>"><i class="fa fa-info-circle"></i> <?= $_about ?></a>

</nav>
<script>
$(document).ready(function(){
  $(".connect").change(function(){
    notify("<?= $_connecting ?>");
    connect(this.value)
  });
});
</script>
<div id="notify"><div class="message"></div></div>
<div id="temp"></div>
<?php 
include('./include/info.php');
} ?>

<div id="main">  
<div id="loading" class="lds-dual-ring"></div>
<?php if($hotspot == 'template-editor' || $id == 'editor'){
echo '<div class="main-container">';
}else{
  echo '<div class="main-container" style="display:none">';
}
?>

