<?php
/**
 * Prueba la apuesta de VARIAS cartas (migración 031) sobre la copia
 * DESECHABLE tcg_prueba (§8). Nunca toca la base real.
 *
 * Lo que se comprueba es exactamente donde puede perderse una carta:
 *   1. Se pueden apostar varias de golpe, y quedan todas registradas.
 *   2. Una carta apostada deja de estar disponible para apostar OTRA VEZ.
 *      Sin esto, dos salas seguidas comprometerían la misma copia.
 *   3. Una carta apostada tampoco se puede poner en venta.
 *   4. Mezclar rarezas se rechaza: la sala pacta una.
 *   5. Pasarse del techo (`duelo_cartas_max`) se rechaza.
 *   6. Entrar en una sala poniendo MENOS cartas de las pactadas se rechaza.
 *      Es el fallo que convertiría un duelo a 3 cartas en un robo.
 *   7. Al liquidar, el ganador se lleva TODAS las cartas del perdedor —no la
 *      primera— y el perdedor no se queda ninguna.
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

$dsn = "mysql:host=127.0.0.1;dbname=tcg_prueba;charset=utf8mb4";
$p = new PDO($dsn, "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

require __DIR__ . "/../consultas.php";
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");

$fallos = 0;
$ok = function ($m) { echo "  OK    $m\n"; };
$ko = function ($m) use (&$fallos) { echo "  FALLO $m\n"; $fallos++; };

/* ---------------------------------------------------------------------------
   Montaje. Los dos duelistas necesitan mazo titular completo, así que se toma
   el que ya exista en la copia y se clona para el segundo jugador: montar dos
   alineaciones de 11 a mano aquí sería reimplementar mazos.php.
   --------------------------------------------------------------------------- */
$titular = $p->query("
    SELECT m.id_mazo, m.id_usuario FROM mazos m
    WHERE m.titular = 1 AND (SELECT COUNT(*) FROM mazo_cartas mc WHERE mc.id_mazo = m.id_mazo) = 11
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$titular) {
    echo "  AVISO no hay ningún mazo titular completo en la copia: no se puede probar.\n";
    exit(0);
}
$jugadorA = (int) $titular["id_usuario"];

/* El rival necesita SU propio mazo completo. Se le copian las mismas cartas
   como copias nuevas en su colección para no robarle ninguna a A —si las
   compartieran, la mitad de las comprobaciones de propiedad medirían otra
   cosa—. */
$jugadorB = (int) $p->query("SELECT id_usuario FROM usuarios WHERE id_usuario <> $jugadorA ORDER BY id_usuario LIMIT 1")->fetchColumn();

$p->exec("DELETE FROM mazos WHERE id_usuario = $jugadorB");
$p->exec("INSERT INTO mazos (id_usuario, nombre, formacion, titular) VALUES ($jugadorB, 'Prueba B', '442', 1)");
$mazoB = (int) $p->lastInsertId();

$cromosTitular = $p->query("
    SELECT col.id_cromo, mc.hueco FROM mazo_cartas mc
    INNER JOIN coleccion col ON col.id_coleccion = mc.id_coleccion
    WHERE mc.id_mazo = {$titular['id_mazo']}
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($cromosTitular as $c) {
    $p->prepare("INSERT INTO coleccion (id_usuario, id_cromo, obtenida, bloqueada) VALUES (?, ?, NOW(), 0)")
      ->execute([$jugadorB, $c["id_cromo"]]);
    $idCol = (int) $p->lastInsertId();
    $p->prepare("INSERT INTO mazo_cartas (id_mazo, id_coleccion, hueco) VALUES (?, ?, ?)")
      ->execute([$mazoB, $idCol, (int) $c["hueco"]]);
}

/** Da a un jugador $n copias nuevas y libres de cromos de la rareza pedida. */
function repartir(PDO $p, $idUsuario, $idRareza, $n) {
    $cromos = $p->query("SELECT id_cromo FROM cromos WHERE id_rareza = $idRareza LIMIT $n")
                ->fetchAll(PDO::FETCH_COLUMN);
    $ids = [];
    foreach ($cromos as $idCromo) {
        $p->prepare("INSERT INTO coleccion (id_usuario, id_cromo, obtenida, bloqueada) VALUES (?, ?, NOW(), 0)")
          ->execute([$idUsuario, $idCromo]);
        $ids[] = (int) $p->lastInsertId();
    }
    return $ids;
}

$RAREZA = 1;   // común: siempre hay suficientes en el catálogo
$OTRA   = 2;

$p->exec("UPDATE configuracion SET valor = '5' WHERE clave = 'duelo_cartas_max'");
$p->exec("DELETE FROM duelos WHERE id_creador IN ($jugadorA, $jugadorB) AND estado = 'creado'");

$cartasA = repartir($p, $jugadorA, $RAREZA, 8);
$cartasB = repartir($p, $jugadorB, $RAREZA, 8);
$otraA   = repartir($p, $jugadorA, $OTRA, 1);

if (count($cartasA) < 6 || count($cartasB) < 4) {
    echo "  AVISO el catálogo no tiene cromos comunes suficientes para la prueba.\n";
    exit(0);
}

echo "PRUEBA: apuesta de varias cartas (jugadores $jugadorA vs $jugadorB)\n\n";

/* --- 1. Apostar tres cartas de golpe -------------------------------------- */
$lote = array_slice($cartasA, 0, 3);
$r = $db->crearDuelo($jugadorA, "carta", 0, $RAREZA, $lote);
if (!$r["ok"]) {
    $ko("apostar tres cartas debería funcionar (dijo: {$r['error']})");
} else {
    $idDuelo = (int) $r["id_duelo"];
    $guardadas = (int) $p->query("
        SELECT COUNT(*) FROM duelo_apuesta_cartas dac
        INNER JOIN duelo_apuestas da ON da.id_apuesta = dac.id_apuesta
        WHERE da.id_duelo = $idDuelo
    ")->fetchColumn();
    $guardadas === 3
        ? $ok("las tres cartas quedan registradas en la apuesta")
        : $ko("se apostaron 3 cartas y quedaron guardadas $guardadas");

    $declaradas = (int) $p->query("SELECT cartas_apuesta FROM duelos WHERE id_duelo = $idDuelo")->fetchColumn();
    $declaradas === 3
        ? $ok("la sala declara que se juega a 3 cartas por lado")
        : $ko("cartas_apuesta debería ser 3 y es $declaradas");
}

/* --- 2. Una carta ya apostada no se puede volver a apostar ---------------- */
$libres = array_map("intval", array_column($db->listarCopiasApostables($jugadorA, $RAREZA), "id_coleccion"));
$solapan = array_intersect($lote, $libres);
empty($solapan)
    ? $ok("las cartas comprometidas desaparecen del selector de apuesta")
    : $ko("siguen apostables cartas ya comprometidas: " . implode(",", $solapan));

$r2 = $db->crearDuelo($jugadorA, "carta", 0, $RAREZA, [$lote[0]]);
!$r2["ok"]
    ? $ok("apostar por segunda vez la misma carta se rechaza")
    : $ko("se ha podido apostar dos veces la misma carta (duelo {$r2['id_duelo']})");

/* --- 3. Ni poner en venta ------------------------------------------------- */
$db->publicarAnuncio($lote[0], $jugadorA, 100) === false
    ? $ok("una carta apostada no se puede poner en venta")
    : $ko("se ha podido vender una carta que está en juego");

/* --- 4. No se pueden mezclar rarezas -------------------------------------- */
$r3 = $db->crearDuelo($jugadorA, "carta", 0, $RAREZA, [$cartasA[4], $otraA[0]]);
!$r3["ok"]
    ? $ok("mezclar rarezas en la misma apuesta se rechaza")
    : $ko("se ha aceptado una apuesta con dos rarezas distintas");

/* --- 5. Techo de cartas --------------------------------------------------- */
$p->exec("UPDATE configuracion SET valor = '2' WHERE clave = 'duelo_cartas_max'");
$dbTecho = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");
$r4 = $dbTecho->crearDuelo($jugadorA, "carta", 0, $RAREZA, array_slice($cartasA, 4, 3));
!$r4["ok"]
    ? $ok("pasarse de duelo_cartas_max se rechaza")
    : $ko("se han apostado 3 cartas con el techo en 2");
$p->exec("UPDATE configuracion SET valor = '5' WHERE clave = 'duelo_cartas_max'");
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");

/* --- 6. Entrar poniendo menos de las pactadas ----------------------------- */
$r5 = $db->aceptarDuelo($idDuelo, $jugadorB, array_slice($cartasB, 0, 1));
!$r5["ok"]
    ? $ok("entrar con 1 carta en una sala de 3 se rechaza")
    : $ko("se ha entrado en una sala de 3 cartas poniendo solo 1");

/* --- 7. El ganador se lleva TODAS ----------------------------------------- */
$loteB = array_slice($cartasB, 0, 3);
$r6 = $db->aceptarDuelo($idDuelo, $jugadorB, $loteB);
if (!$r6["ok"]) {
    $ko("entrar con las 3 cartas pactadas debería funcionar (dijo: {$r6['error']})");
} else {
    $ok("entrar con las 3 cartas pactadas funciona");

    // Se fuerza el duelo a en_juego con A ganando, y se liquida.
    $p->exec("UPDATE duelos SET estado = 'en_juego', goles_creador = 3, goles_rival = 0,
              valor_sorteo = 0.42, resuelto = NOW() WHERE id_duelo = $idDuelo");
    $db->liquidarPartido($idDuelo);

    $marcador = "'" . implode("','", $loteB) . "'";
    $deA = (int) $p->query("SELECT COUNT(*) FROM coleccion WHERE id_coleccion IN ($marcador) AND id_usuario = $jugadorA")->fetchColumn();
    $deB = (int) $p->query("SELECT COUNT(*) FROM coleccion WHERE id_coleccion IN ($marcador) AND id_usuario = $jugadorB")->fetchColumn();

    $deA === 3
        ? $ok("el ganador se lleva las TRES cartas del perdedor")
        : $ko("el ganador debería tener 3 cartas del perdedor y tiene $deA");
    $deB === 0
        ? $ok("al perdedor no le queda ninguna del lote")
        : $ko("al perdedor le quedan $deB cartas que ya había perdido");
}

echo "\n";
if ($fallos) { echo "FALLOS: $fallos\n"; exit(1); }
echo "Todo en verde.\n";
