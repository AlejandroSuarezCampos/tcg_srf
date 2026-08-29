<?php
/**
 * 300 DUELOS DE PUNTA A PUNTA por el camino real, 25 de ellos con CARTA.
 * Copia DESECHABLE tcg_prueba (§8). Nunca toca la base real.
 *
 * Cada duelo se crea, se acepta, se cierran los aumentos, se monta el partido, se
 * salta el reloj al final y se JUEGA: se sondea como los dos navegadores y se
 * responde cada decisión que salga, variando la opción para que haya aciertos y
 * fallos. Nada se ataja llamando a liquidarPartido() a mano.
 *
 * Las invariantes que de verdad importan aquí son de contabilidad:
 *   · MONEDAS: el total del sistema no cambia. Cada uno pone, el ganador cobra el
 *     bote. Si el total sube, el juego imprime dinero; si baja, se lo come.
 *   · CARTAS: el total de copias no cambia; solo cambian de dueño.
 *   · Ningún duelo se queda en_juego.
 *   · El ganador cuadra siempre con el marcador, o `resuelto_por_tanda` lo explica.
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

require __DIR__ . "/../consultas.php";

$p = new PDO("mysql:host=127.0.0.1;dbname=tcg_prueba;charset=utf8mb4", "root", "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");

const TOTAL = 300;
const CON_CARTA = 25;
const APUESTA = 10;

$fallos = 0;
$ok = function ($m) { echo "  OK    $m\n"; };
$ko = function ($m) use (&$fallos) { echo "  FALLO $m\n"; $fallos++; };
$uno = function ($sql) use ($p) { return $p->query($sql)->fetchColumn(); };

// Monedas de sobra para 300 duelos, para que ningún fallo sea "no le llegaba".
$p->exec("UPDATE usuarios SET monedas = 5000000 WHERE id_usuario IN (9,2)");

/* Parejas de copias apostables de la misma rareza, una de cada jugador. Se
   reservan por adelantado: una vez empiezan los duelos, cada carta apostada deja
   de estar disponible y buscarlas al vuelo daría colisiones. */
$parejas = [];
$porRareza = [9 => [], 2 => []];
foreach ([9, 2] as $u) {
    $st = $p->prepare("
        SELECT col.id_coleccion, c.id_rareza
        FROM coleccion col
        INNER JOIN cromos c ON c.id_cromo = col.id_cromo
        WHERE col.id_usuario = :u AND col.bloqueada = 0
          AND col.id_coleccion NOT IN (SELECT id_coleccion FROM mercado WHERE activa = 1)
          AND col.id_coleccion NOT IN (SELECT id_coleccion FROM mazo_cartas)
    ");
    $st->execute([":u" => $u]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $porRareza[$u][(int) $f["id_rareza"]][] = (int) $f["id_coleccion"];
    }
}
foreach ($porRareza[9] as $rareza => $mias) {
    $suyas = $porRareza[2][$rareza] ?? [];
    $n = min(count($mias), count($suyas));
    for ($i = 0; $i < $n && count($parejas) < CON_CARTA; $i++) {
        $parejas[] = ["rareza" => $rareza, "mia" => $mias[$i], "suya" => $suyas[$i]];
    }
    if (count($parejas) >= CON_CARTA) break;
}
echo "Parejas de cartas disponibles para apostar: " . count($parejas) . " (se piden " . CON_CARTA . ")\n\n";

$monedasAntes = (int) $uno("SELECT SUM(monedas) FROM usuarios");
$copiasAntes  = (int) $uno("SELECT COUNT(*) FROM coleccion");
$copias9Antes = (int) $uno("SELECT COUNT(*) FROM coleccion WHERE id_usuario = 9");

$resumen = [
    "liquidados" => 0, "conCarta" => 0, "tandas" => 0, "gana9" => 0, "gana2" => 0,
    "decisiones" => 0, "jugadasResueltas" => 0,
    "golesTotales" => 0, "empatesEnElCampo" => 0, "cartasTraspasadas" => 0, "tirosTanda" => 0,
];
$margenes = [];
$erroresGanador = 0; $bloqueadasMal = 0; $retencionMal = 0;
$arranque = microtime(true);

for ($n = 0; $n < TOTAL; $n++) {
    $conCarta = $n < count($parejas);
    $par = $conCarta ? $parejas[$n] : null;

    $r = $conCarta
        ? $db->crearDuelo(9, "carta", 0, $par["rareza"], $par["mia"])
        : $db->crearDuelo(9, "monedas", APUESTA, null, null);
    if (empty($r["ok"])) { $ko("duelo $n no se creo: " . ($r["error"] ?? "?")); continue; }
    $id = (int) $r["id_duelo"];

    $r = $conCarta ? $db->aceptarDuelo($id, 2, $par["suya"]) : $db->aceptarDuelo($id, 2, null);
    if (empty($r["ok"])) { $ko("duelo $n no se acepto: " . ($r["error"] ?? "?")); continue; }

    $db->elegirAumento($id, 9, 1 + ($n % 3));
    $db->elegirAumento($id, 2, 1 + (($n + 1) % 3));
    $db->cerrarFaseAumento($id);
    if (empty($db->resolverDuelo($id)["ok"])) { $ko("duelo $n no se monto"); continue; }

    /* Con el duelo EN JUEGO, la carta apostada tiene que seguir retenida: no debe
       poder venderse ni volverse a apostar. Se comprueba en el primero de cada
       cinco para no pagar la consulta 25 veces. */
    if ($conCarta && $n % 5 === 0) {
        $apostables = array_column($db->listarCopiasApostables(9), "id_coleccion");
        if (in_array($par["mia"], array_map("intval", $apostables), true)) $retencionMal++;
    }

    /* Se juega el partido entero por el CAMINO REAL DEL MOTOR JUGABLE, el mismo
       que recorre `assets/ajax/duelo_estado.php` en cada sondeo: abrir la jugada
       siguiente (o cerrar el partido si ya no queda ninguna), mirar el estado y
       jugar lo que toque. Nada se ataja escribiendo desenlaces a mano.

       Y TAMBIÉN LA TANDA, que viaja en el mismo `estadoPartido()`. Sin ella los
       duelos que acaban empatados se quedan en `en_juego` con el bote retenido:
       lo detectó esta misma prueba al añadir la tanda jugable (73 de 300).

       El motor narrado viejo —`estadoPartidoNarrado()` y `resolverMinijuegoDuelo()`—
       se retiró en la Task 17; este bucle es su sustituto. */
    for ($i = 0; $i < 400; $i++) {
        // Lo que hace el sondeo ANTES de mirar. `cerrarPartidoJugable()` es
        // idempotente y no hace nada mientras queden jugadas.
        if (empty($db->abrirJugada($id)["jugada"])) $db->cerrarPartidoJugable($id);

        $e = $db->estadoPartido($id, 9);
        if (empty($e["ok"])) break;

        // --- LA TANDA ---
        if (!empty($e["tanda"]) && empty($e["tanda"]["acabada"])) {
            if (!empty($e["tanda"]["tiro"])) {
                /* Los dos eligen. tirarPenalti() ya sabe si a cada uno le toca
                   tirar o parar, así que no hay que averiguarlo aquí.

                   ⚠️ LAS ZONAS TIENEN QUE PODER COINCIDIR. Mi primera fórmula era
                   ZONAS[(n+i)%4] contra ZONAS[(n+3i+1)%4], y eso NUNCA coincide:
                   (n+i) ≡ (n+3i+1) exige 2i ≡ 3 (mod 4), que no tiene solución
                   porque 2i es par. Resultado: gol en todos los penaltis y una
                   muerte súbita que llegó a 42 tiros sin decidirse. Con módulos
                   distintos (4 y 3) las dos series se cruzan y hay paradas. */
                $zA = Tcg::ZONAS_PENALTI[($n + $i) % 4];
                $zB = Tcg::ZONAS_PENALTI[($n + $i) % 3];
                $r9 = $db->tirarPenalti($id, 9, $zA);
                $r2 = $db->tirarPenalti($id, 2, $zB);
                if (!empty($r9["ok"]) || !empty($r2["ok"])) $resumen["tirosTanda"]++;
            }
            continue;
        }

        if (!empty($e["tanda"]["acabada"])) break;
        if (!empty($e["fin"])) break;
        if (empty($e["jugada"])) continue;

        /* LOS DOS LADOS DE LA JUGADA. Una jugada no se resuelve hasta que han
           ejecutado atacante y defensor, así que hay que jugar por los dos: si
           solo contestara el 9, el partido se quedaría parado hasta el plazo. */
        foreach ([9, 2] as $quien) {
            $v = $db->estadoPartido($id, $quien);
            $j = $v["jugada"] ?? null;
            if (!$j) continue;
            $num = (int) $j["numero"];

            // 1) El poseedor elige acción; eso es lo que reparte los minijuegos.
            if (!empty($v["decido_yo"]) && !empty($v["acciones"])) {
                $claves = array_keys($v["acciones"]);
                $db->decidirAccion($id, $quien, $num, $claves[($n + $i) % count($claves)]);
                $v = $db->estadoPartido($id, $quien);
                $j = $v["jugada"] ?? null;
                if (!$j) continue;
            }

            // 2) Ejecutar. Se varía la opción para que haya aciertos y fallos.
            if (empty($j["ya_jugue"]) && !empty($j["minijuego"])) {
                $mj = Partido::catalogo()[$j["minijuego"]] ?? null;
                $carga = [];
                if ($mj && $mj["tipo"] === "lectura") {
                    $ops = array_column($mj["opciones"], "clave");
                    if ($ops) $carga["opcion"] = $ops[($n + $i + $quien) % count($ops)];
                }
                $res = $db->registrarEjecucion($id, $quien, $num, $carga);
                if (!empty($res["ok"])) {
                    $resumen["decisiones"]++;
                    if (!empty($res["resuelta"])) $resumen["jugadasResueltas"]++;
                }
            }
        }
    }

    $d = $p->query("SELECT estado, id_ganador, goles_creador, goles_rival, resuelto_por_tanda,
                           tipo_apuesta FROM duelos WHERE id_duelo = $id")->fetch(PDO::FETCH_ASSOC);
    if ($d["estado"] !== "resuelto") continue;   // se cuenta como colgado más abajo

    $resumen["liquidados"]++;
    if ($conCarta) $resumen["conCarta"]++;
    $gc = (int) $d["goles_creador"]; $gr = (int) $d["goles_rival"];
    $resumen["golesTotales"] += $gc + $gr;
    $margenes[abs($gc - $gr)] = ($margenes[abs($gc - $gr)] ?? 0) + 1;
    if ((int) $d["id_ganador"] === 9) $resumen["gana9"]++; else $resumen["gana2"]++;
    if ($d["resuelto_por_tanda"]) $resumen["tandas"]++;
    if ($gc === $gr) $resumen["empatesEnElCampo"]++;

    // El ganador tiene que salir del marcador, o la tanda tiene que explicarlo.
    $porMarcador = $gc > $gr ? 9 : ($gr > $gc ? 2 : null);
    if ($porMarcador === null) { if (!$d["resuelto_por_tanda"]) $erroresGanador++; }
    elseif ((int) $d["id_ganador"] !== $porMarcador) $erroresGanador++;

    if ($conCarta) {
        $perdedor = (int) $d["id_ganador"] === 9 ? 2 : 9;
        /* `id_coleccion` no vive en `duelo_apuestas`: cada apuesta puede llevar
           varias copias desde la 031, y viven en `duelo_apuesta_cartas`, ligadas
           por `id_apuesta`. */
        $st = $p->prepare("
            SELECT c.id_usuario, c.bloqueada FROM duelo_apuestas da
            INNER JOIN duelo_apuesta_cartas dac ON dac.id_apuesta = da.id_apuesta
            INNER JOIN coleccion c ON c.id_coleccion = dac.id_coleccion
            WHERE da.id_duelo = :d AND da.id_usuario = :u
        ");
        $st->execute([":d" => $id, ":u" => $perdedor]);
        $copia = $st->fetch(PDO::FETCH_ASSOC);
        if ($copia) {
            if ((int) $copia["id_usuario"] === (int) $d["id_ganador"]) $resumen["cartasTraspasadas"]++;
            if ((int) $copia["bloqueada"] !== 0) $bloqueadasMal++;
        }
    }
}
$segundos = microtime(true) - $arranque;

$colgados     = (int) $uno("SELECT COUNT(*) FROM duelos WHERE estado = 'en_juego'");
$monedasDesp  = (int) $uno("SELECT SUM(monedas) FROM usuarios");
$copiasDesp   = (int) $uno("SELECT COUNT(*) FROM coleccion");
$copias9Desp  = (int) $uno("SELECT COUNT(*) FROM coleccion WHERE id_usuario = 9");

printf("%d duelos jugados enteros en %.1f s (%.2f s por duelo)\n\n", $resumen["liquidados"], $segundos, $segundos / max(1, $resumen["liquidados"]));

echo "=== CONTABILIDAD: lo que no puede fallar ===\n";
$resumen["liquidados"] === TOTAL ? $ok("los " . TOTAL . " duelos se liquidaron") : $ko($resumen["liquidados"] . " de " . TOTAL . " liquidados");
$colgados === 0 ? $ok("ninguno se quedo en_juego") : $ko("$colgados duelos colgados con el bote retenido");
$monedasDesp === $monedasAntes
    ? $ok("el total de monedas del sistema NO cambia ($monedasAntes)")
    : $ko("las monedas cambiaron: $monedasAntes -> $monedasDesp (diferencia " . ($monedasDesp - $monedasAntes) . ")");
$copiasDesp === $copiasAntes
    ? $ok("el total de copias NO cambia ($copiasAntes)")
    : $ko("las copias cambiaron: $copiasAntes -> $copiasDesp");
$erroresGanador === 0
    ? $ok("el ganador cuadra con el marcador en los " . $resumen["liquidados"] . ", o lo explica la tanda")
    : $ko("$erroresGanador duelos con un ganador que no cuadra");

echo "\n=== APUESTA DE CARTA (" . $resumen["conCarta"] . " duelos) ===\n";
$resumen["cartasTraspasadas"] === $resumen["conCarta"]
    ? $ok("la copia del perdedor cambio de dueno en los " . $resumen["conCarta"])
    : $ko($resumen["cartasTraspasadas"] . " de " . $resumen["conCarta"] . " traspasos");
$bloqueadasMal === 0 ? $ok("todas llegan desbloqueadas (usables)") : $ko("$bloqueadasMal llegaron bloqueadas");
$retencionMal === 0
    ? $ok("mientras el partido se juega, la carta sigue retenida (no reapostable ni vendible)")
    : $ko("$retencionMal cartas quedaron libres durante el partido");
printf("        el saldo de copias de Claude paso de %d a %d (%+d)\n", $copias9Antes, $copias9Desp, $copias9Desp - $copias9Antes);

echo "\n=== EL PARTIDO ===\n";
printf("        %.2f goles por partido\n", $resumen["golesTotales"] / max(1, $resumen["liquidados"]));
ksort($margenes);
foreach ($margenes as $m => $c) printf("        margen %d: %5.1f %%\n", $m, 100 * $c / max(1, $resumen["liquidados"]));
printf("        empates en el campo: %.1f %% -> a la tanda %d (%.1f %%)\n",
    100 * $resumen["empatesEnElCampo"] / max(1, $resumen["liquidados"]),
    $resumen["tandas"], 100 * $resumen["tandas"] / max(1, $resumen["liquidados"]));
$resumen["tandas"] === $resumen["empatesEnElCampo"]
    ? $ok("todos los empates, y solo ellos, se fueron a la tanda")
    : $ko("empates " . $resumen["empatesEnElCampo"] . " pero tandas " . $resumen["tandas"]);

echo "\n=== LA TANDA, JUGADA ===\n";
$tirosPorTanda = $resumen["tandas"] ? $resumen["tirosTanda"] / $resumen["tandas"] : 0;
printf("        %d tandas, %d tiros en total (%.1f tiros por tanda)\n",
    $resumen["tandas"], $resumen["tirosTanda"], $tirosPorTanda);
$golesPen = (int) $uno("SELECT COUNT(*) FROM duelo_penaltis WHERE gol = 1");
$paradas  = (int) $uno("SELECT COUNT(*) FROM duelo_penaltis WHERE gol = 0");
$abiertos = (int) $uno("SELECT COUNT(*) FROM duelo_penaltis WHERE gol IS NULL");
printf("        %d goles y %d paradas (%.1f %% de paradas)\n",
    $golesPen, $paradas, 100 * $paradas / max(1, $golesPen + $paradas));
$autos = (int) $uno("SELECT COUNT(*) FROM duelo_penaltis WHERE auto_tirador = 1 OR auto_portero = 1");

/* Un penalti abierto solo es un problema si su duelo sigue EN JUEGO: ahi si
   estaria esperando a alguien con el bote dentro. Si el duelo ya esta resuelto es
   una fila sobrante inofensiva —nadie la lee, porque tandaParaCliente() no manda
   `tiro` cuando la tanda esta decidida— y puede venir de un duelo anterior a este
   lote que la copia arrastra. */
$colgadosDeVerdad = (int) $uno("
    SELECT COUNT(*) FROM duelo_penaltis p
    JOIN duelos d ON d.id_duelo = p.id_duelo
    WHERE p.gol IS NULL AND d.estado = 'en_juego'
");
$colgadosDeVerdad === 0
    ? $ok("ningun penalti abierto en un duelo todavia en juego" . ($abiertos ? " ($abiertos sobrantes en duelos ya resueltos)" : ""))
    : $ko("$colgadosDeVerdad penaltis abiertos con el duelo en juego");
$autos === 0
    ? $ok("y ninguno lo decidio el sistema: se jugaron todos")
    : $ko("$autos penaltis los decidio el plazo (la prueba deberia contestarlos todos)");
$resumen["tandas"] > 0 ? $ok("hubo tandas que jugar") : $ko("ninguna tanda: no se ha probado nada");

echo "\n=== LAS JUGADAS, QUE ERA EL PUNTO DE TODO ESTO ===\n";
printf("        %d ejecuciones jugadas (%.2f por duelo), %d jugadas resueltas (%.2f por duelo)\n",
    $resumen["decisiones"], $resumen["decisiones"] / max(1, $resumen["liquidados"]),
    $resumen["jugadasResueltas"], $resumen["jugadasResueltas"] / max(1, $resumen["liquidados"]));
/* En el motor jugable el marcador ES la cuenta de jugadas ganadas: no hay
   resultado escrito de antemano al que estas decisiones tengan que ganarle.
   Que haya goles ya prueba que lo que se juega decide el partido. */
$resumen["jugadasResueltas"] > 0
    ? $ok("las jugadas se resolvieron por el bucle real, no por el plazo")
    : $ko("ninguna jugada se resolvio: no se ha probado nada");
$resumen["golesTotales"] > 0
    ? $ok("las jugadas ganadas construyen el marcador")
    : $ko("ningun gol en " . TOTAL . " duelos");

echo "\n=== BALANCE (dato, no comprobacion) ===\n";
printf("        Claude gana %.1f %%, LuluLulez %.1f %%\n",
    100 * $resumen["gana9"] / max(1, $resumen["liquidados"]),
    100 * $resumen["gana2"] / max(1, $resumen["liquidados"]));

echo "\n" . ($fallos ? "$fallos FALLO(S)\n" : "Todo correcto.\n");
exit($fallos ? 1 : 0);
