<?php
/**
 * Dificultad por nodo (`029`) y cartas exclusivas de cadena (`030`).
 * Sobre la copia DESECHABLE tcg_prueba (§8). Nunca toca la base real.
 *
 * Comprueba lo único que no es obvio de este sistema: que un campo VACÍO
 * signifique "no pisar el valor general" y un CERO signifique "pisar con
 * cero". Si se confunden —y es fácil, un `(int)` de más lo hace— resulta
 * imposible anular un parámetro global desde un nodo, y nadie se enteraría
 * hasta que un Extremo ajustado se comportara como el Extremo de siempre.
 *
 * Cubre además la caché por petición de `ajustesNodo()`, que YA falló una vez
 * mientras se escribía esto: guardar y volver a leer dentro de la misma
 * petición devolvía el valor viejo porque nadie invalidaba la caché.
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

$dsn = "mysql:host=127.0.0.1;dbname=tcg_prueba;charset=utf8mb4";
try {
    $p = new PDO($dsn, "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    exit("No existe la base tcg_prueba. Móntala como para el resto de suites (§8).\n");
}

require __DIR__ . "/../consultas.php";
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");

$fallos = 0;
function comprobar($etiqueta, $obtenido, $esperado) {
    global $fallos;
    $bien = (string) $obtenido === (string) $esperado;
    if (!$bien) { $fallos++; }
    printf("  %-5s %-44s %s%s\n", $bien ? "OK" : "FALLO", $etiqueta,
        var_export($obtenido, true),
        $bien ? "" : "  (esperado " . var_export($esperado, true) . ")");
}

// ---------------------------------------------------------------------------
// La tabla y la columna tienen que existir en la copia. Si faltan, es que
// tcg_prueba se montó antes de las migraciones 029/030: se dice cuál falta en
// vez de fallar con un error de SQL que no explica nada.
// ---------------------------------------------------------------------------
$hayTabla = $p->query("SHOW TABLES LIKE 'cadena_nodo_dificultad'")->fetch();
if (!$hayTabla) { exit("Falta la migración 029 en tcg_prueba.\n"); }
$hayColumna = $p->query("SHOW COLUMNS FROM cromos LIKE 'solo_cadena'")->fetch();
if (!$hayColumna) { exit("Falta la migración 030 en tcg_prueba.\n"); }

$nodo = $p->query("SELECT id_nodo FROM cadena_nodos WHERE tipo = 'partido' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$nodo) { exit("tcg_prueba no tiene ningún nodo de partido.\n"); }
$id = (int) $nodo["id_nodo"];

/* Se limpian los ajustes que ese nodo pueda traer YA de la copia.
   Desde la `033` el panel calibra cadenas enteras de golpe, así que un nodo
   cualquiera de la base real llega con sus cinco filas de
   `cadena_nodo_dificultad` puestas — y la primera comprobación de aquí abajo
   ("sin ajuste, manda el general") medía entonces el ajuste del nodo en vez
   del global, y fallaba teniendo el motor razón.
   La prueba tiene que partir del estado que dice probar, no del que se
   encuentre. */
$p->prepare("DELETE FROM cadena_nodo_dificultad WHERE id_nodo = ?")->execute([$id]);

$globalMult   = $db->config("pve_mult_extremo", "1.0");
$globalCompos = $db->config("pve_compos_mult_extremo", "1.0");

echo "Dificultad por nodo (nodo $id de tcg_prueba)\n";

comprobar("sin ajuste, manda el general", $db->paramPve("mult", "extremo", $id, 1.0, "mult_fuerza"), $globalMult);

$db->guardarAjusteNodo($id, "extremo", [
    "activa" => 1, "mult_fuerza" => "1.400", "mult_compos" => "", "subir_rareza" => "0",
]);

comprobar("el nodo pisa el general",      $db->paramPve("mult", "extremo", $id, 1.0, "mult_fuerza"), "1.400");
comprobar("campo vacio NO pisa",          $db->paramPve("compos_mult", "extremo", $id, 1.0, "mult_compos"), $globalCompos);
comprobar("un CERO si pisa",              $db->paramPve("subir_rareza", "extremo", $id, null), "0");
comprobar("otra dificultad sin tocar",    $db->paramPve("mult", "facil", $id, 1.0, "mult_fuerza"), $db->config("pve_mult_facil", "1.0"));
comprobar("PvP (sin nodo) sin tocar",     $db->paramPve("mult", "extremo", null, 1.0, "mult_fuerza"), $globalMult);
comprobar("el listado admin lo trae",     isset($db->listarAjustesNodoAdmin($id)["extremo"]) ? "si" : "no", "si");

// `activa = 0` tiene que impedir crear el partido en esa dificultad.
$db->guardarAjusteNodo($id, "extremo", ["activa" => 0]);
$usuario = $p->query("SELECT id_usuario FROM usuarios LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$res = $db->crearPartidoCadena((int) $usuario["id_usuario"], $id, "extremo");
comprobar("activa=0 impide jugarlo",      $res["ok"] ? "deja" : "bloquea", "bloquea");

$db->borrarAjusteNodo($id, "extremo");
comprobar("tras borrar vuelve el general", $db->paramPve("mult", "extremo", $id, 1.0, "mult_fuerza"), $globalMult);

/* ---------------------------------------------------------------------------
   SUBIDA DE RAREZA DEL RIVAL (`pve_subir_rareza_<dif>`)
   El parámetro llevaba desde el principio guardándose sin que nadie lo leyera.
   Lo que se prueba aquí es lo único que puede salir mal al leerlo: que suba y
   que NUNCA baje. Una carta que empeora al "mejorar" de rareza pasaría
   desapercibida hasta que alguien mirara un Extremo y lo viera más flojo que
   su Difícil.
   --------------------------------------------------------------------------- */
echo "\nSubida de rareza del rival\n";

$muestraCartas = $p->query("
    SELECT id_cromo, nombre, id_rareza, ataque, defensa, tecnica
    FROM cromos WHERE ataque + defensa + tecnica > 0 ORDER BY id_rareza LIMIT 40
")->fetchAll(PDO::FETCH_ASSOC);

$bajaAlgo = 0;
$sube     = 0;
$fuera    = 0;
foreach ([1, 2, 3, 5] as $niveles) {
    $subidas = $db->subirRarezaCartas($muestraCartas, $niveles);
    foreach ($muestraCartas as $i => $c) {
        foreach (["ataque", "defensa", "tecnica"] as $st) {
            if ($subidas[$i][$st] < $c[$st]) { $bajaAlgo++; }
            if ($subidas[$i][$st] > 99)      { $fuera++; }
        }
        if ($subidas[$i]["id_rareza"] < $c["id_rareza"]) { $bajaAlgo++; }
        if ($subidas[$i]["id_rareza"] > 6)               { $fuera++; }
        if ($subidas[$i]["id_rareza"] > $c["id_rareza"]) { $sube++; }
    }
}
comprobar("subir rareza NUNCA baja nada",   $bajaAlgo, 0);
comprobar("nada se sale de rango (99 / 6)", $fuera, 0);
comprobar("y si que sube",                  $sube > 0 ? "si" : "no", "si");
comprobar("0 niveles no toca nada",         $db->subirRarezaCartas($muestraCartas, 0) === $muestraCartas ? "igual" : "cambia", "igual");

/* ---------------------------------------------------------------------------
   LAS COMPOS Y EL AUMENTO ENTRAN EN EL MARCADOR
   Antes solo movian la probabilidad que se muestra, y esa probabilidad dejo de
   decidir el partido cuando el marcador paso a salir de la simulacion. Lo que
   se comprueba es que la fuerza que se le pasa al simulador ya los lleva.
   --------------------------------------------------------------------------- */
echo "\nCompos y Aumento en la fuerza que simula el partido\n";

$bruta = ["POR" => 200.0, "DF" => 500.0, "MC" => 600.0, "DC" => 350.0];
$bruta["total"] = array_sum($bruta);
$cero     = ["POR" => 0, "DF" => 0, "MC" => 0, "DC" => 0];
$sinNada  = Tcg::fuerzaParaSimulacion(Tcg::calcularTotalFinal($bruta, $cero, 0), 0);
$conLinea = Tcg::fuerzaParaSimulacion(Tcg::calcularTotalFinal($bruta, ["POR" => 0, "DF" => 10, "MC" => 10, "DC" => 10], 0), 0);
$conTotal = Tcg::fuerzaParaSimulacion(Tcg::calcularTotalFinal($bruta, $cero, 8), 8);
$conMalus = Tcg::fuerzaParaSimulacion(Tcg::calcularTotalFinal($bruta, $cero, -20), -20);

comprobar("un bono de linea sube la fuerza", $conLinea["total"] > $sinNada["total"] ? "sube" : "igual", "sube");
comprobar("un bono de total sube la fuerza", $conTotal["total"] > $sinNada["total"] ? "sube" : "igual", "sube");
comprobar("un malus la baja",                $conMalus["total"] < $sinNada["total"] ? "baja" : "igual", "baja");

/* El suelo existe para que la simulacion no divida por cero: reparte ratios de
   ataque contra muro, y una linea a cero la rompe. */
$aplastado = Tcg::fuerzaParaSimulacion(Tcg::calcularTotalFinal($bruta, $cero, -100), -100);
comprobar("ni con -100% baja de 1 por linea",
    min($aplastado["POR"], $aplastado["DF"], $aplastado["MC"], $aplastado["DC"]) >= 1 ? "si" : "no", "si");

echo "\nTrampas del rival de cadena y dificultades de la cadena\n";

/* Las dos reglas que un jefe final puede saltarse. Son de equilibrio ENTRE
   PERSONAS —tope de bonus por linea y malus por mazo caro sin coherencia— y a
   un rival automatico solo le impedian ser un jefe final. */
$onceCaro = $p->query("
    SELECT c.id_cromo, c.posicion, c.id_rareza, c.id_afinidad, c.id_equipo,
           c.ataque, c.defensa, c.tecnica, af.nombre AS afinidad
    FROM cromos c INNER JOIN afinidad af ON af.id = c.id_afinidad
    WHERE c.posicion IN ('POR','DF','MC','DC') AND c.id_rareza >= 4
      AND c.ataque + c.defensa + c.tecnica > 0
    LIMIT 11
")->fetchAll(PDO::FETCH_ASSOC);

if (count($onceCaro) < 11) {
    echo "  AVISO tcg_prueba no tiene once cartas de rareza alta: no se prueban las trampas.\n";
} else {
    $normal   = $db->calcularCompos($onceCaro);
    $sinMalus = $db->calcularCompos($onceCaro, ["sin_malus" => true]);

    comprobar("un once caro paga malus de coherencia",
        $normal["malus"] > 0 ? "si" : "no", "si");
    comprobar("y con sin_malus no paga nada",
        (float) $sinMalus["malus"], 0.0);

    /* El jugador NUNCA lleva trampas: llamar sin el segundo argumento tiene
       que dar exactamente lo de siempre. Si esto se rompe, la exencion se
       habria colado al lado humano. */
    comprobar("sin pedirlas, las reglas siguen igual",
        $db->calcularCompos($onceCaro)["malus"], $normal["malus"]);
}

/* Apagar una dificultad en TODA la cadena. */
$idCad = (int) $p->query("
    SELECT n.id_cadena FROM cadena_nodos n WHERE n.tipo = 'partido'
    GROUP BY n.id_cadena ORDER BY COUNT(*) DESC LIMIT 1
")->fetchColumn();

if (!$idCad) {
    echo "  AVISO no hay cadenas con nodos de partido: no se prueba.\n";
} else {
    $nNodos = (int) $p->query(
        "SELECT COUNT(*) FROM cadena_nodos WHERE id_cadena = $idCad AND tipo = 'partido'"
    )->fetchColumn();

    $db->activarDificultadCadena($idCad, "extremo", false);
    comprobar("apagar Extremo lo apaga en toda la cadena",
        $db->dificultadesCadena($idCad)["extremo"]["activos"], 0);

    /* Y no arrastra a las demas: apagar una no puede llevarse por delante el
       trabajo de afinar otra. */
    comprobar("y no toca las otras dificultades",
        $db->dificultadesCadena($idCad)["facil"]["activos"], $nNodos);

    $db->activarDificultadCadena($idCad, "extremo", true);
    comprobar("y se vuelve a encender entera",
        $db->dificultadesCadena($idCad)["extremo"]["activos"], $nNodos);
}

echo "\nEditor de cadenas: posicion libre, casilla de salida y requisitos\n";

/* --- posicion en pixeles (044) --- */
$p->exec("DELETE FROM cadenas WHERE nombre = '__prueba_044__'");
$p->exec("INSERT INTO cadenas (nombre, descripcion, anfitrion, orden, activa)
          VALUES ('__prueba_044__', '', '', 99, 1)");
$cadPrueba = (int) $p->lastInsertId();

$nA = $db->crearNodo($cadPrueba, "partido", "A", 137, 83, 0);
$fila = $p->query("SELECT pos_x, pos_y FROM cadena_nodos WHERE id_nodo = $nA")->fetch(PDO::FETCH_ASSOC);
comprobar("un nodo se guarda en pixeles exactos", $fila["pos_x"] . "," . $fila["pos_y"], "137,83");

$db->moverNodo($nA, 421, 17);
$fila = $p->query("SELECT pos_x, pos_y FROM cadena_nodos WHERE id_nodo = $nA")->fetch(PDO::FETCH_ASSOC);
comprobar("y se mueve a cualquier pixel", $fila["pos_x"] . "," . $fila["pos_y"], "421,17");

/* Las coordenadas negativas o disparatadas se acotan: un nodo fuera del lienzo
   no se puede volver a coger. */
$db->moverNodo($nA, -500, 99999);
$fila = $p->query("SELECT pos_x, pos_y FROM cadena_nodos WHERE id_nodo = $nA")->fetch(PDO::FETCH_ASSOC);
comprobar("y no se puede sacar del lienzo", $fila["pos_x"] . "," . $fila["pos_y"], "0,9000");
$db->moverNodo($nA, 100, 100);

/* --- la casilla de salida dirige el rumbo --- */
$nB = $db->crearNodo($cadPrueba, "partido", "B", 300, 100, 0);
$nSuelto = $db->crearNodo($cadPrueba, "partido", "SUELTO", 100, 300, 0);
$db->crearArista($nA, $nB);

$abiertos = function () use ($db, $cadPrueba) {
    $m = $db->mapaCadena($cadPrueba, 1);
    return count(array_filter($m["nodos"], fn($n) => $n["disponible"]));
};

// Sin salida: A y SUELTO abiertos por no tener aristas de entrada.
comprobar("sin casilla de salida, todo lo suelto esta abierto", $abiertos(), 2);

$nIni = $db->crearNodo($cadPrueba, "inicio", "SALIDA", 20, 20, 0);
comprobar("con la salida puesta, solo ella esta abierta", $abiertos(), 1);

comprobar("solo se admite una casilla de salida por cadena",
    $db->cadenaTieneInicio($cadPrueba) ? "si" : "no", "si");

$db->crearArista($nIni, $nA);
comprobar("y conectada abre lo que cuelga de ella", $abiertos(), 2);

$m = $db->mapaCadena($cadPrueba, 1);
comprobar("la salida cuenta como superada",
    !empty($m["nodos"][$nIni]["superado"]) ? "si" : "no", "si");
comprobar("y el nodo SUELTO deja de estar abierto",
    empty($m["nodos"][$nSuelto]["disponible"]) ? "cerrado" : "abierto", "cerrado");

/* --- los seis tipos de requisito --- */
comprobar("hay seis tipos de requisito", count(Tcg::REQUISITOS_CADENA), 6);

$idCromoReq = (int) $p->query("SELECT id_cromo FROM cromos LIMIT 1")->fetchColumn();
$creados = 0;
foreach ([["nivel_album", 90, null], ["monedas", 999999, null],
          ["duelos", 500, null], ["rareza", 6, 50], ["cromo", $idCromoReq, null]] as $r) {
    if ($db->crearRequisito($cadPrueba, $r[0], $r[1], $r[2])) { $creados++; }
}
comprobar("los cinco requisitos nuevos se crean", $creados, 5);
comprobar("y un tipo inventado se rechaza",
    $db->crearRequisito($cadPrueba, "chorizo", 1) ? "creado" : "rechazado", "rechazado");

/* Cada uno tiene que decir QUE falta, no solo que falta algo: una cadena que
   dice "no cumples los requisitos" deja a la gente adivinando cual. */
$faltan = $db->requisitosPendientes($cadPrueba, 1);
comprobar("y cada uno explica cuanto le falta",
    count(array_filter($faltan, fn($t) => strpos($t, "(") !== false)) >= 3 ? "si" : "no", "si");

/* --- el resumen del botin --- */
$db->crearLoot($nA, "monedas", null, 200, 50.0, null);
$db->crearLoot($nA, "monedas", null, 100, 100.0, "S");
$resumen = $db->resumenLootNodo($nA);

// En B solo entra el premio sin rango: 50% x 200 = 100 de media.
comprobar("las monedas esperadas en B salen bien", $resumen["B"]["monedas_media"], 100);
// En S entran los dos: 100 + 100% x 100 = 200.
comprobar("y en S entran tambien los de rango alto", $resumen["S"]["monedas_media"], 200);

$p->exec("DELETE FROM cadenas WHERE id_cadena = $cadPrueba");
comprobar("limpieza", $p->query("SELECT COUNT(*) FROM cadena_nodos WHERE id_cadena = $cadPrueba")->fetchColumn(), "0");

echo "\nCartas exclusivas de cadena\n";

$antes = $db->contarCromosTotales();
$muestra = $p->query("SELECT id_expansion, id_equipo, id_afinidad FROM cromos LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$muestra) { exit("tcg_prueba no tiene cromos.\n"); }

$idNuevo = (int) $db->crearCromoSoloCadena(
    "__prueba_exclusiva__", "DC", "",
    (int) $muestra["id_expansion"], (int) $muestra["id_equipo"], 3, (int) $muestra["id_afinidad"],
    50, 40, 60
);

comprobar("se marca como solo_cadena",    $db->obtenerCromoAdmin($idNuevo)["solo_cadena"], "1");
comprobar("NO cuenta para el album",      $db->contarCromosTotales(), $antes);

$p->prepare("DELETE FROM cromos WHERE id_cromo = ?")->execute([$idNuevo]);
comprobar("limpieza",                     $db->obtenerCromoAdmin($idNuevo) === null ? "borrado" : "sigue", "borrado");

echo "\n" . ($fallos === 0 ? "TODO EN VERDE" : "$fallos FALLOS") . "\n";
exit($fallos === 0 ? 0 : 1);
