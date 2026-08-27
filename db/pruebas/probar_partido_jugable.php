<?php
/**
 * EL MOTOR DE PARTIDO JUGABLE.
 *
 * No toca ninguna base de datos: `db/partido.php` son funciones puras, así que
 * aquí se prueba el MODELO con números conocidos.
 *
 *     C:\xampp\php\php.exe db/pruebas/probar_partido_jugable.php
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

require_once __DIR__ . "/../partido.php";

$fallos = 0;
function comprobar($que, $ok, $detalle = "") {
	global $fallos;
	if (!$ok) { $fallos++; }
	printf("  [%s] %s%s\n", $ok ? "ok" : "FALLA", $que, $detalle !== "" ? "  — $detalle" : "");
}
function casi($a, $b, $eps = 0.0001) { return abs($a - $b) < $eps; }

// ---------------------------------------------------------------------------
echo "\n1. EL CICLO ELEMENTAL\n";

comprobar("Fuego vence a Bosque",      casi(Partido::factorElemental("fuego", "bosque"), 1.4));
comprobar("Bosque vence a Viento",     casi(Partido::factorElemental("bosque", "viento"), 1.4));
comprobar("Viento vence a Montaña",    casi(Partido::factorElemental("viento", "montana"), 1.4));
comprobar("Montaña vence a Fuego",     casi(Partido::factorElemental("montana", "fuego"), 1.4));

comprobar("Bosque pierde contra Fuego", casi(Partido::factorElemental("bosque", "fuego"), 0.7));
comprobar("Fuego pierde contra Montaña", casi(Partido::factorElemental("fuego", "montana"), 0.7));

comprobar("Fuego contra Viento no es directa", casi(Partido::factorElemental("fuego", "viento"), 1.0));
comprobar("Bosque contra Montaña no es directa", casi(Partido::factorElemental("bosque", "montana"), 1.0));

comprobar("mismo elemento resuena", casi(Partido::factorElemental("fuego", "fuego"), 1.15));

// Las 16 combinaciones: 4 resonancias, 4 ventajas, 4 desventajas, 4 neutras.
$cuenta = ["1.15" => 0, "1.4" => 0, "0.7" => 0, "1" => 0];
foreach (Partido::ELEMENTOS as $a) {
	foreach (Partido::ELEMENTOS as $b) {
		$cuenta[(string) Partido::factorElemental($a, $b)]++;
	}
}
comprobar("las 16 combinaciones se reparten 4/4/4/4",
	$cuenta["1.15"] === 4 && $cuenta["1.4"] === 4 && $cuenta["0.7"] === 4 && $cuenta["1"] === 4,
	json_encode($cuenta));

comprobar("un elemento desconocido no rompe nada",
	casi(Partido::factorElemental("no-afi", "fuego"), 1.0));

echo "\n" . ($fallos === 0 ? "TODO CORRECTO\n\n" : "$fallos COMPROBACIONES FALLIDAS\n\n");
exit($fallos === 0 ? 0 : 1);
