<?php
/**
 * ¿Qué pasa si alguien lleva el código a una copia SIN la migración 019?
 *
 * Importa porque MariaDB aquí no corre con STRICT_TRANS_TABLES: guardar un valor
 * que el ENUM no conoce NO da error, TRUNCA a cadena vacía y solo deja un
 * warning, que PDO no convierte en excepción. Sin la comprobación, el duelo
 * quedaría cobrado a los dos y en un estado que liquidarPartido() no puede
 * cerrar: el bote no volvería a nadie y nada avisaría.
 *
 * Se prueba sobre tcg_sinmig, una copia desechable a la que se le QUITA el valor
 * 'en_juego' del enum a propósito.
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

require __DIR__ . "/../consultas.php";

$p = new PDO("mysql:host=127.0.0.1;dbname=tcg_sinmig;charset=utf8mb4", "root", "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = new Tcg("127.0.0.1", 3306, "tcg_sinmig", "root", "");

$fallos = 0;
$ok = function ($m) { echo "  OK    $m\n"; };
$ko = function ($m) use (&$fallos) { echo "  FALLO $m\n"; $fallos++; };
$monedas = function ($id) use ($p) {
    $s = $p->prepare("SELECT monedas FROM usuarios WHERE id_usuario = :i");
    $s->execute([":i" => $id]); return (int) $s->fetchColumn();
};

echo "=== El enum de esta copia NO conoce 'en_juego' ===\n";
$tipo = $p->query("
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA='tcg_sinmig' AND TABLE_NAME='duelos' AND COLUMN_NAME='estado'
")->fetchColumn();
strpos($tipo, "en_juego") === false ? $ok("confirmado, migracion 019 ausente") : $ko("la copia SI tiene la migracion");

echo "\n=== Montar un duelo debe FALLAR, y decir por que ===\n";
$r = $db->crearDuelo(9, "monedas", 100, null, null);
$id = (int) $r["id_duelo"];
$db->aceptarDuelo($id, 2, null);
$db->elegirAumento($id, 9, 1);
$db->elegirAumento($id, 2, 1);
$db->cerrarFaseAumento($id);

$antes9 = $monedas(9); $antes2 = $monedas(2);
$res = $db->resolverDuelo($id);

empty($res["ok"]) ? $ok("resolverDuelo() se niega") : $ko("dijo que todo bien");
strpos($res["error"] ?? "", "019") !== false
    ? $ok("y dice cual falta: \"" . $res["error"] . "\"")
    : $ko("el error no dice que migracion falta: " . ($res["error"] ?? "ninguno"));

$s = $p->prepare("SELECT estado, goles_creador FROM duelos WHERE id_duelo = :d");
$s->execute([":d" => $id]);
$d = $s->fetch(PDO::FETCH_ASSOC);
$d["estado"] === "listo_para_resolver"
    ? $ok("el duelo se queda como estaba, sin estado invalido")
    : $ko("quedo en estado [" . $d["estado"] . "]");
$d["goles_creador"] === null ? $ok("y sin marcador escrito (transaccion deshecha)") : $ko("escribio marcador");

echo "\n=== Y sobre todo: el dinero NO se queda colgado ===\n";
($monedas(9) - $antes9) === 0 && ($monedas(2) - $antes2) === 0
    ? $ok("nadie pago nada de mas al intentarlo")
    : $ko("se movieron monedas");
// Lo apostado sigue retenido, pero el duelo se puede cancelar/reintentar porque
// su estado es uno valido. Eso es lo que la comprobacion protege.
!$db->cerrarPartidoSiToca($id) ? $ok("no hay ningun partido fantasma que cerrar") : $ko("hay un partido fantasma");

echo "\n" . ($fallos ? "$fallos FALLO(S)\n" : "Todo correcto.\n");
exit($fallos ? 1 : 0);
