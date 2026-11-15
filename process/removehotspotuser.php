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
// only runs when included by index.php (authenticated, $API connected)
if (!isset($_SESSION["mikhmon"]) || !isset($API)) { header("Location:../admin.php?id=login"); exit; } // mikhmon_process_guard
if ($removehotspotusers != "") {
	$uids = explode("~", $removehotspotusers);

	$nuids = count($uids);

	for ($i = 0; $i < $nuids; $i++) {

		$getuname = $API->comm("/ip/hotspot/user/print", array(
			"?.id" => "$uids[$i]",
		));

		$name = $getuname[0]['name'];

		$getscr = $API->comm("/system/script/print", array(
			"?name" => "$name",
		));

		$scr = $getscr[0]['.id'];

		$getsch = $API->comm("/system/scheduler/print", array(
			"?name" => "$name",
		));

		$sch = $getsch[0]['.id'];

		$API->comm("/system/script/remove", array(
			".id" => "$scr",
		));

		$API->comm("/system/scheduler/remove", array(
			".id" => "$sch",
		));

		$API->comm("/ip/hotspot/user/remove", array(
			".id" => "$uids[$i]",
		));

	}

	if (mikhmon_state_get($session, 'ubp') != "") {
		echo "<script>window.location='./?hotspot=users&profile=" . mikhmon_state_get($session, 'ubp') . "&session=" . $session . "'</script>";
	} elseif (mikhmon_state_get($session, 'ubc') != "") {
		echo "<script>window.location='./?hotspot=users&comment=" . mikhmon_state_get($session, 'ubc') . "&session=" . $session . "'</script>";
	} else {
		echo "<script>window.location='./?hotspot=users&profile=all&session=" . $session . "'</script>";
	}


} else {
	$getuname = $API->comm("/ip/hotspot/user/print", array(
		"?.id" => "$removehotspotuser",
	));

	$name = $getuname[0]['name'];

	$getscr = $API->comm("/system/script/print", array(
		"?name" => "$name",
	));

	$scr = $getscr[0]['.id'];

	$getsch = $API->comm("/system/scheduler/print", array(
		"?name" => "$name",
	));

	$sch = $getsch[0]['.id'];

	$API->comm("/system/script/remove", array(
		".id" => "$scr",
	));

	$API->comm("/system/scheduler/remove", array(
		".id" => "$sch",
	));

	$API->comm("/ip/hotspot/user/remove", array(
		".id" => "$removehotspotuser",
	));


	if (mikhmon_state_get($session, 'ubp') != "") {
		echo "<script>window.location='./?hotspot=users&profile=" . mikhmon_state_get($session, 'ubp') . "&session=" . $session . "'</script>";
	} elseif (mikhmon_state_get($session, 'ubc') != "") {
		echo "<script>window.location='./?hotspot=users&comment=" . mikhmon_state_get($session, 'ubc') . "&session=" . $session . "'</script>";
	} else {
		echo "<script>window.location='./?hotspot=users&profile=all&session=" . $session . "'</script>";
	}
}
?>