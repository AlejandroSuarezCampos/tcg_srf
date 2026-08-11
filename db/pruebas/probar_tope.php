<?php
/**
 * ¿SE APLICA DE VERDAD `partido_presupuesto_marcador`? Copia DESECHABLE.
 *
 * Esta prueba no existía y ahí estaba el bug: al retirar la condición de §1.3 del
 * UPDATE en el Paso 3, el presupuesto se quedó sin juez. El PHP lo contaba para
 * etiquetar la decisión, pero nada impedía el gol de más. Se destapó a mano, no
 * midiendo — de ahí que ahora tenga prueba propia.
 *
 * Lo que se comprueba:
 *   1. Con tope 1, el SEGUNDO movimiento del mismo jugador se rechaza.
 *   2. Con tope 0, no se mueve ni uno.
 *   3. El tope es POR JUGADOR: gastarlo yo no le quita el suyo al rival.
 *   4. Y sigue contando desde `aplicado`, no desde el numero de decisiones.
 */
require __DIR__ . "/../consultas.php";

$p = new PDO("mysql:host=127.0.0.1;dbname=tcg_prueba;charset=utf8mb4", "root", "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");

$fallos = 0;
$ok = function ($m) { echo "  OK    $m\n"; };
$ko = function ($m) use (&$fallos) { echo "  FALLO $m\n"; $fallos++; };

/** Duelo en juego con un marcador holgado, para que nada baje de cero. */
function montar(Tcg $db, PDO $p) {
    $r = $db->crearDuelo(9, "monedas", 10, null, null);
    $id = (int) $r["id_duelo"];
    $db->aceptarDuelo($id, 2, null);
    $db->elegirAumento($id, 9, 1);
    $db->elegirAumento($id, 2, 1);
    $db->cerrarFaseAumento($id);
    $db->resolverDuelo($id);
    $p->prepare("UPDATE duelos SET goles_creador = 5, goles_rival = 5 WHERE id_duelo = :d")
      ->execute([":d" => $id]);
    return $id;
}
/** Marca como aplicada una decision, que es lo que cuenta el tope. */
function aplicada(PDO $p, $id, $usuario, $evento) {
    $p->prepare("INSERT IGNORE INTO duelo_minijuegos
                 (id_duelo, id_evento, id_usuario, minijuego, opcion, resultado, aplicado)
                 VALUES (:d, :e, :u, 'prueba', 'x', 'acierto', 1)")
      ->execute([":d" => $id, ":e" => $evento, ":u" => $usuario]);
}
$marcador = function ($id) use ($p) {
    return $p->query("SELECT CONCAT(goles_creador,'-',goles_rival) FROM duelos WHERE id_duelo = $id")
             ->fetchColumn();
};

echo "=== 1) Con tope 1, el segundo movimiento se rechaza ===\n";
$id = montar($db, $p);
$db->descontarGolRival($id, 9, 1) ? $ok("el primer descuento pasa (" . $marcador($id) . ")") : $ko("no paso el primero");
// Se registra como aplicada, que es lo que el tope cuenta.
aplicada($p, $id, 9, 101);
!$db->descontarGolRival($id, 9, 1)
    ? $ok("el segundo se RECHAZA con tope 1 (" . $marcador($id) . ")")
    : $ko("movio dos goles con tope 1 (" . $marcador($id) . ")");
!$db->sumarGolPropio($id, 9, 1)
    ? $ok("y tampoco cuela por el otro lado (sumar)")
    : $ko("colo sumando: el tope no cubre sumarGolPropio");

echo "\n=== 2) Con tope 0 no se mueve nada ===\n";
$id = montar($db, $p);
$antes = $marcador($id);
!$db->descontarGolRival($id, 9, 0) ? $ok("descontar rechazado") : $ko("descontó con tope 0");
!$db->sumarGolPropio($id, 9, 0)    ? $ok("sumar rechazado")     : $ko("sumó con tope 0");
$marcador($id) === $antes ? $ok("el marcador no se ha movido ($antes)") : $ko("se movio a " . $marcador($id));

echo "\n=== 3) El tope es POR JUGADOR ===\n";
$id = montar($db, $p);
$db->descontarGolRival($id, 9, 1) ? $ok("Claude gasta el suyo") : $ko("no pudo");
aplicada($p, $id, 9, 201);
!$db->descontarGolRival($id, 9, 1) ? $ok("y ya no le queda") : $ko("le quedaba mas");
$db->descontarGolRival($id, 2, 1)
    ? $ok("pero el rival conserva el suyo (" . $marcador($id) . ")")
    : $ko("el tope se comparte entre los dos, y no debe");

echo "\n=== 4) Cuenta desde `aplicado`, no desde el numero de decisiones ===\n";
$id = montar($db, $p);
// Tres decisiones jugadas pero ninguna aplicada: el tope sigue intacto.
$p->prepare("INSERT IGNORE INTO duelo_minijuegos
             (id_duelo, id_evento, id_usuario, minijuego, opcion, resultado, aplicado)
             VALUES (:d, 301, 9, 'prueba', 'x', 'fallo', 0), (:d, 302, 9, 'prueba', 'x', 'fallo', 0),
                    (:d, 303, 9, 'prueba', 'x', 'acierto', 0)")->execute([":d" => $id]);
$db->descontarGolRival($id, 9, 1)
    ? $ok("tres decisiones sin aplicar no gastan tope")
    : $ko("las decisiones falladas gastan tope, y no deben");

echo "\n=== 5) Sin tope (null) se comporta como antes ===\n";
$id = montar($db, $p);
$db->descontarGolRival($id, 9) ? $ok("sin tope pasa") : $ko("no paso sin tope");
aplicada($p, $id, 9, 401);
$db->descontarGolRival($id, 9) ? $ok("y sigue pasando: null = sin limite") : $ko("null limito igual");

echo "\n" . ($fallos ? "$fallos FALLO(S)\n" : "Todo correcto.\n");
exit($fallos ? 1 : 0);
