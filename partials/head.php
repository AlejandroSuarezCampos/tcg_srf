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

<!-- MOTOR DE MOVIMIENTO — va INLINE y lo primero de todo, a propósito.
     · Inline: un fichero .js aparte podría servirse cacheado y dejar la
       página con el modo equivocado (ya pasó con ui.js).
     · Lo primero: fija data-motion en <html> ANTES del primer pintado, así no
       se ve un fotograma con las animaciones del modo que no toca.

     TRES NIVELES, no un interruptor (MASTER.md §5.1). Más animación y móviles
     de gama baja son objetivos en conflicto, y no se resuelve animando menos:
     se resuelve animando DISTINTO según el aparato.
       full   · todo: revelados, inclinación 3D, rescoldo latiendo, ceremonias
       lite   · solo opacidad y desplazamientos cortos. Sin 3D ni latido
       reduce · fundidos de 120 ms y nada más

     Todo el CSS cuelga de :root[data-motion="…"] en vez de
     @media (prefers-reduced-motion), porque una media query la decide el
     sistema y NO se puede sobrescribir desde JavaScript: con ella, activar las
     animaciones en la web dejaba las ceremonias en display:none y la pantalla
     muerta. La elección del jugador manda SIEMPRE sobre la detección. -->
<script>
(function () {
  var SRF = (window.SRF = window.SRF || {});
  var CLAVE = 'srf-animaciones';   // 'full' | 'lite' | 'reduce' | ausente = automático

  // Token CSRF de la sesión, leído del <meta> de esta misma página. Todo
  // fetch/sendBeacon que mute estado lo añade a su payload como 'csrf'.
  SRF.csrfToken = function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  };

  /* Respaldo para cuando localStorage no está disponible (modo privado, o
     cookies de terceros bloqueadas en un iframe). Sin esto, el jugador elige
     un nivel y no pasa nada: se guarda en ningún sitio y al releer vuelve la
     detección automática. Con esto, al menos la elección vale para la página
     en la que está — que es todo lo que se puede prometer sin almacenamiento. */
  var enMemoria = null;

  function leer() {
    try { return localStorage.getItem(CLAVE); } catch (e) { return enMemoria; }
  }

  /* Valores del sistema anterior, que solo tenía dos estados. Se traducen al
     vuelo para que nadie pierda su preferencia al desplegar. */
  var VIEJOS = { si: 'full', no: 'reduce' };

  var NIVELES = { full: 1, lite: 1, reduce: 1 };

  /** Lo que pide el aparato, ignorando lo que haya elegido el jugador. */
  SRF.nivelDetectado = function () {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return 'reduce';
    // `deviceMemory` solo existe en Chromium; en el resto no penalizamos.
    var nucleos = navigator.hardwareConcurrency || 8;
    var memoria = navigator.deviceMemory || 8;
    return (nucleos <= 4 || memoria <= 4) ? 'lite' : 'full';
  };

  /** El nivel que se está aplicando ahora mismo. */
  SRF.nivelMovimiento = function () {
    var guardado = VIEJOS[leer()] || leer();
    return NIVELES[guardado] ? guardado : SRF.nivelDetectado();
  };

  SRF.aplicarMovimiento = function () {
    document.documentElement.dataset.motion = SRF.nivelMovimiento();
  };

  /** null = automático (vuelve a hacer caso al aparato y al sistema). */
  SRF.fijarNivelMovimiento = function (nivel) {
    var valido = (nivel !== null && NIVELES[nivel]) ? nivel : null;
    enMemoria = valido;
    try {
      if (valido === null) localStorage.removeItem(CLAVE);
      else localStorage.setItem(CLAVE, valido);
    } catch (e) { /* sin almacenamiento: vale el respaldo en memoria */ }
    SRF.aplicarMovimiento();
  };

  /* ---- Compatibilidad con el interruptor de dos estados ----
     Lo usan ceremonia.js, ceremonia_cofre.js, duelo.js, presentacion.js y
     configuracion.js. Se mantiene hasta que esas cinco pantallas se migren. */
  SRF.movimientoReducido = function () { return SRF.nivelMovimiento() === 'reduce'; };
  SRF.preferenciaMovimiento = function () {
    var v = leer();
    return VIEJOS[v] || (NIVELES[v] ? v : null);
  };
  SRF.fijarPreferenciaMovimiento = function (valor) {
    SRF.fijarNivelMovimiento(valor === null ? null : (VIEJOS[valor] || valor));
  };

  SRF.aplicarMovimiento();
  // si el jugador cambia la preferencia del SISTEMA con la página abierta y
  // aquí está en automático, el nivel se actualiza solo
  var mq = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (mq.addEventListener) mq.addEventListener('change', SRF.aplicarMovimiento);
})();
</script>

<?php /* Tipografía autoalojada: sin dependencia de terceros, ni siquiera de
         Google Fonts (un host más = un DNS y un TLS más antes del primer
         pintado, y en IONOS eso se nota).
         Inter variable para el cuerpo, Space Grotesk variable para display y
         etiquetas. Solo se precargan los subconjuntos `latin`: los `-ext` los
         pide el navegador únicamente si la página trae caracteres que los
         necesiten, y para castellano casi nunca ocurre. */ ?>
<link rel="preload" href="<?= $base ?>assets/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= $base ?>assets/fonts/space-grotesk-latin.woff2" as="font" type="font/woff2" crossorigin>
<?php /* Las de iconos también, LAS TRES. Sin el preload el navegador no se
         entera de que existen hasta haber descargado y analizado `iconos.css`,
         y como esas fuentes van con `font-display: block` el hueco del icono
         se queda EN BLANCO mientras tanto — que es exactamente el "los iconos
         desaparecen" que se ve en un móvil con la conexión regular.
         Pesan 9,2 + 0,5 + 1,8 KB: precargar las tres cuesta menos que un
         icono suelto de los de antes. */ ?>
<link rel="preload" href="<?= $base ?>assets/fonts/iconos-regular.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= $base ?>assets/fonts/iconos-fill.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= $base ?>assets/fonts/iconos-bold.woff2" as="font" type="font/woff2" crossorigin>

<link rel="stylesheet" href="<?= assetUrl($base, 'assets/css/tokens.css') ?>">
<link rel="stylesheet" href="<?= assetUrl($base, 'assets/css/base.css') ?>">
<link rel="stylesheet" href="<?= assetUrl($base, 'assets/css/components.css') ?>">
<link rel="stylesheet" href="<?= assetUrl($base, 'assets/css/layout.css') ?>">
<?php /* Sistema nuevo: va DESPUÉS de las hojas antiguas para poder ganarles
         mientras dure la migración. Trae los ocho dispositivos visuales y las
         primitivas de movimiento. */ ?>
<link rel="stylesheet" href="<?= assetUrl($base, 'assets/css/ascua.css') ?>">
<?php foreach ($cssExtra as $hoja): ?>
<link rel="stylesheet" href="<?= htmlspecialchars(assetUrl($base, $hoja)) ?>">
<?php endforeach; ?>

<?php /* ⚠️ ICONOS PROPIOS, NO unpkg.com. Aquí había TRES hojas de
         @phosphor-icons servidas desde unpkg, y cada una arrastra su fuente.
         Medido: 250 KB de CSS + 429 KB de fuentes = 679 KB EN CADA CARGA, de
         un tercero (con su DNS y su TLS aparte) y con las tres hojas
         bloqueando el primer pintado. Todo eso para usar 93 iconos de los
         ~1.500 que trae el paquete.

         `assets/css/iconos.css` lleva solo esos 93, y las fuentes están
         subseteadas a sus glifos: 17,9 KB en total, del propio dominio y
         cacheables para siempre. Lo genera un script a partir de los
         `ph-loquesea` que aparecen en el código; si añades un icono nuevo y
         no se ve, hay que volver a pasarlo. */ ?>
<link rel="stylesheet" href="<?= assetUrl($base, 'assets/css/iconos.css') ?>">
</head>
<body<?= $bodyClass !== '' ? ' class="' . htmlspecialchars($bodyClass) . '"' : '' ?>>

<a class="skip-link" href="#contenido">Saltar al contenido</a>
