<?php
/**
 * La tanda jugable. Copia DESECHABLE tcg_prueba (§8).
 *
 * Lo que se comprueba, por orden de lo que puede doler:
 *   1. La regla: misma zona = parada, distinta = gol.
 *   2. ⚠️ QUE LA ELECCIÓN DEL RIVAL NO VIAJE. Es la unica proteccion que hay:
 *      si se filtra, el que elige segundo gana siempre y la tanda no existe.
 *   3. No se puede elegir dos veces, ni cambiar, ni elegir tras resolverse.
 *   4. Corte anticipado, muerte subita y el tope.
 *   5. Abandono a mitad de tanda: el bote NO se queda retenido.
 *   6. El bote se entrega una sola vez.
 */
require __DIR__ . "/../consultas.php";

$p = new PDO("mysql:host=127.0.0.1;dbname=tcg_prueba;charset=utf8mb4", "root", "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");

$fallos = 0;
$ok = function ($m) { echo "  OK    $m\n"; };
$ko = function ($m) use (&$fallos) { echo "  FALLO $m\n"; $fallos++; };
$monedas = function ($id) use ($p) {
    $s = $p->prepare("SELECT monedas FROM usuarios WHERE id_usuario = :i");
    $s->execute([":i" => $id]); return (int) $s->fetchColumn();
};

/** Monta un duelo y le fuerza el EMPATE, que es lo que lleva a la tanda. */
function montarEmpate(Tcg $db, PDO $p, $apuesta = 100) {
    $r = $db->crearDuelo(9, "monedas", $apuesta, null, null);
    $id = (int) $r["id_duelo"];
    $db->aceptarDuelo($id, 2, null);
    $db->elegirAumento($id, 9, 1);
    $db->elegirAumento($id, 2, 1);
    $db->cerrarFaseAumento($id);
    $db->resolverDuelo($id);
    $p->prepare("UPDATE duelos SET goles_creador = 1, goles_rival = 1,
                 partido_inicio = NOW() - INTERVAL 600 SECOND, partido_pausado_en = NULL
                 WHERE id_duelo = :d")->execute([":d" => $id]);
    return $id;
}
/** Quien tira y quien para en el tiro abierto ahora mismo. */
function protagonistas(Tcg $db, $id) {
    $a = $db->tandaParaCliente($id, 9);
    if (empty($a["tiro"])) return null;
    return $a["tiro"]["tiro_yo"] ? ["tira" => 9, "para" => 2] : ["tira" => 2, "para" => 9];
}

echo "=== 1) La regla: misma zona parada, distinta gol ===\n";
$id = montarEmpate($db, $p);
$db->tandaAvanzar($id);
$q = protagonistas($db, $id);
$q ? $ok("hay un penalti abierto y alguien tira") : $ko("no se abrio ningun penalti");

// Los dos al MISMO hueco -> parada.
$db->tirarPenalti($id, $q["tira"], "arriba_izq");
$db->tirarPenalti($id, $q["para"], "arriba_izq");
$t = $p->query("SELECT gol, zona_tirador, zona_portero FROM duelo_penaltis
                WHERE id_duelo = $id ORDER BY ronda, turno LIMIT 1")->fetch(PDO::FETCH_ASSOC);
(int) $t["gol"] === 0 ? $ok("mismo hueco -> PARADA") : $ko("mismo hueco y dio gol");

// Los dos a huecos DISTINTOS -> gol.
$db->tandaAvanzar($id);
$q = protagonistas($db, $id);
$db->tirarPenalti($id, $q["tira"], "abajo_der");
$db->tirarPenalti($id, $q["para"], "arriba_izq");
$t = $p->query("SELECT gol FROM duelo_penaltis WHERE id_duelo = $id AND gol IS NOT NULL
                ORDER BY ronda DESC, turno DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
(int) $t["gol"] === 1 ? $ok("huecos distintos -> GOL") : $ko("huecos distintos y no dio gol");

echo "\n=== 2) La eleccion del rival NO viaja al cliente ===\n";
$id = montarEmpate($db, $p);
$db->tandaAvanzar($id);
$q = protagonistas($db, $id);
// Uno elige; el otro pide su pantalla ANTES de elegir.
$db->tirarPenalti($id, $q["tira"], "abajo_izq");

/* OJO AL COMPARAR: la clave `zonas` lleva SIEMPRE los cuatro huecos, porque es
   el menu de botones que hay que pintar. Buscar "abajo_izq" en el JSON entero
   da un falso positivo garantizado. Lo que hay que mirar es todo lo DEMAS. */
$sinMenu = function ($payload) {
    unset($payload["zonas"]);
    return json_encode($payload, JSON_UNESCAPED_UNICODE);
};
$vistaDelOtro = $db->tandaParaCliente($id, $q["para"]);
$resto = $sinMenu($vistaDelOtro);

strpos($resto, "abajo_izq") === false
    ? $ok("la zona que eligio el rival no aparece por ninguna parte: $resto")
    : $ko("SE FILTRA la zona del rival: " . $resto);
// Y tampoco en el sondeo completo, que es lo que de verdad se manda por la red.
$sondeo = $db->estadoPartido($id, $q["para"]);
$restoSondeo = $sinMenu($sondeo["tanda"] ?? []);
strpos($restoSondeo, "abajo_izq") === false
    ? $ok("ni en la respuesta del sondeo")
    : $ko("SE FILTRA en el sondeo: " . $restoSondeo);
// Y por si el menu se reordenara alguna vez, se comprueba que sigue completo.
count($vistaDelOtro["zonas"]) === 4
    ? $ok("el menu sigue trayendo los 4 huecos (no delata nada: siempre son los 4)")
    : $ko("el menu no trae 4 huecos");
empty($vistaDelOtro["tiro"]["ya_elegi"])
    ? $ok("y a el se le dice que todavia no ha elegido (es verdad)")
    : $ko("le dice que ya eligio sin haberlo hecho");
// Al que SI eligio se le dice que ya eligio.
$vistaSuya = $db->tandaParaCliente($id, $q["tira"]);
!empty($vistaSuya["tiro"]["ya_elegi"]) ? $ok("al que eligio se le confirma") : $ko("no se le confirma");

echo "\n=== 3) No se puede elegir dos veces ni cambiar de opinion ===\n";
$r = $db->tirarPenalti($id, $q["tira"], "arriba_der");
empty($r["ok"]) ? $ok("un segundo intento se rechaza: \"" . $r["error"] . "\"") : $ko("dejo cambiar de opinion");
$zona = $p->query("SELECT zona_tirador FROM duelo_penaltis WHERE id_duelo = $id
                   ORDER BY ronda, turno LIMIT 1")->fetchColumn();
$zona === "abajo_izq" ? $ok("y la zona guardada sigue siendo la primera") : $ko("se cambio a $zona");
$r = $db->tirarPenalti($id, $q["tira"], "hueco_inventado");
empty($r["ok"]) ? $ok("una zona inventada se rechaza") : $ko("acepto una zona que no existe");

echo "\n=== 4) La tanda completa se juega y decide ===\n";
$id = montarEmpate($db, $p);
$tiros = 0;
for ($i = 0; $i < 60; $i++) {
    $db->tandaAvanzar($id);
    $e = $db->tandaEstado($id);
    if ($e["gana"] !== null) break;
    if (!$e["abierto"]) continue;
    $q = protagonistas($db, $id);
    if (!$q) break;
    // Se eligen zonas variadas para que haya goles y paradas.
    $zt = Tcg::ZONAS_PENALTI[$i % 4];
    $zp = Tcg::ZONAS_PENALTI[($i * 3) % 4];
    $db->tirarPenalti($id, $q["tira"], $zt);
    $db->tirarPenalti($id, $q["para"], $zp);
    $tiros++;
}
$e = $db->tandaEstado($id);
$e["gana"] !== null ? $ok("la tanda decide un ganador ($tiros tiros, marcador "
    . $e["goles"][0] . "-" . $e["goles"][1] . ")") : $ko("la tanda no decidio en 60 vueltas");
$tiros <= 2 * Tcg::TANDA_MAX_RONDAS ? $ok("dentro del tope de rondas") : $ko("se paso del tope");

$antes9 = $monedas(9); $antes2 = $monedas(2);
$db->cerrarPartidoSiToca($id);
$d = $p->query("SELECT estado, id_ganador, resuelto_por_tanda FROM duelos WHERE id_duelo = $id")
       ->fetch(PDO::FETCH_ASSOC);
$d["estado"] === "resuelto" ? $ok("y el duelo se liquida") : $ko("no se liquido: " . $d["estado"]);
$d["resuelto_por_tanda"] ? $ok("marcado como decidido en la tanda") : $ko("no marcado");
($monedas(9) - $antes9) + ($monedas(2) - $antes2) === 200
    ? $ok("el bote se entrega (200)") : $ko("bote mal entregado");
// El ganador del duelo tiene que ser el de la tanda.
$ladoGana = $e["gana"];
$esperado = $ladoGana === "local" ? 9 : 2;
(int) $d["id_ganador"] === $esperado ? $ok("gana el de la tanda") : $ko("el ganador no cuadra con la tanda");
// Y no se paga dos veces.
$antes9 = $monedas(9); $antes2 = $monedas(2);
$db->cerrarPartidoSiToca($id); $db->cerrarPartidoSiToca($id);
($monedas(9) - $antes9) + ($monedas(2) - $antes2) === 0 ? $ok("dos cierres mas no pagan nada") : $ko("PAGO DOBLE");

echo "\n=== 5) El plazo decide solo si alguien no elige ===\n";
$id = montarEmpate($db, $p);
$db->tandaAvanzar($id);
$q = protagonistas($db, $id);
$db->tirarPenalti($id, $q["tira"], "arriba_izq");     // solo uno elige
$p->prepare("UPDATE duelo_penaltis SET abierto = NOW() - INTERVAL 600 SECOND
             WHERE id_duelo = :d AND gol IS NULL")->execute([":d" => $id]);
$db->tandaAvanzar($id);
$t = $p->query("SELECT gol, auto_portero, auto_tirador FROM duelo_penaltis
                WHERE id_duelo = $id ORDER BY ronda, turno LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$t["gol"] !== null ? $ok("vencido el plazo, el tiro se resuelve solo") : $ko("se quedo abierto");
(int) $t["auto_portero"] === 1 ? $ok("y consta que la eligio el sistema") : $ko("no consta como automatica");
(int) $t["auto_tirador"] === 0 ? $ok("y la del que si eligio consta como suya") : $ko("marco como automatica una real");

echo "\n=== 6) ABANDONO a mitad de tanda: el bote no se queda retenido ===\n";
$id = montarEmpate($db, $p, 60);
$db->tandaAvanzar($id);
$q = protagonistas($db, $id);
$db->tirarPenalti($id, $q["tira"], "arriba_der");   // se juega un tiro a medias y se van
$antes9 = $monedas(9); $antes2 = $monedas(2);
// Todavia no toca cerrar: acaba de terminar el partido.
$d = $p->query("SELECT estado FROM duelos WHERE id_duelo = $id")->fetch(PDO::FETCH_ASSOC);
$d["estado"] === "en_juego" ? $ok("el duelo espera, con la tanda a medias") : $ko("se cerro antes de tiempo");
// Pasa el plazo de abandono: ya no vuelve nadie.
$p->prepare("UPDATE duelos SET partido_inicio = NOW() - INTERVAL 99999 SECOND WHERE id_duelo = :d")
  ->execute([":d" => $id]);
$db->cerrarPartidoSiToca($id);
$d = $p->query("SELECT estado, id_ganador, resuelto_por_tanda FROM duelos WHERE id_duelo = $id")
       ->fetch(PDO::FETCH_ASSOC);
$d["estado"] === "resuelto" ? $ok("pasado el plazo, la tanda se decide sola y el duelo se cierra")
    : $ko("SE QUEDO COLGADO con el bote dentro: " . $d["estado"]);
in_array((int) $d["id_ganador"], [9, 2], true) ? $ok("con un ganador") : $ko("sin ganador");
($monedas(9) - $antes9) + ($monedas(2) - $antes2) === 120
    ? $ok("y el bote vuelve (120)") : $ko("bote retenido: " . (($monedas(9) - $antes9) + ($monedas(2) - $antes2)));

echo "\n=== 7) CUALQUIER tanda a medias se puede cerrar siempre ===\n";
/* Las pruebas de arriba dejan varios duelos con la tanda empezada y sin acabar:
   es justo el estado peligroso, porque ahi el bote esta retenido. Se comprueba
   que TODOS se pueden cerrar, que es la garantia que de verdad hace falta. */
$aMedias = $p->query("SELECT id_duelo, monedas FROM duelos WHERE estado = 'en_juego'")
             ->fetchAll(PDO::FETCH_ASSOC);
echo "        quedaron " . count($aMedias) . " duelos con la tanda a medias\n";

/* Cuánto dinero hay RETENIDO ahí dentro. Un duelo en juego ya se lo cobró a los
   dos, así que ese bote no está en la suma de monedas de nadie: cerrarlo tiene
   que devolverlo entero al sistema, ni un duro más ni menos. */
$retenido = 0;
foreach ($aMedias as $f) $retenido += ((int) $f["monedas"]) * 2;
$totalAntes = (int) $p->query("SELECT SUM(monedas) FROM usuarios")->fetchColumn();

foreach ($aMedias as $f) {
    $p->prepare("UPDATE duelos SET partido_inicio = NOW() - INTERVAL 99999 SECOND,
                 partido_pausado_en = NULL WHERE id_duelo = :d")->execute([":d" => $f["id_duelo"]]);
    $db->cerrarPartidoSiToca((int) $f["id_duelo"]);
}

$colgados = (int) $p->query("SELECT COUNT(*) FROM duelos WHERE estado = 'en_juego'")->fetchColumn();
$colgados === 0 ? $ok("todos cerrados, ninguno se queda con el bote dentro") : $ko("$colgados siguen colgados");
$sinGanador = (int) $p->query("SELECT COUNT(*) FROM duelos
    WHERE estado = 'resuelto' AND id_ganador IS NULL AND dificultad IS NULL")->fetchColumn();
$sinGanador === 0 ? $ok("y ninguno quedo resuelto sin ganador") : $ko("$sinGanador resueltos sin ganador");

$totalDespues = (int) $p->query("SELECT SUM(monedas) FROM usuarios")->fetchColumn();
($totalDespues - $totalAntes) === $retenido
    ? $ok("y vuelve al sistema exactamente lo retenido ($retenido)")
    : $ko("vuelven " . ($totalDespues - $totalAntes) . " y estaban retenidos $retenido");

echo "\n" . ($fallos ? "$fallos FALLO(S)\n" : "Todo correcto.\n");
exit($fallos ? 1 : 0);
