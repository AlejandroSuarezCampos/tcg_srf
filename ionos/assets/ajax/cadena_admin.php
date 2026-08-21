<?php
/**
 * Endpoint del editor visual de Cadenas (panel/cadena_editor.php).
 *
 * Vive aparte del render, como todo endpoint de sondeo/mutación del proyecto
 * (§2 del CLAUDE.md de branding). El editor es un lienzo con estado en
 * memoria del navegador (posiciones de nodos, líneas de aristas ya
 * dibujadas): una recarga de página completa en cada clic lo destrozaría, así
 * que cada acción viaja por aquí y devuelve solo lo que el JS necesita para
 * actualizar su copia local, nunca la página entera.
 *
 * Guardado igual que assets/ajax/importacion_ejecutar.php: solo dictador=1.
 */
session_start();
require_once __DIR__ . '/../../db/conexion.php';
require_once __DIR__ . '/../../partials/csrf.php';
require_once __DIR__ . '/../../partials/subida_imagen.php';

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

// Escudo subido como archivo (crear_rival / actualizar_rival): gana al campo
// de texto si viene, se guarda siempre en assets/img/Escudos/ (carpeta plana,
// sin subcarpetas por expansión — un rival no pertenece a ninguna).
$escudoSubido = null;
if (!empty($_FILES['escudo_archivo']) && $_FILES['escudo_archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $subida = subirImagenPanel(
        $_FILES['escudo_archivo'],
        __DIR__ . '/../../assets/img/Escudos/',
        './assets/img/Escudos/',
        $_POST['nombre'] ?? 'escudo'
    );
    if (!$subida['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $subida['error']]);
        exit;
    }
    $escudoSubido = $subida['ruta'];
}

$accion = $_POST['accion'] ?? '';

switch ($accion) {

    case 'mover_nodo':
        $db->moverNodo((int) $_POST['id_nodo'], (int) $_POST['columna'], (int) $_POST['fila']);
        echo json_encode(['ok' => true]);
        break;

    case 'crear_nodo':
        $idNodo = $db->crearNodo(
            (int) $_POST['id_cadena'],
            $_POST['tipo'] === 'cofre' ? 'cofre' : 'partido',
            trim($_POST['nombre'] ?? '') ?: null,
            (int) $_POST['columna'],
            (int) $_POST['fila'],
            0
        );
        echo json_encode(['ok' => true, 'nodo' => [
            'id_nodo' => $idNodo, 'tipo' => $_POST['tipo'] === 'cofre' ? 'cofre' : 'partido',
            'nombre' => trim($_POST['nombre'] ?? ''), 'columna' => (int) $_POST['columna'],
            'fila' => (int) $_POST['fila'], 'es_final' => 0, 'id_rival' => null, 'rival' => null,
        ]]);
        break;

    case 'actualizar_nodo':
        $db->actualizarNodo(
            (int) $_POST['id_nodo'],
            $_POST['tipo'] === 'cofre' ? 'cofre' : 'partido',
            trim($_POST['nombre'] ?? '') ?: null,
            isset($_POST['es_final']) ? 1 : 0,
            !empty($_POST['id_rival']) ? (int) $_POST['id_rival'] : null
        );
        echo json_encode(['ok' => true]);
        break;

    case 'eliminar_nodo':
        $db->eliminarNodo((int) $_POST['id_nodo']);
        echo json_encode(['ok' => true]);
        break;

    case 'crear_arista':
        $db->crearArista((int) $_POST['id_origen'], (int) $_POST['id_destino']);
        echo json_encode(['ok' => true]);
        break;

    case 'eliminar_arista':
        $db->eliminarArista((int) $_POST['id_origen'], (int) $_POST['id_destino']);
        echo json_encode(['ok' => true]);
        break;

    case 'crear_rival':
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') { echo json_encode(['ok' => false, 'error' => 'Falta el nombre.']); break; }
        $escudo = $escudoSubido ?? trim($_POST['escudo'] ?? '');
        $idRival = $db->crearRival($nombre, $escudo, trim($_POST['descripcion'] ?? ''), 1);
        echo json_encode(['ok' => true, 'rival' => [
            'id_rival' => $idRival, 'nombre' => $nombre, 'total_estilos' => 0, 'escudo' => $escudo,
        ]]);
        break;

    case 'actualizar_rival':
        $idRival = (int) ($_POST['id_rival'] ?? 0);
        $rival = $db->obtenerRival($idRival);
        if (!$rival) { echo json_encode(['ok' => false, 'error' => 'Rival no encontrado.']); break; }
        $nombre = trim($_POST['nombre'] ?? '') ?: $rival['nombre'];
        $escudo = $escudoSubido ?? trim($_POST['escudo'] ?? '');
        $db->actualizarRival(
            $idRival, $nombre, $escudo,
            $rival['descripcion'], (int) $rival['activo']
        );
        echo json_encode(['ok' => true, 'escudo' => $escudo]);
        break;

    case 'crear_estilo':
        $formacion = $_POST['formacion'] ?? '';
        if (!isset(Tcg::FORMACIONES[$formacion])) { echo json_encode(['ok' => false, 'error' => 'Formación no válida.']); break; }
        $nombre = trim($_POST['nombre'] ?? '') ?: Tcg::FORMACIONES[$formacion]['nombre'];
        $idEstilo = $db->crearEstiloRival((int) $_POST['id_rival'], $nombre, $formacion);
        echo json_encode(['ok' => true, 'estilo' => [
            'id_estilo' => $idEstilo, 'nombre' => $nombre, 'formacion' => $formacion,
        ], 'huecos' => Tcg::huecosDe($formacion)]);
        break;

    case 'listar_cartas_estilo':
        echo json_encode(['ok' => true, 'cartas' => $db->listarCartasEstilo((int) $_POST['id_estilo'])]);
        break;

    case 'asignar_carta':
        $db->asignarCartaEstilo((int) $_POST['id_estilo'], (int) $_POST['hueco'], (int) $_POST['id_cromo']);
        echo json_encode(['ok' => true]);
        break;

    case 'crear_loot':
        $tipo = in_array($_POST['tipo'] ?? '', ['monedas', 'cromo', 'cromo_limitado'], true) ? $_POST['tipo'] : 'monedas';
        $idLoot = $db->crearLoot(
            (int) $_POST['id_nodo'],
            $tipo,
            !empty($_POST['id_cromo']) ? (int) $_POST['id_cromo'] : null,
            !empty($_POST['monedas']) ? (int) $_POST['monedas'] : null,
            (float) ($_POST['probabilidad'] ?? 100),
            trim($_POST['rango_minimo'] ?? '') ?: null
        );
        echo json_encode(['ok' => true, 'id_loot' => $idLoot]);
        break;

    case 'eliminar_loot':
        $db->eliminarLoot((int) $_POST['id_loot']);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Acción desconocida.']);
}
