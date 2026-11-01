#!/usr/bin/env php
<?php
declare(strict_types=1);

/** Original vector-derived brand artwork. No stock photographs, fleet or staff imagery. */
if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD extension is required to generate brand assets.\n");
    exit(1);
}

function brand_font(bool $bold): ?string
{
    $override = getenv($bold ? 'BRAND_FONT_BOLD' : 'BRAND_FONT_REGULAR');
    $candidates = [
        $override ?: '',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans' . ($bold ? '-Bold' : '') . '.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-' . ($bold ? 'Bold' : 'Regular') . '.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-' . ($bold ? 'Bold' : 'Regular') . '.ttf',
        '/Library/Fonts/Arial' . ($bold ? ' Bold' : '') . '.ttf',
        'C:/Windows/Fonts/arial' . ($bold ? 'bd' : '') . '.ttf',
    ];
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_readable($candidate)) {
            return $candidate;
        }
    }
    return null;
}

function brand_color(GdImage $canvas, string $hex, int $alpha = 0): int
{
    return imagecolorallocatealpha($canvas, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), $alpha);
}

function brand_text(GdImage $canvas, string $text, float $size, float $x, float $y, int $color, bool $bold = false): void
{
    $font = brand_font($bold);
    if ($font !== null && function_exists('imagettftext')) {
        imagettftext($canvas, $size, 0, (int) $x, (int) $y, $color, $font, $text);
        return;
    }
    // Last-resort portable GD fallback: render transliterated text, preserving the layout.
    $ascii = strtr($text, ['Ş'=>'S','ş'=>'s','İ'=>'I','ı'=>'i','Ğ'=>'G','ğ'=>'g','Ü'=>'U','ü'=>'u','Ö'=>'O','ö'=>'o','Ç'=>'C','ç'=>'c','·'=>'/']);
    $glyphs = imagecreatetruecolor(max(1, strlen($ascii) * 9), 18);
    imagefill($glyphs, 0, 0, imagecolorallocate($glyphs, 13, 46, 66));
    imagestring($glyphs, 5, 0, 0, $ascii, imagecolorallocate($glyphs, 242, 244, 242));
    $height = (int) ($size * 1.25);
    imagecopyresampled($canvas, $glyphs, (int) $x, (int) ($y - $height), 0, 0, (int) (strlen($ascii) * $height / 2), $height, imagesx($glyphs), imagesy($glyphs));
    imagedestroy($glyphs);
}

function brand_curve(GdImage $canvas, array $points, int $color, int $thickness = 4): void
{
    // Supersampling handles smoothing; GD antialiasing can leave seams between thick segments.
    imageantialias($canvas, false);
    imagesetthickness($canvas, $thickness);
    $previous = $points[0];
    for ($i = 1; $i <= 100; $i++) {
        $t = $i / 100;
        $u = 1 - $t;
        $point = [
            $u ** 3 * $points[0][0] + 3 * $u ** 2 * $t * $points[1][0] + 3 * $u * $t ** 2 * $points[2][0] + $t ** 3 * $points[3][0],
            $u ** 3 * $points[0][1] + 3 * $u ** 2 * $t * $points[1][1] + 3 * $u * $t ** 2 * $points[2][1] + $t ** 3 * $points[3][1],
        ];
        imageline($canvas, (int) $previous[0], (int) $previous[1], (int) $point[0], (int) $point[1], $color);
        $previous = $point;
    }
    imagesetthickness($canvas, 1);
    imageantialias($canvas, true);
}

function brand_mark(GdImage $canvas, float $x, float $y, float $size, int $paper, int $orange): void
{
    // Exact geometry of the user's approved 64×64 truck mark, rendered at 2×.
    $scale = $size / 64;
    $navy = brand_color($canvas, '0D2E42');
    $white = brand_color($canvas, 'FFFFFF');
    $rect = static function (float $x1, float $y1, float $x2, float $y2, int $color) use ($canvas, $x, $y, $scale): void {
        imagefilledrectangle($canvas, (int) ($x+$x1*$scale), (int) ($y+$y1*$scale), (int) ($x+$x2*$scale), (int) ($y+$y2*$scale), $color);
    };
    $circle = static function (float $cx, float $cy, float $diameter, int $color) use ($canvas, $x, $y, $scale): void {
        imagefilledellipse($canvas, (int) ($x+$cx*$scale), (int) ($y+$cy*$scale), (int) ($diameter*$scale), (int) ($diameter*$scale), $color);
    };
    $rect(17, 0, 47, 64, $navy);
    $rect(0, 17, 64, 47, $navy);
    foreach ([[17,17], [47,17], [17,47], [47,47]] as [$cx,$cy]) {
        $circle($cx, $cy, 34, $navy);
    }
    $rect(10, 29, 39, 38, $white);
    $rect(16, 23, 39, 38, $white);
    $circle(16, 29, 12, $white);
    $points = [];
    foreach ([[39,28], [48,28], [56,38], [56,44], [39,44]] as [$px,$py]) {
        $points[]=(int)($x+$px*$scale);
        $points[]=(int)($y+$py*$scale);
    }
    imagefilledpolygon($canvas, $points, $white);
    $rect(10,42,56,46,$orange);
    $circle(10,44,4,$orange);
    $circle(56,44,4,$orange);
    foreach ([22,48] as $cx) {
        $circle($cx,45,14,$white);
        $circle($cx,45,10,$orange);
    }
}

function brand_route(GdImage $canvas, float $x, float $y, float $w, float $h): void
{
    $teal = brand_color($canvas, '087F8C');
    $sea = brand_color($canvas, '216373');
    $line = brand_color($canvas, 'A4C4C1', 95);
    $orange = brand_color($canvas, 'FF9149');
    $paper = brand_color($canvas, 'F2F4F2');
    imagefilledellipse($canvas, (int) ($x + $w * .8), (int) ($y + $h * .37), (int) ($w * .98), (int) ($h * 1.25), $teal);
    imagefilledellipse($canvas, (int) ($x + $w * .93), (int) ($y + $h * .4), (int) ($w * .68), (int) ($h * .9), $sea);
    for ($i = 0; $i < 6; $i++) {
        $offset = $i * $h * .11;
        brand_curve($canvas, [[$x+$w*.1,$y+$h*.15+$offset],[$x+$w*.48,$y-$h*.15+$offset],[$x+$w*.6,$y+$h*.9+$offset],[$x+$w*1.15,$y+$h*.55+$offset]], $line, max(1, (int) ($w / 300)));
    }
    $a = [$x + $w * .2, $y + $h * .7];
    $b = [$x + $w * .79, $y + $h * .27];
    brand_curve($canvas, [$a, [$x+$w*.73,$y+$h*.83], [$x+$w*.27,$y+$h*.16], $b], $orange, max(5, (int) ($w / 80)));
    foreach ([$a, $b] as [$px, $py]) {
        imagefilledellipse($canvas, (int) $px, (int) $py, (int) ($w*.07), (int) ($w*.07), $paper);
        imagefilledellipse($canvas, (int) $px, (int) $py, (int) ($w*.032), (int) ($w*.032), $orange);
    }
    $cx = $x + $w * .55;
    $cy = $y + $h * .49;
    $r = $w * .065;
    imagefilledellipse($canvas, (int) $cx, (int) $cy, (int) ($r * 2.6), (int) ($r * 2.6), $orange);
    imagesetthickness($canvas, max(3, (int) ($r*.13)));
    imageline($canvas, (int) ($cx-$r*.55), (int) $cy, (int) ($cx+$r*.55), (int) $cy, $paper);
    imageline($canvas, (int) ($cx+$r*.15), (int) ($cy-$r*.4), (int) ($cx+$r*.55), (int) $cy, $paper);
    imageline($canvas, (int) ($cx+$r*.15), (int) ($cy+$r*.4), (int) ($cx+$r*.55), (int) $cy, $paper);
    imagesetthickness($canvas, 1);
}

function brand_render(string $path, int $width, int $height, string $format): void
{
    $s = 2;
    $canvas = imagecreatetruecolor($width*$s, $height*$s);
    imageantialias($canvas, true);
    $navy = brand_color($canvas, '0D2E42');
    $paper = brand_color($canvas, 'F2F4F2');
    $orange = brand_color($canvas, 'FF9149');
    $muted = brand_color($canvas, 'B7D0CD');
    if ($format === 'icon') {
        imagealphablending($canvas, false);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagesavealpha($canvas, true);
        imagealphablending($canvas, true);
    } else {
        imagefill($canvas, 0, 0, $navy);
    }
    if ($format === 'icon') {
        brand_mark($canvas, 0, 0, $width*$s, $paper, $orange);
    } elseif ($format === 'wide') {
        brand_route($canvas, 750*$s, 90*$s, 530*$s, 540*$s);
        brand_mark($canvas, 50*$s, 40*$s, 100*$s, $paper, $orange);
        brand_text($canvas, 'KAŞ / ANTALYA', 15*$s, 165*$s, 100*$s, $muted, true);
        brand_text($canvas, 'KAŞ', 88*$s, 76*$s, 276*$s, $paper, true);
        brand_text($canvas, 'NAKLİYAT', 88*$s, 76*$s, 385*$s, $paper, true);
        imagefilledrectangle($canvas, 80*$s, 429*$s, 144*$s, 435*$s, $orange);
        brand_text($canvas, 'Yükünüz için doğru plan.', 25*$s, 80*$s, 494*$s, $muted);
        brand_text($canvas, '+90 505 219 43 46  /  kasnakliyat.com', 15*$s, 80*$s, 566*$s, $paper, true);
    } elseif ($format === 'square') {
        brand_route($canvas, 460*$s, 430*$s, 660*$s, 600*$s);
        brand_mark($canvas, 60*$s, 52*$s, 100*$s, $paper, $orange);
        brand_text($canvas, 'KAŞ / ANTALYA', 17*$s, 175*$s, 115*$s, $muted, true);
        brand_text($canvas, 'KAŞ', 107*$s, 84*$s, 321*$s, $paper, true);
        brand_text($canvas, 'NAKLİYAT', 107*$s, 84*$s, 452*$s, $paper, true);
        imagefilledrectangle($canvas, 90*$s, 513*$s, 162*$s, 520*$s, $orange);
        brand_text($canvas, 'Yükünüz için', 31*$s, 88*$s, 595*$s, $muted);
        brand_text($canvas, 'doğru plan.', 31*$s, 88*$s, 645*$s, $muted);
        brand_text($canvas, '+90 505 219 43 46', 19*$s, 88*$s, 946*$s, $paper, true);
        brand_text($canvas, 'bilgi@kasnakliyat.com', 16*$s, 88*$s, 993*$s, $muted);
    } else {
        brand_route($canvas, 60*$s, 775*$s, 1030*$s, 785*$s);
        brand_mark($canvas, 65*$s, 90*$s, 110*$s, $paper, $orange);
        brand_text($canvas, 'KAŞ / ANTALYA', 18*$s, 195*$s, 160*$s, $muted, true);
        brand_text($canvas, 'KAŞ', 121*$s, 88*$s, 435*$s, $paper, true);
        brand_text($canvas, 'NAKLİYAT', 110*$s, 88*$s, 576*$s, $paper, true);
        imagefilledrectangle($canvas, 94*$s, 637*$s, 166*$s, 644*$s, $orange);
        brand_text($canvas, 'Yükünüz için doğru plan.', 31*$s, 92*$s, 719*$s, $muted);
        brand_text($canvas, 'Her yolculuk', 36*$s, 92*$s, 1670*$s, $paper, true);
        brand_text($canvas, 'bir planla başlar.', 36*$s, 92*$s, 1730*$s, $paper, true);
        brand_text($canvas, '+90 505 219 43 46', 23*$s, 94*$s, 1815*$s, $paper, true);
        brand_text($canvas, 'bilgi@kasnakliyat.com', 19*$s, 94*$s, 1863*$s, $muted);
    }
    $output = imagecreatetruecolor($width, $height);
    if ($format === 'icon') {
        imagealphablending($output, false);
        imagefill($output, 0, 0, imagecolorallocatealpha($output, 0, 0, 0, 127));
        imagesavealpha($output, true);
    }
    imagecopyresampled($output, $canvas, 0, 0, 0, 0, $width, $height, $width*$s, $height*$s);
    if (!imagepng($output, $path, 9)) {
        throw new RuntimeException('Unable to write ' . $path);
    }
    imagedestroy($canvas);
    imagedestroy($output);
    echo basename($path) . ': ' . $width . 'x' . $height . " PNG\n";
}

$destination = dirname(__DIR__) . '/public/assets';
if (!is_dir($destination)) {
    mkdir($destination, 0755, true);
}
brand_render($destination . '/share-card.png', 1200, 630, 'wide');
brand_render($destination . '/social-square.png', 1080, 1080, 'square');
brand_render($destination . '/social-story.png', 1080, 1920, 'story');
brand_render($destination . '/apple-touch-icon.png', 180, 180, 'icon');

brand_render($destination . '/favicon-32.png', 32, 32, 'icon');
brand_render($destination . '/icon-192.png', 192, 192, 'icon');
