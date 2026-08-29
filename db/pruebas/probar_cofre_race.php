<?php
/**
 * RECLAMAR UN COFRE DOS VECES A LA VEZ NO PUEDE DUPLICAR EL BOTÍN.
 *
 * Encontrado en auditoría de seguridad: `reclamarCofre()` comprobaba "¿ya
 * reclamado?" ANTES de abrir la transacción y sin bloqueo de fila. Dos
 * peticiones simultáneas pasaban las dos ese filtro; el `INSERT IGNORE`
 * posterior no fallaba en la segunda (la PK lo evita en silencio), pero nadie
 * miraba `rowCount()`, así que el botín se entregaba dos veces.
 *
 * Se prueba con PROCESOS REALES en paralelo, no con dos llamadas seguidas en
 * el mismo proceso: dos llamadas seguidas nunca reproducen la carrera, porque
 * la segunda ve ya "reclamado" por el camino normal. Solo una carrera de
 * verdad ejercita la ventana entre la comprobación y el `INSERT IGNORE`.
 *
 *     C:\xampp\php\php.exe db/pruebas/probar_cofre_race.php
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

$dsn = "mysql:host=127.0.0.1;dbname=tcg_prueba;charset=utf8mb4";
$p = new PDO($dsn, "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
require __DIR__ . "/../consultas.php";
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");

$fallos = 0;
function comprobar($que, $ok, $detalle = "") {
	global $fallos;
	if (!$ok) { $fallos++; }
	printf("  [%s] %s%s\n", $ok ? "ok" : "FALLA", $que, $detalle !== "" ? "  — $detalle" : "");
}

$usuario = (int) $p->query("SELECT id_usuario FROM usuarios WHERE nombre <> 'CPU' ORDER BY id_usuario LIMIT 1")->fetchColumn();
if (!$usuario) { exit("tcg_prueba no tiene ningún usuario que no sea el bot.\n"); }

$monedasAntes = (int) $p->query("SELECT monedas FROM usuarios WHERE id_usuario = $usuario")->fetchColumn();

// --- Cadena de laboratorio: inicio -> cofre, sin bloqueos ni requisitos. ---
$p->prepare("INSERT INTO cadenas (nombre, activa, visibilidad) VALUES ('Prueba race cofre', 1, 'todos')")->execute();
$cadena = (int) $p->lastInsertId();

$crearNodo = function ($tipo, $nombre = null) use ($p, $cadena) {
	$p->prepare("INSERT INTO cadena_nodos (id_cadena, tipo, nombre, columna, fila, es_final)
	             VALUES (?, ?, ?, 0, 0, 0)")->execute([$cadena, $tipo, $nombre]);
	return (int) $p->lastInsertId();
};
$ini   = $crearNodo("inicio");
$cofre = $crearNodo("cofre", "Cofre de prueba");
$p->prepare("INSERT INTO cadena_aristas (id_origen, id_destino) VALUES (?, ?)")->execute([$ini, $cofre]);
$p->prepare("
	INSERT INTO cadena_loot (id_nodo, tipo, monedas, probabilidad, rango_minimo)
	VALUES (?, 'monedas', 500, 100, NULL)
")->execute([$cofre]);

$estado = $db->mapaCadena($cadena, $usuario)["nodos"][$cofre] ?? null;
comprobar("el cofre de laboratorio nace disponible y sin reclamar",
	$estado && $estado["disponible"] && !$estado["reclamado"]);

echo "\nSeis procesos reales en paralelo reclamando el MISMO cofre...\n";
$worker = __DIR__ . "/_race_cofre_worker.tmp.php";
file_put_contents($worker, '<?php
require __DIR__ . "/../consultas.php";
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");
echo json_encode($db->reclamarCofre((int) $argv[1], (int) $argv[2])) . "\n";
');

$php = PHP_BINARY;
$procesos = [];
for ($i = 0; $i < 6; $i++) {
	$procesos[] = popen("\"$php\" \"$worker\" $cofre $usuario", "r");
}
$oks = 0;
$respuestas = [];
foreach ($procesos as $h) {
	$linea = fgets($h);
	pclose($h);
	$respuestas[] = trim((string) $linea);
	$r = json_decode($linea, true);
	if (!empty($r["ok"])) { $oks++; }
}
unlink($worker);

comprobar("exactamente UNO de los seis tiros se lleva el cofre", $oks === 1, "$oks de 6: " . implode(" | ", $respuestas));

$filasCofre = (int) $p->query("SELECT COUNT(*) FROM cadena_cofres WHERE id_usuario = $usuario AND id_nodo = $cofre")->fetchColumn();
comprobar("solo hay UNA fila de reclamo, no seis", $filasCofre === 1, "$filasCofre filas");

$monedasDespues = (int) $p->query("SELECT monedas FROM usuarios WHERE id_usuario = $usuario")->fetchColumn();
comprobar("las monedas suben EXACTAMENTE una vez (+500), no seis veces (+3000)",
	$monedasDespues - $monedasAntes === 500,
	"antes $monedasAntes, después $monedasDespues, diferencia " . ($monedasDespues - $monedasAntes));

echo "\n" . ($fallos === 0 ? "TODO CORRECTO\n\n" : "$fallos COMPROBACIONES FALLIDAS\n\n");
exit($fallos === 0 ? 0 : 1);
