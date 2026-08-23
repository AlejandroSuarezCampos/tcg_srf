<?php
/**
 * NODOS DE BLOQUEO (`045`): el STOP del mapa de cadena.
 * Sobre la copia DESECHABLE tcg_prueba (§8). Nunca toca la base real.
 *
 * Lo que se comprueba aquí es lo que NO se ve leyendo el código:
 *
 * 1. Que un bloqueo sin requisitos deje pasar. Es el caso que decide si añadir
 *    el nodo puede romper una cadena ya publicada: si por descuido bloqueara,
 *    cualquier STOP recién puesto dejaría la cadena cortada hasta configurarlo.
 *
 * 2. Que `rango_previos` sea "EXISTE UN CAMINO" y no "todos los ancestros".
 *    Con una bifurcación, exigir todos obligaría a jugarse las dos ramas, que
 *    no es como se recorre una cadena. Es el mismo criterio que ya tomó
 *    `caminoPerfectoHastaCofre()` (§15.12), y la única forma de distinguirlos
 *    es un mapa que se bifurque — con un camino recto las dos lecturas dan lo
 *    mismo y el error pasaría inadvertido.
 *
 * 3. Que fijar la dificultad se respete. Las cinco están siempre abiertas y
 *    `mejor_rango` se guarda por dificultad, así que sin este filtro se puede
 *    granjear la S entera en Fácil y abrir un STOP pensado para Extremo. Es un
 *    agujero silencioso: el requisito parecería funcionar.
 *
 * 4. Que `mas_goles` sea un RÉCORD y no el último marcador, y que una portería
 *    a cero solo cuente si además se ganó.
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
    printf("  %-5s %-52s %s%s\n", $bien ? "OK" : "FALLO", $etiqueta,
        var_export($obtenido, true),
        $bien ? "" : "  (esperado " . var_export($esperado, true) . ")");
}

// Si falta la migración se dice cuál, en vez de reventar con un error de SQL
// que no explica nada. Mismo criterio que probar_dificultad_nodo.php.
if (!$p->query("SHOW TABLES LIKE 'cadena_nodo_requisitos'")->fetch()) {
    exit("Falta la migración 045 en tcg_prueba.\n");
}

$usuario = (int) $p->query(
    "SELECT id_usuario FROM usuarios WHERE nombre <> 'CPU' ORDER BY id_usuario LIMIT 1"
)->fetchColumn();
if (!$usuario) { exit("tcg_prueba no tiene ningún usuario que no sea el bot.\n"); }

// ---------------------------------------------------------------------------
// CADENA DE LABORATORIO, con bifurcación a propósito (ver punto 2 de arriba):
//
//        ┌─ A ─┐
//   inicio     ├─ STOP ─ B
//        └─ C ─┘
//
// Se monta a mano y no se reutiliza una de la copia porque la prueba tiene que
// partir del estado que dice probar, no del que se encuentre.
// ---------------------------------------------------------------------------
$p->prepare("INSERT INTO cadenas (nombre, activa, visibilidad) VALUES ('Prueba bloqueo', 1, 'todos')")
  ->execute();
$cadena = (int) $p->lastInsertId();

$crearNodo = function ($tipo, $nombre = null) use ($p, $cadena) {
    $p->prepare("INSERT INTO cadena_nodos (id_cadena, tipo, nombre, columna, fila, es_final)
                 VALUES (?, ?, ?, 0, 0, 0)")->execute([$cadena, $tipo, $nombre]);
    return (int) $p->lastInsertId();
};
$ini  = $crearNodo("inicio");
$a    = $crearNodo("partido", "A");
$c    = $crearNodo("partido", "C");
$stop = $crearNodo("bloqueo", "STOP");
$b    = $crearNodo("partido", "B");

$arista = $p->prepare("INSERT INTO cadena_aristas (id_origen, id_destino) VALUES (?, ?)");
foreach ([[$ini, $a], [$ini, $c], [$a, $stop], [$c, $stop], [$stop, $b]] as [$o, $d]) {
    $arista->execute([$o, $d]);
}

$mapa = function () use ($db, $cadena, $usuario) { return $db->mapaCadena($cadena, $usuario)["nodos"]; };
$limpiarRequisitos = function () use ($p, $stop) {
    $p->prepare("DELETE FROM cadena_nodo_requisitos WHERE id_nodo = ?")->execute([$stop]);
};
$progresoDe = function ($nodo, $dif, $campo) use ($p, $usuario) {
    $q = $p->prepare("SELECT $campo FROM cadena_progreso
                      WHERE id_usuario = ? AND id_nodo = ? AND dificultad = ?");
    $q->execute([$usuario, $nodo, $dif]);
    return (int) $q->fetchColumn();
};

echo "Nodo de bloqueo (cadena $cadena de tcg_prueba, usuario $usuario)\n\n";

// --- 1. sin requisitos, no bloquea ------------------------------------------
$n = $mapa();
comprobar("un bloqueo SIN requisitos esta superado", $n[$stop]["superado"] ? 1 : 0, 1);
comprobar("y deja pasar al nodo siguiente", $n[$b]["disponible"] ? 1 : 0, 1);

// --- 2. un requisito sin cumplir corta el paso -------------------------------
$db->crearRequisitoNodo($stop, "nodos_cadena", 1);
$n = $mapa();
comprobar("con un requisito sin cumplir, no esta superado", $n[$stop]["superado"] ? 1 : 0, 0);
comprobar("y el nodo siguiente queda cerrado", $n[$b]["disponible"] ? 1 : 0, 0);
comprobar("y el mapa dice que falta 1 cosa", count($n[$stop]["requisitos"]), 1);
comprobar("con su barra a 0 de 1", $n[$stop]["requisitos"][0]["lleva"] . "/" . $n[$stop]["requisitos"][0]["pide"], "0/1");

// --- 3. al cumplirlo, se abre -----------------------------------------------
$db->registrarProgresoNodo($usuario, $a, "medio", true, "B", 2, 1);
$n = $mapa();
comprobar("cumplido, el bloqueo se abre solo", $n[$stop]["superado"] ? 1 : 0, 1);
comprobar("y arrastra al siguiente", $n[$b]["disponible"] ? 1 : 0, 1);

// --- 4. rango_previos: EXISTE UN CAMINO, no todos los ancestros --------------
$limpiarRequisitos();
$db->crearRequisitoNodo($stop, "rango_previos", 1);        // 1 = S
$n = $mapa();
comprobar("se exige rango S y solo hay B: cerrado", $n[$stop]["superado"] ? 1 : 0, 0);

$db->registrarProgresoNodo($usuario, $a, "medio", true, "S", 3, 0);
$n = $mapa();
// C no se ha jugado NUNCA. Si esto diera 0, se estarian exigiendo los dos
// caminos de la bifurcacion en vez de uno.
comprobar("con la S en UN camino abre, aunque el otro este sin jugar", $n[$stop]["superado"] ? 1 : 0, 1);

// --- 5. la dificultad se respeta --------------------------------------------
$limpiarRequisitos();
$db->crearRequisitoNodo($stop, "rango_previos", 1, null, "extremo");
$n = $mapa();
comprobar("la S en Medio no vale si el requisito pide Extremo", $n[$stop]["superado"] ? 1 : 0, 0);

$db->registrarProgresoNodo($usuario, $a, "extremo", true, "S", 1, 0);
$n = $mapa();
comprobar("y con la S en Extremo si abre", $n[$stop]["superado"] ? 1 : 0, 1);

// --- 6. mas_goles es un RECORD, no el ultimo marcador ------------------------
$db->registrarProgresoNodo($usuario, $a, "medio", true, "B", 5, 2);
comprobar("el record de goles sube a 5", $progresoDe($a, "medio", "mas_goles"), 5);
$db->registrarProgresoNodo($usuario, $a, "medio", true, "B", 1, 0);
comprobar("y un partido flojo despues NO lo baja", $progresoDe($a, "medio", "mas_goles"), 5);

// --- 7. portería a cero solo si se gana --------------------------------------
// En (A, medio) van: 2-1, 3-0 ganado, 5-2, 1-0 ganado  ->  dos porterias a cero
comprobar("dos victorias sin encajar suman 2", $progresoDe($a, "medio", "porterias_cero"), 2);
$db->registrarProgresoNodo($usuario, $a, "medio", false, null, 0, 0);
comprobar("perder sin encajar NO cuenta como porteria a cero", $progresoDe($a, "medio", "porterias_cero"), 2);

// --- 8. el requisito imposible se rechaza ------------------------------------
comprobar("un bloqueo no puede exigir su PROPIA cadena",
    $db->crearRequisitoNodo($stop, "cadena", $cadena) ? 1 : 0, 0);
comprobar("ni un tipo de requisito inventado",
    $db->crearRequisitoNodo($stop, "lo_que_sea", 1) ? 1 : 0, 0);

// --- 9. goles_partido lee el record -----------------------------------------
$limpiarRequisitos();
$db->crearRequisitoNodo($stop, "goles_partido", 5);
$n = $mapa();
comprobar("pedir 5 goles se cumple con el record de 5", $n[$stop]["superado"] ? 1 : 0, 1);
$limpiarRequisitos();
$db->crearRequisitoNodo($stop, "goles_partido", 6);
$n = $mapa();
comprobar("pedir 6 no", $n[$stop]["superado"] ? 1 : 0, 0);

// --- 10. rango_previos SIN NINGÚN PARTIDO DELANTE: pasa por vacuidad ---------
/* Sale al probar en el navegador: un STOP colgado directamente de la casilla de
   salida, con "todo en rango S", se abre solo. Es correcto y es DELIBERADO —no
   hay ningún partido en el camino que pueda incumplirlo—, y sobre todo es el
   lado seguro por el que fallar: si en vez de abrirse se cerrase, un STOP mal
   cableado dejaría la cadena muerta para siempre y sin forma de saber por qué.
   Mismo criterio que el "un tipo que no conocemos no bloquea a nadie".

   ⚠️ La consecuencia para quien monta cadenas es que `rango_previos` no avisa
   de nada si lo pones donde no hay partidos detrás: parece configurado y no
   exige nada. */
$stop2 = $crearNodo("bloqueo", "STOP suelto");
$arista->execute([$ini, $stop2]);
$db->crearRequisitoNodo($stop2, "rango_previos", 1);
$n = $mapa();
comprobar("un STOP sin partidos delante se abre (vacuidad)", $n[$stop2]["superado"] ? 1 : 0, 1);

// --- limpieza ----------------------------------------------------------------
// La copia se borra entera al terminar la tanda, pero una suite que deja basura
// no debe ensuciar a la siguiente si algún día se corren sobre la misma copia.
$p->prepare("DELETE FROM cadenas WHERE id_cadena = ?")->execute([$cadena]);

echo "\n" . ($fallos ? "$fallos FALLO(S).\n" : "Todo en verde.\n");
exit($fallos ? 1 : 0);
