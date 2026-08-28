<?php
/**
 * ¿SIGUE MANDANDO EL EQUIPO?
 *
 * Simula partidos enteros con la fórmula real de `Partido`, sin base de datos:
 * dos mazos, dos niveles de habilidad, y se mira quién gana. Es la comprobación
 * que dice si el motor cumple su promesa —que la habilidad importa pero el
 * equipo manda— antes de que lo note un jugador.
 *
 *     C:\xampp\php\php.exe db/pruebas/probar_balance_jugable.php
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

require_once __DIR__ . "/../partido.php";

$fallos = 0;
function comprobar($que, $ok, $detalle = "") {
	global $fallos;
	if (!$ok) { $fallos++; }
	printf("  [%s] %s%s\n", $ok ? "ok" : "FALLA", $que, $detalle !== "" ? "  — $detalle" : "");
}

const REF = 80.0, TOPE = 100.0, JUGADAS = 12, PARTIDOS = 300;

/** Un partido: 12 jugadas, cada bando ataca en la mitad. Devuelve [golesA, golesB]. */
function partido($statA, $habA, $statB, $habB, $semilla) {
	$g = [0, 0];
	for ($n = 1; $n <= JUGADAS; $n++) {
		$atacaA = ($n % 2) === 1;
		$sAt = $atacaA ? $statA : $statB;
		$hAt = $atacaA ? $habA  : $habB;
		$sDf = $atacaA ? $statB : $statA;
		$hDf = $atacaA ? $habB  : $habA;

		/* Ruido pequeño alrededor de la habilidad nominal: nadie ejecuta igual
		   dos veces, ni el mejor jugador del mundo. */
		$r = function ($h, $k) use ($semilla, $n) {
			$s = abs(crc32("$semilla|$n|$k"));
			return max(0.0, min(1.0, $h + (($s % 200) / 1000.0 - 0.1)));
		};

		$vAt = Partido::valor($sAt, Partido::multiplicadorEjecucion(
			0.8, 1.8, Partido::factorStat($sAt, REF), $r($hAt, "a")), 1.0, TOPE);
		$vDf = Partido::valor($sDf, Partido::multiplicadorEjecucion(
			0.8, 1.8, Partido::factorStat($sDf, REF), $r($hDf, "d")), 1.0, TOPE);

		if (Partido::desenlace($vAt, $vDf, "gol") === "gol") { $g[$atacaA ? 0 : 1]++; }
	}
	return $g;
}

/** Porcentaje de partidos que gana A. */
function victoriasA($statA, $habA, $statB, $habB) {
	$v = 0;
	for ($i = 0; $i < PARTIDOS; $i++) {
		[$a, $b] = partido($statA, $habA, $statB, $habB, $i);
		if ($a > $b) { $v++; }
	}
	return 100.0 * $v / PARTIDOS;
}

echo "\nBALANCE DEL MOTOR JUGABLE — " . PARTIDOS . " partidos por escenario\n\n";

$flojoPerfecto = victoriasA(40, 1.0, 80, 0.4);
printf("  Mazo 40 perfecto  vs  mazo 80 mediocre : %.1f %% para el flojo\n", $flojoPerfecto);
comprobar("el mazo flojo jugado perfecto casi nunca gana al bueno jugado mal",
	$flojoPerfecto < 10.0);

$decenteFrenteBueno = victoriasA(65, 1.0, 80, 0.4);
printf("  Mazo 65 perfecto  vs  mazo 80 mediocre : %.1f %% para el decente\n", $decenteFrenteBueno);
comprobar("pero el mazo decente jugado perfecto sí compite",
	$decenteFrenteBueno > 40.0);

$mismoMazo = victoriasA(70, 1.0, 70, 0.3);
printf("  Mismo mazo, mejor ejecución            : %.1f %% para el que juega bien\n", $mismoMazo);
comprobar("con el mismo mazo, jugar mejor gana claramente", $mismoMazo > 65.0);

$mismaHabilidad = victoriasA(80, 0.7, 55, 0.7);
printf("  Misma habilidad, mejor mazo            : %.1f %% para el mejor mazo\n", $mismaHabilidad);
comprobar("con la misma habilidad, el mejor mazo gana claramente", $mismaHabilidad > 65.0);

$gemelos = victoriasA(70, 0.6, 70, 0.6);
printf("  Espejo exacto                          : %.1f %%\n", $gemelos);
comprobar("dos iguales quedan cerca del 50 %", $gemelos > 30.0 && $gemelos < 70.0);

echo "\n" . ($fallos === 0 ? "TODO CORRECTO\n\n" : "$fallos COMPROBACIONES FALLIDAS\n\n");
exit($fallos === 0 ? 0 : 1);
