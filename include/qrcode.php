<?php
/*
 *  Local QR code generator (no internet service, no GD needed).
 *
 *  Replaces the chart.googleapis.com / api.qrserver.com calls that sent every voucher's
 *  login link (user name + password included) to a third party.
 *
 *  - mikhmon_qr_matrix($text, $ecl)  -> array of rows of booleans (true = dark module)
 *  - mikhmon_qr_png($text, ...)      -> PNG image bytes
 *  - mikhmon_qr_url($text)           -> short-lived signed link to voucher/qr.php, for apps that
 *                                       must download the picture themselves (Quick Printer)
 *
 *  Byte mode, versions 1-10 (up to 213 bytes at level M), all four error-correction levels.
 *  The algorithm follows the QR Code specification (ISO/IEC 18004).
 */
if (basename($_SERVER['SCRIPT_FILENAME']) == 'qrcode.php') {
    header("Location:../");
    exit;
}

if (!function_exists('mikhmon_qr_matrix')) {

/** Error-correction codewords per block, and number of blocks, for versions 1..10. */
function mikhmon_qr_tables()
{
    return array(
        'L' => array('fmt' => 1, 'ecc' => array(7, 10, 15, 20, 26, 18, 20, 24, 30, 18),   'blocks' => array(1, 1, 1, 1, 1, 2, 2, 2, 2, 4)),
        'M' => array('fmt' => 0, 'ecc' => array(10, 16, 26, 18, 24, 16, 18, 22, 22, 26),  'blocks' => array(1, 1, 1, 2, 2, 4, 4, 4, 5, 5)),
        'Q' => array('fmt' => 3, 'ecc' => array(13, 22, 18, 26, 18, 24, 18, 22, 20, 24),  'blocks' => array(1, 1, 2, 2, 4, 4, 6, 6, 8, 8)),
        'H' => array('fmt' => 2, 'ecc' => array(17, 28, 22, 16, 22, 28, 26, 26, 24, 28),  'blocks' => array(1, 1, 2, 4, 4, 4, 5, 6, 8, 8)),
    );
}

function mikhmon_qr_raw_modules($ver)
{
    $r = (16 * $ver + 128) * $ver + 64;
    if ($ver >= 2) {
        $n = intdiv($ver, 7) + 2;
        $r -= (25 * $n - 10) * $n - 55;
        if ($ver >= 7) {
            $r -= 36;
        }
    }
    return $r;
}

function mikhmon_qr_gf_mul($x, $y)
{
    $z = 0;
    for ($i = 7; $i >= 0; $i--) {
        $z = ($z << 1) ^ (($z >> 7) * 0x11D);
        $z ^= (($y >> $i) & 1) * $x;
    }
    return $z & 0xFF;
}

/** Reed-Solomon error-correction bytes for one block of data bytes. */
function mikhmon_qr_rs($data, $degree)
{
    $gen = array_fill(0, $degree, 0);
    $gen[$degree - 1] = 1;
    $root = 1;
    for ($i = 0; $i < $degree; $i++) {
        for ($j = 0; $j < $degree; $j++) {
            $gen[$j] = mikhmon_qr_gf_mul($gen[$j], $root);
            if ($j + 1 < $degree) {
                $gen[$j] ^= $gen[$j + 1];
            }
        }
        $root = mikhmon_qr_gf_mul($root, 2);
    }
    $rem = array_fill(0, $degree, 0);
    foreach ($data as $b) {
        $factor = $b ^ array_shift($rem);
        $rem[] = 0;
        foreach ($gen as $i => $g) {
            $rem[$i] ^= mikhmon_qr_gf_mul($g, $factor);
        }
    }
    return $rem;
}

/**
 * @return array|false  rows of booleans, or false when the text does not fit in version 10
 */
function mikhmon_qr_matrix($text, $ecl = 'M')
{
    $text = (string) $text;
    $tables = mikhmon_qr_tables();
    if (!isset($tables[$ecl])) {
        $ecl = 'M';
    }
    $t = $tables[$ecl];
    $len = strlen($text);

    // smallest version that holds the text
    $ver = 0;
    for ($v = 1; $v <= 10; $v++) {
        $dataCw = intdiv(mikhmon_qr_raw_modules($v), 8) - $t['ecc'][$v - 1] * $t['blocks'][$v - 1];
        $needBits = 4 + ($v <= 9 ? 8 : 16) + 8 * $len;
        if ($needBits <= $dataCw * 8) {
            $ver = $v;
            break;
        }
    }
    if ($ver === 0) {
        return false;
    }
    $size = $ver * 4 + 17;
    $rawCw = intdiv(mikhmon_qr_raw_modules($ver), 8);
    $eccLen = $t['ecc'][$ver - 1];
    $numBlocks = $t['blocks'][$ver - 1];
    $dataCw = $rawCw - $eccLen * $numBlocks;

    // --- data bits: byte mode, terminator, padding
    $bits = '0100' . str_pad(decbin($len), $ver <= 9 ? 8 : 16, '0', STR_PAD_LEFT);
    for ($i = 0; $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
    }
    $bits .= str_repeat('0', min(4, $dataCw * 8 - strlen($bits)));
    $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
    $data = array();
    for ($i = 0; $i < strlen($bits); $i += 8) {
        $data[] = bindec(substr($bits, $i, 8));
    }
    for ($pad = 0xEC; count($data) < $dataCw; $pad ^= 0xEC ^ 0x11) {
        $data[] = $pad;
    }

    // --- split into blocks, add error correction, interleave
    $numShort = $numBlocks - $rawCw % $numBlocks;
    $shortLen = intdiv($rawCw, $numBlocks);
    $blocks = array();
    $eccs = array();
    for ($i = 0, $k = 0; $i < $numBlocks; $i++) {
        $dl = $shortLen - $eccLen + ($i < $numShort ? 0 : 1);
        $chunk = array_slice($data, $k, $dl);
        $k += $dl;
        $blocks[] = $chunk;
        $eccs[] = mikhmon_qr_rs($chunk, $eccLen);
    }
    $all = array();
    for ($i = 0; $i <= $shortLen - $eccLen; $i++) {
        for ($j = 0; $j < $numBlocks; $j++) {
            if ($i < count($blocks[$j])) {
                $all[] = $blocks[$j][$i];
            }
        }
    }
    for ($i = 0; $i < $eccLen; $i++) {
        for ($j = 0; $j < $numBlocks; $j++) {
            $all[] = $eccs[$j][$i];
        }
    }

    // --- function patterns
    $mod = array_fill(0, $size, array_fill(0, $size, false));
    $fn = array_fill(0, $size, array_fill(0, $size, false));
    $set = function ($x, $y, $dark) use (&$mod, &$fn, $size) {
        if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) {
            $mod[$y][$x] = (bool) $dark;
            $fn[$y][$x] = true;
        }
    };
    for ($i = 0; $i < $size; $i++) {
        $set(6, $i, $i % 2 == 0);
        $set($i, 6, $i % 2 == 0);
    }
    foreach (array(array(3, 3), array($size - 4, 3), array(3, $size - 4)) as $c) {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $d = max(abs($dx), abs($dy));
                $set($c[0] + $dx, $c[1] + $dy, $d != 2 && $d != 4);
            }
        }
    }
    $align = array();
    if ($ver > 1) {
        $n = intdiv($ver, 7) + 2;
        $step = ($ver == 32) ? 26 : intdiv($ver * 4 + $n * 2 + 1, $n * 2 - 2) * 2;
        $align = array(6);
        for ($pos = $size - 7, $c = 0; $c < $n - 1; $pos -= $step, $c++) {
            array_splice($align, 1, 0, array($pos));
        }
    }
    $na = count($align);
    for ($i = 0; $i < $na; $i++) {
        for ($j = 0; $j < $na; $j++) {
            if (($i == 0 && $j == 0) || ($i == 0 && $j == $na - 1) || ($i == $na - 1 && $j == 0)) {
                continue;
            }
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $set($align[$i] + $dx, $align[$j] + $dy, max(abs($dx), abs($dy)) != 1);
                }
            }
        }
    }
    $drawFormat = function ($mask) use (&$set, $t, $size) {
        $d = ($t['fmt'] << 3) | $mask;
        $rem = $d;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $b = (($d << 10) | $rem) ^ 0x5412;
        for ($i = 0; $i <= 5; $i++) {
            $set(8, $i, ($b >> $i) & 1);
        }
        $set(8, 7, ($b >> 6) & 1);
        $set(8, 8, ($b >> 7) & 1);
        $set(7, 8, ($b >> 8) & 1);
        for ($i = 9; $i < 15; $i++) {
            $set(14 - $i, 8, ($b >> $i) & 1);
        }
        for ($i = 0; $i < 8; $i++) {
            $set($size - 1 - $i, 8, ($b >> $i) & 1);
        }
        for ($i = 8; $i < 15; $i++) {
            $set(8, $size - 15 + $i, ($b >> $i) & 1);
        }
        $set(8, $size - 8, true);
    };
    $drawFormat(0); // reserve the area
    if ($ver >= 7) {
        $rem = $ver;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $b = ($ver << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $a = $size - 11 + $i % 3;
            $c = intdiv($i, 3);
            $set($a, $c, ($b >> $i) & 1);
            $set($c, $a, ($b >> $i) & 1);
        }
    }

    // --- place the data, zig-zag from the bottom right
    $totalBits = count($all) * 8;
    $i = 0;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right == 6) {
            $right = 5;
        }
        for ($vert = 0; $vert < $size; $vert++) {
            for ($j = 0; $j < 2; $j++) {
                $x = $right - $j;
                $upward = (($right + 1) & 2) == 0;
                $y = $upward ? $size - 1 - $vert : $vert;
                if (!$fn[$y][$x] && $i < $totalBits) {
                    $mod[$y][$x] = (($all[$i >> 3] >> (7 - ($i & 7))) & 1) == 1;
                    $i++;
                }
            }
        }
    }

    // --- choose the mask with the lowest penalty
    $maskTest = function ($m, $x, $y) {
        switch ($m) {
            case 0: return ($x + $y) % 2 == 0;
            case 1: return $y % 2 == 0;
            case 2: return $x % 3 == 0;
            case 3: return ($x + $y) % 3 == 0;
            case 4: return (intdiv($x, 3) + intdiv($y, 2)) % 2 == 0;
            case 5: return $x * $y % 2 + $x * $y % 3 == 0;
            case 6: return ($x * $y % 2 + $x * $y % 3) % 2 == 0;
            default: return (($x + $y) % 2 + $x * $y % 3) % 2 == 0;
        }
    };
    $applyMask = function ($m) use (&$mod, $fn, $size, $maskTest) {
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (!$fn[$y][$x] && $maskTest($m, $x, $y)) {
                    $mod[$y][$x] = !$mod[$y][$x];
                }
            }
        }
    };
    $best = 0;
    $bestScore = PHP_INT_MAX;
    for ($m = 0; $m < 8; $m++) {
        $applyMask($m);
        $drawFormat($m);
        $score = mikhmon_qr_penalty($mod, $size);
        if ($score < $bestScore) {
            $bestScore = $score;
            $best = $m;
        }
        $applyMask($m); // undo
    }
    $applyMask($best);
    $drawFormat($best);
    return $mod;
}

/** Standard QR mask penalty (runs, 2x2 blocks, finder-like patterns, dark/light balance). */
function mikhmon_qr_penalty($mod, $size)
{
    $score = 0;
    $dark = 0;
    for ($pass = 0; $pass < 2; $pass++) {
        for ($a = 0; $a < $size; $a++) {
            $line = '';
            $run = 0;
            $prev = null;
            for ($b = 0; $b < $size; $b++) {
                $v = $pass == 0 ? $mod[$a][$b] : $mod[$b][$a];
                $line .= $v ? '1' : '0';
                if ($pass == 0 && $v) {
                    $dark++;
                }
                if ($v === $prev) {
                    $run++;
                    if ($run == 5) {
                        $score += 3;
                    } elseif ($run > 5) {
                        $score++;
                    }
                } else {
                    $prev = $v;
                    $run = 1;
                }
            }
            $padded = '0000' . $line . '0000'; // finder-like 1:1:3:1:1 with four light modules on a side
            $score += 40 * (substr_count($padded, '00001011101') + substr_count($padded, '10111010000'));
        }
    }
    for ($y = 0; $y < $size - 1; $y++) {
        for ($x = 0; $x < $size - 1; $x++) {
            if ($mod[$y][$x] === $mod[$y][$x + 1] && $mod[$y][$x] === $mod[$y + 1][$x] && $mod[$y][$x] === $mod[$y + 1][$x + 1]) {
                $score += 3;
            }
        }
    }
    $total = $size * $size;
    $score += (int) (abs($dark * 20 - $total * 10) / $total) * 10;
    return $score;
}

/** PNG bytes: 1-bit greyscale, written by hand (no GD). */
function mikhmon_qr_png($text, $ecl = 'M', $scale = 0, $border = 4)
{
    $m = mikhmon_qr_matrix($text, $ecl);
    if ($m === false) {
        return false;
    }
    $n = count($m);
    $full = $n + 2 * $border;
    if ($scale < 1) {
        $scale = max(2, intdiv(220, $full));
    }
    $px = $full * $scale;
    $rowBytes = intdiv($px + 7, 8);
    $raw = '';
    for ($y = 0; $y < $px; $y++) {
        $my = intdiv($y, $scale) - $border;
        $row = str_repeat("\xFF", $rowBytes); // 1 = white
        if ($my >= 0 && $my < $n) {
            for ($x = 0; $x < $px; $x++) {
                $mx = intdiv($x, $scale) - $border;
                if ($mx >= 0 && $mx < $n && $m[$my][$mx]) {
                    $row[$x >> 3] = chr(ord($row[$x >> 3]) & ~(0x80 >> ($x & 7)));
                }
            }
        }
        $raw .= "\x00" . $row;
    }
    $chunk = function ($type, $body) {
        return pack('N', strlen($body)) . $type . $body . pack('N', crc32($type . $body));
    };
    return "\x89PNG\r\n\x1a\n"
        . $chunk('IHDR', pack('NNCCCCC', $px, $px, 1, 0, 0, 0, 0))
        . $chunk('IDAT', gzcompress($raw, 9))
        . $chunk('IEND', '');
}

// ---------------------------------------------------------------------------
// Signed links for apps that fetch the picture themselves
// ---------------------------------------------------------------------------

function mikhmon_qr_sign($data, $expires)
{
    $key = mikhmon_secret_key();
    if ($key === false) {
        $key = 'mikhmon-no-key'; // legacy fallback; the link is still short-lived
    }
    return hash_hmac('sha256', 'qr|' . $expires . '|' . $data, $key);
}

/** Base64url without padding. */
function mikhmon_qr_b64e($s)
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function mikhmon_qr_b64d($s)
{
    return base64_decode(strtr($s, '-_', '+/'), true);
}

/**
 * Absolute link to voucher/qr.php for $text, valid for $ttl seconds.
 * The Quick Printer app downloads this picture itself and has no login cookie, so the link
 * carries a signature instead; it can only ever return the QR code for exactly this text.
 */
function mikhmon_qr_url($text, $ttl = 600)
{
    $expires = time() + (int) $ttl;
    $data = mikhmon_qr_b64e($text);
    $host = isset($_SERVER['HTTP_HOST']) && preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $https = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
    return ($https ? 'https://' : 'http://') . $host . mikhmon_base_url() . 'voucher/qr.php'
        . '?d=' . $data . '&e=' . $expires . '&s=' . mikhmon_qr_sign($data, $expires);
}

}
