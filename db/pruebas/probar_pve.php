<?php
/**
 * LAS CADENAS, DESDE QUE EL PARTIDO LAS DECIDE (§15.12).
 *
 * Esta prueba decía lo contrario hasta hoy: comprobaba que un partido de cadena
 * se resolvía de una vez, porque era lo que garantizaba que el Paso 3 no se
 * hubiera llevado el PvE por delante. Ahora el PvE va por el mismo camino que
 * el PvP, así que lo que hay que demostrar es lo simétrico.
 *
 * Lo que se comprueba, en orden de lo que puede doler:
 *   1. resolverDuelo() deja el partido de cadena en_juego, SIN rango, SIN
 *      progreso de nodo y SIN botín.
 *   2. El reloj arranca con UN SOLO jugador presente (el CPU no late nunca).
 *   3. El CPU no recibe decisiones: el partido no se queda esperándole.
 *   4. Al terminar, liquidarPartido() escribe el rango, registra el progreso y
 *      entrega monedas y botín. Una sola vez.
 *   5. El nodo queda superado y la cadena avanza.
 *   6. Un empate lo decide la tanda, y ganarla vale como victoria: rango B de
 *      suelo, o el nodo no se abriría nunca.
 *
 * Sobre la copia DESECHABLE tcg_prueba (§8). Nunca toca la base real.
 */
require __DIR__ . "/../consultas.php";

$p = new PDO("mysql:host=127.0.0.1;dbname=tcg_prueba;charset=utf8mb4", "root", "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");

$fallos = 0;
$ok = function ($m) { echo "  OK    $m\n"; };
$ko = function ($m) use (&$fallos) { echo "  FALLO $m\n"; $fallos++; };

$monedas = function ($id = 9) use ($p) {
    $s = $p->prepare("SELECT monedas FROM usuarios WHERE id_usuario = :i");
    $s->execute([":i" => $id]); return (int) $s->fetchColumn();
};
$fila = function ($id) use ($p) {
    $s = $p->prepare("SELECT * FROM duelos WHERE id_duelo = :d");
    $s->execute([":d" => $id]); return $s->fetch(PDO::FETCH_ASSOC);
};
$progreso = function ($nodo, $dif = "medio") use ($p) {
    $s = $p->prepare("SELECT * FROM cadena_progreso WHERE id_usuario = 9 AND id_nodo = :n AND dificultad = :d");
    $s->execute([":n" => $nodo, ":d" => $dif]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
};
$drops = function ($id) use ($p) {
    $s = $p->prepare("SELECT COUNT(*) FROM cadena_drops WHERE id_duelo = :d");
    $s->execute([":d" => $id]); return (int) $s->fetchColumn();
};

/** Monta un partido de cadena y lo deja recién montado (en_juego). */
function montarCadena(Tcg $db, $nodo, $dificultad = "medio") {
    $r = $db->crearPartidoCadena(9, $nodo, $dificultad);
    if (empty($r["ok"])) throw new Exception("crearPartidoCadena: " . ($r["error"] ?? "?"));
    $id = (int) $r["id_duelo"];
    $db->elegirAumento($id, 9, 1);
    $db->cerrarFaseAumento($id);
    $r = $db->resolverDuelo($id);
    if (empty($r["ok"])) throw new Exception("resolverDuelo: " . ($r["error"] ?? "?"));
    return $id;
}

/** Empuja el reloj hacia atrás para que el partido dé por terminado. */
function acabarReloj(PDO $p, $id, $segundos = 600) {
    $p->prepare("
        UPDATE duelos SET partido_inicio = NOW() - INTERVAL :s SECOND, partido_pausado_en = NULL
        WHERE id_duelo = :d
    ")->execute([":s" => $segundos, ":d" => $id]);
}

/**
 * Sondea hasta el final jugando las decisiones, como haría el navegador.
 * Devuelve [estado final, nº de decisiones, veces que dijo "esperando al rival"].
 * Ese último número TIENE QUE SER CERO: el rival es el CPU.
 */
function jugarHastaElFinal(Tcg $db, $id) {
    $decisiones = 0; $esperandoAlBot = 0;
    for ($i = 0; $i < 120; $i++) {
        $e = $db->estadoPartido($id, 9);
        if (empty($e["ok"])) return [null, $decisiones, $esperandoAlBot];

        if (!empty($e["tanda"]) && empty($e["tanda"]["acabada"])) {
            // La tanda contra el CPU todavía se resuelve por plazo (pieza 5 del
            // §15.12, sin construir), así que aquí se fuerza como haría el
            // abandono en vez de esperar los 12 segundos de cada tiro.
            $db->tandaAvanzar($id, true);
            continue;
        }
        if (!empty($e["esperando_rival"])) { $esperandoAlBot++; continue; }
        if (!empty($e["minijuego"])) {
            $db->resolverMinijuegoDuelo($id, 9, (int) $e["minijuego"]["id_evento"], "");
            $decisiones++;
            continue;
        }
        if ($e["fase"] === "final") return [$e, $decisiones, $esperandoAlBot];
    }
    return [null, $decisiones, $esperandoAlBot];
}

$nodo = (int) $p->query("
    SELECT id_nodo FROM cadena_nodos WHERE tipo = 'partido' AND id_rival IS NOT NULL
    ORDER BY id_nodo LIMIT 1
")->fetchColumn();
if (!$nodo) { echo "No hay cadenas en la base; nada que probar.\n"; exit(0); }

echo "=== 1) Un partido de cadena YA NO se resuelve al montarlo ===\n";
$antes = $monedas();
$id = montarCadena($db, $nodo);
$d = $fila($id);
$d["estado"] === "en_juego"  ? $ok("queda en_juego, no resuelto") : $ko("estado " . $d["estado"]);
$d["id_ganador"] === null    ? $ok("sin ganador escrito")          : $ko("escribio id_ganador " . $d["id_ganador"]);
$d["rango"] === null         ? $ok("y SIN rango: sale del marcador final")  : $ko("rango escrito ya: " . $d["rango"]);
$d["dificultad"] !== null    ? $ok("marcado como PvE")             : $ko("no es PvE");
$d["valor_sorteo"] !== null  ? $ok("sorteo escrito (la narracion necesita semilla)") : $ko("sin valor_sorteo");
$monedas() === $antes        ? $ok("no ha entregado ni una moneda todavia") : $ko("pago " . ($monedas() - $antes) . " al montar");
$drops($id) === 0            ? $ok("ni un solo drop antes de jugar")   : $ko("repartio botin al montar");
echo "        marcador previsto {$d['goles_creador']}-{$d['goles_rival']}\n";

echo "\n=== 2) El reloj arranca con un solo jugador (el CPU no late) ===\n";
$db->estadoPartido($id, 9);       // un sondeo del jugador, y solo uno
$d = $fila($id);
$d["partido_inicio"] !== null
    ? $ok("basta el latido del jugador para que arranque")
    : $ko("no arranco: esperaria los 15 s de partido_espera_seg en CADA partido");

echo "\n=== 3) El CPU no recibe decisiones ===\n";
acabarReloj($p, $id);
[$estado, $decisiones, $esperandoAlBot] = jugarHastaElFinal($db, $id);
echo "        se jugaron $decisiones decisiones por el camino\n";
$esperandoAlBot === 0
    ? $ok("el partido nunca se queda esperando al bot")
    : $ko("se detuvo $esperandoAlBot veces esperando una decision del CPU");
$decisiones > 0 ? $ok("y el jugador si tuvo las suyas") : $ko("el jugador no tuvo ninguna decision");
!empty($estado["ok"]) && $estado["fase"] === "final" ? $ok("el partido llega al final") : $ko("no llego al final");

echo "\n=== 4) Al terminar se liquida: rango, progreso y botin ===\n";
$d = $fila($id);
$d["estado"] === "resuelto" ? $ok("el duelo queda resuelto") : $ko("estado " . $d["estado"]);
$gano = (int) $d["id_ganador"] === 9;
echo "        marcador final {$d['goles_creador']}-{$d['goles_rival']}, "
   . ($gano ? "gana el jugador" : "gana el CPU") . ", rango " . ($d["rango"] ?? "—") . "\n";
$prog = $progreso($nodo);
$prog ? $ok("el nodo consta jugado") : $ko("el nodo no consta jugado: el progreso se perdio");
if ($gano) {
    $d["rango"] !== null ? $ok("victoria con rango escrito: " . $d["rango"]) : $ko("victoria sin rango");
    $drops($id) > 0      ? $ok("y con botin entregado")                     : $ko("gano y no cobro nada");
    $prog && (int) $prog["victorias"] === 1 ? $ok("la victoria queda registrada") : $ko("victoria sin registrar");
} else {
    $d["rango"] === null ? $ok("derrota sin rango (perder no puntua)") : $ko("derrota con rango " . $d["rango"]);
    $drops($id) === 0    ? $ok("y sin botin")                          : $ko("perdio y cobro igual");
}

// Idempotencia: el reparto cuelga del mismo UPDATE que el cierre.
$antes = $monedas(); $dropsAntes = $drops($id);
$db->estadoPartido($id, 9); $db->liquidarPartido($id); $db->cerrarPartidoSiToca($id);
$monedas() === $antes      ? $ok("liquidar tres veces mas no paga otra vez") : $ko("PAGO DOBLE al reliquidar");
$drops($id) === $dropsAntes ? $ok("ni reparte el botin dos veces")            : $ko("BOTIN DOBLE al reliquidar");

echo "\n=== 5) La cadena avanza ===\n";
$mapa = $db->mapaCadena((int) $db->obtenerNodo($nodo)["id_cadena"], 9);
$superado = !empty($mapa["nodos"][$nodo]["superado"]);
$gano
    ? ($superado ? $ok("el nodo ganado queda superado") : $ko("gano y el nodo no se abre: la cadena se corta"))
    : (!$superado ? $ok("perder no abre el nodo") : $ko("perdio y el nodo cuenta como superado"));

echo "\n=== 6) Un empate se decide en la tanda, y ganarla vale como victoria ===\n";
/* Se repite hasta que el jugador gane una tanda: quien tira primero sale del
   sorteo del duelo, así que en un empate cualquiera puede llevársela. */
$idEmpate = null;
for ($intento = 0; $intento < 12; $intento++) {
    $idEmpate = montarCadena($db, $nodo, "facil");
    $p->prepare("UPDATE duelos SET goles_creador = 1, goles_rival = 1 WHERE id_duelo = :d")
      ->execute([":d" => $idEmpate]);
    if (!$db->liquidarPartido($idEmpate)["ok"] && $intento === 0) {
        $ok("con la tanda sin jugar NO liquida");
    }
    $db->tandaAvanzar($idEmpate, true);
    $db->liquidarPartido($idEmpate);
    $d = $fila($idEmpate);
    if ((int) $d["id_ganador"] === 9) { break; }
}
$d = $fila($idEmpate);
$d["estado"] === "resuelto"    ? $ok("el empate cierra el duelo")        : $ko("estado " . $d["estado"]);
$d["resuelto_por_tanda"]       ? $ok("marcado como decidido en la tanda") : $ko("no marcado como tanda");
if ((int) $d["id_ganador"] === 9) {
    $d["rango"] === "B"
        ? $ok("ganar en los penaltis da rango B (el suelo), no null")
        : $ko("rango " . ($d["rango"] ?? "null") . ": con null el nodo no se abriria nunca");
    $prog = $progreso($nodo, "facil");
    !empty($prog["mejor_rango"]) ? $ok("y el nodo queda superado") : $ko("nodo sin mejor_rango tras ganar la tanda");
} else {
    $ok("las 12 tandas las gano el CPU (no se pudo probar el suelo B)");
}

echo "\n" . ($fallos ? "$fallos FALLO(S)\n" : "Todo correcto.\n");
exit($fallos ? 1 : 0);
