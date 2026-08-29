<?php
/**
 * DECIDIR Y EJECUTAR una jugada del partido.
 *
 * ⚠️ EL CLIENTE NO MANDA SU NOTA. Manda la opción pulsada o los puntos del
 * trazo, y el servidor recalcula el rendimiento con `Partido::rendimientoTrazo`.
 * Si algún día alguien añade aquí un parámetro `rendimiento`, ha roto el juego:
 * se apuestan cartas reales y esa nota la pondría cualquiera a 1 desde la
 * consola del navegador.
 */
session_start();
require_once __DIR__ . '/../../db/conexion.php';
require_once __DIR__ . '/../../partials/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No has iniciado sesión.']);
    exit;
}

/* Mismo motivo que en duelo_estado.php: este endpoint solo LEE de la sesión,
   y el partido en vivo sondea cada segundo. Sin cerrar aquí el cerrojo de
   sesión, cada petición hace cola detrás de la anterior y en IONOS eso acaba en
   «Gateway Timeout». */
session_write_close();

/* FICHA DEL MINIJUEGO. El cliente no tiene catálogo propio: lo pide aquí, para
   que no existan dos versiones de la verdad que se puedan desincronizar.
   Se sirve la figura ideal porque hay que PINTARLA — pero eso no da ventaja:
   el servidor la vuelve a generar al puntuar, así que conocerla no evita tener
   que trazarla bien. */
if (isset($_GET['ficha'])) {
    $mj = $db->fichaMinijuego(
        (string) $_GET['ficha'],
        (int) ($_GET['id_duelo'] ?? 0),
        (int) ($_GET['numero'] ?? 0),
        (int) $_SESSION['id_usuario']
    );
    echo json_encode($mj, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!csrfValido($_POST['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.']);
    exit;
}

$idDuelo = (int) ($_POST['id_duelo'] ?? 0);
$numero  = (int) ($_POST['numero'] ?? 0);
$usuario = (int) $_SESSION['id_usuario'];

if (($_POST['que'] ?? '') === 'accion') {
    $res = $db->decidirAccion($idDuelo, $usuario, $numero, (string) ($_POST['accion'] ?? ''));
} else {
    $carga = [];
    if (isset($_POST['opcion'])) {
        $carga['opcion'] = (string) $_POST['opcion'];
    }
    if (isset($_POST['trazo'])) {
        /* Tope duro de puntos ANTES de decodificar nada: un trazo legítimo son
           40-60 puntos, y sin este límite un POST de 200.000 puntos convertiría
           el remuestreo en una forma barata de tumbar el servidor. */
        $bruto = json_decode((string) $_POST['trazo'], true);
        $carga['trazo'] = is_array($bruto) ? array_slice($bruto, 0, 400) : [];
    }
    $res = $db->registrarEjecucion($idDuelo, $usuario, $numero, $carga);
}

/* En PvE no hay nadie al otro lado que mande su ejecución: si no se le empuja
   aquí, la jugada se queda abierta para siempre. */
if (!empty($res['ok'])) {
    $db->jugarTurnoCpu($idDuelo, $numero);
}

if (empty($res['ok'])) http_response_code(409);
echo json_encode($res, JSON_UNESCAPED_UNICODE);
