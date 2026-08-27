<?php
/**
 * Sondeado por scriptImportar.js cada ~800ms mientras
 * importacion_ejecutar.php sigue en vuelo. Lee el fichero de progreso que
 * escribe ejecutarImportacion() (db/consultas.php) — nada de BD ni sesión
 * compartida, ver §15.11 del CLAUDE.md de branding.
 */
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['dictador']) || $_SESSION['dictador'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado.']);
    exit;
}

$idSesion = session_id();
session_write_close();

$fichero = sys_get_temp_dir() . '/tcg_importacion_progreso_' . $idSesion . '.json';

if (file_exists($fichero)) {
    $contenido = file_get_contents($fichero);
    echo $contenido !== false && $contenido !== '' ? $contenido : json_encode(['actual' => 0, 'total' => 0]);
} else {
    echo json_encode(['actual' => 0, 'total' => 0]);
}
