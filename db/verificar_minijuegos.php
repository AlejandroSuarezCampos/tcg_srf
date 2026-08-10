<?php
/**
 * VERIFICADOR DEL CATÁLOGO DE MINIJUEGOS  (CLAUDE.md §15.9)
 *
 * Comprueba, sin tocar la base de datos, las invariantes que hacen que un
 * minijuego sea una decisión de verdad y no una lotería ni un regalo. Pásalo
 * SIEMPRE que añadas una entrada a db/minijuegos.php:
 *
 *     C:\xampp\php\php.exe db/verificar_minijuegos.php
 *
 * Qué mira, y por qué cada cosa ha costado sangre alguna vez:
 *   1. Ciclo cerrado — cada opción gana exactamente un valor del dato oculto y
 *      cada valor lo gana exactamente una opción. Sin esto hubo un remate que
 *      no paraba NINGUNA opción (un tercio de jugadas decididas de antemano).
 *   2. Una sola opción "segura", y claves de opción sin repetir.
 *   3. Que la clave de una opción no delate el valor que gana: viaja al cliente.
 *   4. Plazos completos para PvP y las cinco dificultades.
 *   5. Reparto del dato oculto a ~1/3 — si no, hay opción dominante (§1.5 r2).
 *   6. Que TODAS las entradas lleguen a ofrecerse (una entrada que no sale es
 *      código muerto, que es lo que pasaba antes con la segunda de cada familia).
 *   7. Determinismo: misma semilla y evento, mismo minijuego. El servidor tiene
 *      que poder recalcular lo que el cliente jugó.
 *   8. Que leer la pista pague (>33 %) pero no lo resuelva solo (<70 %).
 *   9. Primitiva conocida, y si es "medidor", que traiga `velocidad` completa.
 *  10. Que la opción segura de un medidor esté EN EL CENTRO. Es la única zona
 *      que la aguja cruza dos veces por ciclo, o sea la más fácil de acertar:
 *      si la segura no estuviera ahí, fallar el pulso llevaría a la opción de
 *      más premio en vez de a la conservadora, que es lo contrario de lo que
 *      pide §1.5 regla 4.
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

require __DIR__ . "/conexion.php";

$fallos = [];
$ok = fn($s) => print("  OK    $s\n");
$ko = function ($s) use (&$fallos) { $fallos[] = $s; print("  FALLO $s\n"); };

$cat = Tcg::catalogoMinijuegos();
$valores = [
    "remate"             => ["potente", "colocado", "raso"],
    "estilo_portero"     => ["achica", "tierra", "espera"],
    "colocacion_defensa" => ["salta", "aguanta", "sale"],
    "reaccion_rival"     => ["protesta", "teatro", "sigue"],
];

/* Once sintético. "hueco" y no "posicion": plantillaPorLinea() clasifica por el
   índice de hueco, y sin él las once cartas caen en portería y las demás líneas
   quedan vacías — el dato oculto sale entonces siempre por su valor por defecto
   y todo parece dominante. Ya pasó al escribir este verificador. */
function equipoStub($sem) {
    $c = [];
    for ($i = 0; $i < 11; $i++) {
        $c[] = ["nombre" => "J{$sem}_{$i}", "hueco" => $i,
            "ataque"  => 40 + (($i * 7  + $sem * 3) % 50),
            "defensa" => 40 + (($i * 11 + $sem * 5) % 50),
            "tecnica" => 40 + (($i * 13 + $sem * 2) % 50)];
    }
    return ["cartas" => $c, "formacion" => "442",
            "fuerza" => ["POR" => 60, "DF" => 60, "MC" => 60, "DC" => 60, "total" => 240],
            "nombre" => "Eq$sem"];
}

/* Recorre partidos simulados y entrega cada minijuego ofrecido, ya con las
   cartas del bando correcto. Replica el mapeo de narracionDuelo(): quien ataca
   la jugada es $e["lado"], y el dato oculto puede salir de cualquiera de los
   dos bandos según lo que se adivine.

   El filtro de abajo tiene que ser LITERALMENTE el $tieneSentido de
   narracionDuelo(). Defender ya no exige un gol, sino que la jugada del rival
   traiga `familia_def` — si esta copia se quedara con la condición vieja, las
   entradas defensivas sobre parada/despeje/córner saldrían aquí como código
   muerto aunque en el partido real se ofrezcan.

   NO se aplica el presupuesto por partido (partido_minijuegos_max ni el tope de
   las de impacto "ninguno"): aquí se mide si una entrada es ALCANZABLE, no
   cuántas caben en un encuentro. */
function recorrer($n, callable $fn) {
    for ($d = 0; $d < $n; $d++) {
        $s = ($d * 0.00317) - floor($d * 0.00317);
        $sim = Tcg::generarEventosPartido(equipoStub(1), equipoStub(2), $s, []);
        foreach ($sim["eventos"] as $e) {
            if ($e["lado"] === null) continue;
            foreach ([true, false] as $def) {
                if ($def ? empty($e["familia_def"]) : ($e["tipo"] === "gol")) continue;
                $e["interactivo"] = true;
                $mj = Tcg::minijuegoDeEvento($e, $def, $s);
                if (!$mj) continue;
                $cAt = $e["lado"] === "local" ? equipoStub(1)["cartas"] : equipoStub(2)["cartas"];
                $cDf = $e["lado"] === "local" ? equipoStub(2)["cartas"] : equipoStub(1)["cartas"];
                $fn($e, $mj, Tcg::datoOcultoLoPoneElDefensor($mj) ? $cDf : $cAt, $s, $def);
            }
        }
    }
}

echo "Catálogo: " . count($cat) . " entradas\n";

echo "\n=== 1-4. Forma de cada entrada ===\n";
foreach ($cat as $clave => $mj) {
    $esperados = $valores[$mj["oculto"] ?? ""] ?? null;
    if (!$esperados) { $ko("$clave: dato oculto desconocido"); continue; }

    $gana = array_column($mj["opciones"], "gana"); sort($gana);
    $esp = $esperados; sort($esp);
    if ($gana !== $esp) { $ko("$clave: ciclo NO cerrado (" . implode(",", $gana) . ")"); continue; }

    if (count(array_filter($mj["opciones"], fn($o) => !empty($o["segura"]))) !== 1) {
        $ko("$clave: debe haber exactamente una opción segura"); continue;
    }
    $claves = array_column($mj["opciones"], "clave");
    if (count(array_unique($claves)) !== count($claves)) { $ko("$clave: claves repetidas"); continue; }

    foreach ($mj["opciones"] as $o) {
        foreach ($esperados as $v) {
            if (stripos($o["clave"], $v) !== false) $ko("$clave/{$o['clave']}: la clave delata '$v'");
        }
    }
    $porDificultad = ["pvp","facil","medio","dificil","muy_dificil","extremo"];
    $faltan = array_diff($porDificultad, array_keys($mj["plazo"] ?? []));
    if ($faltan) { $ko("$clave: faltan plazos " . implode(",", $faltan)); continue; }

    /* 9-10. La primitiva, y las dos reglas que solo aplican al medidor. */
    /* El impacto y, si es "partido", su efecto. Un `partido` sin efecto sería
       una decisión que promete arrastrar al resto del encuentro y no arrastra
       nada — el "continuar disfrazado" que prohíbe §1.5 regla 2. */
    $impacto = $mj["impacto"] ?? "jugada";
    if (!in_array($impacto, ["ninguno", "jugada", "partido"], true)) {
        $ko("$clave: impacto desconocido '$impacto'"); continue;
    }
    if ($impacto === "partido") {
        $efectos = ["presupuesto_gol", "presupuesto_parada", "decision"];
        $efecto = $mj["efecto"] ?? null;
        if (!in_array($efecto, $efectos, true)) {
            $ko("$clave: impacto \"partido\" exige `efecto` de " . implode("|", $efectos)
                . " (trae '" . ($efecto ?? "ninguno") . "')");
            continue;
        }
    } elseif (isset($mj["efecto"])) {
        $ko("$clave: trae `efecto` sin ser de impacto \"partido\"; no lo leería nadie");
        continue;
    }

    $primitiva = $mj["primitiva"] ?? "eleccion";
    if (!in_array($primitiva, ["eleccion", "medidor", "zona", "arrastre"], true)) {
        $ko("$clave: primitiva desconocida '$primitiva'"); continue;
    }
    if ($primitiva === "arrastre") {
        /* Un sector por opción y sin repetir: el cliente resuelve el gesto
           buscando el `sector` que casa con el ángulo, así que dos opciones en el
           mismo sector dejarían una inalcanzable con el gesto (aunque siguiera
           pulsable con el botón, que es su alternativa de SC 2.5.7). */
        $sectores = ["izquierda", "centro", "derecha"];
        $usadosSec = [];
        $malSec = false;
        foreach ($mj["opciones"] as $o) {
            $s = $o["sector"] ?? null;
            if (!in_array($s, $sectores, true)) {
                $ko("$clave/{$o['clave']}: sector '" . ($s ?? "(ninguno)") . "' no es izquierda|centro|derecha");
                $malSec = true; break;
            }
            if (isset($usadosSec[$s])) { $ko("$clave: dos opciones en el sector '$s'"); $malSec = true; break; }
            $usadosSec[$s] = true;
        }
        if ($malSec) continue;
    }
    if ($primitiva === "zona") {
        /* El lienzo y las zonas tienen que existir en Tcg::LIENZOS_ZONA, que es la
           misma lista que usan las grid-template-areas de layout.css. Una zona que
           el CSS no conozca se coloca automáticamente y descuadra el mapa SIN dar
           ningún error, así que se comprueba aquí. */
        $lienzo = $mj["lienzo"] ?? null;
        if (!isset(Tcg::LIENZOS_ZONA[$lienzo])) {
            $ko("$clave: lienzo desconocido '" . ($lienzo ?? "(ninguno)") . "'"); continue;
        }
        $huecos = Tcg::LIENZOS_ZONA[$lienzo];
        $usadasZona = [];
        $malZona = false;
        foreach ($mj["opciones"] as $o) {
            $z = $o["zona"] ?? null;
            if (!in_array($z, $huecos, true)) {
                $ko("$clave/{$o['clave']}: zona '" . ($z ?? "(ninguna)") . "' no existe en '$lienzo'");
                $malZona = true; break;
            }
            if (isset($usadasZona[$z])) { $ko("$clave: dos opciones en la zona '$z'"); $malZona = true; break; }
            $usadasZona[$z] = true;
        }
        if ($malZona) continue;
    }
    if ($primitiva === "medidor") {
        $faltanVel = array_diff($porDificultad, array_keys($mj["velocidad"] ?? []));
        if ($faltanVel) { $ko("$clave: faltan velocidades " . implode(",", $faltanVel)); continue; }

        // La segura EN EL CENTRO: la aguja cruza esa zona dos veces por ciclo.
        $iSegura = null;
        foreach ($mj["opciones"] as $i => $o) { if (!empty($o["segura"])) $iSegura = $i; }
        $centro = intdiv(count($mj["opciones"]), 2);
        if ($iSegura !== $centro) {
            $ko("$clave: la segura debe ir en el centro del medidor (está la $iSegura, toca la $centro)");
            continue;
        }
    }

    $ok(sprintf("%-20s %-8s ciclo cerrado sobre %s", $clave, $primitiva, $mj["oculto"]));
}

echo "\n=== 5-6. Reparto del dato oculto y uso de cada entrada ===\n";
$rep = []; $usos = [];
recorrer(600, function ($e, $mj, $cartas, $s) use (&$rep, &$usos) {
    $usos[$mj["clave"]] = ($usos[$mj["clave"]] ?? 0) + 1;
    $v = Tcg::ocultoDeJugada($mj, $e, $cartas, $s);
    $rep[$mj["oculto"]][$v] = ($rep[$mj["oculto"]][$v] ?? 0) + 1;
});
foreach ($rep as $oculto => $cuentas) {
    $tot = array_sum($cuentas); $linea = []; $peor = 0;
    foreach ($valores[$oculto] as $v) {
        $p = 100 * ($cuentas[$v] ?? 0) / $tot;
        $linea[] = sprintf("%s %.1f%%", $v, $p);
        $peor = max($peor, abs($p - 100 / 3));
    }
    $txt = sprintf("%-20s %s", $oculto, implode("  ", $linea));
    $peor <= 8.0 ? $ok($txt) : $ko("$txt  <-- opción dominante");
}
foreach ($cat as $clave => $mj) {
    ($usos[$clave] ?? 0) > 0
        ? $ok(sprintf("%-20s se ofrece %d veces", $clave, $usos[$clave]))
        : $ko("$clave: NUNCA se ofrece (¿familia o tipo inalcanzable?)");
}

echo "\n=== 7. Determinismo ===\n";
$estable = true;
// El lado lo pasa recorrer(): deducirlo del tipo dejó de valer cuando defender
// pasó a ser posible en jugadas que no son gol.
recorrer(60, function ($e, $mj, $cartas, $s, $def) use (&$estable) {
    $a = Tcg::minijuegoDeEvento($e, $def, $s);
    $b = Tcg::minijuegoDeEvento($e, $def, $s);
    if (($a["clave"] ?? null) !== ($b["clave"] ?? null)) $estable = false;
    if (Tcg::ocultoDeJugada($mj, $e, $cartas, $s) !== Tcg::ocultoDeJugada($mj, $e, $cartas, $s)) $estable = false;
});
$estable ? $ok("la elección y el dato oculto se recalculan igual") : $ko("NO es determinista");

echo "\n=== 8. Cuánto vale leer la pista ===\n";
$sugiere = function ($p) {
    foreach ([
        "pega más fuerte" => "potente", "los que la colocan" => "colocado",
        "no tiene un remate" => "raso",  "se lanza pronto" => "tierra",
        "comerte el ángulo" => "achica", "no se define" => "espera",
        "aguanta la posición" => "aguanta", "rompen hacia el balón" => "sale",
        "no se casa" => "salta",
        "lo alargan en el suelo" => "teatro", "ir a por el árbitro" => "protesta",
        "no suele hacer aspavientos" => "sigue",
    ] as $frag => $v) if (str_contains($p, $frag)) return $v;
    return null;
};
$acc = [];
recorrer(900, function ($e, $mj, $cartas, $s) use (&$acc, $sugiere) {
    $real = Tcg::ocultoDeJugada($mj, $e, $cartas, $s);
    $o = $mj["oculto"];
    $acc[$o]["total"] = ($acc[$o]["total"] ?? 0) + 1;
    if ($sugiere(Tcg::pistaDeJugada($mj, $e, $cartas)) === $real) $acc[$o]["pista"] = ($acc[$o]["pista"] ?? 0) + 1;
    foreach ($mj["opciones"] as $op) {
        if (!empty($op["segura"]) && $op["gana"] === $real) $acc[$o]["ciego"] = ($acc[$o]["ciego"] ?? 0) + 1;
    }
});
foreach ($acc as $o => $a) {
    $pc = 100 * ($a["pista"] ?? 0) / $a["total"];
    $cc = 100 * ($a["ciego"] ?? 0) / $a["total"];
    $txt = sprintf("%-20s a ciegas %.1f%%  leyendo %.1f%%", $o, $cc, $pc);
    if ($pc <= $cc)   $ko("$txt  <-- la pista no aporta nada");
    elseif ($pc > 70) $ko("$txt  <-- la pista lo resuelve casi solo");
    else              $ok($txt);
}

echo "\n" . ($fallos ? count($fallos) . " FALLO(S)\n" : "TODO CORRECTO\n");
exit($fallos ? 1 : 0);
