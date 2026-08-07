<?php
/**
 * Ejecuta el paso 2 del importador de datos oficiales (panel/importar.php).
 * Vive fuera del render porque tarda varios minutos con el archivo real
 * (descarga de fotos) y necesita correr en segundo plano mientras
 * importacion_progreso.php sondea el avance — ver §15.11 del CLAUDE.md de
 * branding para el porqué completo.
 *
 * Trampa a evitar: session_start() bloquea por fichero mientras la sesión
 * siga abierta. Si no se cierra aquí antes de llamar a ejecutarImportacion(),
 * el sondeo de importacion_progreso.php (misma sesión, misma pestaña) se
 * quedaría colgado esperando el lock hasta que esta petición termine.
 */
session_start();
require_once __DIR__ . '/../../db/conexion.php';

header('Content-Type: application/json');

if (empty($_SESSION['dictador']) || $_SESSION['dictador'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado.']);
    exit;
}

if (!isset($_SESSION['import_datos'], $_SESSION['import_id_expansion'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No hay ninguna importación pendiente de confirmar.']);
    exit;
}

$datos = $_SESSION['import_datos'];
$idExpansion = (int) $_SESSION['import_id_expansion'];
unset($_SESSION['import_datos'], $_SESSION['import_id_expansion']);

$idSesion = session_id();
session_write_close(); // suelta el lock de sesión antes de la parte larga

$decisiones = [];
foreach ($_POST['equipo_eleccion'] ?? [] as $idEquipoJson => $eleccion) {
    $decisiones[$idEquipoJson] = ['eleccion' => $eleccion, 'texto' => $_POST['equipo_texto'][$idEquipoJson] ?? ''];
}

$resultado = $db->ejecutarImportacion($datos, $idExpansion, $decisiones, $idSesion);

echo json_encode($resultado);
