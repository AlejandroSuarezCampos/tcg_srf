<?php
require_once __DIR__ . '/../db/conexion.php';

$totalCromos      = count($db->listarCromosAdmin());
$totalExpansiones = count($db->listarExpansiones());
$totalUsuarios    = count($db->listarUsuarios());
$totalSobres      = count($db->listarSobresAdmin());

$expansionesActivas = count(array_filter($db->listarExpansiones(), fn($e) => (int) $e['activo'] === 1));
$sobresActivos      = count(array_filter($db->listarSobresAdmin(), fn($s) => (int) $s['activo'] === 1));
$dictadores         = count(array_filter($db->listarUsuarios(), fn($u) => (int) $u['dictador'] === 1));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel de control — Superliga Frontier TCG</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="./assets/css/admin.css">
<link rel="icon" type="image/png" href="../assets/img/iconos/favicon.ico">
</head>
<body>

<div class="admin-shell">
  <?php $activeAdmin = 'inicio'; include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main">
    <div class="admin-head">
      <div>
        <h1>Panel de control</h1>
        <p>Gestiona cromos, sobres, expansiones y usuarios desde un único sitio.</p>
      </div>
    </div>

    <div class="dashboard-grid">
      <a href="cromos.php" class="dashboard-card">
        <div class="dashboard-card-top">
          <span class="dashboard-ico">🃏</span>
          <span class="dashboard-arrow"><i class="bi bi-arrow-up-right"></i></span>
        </div>
        <b class="dashboard-number"><?= $totalCromos ?></b>
        <span class="dashboard-label">Cromos totales</span>
      </a>

      <a href="sobres.php" class="dashboard-card">
        <div class="dashboard-card-top">
          <span class="dashboard-ico">📦</span>
          <span class="dashboard-arrow"><i class="bi bi-arrow-up-right"></i></span>
        </div>
        <b class="dashboard-number"><?= $totalSobres ?></b>
        <span class="dashboard-label">Sobres <?= $sobresActivos ?> activos de <?= $totalSobres ?></span>
      </a>

      <a href="expansiones.php" class="dashboard-card">
        <div class="dashboard-card-top">
          <span class="dashboard-ico">🗂️</span>
          <span class="dashboard-arrow"><i class="bi bi-arrow-up-right"></i></span>
        </div>
        <b class="dashboard-number"><?= $totalExpansiones ?></b>
        <span class="dashboard-label">Expansiones <?= $expansionesActivas ?> activas de <?= $totalExpansiones ?></span>
      </a>

      <a href="usuarios.php" class="dashboard-card">
        <div class="dashboard-card-top">
          <span class="dashboard-ico">👤</span>
          <span class="dashboard-arrow"><i class="bi bi-arrow-up-right"></i></span>
        </div>
        <b class="dashboard-number"><?= $totalUsuarios ?></b>
        <span class="dashboard-label">Usuarios <?= $dictadores ?> dictadores</span>
      </a>
    </div>

    <div class="admin-head" style="margin-top:50px;">
      <div>
        <h1 style="font-size:22px;">Accesos rápidos</h1>
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

</body>
</html>