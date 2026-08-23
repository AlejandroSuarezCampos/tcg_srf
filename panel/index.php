<?php
session_start();
require_once __DIR__ . '/../db/conexion.php';

if (isset($_SESSION['dictador'])) {
    if ($_SESSION['dictador'] != 1) {
        header('Location: ../landing.php');
        exit;
    }
} else {
    header('Location: ../landing.php');
    exit;
}

$totalCromos      = count($db->listarCromosAdmin());
$totalExpansiones = count($db->listarExpansiones());
$totalUsuarios    = count($db->listarUsuarios());
$totalSobres      = count($db->listarSobresAdmin());

$expansionesActivas = count(array_filter($db->listarExpansiones(), fn($e) => (int) $e['activo'] === 1));
$sobresActivos      = count(array_filter($db->listarSobresAdmin(), fn($s) => (int) $s['activo'] === 1));
$dictadores         = count(array_filter($db->listarUsuarios(), fn($u) => (int) $u['dictador'] === 1));

$base         = '../';
$paginaTitulo = 'Panel de control';
$paginaDesc   = 'Gestión de cromos, sobres, expansiones y usuarios.';
$cssExtra     = ['panel/assets/css/admin.css'];
include __DIR__ . '/../partials/head.php';

$activeAdmin = 'inicio';
?>

<div class="admin-shell">
  <?php include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main" id="contenido">
    <div class="admin-head">
      <div>
        <h1>Panel de control</h1>
        <p>Gestiona cromos, sobres, expansiones y usuarios desde un único sitio.</p>
      </div>
    </div>

    <div class="dashboard-grid">
      <a href="cromos.php" class="dashboard-card">
        <div class="dashboard-card-top">
          <span class="dashboard-ico"><i class="ph ph-cards" aria-hidden="true"></i></span>
          <span class="dashboard-arrow"><i class="ph ph-arrow-up-right" aria-hidden="true"></i></span>
        </div>
        <b class="dashboard-number mono"><?= $totalCromos ?></b>
        <span class="dashboard-label">Cromos totales</span>
      </a>

      <a href="sobres.php" class="dashboard-card">
        <div class="dashboard-card-top">
          <span class="dashboard-ico"><i class="ph ph-package" aria-hidden="true"></i></span>
          <span class="dashboard-arrow"><i class="ph ph-arrow-up-right" aria-hidden="true"></i></span>
        </div>
        <b class="dashboard-number mono"><?= $totalSobres ?></b>
        <span class="dashboard-label">Sobres · <?= $sobresActivos ?> activos de <?= $totalSobres ?></span>
      </a>

      <a href="expansiones.php" class="dashboard-card">
        <div class="dashboard-card-top">
          <span class="dashboard-ico"><i class="ph ph-folder-open" aria-hidden="true"></i></span>
          <span class="dashboard-arrow"><i class="ph ph-arrow-up-right" aria-hidden="true"></i></span>
        </div>
        <b class="dashboard-number mono"><?= $totalExpansiones ?></b>
        <span class="dashboard-label">Expansiones · <?= $expansionesActivas ?> activas de <?= $totalExpansiones ?></span>
      </a>

      <a href="usuarios.php" class="dashboard-card">
        <div class="dashboard-card-top">
          <span class="dashboard-ico"><i class="ph ph-users" aria-hidden="true"></i></span>
          <span class="dashboard-arrow"><i class="ph ph-arrow-up-right" aria-hidden="true"></i></span>
        </div>
        <b class="dashboard-number mono"><?= $totalUsuarios ?></b>
        <span class="dashboard-label">Usuarios · <?= $dictadores ?> dictadores</span>
      </a>
    </div>

    <div class="admin-head admin-section-gap">
      <div>
        <h1 class="admin-subhead">Accesos rápidos</h1>
      </div>
    </div>

    <div class="quick-actions">
      <a href="cromos.php" class="btn btn-ghost">+ Nuevo cromo</a>
      <a href="sobres.php" class="btn btn-ghost">+ Nuevo sobre</a>
      <a href="expansiones.php" class="btn btn-ghost">+ Nueva expansión</a>
      <a href="usuarios.php" class="btn btn-ghost">+ Nuevo usuario</a>
    </div>
  </main>
</div>

<?php $pieCompleto = false; include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
