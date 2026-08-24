<?php
/**
 * LO QUE VALE UNA CARTA Y ENTRE QUÉ PRECIOS SE PUEDE VENDER.
 *
 * No toca ninguna base de datos: las dos consultas que hace `valorCarta()`
 * —probabilidad por rareza y distribución de poder— se le inyectan por
 * reflexión, así que se prueba el MODELO con números conocidos en vez de con
 * los que haya cargados hoy.
 *
 *     php db/pruebas/probar_valor_cartas.php
 *
 * Lo que se comprueba, que es exactamente lo que se pidió:
 *   1. Que NO se puede vender una SRF por 2 monedas.
 *   2. Que NO se puede vender una Común por 10 millones.
 *   3. Que el valor crece con la rareza, sin excepciones.
 *   4. Que dentro de una rareza mandan las estadísticas: una carta buena vale
 *      más que una mala del mismo tier, pero nunca tanto como para saltar por
 *      encima del tier de arriba.
 *   5. Que una carta SIN estadísticas (las hay: la mayoría de las legendarias
 *      están a 0/0/0) se queda en el valor base en vez de hundirse.
 *   6. Que el descarte es fijo por rareza — no lo mueven las estadísticas— y
 *      siempre menor que el mínimo de venta, para que no salga a cuenta.
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

require_once __DIR__ . "/../consultas.php";

/* Una instancia sin constructor (no hace falta conexión) con las dos consultas
   ya resueltas. Los números son los de la migración 050 y una distribución de
   poder parecida a la real (media ~170 en Común, ~273 en SRF). */
$tcg  = (new ReflectionClass(Tcg::class))->newInstanceWithoutConstructor();
$refl = new ReflectionClass(Tcg::class);

function inyectar($tcg, $refl, $nombre, $valor) {
	$p = $refl->getProperty($nombre);
	$p->setAccessible(true);
	$p->setValue($tcg, $valor);
}

// probabilidad POR CARTA: premium = (prob/100)/5 ; relleno = peso normalizado
inyectar($tcg, $refl, "cacheProbabilidadCarta", [
	1 => 60.0 / 95.0,          // Común
	2 => 25.0 / 95.0,          // Poco común
	3 => 10.0 / 95.0,          // Raro
	4 => (1.0000 / 100) / 5,   // Épico       1 cada   500 cartas
	5 => (0.3333 / 100) / 5,   // Legendario  1 cada 1.500
	6 => (0.2000 / 100) / 5,   // SRF         1 cada 2.500
	7 => (0.1000 / 100) / 5,   // Numerada    1 cada 5.000
]);
inyectar($tcg, $refl, "cacheEstadisticasRareza", [
	1 => ["n" => 164, "media" => 168.4, "desv" => 10.0],
	2 => ["n" =>  86, "media" => 189.8, "desv" => 10.7],
	3 => ["n" =>  47, "media" => 213.2, "desv" => 10.9],
	4 => ["n" =>  15, "media" => 240.7, "desv" =>  6.0],
	5 => ["n" =>  20, "media" => 258.0, "desv" =>  9.0],
	6 => ["n" =>   5, "media" => 273.4, "desv" =>  8.5],
	7 => ["n" =>   1, "media" => 286.0, "desv" =>  1.0],
]);

$fallos = 0;
function comprobar($que, $ok, $detalle = "") {
	global $fallos;
	if (!$ok) { $fallos++; }
	printf("  [%s] %s%s\n", $ok ? "ok" : "FALLA", $que, $detalle !== "" ? "  — $detalle" : "");
}
function carta($rareza, $poder) {
	$t = (int) floor($poder / 3);
	return ["id_rareza" => $rareza, "ataque" => $t, "defensa" => $t,
	        "tecnica" => $poder - 2 * $t];
}
function mon($n) { return number_format($n, 0, ",", "."); }

$NOMBRES = [1 => "Común", 2 => "Poco común", 3 => "Raro", 4 => "Épico",
            5 => "Legendario", 6 => "SRF", 7 => "Numerada"];
$MEDIA   = [1 => 168, 2 => 190, 3 => 213, 4 => 241, 5 => 258, 6 => 273, 7 => 286];

// ---------------------------------------------------------------------------
echo "\nTASA DE UNA CARTA MEDIA DE CADA RAREZA\n";

$tasas = [];
foreach ($NOMBRES as $r => $nombre) {
	$t = $tcg->valorCarta(carta($r, $MEDIA[$r]));
	$tasas[$r] = $t;
	printf("  %-11s valor %9s   venta %9s – %-9s   descarte %s\n",
		$nombre, mon($t["valor"]), mon($t["min"]), mon($t["max"]), mon($t["descarte"]));
}

// ---------------------------------------------------------------------------
echo "\n1-2. LO QUE SE PIDIÓ QUE NO PUDIERA PASAR\n";

comprobar("una SRF NO se puede vender por 2 monedas",
	$tasas[6]["min"] > 2, "el mínimo son " . mon($tasas[6]["min"]));
comprobar("una Común NO se puede vender por 10 millones",
	$tasas[1]["max"] < 10000000, "el máximo son " . mon($tasas[1]["max"]));
/* El que de verdad cierra el exploit de pasar monedas entre cuentas: por muy
   arriba que se ponga una Común, no llega ni al suelo de un Épico. */
comprobar("el techo de una Común queda por debajo del suelo de un Épico",
	$tasas[1]["max"] < $tasas[4]["min"],
	mon($tasas[1]["max"]) . " < " . mon($tasas[4]["min"]));

// ---------------------------------------------------------------------------
echo "\n3. EL VALOR CRECE CON LA RAREZA\n";

$monotona = true;
for ($r = 2; $r <= 7; $r++) {
	if ($tasas[$r]["valor"] <= $tasas[$r - 1]["valor"]) { $monotona = false; }
}
comprobar("cada rareza vale más que la anterior", $monotona);

// ---------------------------------------------------------------------------
echo "\n4. DENTRO DE LA RAREZA MANDAN LAS ESTADÍSTICAS\n";

$mala  = $tcg->valorCarta(carta(3, 190));   // ~-2σ en Raro
$media = $tcg->valorCarta(carta(3, 213));
$buena = $tcg->valorCarta(carta(3, 236));   // ~+2σ en Raro

printf("      Raro flojo %s · medio %s · bueno %s\n",
	mon($mala["valor"]), mon($media["valor"]), mon($buena["valor"]));

comprobar("una carta buena vale más que una floja del mismo tier",
	$buena["valor"] > $media["valor"] && $media["valor"] > $mala["valor"]);
comprobar("pero el mejor Raro no alcanza al Épico medio",
	$buena["valor"] < $tasas[4]["valor"],
	mon($buena["valor"]) . " < " . mon($tasas[4]["valor"]));

// ---------------------------------------------------------------------------
echo "\n5. CARTAS SIN ESTADÍSTICAS CARGADAS\n";

$sinStats = $tcg->valorCarta(["id_rareza" => 5, "ataque" => 0, "defensa" => 0, "tecnica" => 0]);
printf("      Legendaria a 0/0/0: %s (la media de su tier vale %s)\n",
	mon($sinStats["valor"]), mon($tasas[5]["valor"]));
comprobar("una carta a 0/0/0 se queda en el valor base de su rareza",
	$sinStats["valor"] === $tasas[5]["valor"]);

// ---------------------------------------------------------------------------
echo "\n6. EL DESCARTE\n";

$descarteFijo = true;
foreach ([190, 213, 236] as $poder) {
	if ($tcg->valorCarta(carta(3, $poder))["descarte"] !== $tasas[3]["descarte"]) {
		$descarteFijo = false;
	}
}
comprobar("el descarte es fijo por rareza: no lo mueven las estadísticas", $descarteFijo);

$peorQueVender = true;
foreach ($NOMBRES as $r => $nombre) {
	if ($tasas[$r]["descarte"] >= $tasas[$r]["min"]) { $peorQueVender = false; }
}
comprobar("descartar siempre paga menos que el peor precio de venta", $peorQueVender);

echo "\n" . ($fallos === 0 ? "TODO CORRECTO\n\n" : "$fallos COMPROBACIONES FALLIDAS\n\n");
exit($fallos === 0 ? 0 : 1);
