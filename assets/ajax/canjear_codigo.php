<?php
/**
 * Endpoint asíncrono: canjea un código para el usuario en sesión.
 * Lo usa assets/js/scriptPerfil.js desde la pestaña "Códigos" de perfil.php.
 */
session_start();
require_once __DIR__ . '/../../db/conexion.php';

header('Content-Type: application/json');

if (empty($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No has iniciado sesión.']);
    exit;
}

$codigo = strtoupper(trim($_POST['codigo'] ?? ''));

if ($codigo === '') {
    echo json_encode(['ok' => false, 'error' => 'Escribe un código antes de canjearlo.']);
    exit;
}

$resultado = $db->canjearCodigo($codigo, $_SESSION['id_usuario']);

if ($resultado['ok']) {
    $_SESSION['monedas'] = $resultado['monedas'];
}

echo json_encode($resultado);