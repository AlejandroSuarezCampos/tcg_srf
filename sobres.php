<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/rareza-clases.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = $_SESSION['id_usuario'];

function esPeticionAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

$error = '';

// ----- Comprar y abrir un sobre -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'comprar_sobre') {
    $id_sobre = (int) ($_POST['id_sobre'] ?? 0);

    $resultado = $db->abrirSobre($id_sobre, $id_usuario);

    if ($resultado['ok']) {
        $_SESSION['monedas'] = $resultado['monedas'];
    }

    if (esPeticionAjax()) {
        header('Content-Type: application/json');
        echo json_encode([
            'ok'      => $resultado['ok'],
            'error'   => $resultado['error'],
            'monedas' => $resultado['monedas'] ?? ($_SESSION['monedas'] ?? null),
            'cartas'  => array_map(function ($c) use ($claseRarezaPorId) {
                return [
                    'nombre'  => $c['nombre'],
                    'imagen'  => $c['imagen'],
                    'equipo'  => $c['equipo'],
                    'rareza'  => $c['rareza'],
                    'clase'   => $claseRarezaPorId[$c['id_rareza']] ?? 'r-comun',
                ];
            }, $resultado['cartas'] ?? []),
        ]);
        exit;
    }

    if (!$resultado['ok']) {
        $error = $resultado['error'];
    }
}

// ----- Listado de sobres a la venta, agrupados por expansión -----
$sobresPlanos = $db->listarSobresActivos();

$sobresPorExpansion = [];
foreach ($sobresPlanos as $s) {
    $idExp = $s['id_expansion'];
    if (!isset($sobresPorExpansion[$idExp])) {
        $sobresPorExpansion[$idExp] = [
            'nombre'  => $s['expansion'],
            'total'   => (int) $s['total_cartas'],
            'sobres'  => [],
        ];
    }
    $sobresPorExpansion[$idExp]['sobres'][] = $s;
}

$monedasActuales = $_SESSION['monedas'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sobres — Inazuma TCG</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" type="image/png" href="./assets/img/iconos/favicon.ico">
</head>
<body>

<?php $activePage = 'sobres'; include __DIR__ . '/navbar.php'; ?>

<div class="page-banner">
  <div class="hero-grid"></div>
  <div class="page-banner-content">
    <h1>Sobres</h1>
    <p>Abre sobres para conseguir cartas nuevas al azar. Cada rareza tiene su propia probabilidad de caer.</p>
    <div class="page-banner-stats">
      <div class="pb-stat"><b><?= number_format($monedasActuales) ?></b><span>Tus monedas</span></div>
      <div class="pb-stat"><b><?= count($sobresPlanos) ?></b><span>Sobres disponibles</span></div>
    </div>
  </div>
</div>

<section>
  <?php if ($error): ?>
  <div class="auth-error show" style="margin-bottom:24px;"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (empty($sobresPorExpansion)): ?>
  <div class="empty-state">
    <h3>No hay sobres a la venta ahora mismo</h3>
    <p>Vuelve más tarde, seguro que pronto sale alguno nuevo.</p>
  </div>
  <?php endif; ?>

  <?php foreach ($sobresPorExpansion as $grupo): ?>
  <div class="expansion-group reveal">
    <div class="expansion-head">
      <div>
        <h2><?= htmlspecialchars($grupo['nombre']) ?></h2>
        <span class="exp-sub"><?= $grupo['total'] ?> cromos en esta expansión</span>
      </div>
    </div>

    <div class="pack-grid">
      <?php foreach ($grupo['sobres'] as $s): ?>
      <div class="pack-card reveal">
        <div class="pack-card-inner">
          <div class="pack-portrait">
            <?php if ($s['imagen']): ?>
            <img src="<?= htmlspecialchars($s['imagen']) ?>" alt="<?= htmlspecialchars($s['nombre']) ?>">
            <?php else: ?>
            <span>📦</span>
            <?php endif; ?>
          </div>

          <h3><?= htmlspecialchars($s['nombre']) ?></h3>
          <div class="meta-row">
            <span><?= (int) $s['cantidad'] ?> cartas</span>
            <span><?= htmlspecialchars($s['expansion']) ?></span>
          </div>

          <div class="pack-price"><?= number_format($s['precio']) ?> ⛁</div>

          <form method="POST" action="sobres.php" class="js-sobre-form" data-cantidad="<?= (int) $s['cantidad'] ?>">
            <input type="hidden" name="accion" value="comprar_sobre">
            <input type="hidden" name="id_sobre" value="<?= $s['id_sobre'] ?>">
            <button type="submit" class="btn btn-primary buy-btn" <?= $monedasActuales < $s['precio'] ? 'disabled title="No tienes monedas suficientes"' : '' ?>>
              Abrir sobre
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</section>

<!-- Modal: revelación de cartas obtenidas al abrir un sobre -->
<div class="modal-backdrop" id="modalRevelacion">
  <div class="modal-box modal-box-reveal">
    <div class="modal-box-inner">
      <div class="modal-head">
        <h2>¡Sobre abierto!</h2>
        <button class="modal-close" onclick="cerrarModalRevelacion()">✕</button>
      </div>

      <div class="reveal-grid" id="revealGrid"></div>

      <div class="modal-footer">
        <button type="button" class="btn btn-primary" onclick="cerrarModalRevelacion()">Genial, seguir</button>
      </div>
    </div>
  </div>
</div>

<script src="./assets/js/scriptSobres.js"></script>

</body>
</html>
