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
include_once(__DIR__ . '/../include/security.php');
session_start();
// hide all error
error_reporting(0);
$session = mikhmon_session_param();
$uname = mikhmon_input($_GET, 'name');

include_once('../include/config.php');
$iphost=(explode('!', $data[$session][1] ?? '')[1] ?? ''); 
$userhost=(explode('@|@', $data[$session][2] ?? '')[1] ?? '');
$passwdhost=(explode('#|#', $data[$session][3] ?? '')[1] ?? ''); 
$hotspotname=(explode('%', $data[$session][4] ?? '')[1] ?? ''); 
$dnsname=(explode('^', $data[$session][5] ?? '')[1] ?? ''); 
$curency=(explode('&', $data[$session][6] ?? '')[1] ?? '');


include_once('../lib/routeros_api.class.php');

$API = new RouterosAPI();
$API->debug = false;
$API->connect( $iphost, $userhost, decrypt($passwdhost));

// public page: limit lookups per client
if ($uname != "" && mikhmon_rate_wait('status-exp', 60, 300) > 0) {
	$uname = "";
	$exp = "Too many requests.";
} elseif ($uname != "") {
	mikhmon_rate_hit('status-exp');
}
if($uname != ""){
	$getname = $API->comm("/ip/hotspot/user/print", array("?name" => "$uname"));
  	$exp = $getname[0]['comment'];
	if(substr($exp,3,1) == "/" && substr($exp,6,1) == "/"){
		$exp = $exp;
	}else{
	$getname = $API->comm("/sys/sch/print", array("?name" => "$uname"));
	  $exp = $getname[0]['next-run'];
	}
  
}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Voucher-<?= esc($hotspotname."-".$uname);?></title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<meta http-equiv="pragma" content="no-cache" />
		<style>
		body {
  			font-family: 'Helvetica', arial, sans-serif;
			font-size: 15px;
			margin:0px;
  		}
		</style>
	</head>
	<body>
		<div style="padding:5px;" id="exp" ><?= esc($exp);?></div>	
	</body>
</html>