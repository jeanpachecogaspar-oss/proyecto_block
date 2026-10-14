<?php
/**
 * Genera las 6 ilustraciones de demostración (cordones con nudos al estilo "quipu")
 * que usan las noticias de ejemplo. Son dibujos propios, sin derechos de terceros.
 *
 * Uso:  php database/generar_imagenes_demo.php
 * Crea: public/uploads/demo_1.webp ... demo_6.webp y sus miniaturas demo_N_m.webp
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Ejecuta este script desde la línea de comandos.');
}
if (!function_exists('imagewebp')) {
    exit("Tu PHP no tiene soporte WebP en GD.\n");
}

$destino = dirname(__DIR__) . '/public/uploads';
if (!is_dir($destino) && !mkdir($destino, 0755, true)) {
    exit("No se pudo crear $destino\n");
}

// [fondo arriba, fondo abajo, cordón principal, nudo A, nudo B]
$paletas = [
    1 => ['#0B5A75', '#0F2E3D', '#FAF8F5', '#F2A878', '#9AD7EA'],
    2 => ['#6B2D14', '#2E1409', '#F7EFE6', '#F4C16B', '#E58A5B'],
    3 => ['#1F4D3A', '#0E2219', '#F4F1E8', '#C9E265', '#8FD0B0'],
    4 => ['#3B2A6B', '#170F33', '#F6F2FB', '#F0A6CA', '#A9C3F5'],
    5 => ['#7A5A12', '#2F2306', '#FFF8E6', '#FFD166', '#F29E4C'],
    6 => ['#17415C', '#0A1C29', '#EEF6FA', '#7FD1C7', '#F2A878'],
];

function color(GdImage $img, string $hex): int
{
    return (int) imagecolorallocate($img, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
}

foreach ($paletas as $n => [$arriba, $abajo, $cordon, $nudoA, $nudoB]) {
    mt_srand($n * 7919); // misma imagen cada vez que se ejecute
    $ancho = 1200;
    $alto = 630;
    $img = imagecreatetruecolor($ancho, $alto);
    imageantialias($img, true);

    // Fondo con degradado vertical
    [$r1, $g1, $b1] = [hexdec(substr($arriba, 1, 2)), hexdec(substr($arriba, 3, 2)), hexdec(substr($arriba, 5, 2))];
    [$r2, $g2, $b2] = [hexdec(substr($abajo, 1, 2)), hexdec(substr($abajo, 3, 2)), hexdec(substr($abajo, 5, 2))];
    for ($y = 0; $y < $alto; $y++) {
        $t = $y / ($alto - 1);
        $c = imagecolorallocate(
            $img,
            (int) ($r1 + ($r2 - $r1) * $t),
            (int) ($g1 + ($g2 - $g1) * $t),
            (int) ($b1 + ($b2 - $b1) * $t)
        );
        imageline($img, 0, $y, $ancho, $y, $c);
    }

    $cCordon = color($img, $cordon);
    $cA = color($img, $nudoA);
    $cB = color($img, $nudoB);

    // Cordón principal horizontal
    imagesetthickness($img, 12);
    imageline($img, 90, 120, $ancho - 90, 120, $cCordon);

    // Cordones colgantes con nudos
    $cantidad = 13;
    $separacion = ($ancho - 260) / ($cantidad - 1);
    for ($i = 0; $i < $cantidad; $i++) {
        $x = (int) (130 + $i * $separacion);
        $largo = 190 + mt_rand(0, 280);
        imagesetthickness($img, 6);
        imageline($img, $x, 120, $x, 120 + $largo, $cCordon);

        $nudos = mt_rand(2, 5);
        for ($k = 1; $k <= $nudos; $k++) {
            $yNudo = 120 + (int) ($largo * $k / ($nudos + 1));
            $radio = mt_rand(11, 17);
            imagefilledellipse($img, $x, $yNudo, $radio * 2, $radio * 2, $k % 2 === 0 ? $cA : $cB);
        }
    }

    $principal = $destino . "/demo_$n.webp";
    $miniatura = $destino . "/demo_{$n}_m.webp";
    imagewebp($img, $principal, 82);

    $mini = imagecreatetruecolor(480, 252);
    imagecopyresampled($mini, $img, 0, 0, 0, 0, 480, 252, $ancho, $alto);
    imagewebp($mini, $miniatura, 78);

    imagedestroy($img);
    imagedestroy($mini);
    printf("demo_%d.webp (%d KB) + miniatura (%d KB)\n", $n, filesize($principal) / 1024, filesize($miniatura) / 1024);
}
