<?php
/**
 * Paso 3 de punta a punta, sobre la copia DESECHABLE tcg_prueba (§8).
 * Nunca toca la base real.
 *
 * Lo que se comprueba, en orden de lo que puede doler:
 *   1. El modo natural produce EMPATES (sin ellos la tanda no existe).
 *   2. resolverDuelo() deja el duelo en_juego, sin ganador y SIN PAGAR.
 *   3. Al llegar el reloj al final, el sondeo liquida: ganador y bote, una vez.
 *   4. Un minijuego puede DAR LA VUELTA al resultado (ya no hay §1.3).
 *   5. Un empate se decide en la tanda.
 *   6. Los dos abandonos: partido que no arranca y partido parado.
 *   7. Nada mueve el marcador de un duelo ya liquidado.
 */
require __DIR__ . "/../consultas.php";

$dsn = "mysql:host=127.0.0.1;dbname=tcg_prueba;charset=utf8mb4";
$p = new PDO($dsn, "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");

$fallos = 0;
$ok = function ($m) { echo "  OK    $m\n"; };
$ko = function ($m) use (&$fallos) { echo "  FALLO $m\n"; $fallos++; };
$monedas = function ($id) use ($p) {
    $s = $p->prepare("SELECT monedas FROM usuarios WHERE id_usuario = :i");
    $s->execute([":i" => $id]); return (int) $s->fetchColumn();
};
$fila = function ($id) use ($p) {
    $s = $p->prepare("SELECT * FROM duelos WHERE id_duelo = :d");
    $s->execute([":d" => $id]); return $s->fetch(PDO::FETCH_ASSOC);
};

/** Monta un duelo real de 9 contra 2 y lo deja recién resuelto (en_juego). */
function montarDuelo(Tcg $db, PDO $p, $apuesta = 100) {
    $r = $db->crearDuelo(9, "monedas", $apuesta, null, null);
    if (empty($r["ok"])) throw new Exception("crearDuelo: " . ($r["error"] ?? "?"));
    $id = (int) $r["id_duelo"];

    $r = $db->aceptarDuelo($id, 2, null);
    if (empty($r["ok"])) throw new Exception("aceptarDuelo: " . ($r["error"] ?? "?"));

    // La fase de aumento se cierra eligiendo por los dos y resolviendo.
    // Las opciones van de 1 a 3 (ver generarAumentos), no de 0.
    if (!$db->elegirAumento($id, 9, 1)) throw new Exception("elegirAumento creador");
    if (!$db->elegirAumento($id, 2, 1)) throw new Exception("elegirAumento rival");
    if (!$db->cerrarFaseAumento($id))   throw new Exception("cerrarFaseAumento");
    $r = $db->resolverDuelo($id);
    if (empty($r["ok"])) throw new Exception("resolverDuelo: " . ($r["error"] ?? "?"));
    return $id;
}

/** Empuja el reloj del partido hacia atrás para que dé por terminado. */
function acabarReloj(PDO $p, $id, $segundos = 600) {
    $p->prepare("
        UPDATE duelos
        SET partido_inicio = NOW() - INTERVAL :s SECOND, partido_pausado_en = NULL
        WHERE id_duelo = :d
    ")->execute([":s" => $segundos, ":d" => $id]);
}

/**
 * Sondea hasta el final resolviendo las decisiones que salgan, igual que hacen
 * los dos navegadores de verdad.
 *
 * Hace falta porque saltar el reloj hasta el final NO acaba el partido: si queda
 * una decisión sin jugar, el encuentro se detiene ahí y se queda esperando. Es
 * el comportamiento correcto —esa decisión todavía puede mover el marcador— y
 * mi primera versión de esta prueba lo daba por terminado con un solo sondeo.
 */
function jugarHastaElFinal(Tcg $db, $id, &$decisiones = 0) {
    for ($i = 0; $i < 120; $i++) {
        $e = $db->estadoPartido($id, 9);
        if (empty($e["ok"])) return null;

        /* LA TANDA es una fase más, y hay que jugarla. Es la TERCERA vez que este
           olvido muerde: un ayudante que dice "juega hasta el final" tiene que
           cubrir TODAS las fases, o los duelos empatados se quedan colgados y
           parece un fallo del motor. Zonas con módulos distintos (4 y 3) para que
           puedan coincidir: si nunca coinciden, no hay paradas y la muerte súbita
           no acaba nunca. */
        if (!empty($e["tanda"]) && empty($e["tanda"]["acabada"])) {
            if (!empty($e["tanda"]["tiro"])) {
                $db->tirarPenalti($id, 9, Tcg::ZONAS_PENALTI[$i % 4]);
                $db->tirarPenalti($id, 2, Tcg::ZONAS_PENALTI[$i % 3]);
            }
            continue;
        }

        if (!empty($e["minijuego"])) {
            $db->resolverMinijuegoDuelo($id, 9, (int) $e["minijuego"]["id_evento"], "");
            $decisiones++;
            continue;
        }
        if (!empty($e["esperando_rival"])) {
            $e2 = $db->estadoPartido($id, 2);
            if (!empty($e2["minijuego"])) {
                $db->resolverMinijuegoDuelo($id, 2, (int) $e2["minijuego"]["id_evento"], "");
                $decisiones++;
            }
            continue;
        }
        if ($e["fase"] === "final") return $e;
    }
    return null;
}

echo "=== 1) El modo natural produce empates ===\n";
$cartas9 = null;
$reparto = ["local" => 0, "visitante" => 0, "empate" => 0];
$golesTot = 0;
for ($k = 1; $k <= 400; $k++) {
    $f = ["POR" => 70, "DF" => 70, "MC" => 70, "DC" => 70, "total" => 280];
    $sim = Tcg::generarEventosPartido(
        ["nombre" => "local", "fuerza" => $f, "goles" => null, "cartas" => [], "formacion" => "442"],
        ["nombre" => "visitante", "fuerza" => $f, "goles" => null, "cartas" => [], "formacion" => "442"],
        $k / 401,
        []
    );
    [$gl, $gv] = $sim["goles"];
    $golesTot += $gl + $gv;
    $reparto[$gl === $gv ? "empate" : ($gl > $gv ? "local" : "visitante")]++;
}
printf("        empates %.1f %%, local %.1f %%, visitante %.1f %%, %.2f goles/partido\n",
    100 * $reparto["empate"] / 400, 100 * $reparto["local"] / 400,
    100 * $reparto["visitante"] / 400, $golesTot / 400);
$reparto["empate"] > 0 ? $ok("hay empates (la tanda tiene para qué existir)") : $ko("NINGUN empate en 400 partidos");
abs($reparto["local"] - $reparto["visitante"]) < 60 ? $ok("con fuerzas iguales no hay sesgo de lado") : $ko("sesgado a un lado");

echo "\n=== 2) resolverDuelo() no decide y no paga ===\n";
$antes9 = $monedas(9); $antes2 = $monedas(2);
$id = montarDuelo($db, $p, 100);
$d = $fila($id);
$d["estado"] === "en_juego" ? $ok("queda en_juego") : $ko("estado " . $d["estado"]);
$d["id_ganador"] === null ? $ok("sin ganador escrito") : $ko("escribio id_ganador " . $d["id_ganador"]);
$d["valor_sorteo"] !== null ? $ok("sorteo escrito (la narracion necesita semilla)") : $ko("sin valor_sorteo");
$d["resuelto"] !== null ? $ok("hora de montaje escrita (la espera cuenta desde ahi)") : $ko("sin resuelto");
// Cada uno ha puesto 100 y nadie ha cobrado.
($monedas(9) - $antes9) === -100 ? $ok("al creador se le retuvo su apuesta") : $ko("creador: " . ($monedas(9) - $antes9));
($monedas(2) - $antes2) === -100 ? $ok("al rival se le retuvo la suya") : $ko("rival: " . ($monedas(2) - $antes2));
$d["goles_creador"] !== null ? $ok("marcador previsto guardado ({$d['goles_creador']}-{$d['goles_rival']})") : $ko("sin marcador");

echo "\n=== 3) El sondeo liquida al llegar al final ===\n";
acabarReloj($p, $id);
$antes9 = $monedas(9); $antes2 = $monedas(2);
$decisiones = 0;
$estado = jugarHastaElFinal($db, $id, $decisiones);
echo "        se jugaron $decisiones decisiones por el camino\n";
$d = $fila($id);
!empty($estado["ok"]) && $estado["fase"] === "final" ? $ok("el sondeo dice 'final'") : $ko("fase " . ($estado["fase"] ?? "?"));
!empty($estado["decidido"]) ? $ok("el sondeo avisa de que ya esta decidido") : $ko("no avisa (el cliente no recargaria)");
$d["estado"] === "resuelto" ? $ok("el duelo queda resuelto") : $ko("estado " . $d["estado"]);
$d["id_ganador"] !== null ? $ok("ganador escrito: " . $d["id_ganador"]) : $ko("sin ganador");
$entregado = ($monedas(9) - $antes9) + ($monedas(2) - $antes2);
$entregado === 200 ? $ok("se entregan 200 en total") : $ko("entregado $entregado");
$esperado = (int) $d["id_ganador"] === 9 ? [200, 0] : [0, 200];
[$monedas(9) - $antes9, $monedas(2) - $antes2] === $esperado ? $ok("cobra el que gano") : $ko("cobro el que no gano");
// El ganador tiene que ser el del marcador, no el del sorteo.
$porMarcador = (int) $d["goles_creador"] > (int) $d["goles_rival"] ? 9
    : ((int) $d["goles_rival"] > (int) $d["goles_creador"] ? 2 : null);
if ($porMarcador === null) {
    $d["resuelto_por_tanda"] ? $ok("empate: decidido en la tanda") : $ko("empate sin marcar la tanda");
} else {
    (int) $d["id_ganador"] === $porMarcador ? $ok("gana el del MARCADOR, no el del sorteo") : $ko("el ganador no cuadra con el marcador");
}
// Y no se paga dos veces por sondear otra vez.
$antes9 = $monedas(9); $antes2 = $monedas(2);
$db->estadoPartido($id, 9); $db->estadoPartido($id, 2); $db->estadoPartido($id, 9);
($monedas(9) - $antes9) + ($monedas(2) - $antes2) === 0 ? $ok("tres sondeos mas no pagan nada") : $ko("PAGO DOBLE al sondear");

echo "\n=== 4) Un minijuego puede DAR LA VUELTA al resultado ===\n";
$id = montarDuelo($db, $p, 50);
// Se fuerza un 0-1: el creador perdiendo por uno.
$p->prepare("UPDATE duelos SET goles_creador = 0, goles_rival = 1 WHERE id_duelo = :d")->execute([":d" => $id]);
$db->descontarGolRival($id, 9) ? $ok("el que pierde por uno PUEDE parar el gol (§1.3 fuera)") : $ko("no pudo: la §1.3 sigue puesta");
$d = $fila($id);
[(int) $d["goles_creador"], (int) $d["goles_rival"]] === [0, 0] ? $ok("queda 0-0, o sea empate") : $ko("marcador " . $d["goles_creador"] . "-" . $d["goles_rival"]);
$db->sumarGolPropio($id, 9) ? $ok("y ademas puede marcar") : $ko("no pudo marcar");
$d = $fila($id);
[(int) $d["goles_creador"], (int) $d["goles_rival"]] === [1, 0] ? $ok("1-0: ha dado la vuelta al partido") : $ko("marcador " . $d["goles_creador"] . "-" . $d["goles_rival"]);
// Se liquida directamente: lo que se prueba aquí es que el ganador sale del
// marcador remontado, no el camino del sondeo (eso ya lo cubre el grupo 3).
$db->liquidarPartido($id);
$d = $fila($id);
(int) $d["id_ganador"] === 9 ? $ok("y el duelo se lo lleva el que remonto") : $ko("gano otro: " . $d["id_ganador"]);
// Nada baja de cero.
$id2 = montarDuelo($db, $p, 50);
$p->prepare("UPDATE duelos SET goles_creador = 0, goles_rival = 0 WHERE id_duelo = :d")->execute([":d" => $id2]);
!$db->descontarGolRival($id2, 9) ? $ok("con 0 goles no hay nada que quitar") : $ko("resto por debajo de cero");

echo "\n=== 5) Empate forzado: se decide en la tanda ===\n";
/* Desde que la tanda SE JUEGA, liquidarPartido() ya no la sortea: se niega a
   liquidar mientras no haya ganador de tanda. Así que aquí hay que jugarla —o
   forzarla, que es lo que hace tandaAvanzar(true)— antes de liquidar. Esta
   prueba pedía el comportamiento viejo y por eso fallaba. */
$id = montarDuelo($db, $p, 100);
$p->prepare("UPDATE duelos SET goles_creador = 1, goles_rival = 1 WHERE id_duelo = :d")->execute([":d" => $id]);
$antes9 = $monedas(9); $antes2 = $monedas(2);
!$db->liquidarPartido($id)["ok"]
    ? $ok("con la tanda sin jugar, NO liquida (antes se la sorteaba)")
    : $ko("liquido sin jugar la tanda");
$db->tandaAvanzar($id, true);      // nadie va a venir: se decide sola
$db->liquidarPartido($id);
$d = $fila($id);
$d["estado"] === "resuelto" ? $ok("un empate tambien cierra el duelo") : $ko("estado " . $d["estado"]);
$d["resuelto_por_tanda"] ? $ok("marcado como decidido en la tanda") : $ko("no marcado");
in_array((int) $d["id_ganador"], [9, 2], true) ? $ok("hay un ganador") : $ko("empate sin ganador: el bote se queda colgado");
($monedas(9) - $antes9) + ($monedas(2) - $antes2) === 200 ? $ok("el bote se entrega igual") : $ko("bote sin entregar");
$v = $db->veredictoDuelo($id, (int) $d["id_ganador"]);
!empty($v["por_tanda"]) ? $ok("el veredicto lo cuenta: \"" . $v["detalle"] . "\"") : $ko("el veredicto calla la tanda");
strpos($v["compartible"], "(pen.)") !== false ? $ok("y el resumen compartible lo marca") : $ko("compartible sin marca de penaltis");

echo "\n=== 6) Los dos abandonos ===\n";
// 6a: el partido no arranca nunca (nadie volvio a abrir la pantalla).
$id = montarDuelo($db, $p, 70);
!$db->cerrarPartidoSiToca($id) ? $ok("recien montado NO se cierra (te da tiempo a llegar)") : $ko("cerro un partido que nadie ha jugado aun");
$p->prepare("UPDATE duelos SET resuelto = NOW() - INTERVAL 4000 SECOND WHERE id_duelo = :d")->execute([":d" => $id]);
$antes9 = $monedas(9); $antes2 = $monedas(2);
$db->cerrarPartidoSiToca($id) ? $ok("pasado el plazo se cierra por abandono") : $ko("se quedo colgado: el bote no vuelve a nadie");
($monedas(9) - $antes9) + ($monedas(2) - $antes2) === 140 ? $ok("y el bote se entrega") : $ko("bote retenido para siempre");

// 6b: el partido se queda PARADO en una decision que nadie toma.
$id = montarDuelo($db, $p, 70);
acabarReloj($p, $id);
$p->prepare("UPDATE duelos SET partido_pausado_en = NOW() WHERE id_duelo = :d")->execute([":d" => $id]);
!$db->cerrarPartidoSiToca($id) ? $ok("parado hace un instante NO se cierra (alguien esta decidiendo)") : $ko("cerro con una decision abierta");
$p->prepare("UPDATE duelos SET partido_pausado_en = NOW() - INTERVAL 4000 SECOND WHERE id_duelo = :d")->execute([":d" => $id]);
$antes9 = $monedas(9); $antes2 = $monedas(2);
$db->cerrarPartidoSiToca($id) ? $ok("parado desde hace mucho se cierra") : $ko("un partido parado se queda colgado");
($monedas(9) - $antes9) + ($monedas(2) - $antes2) === 140 ? $ok("y el bote se entrega") : $ko("bote retenido");

// 6c: la red del listado de duelos.
/* ⚠️ EL MARCADOR SE FUERZA DECISIVO A PROPÓSITO, y no es por comodidad: esta
   rama NO fuerza la tanda —el partido acaba de terminar, todavía no hay
   abandono— así que con un empate `liquidarPartido()` se niega a cerrar y esto
   contaba 0 partidos cerrados. Correcto en el motor, intermitente en la prueba:
   fallaba en el 25 % de las ejecuciones, que es justo lo que empata. Lo que aquí
   se quiere comprobar es que ABRIR LA LISTA cierra un partido terminado; el
   empate por el listado ya lo cubren 6a y 6b, que sí fuerzan. */
$id = montarDuelo($db, $p, 70);
acabarReloj($p, $id);
$p->prepare("UPDATE duelos SET goles_creador = 2, goles_rival = 0 WHERE id_duelo = :d")
  ->execute([":d" => $id]);
$antes9 = $monedas(9); $antes2 = $monedas(2);
$db->cerrarPartidosPendientes(2) >= 1 ? $ok("abrir la lista de duelos cierra los partidos terminados") : $ko("la lista no cierra nada");
($monedas(9) - $antes9) + ($monedas(2) - $antes2) === 140 ? $ok("y entrega el bote") : $ko("bote sin entregar");
$db->cerrarPartidosPendientes(2) === 0 ? $ok("y no vuelve a cerrar lo ya cerrado") : $ko("cerro dos veces");

echo "\n=== 7) Un duelo ya liquidado esta cerrado a cal y canto ===\n";
!$db->descontarGolRival($id, 9) ? $ok("no se le puede quitar un gol") : $ko("movio el marcador de un duelo pagado");
!$db->sumarGolPropio($id, 9) ? $ok("ni sumar uno") : $ko("movio el marcador de un duelo pagado");
$antes = $monedas(9) + $monedas(2);
!$db->cerrarPartidoSiToca($id) ? $ok("ni volver a cerrarlo") : $ko("lo cerro otra vez");
($monedas(9) + $monedas(2)) === $antes ? $ok("y no se mueve una moneda") : $ko("movio monedas");

echo "\n" . ($fallos ? "$fallos FALLO(S)\n" : "Todo correcto.\n");
exit($fallos ? 1 : 0);
