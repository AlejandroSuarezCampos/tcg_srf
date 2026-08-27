<?php
/**
 * PROBAR_AGRUPADO_POR_CROMO — las cuatro consultas que devuelven UNA FILA POR
 * CROMO en vez de una por copia siguen cuadrando.
 *
 *   C:\xampp\php\php.exe db/pruebas/probar_agrupado_por_cromo.php
 *
 * Es de SOLO LECTURA y va contra la base de datos de esta máquina: no monta
 * `tcg_prueba` ni escribe nada, así que no entra en `correr_todas.php`. Se
 * corre a mano cuando se toca alguna de esas consultas.
 *
 * Qué vigila, y por qué esas dos cosas:
 *
 * 1. **Que las copias sigan cuadrando.** `COUNT(*)` sumado sobre todos los
 *    cromos tiene que dar exactamente el número de copias que devolvía la
 *    consulta sin agrupar. Si alguien añade un JOIN que multiplica filas —ya
 *    había uno así, el `LEFT JOIN cadena_numeracion` de `listarColeccionVendible`
 *    que no aportaba ninguna columna—, el «×N» de la pantalla empieza a mentir
 *    y nada más se entera.
 *
 * 2. **Que el representante sea una copia de verdad.** Las pantallas mandan un
 *    `id_coleccion` de los que da la consulta agrupada (`MIN(...)`), y quien
 *    recibe el formulario lo vuelve a validar contra la lista SIN agrupar
 *    (`validarLoteApuesta()`, `guardarCartasMazo()`, `publicarAnuncio()`). Si
 *    las dos listas dejaran de coincidir, la pantalla ofrecería cartas que el
 *    servidor rechaza — un fallo que solo aparece al pulsar el botón.
 */

require_once dirname(__DIR__) . '/conexion.php';

$fallos = 0;
$avisos = [];

function ok(string $t): void   { echo "  OK    $t\n"; }
function ko(string $t): void   { global $fallos; $fallos++; echo "  FALLA $t\n"; }

/* Los usuarios con más copias: son los únicos donde un agrupado mal hecho se
   nota. Con una colección de cuatro cartas cuadra hasta estando roto. */
$pdo = (new ReflectionClass($db))->getProperty('pdo');
$pdo->setAccessible(true);
$usuarios = $pdo->getValue($db)->query("
    SELECT id_usuario FROM coleccion
    GROUP BY id_usuario ORDER BY COUNT(*) DESC LIMIT 4
")->fetchAll(PDO::FETCH_COLUMN);

if (!$usuarios) {
    echo "No hay ninguna colección en esta base de datos: nada que comprobar.\n";
    exit(0);
}

echo "PROBAR_AGRUPADO_POR_CROMO — usuarios de prueba: " . implode(', ', $usuarios) . "\n";
echo str_repeat('-', 72), "\n";

foreach ($usuarios as $u) {
    $u = (int) $u;

    // --- 1. Apostables: hay las dos formas, así que se comparan directamente
    $porCopia = $db->listarCopiasApostables($u);
    $porCromo = $db->listarCopiasApostables($u, null, true);

    $suma = array_sum(array_map('intval', array_column($porCromo, 'copias')));
    $suma === count($porCopia)
        ? ok("u$u apostables: " . count($porCromo) . " cromos suman las " . count($porCopia) . " copias")
        : ko("u$u apostables: los cromos suman $suma copias y las copias son " . count($porCopia));

    $idsCopia = array_map('intval', array_column($porCopia, 'id_coleccion'));
    $huerfanos = array_diff(array_map('intval', array_column($porCromo, 'id_coleccion')), $idsCopia);
    empty($huerfanos)
        ? ok("u$u apostables: todos los representantes son copias apostables de verdad")
        : ko("u$u apostables: " . count($huerfanos) . " representantes no están en la lista sin agrupar");

    // --- 2. Las otras tres: un cromo no puede salir dos veces, y el
    //        representante tiene que ser una copia suya.
    foreach ([
        'listarJugadoresDisponibles' => $db->listarJugadoresDisponibles($u),
        'listarColeccionVendible'    => $db->listarColeccionVendible($u),
    ] as $nombre => $filas) {

        $cromos = array_map('intval', array_column($filas, 'id_cromo'));
        count(array_unique($cromos)) === count($cromos)
            ? ok("u$u $nombre: " . count($filas) . " filas, ningún cromo repetido")
            : ko("u$u $nombre: hay cromos repetidos, el GROUP BY no agrupa lo que debe");

        foreach ($filas as $f) {
            if ((int) $f['copias'] < 1) {
                ko("u$u $nombre: una fila dice tener {$f['copias']} copias");
                break;
            }
        }

        // El representante tiene que ser una copia de ESE cromo y de ESTE usuario.
        $malos = 0;
        foreach ($filas as $f) {
            $q = $pdo->getValue($db)->prepare(
                "SELECT COUNT(*) FROM coleccion WHERE id_coleccion = ? AND id_usuario = ? AND id_cromo = ?"
            );
            $q->execute([(int) $f['id_coleccion'], $u, (int) $f['id_cromo']]);
            if (!(int) $q->fetchColumn()) { $malos++; }
        }
        $malos === 0
            ? ok("u$u $nombre: los representantes son copias suyas del cromo que dicen")
            : ko("u$u $nombre: $malos representantes no son copias válidas");
    }

    // --- 3. cromosPoseidos: el mismo conjunto que la cuenta de progreso.
    $poseidos = $db->cromosPoseidos($u);
    count($poseidos) === $db->contarColeccionUsuario($u)
        ? ok("u$u cromosPoseidos: " . count($poseidos) . " cromos, igual que el contador de progreso")
        : ko("u$u cromosPoseidos: " . count($poseidos) . " frente a " . $db->contarColeccionUsuario($u));

    echo "\n";
}

if ($fallos) { echo "$fallos comprobación(es) en rojo.\n"; exit(1); }
echo "Todo en verde.\n";
