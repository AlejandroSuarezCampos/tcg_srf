<?php
/**
 * PERFIL PÚBLICO DE OTRO JUGADOR — `usuario.php?u=ID`
 *
 * La vitrina de otra persona: sus mejores cartas, lo último que ha sacado y su
 * colección por rareza. Se llega desde el nombre del vendedor en el mercado,
 * que es donde de verdad da curiosidad saber quién es el que vende.
 *
 * ⚠️ LO QUE NO SE ENSEÑA, Y POR QUÉ.
 *    · Las MONEDAS. El saldo ajeno no es asunto de nadie, y enseñarlo convierte
 *      la lista de jugadores en una lista de objetivos.
 *    · Nada de la tabla `usuarios` que no venga de `perfilPublico()`, que trae
 *      una lista de columnas escrita a mano. `obtenerUsuario()` hace `SELECT *`
 *      y ahí está `password_hash`: sirve para tu propia sesión, no para una
 *      pantalla que ve otro.
 *    · Las cartas PROTEGIDAS como tales. Poner el candado a una carta dice qué
 *      no piensas vender, y eso es información de mercado tuya. Se enseñan las
 *      mejores, calculadas por rareza y estadísticas.
 *
 * Tu propio perfil sigue en `perfil.php`, con lo tuyo entero.
 *
 * No estrena ni una clase de CSS: reutiliza `.perfil-cabecera`, `.cabecera-datos`,
 * `.panel` y `.carta-grid`, que ya existen y ya están probadas en móvil.
 */
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/partials/cabecera.php';
require_once __DIR__ . '/components/carta.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_visitante = (int) $_SESSION['id_usuario'];
$id_perfil    = (int) ($_GET['u'] ?? 0);

// Mirarse a uno mismo aquí sería una versión recortada de la pantalla que ya
// tienes: se redirige a la buena en vez de enseñar media.
if ($id_perfil === $id_visitante) {
    header('Location: perfil.php');
    exit;
}

$perfil = $id_perfil > 0 ? $db->perfilPublico($id_perfil) : null;

if (!$perfil) {
    http_response_code(404);
    $paginaTitulo = 'Jugador no encontrado';
    include __DIR__ . '/partials/head.php';
    $activePage = '';
    include __DIR__ . '/navbar.php';
    ?>
    <main class="wrap seccion">
      <div class="vacio">
        <i class="ph ph-user vacio-ico" aria-hidden="true"></i>
        <h3>Ese jugador no existe</h3>
        <p>Puede que se haya dado de baja, o que el enlace esté mal.</p>
        <a class="btn btn-primary" href="mercado.php">Ir al mercado</a>
      </div>
    </main>
    <?php
    include __DIR__ . '/partials/footer.php';
    exit;
}

$totalCartas          = $db->contarColeccionUsuario($id_perfil);
$expansionesCompletas = $db->contarExpansionesCompletas($id_perfil);
$destacadas           = $db->destacadasUsuario($id_perfil, 6);
$recientes            = $db->listarColeccionRecienteUsuario($id_perfil, 6);
$porRareza            = $db->resumenRarezasUsuario($id_perfil);

// Solo se pinta la <img> si el archivo existe de verdad: si no, las iniciales,
// que es lo mismo que hace el resto del sitio.
$fotoWeb   = $perfil['foto'] ?? '';
$fotoDisco = $fotoWeb !== '' ? __DIR__ . '/' . ltrim($fotoWeb, './') : '';
$tieneFoto = $fotoWeb !== '' && is_file($fotoDisco);
$iniciales = mb_strtoupper(mb_substr($perfil['nombre'], 0, 2));

$paginaTitulo = $perfil['nombre'];
$paginaDesc   = 'La colección de ' . $perfil['nombre'] . ' en la Superliga Frontier.';
include __DIR__ . '/partials/head.php';

$activePage = '';
include __DIR__ . '/navbar.php';
?>

<?php
$avatarHtml = '<span class="avatar avatar--lg">'
  . ($tieneFoto ? '<img src="' . htmlspecialchars($fotoWeb) . '" alt="">' : htmlspecialchars($iniciales))
  . '</span>';

$datosPerfil = [
  [number_format($totalCartas, 0, ',', '.'), 'fichas distintas'],
  [(int) $expansionesCompletas, 'expansiones completas'],
];
foreach (array_slice($porRareza, 0, 2) as $r) {
  $datosPerfil[] = [(int) $r['distintas'], $r['nombre']];
}

cabecera([
  'rotulo'   => 'Jugador de la liga',
  'titulo'   => $perfil['nombre'],
  'texto'    => 'En la liga desde el ' . date('d/m/Y', strtotime($perfil['fecha_registro'])) . '.',
  'avatar'   => $avatarHtml,
  'pastilla' => !empty($perfil['dictador']) ? 'Administración' : '',
  'datos'    => $datosPerfil,
]);
?>

<main class="wrap seccion stack stack-6">

  <?php if ($destacadas): ?>
    <section class="panel">
      <div class="panel-head">
        <h2 class="panel-titulo">Destacadas</h2>
        <p class="t-caption t-dim">Lo mejor que tiene, por rareza y estadísticas.</p>
      </div>
      <div class="carta-grid">
        <?php foreach ($destacadas as $c): ?>
          <?php render_carta($c); ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($recientes): ?>
    <section class="panel">
      <div class="panel-head">
        <h2 class="panel-titulo">Lo último que ha sacado</h2>
      </div>
      <div class="carta-grid">
        <?php foreach ($recientes as $c): ?>
          <?php render_carta($c); ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($porRareza): ?>
    <section class="panel">
      <div class="panel-head">
        <h2 class="panel-titulo">Su colección por rareza</h2>
        <p class="t-caption t-dim">Cromos distintos, sin contar repetidas.</p>
      </div>
      <div class="cabecera-datos">
        <?php foreach ($porRareza as $r): ?>
          <div class="dato">
            <b><?= (int) $r['distintas'] ?></b><span><?= htmlspecialchars($r['nombre']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!$destacadas && !$recientes): ?>
    <div class="vacio">
      <i class="ph ph-cards vacio-ico" aria-hidden="true"></i>
      <h3>Todavía no tiene cartas</h3>
      <p>Cuando abra su primer sobre, aparecerán aquí.</p>
    </div>
  <?php endif; ?>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
