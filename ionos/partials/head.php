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
require_once __DIR__ . '/assets.php';   // assetUrl() / assetScript()
require_once __DIR__ . '/csrf.php';     // csrfToken() / csrfCampo() / csrfValido()

$base         = $base         ?? '';
$paginaTitulo = $paginaTitulo ?? 'Superliga Frontier TCG';
$paginaDesc   = $paginaDesc   ?? 'El registro coleccionable de la Superliga Frontier.';
$cssExtra     = $cssExtra     ?? [];
$bodyClass    = $bodyClass    ?? '';

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($paginaTitulo) ?> · Superliga Frontier TCG</title>
<meta name="description" content="<?= htmlspecialchars($paginaDesc) ?>">
<meta name="color-scheme" content="dark">
<meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
<link rel="icon" type="image/png" href="<?= $base ?>assets/img/iconos/favicon.ico">

<!-- PREFERENCIA DE MOVIMIENTO — va INLINE y lo primero de todo, a propósito.
     · Inline: un fichero .js aparte podría servirse cacheado y dejar la
       página con el modo equivocado (ya pasó con ui.js).
     · Lo primero: fija data-motion en <html> ANTES del primer pintado, así no
       se ve un fotograma con las animaciones del modo que no toca.
     Todo el CSS del sitio cuelga de :root[data-motion="reduce"] en vez de
     @media (prefers-reduced-motion), porque una media query la decide el
     sistema y NO se puede sobrescribir desde JavaScript: con ella, activar las
     animaciones en la web dejaba las ceremonias en display:none y la pantalla
     muerta. La preferencia propia manda sobre la del sistema. -->
<script>
(function () {
  var SRF = (window.SRF = window.SRF || {});
  var CLAVE = 'srf-animaciones';   // 'si' | 'no' | ausente = automático

  // Token CSRF de la sesión, leído del <meta> de esta misma página. Todo
  // fetch/sendBeacon que mute estado lo añade a su payload como 'csrf'.
  SRF.csrfToken = function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  };

  SRF.preferenciaMovimiento = function () {
    try { return localStorage.getItem(CLAVE); } catch (e) { return null; }
  };
  SRF.movimientoReducido = function () {
    var p = SRF.preferenciaMovimiento();
    if (p === 'si') return false;
    if (p === 'no') return true;
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  };
  SRF.aplicarMovimiento = function () {
    document.documentElement.dataset.motion = SRF.movimientoReducido() ? 'reduce' : 'full';
  };
  SRF.fijarPreferenciaMovimiento = function (valor) {
    try {
      if (valor === null) localStorage.removeItem(CLAVE);
      else localStorage.setItem(CLAVE, valor);
    } catch (e) { /* modo privado: se queda en automático */ }
    SRF.aplicarMovimiento();
  };

  SRF.aplicarMovimiento();
  // si el jugador cambia la preferencia del SISTEMA con la página abierta y
  // aquí está en automático, el modo se actualiza solo
  var mq = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (mq.addEventListener) mq.addEventListener('change', SRF.aplicarMovimiento);
})();
</script>

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
