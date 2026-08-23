<?php
/**
 * ASIGNA LA COMPO QUE FALTA A LAS CARTAS QUE SE QUEDARON SIN NINGUNA,
 * desde la consola.
 *
 * Lo mismo que hace el botón de `panel/mantenimiento.php`, y con el MISMO
 * código: la lógica vive en `mantenimiento.php`.
 *
 *   C:\xampp\php\php.exe db/herramientas/reparar_compos.php            (simula)
 *   C:\xampp\php\php.exe db/herramientas/reparar_compos.php --aplica   (hazlo)
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/mantenimiento.php';

$aplica = in_array('--aplica', $argv, true);

echo $aplica
    ? "APLICANDO\n\n"
    : "SIMULACIÓN — no se toca nada. Añade --aplica para hacerlo de verdad.\n\n";

$r = mantenimientoCompos($db, $aplica);

if (!$r['pendientes']) {
    echo "No hay ninguna carta jugable sin compo. Nada que hacer.\n";
    exit(0);
}

printf("Cartas jugables sin compo: %d\n\n", count($r['pendientes']));
foreach (array_slice($r['pendientes'], 0, 25) as $c) {
    printf("  %-32s %-4s %-10s %s\n", $c['nombre'], $c['posicion'], $c['afinidad'],
        $c['solo_cadena'] ? '(exclusiva de cadena)' : '');
}
if (count($r['pendientes']) > 25) { printf("  … y %d más\n", count($r['pendientes']) - 25); }

if (!$aplica) {
    echo "\nNada de esto se ha hecho todavía. Repite con --aplica.\n";
    exit(0);
}

printf("\nCartas repasadas: %d\n", $r['tocadas']);
printf("Cartas que siguen sin compo: %d\n", count($r['quedan']));

if ($r['quedan']) {
    echo "\nSe quedan sin compo a propósito (sin afinidad real):\n";
    foreach (array_slice($r['quedan'], 0, 15) as $c) {
        printf("  %-32s %-4s %s\n", $c['nombre'], $c['posicion'], $c['afinidad']);
    }
}
