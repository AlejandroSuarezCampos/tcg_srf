<?php
/**
 * Endpoint de las tareas de mantenimiento (panel/mantenimiento.php).
 *
 * Vive aparte del render como todo endpoint de mutación del proyecto: son
 * tareas que tardan segundos y devuelven un informe, no una página.
 *
 * ⚠️ SOLO DICTADOR. Estas dos borran archivos del servidor y reescriben
 * columnas de la base; no hay ninguna versión suave de eso.
 */

session_start();
require_once __DIR__ . '/../../db/conexion.php';
require_once __DIR__ . '/../../partials/csrf.php';
require_once __DIR__ . '/../../db/herramientas/mantenimiento.php';

header('Content-Type: application/json');

if (empty($_SESSION['dictador']) || $_SESSION['dictador'] != 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado.']);
    exit;
}

if (!csrfValido($_POST['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.']);
    exit;
}

/* Se suelta el bloqueo de sesión: este endpoint solo LEE de ella. Ver la nota
   larga en `duelo_narracion.php` — mientras un script la tiene abierta,
   cualquier otra petición de la misma persona espera en su `session_start()`,
   y esa cola es la que acababa en «Gateway Timeout». Si algún día hace falta
   escribir en `$_SESSION` aquí, hay que quitar esta línea. */
session_write_close();

/* Convertir el catálogo entero de imágenes tarda segundos, no milisegundos:
   son cientos de archivos leídos, recomprimidos y escritos. Con el límite de
   30 s por defecto la tarea moría a media faena y dejaba media carpeta
   convertida y media no — el peor estado posible. */
set_time_limit(300);

/* `aplica` tiene que llegar EXPLÍCITAMENTE como "1". Cualquier otra cosa
   —vacío, ausente, "0", "false"— simula. Es la diferencia entre enseñar un
   informe y borrar archivos, así que el valor por defecto es no hacer nada. */
$aplica = ($_POST['aplica'] ?? '') === '1';

try {
    switch ($_POST['accion'] ?? '') {
        case 'webp':
            echo json_encode(['ok' => true, 'aplicado' => $aplica]
                + mantenimientoWebp($db, $aplica));
            break;

        case 'compos':
            $r = mantenimientoCompos($db, $aplica);
            echo json_encode([
                'ok'         => true,
                'aplicado'   => $aplica,
                'pendientes' => count($r['pendientes']),
                'tocadas'    => $r['tocadas'],
                'quedan'     => count($r['quedan']),
                // Solo las primeras: la lista es para ver QUÉ falta, no para
                // mandar cuatrocientas filas por la red.
                'muestra'    => array_slice($r['pendientes'], 0, 20),
            ]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Acción desconocida.']);
    }
} catch (Throwable $e) {
    /* Se informa de que ha fallado, nunca del detalle: el mensaje de una
       excepción puede llevar rutas del servidor o trozos de SQL. */
    error_log('mantenimiento.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'La tarea falló. Mira el registro del servidor.']);
}
