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

/* ⚠️ SE SUELTA EL BLOQUEO DE SESIÓN AQUÍ, Y ES LO QUE ARREGLABA LOS 504.

   El manejador de sesiones de PHP guarda cada sesión en un archivo y lo tiene
   BLOQUEADO en exclusiva desde `session_start()` hasta que el script termina.
   Mientras uno corre, cualquier otra petición DE LA MISMA PERSONA se queda
   esperando en su `session_start()` — no en la base de datos, no en la red: en
   un cerrojo de archivo.

   Con el partido en vivo eso se convertía en un atasco: el navegador sondea
   cada segundo, y en IONOS la base de datos vive en OTRA máquina
   (`...hosting-data.io`), así que cada sondeo se lleva varias idas y vueltas de
   red. En cuanto una petición tarda más de un segundo, la siguiente ya está
   esperando, y la cola crece sola. Al cambiar de pantalla —pasar la cadena,
   abrir un sobre, guardar un mazo— esa petición se pone LA ÚLTIMA de la cola y
   espera a que se vacíen todos los sondeos pendientes. Si no le da tiempo,
   PHP-FPM corta y sale el «Gateway Timeout».

   Este endpoint solo LEE de la sesión (comprueba quién eres y ya). Cerrándola
   aquí, el cerrojo dura microsegundos en vez de toda la petición y las demás
   dejan de hacer cola. Los datos leídos siguen disponibles en `$_SESSION`: lo
   que se cierra es la escritura, no el array.

   ⚠️ SI ALGÚN DÍA ESTE ARCHIVO NECESITA ESCRIBIR EN `$_SESSION`, la escritura
      ya no se guardará. Habría que quitar esta línea o volver a abrirla con
      `session_start()` justo antes de escribir. */
session_write_close();

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
