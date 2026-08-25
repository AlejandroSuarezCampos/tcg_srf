<?php
/**
 * NAVEGACIÓN PRINCIPAL — sistema Ascua, bloque 2.
 *
 * Sustituye a la barra de once destinos en hamburguesa. Ahora son CINCO, y en
 * móvil viven abajo, al alcance del pulgar:
 *
 *   HOY · JUGAR · PLANTILLA · MERCADO · PERFIL
 *
 * «Jugar» se comporta distinto según el tamaño:
 *
 *   móvil       abre una HOJA desde abajo, sin recargar y bajo el pulgar
 *   escritorio  navega a `jugar.php`, el centro de mando de los cuatro modos
 *
 * En el bloque 5 se quitó la hoja argumentando que los toques eran los mismos
 * que con una pantalla intermedia. El recuento era correcto y el argumento no
 * venía al caso: en móvil una hoja NO RECARGA y cae donde está el dedo, y eso
 * se nota aunque sean dos toques igual. Vuelve, y con las cifras que le
 * faltaban.
 *
 * El enlace apunta a `jugar.php` de verdad: sin JavaScript, o en escritorio,
 * navega. La hoja es una mejora encima, no un requisito.
 *
 * ESTRUCTURA
 *   .barra    fija arriba. En móvil: logotipo y saldo. En escritorio: además
 *             los cinco destinos y el avatar — la barra inferior desaparece.
 *   .tabbar   fija abajo, SOLO en móvil.
 *
 * La hoja inferior genérica (`.hoja`, `iniciarHojas()` en ui.js) sigue viva:
 * la usan los filtros de `plantilla.php`.
 *
 * Antes de incluir este archivo, cada página puede definir:
 *   $activePage     -> clave de la página (ver $MAPA_TABS más abajo)
 *   $base           -> prefijo relativo a la raíz ('' o '../')
 *   $navPendientes  -> int|null, insignia sobre «Jugar». Lo calcula la página
 *                      que ya tiene ese dato a mano (hoy.php, jugar.php); el
 *                      resto no paga ninguna consulta extra por una cifra.
 *   $navEstado      -> array<clave,int>, cifras por modo para la hoja
 *                      (['sobres'=>1,'duelos'=>2,…]). Mismo criterio: solo la
 *                      pone quien ya tiene los datos.
 */
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$activePage    = $activePage    ?? '';
$base          = $base          ?? '';
$navPendientes = $navPendientes ?? null;
$navEstado     = $navEstado     ?? [];

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

/**
 * Los cinco destinos.
 *
 * `url` apunta HOY a la pantalla que existe; las tres marcadas migran en los
 * bloques 3–5 y entonces solo cambia esta cadena. Prefiero un destino viejo
 * explícito y comentado a inventar redirecciones que luego hay que perseguir.
 */
/** Lo que abre la hoja de «Jugar» en móvil. La clave `cifra` la rellena
 *  $navEstado si la pantalla lo trae; si no, la opción sale sin insignia. */
$navJugar = [
	['sobres',   'sobres.php',   'Sobres',       'ph-package',     'Ábrelos y mira qué cae'],
	['duelos',   'duelos.php',   'Duelos',       'ph-sword',       'Reta a alguien o entra en una sala'],
	['mazos',    'mazos.php',    'Alineaciones', 'ph-list-checks', 'Monta el once que vas a sacar'],
	['misiones', 'misiones.php', 'Objetivos',    'ph-target',      'Lo que te queda por cerrar'],
	['cadenas',  'cadenas.php',  'Cadenas',      'ph-path',        'Encadena partidos y sube de nodo'],
];

$navTabs = [
	['hoy',       'hoy.php',       'Hoy',       'ph-house'],
	['jugar',     'jugar.php',     'Jugar',     'ph-lightning'],
	['plantilla', 'plantilla.php', 'Plantilla', 'ph-cards'],
	['mercado',   'mercado.php',   'Mercado',   'ph-storefront'],
	['perfil',    'perfil.php',    'Perfil',    'ph-user'],
];

/**
 * Qué pestaña se ilumina en cada pantalla. Se mapea aquí y no en cada página
 * para no tener que tocar las diecisiete: siguen declarando el `$activePage`
 * que ya declaraban.
 */
$MAPA_TABS = [
	'hoy' => 'hoy', 'landing' => 'hoy',
	'jugar' => 'jugar',
	'sobres' => 'jugar', 'duelos' => 'jugar', 'duelo' => 'jugar', 'mazos' => 'jugar',
	'misiones' => 'jugar', 'cadenas' => 'jugar', 'cadena' => 'jugar',
	'plantilla' => 'plantilla', 'coleccion' => 'plantilla', 'album' => 'plantilla', 'descartar' => 'plantilla',
	'mercado' => 'mercado',
	'perfil' => 'perfil', 'configuracion' => 'perfil', 'usuario' => 'perfil',
];
$tabActiva = $MAPA_TABS[$activePage] ?? '';

/** Pinta un destino, sea de la barra superior o de la inferior. */
function nav_destino(array $tab, string $tabActiva, string $base, ?int $pendientes): void {
	[$clave, $url, $texto, $icono] = $tab;
	$activo = $tabActiva === $clave;
	?>
	<?php /* El de «Jugar» lleva href de verdad Y los ganchos de la hoja: en
	         escritorio y sin JavaScript navega; en móvil, ui.js lo intercepta. */ ?>
	<a class="nav-destino<?= $activo ? ' es-activo' : '' ?>"
	   href="<?= htmlspecialchars($base . $url) ?>"
	   <?= $clave === 'jugar' ? 'data-abre-hoja-movil="hoja-jugar" aria-controls="hoja-jugar"' : '' ?>
	   <?= $activo ? 'aria-current="page"' : '' ?>>
		<span class="nav-destino-ico">
			<i class="ph <?= $icono ?>" aria-hidden="true"></i>
			<?php /* La insignia va `aria-hidden` y su lectura se añade DESPUÉS del
			         rótulo: si no, el nombre accesible sale al revés («3 pendientes
			         Jugar») porque el orden lo marca el DOM. */ ?>
			<?php if ($clave === 'jugar' && $pendientes): ?>
				<span class="nav-insignia" aria-hidden="true"><?= (int) $pendientes ?></span>
			<?php endif; ?>
		</span>
		<span class="nav-destino-txt"><?= htmlspecialchars($texto) ?></span>
		<?php if ($clave === 'jugar' && $pendientes): ?>
			<span class="sr-only">, <?= (int) $pendientes ?> pendientes</span>
		<?php endif; ?>
	</a>
	<?php
}
?>
<header class="barra">
	<div class="barra-interior">

		<?php /* Con sesión el logotipo lleva a tu portada, no a la de captación:
		         `landing.php` ya solo existe para quien no ha entrado. */ ?>
		<a class="marca" href="<?= $base . ($haySesion ? 'hoy.php' : 'landing.php') ?>">
			<span class="marca-chispa" aria-hidden="true"></span>
			Frontier<span class="marca-tcg">TCG</span>
		</a>

		<?php /* Los cinco destinos solo se pintan aquí en escritorio; en móvil
		         esta lista está en display:none y manda la barra inferior. Va en
		         el marcado igualmente, sin duplicar: una sola fuente de verdad. */ ?>
		<?php if ($haySesion): ?>
		<nav class="barra-destinos" aria-label="Navegación principal">
			<?php foreach ($navTabs as $tab) nav_destino($tab, $tabActiva, $base, $navPendientes); ?>
		</nav>
		<?php endif; ?>

		<div class="barra-derecha">
			<?php if ($haySesion): ?>
				<span class="saldo" title="Tus monedas">
					<i class="ph-fill ph-coins" aria-hidden="true"></i>
					<span class="sr-only">Monedas: </span>
					<?php /* El id lo busca actualizarMonedasNav() en scriptsAsync.js para
					         repintar el saldo sin recargar tras comprar o vender. */ ?>
					<span class="saldo-cifra" id="navCoins"><?= number_format($navMonedas, 0, ',', '.') ?></span>
				</span>

				<?php if (!empty($_SESSION['dictador'])): ?>
					<a class="barra-ico" href="<?= $base ?>panel/index.php" title="Panel de administración">
						<i class="ph ph-sliders" aria-hidden="true"></i>
						<span class="sr-only">Panel de administración</span>
					</a>
				<?php endif; ?>

				<a class="barra-ico" href="<?= $base ?>logout.php" title="Cerrar sesión">
					<i class="ph ph-sign-out" aria-hidden="true"></i>
					<span class="sr-only">Cerrar sesión</span>
				</a>

				<a class="avatar-enlace" href="<?= $base ?>perfil.php" title="Tu perfil">
					<span class="avatar">
						<?php if ($navTieneFoto): ?>
							<img src="<?= $base . htmlspecialchars($navFotoWeb) ?>" alt="">
						<?php else: ?>
							<?= htmlspecialchars($navIniciales) ?>
						<?php endif; ?>
					</span>
					<span class="sr-only">Tu perfil</span>
				</a>
			<?php else: ?>
				<a class="btn btn-primary btn-sm" href="<?= $base ?>login.php">Entrar</a>
			<?php endif; ?>
		</div>

	</div>
</header>

<?php if ($haySesion): ?>
<?php /* HOJA DE «JUGAR» — solo se usa en móvil (ui.js la abre por debajo de
         1024px). En escritorio queda en el árbol sin molestar: el enlace
         navega antes de que nadie la abra. */ ?>
<div class="hoja-velo" data-cierra-hoja hidden></div>
<div class="hoja hoja--jugar" id="hoja-jugar" role="dialog" aria-modal="true"
     aria-labelledby="hoja-jugar-titulo" hidden>
	<div class="hoja-asa" aria-hidden="true"></div>

	<div class="hoja-jugar-cab">
		<h2 class="hoja-titulo" id="hoja-jugar-titulo">A qué juegas</h2>
		<a class="hoja-jugar-todo" href="<?= $base ?>jugar.php">
			Ver todo <i class="ph ph-arrow-right" aria-hidden="true"></i>
		</a>
	</div>

	<div class="hoja-lista">
		<?php foreach ($navJugar as [$clave, $url, $texto, $icono, $pie]): ?>
			<?php $cifra = (int) ($navEstado[$clave] ?? 0); ?>
			<a class="hoja-opcion<?= $activePage === $clave ? ' es-activo' : '' ?>"
			   href="<?= htmlspecialchars($base . $url) ?>"
			   <?= $activePage === $clave ? 'aria-current="page"' : '' ?>>
				<span class="hoja-opcion-ico"><i class="ph <?= $icono ?>" aria-hidden="true"></i></span>
				<span class="hoja-opcion-txt">
					<b><?= htmlspecialchars($texto) ?></b>
					<span><?= htmlspecialchars($pie) ?></span>
				</span>
				<?php /* La cifra solo aparece si la pantalla trajo el dato. Una
				         insignia que sale siempre —aunque sea un cero— deja de
				         significar «hay algo aquí». */ ?>
				<?php if ($cifra > 0): ?>
					<span class="hoja-opcion-cifra"><?= $cifra ?><span class="sr-only"> pendientes</span></span>
				<?php else: ?>
					<i class="ph ph-caret-right hoja-opcion-flecha" aria-hidden="true"></i>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>

	<button class="hoja-cerrar" type="button" data-cierra-hoja>Cerrar</button>
</div>
<?php endif; ?>

<?php if ($haySesion): ?>
<nav class="tabbar" aria-label="Navegación principal">
	<?php foreach ($navTabs as $tab) nav_destino($tab, $tabActiva, $base, $navPendientes); ?>
</nav>
<?php endif; ?>

<?= assetScript($base ?? '', 'assets/async/js/scriptsAsync.js') ?>
