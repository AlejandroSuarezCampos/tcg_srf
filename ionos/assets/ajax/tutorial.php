<?php
/**
 * Avance del tutorial de bienvenida (migración `036`).
 *
 * Tres acciones y ninguna más: guardar en qué paso va, darlo por terminado y
 * darlo por saltado. El guion no viaja por aquí —lo sirve el propio HTML— y
 * los requisitos tampoco: los calcula el servidor al pintar la página.
 *
 * A diferencia del resto de endpoints, este NO es del panel: lo usa cualquier
 * cuenta recién registrada. La guardia es solo estar identificado, y cada
 * usuario únicamente puede tocar SU propio tutorial —el id sale de la sesión,
 * nunca del envío.
 */
session_start();
require_once __DIR__ . '/../../db/conexion.php';
require_once __DIR__ . '/../../partials/csrf.php';

header('Content-Type: application/json');

if (empty($_SESSION['id_usuario'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No has iniciado sesión.']);
    exit;
}

if (!csrfValido($_POST['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.']);
    exit;
}

$id = (int) $_SESSION['id_usuario'];

switch ($_POST['accion'] ?? '') {

    case 'paso':
        echo json_encode($db->guardarTutorialPaso($id, $_POST['paso'] ?? ''));
        break;

    // Terminado y saltado se guardan distinto a propósito: así se puede saber
    // cuánta gente lo termina y cuánta lo abandona, que es el único dato que
    // dice si el tutorial sirve para algo.
    case 'hecho':
    case 'saltado':
        echo json_encode($db->guardarTutorialPaso($id, $_POST['accion']));
        break;

    /* El partido de prueba del tutorial. Lo crea el SERVIDOR y devuelve a
       dónde ir: el cliente no puede montar un duelo por su cuenta, y así el
       amistoso queda con las mismas garantías que cualquier otro partido. */
    case 'amistoso':
        $r = $db->crearPartidoAmistoso($id);
        if ($r['ok']) { $r['url'] = 'duelo.php?id=' . (int) $r['id_duelo']; }
        echo json_encode($r);
        break;

    /* Releer los requisitos SIN recargar la página.
       Hace falta porque las dos puertas se cruzan por AJAX —abrir el sobre de
       bienvenida, guardar el mazo— y sin esto el tutorial se quedaba diciendo
       "todavía no lo has hecho" encima de algo que la persona acababa de
       hacer, sin más salida que recargar a mano o saltarse el tutorial. */
    case 'logros':
        echo json_encode(['ok' => true, 'logros' => $db->tutorialLogros($id)]);
        break;

    // Volver a verlo desde el perfil.
    case 'reiniciar':
        echo json_encode($db->guardarTutorialPaso($id, Tcg::TUTORIAL_PASOS[0]['clave']));
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Acción desconocida.']);
}
