<?php
/**
 * Resuelve UNA jugada de minijuego del partido en vivo.
 *
 * El navegador manda solo QUÉ OPCIÓN eligió. Todo lo demás —qué remate
 * llegaba, si acierta, si el marcador puede moverse y si el partido se
 * reanuda— lo recalcula el servidor. El remate nunca viaja al cliente antes de
 * decidir (ver Tcg::narracionDuelo), así que no se puede acertar mirando la
 * respuesta de red.
 *
 * La protección contra resolver dos veces la misma jugada ya no está en la
 * sesión sino en la clave primaria de `duelo_minijuegos`: la sesión no servía
 * con dos jugadores ni sobrevivía a un cambio de pestaña.
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

$res = $db->resolverMinijuegoDuelo(
    (int) ($_POST['id_duelo'] ?? 0),
    (int) $_SESSION['id_usuario'],
    (int) ($_POST['id_evento'] ?? 0),
    (string) ($_POST['opcion'] ?? '')
);

if (empty($res['ok'])) http_response_code(409);
echo json_encode($res, JSON_UNESCAPED_UNICODE);
