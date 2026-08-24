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

$id_duelo = (int) ($_GET['id_duelo'] ?? $_POST['id_duelo'] ?? 0);
$res = $db->estadoPartido($id_duelo, (int) $_SESSION['id_usuario']);

if (empty($res['ok'])) http_response_code(409);
echo json_encode($res, JSON_UNESCAPED_UNICODE);
