<?php
/*
 *  QR code image for a signed link made by mikhmon_qr_url().
 *
 *  The Quick Printer phone app downloads the picture itself and has no login cookie, so access is
 *  by signature instead of by session: the link only ever returns the QR code of the exact text it
 *  was signed for, and it expires after a few minutes. Anything else gets a plain 404.
 */
include_once(__DIR__ . '/../include/security.php');
include_once(__DIR__ . '/../include/qrcode.php');

function qr_gone()
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'Not found';
    exit;
}

$d = mikhmon_input($_GET, 'd');
$e = mikhmon_input($_GET, 'e');
$s = mikhmon_input($_GET, 's');

if ($d === '' || strlen($d) > 400 || !preg_match('/^[A-Za-z0-9_-]+$/', $d)
    || !ctype_digit($e) || (int) $e < time() || (int) $e > time() + 3600
    || !preg_match('/^[0-9a-f]{64}$/', $s)
    || !hash_equals(mikhmon_qr_sign($d, $e), $s)) {
    qr_gone();
}

$text = mikhmon_qr_b64d($d);
$png = ($text === false || $text === '') ? false : mikhmon_qr_png($text, 'M');
if ($png === false) {
    qr_gone();
}

header('Content-Type: image/png');
header('Content-Length: ' . strlen($png));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $png;
