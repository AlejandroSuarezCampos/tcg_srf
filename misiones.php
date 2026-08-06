<?php
session_start();
require_once __DIR__ . '/db/conexion.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = $_SESSION['id_usuario'];

$mensaje = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reclamar') {
    $res = $db->reclamarMision((int) $_POST['id_mision'], $id_usuario);
    if ($res['ok']) {
        header('Location: misiones.php?reclamada=' . $res['recompensa']);
        exit;
    }
    $error = $res['error'];
}

if (isset($_GET['reclamada'])) {
    $mensaje = 'Misión reclamada: +' . number_format((int) $_GET['reclamada'], 0, ',', '.') . ' monedas.';
}

$misiones = $db->listarMisionesConProgreso($id_usuario);

$paginaTitulo = 'Misiones';
$paginaDesc   = 'Objetivos de la Superliga Frontier y su recompensa en monedas.';
include __DIR__ . '/partials/head.php';

$activePage = 'misiones';
include __DIR__ . '/navbar.php';
?>

<header class="cabecera">
  <div class="linea-campo" aria-hidden="true"></div>
  <div class="wrap cabecera-contenido">
    <h1>Misiones</h1>
    <p>Objetivos de una sola vez. Reclama la recompensa en cuanto los cumplas.</p>
  </div>
</header>

<main id="contenido" class="seccion wrap">

  <?php if ($mensaje !== ''): ?>
    <p class="alerta alerta-success" role="status"><?= htmlspecialchars($mensaje) ?></p>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <p class="alerta alerta-danger" role="alert"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <?php if (empty($misiones)): ?>
    <div class="vacio">
      <span class="vacio-ico"><i class="ph ph-target" aria-hidden="true"></i></span>
      <h3>No hay misiones activas</h3>
      <p>Vuelve más adelante.</p>
    </div>
  <?php else: ?>
    <div class="stack stack-4">
      <?php foreach ($misiones as $m): ?>
        <?php
        $pct = $m['objetivo'] > 0 ? min(100, (int) round($m['progreso'] / $m['objetivo'] * 100)) : 100;
        ?>
        <section class="panel">
          <div class="panel-head">
            <h2 class="panel-titulo"><?= htmlspecialchars($m['nombre']) ?></h2>
            <?php if ($m['reclamada']): ?>
              <span class="pastilla pastilla-on">Reclamada</span>
            <?php elseif ($m['completada']): ?>
              <span class="pastilla pastilla-warn">Completada</span>
            <?php endif; ?>
          </div>

          <p class="t-body-sm t-dim" style="margin-bottom:var(--space-4);">
            <?= htmlspecialchars($m['descripcion']) ?>
          </p>

          <div class="progreso" style="margin-bottom:var(--space-4);">
            <div class="progreso-riel">
              <div class="progreso-relleno" style="width:<?= $pct ?>%"></div>
            </div>
            <span class="progreso-label"><?= (int) $m['progreso'] ?> / <?= (int) $m['objetivo'] ?></span>
          </div>

          <div style="display:flex; align-items:center; justify-content:space-between;">
            <span class="mono"><?= number_format((int) $m['recompensa_monedas'], 0, ',', '.') ?> monedas</span>

            <?php if ($m['reclamada']): ?>
              <button type="button" class="btn btn-plano btn-sm" disabled>Ya reclamada</button>
            <?php elseif ($m['completada']): ?>
              <form method="POST">
                <input type="hidden" name="accion" value="reclamar">
                <input type="hidden" name="id_mision" value="<?= $m['id_mision'] ?>">
                <button type="submit" class="btn btn-primary btn-sm">Reclamar</button>
              </form>
            <?php else: ?>
              <button type="button" class="btn btn-plano btn-sm" disabled>Todavía no</button>
            <?php endif; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

</body>
</html>
