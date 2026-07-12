<?php
require_once __DIR__ . '.\db\conexion.php';

/**
 * Maqueta visual con datos de ejemplo. Cuando tengas tus métodos en Tcg
 * (consultas.php), sustituye estos arrays por tus llamadas reales, p. ej.:
 *   $usuario           = $db->obtenerUsuario($id_usuario);
 *   $coleccionReciente = $db->listarColeccionReciente($id_usuario);
 *   $bloqueadas        = $db->listarColeccionBloqueada($id_usuario);
 *   $historialMercado  = $db->listarHistorialMercado($id_usuario);
 */
$usuario = [
    'nombre'         => 'KazeStorm_7',
    'monedas'        => 1240,
    'dictador'       => false,
    'fecha_registro' => '12/03/2025',
];

$totalCartas   = 86;
$totalBloqueadas = 12;
$expansionesCompletas = 2;

$coleccionReciente = [
    ['nombre' => 'Mark Slate',    'equipo' => 'Raimon FC',  'rareza' => 'Legendaria', 'clave' => 'r-legend'],
    ['nombre' => 'Axel Blaze',    'equipo' => 'Zeus FC',    'rareza' => 'Épica',      'clave' => 'r-epic'],
    ['nombre' => 'Jude Sharp',    'equipo' => 'Zeus FC',    'rareza' => 'Rara',       'clave' => 'r-rare'],
    ['nombre' => 'Shawn Frost',   'equipo' => 'Diamond Dust','rareza' => 'Épica',     'clave' => 'r-epic'],
];

$bloqueadas = [
    ['nombre' => 'Mark Slate',   'equipo' => 'Raimon FC', 'rareza' => 'Legendaria', 'clave' => 'r-legend'],
    ['nombre' => 'Xavier Foster','equipo' => 'Big Waves', 'rareza' => 'Épica',      'clave' => 'r-epic'],
];

$historialMercado = [
    ['fecha' => '02 Jul 2026', 'carta' => 'Jude Sharp',  'accion' => 'Venta',  'precio' => 320],
    ['fecha' => '27 Jun 2026', 'carta' => 'Shawn Frost', 'accion' => 'Compra', 'precio' => 540],
    ['fecha' => '19 Jun 2026', 'carta' => 'Axel Blaze',  'accion' => 'Venta',  'precio' => 410],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi perfil — Inazuma TCG</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav id="nav">
  <div class="logo">INAZUMA<span>·</span>TCG</div>
  <div class="nav-links">
    <a href="landing.php">Inicio</a>
    <a href="coleccion.php">Colección</a>
    <a href="mercado.php">Mercado</a>
    <a href="perfil.php" class="active">Perfil</a>
  </div>
  <div class="user-chip">
    <div class="avatar-sm"><?= strtoupper(substr($usuario['nombre'], 0, 2)) ?></div>
    <span class="coins"><?= number_format($usuario['monedas']) ?></span>
  </div>
</nav>

<div class="page-banner">
  <div class="hero-grid"></div>
  <div class="page-banner-content">
    <div class="profile-header">
      <div class="avatar-lg"><?= strtoupper(substr($usuario['nombre'], 0, 2)) ?></div>
      <div>
        <div class="profile-name-row">
          <h1><?= htmlspecialchars($usuario['nombre']) ?></h1>
          <?php if ($usuario['dictador']): ?><span class="badge-role">Dictador</span><?php endif; ?>
        </div>
        <p class="profile-meta">Miembro desde <?= htmlspecialchars($usuario['fecha_registro']) ?></p>
      </div>
    </div>

    <div class="stat-block-row">
      <div class="stat-block"><b><?= $totalCartas ?></b><span>Cartas en colección</span></div>
      <div class="stat-block"><b><?= $totalBloqueadas ?></b><span>Cartas bloqueadas</span></div>
      <div class="stat-block"><b><?= $expansionesCompletas ?></b><span>Expansiones completas</span></div>
      <div class="stat-block"><b><?= number_format($usuario['monedas']) ?></b><span>Monedas</span></div>
    </div>
  </div>
</div>

<section style="padding-top:70px;">
  <div class="tabs-bar">
    <button class="tab-btn active" data-target="tp-coleccion">Colección reciente</button>
    <button class="tab-btn" data-target="tp-bloqueadas">Bloqueadas</button>
    <button class="tab-btn" data-target="tp-historial">Historial de mercado</button>
    <button class="tab-btn" data-target="tp-logros">Logros</button>
  </div>

  <div class="tab-panel active" id="tp-coleccion">
    <div class="card-grid">
      <?php foreach ($coleccionReciente as $c): ?>
      <div class="tcard">
        <div class="tcard-inner">
          <span class="rarity <?= $c['clave'] ?>"><?= htmlspecialchars($c['rareza']) ?></span>
          <div class="portrait"><?= strtoupper(substr($c['nombre'],0,2)) ?></div>
          <h3><?= htmlspecialchars($c['nombre']) ?></h3>
          <div class="meta-row"><span><?= htmlspecialchars($c['equipo']) ?></span></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="tab-panel" id="tp-bloqueadas">
    <div class="card-grid">
      <?php foreach ($bloqueadas as $c): ?>
      <div class="tcard">
        <div class="tcard-inner" style="position:relative;">
          <span class="lock-badge">🔒</span>
          <span class="rarity <?= $c['clave'] ?>"><?= htmlspecialchars($c['rareza']) ?></span>
          <div class="portrait"><?= strtoupper(substr($c['nombre'],0,2)) ?></div>
          <h3><?= htmlspecialchars($c['nombre']) ?></h3>
          <div class="meta-row"><span><?= htmlspecialchars($c['equipo']) ?></span></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="tab-panel" id="tp-historial">
    <div class="history-table-wrap">
      <table>
        <thead><tr><th>Fecha</th><th>Carta</th><th>Acción</th><th>Precio</th></tr></thead>
        <tbody>
          <?php foreach ($historialMercado as $h): ?>
          <tr>
            <td><?= htmlspecialchars($h['fecha']) ?></td>
            <td><?= htmlspecialchars($h['carta']) ?></td>
            <td><?= htmlspecialchars($h['accion']) ?></td>
            <td><?= number_format($h['precio']) ?> ⛁</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-panel" id="tp-logros">
    <div class="empty-state">
      <div class="hex el-earth" style="margin:0 auto 18px;">🏆</div>
      <h3>Los logros llegan pronto</h3>
      <p>Estamos preparando el sistema de logros. Vuelve más adelante para desbloquear tus primeras insignias.</p>
    </div>
  </div>
</section>

<script src="./assets/js/scriptPerfil.js"></script>

</body>
</html>
