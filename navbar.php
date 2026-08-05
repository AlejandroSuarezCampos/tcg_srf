<?php
/**
 * NAVEGACIÓN PRINCIPAL — compartida por todas las páginas del sitio.
 *
 * Los destinos se agrupan en tres clústeres (briefing, sección 34) para que la
 * barra siga siendo legible cuando entren Duelos y Misiones en la Fase 2:
 *   Jugar        → Sobres  (+ Duelos, Misiones)
 *   Coleccionar  → Colección, Álbum, Mercado
 *   Cuenta       → Perfil
 * Inicio queda fuera de los grupos, como ancla siempre visible.
 *
 * Antes de incluir este archivo, cada página puede definir:
 *   $activePage -> 'landing' | 'sobres' | 'coleccion' | 'mercado' | 'album' | 'perfil'
 *   $base       -> prefijo relativo a la raíz ('' o '../')
 *
 * Ejemplo:
 *   <?php $activePage = 'coleccion'; include __DIR__ . '/navbar.php'; ?>
 */
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$activePage = $activePage ?? '';
$base       = $base       ?? '';

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

$haySesion = !empty($_SESSION['id_usuario']);

if ($haySesion) {
	$palabras = preg_split('/[\s_]+/', trim($_SESSION['nombre'] ?? ''));
	$iniciales = '';
	foreach (array_slice($palabras, 0, 2) as $palabra) {
		$iniciales .= mb_strtoupper(mb_substr($palabra, 0, 1));
	}
	$navIniciales = $navIniciales ?? ($iniciales !== '' ? $iniciales : '??');
	$navMonedas   = $navMonedas   ?? ($_SESSION['monedas'] ?? 0);

	// Solo usamos la foto si existe realmente en disco (evita imágenes rotas
	// si se borró el archivo a mano o el registro quedó con una ruta rara)
	$navFotoWeb   = $_SESSION['foto'] ?? '';
	$navFotoDisco = $navFotoWeb !== '' ? __DIR__ . '/' . ltrim($navFotoWeb, './') : '';
	$navTieneFoto = $navFotoWeb !== '' && is_file($navFotoDisco);
}

/** Grupos de destinos. Añadir Duelos o Misiones es añadir una línea aquí. */
$navGrupos = [
	'Jugar' => [
		['sobres', 'sobres.php', 'Sobres', 'ph-package'],
		['mazos',  'mazos.php',  'Mazos',  'ph-list-checks'],
		['duelos', 'duelos.php', 'Duelos', 'ph-sword'],
	],
	'Coleccionar' => [
		['coleccion', 'coleccion.php', 'Colección', 'ph-cards'],
		['album',     'album.php',     'Álbum',     'ph-book-open'],
		['mercado',   'mercado.php',   'Mercado',   'ph-storefront'],
	],
];
?>
<header class="nav">
  <div class="nav-interior">

    <a class="logo" href="<?= $base ?>landing.php">
      Superliga Frontier<span class="logo-punto">·</span>TCG
    </a>

    <button class="nav-burger" type="button"
            aria-expanded="false" aria-controls="nav-menu" aria-label="Abrir menú de navegación">
      <span></span><span></span><span></span>
    </button>

    <nav class="nav-menu" id="nav-menu" aria-label="Navegación principal">
      <ul class="nav-lista">
        <li>
          <a href="<?= $base ?>landing.php" class="nav-enlace<?= $activePage === 'landing' ? ' is-activo' : '' ?>"
             <?= $activePage === 'landing' ? 'aria-current="page"' : '' ?>>Inicio</a>
        </li>

        <?php foreach ($navGrupos as $grupo => $destinos): ?>
          <li class="nav-sep" aria-hidden="true"></li>
          <li class="nav-grupo-titulo"><?= htmlspecialchars($grupo) ?></li>
          <?php foreach ($destinos as [$clave, $url, $texto, $icono]): ?>
          <li>
            <a href="<?= $base . $url ?>" class="nav-enlace<?= $activePage === $clave ? ' is-activo' : '' ?>"
               <?= $activePage === $clave ? 'aria-current="page"' : '' ?>>
              <i class="ph <?= $icono ?> nav-ico" aria-hidden="true"></i><?= $texto ?>
            </a>
          </li>
          <?php endforeach; ?>
        <?php endforeach; ?>

        <?php if ($haySesion): ?>
          <li class="nav-sep" aria-hidden="true"></li>
          <li class="nav-grupo-titulo">Cuenta</li>
          <li>
            <a href="<?= $base ?>perfil.php" class="nav-enlace<?= $activePage === 'perfil' ? ' is-activo' : '' ?>"
               <?= $activePage === 'perfil' ? 'aria-current="page"' : '' ?>>
              <i class="ph ph-user nav-ico" aria-hidden="true"></i>Perfil
            </a>
          </li>
          <?php if (!empty($_SESSION['dictador'])): ?>
          <li>
            <a href="<?= $base ?>panel/index.php" class="nav-enlace">
              <i class="ph ph-sliders nav-ico" aria-hidden="true"></i>Panel
            </a>
          </li>
          <?php endif; ?>
          <?php /* En móvil el chip se queda sin sitio para el icono de salir,
                   así que la salida vive aquí dentro. */ ?>
          <li class="solo-movil">
            <a href="<?= $base ?>logout.php" class="nav-enlace">
              <i class="ph ph-sign-out nav-ico" aria-hidden="true"></i>Cerrar sesión
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </nav>

    <div class="nav-derecha">
      <?php if ($haySesion): ?>
        <div class="chip-usuario">
          <span class="avatar">
            <?php if ($navTieneFoto): ?>
              <img src="<?= $base . htmlspecialchars($navFotoWeb) ?>" alt="">
            <?php else: ?>
              <?= htmlspecialchars($navIniciales) ?>
            <?php endif; ?>
          </span>
          <span class="monedas" id="navCoins">
            <i class="ph ph-coins" aria-hidden="true"></i>
            <span class="sr-only">Monedas:</span><?= number_format($navMonedas, 0, ',', '.') ?>
          </span>
          <a href="<?= $base ?>logout.php" class="nav-salir" title="Cerrar sesión">
            <i class="ph ph-sign-out" aria-hidden="true"></i>
            <span class="sr-only">Cerrar sesión</span>
          </a>
        </div>
      <?php else: ?>
        <a href="<?= $base ?>login.php" class="btn btn-ghost btn-sm">Entrar</a>
      <?php endif; ?>
    </div>

  </div>
</header>

<script src="<?= $base ?>assets/async/js/scriptsAsync.js"></script>
