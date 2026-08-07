<?php
/**
 * SONDEO DEL PARTIDO EN VIVO (migración 014).
 *
 * El navegador ya no reproduce el partido por su cuenta: pregunta aquí en qué
 * minuto va, qué se ha narrado hasta ahí y si hay un minijuego esperando. El
 * reloj lo lleva el servidor, así que los dos jugadores ven el mismo minuto y
 * una pausa por minijuego los detiene a los dos.
 *
 * Mismo patrón sin websockets que la sala de espera: sondeo + latido. Toda la
 * lógica vive en Tcg::estadoPartido(), que además arranca el reloj, pausa,
 * aplica el fallback de quien no contesta y reanuda, todo en diferido — no hay
 * cron en este proyecto.
 */
session_start();
require_once __DIR__ . '/../../db/conexion.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No has iniciado sesión.']);
    exit;
}

$id_duelo = (int) ($_GET['id_duelo'] ?? $_POST['id_duelo'] ?? 0);
$res = $db->estadoPartido($id_duelo, (int) $_SESSION['id_usuario']);

if (empty($res['ok'])) http_response_code(409);
echo json_encode($res, JSON_UNESCAPED_UNICODE);
