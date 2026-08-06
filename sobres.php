<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';
require_once __DIR__ . '/components/caja3d.php';

// Los 50 sobres de un tipo, ya listos para vivir DENTRO de .caja3d-interior
// (requisito técnico del prompt: mismo árbol preserve-3d que la caja, nunca
// un contenedor hermano). Se reutiliza tanto si la caja que se abre es la
// grande (expansión con un solo tipo) como una pequeña del submenú.
function interiorSobresHtml(Tcg $db, array $s, bool $sinSaldo): string {
    $rutasSobre = $db->rutasPlantilla('sobre', $s['id_sobre']);
    $html = '';
    for ($i = 0; $i < Tcg::SOBRES_POR_CAJA; $i++) {
        $html .= pack3d_sobre_html($rutasSobre, [
            'clase' => 'js-sobre-individual',
            'disabled' => $sinSaldo,
            'indice' => $i,
            'total'  => Tcg::SOBRES_POR_CAJA,
            'datos' => [
                'id-sobre' => $s['id_sobre'],
                'nombre'   => $s['nombre'],
                'precio'   => (int) $s['precio'],
                'imagen'   => $s['imagen'] ?? '',
                // texturas de la plantilla: la ceremonia abre EL MISMO sobre
                // que acabas de coger, no un genérico
                'frente'   => $rutasSobre['frente'] ?? '',
                'reverso'  => $rutasSobre['reverso'] ?? '',
            ],
        ]);
    }
    return $html;
}

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

  <?php if (!empty($sobresPorExpansion)): ?>
  <!-- Fase 1: una caja cerrada de 50 sobres por expansión.
       Todo el bloque va en #vistaExpansiones para poder ocultarlo entero al
       entrar en una expansión: al elegir caja se ve SOLO el submenú de tipos
       de sobre, sin repetir debajo la caja de la expansión ni su cabecera. -->
  <div id="vistaExpansiones">
  <div class="vitrina-cabecera">
    <h2>Expansiones</h2>
    <p class="t-caption t-dim">Elige una caja y ábrela para ver sus sobres.</p>
  </div>
  <div class="vitrina-grid" id="vitrina">
    <?php foreach ($sobresPorExpansion as $idExp => $grupo): ?>
    <?php $rutasCaja = $db->rutasPlantilla('caja_expansion', $idExp); ?>
    <?php $tipoUnico = count($grupo['sobres']) === 1 ? $grupo['sobres'][0] : null; ?>
    <div class="vitrina-item">
      <div class="pack3d-portal" id="portal-caja-<?= $idExp ?>">
        <!-- div, no button: el interior lleva hasta 50 <button> de sobre
             (interiorHtml) y un <button> no puede contener otro <button> —
             el navegador cerraría este de golpe al primer sobre y rompería
             toda la caja. role=button + tabindex hacen el mismo trabajo. -->
        <div class="js-caja-expansion" role="button" tabindex="0"
                data-expansion="<?= $idExp ?>" data-tipos="<?= count($grupo['sobres']) ?>"
                <?= $tipoUnico ? 'data-id-sobre-unico="' . $tipoUnico['id_sobre'] . '"' : '' ?>>
          <?= pack3d_caja_html($rutasCaja, [
              'datos' => ['expansion' => $idExp],
              'interiorHtml' => $tipoUnico ? interiorSobresHtml($db, $tipoUnico, $monedasActuales < $tipoUnico['precio']) : '',
          ]) ?>
        </div>
        <?php if ($tipoUnico): ?>
        <button type="button" class="btn btn-ghost pack3d-portal-cerrar js-cerrar-blister">
          <i class="ph ph-x" aria-hidden="true"></i> Cerrar
        </button>
        <?php endif; ?>
        <span class="vitrina-item-nombre"><?= htmlspecialchars($grupo['nombre']) ?></span>
        <span class="t-caption t-dim mono"><?= $grupo['total'] ?> cartas · <?= count($grupo['sobres']) ?> tipos de sobre</span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  </div><!-- /#vistaExpansiones -->

  <!-- Fase 2: submenú de tipos de sobre (solo si la expansión tiene más de uno) -->
  <?php foreach ($sobresPorExpansion as $idExp => $grupo): ?>
  <?php if (count($grupo['sobres']) > 1): ?>
  <div class="submenu-tipos" id="submenu-<?= $idExp ?>" data-expansion="<?= $idExp ?>" hidden>
    <div class="submenu-tipos-cabecera">
      <button type="button" class="btn btn-ghost btn-sm js-cerrar-submenu">
        <i class="ph ph-arrow-left" aria-hidden="true"></i> Expansiones
      </button>
      <div>
        <h2><?= htmlspecialchars($grupo['nombre']) ?></h2>
        <p class="t-caption t-dim">Elige un tipo de sobre.</p>
      </div>
    </div>
    <?php foreach ($grupo['sobres'] as $s): ?>
    <?php $rutasCajaTipo = $db->rutasPlantilla('caja_sobre', $s['id_sobre']); ?>
    <div class="submenu-tipo">
      <div class="pack3d-portal" id="portal-tipo-<?= $s['id_sobre'] ?>">
        <!-- div, no button: mismo motivo que en la caja de expansión -->
        <div class="js-tipo-sobre" role="button" tabindex="0" data-id-sobre="<?= $s['id_sobre'] ?>">
          <?= pack3d_caja_html($rutasCajaTipo, [
              'escala' => 'pequena',
              'interiorHtml' => interiorSobresHtml($db, $s, $monedasActuales < $s['precio']),
          ]) ?>
        </div>
        <button type="button" class="btn btn-ghost pack3d-portal-cerrar js-cerrar-blister">
          <i class="ph ph-x" aria-hidden="true"></i> Cerrar
        </button>
        <span class="submenu-tipo-nombre"><?= htmlspecialchars($s['nombre']) ?></span>
        <span class="submenu-tipo-precio">
          <i class="ph ph-coins" aria-hidden="true"></i> <?= number_format($s['precio'], 0, ',', '.') ?>
        </span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php endforeach; ?>
  <?php endif; ?>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
<?php include __DIR__ . '/partials/confirmar.php'; ?>
<?php include __DIR__ . '/partials/ceremonia.php'; ?>

<?= assetScript($base ?? '', 'assets/js/sobres.js') ?>

</body>
</html>
