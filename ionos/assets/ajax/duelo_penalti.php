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

$res = $db->tirarPenalti(
    (int) ($_POST['id_duelo'] ?? 0),
    (int) $_SESSION['id_usuario'],
    (string) ($_POST['zona'] ?? '')
);

if (empty($res['ok'])) http_response_code(409);
echo json_encode($res, JSON_UNESCAPED_UNICODE);
