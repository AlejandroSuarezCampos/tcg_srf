<?php
/**
 * Endpoint asíncrono de la sala de duelo. Hace dos cosas a la vez:
 *
 *   · LATIDO — mientras el creador espera rival, está DENTRO de la sala y no
 *     puede estar en otra parte de la web. Sin websockets, "estar dentro" se
 *     demuestra latiendo cada pocos segundos. Si deja de latir, la sala se da
 *     por abandonada y se cancela devolviendo lo apostado.
 *
 *   · SONDEO — devuelve el estado del duelo para que la pantalla sepa cuándo
 *     ha entrado alguien y pase sola a la pantalla de partido.
 *
 * Acepta también `accion=salir`, que usa navigator.sendBeacon al cerrar la
 * pestaña: cancela la sala al instante en vez de esperar a que caduque.
 */
session_start();
require_once __DIR__ . '/../../db/conexion.php';
require_once __DIR__ . '/../../partials/csrf.php';

header('Content-Type: application/json');

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

// El navegador siempre manda esto por POST (fetch o sendBeacon con FormData),
// nunca por query string: el antiguo fallback a $_GET solo abría la puerta a
// disparar 'salir' con un simple enlace (CSRF), no lo usaba nadie de verdad.
if (!csrfValido($_POST['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.']);
    exit;
}

$id_usuario = (int) $_SESSION['id_usuario'];
$id_duelo   = (int) ($_POST['id_duelo'] ?? 0);
$accion     = $_POST['accion'] ?? 'latir';

$duelo = $db->obtenerDuelo($id_duelo, $id_usuario);
if (!$duelo) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Ese duelo no existe o no es tuyo.']);
    exit;
}

// Salir de la sala la mata: es la regla acordada, el creador no puede dejarla
// esperando de fondo mientras hace otra cosa.
if ($accion === 'salir') {
    if ((int) $duelo['id_creador'] === $id_usuario && $duelo['estado'] === 'creado') {
        $db->cancelarDuelo($id_duelo, $id_usuario);
    }
    echo json_encode(['ok' => true, 'estado' => 'cancelado']);
    exit;
}

// Latido normal del creador mientras espera.
if ((int) $duelo['id_creador'] === $id_usuario && $duelo['estado'] === 'creado') {
    $db->latirDuelo($id_duelo, $id_usuario);
}

// El sondeo de la fase de aumento empuja el duelo: si venció el plazo se elige
// por quien falte, y si ya han elegido los dos se resuelve. Así el partido
// avanza aunque nadie recargue a mano.
if ($duelo['estado'] === 'aumento_pendiente') {
    $db->cerrarFaseAumento($id_duelo);
    $duelo = $db->obtenerDuelo($id_duelo, $id_usuario);
}
if ($duelo['estado'] === 'listo_para_resolver') {
    $db->resolverDuelo($id_duelo);
}

// Se relee el estado: entre el latido y ahora puede haber entrado el rival.
$duelo = $db->obtenerDuelo($id_duelo, $id_usuario);

$respuesta = [
    'ok'     => true,
    'estado' => $duelo['estado'],
    // La pantalla solo necesita saber si debe seguir esperando o ya pasar al
    // partido; el detalle lo pinta el servidor al recargar.
    'listo'  => !in_array($duelo['estado'], ['creado'], true),
];

/* El bucle de jugadas viaja en el mismo sondeo que ya existía: una petición por
   segundo, no dos. `estadoPartido()` ya abre la jugada siguiente si hace falta,
   así que sondear es también lo que hace avanzar el partido. */
$respuesta['partido'] = $db->estadoPartido($id_duelo, (int) $_SESSION['id_usuario']);
if (!empty($respuesta['partido']['jugada'])) {
    $db->jugarTurnoCpu($id_duelo, (int) $respuesta['partido']['jugada']['numero']);
}

echo json_encode($respuesta);
