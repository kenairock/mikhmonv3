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

// check url
$url2 = explode("&setlang", $url)[0];

$getlang = $_GET['setlang'];

if (empty($getlang) || !isset($_SESSION["mikhmon"])) {

} else {
    if (!empty($isocodelang[$getlang])) {
        include_once('./include/headhtml.php');
        mikhmon_write_php_var('./include/lang.php', array('langid' => $getlang));
        $_SESSION['lang'] = $getlang;
        echo '<center><div style="padding-top:10%;"><i class="fa fa-circle-o-notch fa-spin" style="font-size:40px"></i></div><h3>Load '.esc($getlang).' lang...</h3></center>';
        echo "<script>window.location=" . esc_js($url2) . "</script>";
        
    } else {
        include_once('./include/headhtml.php');
        echo '<center><div style="padding-top:10%;"><i class="fa fa-circle-o-notch fa-spin" style="font-size:40px"></i></div><h3>'.esc($getlang).' lang not found...</h3></center>';
        echo "<script>window.location=" . esc_js($url2) . "</script>";
    }
}

?>