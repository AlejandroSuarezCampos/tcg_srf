<?php
/**
 * Hace de SEGUNDO JUGADOR en la TANDA, contra la base real, para poder verificar
 * los penaltis con un solo navegador.
 *
 *   php rival_pen.php <id_duelo> <zona>   -> el rival elige ese hueco
 *   php rival_pen.php <id_duelo>          -> solo mira como va
 */
require __DIR__ . "/../consultas.php";
$db = new Tcg("127.0.0.1", 3306, "tcg", "root", "");
$RIVAL = 2;

$id   = (int) ($argv[1] ?? 0);
$zona = $argv[2] ?? null;

if ($zona !== null) {
    $r = $db->tirarPenalti($id, $RIVAL, $zona);
    echo "eleccion del rival ($zona): " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

// El rival tambien sondea, que es lo que empuja la tanda.
$e = $db->estadoPartido($id, $RIVAL);
$t = $e["tanda"] ?? null;
if (!$t) { echo "fase=" . ($e["fase"] ?? "?") . " sin tanda\n"; exit; }

printf("fase=%s  tanda %d-%d  acabada=%s\n",
    $e["fase"], $t["marcador"][0], $t["marcador"][1], $t["acabada"] ? "SI" : "no");
if (!empty($t["tiro"])) {
    printf("  tiro abierto: ronda %d, %s, ya_elegi=%s, quedan %ds\n",
        $t["tiro"]["ronda"], $t["tiro"]["tiro_yo"] ? "TIRA el rival" : "PARA el rival",
        $t["tiro"]["ya_elegi"] ? "si" : "no", $t["tiro"]["restante"]);
}
foreach ($t["historial"] as $h) {
    printf("  ronda %d: %s -> %s   (tirador %s / portero %s)%s\n",
        $h["ronda"], $h["mio"] ? "tiraba el rival" : "tiraba Claude",
        $h["gol"] ? "GOL" : "parada", $h["tirador"], $h["portero"],
        $h["auto"] ? " [automatico]" : "");
}
