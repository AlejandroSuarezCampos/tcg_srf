<?php
/**
 * Navbar compartida por todas las páginas con sesión iniciada
 * (perfil, colección, mercado, álbum...).
 *
 * Antes de incluir este archivo, cada página puede definir:
 *   $activePage    -> 'landing' | 'coleccion' | 'mercado' | 'album' | 'perfil'
 *   $navIniciales  -> iniciales para el avatar (por defecto 'KS')
 *   $navMonedas    -> monedas a mostrar (por defecto 1240)
 *
 * Ejemplo de uso en una página:
 *   <?php $activePage = 'coleccion'; include __DIR__ . '/navbar.php'; ?>
 */
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$activePage = $activePage ?? '';

// Si hay sesión iniciada, refrescamos el saldo real desde la BD (evita que
// las monedas se queden "congeladas" con el valor que había al hacer login)
if (!empty($_SESSION['id_usuario']) && isset($db)) {
	$usuarioNav = $db->obtenerUsuario($_SESSION['id_usuario']);
	if ($usuarioNav) {
		$_SESSION['monedas']  = $usuarioNav['monedas'];
		$_SESSION['dictador'] = (bool) $usuarioNav['dictador'];
		$_SESSION['foto']     = $usuarioNav['foto'];
	}
}

// Si hay sesión iniciada, usamos los datos reales del usuario logueado
if (!empty($_SESSION['id_usuario'])) {
	$palabras = preg_split('/[\s_]+/', trim($_SESSION['nombre'] ?? ''));
	$iniciales = '';
	foreach (array_slice($palabras, 0, 2) as $palabra) {
		$iniciales .= mb_strtoupper(mb_substr($palabra, 0, 1));
	}
	$navIniciales = $navIniciales ?? ($iniciales !== '' ? $iniciales : 'KS');
	$navMonedas   = $navMonedas   ?? ($_SESSION['monedas'] ?? 0);

	// Solo usamos la foto si existe realmente en disco (evita imágenes rotas
	// si se borró el archivo a mano o el registro quedó con una ruta rara)
	$navFotoWeb   = $_SESSION['foto'] ?? '';
	$navFotoDisco = $navFotoWeb !== '' ? __DIR__ . '/' . ltrim($navFotoWeb, './') : '';
	$navTieneFoto = $navFotoWeb !== '' && is_file($navFotoDisco);
}
?>
<nav id="nav">
  <div class="logo">SUPERLIGA FRONTIER<span>·</span>TCG</div>

  <input type="checkbox" id="navToggle" class="nav-toggle-input">

  <div class="nav-collapse">
    <div class="nav-links">
      <a href="landing.php" class="<?= $activePage === 'landing' ? 'active' : '' ?>">Inicio</a>
      <a href="sobres.php" class="<?= $activePage === 'sobres' ? 'active' : '' ?>">Sobres</a>
      <a href="coleccion.php" class="<?= $activePage === 'coleccion' ? 'active' : '' ?>">Colección</a>
      <a href="mercado.php" class="<?= $activePage === 'mercado' ? 'active' : '' ?>">Mercado</a>
      <a href="album.php" class="<?= $activePage === 'album' ? 'active' : '' ?>">Álbum</a>
      <a href="perfil.php" class="<?= $activePage === 'perfil' ? 'active' : '' ?>">Perfil</a>
    </div>
  </div>

  <div class="nav-right">
    <?php if (!empty($_SESSION['id_usuario'])): ?>
      <div class="user-chip">
        <div class="avatar-sm">
          <?php if ($navTieneFoto): ?>
            <img src="<?= htmlspecialchars($navFotoWeb) ?>" alt="">
          <?php else: ?>
            <?= htmlspecialchars($navIniciales) ?>
          <?php endif; ?>
        </div>
        <span class="coins" id="navCoins"><?= number_format($navMonedas) ?></span>
        <a href="logout.php" class="nav-logout" title="Cerrar sesión">⏻</a>
      </div>
    <?php else: ?>
      <a href="login.php" class="btn btn-ghost nav-login-btn">Entrar</a>
    <?php endif; ?>
    <label for="navToggle" class="nav-burger" aria-label="Abrir menú">
      <span></span><span></span><span></span>
    </label>
  </div>
</nav>

<script src="assets/async/js/scriptsAsync.js"></script>