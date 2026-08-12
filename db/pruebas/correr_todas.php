<?php
/**
 * LANZADOR DE LAS PRUEBAS DEL PARTIDO. Un solo comando:
 *
 *   C:\xampp\php\php.exe db/pruebas/correr_todas.php
 *
 * ⚠️ NUNCA TOCA LA BASE REAL. Antes de cada suite recrea `tcg_prueba` desde
 * `tcg` con mysqldump, y ahí es donde se juega todo. Es la regla del §8: las
 * pruebas que escriben van sobre una copia desechable.
 *
 * También apaga en la copia el interruptor `depuracion_forzar_empate`, porque si
 * la base real lo tiene puesto (fuerza 1-1 para probar la tanda a mano) las
 * suites medirían una distribución de marcadores falsa.
 *
 * `probar_300.php` no entra aquí: tarda unos 7 minutos. Se lanza aparte cuando se
 * ha tocado algo del partido y hace falta la comprobación grande.
 */

$SUITES = [
    "probar_liquidar" => "la liquidación y la idempotencia del bote",
];

$mysql    = "C:\\xampp\\mysql\\bin\\mysql.exe";
$mysqldmp = "C:\\xampp\\mysql\\bin\\mysqldump.exe";
$php      = PHP_BINARY;

function correr($cmd) { $s = 0; passthru($cmd, $s); return $s; }

echo "Recreando la copia desechable y corriendo " . count($SUITES) . " suites.\n";
echo "La base real (tcg) no se toca en ningún momento.\n\n";

$fallos = [];
foreach ($SUITES as $suite => $queCubre) {
    // Copia limpia por suite: una suite que deja basura no debe ensuciar a la
    // siguiente, y varias fuerzan estados raros a propósito.
    correr("\"$mysql\" -u root -e \"DROP DATABASE IF EXISTS tcg_prueba; CREATE DATABASE tcg_prueba CHARACTER SET utf8mb4;\"");
    correr("\"$mysqldmp\" -u root tcg | \"$mysql\" -u root tcg_prueba");
    correr("\"$mysql\" -u root tcg_prueba -e \""
        . "UPDATE configuracion SET valor='0' WHERE clave='depuracion_forzar_empate';"
        . "UPDATE configuracion SET valor='12' WHERE clave='tanda_plazo_seg';\"");

    echo str_repeat("=", 72) . "\n";
    echo strtoupper($suite) . " — $queCubre\n";
    echo str_repeat("=", 72) . "\n";
    $codigo = correr("\"$php\" " . escapeshellarg(__DIR__ . "/$suite.php"));
    if ($codigo !== 0) $fallos[] = $suite;
    echo "\n";
}

correr("\"$mysql\" -u root -e \"DROP DATABASE IF EXISTS tcg_prueba;\"");

echo str_repeat("=", 72) . "\n";
if ($fallos) {
    echo "FALLARON: " . implode(", ", $fallos) . "\n";
    exit(1);
}
echo "Las " . count($SUITES) . " suites en verde. Copia borrada.\n";
echo "Falta, si has tocado el partido: php db/pruebas/probar_300.php (unos 7 min,\n";
echo "y hay que montar tcg_prueba a mano antes).\n";
exit(0);
