<?php
/**
 * CONVIERTE A WEBP LAS IMÁGENES YA SUBIDAS, desde la consola.
 *
 * Lo mismo que hace el botón de `panel/mantenimiento.php`, y con el MISMO
 * código: la lógica vive en `mantenimiento.php` y aquí solo se imprime. Dos
 * copias de algo que borra archivos son dos versiones de lo que pasa al
 * pulsar, y solo se descubre cuando ya han divergido.
 *
 *   C:\xampp\php\php.exe db/herramientas/convertir_a_webp.php            (simula)
 *   C:\xampp\php\php.exe db/herramientas/convertir_a_webp.php --aplica   (hazlo)
 *
 * Sin `--aplica` no toca NADA: enseña qué haría y cuánto se ahorraría.
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/mantenimiento.php';

$aplica = in_array('--aplica', $argv, true);

echo $aplica
    ? "APLICANDO CAMBIOS\n\n"
    : "SIMULACIÓN — no se toca nada. Añade --aplica para hacerlo de verdad.\n\n";

$r = mantenimientoWebp($db, $aplica);

foreach ($r['archivos'] as $a) {
    printf("  %-56s %8.1f KB -> %7.1f KB\n", $a['ruta'], $a['antes'] / 1024, $a['despues'] / 1024);
}

echo "\n";
printf("Imágenes convertidas: %d\n", $r['convertidas']);
printf("Peso: %.2f MB -> %.2f MB  (%.0f %% menos)\n",
    $r['antes'] / 1048576, $r['despues'] / 1048576,
    $r['antes'] > 0 ? (1 - $r['despues'] / $r['antes']) * 100 : 0);
printf("Rutas %s en la base: %d\n", $aplica ? 'actualizadas' : 'por actualizar', $r['rutas']);

if ($r['fallos']) {
    echo "\nNo se pudieron convertir (se dejan como están):\n";
    foreach ($r['fallos'] as $x) { echo "  $x\n"; }
}
if (!$aplica) { echo "\nNada de esto se ha hecho todavía. Repite con --aplica.\n"; }
