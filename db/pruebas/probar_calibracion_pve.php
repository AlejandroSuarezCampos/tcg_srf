<?php
/**
 * Prueba la calibración de dificultad PvE (migración `033`) sobre la copia
 * DESECHABLE tcg_prueba (§8). Nunca toca la base real.
 *
 * Lo que se comprueba es que la calibración de verdad calibra, no que el botón
 * responda:
 *   1. El equipo de referencia se monta con las once posiciones cubiertas.
 *   2. La curva es MONÓTONA: más multiplicador nunca puede dar más victorias.
 *      Si dejara de serlo, la interpolación devolvería cualquier cosa.
 *   3. El espejo (×1) da ~50 %. Es la comprobación de cordura del simulador:
 *      un equipo contra sí mismo no puede tener ventaja.
 *   4. Los presets son monótonos entre sí: un preset más duro nunca deja ganar
 *      más en ninguna dificultad.
 *   5. Calibrar en global escribe los cinco `pve_mult_*` y el porcentaje MEDIDO
 *      cae cerca del objetivo. Es la prueba de fuego: si esto falla, el panel
 *      está prometiendo un número que el juego no da.
 *   6. El ancla que fijó Alejandro: el preset `normal` baja de veinte en
 *      veinte — 80 / 60 / 40 / 20 / 10 —, un número redondo por dificultad.
 *   7. Calibrar una cadena escribe un ajuste por nodo y dificultad, y los nodos
 *      sin alineación rival se REPORTAN en vez de calibrarse con ceros.
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

$p = new PDO("mysql:host=127.0.0.1;dbname=tcg_prueba;charset=utf8mb4", "root", "",
	[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

require __DIR__ . "/../consultas.php";
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");

$fallos = 0;
$ok = function ($m) { echo "  OK    $m\n"; };
$ko = function ($m) use (&$fallos) { echo "  FALLO $m\n"; $fallos++; };

/* Menos partidos que en producción: la suite tiene que correr en segundos, y
   lo que se prueba aquí es que el mecanismo funciona y no se descuelga, no la
   última décima del porcentaje. Las tolerancias de abajo van acordes. */
$p->exec("UPDATE configuracion SET valor = '150' WHERE clave = 'pve_calibrar_sims'");
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");

$ref = new ReflectionClass($db);
$priv = function ($metodo, ...$args) use ($db, $ref) {
	$m = $ref->getMethod($metodo);
	$m->setAccessible(true);
	return $m->invoke($db, ...$args);
};

echo "PRUEBA: calibración de dificultad PvE\n\n";

/* --- 1. El equipo de referencia ------------------------------------------ */
$cartas = $db->plantillaReferencia();
$fuerza = Tcg::fuerzaAlineacion($cartas, Tcg::FORMACION_BASE);

count($cartas) === Tcg::MAZO_TAMANO
	? $ok("el equipo de referencia tiene las " . Tcg::MAZO_TAMANO . " posiciones")
	: $ko("el equipo de referencia tiene " . count($cartas) . " cartas");

$lineasVacias = array_filter(["POR", "DF", "MC", "DC"], fn($l) => $fuerza[$l] <= 0);
empty($lineasVacias)
	? $ok("ninguna línea del equipo de referencia se queda a cero")
	: $ko("líneas a cero en el equipo de referencia: " . implode(",", $lineasVacias));

/* --- 2. La curva es monótona --------------------------------------------- */
$opciones = $priv("opcionesSimulacion");
$bando = $priv("bandoReferencia", Tcg::FORMACION_BASE);
$curva = $priv("curvaWinratePve", $bando, $bando, 150, $opciones);

$monotona = true;
for ($i = 1; $i < count($curva); $i++) {
	// Con 150 partidos hay ruido; se tolera un repunte de un punto, no más.
	if ($curva[$i]["wr"] > $curva[$i - 1]["wr"] + 0.01) { $monotona = false; }
}
$monotona
	? $ok("la curva baja siempre: más multiplicador, menos victorias")
	: $ko("la curva NO es monótona; la interpolación devolvería cualquier cosa");

/* --- 3. El espejo da ~50 % ----------------------------------------------- */
$espejo = $priv("winrateSimulado", $bando, $bando, 1.0, 600, $opciones);
abs($espejo - 0.5) < 0.06
	? $ok(sprintf("un equipo contra sí mismo gana el %.1f %% (≈50 %%)", $espejo * 100))
	: $ko(sprintf("el espejo da %.1f %%, y tiene que rondar el 50 %%", $espejo * 100));

/* --- 4. Los presets son monótonos entre sí -------------------------------- */
$orden = ["mas_facil", "normal", "dificil", "extremo"];
$rompe = [];
foreach (Tcg::DIFICULTADES as $dif) {
	for ($i = 1; $i < count($orden); $i++) {
		if (Tcg::PRESETS_PVE[$orden[$i]][$dif] > Tcg::PRESETS_PVE[$orden[$i - 1]][$dif]) {
			$rompe[] = "$dif entre {$orden[$i-1]} y {$orden[$i]}";
		}
	}
}
empty($rompe)
	? $ok("un preset más duro nunca deja ganar más en ninguna dificultad")
	: $ko("presets no monótonos: " . implode("; ", $rompe));

/* --- 6. El ancla de Alejandro -------------------------------------------- */
/* El preset `normal` es el que se juega por defecto y el que fija el tono de
   toda la tabla: 80 / 60 / 40 / 20 / 10. Si alguien lo mueve sin querer, aquí
   salta — los otros tres presets se colocan en relación a este. */
$ancla = ["facil" => 0.80, "medio" => 0.60, "dificil" => 0.40,
          "muy_dificil" => 0.20, "extremo" => 0.10];
$anclaOk = true;
foreach ($ancla as $dif => $esperado) {
	if (abs(Tcg::PRESETS_PVE["normal"][$dif] - $esperado) > 1e-9) { $anclaOk = false; }
}
$anclaOk
	? $ok("ancla: el preset normal es 80 / 60 / 40 / 20 / 10")
	: $ko("el ancla del preset normal se ha movido");

/* --- 5. Calibración global ------------------------------------------------ */
$r = $db->calibrarPveGlobal("normal");
if (!$r["ok"]) {
	$ko("la calibración global falló: " . $r["error"]);
} else {
	$ok("la calibración global responde");

	$escritos = 0;
	foreach (Tcg::DIFICULTADES as $dif) {
		$v = $p->query("SELECT valor FROM configuracion WHERE clave = 'pve_mult_$dif'")->fetchColumn();
		if ($v !== false && (float) $v > 0) { $escritos++; }
	}
	$escritos === count(Tcg::DIFICULTADES)
		? $ok("quedan escritos los " . count(Tcg::DIFICULTADES) . " multiplicadores")
		: $ko("solo se escribieron $escritos multiplicadores");

	$p->query("SELECT valor FROM configuracion WHERE clave = 'pve_preset'")->fetchColumn() === "normal"
		? $ok("queda guardado qué preset se aplicó")
		: $ko("no se guardó el preset aplicado");

	/* El multiplicador tiene que SUBIR con la dificultad: si Extremo pidiera
	   menos fuerza que Fácil, la escalera estaría del revés y el juego sería
	   más fácil cuanto más difícil se elige. */
	$anterior = 0;
	$escalera = true;
	foreach (Tcg::DIFICULTADES as $dif) {
		if ($r["dificultades"][$dif]["mult"] < $anterior) { $escalera = false; }
		$anterior = $r["dificultades"][$dif]["mult"];
	}
	$escalera
		? $ok("el multiplicador sube de Fácil a Extremo")
		: $ko("la escalera de dificultad está desordenada");

	/* Lo medido tiene que parecerse a lo pedido. Con 150 partidos el margen es
	   ancho a propósito —±10 puntos— porque lo que se prueba aquí es que la
	   calibración apunta a donde dice, no su última décima. */
	$desviados = [];
	foreach ($r["dificultades"] as $dif => $d) {
		if ($d["medido"] === null) { continue; }
		if (abs($d["medido"] - $d["objetivo"]) > 0.10) {
			$desviados[] = sprintf("%s pedía %.1f%% y da %.1f%%", $dif, $d["objetivo"] * 100, $d["medido"] * 100);
		}
	}
	empty($desviados)
		? $ok("lo medido cae cerca de lo pedido en las cinco dificultades")
		: $ko("se desvían: " . implode("; ", $desviados));
}

/* --- 7. Calibración de una cadena ----------------------------------------- */
$idCadena = $p->query("
	SELECT n.id_cadena FROM cadena_nodos n
	WHERE n.tipo = 'partido' AND n.id_rival IS NOT NULL
	GROUP BY n.id_cadena ORDER BY COUNT(*) DESC LIMIT 1
")->fetchColumn();

if (!$idCadena) {
	echo "  AVISO no hay ninguna cadena con nodos de partido: no se prueba la calibración por cadena.\n";
} else {
	$p->exec("DELETE d FROM cadena_nodo_dificultad d
	          JOIN cadena_nodos n USING(id_nodo) WHERE n.id_cadena = $idCadena");

	$rc = $db->calibrarPveCadena((int) $idCadena, "extremo");
	if (!$rc["ok"]) {
		$ko("la calibración de la cadena falló: " . $rc["error"]);
	} else {
		$esperadas = count($rc["nodos"]) * count(Tcg::DIFICULTADES);
		$filas = (int) $p->query("
			SELECT COUNT(*) FROM cadena_nodo_dificultad d
			JOIN cadena_nodos n USING(id_nodo) WHERE n.id_cadena = $idCadena
		")->fetchColumn();

		$filas === $esperadas
			? $ok("la cadena queda con un ajuste por nodo y dificultad ($filas)")
			: $ko("se esperaban $esperadas ajustes y hay $filas");

		$p->query("SELECT pve_preset FROM cadenas WHERE id_cadena = $idCadena")->fetchColumn() === "extremo"
			? $ok("la cadena recuerda con qué preset se calibró")
			: $ko("la cadena no guardó su preset");

		/* Un nodo sin alineación rival no se puede medir. Tiene que salir en
		   `saltados` y NO en `nodos`: calibrarlo a ciegas dejaría un agujero de
		   dificultad que nadie vería hasta jugarlo. */
		$sinAlineacion = array_column($rc["saltados"], "id_nodo");
		$calibrados    = array_column($rc["nodos"], "id_nodo");
		empty(array_intersect($sinAlineacion, $calibrados))
			? $ok("los nodos sin alineación rival se reportan, no se calibran")
			: $ko("hay nodos a la vez calibrados y reportados como saltados");
	}
}

/* --- preset inválido ------------------------------------------------------ */
$db->calibrarPveGlobal("no_existe")["ok"] === false
	? $ok("un preset inventado se rechaza")
	: $ko("se aceptó un preset que no existe");

echo "\n";
if ($fallos) { echo "FALLOS: $fallos\n"; exit(1); }
echo "Todo en verde.\n";
