<?php
/**
 * Registra el hueco que ha elegido un jugador en el penalti abierto.
 *
 * El navegador manda SOLO la zona. Todo lo demás —si le toca tirar o parar, si
 * el tiro sigue abierto, si con esto se resuelve y si la tanda ha terminado— lo
 * decide el servidor.
 *
 * ⚠️ La respuesta NO dice qué eligió el rival, ni siquiera cuando el tiro se
 * resuelve con esta misma petición: el resultado se lee del sondeo, igual para
 * los dos. Si esto contestara con la zona del otro, el que eligiera SEGUNDO se
 * enteraría antes, y en una tanda simultánea eso es toda la ventaja del mundo.
 *
 * Que no se pueda elegir dos veces, ni cambiar de opinión, ni elegir después de
 * que el tiro se resuelva, lo garantiza el `zona_X IS NULL` que va dentro del
 * UPDATE de Tcg::tirarPenalti(), no una comprobación en PHP.
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

if (!csrfValido($_POST['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.']);
    exit;
}

$res = $db->tirarPenalti(
    (int) ($_POST['id_duelo'] ?? 0),
    (int) $_SESSION['id_usuario'],
    (string) ($_POST['zona'] ?? '')
);

if (empty($res['ok'])) http_response_code(409);
echo json_encode($res, JSON_UNESCAPED_UNICODE);
