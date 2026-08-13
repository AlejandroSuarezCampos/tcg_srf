<?php
/**
 * Prueba liquidarPartido() sobre la copia DESECHABLE tcg_prueba (§8).
 * Nunca toca la base real.
 *
 * Comprueba lo que de verdad puede salir mal cuando se mueve dinero:
 *   1. Un duelo en_juego con ganador claro: se paga UNA vez y queda resuelto.
 *   2. IDEMPOTENCIA: llamarla cinco veces seguidas paga una sola vez.
 *      Es el caso real, porque los dos jugadores la piden en cada sondeo.
 *   3. Un empate se rompe en la tanda y queda marcado.
 *   4. Un duelo que no está en_juego no se toca.
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

$dsn = "mysql:host=127.0.0.1;dbname=tcg_prueba;charset=utf8mb4";
$pdoDirecto = new PDO($dsn, "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Se instancia Tcg apuntando a la copia
require __DIR__ . "/../consultas.php";
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");

$fallos = 0;
$ok = function ($m) { echo "  OK    $m\n"; };
$ko = function ($m) use (&$fallos) { echo "  FALLO $m\n"; $fallos++; };

/** Prepara un duelo en_juego con el marcador que se le diga. */
function montar(PDO $p, $gc, $gr, $monedas = 100) {
    $p->exec("UPDATE usuarios SET monedas = 1000 WHERE id_usuario IN (9,2)");
    $p->exec("DELETE FROM duelo_apuestas WHERE id_duelo = 90001");
    $p->exec("DELETE FROM duelos WHERE id_duelo = 90001");
    $p->prepare("
        INSERT INTO duelos (id_duelo, id_creador, id_rival, id_mazo_creador,
                            formacion_creador, formacion_rival, tipo_apuesta, monedas,
                            estado, valor_sorteo, goles_creador, goles_rival)
        VALUES (90001, 9, 2, (SELECT id_mazo FROM mazos WHERE id_usuario = 9 LIMIT 1),
                '442','442','monedas', :m, 'en_juego', 0.4242, :gc, :gr)
    ")->execute([":m" => $monedas, ":gc" => $gc, ":gr" => $gr]);
}
$monedasDe = function ($p, $id) {
    $s = $p->prepare("SELECT monedas FROM usuarios WHERE id_usuario = :i");
    $s->execute([":i" => $id]); return (int) $s->fetchColumn();
};
$duelo = function ($p) {
    return $p->query("SELECT estado, id_ganador, resuelto_por_tanda FROM duelos WHERE id_duelo = 90001")
             ->fetch(PDO::FETCH_ASSOC);
};

echo "=== 1) Ganador claro (2-0 al creador) ===\n";
montar($pdoDirecto, 2, 0, 100);
$antes9 = $monedasDe($pdoDirecto, 9);
$r = $db->liquidarPartido(90001);
$d = $duelo($pdoDirecto);
$despues9 = $monedasDe($pdoDirecto, 9);
$r["ok"] && (int) $r["id_ganador"] === 9 ? $ok("gana el creador") : $ko("no gana el creador: " . json_encode($r));
$d["estado"] === "resuelto" ? $ok("queda resuelto") : $ko("estado " . $d["estado"]);
($despues9 - $antes9) === 200 ? $ok("cobra el bote entero (200 = 100 x 2)") : $ko("cobro " . ($despues9 - $antes9));
!$d["resuelto_por_tanda"] ? $ok("no marcado como tanda") : $ko("marcado como tanda sin empate");

echo "\n=== 2) IDEMPOTENCIA: cinco llamadas seguidas ===\n";
montar($pdoDirecto, 2, 0, 100);
$antes9 = $monedasDe($pdoDirecto, 9);
$pagos = 0;
for ($i = 0; $i < 5; $i++) { $res = $db->liquidarPartido(90001); if ($res["ok"]) $pagos++; }
$cobrado = $monedasDe($pdoDirecto, 9) - $antes9;
$pagos === 1 ? $ok("solo una llamada liquida (las otras 4 se descartan)") : $ko("$pagos llamadas liquidaron");
$cobrado === 200 ? $ok("el bote se entrega UNA vez ($cobrado)") : $ko("cobrado $cobrado, deberia ser 200");

echo "\n=== 3) Empate: se rompe en la tanda ===\n";
/* La tanda ya SE JUEGA, así que liquidarPartido() no la sortea: hay que jugarla
   antes. tandaAvanzar(true) la decide sola, que es lo que hace el cierre por
   abandono cuando ya no queda nadie a quien esperar. */
montar($pdoDirecto, 1, 1, 100);
$antes9 = $monedasDe($pdoDirecto, 9); $antes2 = $monedasDe($pdoDirecto, 2);
!$db->liquidarPartido(90001)["ok"]
    ? $ok("con la tanda sin jugar NO liquida") : $ko("liquido sin tanda");
$db->tandaAvanzar(90001, true);
$r = $db->liquidarPartido(90001);
$d = $duelo($pdoDirecto);
$r["ok"] ? $ok("liquida un empate") : $ko("no liquida el empate");
$r["por_tanda"] ? $ok("marcado por_tanda en la respuesta") : $ko("no marcado por_tanda");
$d["resuelto_por_tanda"] ? $ok("marcado en la BD") : $ko("no marcado en la BD");
in_array((int) $r["id_ganador"], [9, 2], true) ? $ok("hay un ganador: " . $r["id_ganador"]) : $ko("sin ganador valido");
$total = ($monedasDe($pdoDirecto, 9) - $antes9) + ($monedasDe($pdoDirecto, 2) - $antes2);
$total === 200 ? $ok("se entregan 200 en total, a uno solo") : $ko("se entregaron $total");

echo "\n=== 4) Un duelo que no esta en_juego no se toca ===\n";
$pdoDirecto->exec("UPDATE duelos SET estado='resuelto' WHERE id_duelo=90001");
$antes9 = $monedasDe($pdoDirecto, 9);
$r = $db->liquidarPartido(90001);
!$r["ok"] ? $ok("se niega a liquidar un duelo ya resuelto") : $ko("liquido un duelo ya resuelto");
($monedasDe($pdoDirecto, 9) - $antes9) === 0 ? $ok("no mueve monedas") : $ko("movio monedas");

echo "\n=== 5) La tanda automatica ya no existe ===\n";
/* Tcg::tandaDePenaltis() se BORRO al hacer la tanda jugable, y este bloque la
   probaba. Se deja la comprobacion de que no ha vuelto: si alguien la reintroduce,
   el duelo volveria a decidirse sin que el jugador tocase nada. */
!method_exists("Tcg", "tandaDePenaltis")
    ? $ok("tandaDePenaltis() sigue retirada (el reparto lo decide el jugador)")
    : $ko("ha vuelto la tanda automatica: el duelo se decidiria sin jugar");
method_exists("Tcg", "tandaAvanzar") && method_exists("Tcg", "tirarPenalti")
    ? $ok("y la jugable esta en su sitio") : $ko("falta el motor de la tanda jugable");

// Limpieza del duelo de prueba
$pdoDirecto->exec("DELETE FROM duelo_apuestas WHERE id_duelo = 90001");
$pdoDirecto->exec("DELETE FROM duelos WHERE id_duelo = 90001");

echo "\n" . ($fallos ? "$fallos FALLO(S)\n" : "Todo correcto.\n");
exit($fallos ? 1 : 0);
