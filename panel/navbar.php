<?php
/**
 * Sidebar compartida por las páginas del panel de administración.
 *
 * Antes de incluir este archivo, cada página define:
 *   $activeAdmin -> 'inicio' | 'cromos' | 'importar' | 'sobres' |
 *                   'expansiones' | 'plantillas' | 'usuarios'
 *
 * El desplegable en móvil NO tiene JS propio: usa el mismo mecanismo que la
 * nav del sitio (assets/js/ui.js → iniciarNav()), que ya está cargado por
 * partials/footer.php en cada página del panel. Por eso el <nav> lleva
 * id="nav-menu" y el botón la clase .nav-burger — son los selectores que
 * iniciarNav() busca. La CSS de cómo se ve colapsado es propia
 * (panel/assets/css/admin.css), pero el comportamiento (abrir/cerrar, Esc,
 * aria-expanded) es el compartido.
 */
$activeAdmin = $activeAdmin ?? '';

$enlaces = [
    ['inicio',      'index.php',       'Inicio',          'ph-house'],
    ['cromos',      'cromos.php',      'Cromos',          'ph-cards'],
    ['importar',    'importar.php',    'Importar datos',  'ph-cloud-arrow-up'],
    ['sobres',      'sobres.php',      'Sobres',          'ph-package'],
    ['expansiones', 'expansiones.php', 'Expansiones',     'ph-folder-open'],
    ['plantillas',  'plantillas.php',  'Plantillas 3D',   'ph-cube'],
    ['usuarios',    'usuarios.php',    'Usuarios',        'ph-users'],
];
?>
<aside class="admin-sidebar">
  <div class="admin-sidebar-top">
    <div class="logo">Superliga Frontier<span class="logo-punto">·</span>TCG</div>
    <button class="nav-burger" type="button"
            aria-expanded="false" aria-controls="nav-menu" aria-label="Abrir menú del panel">
      <span></span><span></span><span></span>
    </button>
  </div>

  <span class="admin-tag">Panel de control</span>

  <nav class="admin-nav" id="nav-menu" aria-label="Navegación del panel">
    <?php foreach ($enlaces as [$clave, $url, $texto, $icono]): ?>
    <a href="<?= htmlspecialchars($url) ?>"
       class="<?= $activeAdmin === $clave ? 'is-activo' : '' ?>"
       <?= $activeAdmin === $clave ? 'aria-current="page"' : '' ?>>
      <i class="ph <?= htmlspecialchars($icono) ?>" aria-hidden="true"></i> <?= htmlspecialchars($texto) ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <a href="../landing.php" class="admin-volver">
    <i class="ph ph-arrow-left" aria-hidden="true"></i> Volver al sitio
  </a>
</aside>
