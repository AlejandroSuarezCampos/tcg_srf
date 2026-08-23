<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

echo '<h1>DIAGNÓSTICO IONOS</h1>';

echo '<h2>PHP</h2>';
echo 'PHP_VERSION: ' . PHP_VERSION . '<br>';
echo 'PHP_SAPI: ' . PHP_SAPI . '<br>';
echo 'PHP_OS: ' . PHP_OS . '<br>';
echo 'DIR: ' . __DIR__ . '<br>';
echo 'DOCUMENT_ROOT: ' . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . '<br>';

echo '<h2>Extensiones</h2>';

$extensiones = [
    'mysqli',
    'pdo',
    'pdo_mysql',
    'mbstring',
    'curl',
    'json',
    'openssl',
    'session',
    'gd'
];

foreach ($extensiones as $ext) {
    echo $ext . ': ' . (extension_loaded($ext) ? 'OK' : 'NO') . '<br>';
}

echo '<h2>Archivos principales</h2>';

$archivos = [
    'landing.php',
    'login.php',
    'navbar.php',
    'configuracion.php',
    'db',
    'partials',
    'components'
];

foreach ($archivos as $archivo) {
    $ruta = __DIR__ . '/' . $archivo;
    echo htmlspecialchars($archivo) . ': ' .
        (file_exists($ruta) ? 'EXISTE' : 'NO EXISTE') .
        '<br>';
}

echo '<h2>Test de include</h2>';

try {
    echo 'Intentando cargar configuracion.php...<br>';

    ob_start();
    include __DIR__ . '/configuracion.php';
    $salida = ob_get_clean();

    echo '<strong>configuracion.php cargado correctamente</strong><br>';

    if ($salida !== '') {
        echo '<pre>' . htmlspecialchars($salida) . '</pre>';
    }

} catch (Throwable $e) {

    if (ob_get_level()) {
        ob_end_clean();
    }

    echo '<h3 style="color:red">ERROR EN configuracion.php</h3>';

    echo '<pre>';
    echo htmlspecialchars(
        get_class($e) . "\n" .
        $e->getMessage() . "\n" .
        'Archivo: ' . $e->getFile() . "\n" .
        'Línea: ' . $e->getLine() . "\n\n" .
        $e->getTraceAsString()
    );
    echo '</pre>';
}

echo '<h2>Fin</h2>';