<?php
/**
 * GENERAR LA IMAGEN SOCIAL (og:image) — 1200×630.
 *
 *   C:\xampp\php\php.exe db/herramientas/generar_og.php
 *
 * Es la imagen que sale cuando alguien pega un enlace del juego en Discord, en
 * X o en WhatsApp. Antes no había ninguna y el enlace salía en blanco: sin
 * imagen, sin título y sin descripción, justo en el momento en que alguien
 * estaba recomendando el juego. Para un proyecto que se distribuye por Discord
 * eso era el escaparate apagado.
 *
 * Se genera con el arte que ya existe en lugar de encargar un diseño: coge las
 * tres cartas de más rareza que haya en la base y las compone sobre el fondo de
 * carbón y brasa del sistema Ascua (assets/css/tokens.css). Volver a lanzarlo
 * la regenera con las cartas de más rareza del momento.
 *
 * 1200×630 es lo que piden Facebook, Discord, X y LinkedIn para la vista
 * grande; por debajo de 600×315 se degrada a miniatura pequeña.
 */

require_once __DIR__ . '/../conexion.php';

const ANCHO = 1200;
const ALTO  = 630;

$destino = __DIR__ . '/../../assets/img/og-portada.png';

// Paleta: los mismos valores de assets/css/tokens.css, no aproximaciones.
$NEGRO  = [0x08, 0x07, 0x0A];
$CARBON = [0x1A, 0x18, 0x1D];
$BRASA  = [0xFF, 0x5C, 0x1A];
$BRASA2 = [0xFF, 0x8A, 0x3D];
$HUESO  = [0xF5, 0xF3, 0xF0];
$CENIZA = [0xA0, 0x9C, 0xA6];

$fuenteNegra = 'C:/Windows/Fonts/seguibl.ttf';   // Segoe UI Black
$fuenteBold  = 'C:/Windows/Fonts/segoeuib.ttf';  // Segoe UI Bold
foreach ([$fuenteNegra, $fuenteBold] as $f) {
    if (!is_file($f)) { fwrite(STDERR, "No encuentro la fuente $f\n"); exit(1); }
}

$im = imagecreatetruecolor(ANCHO, ALTO);
imagealphablending($im, true);
imagesavealpha($im, true);

$col = fn(array $c, int $a = 0) => imagecolorallocatealpha($im, $c[0], $c[1], $c[2], $a);

// ---- Fondo: carbón con un rescoldo cálido abajo a la izquierda -------------
imagefilledrectangle($im, 0, 0, ANCHO, ALTO, $col($NEGRO));

/* El "rescoldo" del sistema: un halo de brasa que se apaga hacia fuera. Se
   pinta con círculos concéntricos porque GD no tiene degradados radiales. */
$cx = 150; $cy = ALTO + 60; $r = 620;
for ($i = $r; $i > 0; $i -= 3) {
    $t = $i / $r;                       // 1 en el borde, 0 en el centro
    $alpha = (int) round(118 + $t * 9); // casi transparente fuera, cálido dentro
    imagefilledellipse($im, $cx, $cy, $i * 2, (int) ($i * 1.5), $col($BRASA, min(127, $alpha)));
}

// Trama técnica: líneas finísimas, el mismo recurso que la portada.
for ($x = 0; $x < ANCHO; $x += 40) { imageline($im, $x, 0, $x, ALTO, $col($HUESO, 122)); }
for ($y = 0; $y < ALTO; $y += 40) { imageline($im, 0, $y, ANCHO, $y, $col($HUESO, 122)); }

// ---- Las tres cartas más raras que haya --------------------------------- */
$cartas = $db->listarDestacados(3);

$puestas = 0;
$baseX = 700;
foreach ($cartas as $i => $c) {
    $ruta = __DIR__ . '/../../' . ltrim((string) ($c['imagen'] ?? ''), './');
    if (($c['imagen'] ?? '') === '' || !is_file($ruta)) { continue; }

    $arte = @imagecreatefromwebp($ruta);
    if (!$arte) { continue; }

    // Cada carta un poco más pequeña y más abajo, en abanico.
    $anchoCarta = (int) (300 - $i * 26);
    $altoCarta  = (int) ($anchoCarta * imagesy($arte) / imagesx($arte));
    $x = $baseX + $i * 150;
    $y = (int) (ALTO / 2 - $altoCarta / 2 + $i * 34);

    // Marco de brasa: dos rectángulos, uno de sombra y otro de filo.
    imagefilledrectangle($im, $x - 6, $y - 6, $x + $anchoCarta + 6, $y + $altoCarta + 6, $col($CARBON));
    imagerectangle($im, $x - 6, $y - 6, $x + $anchoCarta + 6, $y + $altoCarta + 6, $col($BRASA, 60));

    imagecopyresampled($im, $arte, $x, $y, 0, 0, $anchoCarta, $altoCarta, imagesx($arte), imagesy($arte));
    imagedestroy($arte);
    $puestas++;
}

/* Un velo oscuro por la izquierda para que el texto se lea SIEMPRE, sea cual
   sea el arte que toque ese día. Sin esto, una carta clara detrás del titular
   lo dejaba ilegible. */
for ($x = 0; $x < 760; $x++) {
    $a = (int) round(127 * min(1, $x / 760) * 0.92);
    imageline($im, $x, 0, $x, ALTO, $col($NEGRO, $a));
}

// ---- Texto ---------------------------------------------------------------
$texto = function (string $t, int $x, int $y, int $tam, array $color, string $fuente, float $espaciado = 0) use ($im, $col) {
    if ($espaciado == 0) { imagettftext($im, $tam, 0, $x, $y, $col($color), $fuente, $t); return; }
    // GD no sabe de letter-spacing: se dibuja letra a letra.
    foreach (preg_split('//u', $t, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        imagettftext($im, $tam, 0, $x, $y, $col($color), $fuente, $ch);
        $caja = imagettfbbox($tam, 0, $fuente, $ch);
        $x += ($caja[2] - $caja[0]) + $espaciado;
    }
};

$texto('SUPERLIGA FRONTIER', 72, 218, 17, $BRASA2, $fuenteBold, 5.5);

$texto('Cada jugador',        68, 310, 62, $HUESO, $fuenteNegra);
$texto('de la liga,',         68, 386, 62, $HUESO, $fuenteNegra);
$texto('una carta',           68, 462, 62, $BRASA2, $fuenteNegra);

$total = (int) ($db->estadisticasPublicas()['fichas'] ?? 0);
$texto(number_format($total, 0, ',', '.') . ' fichas · siete rarezas · duelos entre jugadores',
       70, 524, 19, $CENIZA, $fuenteBold);

// Filo de brasa arriba, como la "linea-campo" del sitio.
imagefilledrectangle($im, 0, 0, ANCHO, 5, $col($BRASA));

imagepng($im, $destino, 9);
imagedestroy($im);

printf("Escrita %s  (%d×%d, %.0f KB, %d cartas)\n",
    str_replace('\\', '/', realpath($destino)), ANCHO, ALTO, filesize($destino) / 1024, $puestas);
