<?php
/**
 * Sidebar compartida por las páginas del panel de administración
 * (admin-cromos, admin-expansiones, admin-usuarios).
 *
 * Antes de incluir este archivo, cada página define:
 *   $activeAdmin -> 'cromos' | 'expansiones' | 'usuarios'
 *
 * Ejemplo de uso:
 *   <?php $activeAdmin = 'cromos'; include __DIR__ . '/navbar.php'; ?>
 */
$activeAdmin = $activeAdmin ?? '';
?>
<aside class="admin-sidebar">
  <input type="checkbox" id="adminNavToggle" class="nav-toggle-input">

  <div class="admin-sidebar-top">
    <div class="logo">SUPERLIGA FRONTIER<span>·</span>TCG</div>
    <label for="adminNavToggle" class="nav-burger" aria-label="Abrir menú">
      <span></span><span></span><span></span>
    </label>
  </div>

  <span class="admin-tag">Panel de control</span>

  <nav class="admin-nav">
    <a href="index.php" class="<?= $activeAdmin === 'inicio' ? 'active' : '' ?>">
      <span class="nav-ico">🏠</span> Inicio
    </a>
    <a href="cromos.php" class="<?= $activeAdmin === 'cromos' ? 'active' : '' ?>">
      <span class="nav-ico">🃏</span> Cromos
    </a>
    <a href="sobres.php" class="<?= $activeAdmin === 'sobres' ? 'active' : '' ?>">
      <span class="nav-ico">📦</span> Sobres
    </a>
    <a href="expansiones.php" class="<?= $activeAdmin === 'expansiones' ? 'active' : '' ?>">
      <span class="nav-ico">🗂️</span> Expansiones
    </a>
    <a href="usuarios.php" class="<?= $activeAdmin === 'usuarios' ? 'active' : '' ?>">
      <span class="nav-ico">👤</span> Usuarios
    </a>
  </nav>

  <a href="../landing.php" class="admin-back">&larr; Volver al sitio</a>
</aside>