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

header('Content-Type: application/json');

if (empty($_SESSION['dictador']) || $_SESSION['dictador'] != 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado.']);
    exit;
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
        $idRival = $db->crearRival($nombre, trim($_POST['escudo'] ?? ''), trim($_POST['descripcion'] ?? ''), 1);
        echo json_encode(['ok' => true, 'rival' => [
            'id_rival' => $idRival, 'nombre' => $nombre, 'total_estilos' => 0,
        ]]);
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
