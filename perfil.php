<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/rareza-clases.php';

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
$totalBloqueadas       = $db->contarBloqueadasUsuario($id_usuario);
$expansionesCompletas  = $db->contarExpansionesCompletas($id_usuario);

$coleccionReciente = $db->listarColeccionRecienteUsuario($id_usuario, 8);
$bloqueadas        = $db->listarBloqueadasUsuario($id_usuario);
$anunciosUsuario   = $db->listarAnunciosUsuario($id_usuario);

// Solo mostramos la <img> si el archivo existe de verdad en disco; si no,
// caemos de vuelta a las iniciales para no romper el layout con un icono roto.
$fotoWeb    = $usuario['foto'] ?? '';
$fotoDisco  = $fotoWeb !== '' ? __DIR__ . '/' . ltrim($fotoWeb, './') : '';
$tieneFoto  = $fotoWeb !== '' && is_file($fotoDisco);
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
<link rel="icon" type="image/png" href="assets/img/iconos/favicon.ico">
</head>
<body>

<?php
$activePage   = 'perfil';
$navIniciales = strtoupper(substr($usuario['nombre'], 0, 2));
$navMonedas   = $usuario['monedas'];
include __DIR__ . '/navbar.php';
?>

<div class="page-banner">
  <div class="hero-grid"></div>
  <div class="page-banner-content">
    <div class="profile-header">
      <div class="avatar-lg">
        <?php if ($tieneFoto): ?>
          <img src="<?= htmlspecialchars($fotoWeb) ?>" alt="Foto de perfil de <?= htmlspecialchars($usuario['nombre']) ?>">
        <?php else: ?>
          <?= strtoupper(substr($usuario['nombre'], 0, 2)) ?>
        <?php endif; ?>
      </div>
      <div>
        <div class="profile-name-row">
          <h1><?= htmlspecialchars($usuario['nombre']) ?></h1>
          <?php if ($usuario['dictador']): ?><span class="badge-role">Dictador</span><?php endif; ?>
        </div>
        <p class="profile-meta">Miembro desde <?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?></p>
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
    <button class="tab-btn" data-target="tp-historial">Tus anuncios</button>
    <button class="tab-btn" data-target="tp-logros">Logros</button>
  </div>

  <div class="tab-panel active" id="tp-coleccion">
    <?php if (empty($coleccionReciente)): ?>
    <div class="empty-state">
      <h3>Todavía no tienes cromos</h3>
      <p>Cuando consigas tu primera carta, aparecerá aquí.</p>
    </div>
    <?php else: ?>
    <div class="card-grid">
      <?php foreach ($coleccionReciente as $c): ?>
      <div class="tcard">
        <div class="tcard-inner">
          <span class="rarity <?= $claseRarezaPorId[$c['id_rareza']] ?? 'r-comun' ?>"><?= htmlspecialchars($c['rareza']) ?></span>
          <div class="portrait"><?= strtoupper(substr($c['nombre'],0,2)) ?></div>
          <h3><?= htmlspecialchars($c['nombre']) ?></h3>
          <div class="meta-row"><span><?= htmlspecialchars($c['equipo']) ?></span></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="tab-panel" id="tp-bloqueadas">
    <?php if (empty($bloqueadas)): ?>
    <div class="empty-state">
      <h3>No tienes cromos bloqueados</h3>
      <p>Puedes bloquear cartas desde tu colección para evitar venderlas por error.</p>
    </div>
    <?php else: ?>
    <div class="card-grid">
      <?php foreach ($bloqueadas as $c): ?>
      <div class="tcard">
        <div class="tcard-inner" style="position:relative;">
          <span class="lock-badge">🔒</span>
          <span class="rarity <?= $claseRarezaPorId[$c['id_rareza']] ?? 'r-comun' ?>"><?= htmlspecialchars($c['rareza']) ?></span>
          <div class="portrait"><?= strtoupper(substr($c['nombre'],0,2)) ?></div>
          <h3><?= htmlspecialchars($c['nombre']) ?></h3>
          <div class="meta-row"><span><?= htmlspecialchars($c['equipo']) ?></span></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="tab-panel" id="tp-historial">
    <?php if (empty($anunciosUsuario)): ?>
    <div class="empty-state">
      <h3>Todavía no has puesto nada a la venta</h3>
      <p>Ve a Mercado y pulsa "Vender una carta" para publicar tu primer anuncio.</p>
    </div>
    <?php else: ?>
    <div class="history-table-wrap">
      <table>
        <thead><tr><th>Carta</th><th>Precio</th><th>Publicado</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach ($anunciosUsuario as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['carta']) ?></td>
            <td><?= number_format($a['precio']) ?> ⛁</td>
            <td><?= date('d/m/Y', strtotime($a['fecha_publicacion'])) ?></td>
            <td>
              <?php if ($a['activa']): ?>
              <span class="status-pill status-on">En venta</span>
              <?php else: ?>
              <span class="status-pill status-off">Inactivo</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
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