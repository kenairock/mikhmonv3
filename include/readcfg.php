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
if (basename($_SERVER["SCRIPT_FILENAME"]) == "readcfg.php") {
    header("Location:./");
    exit;
}
// read config
$cfgsession = isset($data[(string)$session]) ? $data[(string)$session] : array();
$cfgadmin = isset($data['mikhmon']) ? $data['mikhmon'] : array();

$iphost = explode('!', $cfgsession[1] ?? '')[1] ?? '';
$userhost = explode('@|@', $cfgsession[2] ?? '')[1] ?? '';
$passwdhost = explode('#|#', $cfgsession[3] ?? '')[1] ?? '';
$hotspotname = explode('%', $cfgsession[4] ?? '')[1] ?? '';
$dnsname = explode('^', $cfgsession[5] ?? '')[1] ?? '';
$currency = explode('&', $cfgsession[6] ?? '')[1] ?? '';
$areload = explode('*', $cfgsession[7] ?? '')[1] ?? '';
$iface = explode('(', $cfgsession[8] ?? '')[1] ?? '';
$infolp = explode(')', $cfgsession[9] ?? '')[1] ?? '';
$idleto = explode('=', $cfgsession[10] ?? '')[1] ?? '';
$sesname = explode('+', $cfgsession[10] ?? '')[1] ?? '';
$useradm = explode('<|<', $cfgadmin[1] ?? '')[1] ?? '';
$passadm = explode('>|>', $cfgadmin[2] ?? '')[1] ?? '';
$livereport = explode('@!@', $cfgsession[11] ?? '')[1] ?? '';

$cekindo['indo'] = array(
    'RP', 'Rp', 'rp', 'IDR', 'idr', 'RP.', 'Rp.', 'rp.', 'IDR.', 'idr.',
);


