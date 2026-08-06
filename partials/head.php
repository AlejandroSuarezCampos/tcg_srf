<?php
/**
 * CABECERA COMPARTIDA — abre el documento en todas las páginas del sitio.
 *
 * Centraliza fuentes, iconos y hojas de estilo: migrar tipografía o añadir una
 * hoja nueva se hace aquí una vez, no en catorce ficheros.
 *
 * Antes de incluirlo, la página puede definir:
 *   $paginaTitulo  -> título de la pestaña (sin el sufijo de marca)
 *   $paginaDesc    -> meta description
 *   $base          -> prefijo relativo a la raíz del proyecto ('' o '../')
 *   $cssExtra      -> array de hojas adicionales, relativas a $base
 *   $bodyClass     -> clases del <body>
 */
$base         = $base         ?? '';
$paginaTitulo = $paginaTitulo ?? 'Superliga Frontier TCG';
$paginaDesc   = $paginaDesc   ?? 'El registro coleccionable de la Superliga Frontier.';
$cssExtra     = $cssExtra     ?? [];
$bodyClass    = $bodyClass    ?? '';

// Cache-busting por fecha de modificación: el navegador cachea CSS/JS con
// fuerza (§8 de branding/CLAUDE.md, ya mordió más de una vez — la última,
// tras renombrar caja3d→pack3d en todo el sistema de sobres, dejó HTML con
// clases nuevas sirviéndose contra un components.css viejo en caché, con el
// layout roto por completo). "?v=filemtime" cambia solo cuando el fichero
// cambia de verdad, así que no invalida caché en cada visita sin motivo.
function assetUrl(string $base, string $ruta): string {
    $abs = __DIR__ . '/../' . $ruta;
    $v = @filemtime($abs);
    return $base . $ruta . ($v ? '?v=' . $v : '');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($paginaTitulo) ?> · Superliga Frontier TCG</title>
<meta name="description" content="<?= htmlspecialchars($paginaDesc) ?>">
<meta name="color-scheme" content="dark">
<link rel="icon" type="image/png" href="<?= $base ?>assets/img/iconos/favicon.ico">

<!-- Geist autoalojada: sin dependencia de terceros para la tipografía -->
<link rel="preload" href="<?= $base ?>assets/fonts/geist-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= $base ?>assets/fonts/geist-mono-latin.woff2" as="font" type="font/woff2" crossorigin>

<link rel="stylesheet" href="<?= assetUrl($base, 'assets/css/tokens.css') ?>">
<link rel="stylesheet" href="<?= assetUrl($base, 'assets/css/base.css') ?>">
<link rel="stylesheet" href="<?= assetUrl($base, 'assets/css/components.css') ?>">
<link rel="stylesheet" href="<?= assetUrl($base, 'assets/css/layout.css') ?>">
<?php foreach ($cssExtra as $hoja): ?>
<link rel="stylesheet" href="<?= htmlspecialchars(assetUrl($base, $hoja)) ?>">
<?php endforeach; ?>

<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
</head>
<body<?= $bodyClass !== '' ? ' class="' . htmlspecialchars($bodyClass) . '"' : '' ?>>

<a class="skip-link" href="#contenido">Saltar al contenido</a>
