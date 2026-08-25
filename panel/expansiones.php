<?php
session_start();
require_once __DIR__ . '/../db/conexion.php';
require_once __DIR__ . '/../partials/csrf.php';

if (isset($_SESSION['dictador'])) {
    if ($_SESSION['dictador'] != 1) {
        header('Location: ../landing.php');
        exit;
    }
} else {
    header('Location: ../landing.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['eliminar'])) && !csrfValido($_REQUEST['csrf'] ?? null)) {
    header('Location: expansiones.php?error=csrf');
    exit;
}

// ----- Borrado (?eliminar=ID) -----
if (isset($_GET['eliminar'])) {
    $borrada = $db->eliminarExpansion((int) $_GET['eliminar']);
    header('Location: expansiones.php' . ($borrada ? '' : '?error=cartas_en_uso'));
    exit;
}

// ----- Creación / edición (POST desde el modal) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_expansion = $_POST['id_expansion'] ?? '';
    $nombre       = trim($_POST['nombre'] ?? '');
    $fecha_salida = $_POST['fecha_salida'] ?? date('Y-m-d');
    $activo       = isset($_POST['activo']) ? 1 : 0;

    if ($id_expansion !== '') {
        $db->actualizarExpansion((int) $id_expansion, $nombre, $fecha_salida, $activo);
    } else {
        $db->crearExpansion($nombre, $fecha_salida, $activo);
    }

    header('Location: expansiones.php');
    exit;
}

$expansiones = $db->listarExpansiones();

$filtroTexto = trim($_GET['q'] ?? '');
if ($filtroTexto !== '') {
    $expansiones = array_values(array_filter($expansiones, fn($e) => stripos($e['nombre'], $filtroTexto) !== false));
}

$base         = '../';
$paginaTitulo = 'Expansiones — Panel';
$paginaDesc   = 'Crea, edita y elimina las expansiones (sets) del juego.';
$cssExtra     = ['panel/assets/css/admin.css'];
include __DIR__ . '/../partials/head.php';

$activeAdmin = 'expansiones';
?>

<div class="admin-shell">
  <?php include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main" id="contenido">
    <div class="admin-head">
      <div>
        <h1>Expansiones</h1>
        <p>Crea, edita y elimina las expansiones (sets) del juego.</p>
      </div>
      <button type="button" class="btn btn-primary" onclick="abrirModalExpansion()">
        <i class="ph ph-plus" aria-hidden="true"></i> Nueva expansión
      </button>
    </div>

    <?php if (($_GET['error'] ?? '') === 'cartas_en_uso'): ?>
    <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--e-5);">
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span>No se pudo eliminar por un error inesperado. Inténtalo de nuevo.</span>
    </div>
    <?php endif; ?>

    <form method="GET" class="barra-filtros">
      <div class="campo">
        <label for="e-buscar">Buscar</label>
        <input type="search" name="q" id="e-buscar" value="<?= htmlspecialchars($filtroTexto) ?>"
               placeholder="Nombre de la expansión">
      </div>
      <div class="barra-filtros-acciones">
        <button type="submit" class="btn btn-ghost">Filtrar</button>
        <?php if ($filtroTexto !== ''): ?>
        <a class="btn btn-plano" href="expansiones.php">Quitar</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="tabla-wrap">
      <table class="tabla">
        <thead>
          <tr>
            <th scope="col">Expansión</th>
            <th scope="col">Fecha de salida</th>
            <th scope="col">Cromos incluidos</th>
            <th scope="col">Estado</th>
            <th scope="col" style="text-align:right;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($expansiones as $ex):
              $totalCromos = $db->cartasExpansion($ex['id_expansion']);
          ?>
          <tr>
            <td>
              <div class="admin-cell-title"><?= htmlspecialchars($ex['nombre']) ?></div>
              <div class="admin-cell-sub">ID #<?= (int) $ex['id_expansion'] ?></div>
            </td>
            <td class="mono"><?= date('d/m/Y', strtotime($ex['fecha_salida'])) ?></td>
            <td class="mono"><?= $totalCromos ?> cromos</td>
            <td>
              <?php if ($ex['activo']): ?>
              <span class="status-pill esta-activo">Activa</span>
              <?php else: ?>
              <span class="status-pill esta-inactivo">Inactiva</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <button type="button" class="icon-btn" title="Editar"
                        onclick='abrirModalExpansion(<?= htmlspecialchars(json_encode($ex), ENT_QUOTES) ?>)'>
                  <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                </button>
                <button type="button" class="icon-btn es-peligro" title="Eliminar"
                        onclick="pedirBorrado('<?= htmlspecialchars($ex['nombre'], ENT_QUOTES) ?>', <?= (int) $ex['id_expansion'] ?>)">
                  <i class="ph ph-trash" aria-hidden="true"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($expansiones)): ?>
          <tr><td colspan="5" style="text-align:center; color:var(--ceniza); padding:40px;">No se encontraron expansiones con ese nombre.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <p class="t-caption t-dim" style="margin-top:var(--e-4);"><b class="mono"><?= count($expansiones) ?></b> expansiones mostradas</p>
  </main>
</div>

<!-- Modal crear / editar expansión -->
<div class="modal" id="modalExpansion" role="dialog" aria-modal="true" aria-labelledby="modalExpansionTitulo" aria-hidden="true">
  <div class="modal-caja">
    <div class="modal-head">
      <h2 id="modalExpansionTitulo">Nueva expansión</h2>
      <button type="button" class="modal-cerrar" onclick="cerrarModalExpansion()" aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <form method="POST" action="expansiones.php" id="formExpansion">
      <?= csrfCampo() ?>
      <input type="hidden" name="id_expansion" id="fe_id_expansion">

      <div class="form-grid">
        <div class="campo campo-full">
          <label for="fe_nombre">Nombre de la expansión</label>
          <input type="text" name="nombre" id="fe_nombre" placeholder="Ej. Tormenta de Invierno" required>
        </div>

        <div class="campo campo-full">
          <label for="fe_fecha_salida">Fecha de salida</label>
          <input type="date" name="fecha_salida" id="fe_fecha_salida">
        </div>

        <div class="campo campo-full">
          <div class="fila-interruptor">
            <span>Expansión activa (visible en el sitio)</span>
            <label class="interruptor">
              <input type="checkbox" name="activo" id="fe_activo">
              <span class="interruptor-riel"></span>
            </label>
          </div>
        </div>
      </div>

      <div class="modal-pie">
        <button type="button" class="btn btn-ghost" onclick="cerrarModalExpansion()">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="fe_submit">Crear expansión</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../partials/confirmar.php'; ?>

<?php $pieCompleto = false; include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= htmlspecialchars(assetUrl($base, 'panel/assets/js/scriptExpansiones.js')) ?>"></script>
</body>
</html>
