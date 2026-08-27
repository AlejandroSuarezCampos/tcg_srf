<?php
/**
 * Equipos (`035`), universo y numeración de las cartas (`037`, `038`) y el
 * tutorial de bienvenida con su sobre y su amistoso (`036`, `039`).
 * Sobre la copia DESECHABLE tcg_prueba (§8). Nunca toca la base real.
 *
 * Lo que se comprueba es lo que puede romper datos o dejar a alguien
 * atrapado:
 *   1. Un equipo con cromos NO se puede borrar. `cromos.id_equipo` no admite
 *      NULL, así que borrarlo o falla por clave ajena o —si alguien pone
 *      CASCADE— se lleva las cartas y el progreso de todo el mundo dentro.
 *   2. Los nombres de equipo no se repiten.
 *   3. El universo y la tirada numerada se guardan EN LA CARTA; un universo
 *      inventado cae al propio, y un cupo 0 se guarda como NULL —que es lo
 *      que significa "sin tirada limitada"— y no como un cero.
 *   7. El sobre de bienvenida entrega un once con las posiciones que pide la
 *      formación, y no se puede abrir dos veces.
 *   8. El amistoso no es de ninguna cadena, no apuesta nada y no sale en el
 *      historial.
 *   4. El universo VIAJA con la carta: si no llega a `render_carta()`, la
 *      insignia no se pinta y la distinción no existe.
 *   5. El tutorial solo guarda pasos del guion. Un valor inventado dejaría al
 *      usuario en un paso que el motor no encuentra: tutorial colgado y sin
 *      forma de cerrarlo.
 *   6. Los dos requisitos (mazo titular, partido jugado) los calcula el
 *      SERVIDOR. Son las puertas del tutorial y no las puede abrir el cliente.
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

$p = new PDO("mysql:host=127.0.0.1;dbname=tcg_prueba;charset=utf8mb4", "root", "",
	[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

require __DIR__ . "/../consultas.php";
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");

$fallos = 0;
$ok = function ($m) { echo "  OK    $m\n"; };
$ko = function ($m) use (&$fallos) { echo "  FALLO $m\n"; $fallos++; };

echo "Equipos y universo\n";

/* --- 1 y 2: alta, duplicados y borrado ---------------------------------- */
$nombre = "__prueba_equipo__";
$p->prepare("DELETE FROM equipos WHERE nombre LIKE '__prueba_equipo%'")->execute();

$r = $db->crearEquipoAdmin($nombre, null, "Equipo de prueba");
$r["ok"] ? $ok("se crea un equipo") : $ko("no se pudo crear: " . $r["error"]);
$idNuevo = (int) $r["id_equipo"];

$db->crearEquipoAdmin($nombre)["ok"] === false
	? $ok("un nombre repetido se rechaza")
	: $ko("se han creado dos equipos con el mismo nombre");

$db->crearEquipoAdmin("")["ok"] === false
	? $ok("un equipo sin nombre se rechaza")
	: $ko("se ha creado un equipo sin nombre");

/* --- 3: el universo es de la CARTA, no del equipo (migración `037`) ------- */
$muestra = $p->query("SELECT id_expansion, id_equipo, id_afinidad FROM cromos LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$p->prepare("DELETE FROM cromos WHERE nombre LIKE '__prueba_carta%'")->execute();

$idCarta = (int) $db->crearCromo("__prueba_carta__", "DC", "", "",
	$muestra["id_expansion"], $muestra["id_equipo"], 7, $muestra["id_afinidad"],
	80, 50, 70, 0, "ie", 50);

$carta = $p->query("SELECT universo, cupo_numerado, id_rareza FROM cromos WHERE id_cromo = $idCarta")->fetch(PDO::FETCH_ASSOC);
$carta["universo"] === "ie"
	? $ok("el universo se guarda EN LA CARTA")
	: $ko("la carta no guardó su universo: " . var_export($carta["universo"], true));

(int) $carta["cupo_numerado"] === 50
	? $ok("la tirada numerada se guarda al crear la carta")
	: $ko("no se guardó el cupo: " . var_export($carta["cupo_numerado"], true));

// Un universo inventado tiene que caer al propio, no guardarse tal cual.
$idCarta2 = (int) $db->crearCromo("__prueba_carta_2__", "DC", "", "",
	$muestra["id_expansion"], $muestra["id_equipo"], 1, $muestra["id_afinidad"],
	50, 50, 50, 0, "universo_paralelo", 0);
$p->query("SELECT universo FROM cromos WHERE id_cromo = $idCarta2")->fetchColumn() === "srf"
	? $ok("un universo inventado cae al del propio juego")
	: $ko("se guardó un universo inventado");

// Cupo 0 significa "sin tirada limitada" y se guarda como NULL, no como 0.
$p->query("SELECT cupo_numerado FROM cromos WHERE id_cromo = $idCarta2")->fetchColumn() === null
	? $ok("cupo 0 se guarda como NULL, no como cero")
	: $ko("un cupo 0 se guardó como valor");

$p->prepare("DELETE FROM cromos WHERE nombre LIKE '__prueba_carta%'")->execute();

/* --- 1: un equipo CON cromos no se borra --------------------------------- */
$conCromos = (int) $p->query("SELECT id_equipo FROM cromos LIMIT 1")->fetchColumn();
$borrado = $db->eliminarEquipoAdmin($conCromos);
$borrado["ok"] === false
	? $ok("un equipo con cromos no se puede borrar")
	: $ko("SE HA BORRADO un equipo que tenía cromos");

$sigue = (int) $p->query("SELECT COUNT(*) FROM equipos WHERE id_equipo = $conCromos")->fetchColumn();
$sigue === 1 ? $ok("y sigue estando ahí") : $ko("el equipo con cromos ha desaparecido");

$db->eliminarEquipoAdmin($idNuevo)["ok"]
	? $ok("un equipo sin cromos sí se borra")
	: $ko("no se pudo borrar un equipo vacío");
$p->prepare("DELETE FROM equipos WHERE nombre LIKE '__prueba_equipo%'")->execute();

/* --- 4: el universo llega hasta la carta --------------------------------- */
$cromos = $db->listarCromosAdmin();
!empty($cromos) && array_key_exists("universo", $cromos[0])
	? $ok("el universo viaja con el cromo al panel")
	: $ko("listarCromosAdmin no trae el universo");

$colec = $db->listarCopiasApostables(
	(int) $p->query("SELECT id_usuario FROM coleccion LIMIT 1")->fetchColumn());
empty($colec) || array_key_exists("universo", $colec[0])
	? $ok("el universo viaja con la copia del jugador")
	: $ko("las copias del jugador no traen el universo");

echo "\nTutorial de bienvenida\n";

$idUsuario = (int) $p->query("SELECT id_usuario FROM usuarios LIMIT 1")->fetchColumn();
$original  = $db->tutorialPaso($idUsuario);

/* --- 5: solo pasos del guion --------------------------------------------- */
$primero = Tcg::TUTORIAL_PASOS[0]["clave"];
$db->guardarTutorialPaso($idUsuario, $primero)["ok"]
	? $ok("se guarda un paso del guion")
	: $ko("no se pudo guardar un paso válido");
$db->tutorialPaso($idUsuario) === $primero
	? $ok("y se relee igual")
	: $ko("el paso guardado no se relee");

$db->guardarTutorialPaso($idUsuario, "paso_que_no_existe")["ok"] === false
	? $ok("un paso inventado se rechaza")
	: $ko("se ha guardado un paso que no existe");
$db->tutorialPaso($idUsuario) === $primero
	? $ok("y no ha pisado el que había")
	: $ko("el paso inventado pisó al bueno");

foreach (Tcg::TUTORIAL_TERMINADO as $terminal) {
	if (!$db->guardarTutorialPaso($idUsuario, $terminal)["ok"]) {
		$ko("no se pudo cerrar el tutorial con '$terminal'");
	}
}
$ok("el tutorial se puede cerrar como hecho y como saltado");

/* Cada paso tiene que apuntar a una pantalla y traer su texto: un paso sin
   página dejaría al motor sin saber dónde enseñarlo. */
$malos = [];
foreach (Tcg::TUTORIAL_PASOS as $paso) {
	if (empty($paso["clave"]) || empty($paso["pagina"]) || empty($paso["destino"])
		|| empty($paso["titulo"]) || empty($paso["texto"])) {
		$malos[] = $paso["clave"] ?? "(sin clave)";
	}
}
empty($malos)
	? $ok("los " . count(Tcg::TUTORIAL_PASOS) . " pasos están completos")
	: $ko("pasos incompletos: " . implode(", ", $malos));

$claves = array_column(Tcg::TUTORIAL_PASOS, "clave");
count($claves) === count(array_unique($claves))
	? $ok("ningún paso repite clave")
	: $ko("hay claves de paso repetidas");

/* `pagina` TIENE que ser el nombre del archivo de `destino`.
   El motor compara `pagina` con la pantalla en la que está para saber si hay
   que navegar; si no cuadran, el paso o no lleva a ningún sitio o se da por
   visto desde otra pantalla. Pasó de verdad: el paso de ajustes apuntaba a
   'perfil' porque configuracion.php se marca así en la barra, y desde el
   perfil el tutorial daba el paso por hecho sin llevar nunca a los ajustes. */
$descuadres = [];
foreach (Tcg::TUTORIAL_PASOS as $paso) {
	/* El destino puede llevar `?consulta` o `#ancla` —`plantilla.php?ver=todas`,
	   `perfil.php#panel-ajustes`—, así que se compara solo el fichero. Sin esto
	   la prueba daba en rojo por su propia cuenta: `basename()` no recorta el
	   `.php` cuando detrás hay una cadena de consulta, e `is_file()` buscaba un
	   fichero llamado literalmente `plantilla.php?ver=todas`. */
	$fichero = strtok($paso["destino"], "?#");
	if ($paso["pagina"] !== basename($fichero, ".php")) {
		$descuadres[] = $paso["clave"] . " (" . $paso["pagina"] . " vs " . $paso["destino"] . ")";
	}
	if (!is_file(__DIR__ . "/../../" . $fichero)) {
		$descuadres[] = $paso["clave"] . ": no existe " . $paso["destino"];
	}
}
empty($descuadres)
	? $ok("cada paso apunta a una pantalla que existe")
	: $ko("pasos mal enrutados: " . implode(", ", $descuadres));

/* --- 6: los requisitos los calcula el servidor --------------------------- */
$logros = $db->tutorialLogros($idUsuario);
(isset($logros["mazo"]) && isset($logros["partido"])
	&& is_bool($logros["mazo"]) && is_bool($logros["partido"]))
	? $ok("los dos requisitos se calculan en el servidor")
	: $ko("tutorialLogros no devuelve los dos requisitos");

/* Coherencia: si dice que tiene mazo, tiene que haberlo de verdad. */
$logros["mazo"] === ($db->obtenerMazoTitular($idUsuario) !== null)
	? $ok("el requisito del mazo cuadra con la realidad")
	: $ko("el requisito del mazo miente");

$db->guardarTutorialPaso($idUsuario, $original);

/* ---------------------------------------------------------------------------
   CUÁNDO SE PINTA UNA CARTA CON MARCO

   El marco es una MAQUETA con un hueco cuadrado para un retrato, no un adorno:
   solo vale para las cartas cuyo arte es la foto del jugador —las importadas—.
   El arte propio se dibuja para llenar la carta entera y meterlo en el
   cuadradito sería recortar justo lo que se dibujó.

   Se comprueba porque la regla anterior miraba la RAREZA (1 a 4) y coincidía
   de casualidad: todo lo importado es de rareza baja. En cuanto exista una
   legendaria con foto de jugador, aquella regla fallaba en los dos sentidos.
   --------------------------------------------------------------------------- */
echo "
Cuando una carta lleva marco
";

require_once __DIR__ . "/../../components/carta.php";

$casos = [
    [1, "./assets/img/Cromos/Importados/x/y.webp", true,  "comun importada"],
    [4, "./assets/img/Cromos/Importados/x/y.webp", true,  "epica importada"],
    [5, "./assets/img/Cromos/Importados/x/y.webp", true,  "legendaria importada"],
    [6, "./assets/img/Cromos/Importados/x/y.webp", true,  "SRF importada"],
    [1, "./assets/img/Cromos/ALL STARS/x.webp",    false, "comun con arte propio"],
    [5, "./assets/img/Cromos/Base Set/tom.webp",   false, "legendaria con arte propio"],
    [6, "./assets/img/Cromos/ALL STARS/g.webp",    false, "SRF con arte propio"],
    [1, "",                                        false, "sin arte"],
];
$falla = [];
foreach ($casos as [$rz, $img, $esperado, $etiqueta]) {
    if (carta_usa_marco($rz, $img) !== $esperado) { $falla[] = $etiqueta; }
}
empty($falla)
    ? $ok("el marco depende de la carpeta del arte, no de la rareza")
    : $ko("fallan: " . implode(", ", $falla));

/* ---------------------------------------------------------------------------
   VISIBILIDAD Y REPARTO DE UNA CARTA (`030` + `040`)

   Son DOS ejes independientes y aquí se comprueba que de verdad lo sean:
     · `solo_cadena` — si se ve en el álbum;
     · `en_sobres`   — si entra en el sorteo de los sobres.
   Y que una tirada numerada que salga de un sobre se lleve su número y
   descuente cupo: sin eso existirían más copias de las que dice la tirada.
   --------------------------------------------------------------------------- */
echo "\nVisibilidad y reparto de las cartas\n";

$base = $p->query("SELECT id_expansion, id_equipo, id_afinidad FROM cromos LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$p->prepare("DELETE FROM cromos WHERE nombre LIKE '__vis_%'")->execute();

/* --- una carta SECRETA no cuenta para el álbum ni sale en sobres --------- */
$totalAntes = $db->contarCromosTotales();
$idSecreta = (int) $db->crearCromo("__vis_secreta__", "DC", "", "",
    $base["id_expansion"], $base["id_equipo"], 4, $base["id_afinidad"],
    90, 90, 90, 0, "srf", 0, 1, 0);

$db->contarCromosTotales() === $totalAntes
    ? $ok("una carta secreta no cuenta para el album")
    : $ko("la carta secreta cuenta para el album");

$enAlbumDe = function ($idUsuario, $idCromo) use ($db) {
    foreach ($db->listarColeccionCompleta($idUsuario) as $exp) {
        foreach ($exp["cromos"] as $cr) {
            if ((int) $cr["id_cromo"] === $idCromo) { return true; }
        }
    }
    return false;
};

!$enAlbumDe(0, $idSecreta)
    ? $ok("y no aparece en el catalogo del album")
    : $ko("la carta secreta aparece en el album");

/* --- pero UNA VEZ CONSEGUIDA si aparece ---------------------------------- */
$duenyo = (int) $p->query("SELECT id_usuario FROM usuarios ORDER BY id_usuario LIMIT 1")->fetchColumn();
$p->exec("INSERT INTO coleccion (id_usuario, id_cromo) VALUES ($duenyo, $idSecreta)");

$enAlbumDe($duenyo, $idSecreta)
    ? $ok("pero al conseguirla si sale en SU album")
    : $ko("la secreta conseguida sigue sin salir en el album");

!$enAlbumDe(0, $idSecreta)
    ? $ok("y sigue oculta para quien no la tiene")
    : $ko("la secreta de uno se le desvela a los demas");

// El denominador sube con ella, asi que encontrarla nunca baja el porcentaje.
$db->contarCromosTotales($duenyo) === $totalAntes + 1
    ? $ok("y cuenta para el album de quien la tiene")
    : $ko("la secreta conseguida no cuenta en su barra de progreso");

$p->exec("DELETE FROM coleccion WHERE id_usuario = $duenyo AND id_cromo = $idSecreta");

/* --- los dos ejes son independientes ------------------------------------- */
$idVisible = (int) $db->crearCromo("__vis_visible_sin_sobres__", "DC", "", "",
    $base["id_expansion"], $base["id_equipo"], 7, $base["id_afinidad"],
    90, 90, 90, 0, "srf", 10, 0, 0);

$fila = $p->query("SELECT solo_cadena, en_sobres FROM cromos WHERE id_cromo = $idVisible")->fetch(PDO::FETCH_ASSOC);
((int) $fila["solo_cadena"] === 0 && (int) $fila["en_sobres"] === 0)
    ? $ok("una carta puede verse en el album y NO salir en sobres")
    : $ko("los dos ejes se pisan entre si");

$db->contarCromosTotales() === $totalAntes + 1
    ? $ok("y esa si cuenta para el album")
    : $ko("una carta no secreta no cuenta para el album");

/* --- una numerada que sale de un sobre se lleva su numero ---------------- */
$sobreNormal = (int) $p->query("
    SELECT s.id_sobre FROM sobre s
    WHERE s.activo = 1 AND s.inicial = 0 AND s.id_expansion = {$base['id_expansion']}
    LIMIT 1
")->fetchColumn();

$idNumerada = (int) $db->crearCromo("__vis_numerada__", "DC", "", "",
    $base["id_expansion"], $base["id_equipo"], 7, $base["id_afinidad"],
    99, 99, 99, 0, "srf", 2, 0, 1);

if (!$sobreNormal) {
    echo "  AVISO no hay sobre normal en esa expansion: no se prueba el reparto numerado.\n";
} else {
    $jugador = (int) $p->query("SELECT id_usuario FROM usuarios LIMIT 1")->fetchColumn();
    $p->exec("UPDATE usuarios SET monedas = 999999 WHERE id_usuario = $jugador");

    $conNumero = 0;
    $sinNumero = 0;
    for ($i = 0; $i < 400 && $conNumero < 2; $i++) {
        $dbAbre = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");
        $r = $dbAbre->abrirSobre($sobreNormal, $jugador);
        if (!$r["ok"]) { break; }
        foreach ($r["cartas"] as $c) {
            if ((int) $c["id_cromo"] !== $idNumerada) { continue; }
            isset($c["numero_serie"]) ? $conNumero++ : $sinNumero++;
        }
    }

    $sinNumero === 0
        ? $ok("ninguna copia numerada sale de un sobre sin numero")
        : $ko("$sinNumero copias salieron SIN numero de serie");

    $emitidas = (int) $p->query("SELECT COUNT(*) FROM cadena_numeracion WHERE id_cromo = $idNumerada")->fetchColumn();
    $emitidas <= 2
        ? $ok("y la tirada no pasa de su cupo ($emitidas de 2)")
        : $ko("se emitieron $emitidas copias con un cupo de 2");
}

/* --- el sobre de bienvenida NUNCA gasta una tirada limitada -------------- */
$idNum2 = (int) $db->crearCromo("__vis_numerada_2__", "POR", "", "",
    $base["id_expansion"], $base["id_equipo"], 7, $base["id_afinidad"],
    99, 99, 99, 0, "srf", 500, 0, 1);

$usuarioNuevo = (int) $p->query("SELECT id_usuario FROM usuarios ORDER BY id_usuario DESC LIMIT 1")->fetchColumn();
$p->exec("UPDATE usuarios SET sobre_inicial_abierto = 0 WHERE id_usuario = $usuarioNuevo");
$p->exec("DELETE FROM coleccion WHERE id_usuario = $usuarioNuevo");
$dbBien = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");
$sobreIni = $dbBien->sobreInicialPendiente($usuarioNuevo);

if ($sobreIni) {
    $r = $dbBien->abrirSobre((int) $sobreIni["id_sobre"], $usuarioNuevo);
    $numeradas = 0;
    foreach (($r["cartas"] ?? []) as $c) {
        if ((int) ($c["cupo_numerado"] ?? 0) > 0) { $numeradas++; }
    }
    $numeradas === 0
        ? $ok("el sobre de bienvenida no gasta ninguna tirada limitada")
        : $ko("el sobre de bienvenida entrego $numeradas cartas numeradas");
}

$p->prepare("DELETE FROM coleccion WHERE id_cromo IN (?, ?, ?, ?)")
  ->execute([$idSecreta, $idVisible, $idNumerada, $idNum2]);
$p->prepare("DELETE FROM cromos WHERE nombre LIKE '__vis_%'")->execute();

/* --- 7: el sobre de bienvenida ------------------------------------------- */
echo "\nSobre de bienvenida y partido amistoso\n";

$idNuevo2 = (int) $p->query("SELECT id_usuario FROM usuarios ORDER BY id_usuario DESC LIMIT 1")->fetchColumn();
$p->exec("UPDATE usuarios SET sobre_inicial_abierto = 0 WHERE id_usuario = $idNuevo2");
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");

$sobre = $db->sobreInicialPendiente($idNuevo2);
$sobre
    ? $ok("hay un sobre de bienvenida pendiente")
    : $ko("no se encontro el sobre de bienvenida");

if ($sobre) {
    $r = $db->abrirSobre((int) $sobre["id_sobre"], $idNuevo2);
    if (!$r["ok"]) {
        $ko("no se pudo abrir el sobre de bienvenida: " . $r["error"]);
    } else {
        count($r["cartas"]) === Tcg::MAZO_TAMANO
            ? $ok("entrega " . Tcg::MAZO_TAMANO . " cartas")
            : $ko("entrego " . count($r["cartas"]) . " cartas");

        /* Las posiciones tienen que cuadrar con la formacion base: si no, el
           paso siguiente del tutorial —montar el mazo titular— es imposible, y
           el tutorial se queda atascado en una puerta que no se puede abrir. */
        $pedidas = array_count_values(array_values(Tcg::huecosDe(Tcg::FORMACION_BASE)));
        $dadas   = array_count_values(array_column($r["cartas"], "posicion"));
        ksort($pedidas);
        ksort($dadas);
        $pedidas === $dadas
            ? $ok("y las posiciones cuadran con la formacion base")
            : $ko("las posiciones no cuadran: " . json_encode($dadas) . " frente a " . json_encode($pedidas));

        $db->abrirSobre((int) $sobre["id_sobre"], $idNuevo2)["ok"] === false
            ? $ok("no se puede abrir dos veces")
            : $ko("SE ABRIO DOS VECES el sobre de bienvenida");

        $db->sobreInicialPendiente($idNuevo2) === null
            ? $ok("y deja de estar pendiente")
            : $ko("sigue apareciendo como pendiente");
    }
}

/* --- 8: el partido amistoso ---------------------------------------------- */
$conMazo = (int) $p->query("
    SELECT m.id_usuario FROM mazos m
    WHERE m.titular = 1 AND (SELECT COUNT(*) FROM mazo_cartas mc WHERE mc.id_mazo = m.id_mazo) = 11
    LIMIT 1
")->fetchColumn();

if (!$conMazo) {
    echo "  AVISO ningun usuario tiene mazo titular completo: no se prueba el amistoso.\n";
} else {
    $p->exec("DELETE FROM duelos WHERE id_creador = $conMazo AND amistoso = 1");
    $ra = $db->crearPartidoAmistoso($conMazo);
    if (!$ra["ok"]) {
        $ko("no se pudo crear el amistoso: " . $ra["error"]);
    } else {
        $idA = (int) $ra["id_duelo"];
        $d = $p->query("SELECT amistoso, id_nodo, monedas FROM duelos WHERE id_duelo = $idA")
            ->fetch(PDO::FETCH_ASSOC);

        ((int) $d["amistoso"] === 1 && $d["id_nodo"] === null)
            ? $ok("el amistoso no pertenece a ninguna cadena")
            : $ko("el amistoso tiene nodo de cadena o no esta marcado");

        (int) $d["monedas"] === 0
            ? $ok("y no apuesta nada")
            : $ko("el amistoso apuesta monedas");

        count($db->listarAlineacionDuelo($idA, $db->idBot())) === Tcg::MAZO_TAMANO
            ? $ok("el rival sale con once jugadores")
            : $ko("el rival del amistoso no tiene once");

        // Pulsar dos veces no debe abrir dos partidos.
        $rb = $db->crearPartidoAmistoso($conMazo);
        ((int) $rb["id_duelo"] === $idA && !empty($rb["reanudado"]))
            ? $ok("pulsar dos veces reanuda el mismo, no abre otro")
            : $ko("se abrio un segundo amistoso");

        count(array_filter($db->listarMisDuelos($conMazo, 50),
              fn($h) => (int) $h["id_duelo"] === $idA)) === 0
            ? $ok("y no sale en el historial de duelos")
            : $ko("el amistoso aparece en el historial");

        /* EL RIVAL NO PUEDE HACER ESPERAR.
           El amistoso se presenta sin reloj ("tomate el tiempo que quieras"),
           asi que si el bot no elige su aumento nadie lo elige por el: no hay
           vencimiento que dispare el repesca y la fase no cierra JAMAS. El
           jugador se quedaba clavado en "Esperando a tu rival..." en el primer
           partido de su vida, dentro del tutorial. */
        $db->aumentoElegido($idA, $db->idBot()) !== null
            ? $ok("el rival elige su aumento al crearse el partido")
            : $ko("el rival no ha elegido aumento: el amistoso se colgaria");

        // Con el jugador eligiendo, la fase tiene que cerrar sin esperar plazo.
        $opcionesYo = $db->listarAumentos($idA, $conMazo);
        if ($opcionesYo) {
            $db->elegirAumento($idA, $conMazo, (int) $opcionesYo[0]["opcion"]);
            $db->cerrarFaseAumento($idA);
            $p->query("SELECT estado FROM duelos WHERE id_duelo = $idA")->fetchColumn() !== 'aumento_pendiente'
                ? $ok("y la fase de aumento cierra en cuanto elige el jugador")
                : $ko("la fase de aumento sigue abierta: el partido no arranca");
        }

        /* --- 9: la presentacion de alineaciones previa al partido ---------- */
        echo "\nPresentacion de alineaciones\n";

        $pres = $db->datosPresentacionDuelo($idA, $conMazo);

        $pres !== null
            ? $ok("el amistoso trae datos de presentacion")
            : $ko("datosPresentacionDuelo devuelve null en un amistoso montado");

        if ($pres) {
            /* LOCAL ES SIEMPRE EL CREADOR, y en PvE el creador es la persona.
               Si esto se invierte, el jugador humano sale como visitante en su
               propio partido contra la maquina. */
            ($pres["local"]["lado"] === "local" && $pres["local"]["tipo"] === "humano")
                ? $ok("el local es el jugador humano")
                : $ko("el local no es el jugador humano");

            ($pres["visitante"]["tipo"] === "ia" && $pres["modo"] === "pve")
                ? $ok("y el visitante es el rival automatico")
                : $ko("el visitante no sale marcado como IA");

            /* El once entero tiene que llegar, repartido por lineas. Si una
               linea se pierde por el camino, la presentacion ensena una
               alineacion incompleta justo antes de jugar con ella. */
            $contar = function (array $lado) {
                $n = 0;
                foreach ($lado["lineas"] as $l) { $n += count($l["unidades"]); }
                return $n;
            };
            ($contar($pres["local"]) === Tcg::MAZO_TAMANO
             && $contar($pres["visitante"]) === Tcg::MAZO_TAMANO)
                ? $ok("los dos onces llegan completos (" . Tcg::MAZO_TAMANO . " por lado)")
                : $ko("falta gente en alguna alineacion de la presentacion");

            // Y en el orden en que se presentan: porteria, defensa, medio, ataque.
            $orden = array_column($pres["local"]["lineas"], "linea");
            $esperado = array_values(array_intersect(["POR", "DF", "MC", "DC"], $orden));
            $orden === $esperado
                ? $ok("y las lineas van de porteria a ataque")
                : $ko("las lineas salen desordenadas: " . implode(",", $orden));

            // El aumento es la decision que la presentacion viene a destacar.
            (!empty($pres["local"]["aumento"]["stat"]) && !empty($pres["visitante"]["aumento"]["stat"]))
                ? $ok("los dos aumentos elegidos viajan con la presentacion")
                : $ko("falta el aumento de algun lado");

            // Un duelo que no existe NO puede reventar: devuelve null y el
            // partido empieza sin intro.
            $db->datosPresentacionDuelo(0, $conMazo) === null
                ? $ok("un duelo que no existe devuelve null, no un error")
                : $ko("datosPresentacionDuelo no protege el duelo inexistente");
        }

        $p->exec("DELETE FROM duelos WHERE id_duelo = $idA");
    }
}

echo "\n";
if ($fallos) { echo "FALLOS: $fallos\n"; exit(1); }
echo "Todo en verde.\n";
