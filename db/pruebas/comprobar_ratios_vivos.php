<?php
/**
 * ¿QUÉ RATIOS ESTÁN VIVOS DE VERDAD EN ESTA INSTALACIÓN?
 *
 * Existe porque «sigo sacando 2 SRF en 55 sobres» y «la tabla dice 1 cada 500»
 * son dos afirmaciones que no se pueden reconciliar discutiendo: hay que mirar.
 * Y hay tres cosas distintas que pueden estar desalineadas a la vez —el código
 * desplegado, los valores de la tabla `rarezas` y el tamaño de cada sobre—, así
 * que este comando las enseña las tres juntas.
 *
 *     php db/pruebas/comprobar_ratios_vivos.php
 *
 * Lo que responde:
 *   1. ¿El código desplegado sortea una vez POR SOBRE o una vez POR CARTA?
 *      Es la diferencia entre 0,2 % y 4,9 % en un sobre de diez.
 *   2. ¿Qué dice `rarezas.probabilidad` ahora mismo? (¿está aplicada la 050?)
 *   3. Para cada sobre a la venta, qué probabilidad real tiene de traer cada
 *      rareza alta, y a cuántas cartas equivale.
 *
 * Si el número 1 dice «por carta», lo que falta es subir `db/consultas.php`.
 * Si el 2 enseña 0.50 en el SRF, lo que falta es correr la migración 050.
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

require_once __DIR__ . "/../conexion.php";

function titulo($t) { echo "\n" . $t . "\n" . str_repeat("-", strlen($t)) . "\n"; }
function unoDeCada($pct) { return $pct > 0 ? number_format(100 / $pct, 0, ",", ".") : "∞"; }

/* --- 1. ¿qué código hay desplegado? ------------------------------------- */
titulo("1. CÓDIGO");

$tienePorSobre  = method_exists("Tcg", "sortearPremio") || defined("Tcg::RAREZA_PREMIUM");
$tieneReferencia = defined("Tcg::SOBRE_REFERENCIA");

$refl = new ReflectionClass("Tcg");
$fuente = file_get_contents($refl->getFileName());

/* La conexión de `Tcg` es privada. Se toma prestada por reflexión en vez de
   abrir una segunda: así este comando mira EXACTAMENTE la misma base que usa
   el juego, que es justo lo que se quiere comprobar. Vale para un diagnóstico;
   no se hace en ningún sitio del código de verdad. */
$prop = $refl->getProperty("pdo");
$prop->setAccessible(true);
/** @var PDO $pdo */
$pdo = $prop->getValue($db);
$unaTiradaPorSobre = strpos($fuente, "sortearPremio") !== false;
$escalaPorTamano   = strpos($fuente, "SOBRE_REFERENCIA") !== false;

printf("  sorteo una vez por SOBRE ......... %s\n", $unaTiradaPorSobre ? "sí" : "NO — sortea por CARTA, la probabilidad se multiplica por el tamaño del sobre");
printf("  escala con el tamaño del sobre ... %s\n", $escalaPorTamano ? "sí" : "no");
if ($tieneReferencia) {
	printf("  sobre de referencia .............. %d cartas\n", Tcg::SOBRE_REFERENCIA);
}
if (!$unaTiradaPorSobre) {
	echo "\n  ⚠️ Falta subir db/consultas.php. Con el sorteo por carta, un sobre de\n";
	echo "     diez cartas saca SRF el 4,9 % de las veces aunque la tabla diga 0,50.\n";
}

/* --- 2. la tabla --------------------------------------------------------- */
titulo("2. TABLA `rarezas` (lo que hay guardado ahora)");

$rarezas = $pdo->query("SELECT id_rareza, nombre, probabilidad FROM rarezas ORDER BY id_rareza")
                    ->fetchAll(PDO::FETCH_ASSOC);

$esperado = [4 => 1.0, 5 => 0.3333, 6 => 0.2, 7 => 0.1];
$desviadas = 0;
foreach ($rarezas as $r) {
	$id  = (int) $r["id_rareza"];
	$p   = (float) $r["probabilidad"];
	$nota = "";
	if (isset($esperado[$id])) {
		$ok = abs($p - $esperado[$id]) < 0.001;
		if (!$ok) { $desviadas++; }
		$nota = $ok ? "  ✓" : sprintf("  ✗ la 050 la deja en %.4f", $esperado[$id]);
	} else {
		$nota = "  (relleno: sale varias veces por sobre, no tiene «1 de cada N»)";
	}
	printf("  %d %-12s %8.4f %%%s\n", $id, $r["nombre"], $p, $nota);
}
if ($desviadas) {
	echo "\n  ⚠️ Falta correr db/migraciones/050_ratios_por_sobre_y_shawn.sql.\n";
}

/* --- 3. cada sobre a la venta -------------------------------------------- */
titulo("3. LO QUE SALE DE CADA SOBRE A LA VENTA");

$sobres = $pdo->query("
	SELECT s.id_sobre, s.nombre, s.cantidad, s.precio, s.id_expansion
	FROM sobre s WHERE s.activo = 1 ORDER BY s.id_sobre")->fetchAll(PDO::FETCH_ASSOC);

$ref = $tieneReferencia ? Tcg::SOBRE_REFERENCIA : 5;

foreach ($sobres as $s) {
	printf("\n  %s — %d cartas, %d monedas\n", $s["nombre"], $s["cantidad"], $s["precio"]);

	// Solo las rarezas que esa expansión tiene de verdad en el bombo.
	$hay = $pdo->prepare("
		SELECT DISTINCT c.id_rareza FROM cromos c
		WHERE c.id_expansion = :e AND c.solo_cadena = 0 AND c.en_sobres = 1");
	$hay->execute([":e" => $s["id_expansion"]]);
	$presentes = array_map("intval", $hay->fetchAll(PDO::FETCH_COLUMN));

	$escala = $escalaPorTamano ? ((int) $s["cantidad"] / $ref) : 1.0;

	foreach ($rarezas as $r) {
		$id = (int) $r["id_rareza"];
		if ($id < 4 || !in_array($id, $presentes, true)) { continue; }

		$porSobre = $unaTiradaPorSobre
			? (float) $r["probabilidad"] * $escala
			// código viejo: una tirada por carta, así que la probabilidad de
			// que NINGUNA de las N cartas sea de esta rareza es (1-p)^N
			: (1 - pow(1 - (float) $r["probabilidad"] / 100, (int) $s["cantidad"])) * 100;

		printf("      %-12s %6.3f %% por sobre   ·   1 cada %s sobres   ·   1 cada %s cartas\n",
			$r["nombre"], $porSobre, unoDeCada($porSobre),
			unoDeCada($porSobre / (int) $s["cantidad"]));
	}
}

echo "\n";
