<?php
/**
 * Fusionada en `plantilla.php` (bloque 4 del rediseño).
 *
 * Se conserva como redirección permanente y no se borra porque hay enlaces
 * repartidos por el sitio, marcadores de jugadores y un paso del tutorial que
 * apuntan aquí. Los filtros de la URL viajan intactos: `plantilla.php` usa los
 * mismos nombres a propósito.
 */
$q = $_SERVER['QUERY_STRING'] ?? '';
header('Location: plantilla.php' . ($q !== '' ? '?' . $q : ''), true, 301);
exit;
