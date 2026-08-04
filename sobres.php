<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';

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
            // Cada carta viaja ya renderizada por el componente compartido: la
            // ceremonia no reimplementa el marcado de carta en JavaScript.
            'cartas'  => array_map(function ($c) {
                return [
                    'nombre'    => $c['nombre'],
                    'rareza'    => $c['rareza'],
                    'id_rareza' => (int) $c['id_rareza'],
                    'html'      => carta_html($c, ['tamano' => 'sm', 'lazy' => false]),
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

$paginaTitulo = 'Sobres';
$paginaDesc   = 'Abre sobres para conseguir cartas nuevas de la Superliga Frontier.';
include __DIR__ . '/partials/head.php';

$activePage = 'sobres';
include __DIR__ . '/navbar.php';
?>

<header class="cabecera">
  <div class="linea-campo" aria-hidden="true"></div>
  <div class="wrap cabecera-contenido">
    <h1>Sobres</h1>
    <p>Cada sobre reparte cartas al azar. La probabilidad de cada rareza es siempre la misma.</p>
    <div class="cabecera-datos">
      <div class="dato"><b id="saldoMonedas"><?= number_format($monedasActuales, 0, ',', '.') ?></b><span>Tus monedas</span></div>
      <div class="dato"><b><?= count($sobresPlanos) ?></b><span>Sobres disponibles</span></div>
    </div>
  </div>
</header>

<main id="contenido" class="seccion wrap">

  <?php if ($error): ?>
  <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--space-5);">
    <i class="ph ph-warning-circle" aria-hidden="true"></i>
    <span><?= htmlspecialchars($error) ?></span>
  </div>
  <?php endif; ?>

  <?php if (empty($sobresPorExpansion)): ?>
  <div class="vacio">
    <span class="vacio-ico"><i class="ph ph-package" aria-hidden="true"></i></span>
    <h3>No hay sobres a la venta</h3>
    <p>Cuando se abra una expansión nueva, sus sobres aparecerán aquí.</p>
    <a class="btn btn-ghost" href="album.php">Ver el álbum</a>
  </div>
  <?php endif; ?>

  <?php foreach ($sobresPorExpansion as $grupo): ?>
  <section class="expansion-grupo reveal">
    <div class="expansion-cabecera">
      <div>
        <h2><?= htmlspecialchars($grupo['nombre']) ?></h2>
        <span class="t-caption t-dim"><span class="mono"><?= $grupo['total'] ?></span> cartas en esta expansión</span>
      </div>
    </div>

    <div class="sobre-grid">
      <?php foreach ($grupo['sobres'] as $s): ?>
      <?php $sinSaldo = $monedasActuales < $s['precio']; ?>
      <article class="sobre">
        <div class="sobre-arte">
          <?php if ($s['imagen']): ?>
          <img src="<?= htmlspecialchars($s['imagen']) ?>" alt="Sobre <?= htmlspecialchars($s['nombre']) ?>">
          <?php else: ?>
          <i class="ph ph-package" aria-hidden="true"></i>
          <?php endif; ?>
        </div>

        <h3><?= htmlspecialchars($s['nombre']) ?></h3>
        <p class="t-caption t-dim"><span class="mono"><?= (int) $s['cantidad'] ?></span> cartas</p>
        <p class="sobre-precio">
          <i class="ph ph-coins" aria-hidden="true"></i>
          <?= number_format($s['precio'], 0, ',', '.') ?>
        </p>

        <form method="POST" action="sobres.php" class="js-sobre"
              data-precio="<?= (int) $s['precio'] ?>" data-cantidad="<?= (int) $s['cantidad'] ?>">
          <input type="hidden" name="accion" value="comprar_sobre">
          <input type="hidden" name="id_sobre" value="<?= $s['id_sobre'] ?>">
          <button type="submit" class="btn btn-primary btn-bloque"
                  <?= $sinSaldo ? 'disabled title="No tienes monedas suficientes"' : '' ?>>
            Abrir sobre
          </button>
        </form>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
<?php include __DIR__ . '/partials/ceremonia.php'; ?>

<script src="assets/js/sobres.js"></script>

</body>
</html>
