<?php
/**
 * COLOCAR BIEN TIENE QUE RENDIR MÁS QUE COLOCAR MAL.
 *
 * El juego permite a propósito poner cualquier carta en cualquier hueco: no
 * hay reglas de posición, y el metajuego está en decidir dónde va cada uno
 * (ver PESOS_LINEA). Eso solo funciona si la colocación CORRECTA es la que
 * más puntúa; si sale mejor jugar con el portero de delantero, el sistema no
 * premia decidir bien, premia hacer trampa con la fórmula.
 *
 * ⚠️ ESTO ESTUVO ROTO Y SE VIO EN PARTIDAS REALES. Los pesos de cada línea no
 *    sumaban lo mismo —POR 3,00; DF 1,75; MC 2,00; DC 2,15—, así que la MISMA
 *    carta valía un 71 % más metida en la portería que en la defensa. Medido
 *    con el baremo real: un delantero Común aportaba 182 puesto de portero y
 *    149,6 puesto de delantero. La jugada óptima era ignorar las posiciones y
 *    amontonar lo mejor en la portería, y había gente ganando duelos y cadenas
 *    difíciles con el once entero cambiado de sitio.
 *
 *     php db/pruebas/probar_posiciones.php
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

require_once __DIR__ . "/../consultas.php";

$fallos = 0;
function comprobar(string $que, bool $ok, string $detalle = "") {
    global $fallos;
    if (!$ok) { $fallos++; }
    printf("  [%s] %s%s\n", $ok ? "ok" : "FALLA", $que, $detalle ? "  — $detalle" : "");
}

/* Cartas tipo del baremo real (rareza Común). POR y DF comparten perfil en la
   tabla de Alejandro, así que se usan las mismas cifras para los dos. */
$TIPO = [
    "POR" => ["ataque" => 57, "defensa" => 72, "tecnica" => 68],
    "DF"  => ["ataque" => 57, "defensa" => 72, "tecnica" => 68],
    "MC"  => ["ataque" => 63, "defensa" => 63, "tecnica" => 72],
    "DC"  => ["ataque" => 72, "defensa" => 57, "tecnica" => 68],
];

// ---------------------------------------------------------------------------
echo "\n1 · Los pesos de cada línea pesan LO MISMO en total\n";

/* Es la raíz del problema: si una línea suma más que otra, dónde pongas una
   carta cambia CUÁNTO vale, no solo qué estadística suya cuenta. Y entonces
   existe una línea "que paga más" y el juego se rompe solo. */
$sumas = [];
foreach (Tcg::PESOS_LINEA as $linea => $pesos) { $sumas[$linea] = array_sum($pesos); }
$ref = reset($sumas);
foreach ($sumas as $linea => $suma) {
    comprobar(sprintf("%-3s suma %.3f", $linea, $suma), abs($suma - $ref) < 0.001,
        sprintf("las demás suman %.3f", $ref));
}

// ---------------------------------------------------------------------------
echo "\n2 · Cada carta rinde MÁS en su propia posición\n";

/* ⚠️ SIN EXCEPCIONES, TAMPOCO LA PAREJA POR/DF. La primera versión de esta
   prueba se saltaba la pareja portería-defensa con el argumento de que
   comparten estadísticas en el baremo y por tanto "es la misma carta". Ese
   argumento era la tapadera del fallo: precisamente porque comparten
   estadísticas, la fórmula no podía distinguirlas y lo óptimo acababa siendo
   meter a tu mejor defensa bajo palos. Si el once del jugador dice que alguien
   es portero, jugar de portero tiene que ser lo que más le rinda. */
foreach ($TIPO as $suya => $carta) {
    $carta["posicion"] = $suya;
    $enSuSitio = Tcg::aportarCarta($carta, $suya);
    $mejorFuera = 0; $donde = "";
    foreach (["POR", "DF", "MC", "DC"] as $otra) {
        if ($otra === $suya) { continue; }
        $v = Tcg::aportarCarta($carta, $otra);
        if ($v > $mejorFuera) { $mejorFuera = $v; $donde = $otra; }
    }
    comprobar(sprintf("un %s rinde más de %s (%.1f) que de %s (%.1f)",
        $suya, $suya, $enSuSitio, $donde, $mejorFuera), $enSuSitio > $mejorFuera);
}

// ---------------------------------------------------------------------------
echo "\n2b · Cuanto más lejos de su puesto, menos rinde\n";

/* No basta con que su sitio sea el mejor: el orden tiene que ser coherente.
   Un central de medio debe rendir más que de delantero, y el portero de
   delantero debe ser el peor caso posible. Si no, seguiría habiendo
   colocaciones raras que compensan. */
foreach ($TIPO as $suya => $carta) {
    $carta["posicion"] = $suya;
    $porDistancia = [];
    foreach (["POR", "DF", "MC", "DC"] as $otra) {
        $d = abs(Tcg::ORDEN_LINEA[$suya] - Tcg::ORDEN_LINEA[$otra]);
        $porDistancia[$d][] = Tcg::aportarCarta($carta, $otra);
    }
    ksort($porDistancia);
    $ordenado = true; $previo = INF;
    foreach ($porDistancia as $d => $vals) {
        $maximo = max($vals);
        if ($maximo > $previo) { $ordenado = false; }
        $previo = $maximo;
    }
    comprobar("un $suya rinde menos cuanto más lejos lo pongas", $ordenado,
        implode(" > ", array_map(fn($v) => sprintf("%.0f", max($v)), $porDistancia)));
}

// ---------------------------------------------------------------------------
echo "
2c · La portería pide un portero
";

/* ⚠️ ESTA ES LA COMPROBACIÓN QUE FALTABA, y es la que obliga a que el
   rendimiento sea una MATRIZ y no una tabla por distancia. Midiendo solo
   "cuántas líneas se ha movido", un defensa en la portería rendía al 92 %. Un
   8 % de castigo, sobre dos cartas con ESTADÍSTICAS IDÉNTICAS —POR y DF
   comparten baremo en las siete rarezas—, no cambia nada del metajuego:
   seguía saliendo a cuenta tratar el hueco del portero como uno más y
   rellenarlo con cualquiera.

   Ser portero es otro oficio, no una línea más atrás. Meter ahí a quien no lo
   es tiene que doler de verdad. */
foreach (["DF", "MC", "DC"] as $suya) {
    $r = Tcg::rendimientoPuesto($suya, "POR");
    comprobar(sprintf("un %s de portero rinde al %d %% (tiene que bajar del 70)",
        $suya, round($r * 100)), $r <= 0.70);
}
comprobar("y un portero de portero rinde al 100 %",
    abs(Tcg::rendimientoPuesto("POR", "POR") - 1.0) < 0.0001);

// Y al revés: un portero fuera de la portería tampoco vale.
foreach (["DF", "MC", "DC"] as $otra) {
    $r = Tcg::rendimientoPuesto("POR", $otra);
    comprobar(sprintf("un portero de %s rinde al %d %% (tiene que bajar del 80)",
        $otra, round($r * 100)), $r <= 0.80);
}

/* En cambio, moverse entre líneas DE CAMPO tiene que seguir siendo barato: ahí
   es donde se quiere que haya decisión, no prohibición. */
foreach ([["DF","MC"], ["MC","DF"], ["MC","DC"], ["DC","MC"]] as $par) {
    $r = Tcg::rendimientoPuesto($par[0], $par[1]);
    comprobar(sprintf("un %s de %s sigue siendo jugable (%d %%)", $par[0], $par[1], round($r * 100)),
        $r >= 0.85);
}

// ---------------------------------------------------------------------------
echo "
2d · La matriz está bien formada
";

foreach (["POR", "DF", "MC", "DC"] as $p) {
    $fila = Tcg::RENDIMIENTO_FUERA_DE_PUESTO[$p] ?? null;
    comprobar("$p tiene su fila con las cuatro líneas", is_array($fila) && count($fila) === 4);
    if (!$fila) { continue; }
    comprobar("$p rinde al 100 % en su puesto y menos en las otras tres",
        abs($fila[$p] - 1.0) < 0.0001
        && count(array_filter($fila, function ($v) { return $v < 1.0; })) === 3);
}

// ---------------------------------------------------------------------------
echo "\n3 · Un once BIEN colocado gana a uno con todos cambiados\n";

/* Se montan dos onces con EXACTAMENTE las mismas once cartas y la misma
   formación; lo único que cambia es dónde va cada una. */
function once(array $tipoPorHueco, array $TIPO): array {
    $cartas = [];
    foreach ($tipoPorHueco as $hueco => $tipo) {
        /* `posicion` es lo que dice la carta que ES; `hueco` es dónde se la
           pone. Cuando no coinciden, `aportarCarta()` aplica el rendimiento
           por jugar fuera de puesto — que es justo lo que se quiere medir. */
        $cartas[] = $TIPO[$tipo] + ["hueco" => $hueco, "posicion" => $tipo];
    }
    return $cartas;
}

$formacion = "442";
$huecos = Tcg::huecosDe($formacion);         // POR, DF×4, MC×4, DC×2

// Bien: cada carta en la línea que le toca.
$bien = once($huecos, $TIPO);

/* Mal: el once DADO LA VUELTA. Es el caso que se vio en partidas reales —el
   portero de delantero y los delanteros bajo palos— y tiene una propiedad que
   hace la comparación honesta: invertir el orden es una PERMUTACIÓN, así que
   los dos onces llevan exactamente las mismas once cartas y lo único que
   cambia es dónde juega cada una. Comparar contra un equipo con otra
   composición no diría nada sobre la colocación. */
$cambiado = array_reverse($huecos);

$comprobarA = $cambiado; $comprobarB = $huecos;
sort($comprobarA); sort($comprobarB);
comprobar("los dos onces llevan las mismas once cartas", $comprobarA === $comprobarB);

$mal = once($cambiado, $TIPO);

$fBien = Tcg::fuerzaAlineacion($bien, $formacion);
$fMal  = Tcg::fuerzaAlineacion($mal, $formacion);

printf("      bien colocado: %.1f   ·   todos cambiados: %.1f   (%+.1f %%)\n",
    $fBien["total"], $fMal["total"], ($fMal["total"] / $fBien["total"] - 1) * 100);

comprobar("el once bien colocado suma más", $fBien["total"] > $fMal["total"]);

/* Y no por poco: si la diferencia fuera del 1 % daría igual colocar bien que
   mal, y el metajuego seguiría sin existir. */
comprobar("y la diferencia se nota (más de un 5 %)",
    $fBien["total"] > $fMal["total"] * 1.05,
    sprintf("%.1f %%", ($fBien["total"] / $fMal["total"] - 1) * 100));

// ---------------------------------------------------------------------------
echo "\n" . ($fallos === 0 ? "TODO CORRECTO\n\n" : "$fallos COMPROBACIONES FALLIDAS\n\n");
exit($fallos === 0 ? 0 : 1);
