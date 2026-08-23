<?php
/**
 * Alta rápida de equipos, para no tener que salir del formulario de un cromo.
 *
 * Existe porque el orden natural al meter contenido es al revés del que
 * permitía la web: llega un jugador de un equipo que todavía no está dado de
 * alta, y hasta ahora había que abandonar el formulario a medias, irse a la
 * base de datos a crear el equipo, y volver a empezar. Lo usan el panel de
 * cromos y el editor de cadenas, así que vive aquí y no dentro de ninguno de
 * los dos.
 *
 * Guardado igual que el resto de endpoints del panel: solo dictador = 1.
 */
session_start();
require_once __DIR__ . '/../../db/conexion.php';
require_once __DIR__ . '/../../partials/csrf.php';

header('Content-Type: application/json');

if (empty($_SESSION['dictador']) || $_SESSION['dictador'] != 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado.']);
    exit;
}

if (!csrfValido($_POST['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.']);
    exit;
}

switch ($_POST['accion'] ?? '') {

    case 'crear_equipo':
        $r = $db->crearEquipoAdmin(
            $_POST['nombre'] ?? '',
            $_POST['universo'] ?? 'srf',
            null,
            $_POST['descripcion'] ?? null
        );
        if ($r['ok']) {
            // Se devuelve el equipo entero para que quien llame pueda meterlo
            // en su desplegable sin recargar ni volver a preguntar.
            $r['equipo'] = $db->obtenerEquipo($r['id_equipo']);
        }
        echo json_encode($r);
        break;

    case 'listar_equipos':
        echo json_encode(['ok' => true, 'equipos' => $db->listarEquiposAdmin()]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Acción desconocida.']);
}
