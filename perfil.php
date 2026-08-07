<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = $_SESSION['id_usuario'];

$usuario = $db->obtenerUsuario($id_usuario);
if (!$usuario) {
    // Sesión de un usuario que ya no existe en la BD (p. ej. borrado desde el panel)
    header('Location: logout.php');
    exit;
}

$totalCartas          = $db->contarColeccionUsuario($id_usuario);
$totalBloqueadas      = $db->contarBloqueadasUsuario($id_usuario);
$expansionesCompletas = $db->contarExpansionesCompletas($id_usuario);

$coleccionReciente = $db->listarColeccionRecienteUsuario($id_usuario, 8);
$bloqueadas        = $db->listarBloqueadasUsuario($id_usuario);
$anunciosUsuario   = $db->listarAnunciosUsuario($id_usuario);

// Solo mostramos la <img> si el archivo existe de verdad en disco; si no,
// caemos de vuelta a las iniciales para no romper el layout con un icono roto.
$fotoWeb   = $usuario['foto'] ?? '';
$fotoDisco = $fotoWeb !== '' ? __DIR__ . '/' . ltrim($fotoWeb, './') : '';
$tieneFoto = $fotoWeb !== '' && is_file($fotoDisco);
$iniciales = mb_strtoupper(mb_substr($usuario['nombre'], 0, 2));

$paginaTitulo = 'Tu perfil';
$paginaDesc   = 'Tu colección, tus anuncios y tu progreso en la Superliga Frontier.';
include __DIR__ . '/partials/head.php';

$activePage   = 'perfil';
$navIniciales = $iniciales;
$navMonedas   = $usuario['monedas'];
include __DIR__ . '/navbar.php';
?>

<header class="cabecera">
  <div class="linea-campo" aria-hidden="true"></div>
  <div class="wrap cabecera-contenido">

    <div class="perfil-cabecera">
      <span class="avatar avatar--lg">
        <?php if ($tieneFoto): ?>
          <img src="<?= htmlspecialchars($fotoWeb) ?>" alt="">
        <?php else: ?>
          <?= htmlspecialchars($iniciales) ?>
        <?php endif; ?>
      </span>

      <div class="stack stack-2">
        <div class="fila">
          <h1><?= htmlspecialchars($usuario['nombre']) ?></h1>
          <?php if ($usuario['dictador']): ?>
            <span class="pastilla pastilla-warn">Administración</span>
          <?php endif; ?>
        </div>
        <p class="t-caption t-dim">
          Miembro desde <span class="mono"><?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?></span>
        </p>
        <div class="fila" style="margin-top:var(--space-2);">
          <a class="btn btn-ghost btn-sm" href="configuracion.php">
            <i class="ph ph-gear-six" aria-hidden="true"></i> Configuración
          </a>
        </div>
      </div>
    </div>

    <div class="cabecera-datos">
      <div class="dato"><b><?= $totalCartas ?></b><span>Cartas en colección</span></div>
      <div class="dato"><b><?= $totalBloqueadas ?></b><span>Cartas protegidas</span></div>
      <div class="dato"><b><?= $expansionesCompletas ?></b><span>Expansiones completas</span></div>
      <div class="dato"><b><?= number_format($usuario['monedas'], 0, ',', '.') ?></b><span>Monedas</span></div>
    </div>

  </div>
</header>

<main id="contenido" class="seccion wrap">

  <div class="tabs" role="tablist" aria-label="Secciones del perfil">
    <button class="tab" role="tab" id="tab-reciente" aria-controls="panel-reciente" aria-selected="true">Reciente</button>
    <button class="tab" role="tab" id="tab-protegidas" aria-controls="panel-protegidas" aria-selected="false">Protegidas</button>
    <button class="tab" role="tab" id="tab-anuncios" aria-controls="panel-anuncios" aria-selected="false">Tus anuncios</button>
    <button class="tab" role="tab" id="tab-codigos" aria-controls="panel-codigos" aria-selected="false">Códigos</button>
  </div>

  <div class="tab-panel" role="tabpanel" id="panel-reciente" aria-labelledby="tab-reciente" tabindex="0"
       style="padding-top:var(--space-6);">
    <?php if (empty($coleccionReciente)): ?>
      <div class="vacio">
        <span class="vacio-ico"><i class="ph ph-cards" aria-hidden="true"></i></span>
        <h3>Todavía no tienes cartas</h3>
        <p>Abre tu primer sobre y aparecerán aquí, empezando por la más reciente.</p>
        <a class="btn btn-primary" href="sobres.php">Ir a sobres</a>
      </div>
    <?php else: ?>
      <div class="carta-grid">
        <?php foreach ($coleccionReciente as $c): ?>
          <?php render_carta($c, ['protegida' => !empty($c['bloqueada'])]); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="tab-panel" role="tabpanel" id="panel-protegidas" aria-labelledby="tab-protegidas" tabindex="0"
       style="padding-top:var(--space-6);" hidden>
    <?php if (empty($bloqueadas)): ?>
      <div class="vacio">
        <span class="vacio-ico"><i class="ph ph-lock-simple-open" aria-hidden="true"></i></span>
        <h3>No tienes cartas protegidas</h3>
        <p>Protege una carta desde tu colección para no ponerla a la venta por error.</p>
        <a class="btn btn-ghost" href="coleccion.php">Ir a la colección</a>
      </div>
    <?php else: ?>
      <div class="carta-grid">
        <?php foreach ($bloqueadas as $c): ?>
          <?php render_carta($c, ['protegida' => true]); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="tab-panel" role="tabpanel" id="panel-anuncios" aria-labelledby="tab-anuncios" tabindex="0"
       style="padding-top:var(--space-6);" hidden>
    <?php if (empty($anunciosUsuario)): ?>
      <div class="vacio">
        <span class="vacio-ico"><i class="ph ph-tag" aria-hidden="true"></i></span>
        <h3>Todavía no has puesto nada a la venta</h3>
        <p>Publica tu primer anuncio desde el mercado.</p>
        <a class="btn btn-primary" href="mercado.php">Ir al mercado</a>
      </div>
    <?php else: ?>
      <div class="tabla-wrap">
        <table class="tabla">
          <thead>
            <tr>
              <th scope="col">Carta</th>
              <th scope="col" class="num">Precio</th>
              <th scope="col">Publicado</th>
              <th scope="col">Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($anunciosUsuario as $a): ?>
            <tr>
              <td><?= htmlspecialchars($a['carta']) ?></td>
              <td class="num"><?= number_format($a['precio'], 0, ',', '.') ?></td>
              <td class="mono t-dim"><?= date('d/m/Y', strtotime($a['fecha_publicacion'])) ?></td>
              <td>
                <?php if ($a['activa']): ?>
                  <span class="pastilla pastilla-on">En venta</span>
                <?php else: ?>
                  <span class="pastilla">Cerrado</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="tab-panel" role="tabpanel" id="panel-codigos" aria-labelledby="tab-codigos" tabindex="0"
       style="padding-top:var(--space-6);" hidden>
    <div class="panel" style="max-width:520px;">
      <div class="panel-head">
        <div>
          <h2 class="panel-titulo">Canjear un código</h2>
          <p class="t-body-sm t-dim" style="margin-top:var(--space-2);">
            Introduce un código de evento para recibir su recompensa.
          </p>
        </div>
      </div>

      <form id="formCodigo" class="fila" style="align-items:flex-end;">
        <div class="campo" style="flex:1; min-width:200px;">
          <label for="inputCodigo">Código</label>
          <input type="text" id="inputCodigo" name="codigo" maxlength="50" required
                 placeholder="BIENVENIDA2026" style="text-transform:uppercase;">
        </div>
        <button type="submit" class="btn btn-primary">Canjear</button>
      </form>

      <p id="codigoFeedback" class="campo-hint" role="status" aria-live="polite"
         style="margin-top:var(--space-3);"></p>
    </div>
  </div>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

<?= assetScript($base ?? '', 'assets/js/perfil.js') ?>

</body>
</html>
