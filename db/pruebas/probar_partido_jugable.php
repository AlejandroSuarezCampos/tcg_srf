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

// ---------------------------------------------------------------------------
echo "\n2. LA FÓRMULA DE RESOLUCIÓN\n";

$REF  = 80.0;   // stat_ref, el listón de élite
$TOPE = 100.0;  // habilidad_tope_pct, generoso de nacimiento

/* Un minijuego de ejecución de suelo 0.8 y techo 1.8 — los de Espiral de Fuego.
   Las cuatro filas son literalmente la tabla del spec §6.1. */
function fila($stat, $rend, $REF, $TOPE) {
	$mult = Partido::multiplicadorEjecucion(0.8, 1.8, Partido::factorStat($stat, $REF), $rend);
	return ["mult" => $mult, "valor" => Partido::valor($stat, $mult, 1.0, $TOPE)];
}

$flojoPerfecto  = fila(40, 1.0, $REF, $TOPE);
$buenoMediocre  = fila(80, 0.4, $REF, $TOPE);
$buenoPerfecto  = fila(80, 1.0, $REF, $TOPE);
$decentePerfect = fila(65, 1.0, $REF, $TOPE);

comprobar("Ataque 40 perfecto da x1.30 y valor 52",
	casi($flojoPerfecto["mult"], 1.30) && casi($flojoPerfecto["valor"], 52.0),
	sprintf("x%.2f -> %.1f", $flojoPerfecto["mult"], $flojoPerfecto["valor"]));
comprobar("Ataque 80 mediocre da x1.20 y valor 96",
	casi($buenoMediocre["mult"], 1.20) && casi($buenoMediocre["valor"], 96.0),
	sprintf("x%.2f -> %.1f", $buenoMediocre["mult"], $buenoMediocre["valor"]));
comprobar("Ataque 80 perfecto da x1.80 y valor 144",
	casi($buenoPerfecto["mult"], 1.80) && casi($buenoPerfecto["valor"], 144.0),
	sprintf("x%.2f -> %.1f", $buenoPerfecto["mult"], $buenoPerfecto["valor"]));
comprobar("Ataque 65 perfecto da x1.61 y valor 105",
	casi($decentePerfect["mult"], 1.6125, 0.001) && casi($decentePerfect["valor"], 104.8125, 0.001),
	sprintf("x%.4f -> %.4f", $decentePerfect["mult"], $decentePerfect["valor"]));

/* INVARIANTE 2 — PRIMACÍA DEL EQUIPO. Si esto se rompe, el mercado se cae:
   un mazo flojo jugado perfecto tumbaría a uno bueno jugado mal. */
comprobar("INVARIANTE: mazo flojo perfecto PIERDE contra mazo bueno mediocre",
	$flojoPerfecto["valor"] < $buenoMediocre["valor"],
	sprintf("%.1f < %.1f", $flojoPerfecto["valor"], $buenoMediocre["valor"]));

/* Pero la habilidad tiene que servir para algo: con una diferencia moderada de
   equipo, jugar perfecto remonta a jugar a medias. */
comprobar("pero mazo decente perfecto SUPERA a mazo bueno mediocre",
	$decentePerfect["valor"] > $buenoMediocre["valor"],
	sprintf("%.1f > %.1f", $decentePerfect["valor"], $buenoMediocre["valor"]));

comprobar("el factor de stat topa en 1 aunque la stat supere la referencia",
	casi(Partido::factorStat(200, $REF), 1.0));
comprobar("una stat de 0 no da factor negativo",
	casi(Partido::factorStat(0, $REF), 0.0));
comprobar("stat_ref a 0 no divide por cero",
	casi(Partido::factorStat(50, 0), 1.0));

comprobar("no ejecutar deja el multiplicador en el suelo",
	casi(Partido::multiplicadorEjecucion(0.8, 1.8, 1.0, 0.0), 0.8));

comprobar("la capa elemental multiplica sobre el valor ya calculado",
	casi(Partido::valor(80, 1.8, 1.4, $TOPE), 160.0),
	"144 x 1.4 = 201.6, recortado por el tope a 160");

comprobar("la red de seguridad recorta por arriba",
	casi(Partido::valor(100, 5.0, 1.0, 50.0), 150.0));
comprobar("la red de seguridad recorta por abajo",
	casi(Partido::valor(100, 0.1, 1.0, 50.0), 50.0));

// ---------------------------------------------------------------------------
echo "\n3. EL RENDIMIENTO DE UN TRAZO\n";

/* Genera una curva (un arco de circunferencia) muestreada en $n puntos. La
   GEOMETRÍA es idéntica en todos los casos; lo único que cambia es cuántas
   veces la ha muestreado el dispositivo. Eso es exactamente la diferencia
   entre un móvil a 20 fps y uno a 120. */
function arco($n, $desvio = 0.0) {
	$p = [];
	for ($i = 0; $i < $n; $i++) {
		$a = M_PI * $i / ($n - 1);
		$p[] = ["x" => 100 + 100 * cos($a), "y" => 100 + 100 * sin($a) + $desvio, "t" => $i * 10];
	}
	return $p;
}

$ideal = arco(60);

comprobar("remuestrear devuelve siempre el número de puntos pedido",
	count(Partido::remuestrear(arco(7), 32)) === 32);
comprobar("remuestrear conserva el primer y el último punto",
	casi(Partido::remuestrear($ideal, 32)[0]["x"], $ideal[0]["x"], 0.001)
	&& casi(Partido::remuestrear($ideal, 32)[31]["x"], $ideal[59]["x"], 0.001));

comprobar("un trazo idéntico al ideal da rendimiento 1",
	casi(Partido::rendimientoTrazo($ideal, $ideal, 30.0), 1.0));

/* INVARIANTE 1 — JUSTICIA DE HARDWARE. La misma curva muestreada a 20 y a 120
   puntos tiene que puntuar igual. Si esto se rompe, tener peor móvil pasa a
   ser tener peor balance, que es la trampa que este diseño existe para evitar. */
$lento  = Partido::rendimientoTrazo($ideal, arco(20),  30.0);
$rapido = Partido::rendimientoTrazo($ideal, arco(120), 30.0);
comprobar("INVARIANTE: 20 fps y 120 fps puntúan igual el mismo trazo",
	casi($lento, $rapido, 0.01),
	sprintf("20 pts: %.4f  /  120 pts: %.4f", $lento, $rapido));

/* Y lo mismo con un trazo IMPERFECTO: la independencia no puede depender de
   que el jugador lo haya hecho bien. */
$malLento  = Partido::rendimientoTrazo($ideal, arco(20,  15.0), 30.0);
$malRapido = Partido::rendimientoTrazo($ideal, arco(120, 15.0), 30.0);
comprobar("INVARIANTE: también con un trazo torcido",
	casi($malLento, $malRapido, 0.01),
	sprintf("20 pts: %.4f  /  120 pts: %.4f", $malLento, $malRapido));

comprobar("desviarse la mitad de la tolerancia da alrededor de medio rendimiento",
	casi(Partido::rendimientoTrazo($ideal, arco(60, 15.0), 30.0), 0.5, 0.05));
comprobar("desviarse más que la tolerancia da rendimiento 0",
	casi(Partido::rendimientoTrazo($ideal, arco(60, 400.0), 30.0), 0.0));

comprobar("un trazo vacío da rendimiento 0",
	casi(Partido::rendimientoTrazo($ideal, [], 30.0), 0.0));
comprobar("un trazo de un solo punto da rendimiento 0",
	casi(Partido::rendimientoTrazo($ideal, [["x" => 100, "y" => 100, "t" => 0]], 30.0), 0.0));
comprobar("tolerancia 0 no divide por cero",
	casi(Partido::rendimientoTrazo($ideal, $ideal, 0.0), 0.0));
comprobar("un dedo que no se mueve da rendimiento 0",
	casi(Partido::rendimientoTrazo($ideal, [
		["x" => 100, "y" => 100, "t" => 0], ["x" => 100, "y" => 100, "t" => 10],
	], 30.0), 0.0));

echo "\n" . ($fallos === 0 ? "TODO CORRECTO\n\n" : "$fallos COMPROBACIONES FALLIDAS\n\n");
exit($fallos === 0 ? 0 : 1);
