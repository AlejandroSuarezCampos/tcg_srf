<?php
/**
 * LA PROBABILIDAD DE LAS RAREZAS ALTAS ES POR SOBRE, NO POR CARTA.
 *
 * No toca ninguna base de datos: `elegirCartasSobre()` es lógica pura sobre el
 * array de cartas disponibles, así que se prueba con un bombo de mentira y se
 * cuenta lo que sale. Se ejecuta con:
 *
 *     php db/pruebas/probar_probabilidad_sobre.php
 *
 * Lo que se comprueba:
 *   1. Un sobre trae siempre EXACTAMENTE las cartas que pide, ni una más.
 *   2. Ningún sobre trae dos cartas premium. Es la contrapartida asumida de
 *      la tirada única: si esto falla, las rarezas altas han vuelto a
 *      multiplicarse por el tamaño del sobre.
 *   3. La frecuencia POR SOBRE de cada tier premium es la que dice la tabla:
 *      SRF 1/500, Legendario 1/300, Épico 1/100. Con el bug viejo el SRF salía
 *      ~2,5 %, o sea fuera de la horquilla por goleada.
 *   4. El ratio NO depende de qué otras rarezas tenga la expansión. Es lo que
 *      garantiza la escala fija de 100 de `sortearPremio()`: antes se sorteaba
 *      contra la suma de las presentes, así que quitar un tier le regalaba su
 *      probabilidad a los demás.
 *   5. Una expansión que solo tenga rarezas premium sigue entregando el sobre
 *      entero en vez de devolverlo a medias.
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

require_once __DIR__ . "/../consultas.php";

/** Llama al método privado sin abrir la clase al resto del mundo. */
function elegir(array $bombo, int $cantidad): array {
    static $metodo = null;
    if ($metodo === null) {
        $metodo = (new ReflectionClass(Tcg::class))->getMethod("elegirCartasSobre");
        $metodo->setAccessible(true);
    }
    // La instancia no se usa: el método no toca $this. Se salta el constructor
    // para no necesitar una conexión.
    $tcg = (new ReflectionClass(Tcg::class))->newInstanceWithoutConstructor();
    return $metodo->invoke($tcg, $bombo, $cantidad);
}

/** Bombo de mentira: las mismas rarezas y pesos que la tabla `rarezas`. */
function bombo(array $rarezas): array {
    // Los mismos valores que siembra la migración 050.
    $PESO = [1 => 60.0, 2 => 25.0, 3 => 10.0, 4 => 1.0, 5 => 0.3333, 6 => 0.2, 7 => 0.1];
    $cartas = [];
    foreach ($rarezas as $r) {
        for ($i = 0; $i < 8; $i++) {   // varias por tier, para que array_rand tenga de dónde
            $cartas[] = [
                "id_cromo"     => $r * 100 + $i,
                "id_rareza"    => $r,
                "probabilidad" => $PESO[$r],
                "cupo_numerado" => null,
            ];
        }
    }
    return $cartas;
}

$fallos = 0;
function comprobar(string $que, bool $ok, string $detalle = "") {
    global $fallos;
    if (!$ok) { $fallos++; }
    printf("  [%s] %s%s\n", $ok ? "ok" : "FALLA", $que, $detalle ? "  — $detalle" : "");
}

// ---------------------------------------------------------------------------
echo "\nBombo completo (rarezas 1-6), 200.000 sobres de 5 cartas\n";

$TIRADAS  = 200000;
$POR_SOBRE = 5;
$bombo = bombo([1, 2, 3, 4, 5, 6]);

$conSrf = 0; $conEpico = 0; $conLegendario = 0;
$tamanoMalo = 0; $dobles = 0;

for ($i = 0; $i < $TIRADAS; $i++) {
    $sobre = elegir($bombo, $POR_SOBRE);
    if (count($sobre) !== $POR_SOBRE) { $tamanoMalo++; }

    $premium = 0;
    $rarezas = [];
    foreach ($sobre as $c) {
        $rarezas[] = (int) $c["id_rareza"];
        if ((int) $c["id_rareza"] >= Tcg::RAREZA_PREMIUM) { $premium++; }
    }
    if ($premium > 1) { $dobles++; }
    if (in_array(6, $rarezas, true)) { $conSrf++; }
    if (in_array(5, $rarezas, true)) { $conLegendario++; }
    if (in_array(4, $rarezas, true)) { $conEpico++; }
}

comprobar("todos los sobres traen $POR_SOBRE cartas", $tamanoMalo === 0, "$tamanoMalo mal");
comprobar("ningún sobre trae dos premium", $dobles === 0, "$dobles con dos o más");

/* La horquilla es amplia a propósito: esto es un sorteo, no una cuenta. Con
   200.000 tiradas la desviación típica del 0,20 % es ~0,010 puntos, así que
   [0,15 – 0,25] son unas cinco sigmas: no salta por azar, solo si la lógica
   cambia. El valor del bug viejo (2,47 %) queda muy fuera. */
$pctSrf = $conSrf / $TIRADAS * 100;
$pctLeg = $conLegendario / $TIRADAS * 100;
$pctEpi = $conEpico / $TIRADAS * 100;

$unoDeCada = fn($pct) => $pct > 0 ? round(100 / $pct) : 0;
printf("      SRF        %.3f %% (tabla 0,200 · 1 de cada %d, se busca 500)\n", $pctSrf, $unoDeCada($pctSrf));
printf("      Legendario %.3f %% (tabla 0,333 · 1 de cada %d, se busca 300)\n", $pctLeg, $unoDeCada($pctLeg));
printf("      Épico      %.3f %% (tabla 1,000 · 1 de cada %d, se busca 100)\n", $pctEpi, $unoDeCada($pctEpi));

comprobar("el SRF sale ~1 de cada 500 sobres", $pctSrf > 0.15 && $pctSrf < 0.25);
comprobar("el Legendario sale ~1 de cada 300 sobres", $pctLeg > 0.27 && $pctLeg < 0.40);
comprobar("el Épico sale ~1 de cada 100 sobres", $pctEpi > 0.87 && $pctEpi < 1.13);

// ---------------------------------------------------------------------------
/* EL RATIO NO PUEDE DEPENDER DE LAS DEMÁS RAREZAS. Con el sorteo viejo —contra
   la suma de las presentes— quitar el Épico del bombo le regalaba su 1 % al
   resto y el SRF se disparaba. Con la escala fija de 100, el SRF sigue en su
   0,20 % aunque sea el único premium de la expansión. */
echo "\nExpansión SIN Épico ni Legendario (1-3 y 6), 200.000 sobres\n";

$sinTiersMedios = bombo([1, 2, 3, 6]);
$conSrfSolo = 0;
for ($i = 0; $i < $TIRADAS; $i++) {
    foreach (elegir($sinTiersMedios, $POR_SOBRE) as $c) {
        if ((int) $c["id_rareza"] === 6) { $conSrfSolo++; break; }
    }
}
$pctSolo = $conSrfSolo / $TIRADAS * 100;
printf("      SRF %.3f %% (sigue siendo 1 de cada %d)\n", $pctSolo, $unoDeCada($pctSolo));
comprobar("el SRF no se infla al faltar los tiers de en medio", $pctSolo > 0.15 && $pctSolo < 0.25);

// ---------------------------------------------------------------------------
echo "\nExpansión SOLO con rarezas premium (5 y 6)\n";

$soloPremium = bombo([5, 6]);
$sobre = elegir($soloPremium, $POR_SOBRE);
comprobar("entrega el sobre entero igualmente", count($sobre) === $POR_SOBRE, count($sobre) . " cartas");

// ---------------------------------------------------------------------------
echo "\nExpansión SIN premium (rarezas 1-3)\n";

$sinPremium = bombo([1, 2, 3]);
$vistos = [];
for ($i = 0; $i < 2000; $i++) {
    foreach (elegir($sinPremium, $POR_SOBRE) as $c) { $vistos[(int) $c["id_rareza"]] = true; }
}
ksort($vistos);
comprobar("solo reparte 1, 2 y 3", array_keys($vistos) === [1, 2, 3], implode(",", array_keys($vistos)));

// ---------------------------------------------------------------------------
echo "\n" . ($fallos === 0 ? "TODO CORRECTO\n\n" : "$fallos COMPROBACIONES FALLIDAS\n\n");
exit($fallos === 0 ? 0 : 1);
