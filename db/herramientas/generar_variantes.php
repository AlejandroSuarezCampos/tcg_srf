<?php
/**
 * VARIANTES DE ARTE PARA `srcset`.
 *
 *   C:\xampp\php\php.exe db/herramientas/generar_variantes.php          (simula)
 *   C:\xampp\php\php.exe db/herramientas/generar_variantes.php --aplica (escribe)
 *
 * ⚠️ NO GENERA VARIANTES DE TODO, Y ESO ES LA MITAD DEL TRABAJO.
 *
 *    La auditoría decía «ninguna imagen lleva srcset, el móvil se baja el arte a
 *    tamaño de escritorio». Al medir el catálogo de verdad resultó que
 *    **507 de las 538 ilustraciones ya miden 256 px y pesan unos 8 KB**: para
 *    esas, un `srcset` no ahorra nada, porque la carta se pinta entre 150 y
 *    280 px y 256 es justo el tamaño bueno. Generarles variantes sería crear
 *    1.000 ficheros para no ganar un byte.
 *
 *    El peso está concentrado en **31 cartas (5,8 %) que se llevan el 46 % de
 *    los megas**: son de 760 a 1130 px y pesan entre 150 y 190 KB. Todas
 *    Legendario, SRF o Numerada — es decir, justo las que salen destacadas en
 *    la portada y las que la gente abre en su ficha.
 *
 *    Así que solo se reduce lo que de verdad está sobredimensionado, y el resto
 *    se deja en paz.
 *
 * Las variantes se escriben AL LADO del original, con sufijo `-256w` / `-512w`.
 * Es seguro: los dos escáneres que recorren `assets/img` —el mantenimiento y la
 * subida de imágenes— solo miran ficheros `png/jpg/jpeg`, así que un `.webp`
 * nuevo no los confunde. Y el original NUNCA se toca: es la copia maestra.
 */
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

require_once __DIR__ . '/../conexion.php';

/** Por debajo de esto la imagen ya está al tamaño al que se pinta: no hay nada que recortar. */
const ANCHO_MINIMO_PARA_VARIAR = 320;

/** 256 cubre la carta a 1x; 512 la cubre en pantallas de doble densidad. */
const ANCHOS = [256, 512];

const CALIDAD = 82;

$aplica = in_array('--aplica', $argv, true);
$raiz   = dirname(__DIR__, 2);
$imgDir = $raiz . '/assets/img';

if (!is_dir($imgDir)) { fwrite(STDERR, "No existe assets/img\n"); exit(1); }

/** ¿Es este fichero una variante generada, y no un original? */
$esVariante = fn(string $ruta): bool => (bool) preg_match('/-\d+w\.webp$/i', $ruta);

$revisadas = 0; $saltadas = 0; $escritas = 0; $alDia = 0; $fallos = [];
$pesoOriginal = 0; $pesoVariante = 0;

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($imgDir, FilesystemIterator::SKIP_DOTS));

foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'webp') { continue; }

    $origen = str_replace('\\', '/', $f->getPathname());
    if ($esVariante($origen)) { continue; }

    $revisadas++;

    $medidas = @getimagesize($origen);
    if (!$medidas) { $fallos[] = basename($origen) . ' (no se puede leer)'; continue; }

    [$ancho, $alto] = $medidas;
    if ($ancho < ANCHO_MINIMO_PARA_VARIAR) { $saltadas++; continue; }

    foreach (ANCHOS as $w) {
        // Una variante más ancha que el original sería un reescalado hacia
        // arriba: más peso y peor imagen. No se genera.
        if ($w >= $ancho) { continue; }

        $destino = preg_replace('/\.webp$/i', "-{$w}w.webp", $origen);

        // Si ya existe y es más nueva que el original, está al día.
        if (is_file($destino) && filemtime($destino) >= filemtime($origen)) {
            $alDia++;
            $pesoOriginal += $f->getSize();
            $pesoVariante += filesize($destino);
            continue;
        }

        if (!$aplica) {
            $escritas++;
            $pesoOriginal += $f->getSize();
            // En simulación se estima por área: sirve para decidir, no para publicar.
            $pesoVariante += (int) round($f->getSize() * ($w / $ancho) ** 2);
            continue;
        }

        $im = @imagecreatefromwebp($origen);
        if (!$im) { $fallos[] = basename($origen) . ' (no se pudo abrir)'; continue; }

        $nuevo = imagescale($im, $w, (int) round($alto * $w / $ancho), IMG_BICUBIC_FIXED);
        imagedestroy($im);
        if (!$nuevo) { $fallos[] = basename($origen) . ' (no se pudo escalar)'; continue; }

        // El arte de carta lleva transparencia en los bordes: sin esto se
        // rellenaría de negro al guardar.
        imagealphablending($nuevo, false);
        imagesavealpha($nuevo, true);

        $ok = imagewebp($nuevo, $destino, CALIDAD);
        imagedestroy($nuevo);

        if (!$ok) { $fallos[] = basename($destino) . ' (no se pudo escribir)'; continue; }

        $escritas++;
        $pesoOriginal += $f->getSize();
        $pesoVariante += filesize($destino);
    }
}

echo $aplica ? "VARIANTES GENERADAS\n" : "SIMULACIÓN (no se ha escrito nada; añade --aplica)\n";
echo str_repeat('-', 68), "\n";
printf("  ilustraciones revisadas      %d\n", $revisadas);
printf("  ya estaban al tamaño bueno   %d  (menos de %d px de ancho)\n", $saltadas, ANCHO_MINIMO_PARA_VARIAR);
printf("  variantes %-18s %d\n", $aplica ? 'escritas' : 'que se escribirían', $escritas);
if ($alDia)  { printf("  variantes ya al día          %d\n", $alDia); }

if ($pesoOriginal > 0) {
    printf("\n  lo que se sirve hoy en esas cartas   %.1f MB\n", $pesoOriginal / 1048576);
    printf("  lo que se serviría con la variante   %.1f MB  (%.0f %% menos)\n",
        $pesoVariante / 1048576, 100 - 100 * $pesoVariante / $pesoOriginal);
}

if ($fallos) {
    echo "\n  fallos:\n";
    foreach (array_slice($fallos, 0, 10) as $x) { echo "    - $x\n"; }
    if (count($fallos) > 10) { printf("    … y %d más\n", count($fallos) - 10); }
}

echo "\nEl original no se toca nunca: es la copia maestra y la que sigue en la base de datos.\n";
