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

// ----- Borrado (?eliminar=ID) -----
if (isset($_GET['eliminar'])) {
    $db->eliminarCodigoAdmin((int) $_GET['eliminar']);
    header('Location: codigos.php');
    exit;
}

// ----- Creación / edición (POST desde el modal) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_codigo = $_POST['id_codigo'] ?? '';
    $codigo    = trim($_POST['codigo'] ?? '');
    $tipo      = $_POST['tipo'] === 'unico' ? 'unico' : 'global';
    $monedas   = max(0, (int) ($_POST['monedas'] ?? 0));
    $activo    = isset($_POST['activo']) ? 1 : 0;

    if ($id_codigo !== '') {
        $ok = $db->actualizarCodigoAdmin((int) $id_codigo, $codigo, $tipo, $monedas, $activo);
    } else {
        $ok = $db->crearCodigoAdmin($codigo, $tipo, $monedas, $activo) !== false;
    }

    header('Location: codigos.php' . ($ok ? '' : '?error=codigo_duplicado'));
    exit;
}

$codigos = $db->listarCodigosAdmin();

$idCanjes = isset($_GET['canjes']) ? (int) $_GET['canjes'] : null;
$codigoCanjes = null;
$canjes = [];
if ($idCanjes) {
    foreach ($codigos as $c) {
        if ((int) $c['id_codigo'] === $idCanjes) { $codigoCanjes = $c; break; }
    }
    if ($codigoCanjes) { $canjes = $db->listarCanjesCodigo($idCanjes); }
}

$base         = '../';
$paginaTitulo = 'Códigos — Panel';
$paginaDesc   = 'Crea y edita los códigos de canje que los jugadores usan desde su perfil.';
$cssExtra     = ['panel/assets/css/admin.css'];
include __DIR__ . '/../partials/head.php';

$activeAdmin = 'codigos';
?>

<div class="admin-shell">
  <?php include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main" id="contenido">
    <div class="admin-head">
      <div>
        <h1>Códigos</h1>
        <p>Códigos que los jugadores canjean desde su perfil a cambio de monedas. «Único» solo lo puede usar una persona en todo el sistema; «Global» lo puede usar cualquiera, una vez cada uno.</p>
      </div>
      <button type="button" class="btn btn-primary" onclick="abrirModalCodigo()">
        <i class="ph ph-plus" aria-hidden="true"></i> Nuevo código
      </button>
    </div>

    <?php if (($_GET['error'] ?? '') === 'codigo_duplicado'): ?>
    <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--space-5);">
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span>Ya existe un código con ese texto (no se distinguen mayúsculas de minúsculas).</span>
    </div>
    <?php endif; ?>

    <div class="tabla-wrap">
      <table class="tabla">
        <thead>
          <tr>
            <th scope="col">Código</th>
            <th scope="col">Tipo</th>
            <th scope="col">Monedas</th>
            <th scope="col">Canjeado</th>
            <th scope="col">Estado</th>
            <th scope="col" style="text-align:right;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($codigos as $c): ?>
          <tr>
            <td>
              <div class="admin-cell-title mono"><?= htmlspecialchars($c['codigo']) ?></div>
              <div class="admin-cell-sub">ID #<?= (int) $c['id_codigo'] ?> · creado <?= date('d/m/Y', strtotime($c['creado'])) ?></div>
            </td>
            <td><?= $c['tipo'] === 'unico' ? 'Único (1 en total)' : 'Global (1 por jugador)' ?></td>
            <td class="mono"><?= (int) $c['monedas'] ?></td>
            <td>
              <a class="mono" href="codigos.php?canjes=<?= (int) $c['id_codigo'] ?>"><?= (int) $c['veces_canjeado'] ?> veces</a>
            </td>
            <td>
              <?php if ($c['activo']): ?>
              <span class="status-pill esta-activo">Activo</span>
              <?php else: ?>
              <span class="status-pill esta-inactivo">Inactivo</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <button type="button" class="icon-btn" title="Editar"
                        onclick='abrirModalCodigo(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'>
                  <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                </button>
                <button type="button" class="icon-btn es-peligro" title="Eliminar"
                        onclick="pedirBorradoCodigo('<?= htmlspecialchars($c['codigo'], ENT_QUOTES) ?>', <?= (int) $c['id_codigo'] ?>)">
                  <i class="ph ph-trash" aria-hidden="true"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($codigos)): ?>
          <tr><td colspan="6" style="text-align:center; color:var(--frost-dim); padding:40px;">Todavía no hay ningún código. Crea el primero con el botón de arriba.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <p class="t-caption t-dim" style="margin-top:var(--space-4);"><b class="mono"><?= count($codigos) ?></b> códigos</p>
  </main>
</div>

<!-- Modal crear / editar código -->
<div class="modal" id="modalCodigo" role="dialog" aria-modal="true" aria-labelledby="modalCodigoTitulo" aria-hidden="true">
  <div class="modal-caja">
    <div class="modal-head">
      <h2 id="modalCodigoTitulo">Nuevo código</h2>
      <button type="button" class="modal-cerrar" onclick="cerrarModalCodigo()" aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <form method="POST" action="codigos.php" id="formCodigo">
      <input type="hidden" name="id_codigo" id="fk_id_codigo">

      <div class="form-grid">
        <div class="campo campo-full">
          <label for="fk_codigo">Código</label>
          <input type="text" name="codigo" id="fk_codigo" placeholder="Ej. BIENVENIDA2026" maxlength="50" required
                 style="text-transform:uppercase;">
        </div>

        <div class="campo">
          <label for="fk_tipo">Tipo</label>
          <select name="tipo" id="fk_tipo">
            <option value="global">Global — cualquiera, una vez cada uno</option>
            <option value="unico">Único — solo una persona en todo el sistema</option>
          </select>
        </div>

        <div class="campo">
          <label for="fk_monedas">Monedas que entrega</label>
          <input type="number" name="monedas" id="fk_monedas" value="100" min="0">
        </div>

        <div class="campo campo-full">
          <div class="fila-interruptor">
            <span>Código activo (canjeable)</span>
            <label class="interruptor">
              <input type="checkbox" name="activo" id="fk_activo">
              <span class="interruptor-riel"></span>
            </label>
          </div>
        </div>
      </div>

      <div class="modal-pie">
        <button type="button" class="btn btn-ghost" onclick="cerrarModalCodigo()">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="fk_submit">Crear código</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal de canjes de un código, abierto directamente si ?canjes=ID viene en la URL -->
<div class="modal<?= $codigoCanjes ? ' is-abierto' : '' ?>" id="modalCanjes" role="dialog" aria-modal="true" aria-labelledby="modalCanjesTitulo" aria-hidden="<?= $codigoCanjes ? 'false' : 'true' ?>">
  <div class="modal-caja">
    <div class="modal-head">
      <h2 id="modalCanjesTitulo">Canjes — <?= $codigoCanjes ? htmlspecialchars($codigoCanjes['codigo']) : '' ?></h2>
      <a class="modal-cerrar" href="codigos.php" aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </a>
    </div>

    <?php if ($codigoCanjes): ?>
    <div class="tabla-wrap">
      <table class="tabla">
        <thead><tr><th>Jugador</th><th>Fecha</th></tr></thead>
        <tbody>
          <?php foreach ($canjes as $cj): ?>
          <tr>
            <td><?= htmlspecialchars($cj['usuario']) ?></td>
            <td class="mono"><?= date('d/m/Y H:i', strtotime($cj['fecha_canje'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($canjes)): ?>
          <tr><td colspan="2" style="text-align:center; color:var(--frost-dim);">Nadie lo ha canjeado todavía.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../partials/confirmar.php'; ?>

<?php $pieCompleto = false; include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= htmlspecialchars(assetUrl($base, 'panel/assets/js/scriptCodigos.js')) ?>"></script>
</body>
</html>
