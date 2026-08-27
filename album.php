<?php
/**
 * Fusionada en `plantilla.php` (bloque 4 del rediseño).
 *
 * El álbum era la misma rejilla que la colección con otro filtro; ahora es la
 * posición «Todas» del conmutador. Se conserva como redirección porque la
 * portada, el pie, `sobres.php` y un paso del tutorial enlazan aquí.
 */
$q = $_SERVER['QUERY_STRING'] ?? '';
$q = ($q !== '' ? $q . '&' : '') . 'ver=todas';
header('Location: plantilla.php?' . $q, true, 301);
exit;
