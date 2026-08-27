<?php
/**
 * QUÉ CARTAS SE PINTAN CON PLANTILLA.
 *
 * `carta_usa_marco()` decidía por CARPETA (`/Cromos/Importados/`) y ahora
 * decide por FORMA: una imagen cuadrada es un retrato y cabe en el hueco de la
 * plantilla; una vertical es arte a sangre y se recortaría.
 *
 * No toca la base de datos: recorre `assets/img/Cromos/` y pregunta por cada
 * fichero. Se ejecuta con:
 *
 *     php db/pruebas/probar_marco_cuadrado.php
 *
 * Lo que se comprueba:
 *   1. TODO lo importado sigue con marco. Es la regla vieja: si alguna se
 *      queda fuera, el cambio ha roto lo que ya funcionaba.
 *   2. Las cuadradas de fuera de `Importados` AHORA llevan marco. Es lo que se
 *      venía a arreglar; sin esto el cambio no ha hecho nada.
 *   3. Ningún arte a sangre (vertical o apaisado) se mete en la plantilla.
 *      Es el daño que hay que evitar: recortaría justo lo dibujado.
 *   4. Una ruta que no existe no revienta y cae a "sin marco".
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

require_once __DIR__ . "/../../components/carta.php";

$RAIZ = dirname(__DIR__, 2);
$DIR  = $RAIZ . "/assets/img/Cromos";

/* Rareza 1 (Común): existe su plantilla, así que la decisión depende solo de
   la forma, que es lo que se quiere medir. */
const RAREZA = 1;

$fallos = 0;
function comprobar(string $que, bool $ok, string $detalle = "") {
    global $fallos;
    if (!$ok) { $fallos++; }
    printf("  [%s] %s%s\n", $ok ? "ok" : "FALLA", $que, $detalle ? "  — $detalle" : "");
}

if (!is_dir($DIR)) {
    echo "\nNo hay carpeta de cromos en $DIR — nada que comprobar.\n\n";
    exit(0);
}

$importadasSinMarco = [];
$cuadradasSinMarco  = [];
$artesConMarco      = [];
$conMarco = 0; $total = 0;

$iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($DIR));
foreach ($iterador as $fichero) {
    if (!$fichero->isFile()) { continue; }
    if (!preg_match('/\.(png|jpe?g|webp)$/i', $fichero->getFilename())) { continue; }

    $absoluta = str_replace('\\', '/', $fichero->getPathname());
    $web = './' . ltrim(str_replace(str_replace('\\', '/', $RAIZ), '', $absoluta), '/');

    $medidas = @getimagesize($absoluta);
    if (!$medidas) { continue; }
    [$w, $h] = $medidas;

    $esCuadrada = abs($w - $h) <= max($w, $h) * 0.02;
    $esImportada = stripos($web, '/Cromos/Importados/') !== false;
    $usa = carta_usa_marco(RAREZA, $web);

    $total++;
    if ($usa) { $conMarco++; }

    if ($esImportada && !$usa)              { $importadasSinMarco[] = $web; }
    if ($esCuadrada && !$usa)               { $cuadradasSinMarco[]  = $web; }
    if (!$esCuadrada && $usa)               { $artesConMarco[]      = "$web ({$w}x{$h})"; }
}

echo "\n$total imágenes de cromo · $conMarco con plantilla\n\n";

comprobar("todo lo importado conserva su plantilla",
    !$importadasSinMarco, count($importadasSinMarco) . " sin ella");
comprobar("toda imagen cuadrada usa plantilla",
    !$cuadradasSinMarco, count($cuadradasSinMarco) . " sin ella: " . implode(', ', array_slice($cuadradasSinMarco, 0, 3)));
comprobar("ningún arte a sangre entra en la plantilla",
    !$artesConMarco, implode(', ', array_slice($artesConMarco, 0, 3)));

// ---------------------------------------------------------------------------
comprobar("una ruta que no existe no revienta y va sin marco",
    carta_usa_marco(RAREZA, './assets/img/Cromos/no-existe/fantasma.webp') === false);
comprobar("una carta sin arte va sin marco",
    carta_usa_marco(RAREZA, '') === false);

echo "\n" . ($fallos === 0 ? "TODO CORRECTO\n\n" : "$fallos COMPROBACIONES FALLIDAS\n\n");
exit($fallos === 0 ? 0 : 1);
