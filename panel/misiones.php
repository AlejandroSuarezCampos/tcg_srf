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
    header('Location: misiones.php?error=csrf');
    exit;
}

// ----- Borrado (?eliminar=ID) -----
if (isset($_GET['eliminar'])) {
    $borrada = $db->eliminarMisionAdmin((int) $_GET['eliminar']);
    header('Location: misiones.php' . ($borrada ? '' : '?error=ya_reclamada'));
    exit;
}

// ----- Creación / edición (POST desde el modal) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_mision           = $_POST['id_mision'] ?? '';
    $nombre              = trim($_POST['nombre'] ?? '');
    $descripcion         = trim($_POST['descripcion'] ?? '');
    $tipo                = $_POST['tipo'] ?? '';
    $ciclo               = $_POST['ciclo'] ?? 'unica';
    $objetivo            = max(1, (int) ($_POST['objetivo'] ?? 1));
    $recompensa_monedas  = max(0, (int) ($_POST['recompensa_monedas'] ?? 0));
    $activo              = isset($_POST['activo']) ? 1 : 0;

    if ($id_mision !== '') {
        $ok = $db->actualizarMisionAdmin((int) $id_mision, $nombre, $descripcion, $tipo, $ciclo, $objetivo, $recompensa_monedas, $activo);
    } else {
        $ok = $db->crearMisionAdmin($nombre, $descripcion, $tipo, $ciclo, $objetivo, $recompensa_monedas, $activo) !== false;
    }

    header('Location: misiones.php' . ($ok ? '' : '?error=tipo_no_valido'));
    exit;
}

$misiones = $db->listarMisionesAdmin();

$etiquetasTipo = [
    'cartas_distintas'      => 'Cromos distintos en colección',
    'copias_totales'        => 'Copias totales (con repetidos)',
    'duelos_jugados'        => 'Duelos jugados',
    'duelos_ganados'        => 'Duelos ganados',
    'expansiones_completas' => 'Expansiones completadas',
    'mazos_creados'         => 'Mazos creados',
];
$etiquetasCiclo = ['unica' => 'Única', 'diaria' => 'Diaria', 'semanal' => 'Semanal'];

$base         = '../';
$paginaTitulo = 'Misiones — Panel';
$paginaDesc   = 'Crea y edita las misiones que los jugadores completan desde misiones.php.';
$cssExtra     = ['panel/assets/css/admin.css'];
include __DIR__ . '/../partials/head.php';

$activeAdmin = 'misiones';
?>

<div class="admin-shell">
  <?php include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main" id="contenido">
    <div class="admin-head">
      <div>
        <h1>Misiones</h1>
        <p>El progreso se deriva en cada carga (colección, mazos, duelos): no hay contador que desincronizar. Aquí solo se define el objetivo y la recompensa.</p>
      </div>
      <button type="button" class="btn btn-primary" onclick="abrirModalMision()">
        <i class="ph ph-plus" aria-hidden="true"></i> Nueva misión
      </button>
    </div>

    <?php if (($_GET['error'] ?? '') === 'ya_reclamada'): ?>
    <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--e-5);">
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span>No se pudo eliminar por un error inesperado. Inténtalo de nuevo.</span>
    </div>
    <?php endif; ?>
    <?php if (($_GET['error'] ?? '') === 'tipo_no_valido'): ?>
    <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--e-5);">
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span>Combinación no válida: «Expansiones completadas» no puede ser diaria ni semanal (es un hito de estado, no tiene una fecha de la que partir para contar solo el periodo actual).</span>
    </div>
    <?php endif; ?>

    <div class="tabla-wrap">
      <table class="tabla">
        <thead>
          <tr>
            <th scope="col">Misión</th>
            <th scope="col">Cuenta</th>
            <th scope="col">Objetivo</th>
            <th scope="col">Recompensa</th>
            <th scope="col">Reclamada</th>
            <th scope="col">Estado</th>
            <th scope="col" style="text-align:right;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($misiones as $m): ?>
          <tr>
            <td>
              <div class="admin-cell-title"><?= htmlspecialchars($m['nombre']) ?></div>
              <div class="admin-cell-sub"><?= htmlspecialchars($m['descripcion']) ?></div>
            </td>
            <td>
              <?= htmlspecialchars($etiquetasTipo[$m['tipo']] ?? $m['tipo']) ?>
              <div class="admin-cell-sub"><?= htmlspecialchars($etiquetasCiclo[$m['ciclo']] ?? $m['ciclo']) ?></div>
            </td>
            <td class="mono"><?= (int) $m['objetivo'] ?></td>
            <td class="mono"><?= (int) $m['recompensa_monedas'] ?></td>
            <td class="mono"><?= (int) $m['veces_reclamada'] ?></td>
            <td>
              <?php if ($m['activo']): ?>
              <span class="status-pill esta-activo">Activa</span>
              <?php else: ?>
              <span class="status-pill esta-inactivo">Inactiva</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <button type="button" class="icon-btn" title="Editar"
                        onclick='abrirModalMision(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)'>
                  <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                </button>
                <button type="button" class="icon-btn es-peligro" title="Eliminar"
                        onclick="pedirBorradoMision('<?= htmlspecialchars($m['nombre'], ENT_QUOTES) ?>', <?= (int) $m['id_mision'] ?>)">
                  <i class="ph ph-trash" aria-hidden="true"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($misiones)): ?>
          <tr><td colspan="7" style="text-align:center; color:var(--ceniza); padding:40px;">Todavía no hay ninguna misión. Crea la primera con el botón de arriba.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <p class="t-caption t-dim" style="margin-top:var(--e-4);"><b class="mono"><?= count($misiones) ?></b> misiones</p>
  </main>
</div>

<!-- Modal crear / editar misión -->
<div class="modal" id="modalMision" role="dialog" aria-modal="true" aria-labelledby="modalMisionTitulo" aria-hidden="true">
  <div class="modal-caja">
    <div class="modal-head">
      <h2 id="modalMisionTitulo">Nueva misión</h2>
      <button type="button" class="modal-cerrar" onclick="cerrarModalMision()" aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <form method="POST" action="misiones" id="formMision">
      <?= csrfCampo() ?>
      <input type="hidden" name="id_mision" id="fm_id_mision">

      <div class="form-grid">
        <div class="campo campo-full">
          <label for="fm_nombre">Nombre</label>
          <input type="text" name="nombre" id="fm_nombre" placeholder="Ej. Primeras fichas" required>
        </div>

        <div class="campo campo-full">
          <label for="fm_descripcion">Descripción (texto que ve el jugador)</label>
          <input type="text" name="descripcion" id="fm_descripcion" placeholder="Ej. Consigue 10 cromos distintos." required>
        </div>

        <div class="campo">
          <label for="fm_tipo">Qué cuenta</label>
          <select name="tipo" id="fm_tipo">
            <option value="cartas_distintas">Cromos distintos en colección</option>
            <option value="copias_totales">Copias totales (con repetidos)</option>
            <option value="mazos_creados">Mazos creados</option>
            <option value="duelos_jugados">Duelos jugados</option>
            <option value="duelos_ganados">Duelos ganados</option>
            <option value="expansiones_completas" id="fm_opcion_expansiones">Expansiones completadas</option>
          </select>
        </div>

        <div class="campo">
          <label for="fm_objetivo">Objetivo</label>
          <input type="number" name="objetivo" id="fm_objetivo" value="1" min="1">
        </div>

        <div class="campo">
          <label for="fm_ciclo">Ciclo</label>
          <select name="ciclo" id="fm_ciclo" onchange="SRF.misiones && SRF.misiones.alCambiarCiclo()">
            <option value="unica">Única (se reclama una vez y ya está)</option>
            <option value="diaria">Diaria — se reinicia cada día a medianoche (hora del servidor)</option>
            <option value="semanal">Semanal — se reinicia cada lunes a medianoche (hora del servidor)</option>
          </select>
          <p class="t-caption t-dim" style="margin-top:var(--e-2);">
            En diaria/semanal el progreso solo cuenta lo ocurrido DENTRO del periodo actual (hoy, o desde el lunes), no de toda la vida — por eso «Expansiones completadas» no está disponible aquí: es un hito de estado, no tiene una fecha de la que partir.
          </p>
        </div>

        <div class="campo">
          <label for="fm_recompensa_monedas">Recompensa (monedas)</label>
          <input type="number" name="recompensa_monedas" id="fm_recompensa_monedas" value="100" min="0">
        </div>

        <div class="campo campo-full">
          <div class="fila-interruptor">
            <span>Misión activa (visible en el sitio)</span>
            <label class="interruptor">
              <input type="checkbox" name="activo" id="fm_activo">
              <span class="interruptor-riel"></span>
            </label>
          </div>
        </div>
      </div>

      <div class="modal-pie">
        <button type="button" class="btn btn-ghost" onclick="cerrarModalMision()">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="fm_submit">Crear misión</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../partials/confirmar.php'; ?>

<?php $pieCompleto = false; include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= htmlspecialchars(assetUrl($base, 'panel/assets/js/scriptMisiones.js')) ?>"></script>
</body>
</html>
