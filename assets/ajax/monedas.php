<?php
/**
 * Endpoint asíncrono: devuelve el saldo de monedas actual del usuario en
 * sesión, directamente desde la BD (nunca desde $_SESSION, que puede
 * quedarse desactualizada). Lo usa assets/async/js/scriptsAsync.js.
 */
session_start();
require_once __DIR__ . '/../db/conexion.php';

header('Content-Type: application/json');

if (empty($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No has iniciado sesión.']);
    exit;
}

$usuario = $db->obtenerUsuario($_SESSION['id_usuario']);

if (!$usuario) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado.']);
    exit;
}

// Aprovechamos para mantener también la sesión sincronizada
$_SESSION['monedas'] = $usuario['monedas'];

echo json_encode(['ok' => true, 'monedas' => (int) $usuario['monedas']]);