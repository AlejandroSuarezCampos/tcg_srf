<?php
/**
 * CACHE-BUSTING DE CSS Y JS — helper compartido por todo el sitio.
 *
 * El navegador cachea CSS y JS con fuerza (§8 de branding/CLAUDE.md). Ya ha
 * mordido varias veces, y siempre con el mismo síntoma engañoso: el PHP se
 * regenera (no se cachea nunca), así que el HTML nuevo llega bien, pero se
 * sirve contra un .css/.js viejo. El resultado es "he cambiado el código y no
 * pasa nada" — clases que no existen en la hoja cacheada, funciones que no
 * están en el script cacheado.
 *
 * "?v=filemtime" cambia SOLO cuando el fichero cambia de verdad, así que no
 * tira la caché en cada visita sin motivo.
 *
 * Se incluye desde partials/head.php, pero cualquier página puede incluirlo
 * directamente (require_once) si no usa head.php — es idempotente.
 */

if (!function_exists('assetUrl')) {
    /**
     * @param string $base Prefijo a la raíz del proyecto ('' o '../').
     * @param string $ruta Ruta del asset relativa a la raíz ('assets/js/ui.js').
     */
    function assetUrl(string $base, string $ruta): string {
        // __DIR__ es partials/, así que la raíz del proyecto es un nivel arriba
        $v = @filemtime(__DIR__ . '/../' . $ruta);
        return $base . $ruta . ($v ? '?v=' . $v : '');
    }

    /** Atajo para escribir la etiqueta <script> entera. */
    function assetScript(string $base, string $ruta): string {
        return '<script src="' . htmlspecialchars(assetUrl($base, $ruta)) . '"></script>';
    }
}
