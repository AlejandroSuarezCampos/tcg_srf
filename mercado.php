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

$mensaje = '';
$error   = '';

// ----- Publicar un anuncio nuevo -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'publicar') {
    $id_coleccion = (int) ($_POST['id_coleccion'] ?? 0);
    $precio       = (int) ($_POST['precio'] ?? 0);

    if ($id_coleccion <= 0 || $precio <= 0) {
        $error = 'Elige una carta y un precio válido.';
    } elseif (!$db->publicarAnuncio($id_coleccion, $id_usuario, $precio)) {
        $error = 'No se pudo publicar el anuncio (¿esa carta ya no es tuya, o está bloqueada?).';
    } else {
        header('Location: mercado.php?ok=publicado');
        exit;
    }
}

// ----- Retirar un anuncio propio -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'retirar') {
    $db->retirarAnuncio((int) $_POST['id_anuncio'], $id_usuario);

    if (esPeticionAjax()) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    header('Location: mercado.php?ok=retirado');
    exit;
}

// ----- Comprar una carta -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'comprar') {
    $resultado = $db->comprarAnuncio((int) $_POST['id_anuncio'], $id_usuario);

    if ($resultado['ok']) {
        // Sincronizamos la sesión con el saldo real tras la compra
        $usuarioActualizado = $db->obtenerUsuario($id_usuario);
        if ($usuarioActualizado) {
            $_SESSION['monedas'] = $usuarioActualizado['monedas'];
        }
    }

    if (esPeticionAjax()) {
        header('Content-Type: application/json');
        echo json_encode([
            'ok'      => $resultado['ok'],
            'error'   => $resultado['error'],
            'monedas' => $_SESSION['monedas'] ?? null,
        ]);
        exit;
    }

    if ($resultado['ok']) {
        header('Location: mercado.php?ok=comprado');
        exit;
    } else {
        $error = $resultado['error'];
    }
}

if (isset($_GET['ok'])) {
    $mensajes = [
        'publicado' => 'Tu carta ya está publicada en el mercado.',
        'retirado'  => 'Anuncio retirado.',
        'comprado'  => '¡Compra realizada! La carta ya es tuya.',
    ];
    $mensaje = $mensajes[$_GET['ok']] ?? '';
}

$rarezasDB = $db->listarRarezas();
$rarezas = [];
foreach ($rarezasDB as $r) {
    $rarezas[$r['id_rareza']] = $r['nombre'];
}

$filtroNombre = trim($_GET['q'] ?? '');
$filtroRareza = $_GET['id_rareza'] ?? '';
$orden        = $_GET['orden'] ?? '';

$anuncios = $db->listarMercadoActivo([
    'nombre'    => $filtroNombre,
    'id_rareza' => $filtroRareza,
    'orden'     => $orden,
]);

$cromosVendibles = $db->listarColeccionVendible($id_usuario);
$monedasActuales = $_SESSION['monedas'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mercado — Inazuma TCG</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" type="image/png" href="assets/img/iconos/favicon.ico">
</head>
<body>

<?php $activePage = 'mercado'; include __DIR__ . '/navbar.php'; ?>

<div class="page-banner">
  <div class="hero-grid"></div>
  <div class="page-banner-content" style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:24px;">
    <div>
      <h1>Mercado</h1>
      <p>Compra y vende cromos con otros invocadores.</p>
    </div>
    <button class="btn btn-primary" onclick="abrirModalVender()">Vender una carta</button>
  </div>
</div>

<section>
  <?php if ($mensaje): ?>
  <div class="status-pill status-on" style="display:block; margin-bottom:24px; padding:14px 18px; font-size:13px;"><?= htmlspecialchars($mensaje) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="auth-error show" style="margin-bottom:24px;"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="GET" class="market-toolbar reveal">
    <input type="text" name="q" value="<?= htmlspecialchars($filtroNombre) ?>" placeholder="Buscar carta por nombre...">
    <select name="id_rareza">
      <option value="">Todas las rarezas</option>
      <?php foreach ($rarezas as $id => $nombre): ?>
      <option value="<?= $id ?>" <?= (string) $filtroRareza === (string) $id ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="orden">
      <option value="">Ordenar por</option>
      <option value="precio_asc" <?= $orden === 'precio_asc' ? 'selected' : '' ?>>Precio: menor a mayor</option>
      <option value="precio_desc" <?= $orden === 'precio_desc' ? 'selected' : '' ?>>Precio: mayor a menor</option>
    </select>
    <button type="submit" class="btn btn-ghost" style="padding:11px 20px; font-size:12px;">Buscar</button>
  </form>

  <div class="card-grid">
    <?php foreach ($anuncios as $a):
        $esTuyo = (int) $a['id_vendedor'] === (int) $id_usuario;
        $imagenPanel = $a['imagen'] ? $a['imagen'] : null;
    ?>
    <div class="tcard reveal" data-anuncio="<?= $a['id_anuncio'] ?>">
      <div class="tcard-inner" style="position:relative;">
        <span class="listing-price"><?= number_format($a['precio']) ?> ⛁</span>
        <span class="rarity <?= $claseRarezaPorId[$a['id_rareza']] ?? 'r-comun' ?>"><?= htmlspecialchars($a['rareza']) ?></span>
        <div class="portrait">
          <?php if ($imagenPanel): ?>
          <img src="<?= htmlspecialchars($imagenPanel) ?>" alt="">
          <?php else: ?>
          <?= strtoupper(substr($a['carta'],0,2)) ?>
          <?php endif; ?>
        </div>
        <h3><?= htmlspecialchars($a['carta']) ?></h3>
        <div class="meta-row"><span><?= htmlspecialchars($a['equipo']) ?></span></div>
        <div class="listing-seller">
          <div class="avatar-sm"><?= strtoupper(substr($a['vendedor'],0,2)) ?></div>
          <span>Vende <?= htmlspecialchars($a['vendedor']) ?></span>
        </div>

        <?php if ($esTuyo): ?>
        <form method="POST" action="mercado.php" class="js-mercado-form" data-confirm="¿Retirar este anuncio del mercado?">
          <input type="hidden" name="accion" value="retirar">
          <input type="hidden" name="id_anuncio" value="<?= $a['id_anuncio'] ?>">
          <button type="submit" class="btn btn-ghost buy-btn">Retirar mi anuncio</button>
        </form>
        <?php else: ?>
        <form method="POST" action="mercado.php" class="js-mercado-form" data-confirm="¿Comprar <?= htmlspecialchars($a['carta'], ENT_QUOTES) ?> por <?= (int) $a['precio'] ?> monedas?">
          <input type="hidden" name="accion" value="comprar">
          <input type="hidden" name="id_anuncio" value="<?= $a['id_anuncio'] ?>">
          <button type="submit" class="btn btn-primary buy-btn">Comprar</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($anuncios)): ?>
    <div class="empty-state" style="grid-column:1/-1;">
      <h3>No hay anuncios con esos filtros</h3>
      <p>Prueba a cambiar la búsqueda, o sé el primero en poner una carta a la venta.</p>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- Modal: vender una carta -->
<div class="modal-backdrop" id="modalVender">
  <div class="modal-box">
    <div class="modal-box-inner">
      <div class="modal-head">
        <h2>Vender una carta</h2>
        <button class="modal-close" onclick="cerrarModalVender()">✕</button>
      </div>

      <?php if (empty($cromosVendibles)): ?>
      <div class="empty-state">
        <h3>No tienes cartas disponibles para vender</h3>
        <p>Puede que todas estén bloqueadas o ya publicadas. Puedes gestionarlo desde tu colección.</p>
      </div>
      <?php else: ?>
      <form method="POST" action="mercado.php">
        <input type="hidden" name="accion" value="publicar">

        <div class="form-grid">
          <div class="field field-full">
            <label>Carta a vender</label>
            <select name="id_coleccion" required>
              <option value="">Selecciona una carta...</option>
              <?php foreach ($cromosVendibles as $c): ?>
              <option value="<?= $c['id_coleccion'] ?>"><?= htmlspecialchars($c['nombre']) ?> — <?= htmlspecialchars($c['equipo']) ?> (<?= htmlspecialchars($c['rareza']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field field-full">
            <label>Precio (monedas)</label>
            <input type="number" name="precio" min="1" placeholder="Ej. 250" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="cerrarModalVender()">Cancelar</button>
          <button type="submit" class="btn btn-primary">Publicar anuncio</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="./assets/js/scriptMercado.js"></script>

</body>
</html>