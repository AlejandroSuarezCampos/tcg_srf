<?php
/**
 * FORMACIONES DE VARIAS LÍNEAS (`filas`) SIN ROMPER LAS DE SIEMPRE.
 *
 * El motor pasó de "tres líneas, una por rol" a "filas con un rol por hueco".
 * Lo que hay que demostrar no es que las nuevas funcionen —eso se ve mirando—
 * sino que las OCHO VIEJAS salen EXACTAMENTE igual que antes, byte a byte.
 *
 * ⚠️ POR QUÉ ESO ES LO CRÍTICO. `mazo_cartas.hueco` guarda un índice dentro de
 *    la lista que devuelve `huecosDe()`, y los duelos guardan su formación
 *    congelada. Si el orden de los huecos cambiara, todos los onces montados
 *    hasta hoy quedarían con las cartas en otro sitio —y puntuando con otros
 *    pesos— sin que nadie tocara nada. Si cambiaran solo las coordenadas, los
 *    campos se redibujarían torcidos. Los valores esperados de aquí abajo están
 *    copiados de la implementación ANTERIOR al cambio.
 *
 *     php db/pruebas/probar_formaciones.php
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

require_once __DIR__ . "/../consultas.php";

$fallos = 0;
function comprobar(string $que, bool $ok, string $detalle = "") {
    global $fallos;
    if (!$ok) { $fallos++; }
    printf("  [%s] %s%s\n", $ok ? "ok" : "FALLA", $que, $detalle ? "  — $detalle" : "");
}

/* Cómo colocaba el motor VIEJO: agrupando por rol, con la `y` de LINEA_Y.
   Se reimplementa aquí a propósito, para comparar contra una copia
   independiente y no contra el código que se está probando. */
function huecosViejos(array $lineas): array {
    $h = ["POR"];
    foreach (["DF", "MC", "DC"] as $i => $rol) {
        $h = array_merge($h, array_fill(0, $lineas[$i], $rol));
    }
    return $h;
}
function coordsViejas(array $lineas): array {
    $huecos = huecosViejos($lineas);
    $porLinea = [];
    foreach ($huecos as $i => $l) { $porLinea[$l][] = $i; }

    $coords = [];
    foreach ($porLinea as $linea => $indices) {
        $xs = Tcg::REPARTO_X[count($indices)] ?? Tcg::REPARTO_X[4];
        [$base, $a, $b] = Tcg::LINEA_Y[$linea];
        foreach ($indices as $n => $hueco) {
            $x = $xs[$n];
            $d = abs($x - 50) / 40;
            $coords[$hueco] = ["x" => $x, "y" => round($base + $a * $d + $b, 1)];
        }
    }
    ksort($coords);
    return $coords;
}

// ---------------------------------------------------------------------------
echo "\nLas ocho de siempre, contra el motor anterior\n";

$VIEJAS = [
    "442" => [4, 4, 2], "433" => [4, 3, 3], "352" => [3, 5, 2], "532" => [5, 3, 2],
    "451" => [4, 5, 1], "343" => [3, 4, 3], "541" => [5, 4, 1], "361" => [3, 6, 1],
];

foreach ($VIEJAS as $clave => $lineas) {
    $huecosIgual = Tcg::huecosDe($clave) === huecosViejos($lineas);
    $coordsIgual = Tcg::coordenadasDe($clave) == coordsViejas($lineas);
    comprobar("$clave · huecos idénticos", $huecosIgual,
        $huecosIgual ? "" : implode(",", Tcg::huecosDe($clave)));
    comprobar("$clave · coordenadas idénticas", $coordsIgual);
}

// ---------------------------------------------------------------------------
echo "\nTodas las formaciones del catálogo\n";

foreach (Tcg::FORMACIONES as $clave => $f) {
    $huecos = Tcg::huecosDe($clave);
    $coords = Tcg::coordenadasDe($clave);

    comprobar("$clave · once jugadores", count($huecos) === Tcg::MAZO_TAMANO,
        count($huecos) . " huecos");
    comprobar("$clave · un portero, y es el hueco 0",
        $huecos[0] === "POR" && count(array_filter($huecos, fn($h) => $h === "POR")) === 1);
    comprobar("$clave · una coordenada por hueco",
        count($coords) === count($huecos) && array_keys($coords) === range(0, count($huecos) - 1));

    /* Nadie se sale del campo. El retrato se dibuja centrado en su coordenada
       y `.alineacion` recorta, así que un 5 % o un 95 % se queda a medias
       fuera — es lo que ya obligó a meter los repartos de 5 y 6. */
    $dentro = true;
    foreach ($coords as $c) {
        if ($c["x"] < 10 || $c["x"] > 90 || $c["y"] < 8 || $c["y"] > 95) { $dentro = false; }
    }
    comprobar("$clave · nadie se sale del campo", $dentro);

    // Dos huecos en el mismo punto serían dos retratos superpuestos.
    $puntos = array_map(fn($c) => $c["x"] . "|" . $c["y"], $coords);
    comprobar("$clave · sin huecos superpuestos", count(array_unique($puntos)) === count($puntos));

    /* El nombre describe la forma SIN CONTAR AL PORTERO: un "4-4-2" son cuatro
       defensas, cuatro medios y dos delanteros. El portero no se nombra porque
       es siempre uno y en todas las formaciones. */
    $porFila = array_map(fn($fila) => count($fila["roles"]), Tcg::filasDe($clave));
    $esperado = implode("-", $porFila);
    $nombre = preg_replace('/\s*\(.*$/', '', $f["nombre"]);
    /* Esta comprobación YA cubre lo del portero: `porFila` no lo incluye, así
       que si el nombre lo contara sobraría un tramo y no cuadraría.
       ⚠️ NO vale comprobar "que no empiece por 1-": hay formas que arrancan de
          verdad con una línea de un solo jugador —el 1-3-5-1 lleva líbero— y
          esa comprobación las daba por malas estando bien. */
    comprobar("$clave · el nombre describe la forma ($esperado)", $nombre === $esperado, $f["nombre"]);
}

// ---------------------------------------------------------------------------
echo "\nCon qué se empieza\n";

/* Se arranca con dos formaciones y nada más; las otras cuarenta y tres se
   ganan completando cadenas. Va aquí porque es una decisión de producto fácil
   de romper sin querer: cada formación nueva que se añade al catálogo entra
   por defecto BLOQUEADA, y bastaría con meter una clave de más en
   FORMACIONES_LIBRES para regalarlas todas sin que nada fallara. */
comprobar("solo el 4-4-2 y el 4-3-3 son gratis",
    Tcg::FORMACIONES_LIBRES === ["442", "433"],
    implode(", ", Tcg::FORMACIONES_LIBRES));
comprobar("las libres existen en el catálogo",
    !array_diff(Tcg::FORMACIONES_LIBRES, array_map("strval", array_keys(Tcg::FORMACIONES))));
printf("      %d de %d formaciones son desbloqueables\n",
    count(Tcg::FORMACIONES) - count(Tcg::FORMACIONES_LIBRES), count(Tcg::FORMACIONES));

// ---------------------------------------------------------------------------
echo "\nLo que solo sabe hacer el modelo nuevo\n";

/* El caso que motivó todo: en un 1-4-2-3-1 la fila de tres son extremo,
   mediapunta y extremo. Con el modelo viejo los tres eran el mismo rol. */
$h4231 = Tcg::huecosDe("4231");
comprobar("4231 · la fila de tres es DC-MC-DC",
    array_slice($h4231, 7, 3) === ["DC", "MC", "DC"], implode(",", array_slice($h4231, 7, 3)));

$c4231 = Tcg::coordenadasDe("4231");
comprobar("4231 · los extremos y el punta están a distinta altura",
    $c4231[7]["y"] !== $c4231[10]["y"],
    "extremo y=" . $c4231[7]["y"] . " punta y=" . $c4231[10]["y"]);

/* Las filas tienen que ir de atrás hacia adelante. En este campo la `y` es el
   % DESDE ARRIBA y el ataque está arriba, así que "hacia adelante" es y que
   BAJA: la defensa a 77 y el punta a 16. Se comprueba en todas, no solo en la
   nueva, porque una fila declarada fuera de orden dibujaría la formación del
   revés sin que ninguna otra comprobación se enterara. */
foreach (Tcg::FORMACIONES as $clave => $f) {
    $ys = array_map(fn($fila) => $fila["y"], Tcg::filasDe($clave));
    comprobar("$clave · las filas van de atrás hacia adelante",
        $ys === array_sort_desc($ys), implode(" > ", $ys));
}

// ---------------------------------------------------------------------------
echo "\n" . ($fallos === 0 ? "TODO CORRECTO\n\n" : "$fallos COMPROBACIONES FALLIDAS\n\n");
exit($fallos === 0 ? 0 : 1);

/** Copia ordenada de mayor a menor, sin tocar el original. */
function array_sort_desc(array $a): array { rsort($a); return $a; }
