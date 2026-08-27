<?php
/**
 * LAS JUGADAS EN LA BASE DE DATOS: apertura, decisión, ejecución y, sobre todo,
 * QUE NADA SE RESUELVA DOS VECES.
 *
 * Escribe, así que va sobre `tcg_prueba`. Lo lanza `correr_todas.php`.
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

/* Mismo montaje que `probar_liquidar.php`: se apunta a la copia desechable a
   mano, NO a `conexion.local.php`, que apunta a la base real. */
require_once __DIR__ . "/../consultas.php";
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");

$fallos = 0;
function comprobar($que, $ok, $detalle = "") {
	global $fallos;
	if (!$ok) { $fallos++; }
	printf("  [%s] %s%s\n", $ok ? "ok" : "FALLA", $que, $detalle !== "" ? "  — $detalle" : "");
}

/* Un duelo de laboratorio: dos usuarios y un duelo en juego. Se usa el mismo
   camino que usa el juego real para no probar un montaje que no existe. */
/* crearUsuarioAdmin() devuelve lastInsertId() SIN convertir a int (es un método
   general del panel, no de esta suite), y este archivo compara con === contra
   el id_poseedor —que sí sale ya convertido de la base—. Sin el cast aquí,
   "51" !== 51 y toda la lógica de "quién es el otro" se rompe en silencio. */
$idA = (int) $db->crearUsuarioAdmin("pruebaA_" . uniqid(), "x", 1000, 0);
$idB = (int) $db->crearUsuarioAdmin("pruebaB_" . uniqid(), "x", 1000, 0);
comprobar("se crean los dos usuarios de prueba", $idA > 0 && $idB > 0);

$idDuelo = $db->crearDueloDePrueba($idA, $idB);
comprobar("se crea el duelo de laboratorio", $idDuelo > 0);

echo "\n1. ABRIR JUGADA\n";

$r1 = $db->abrirJugada($idDuelo);
comprobar("abre la primera jugada", !empty($r1["ok"]) && (int) $r1["jugada"]["numero"] === 1);
comprobar("la primera jugada arranca en salida", $r1["jugada"]["zona"] === "salida");
comprobar("la jugada nace abierta", $r1["jugada"]["desenlace"] === null);

$r2 = $db->abrirJugada($idDuelo);
comprobar("IDEMPOTENCIA: volver a abrir devuelve la MISMA jugada, no crea otra",
	(int) $r2["jugada"]["numero"] === 1);

echo "\n2. DECIDIR LA ACCIÓN\n";

$poseedor = (int) $r1["jugada"]["id_poseedor"];
$otro     = $poseedor === $idA ? $idB : $idA;

$mal = $db->decidirAccion($idDuelo, $otro, 1, "pase_corto");
comprobar("el que NO tiene el balón no puede decidir la acción", empty($mal["ok"]));

$inventada = $db->decidirAccion($idDuelo, $poseedor, 1, "chilena_imposible");
comprobar("una acción que no existe en esa zona se rechaza", empty($inventada["ok"]));

$ok = $db->decidirAccion($idDuelo, $poseedor, 1, "pase_corto");
comprobar("el poseedor decide su acción", !empty($ok["ok"]));
comprobar("al decidir se fijan los dos minijuegos",
	$ok["jugada"]["mj_atacante"] !== null && $ok["jugada"]["mj_defensor"] !== null);

$otraVez = $db->decidirAccion($idDuelo, $poseedor, 1, "balon_largo");
comprobar("IDEMPOTENCIA: no se puede cambiar de opinión", empty($otraVez["ok"]));

echo "\n3. EJECUTAR Y RESOLVER\n";

$e1 = $db->registrarEjecucion($idDuelo, $poseedor, 1, ["opcion" => "izquierda"]);
comprobar("el atacante registra su ejecución", !empty($e1["ok"]));
comprobar("con un solo lado la jugada sigue abierta", empty($e1["resuelta"]));

$dup = $db->registrarEjecucion($idDuelo, $poseedor, 1, ["opcion" => "derecha"]);
comprobar("IDEMPOTENCIA: no se puede ejecutar dos veces", empty($dup["ok"]));

$e2 = $db->registrarEjecucion($idDuelo, $otro, 1, ["opcion" => "carga"]);
comprobar("con los dos lados la jugada se resuelve", !empty($e2["resuelta"]));
comprobar("la jugada resuelta tiene desenlace",
	in_array($e2["jugada"]["desenlace"], ["gol", "avanza", "area", "recupera"], true),
	(string) $e2["jugada"]["desenlace"]);
comprobar("la jugada resuelta guarda los dos valores",
	$e2["jugada"]["val_atacante"] !== null && $e2["jugada"]["val_defensor"] !== null);

echo "\n4. LA SIGUIENTE JUGADA\n";

$r3 = $db->abrirJugada($idDuelo);
comprobar("resuelta la primera, se abre la segunda", (int) $r3["jugada"]["numero"] === 2);
comprobar("el minuto avanza", (int) $r3["jugada"]["minuto"] > (int) $r1["jugada"]["minuto"]);

$esperada = Partido::zonaTras($r1["jugada"]["zona"], $e2["jugada"]["desenlace"]);
comprobar("la zona de la segunda sale del desenlace de la primera",
	$r3["jugada"]["zona"] === $esperada,
	"{$r3["jugada"]["zona"]} vs $esperada");

echo "\n5. EL ESTADO QUE VE EL SONDEO\n";

$est = $db->estadoPartido($idDuelo, $idA);
comprobar("el sondeo devuelve la jugada abierta", !empty($est["jugada"]));
comprobar("el sondeo dice si me toca decidir a mí", array_key_exists("decido_yo", $est));
comprobar("el sondeo lleva el marcador", array_key_exists("goles", $est));
comprobar("el sondeo NO filtra la ejecución del rival",
	!isset($est["jugada"]["rend_defensor"]) && !isset($est["jugada"]["opc_defensor"]));

echo "\n" . ($fallos === 0 ? "TODO CORRECTO\n\n" : "$fallos COMPROBACIONES FALLIDAS\n\n");
exit($fallos === 0 ? 0 : 1);
