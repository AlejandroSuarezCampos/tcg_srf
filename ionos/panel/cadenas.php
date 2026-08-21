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

if (($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['eliminar']) || isset($_GET['eliminar_requisito']))
    && !csrfValido($_REQUEST['csrf'] ?? null)) {
    header('Location: cadenas.php?error=csrf');
    exit;
}

// ----- Borrado de una cadena entera (?eliminar=ID) -----
if (isset($_GET['eliminar'])) {
    $borrada = $db->eliminarCadenaAdmin((int) $_GET['eliminar']);
    header('Location: cadenas.php' . ($borrada ? '' : '?error=progreso_en_uso'));
    exit;
}

// ----- Borrado de un requisito (?eliminar_requisito=ID) -----
if (isset($_GET['eliminar_requisito'])) {
    $db->eliminarRequisito((int) $_GET['eliminar_requisito']);
    header('Location: cadenas.php?requisitos=' . (int) ($_GET['id_cadena'] ?? 0));
    exit;
}

// ----- Añadir un requisito (POST desde el modal de requisitos) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_requisito'])) {
    $id_cadena = (int) $_POST['id_cadena'];
    $tipo  = $_POST['tipo'] === 'cromo' ? 'cromo' : 'cadena';
    $valor = (int) ($tipo === 'cromo' ? ($_POST['valor_cromo'] ?? 0) : ($_POST['valor_cadena'] ?? 0));
    $ok = true;
    if ($valor > 0) {
        $ok = $db->crearRequisito($id_cadena, $tipo, $valor);
    }
    header('Location: cadenas.php?requisitos=' . $id_cadena . ($ok ? '' : '&error=ciclo_requisito'));
    exit;
}

// ----- Creación / edición de una cadena (POST desde el modal principal) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre']) && !isset($_POST['nuevo_requisito'])) {
    $id_cadena            = $_POST['id_cadena'] ?? '';
    $nombre               = trim($_POST['nombre'] ?? '');
    $descripcion          = trim($_POST['descripcion'] ?? '');
    $anfitrion            = trim($_POST['anfitrion'] ?? '');
    $orden                = (int) ($_POST['orden'] ?? 0);
    $activa               = isset($_POST['activa']) ? 1 : 0;
    $formacion_recompensa = $_POST['formacion_recompensa'] ?? '';
    $fecha_fin            = trim($_POST['fecha_fin'] ?? '');

    if ($id_cadena !== '') {
        $db->actualizarCadenaAdmin((int) $id_cadena, $nombre, $descripcion, $anfitrion, $orden, $activa, $formacion_recompensa, $fecha_fin);
        $idParaEditor = (int) $id_cadena;
    } else {
        $idParaEditor = $db->crearCadenaAdmin($nombre, $descripcion, $anfitrion, $orden, $activa, $formacion_recompensa, $fecha_fin);
    }

    // Una cadena recién creada no tiene mapa: se manda directa al editor para
    // que el siguiente paso natural sea dibujar el primer nodo, no volver a
    // esta lista sin nada más que hacer.
    if ($id_cadena === '') {
        header('Location: cadena_editor.php?id=' . $idParaEditor);
    } else {
        header('Location: cadenas.php');
    }
    exit;
}

$cadenas = $db->listarCadenasAdmin();

$idRequisitos = isset($_GET['requisitos']) ? (int) $_GET['requisitos'] : null;
$cadenaRequisitos = $idRequisitos ? $db->obtenerCadenaAdmin($idRequisitos) : null;
$requisitos = $idRequisitos ? $db->listarRequisitosAdmin($idRequisitos) : [];
$todasCadenas = $cadenas; // para el select "completar esta otra cadena antes"
$cromosParaRequisito = $idRequisitos ? $db->listarCromosAdmin() : [];

$base         = '../';
$paginaTitulo = 'Cadenas PvE — Panel';
$paginaDesc   = 'Crea y edita las Cadenas de Partido (PvE): el mapa de nodos, los rivales y el botín.';
$cssExtra     = ['panel/assets/css/admin.css'];
include __DIR__ . '/../partials/head.php';

$activeAdmin = 'cadenas';
?>

<div class="admin-shell">
  <?php include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main" id="contenido">
    <div class="admin-head">
      <div>
        <h1>Cadenas PvE</h1>
        <p>Cada cadena es un mapa de nodos (partidos y cofres). Aquí se crean sus datos generales; el mapa, los rivales y el botín se editan en el editor visual.</p>
      </div>
      <button type="button" class="btn btn-primary" onclick="abrirModalCadena()">
        <i class="ph ph-plus" aria-hidden="true"></i> Nueva cadena
      </button>
    </div>

    <?php if (($_GET['error'] ?? '') === 'progreso_en_uso'): ?>
    <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--space-5);">
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span>No se pudo eliminar por un error inesperado. Inténtalo de nuevo.</span>
    </div>
    <?php endif; ?>

    <div class="tabla-wrap">
      <table class="tabla">
        <thead>
          <tr>
            <th scope="col">Cadena</th>
            <th scope="col">Orden</th>
            <th scope="col">Nodos</th>
            <th scope="col">Recompensa final</th>
            <th scope="col">Estado</th>
            <th scope="col" style="text-align:right;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cadenas as $c):
              $caducada = $c['fecha_fin'] && strtotime($c['fecha_fin']) <= time();
          ?>
          <tr>
            <td>
              <div class="admin-cell-title"><?= htmlspecialchars($c['nombre']) ?></div>
              <div class="admin-cell-sub">ID #<?= (int) $c['id_cadena'] ?><?= $c['anfitrion'] ? ' · ' . htmlspecialchars($c['anfitrion']) : '' ?></div>
            </td>
            <td class="mono"><?= (int) $c['orden'] ?></td>
            <td class="mono"><?= (int) $c['total_nodos'] ?></td>
            <td class="mono"><?= $c['formacion_recompensa'] ? htmlspecialchars($c['formacion_recompensa']) : '—' ?></td>
            <td>
              <?php if ($caducada): ?>
              <span class="status-pill esta-inactivo">Caducada</span>
              <?php elseif ($c['activa']): ?>
              <span class="status-pill esta-activo">Activa</span>
              <?php else: ?>
              <span class="status-pill esta-inactivo">Inactiva</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <a class="icon-btn" title="Editar el mapa" href="cadena_editor.php?id=<?= (int) $c['id_cadena'] ?>">
                  <i class="ph ph-flow-arrow" aria-hidden="true"></i>
                </a>
                <a class="icon-btn" title="Requisitos de entrada" href="cadenas.php?requisitos=<?= (int) $c['id_cadena'] ?>">
                  <i class="ph ph-lock-key" aria-hidden="true"></i>
                </a>
                <button type="button" class="icon-btn" title="Editar datos"
                        onclick='abrirModalCadena(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'>
                  <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                </button>
                <button type="button" class="icon-btn es-peligro" title="Eliminar"
                        onclick="pedirBorradoCadena('<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>', <?= (int) $c['id_cadena'] ?>)">
                  <i class="ph ph-trash" aria-hidden="true"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($cadenas)): ?>
          <tr><td colspan="6" style="text-align:center; color:var(--frost-dim); padding:40px;">Todavía no hay ninguna cadena. Crea la primera con el botón de arriba.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <p class="t-caption t-dim" style="margin-top:var(--space-4);"><b class="mono"><?= count($cadenas) ?></b> cadenas</p>
  </main>
</div>

<!-- Modal crear / editar cadena -->
<div class="modal" id="modalCadena" role="dialog" aria-modal="true" aria-labelledby="modalCadenaTitulo" aria-hidden="true">
  <div class="modal-caja">
    <div class="modal-head">
      <h2 id="modalCadenaTitulo">Nueva cadena</h2>
      <button type="button" class="modal-cerrar" onclick="cerrarModalCadena()" aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <form method="POST" action="cadenas.php" id="formCadena">
      <?= csrfCampo() ?>
      <input type="hidden" name="id_cadena" id="fc_id_cadena">

      <div class="form-grid">
        <div class="campo campo-full">
          <label for="fc_nombre">Nombre</label>
          <input type="text" name="nombre" id="fc_nombre" placeholder="Ej. Ruta de ascenso" required>
        </div>

        <div class="campo campo-full">
          <label for="fc_descripcion">Descripción</label>
          <textarea name="descripcion" id="fc_descripcion" rows="2"></textarea>
        </div>

        <div class="campo">
          <label for="fc_anfitrion">Anfitrión</label>
          <input type="text" name="anfitrion" id="fc_anfitrion" placeholder="Ej. Escuadra Fantasma">
        </div>

        <div class="campo">
          <label for="fc_orden">Orden de presentación</label>
          <input type="number" name="orden" id="fc_orden" value="0" min="0">
        </div>

        <div class="campo">
          <label for="fc_formacion_recompensa">Formación que desbloquea al completarla</label>
          <select name="formacion_recompensa" id="fc_formacion_recompensa">
            <option value="">Ninguna</option>
            <?php foreach (Tcg::FORMACIONES as $clave => $f): ?>
            <option value="<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($f['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="fc_fecha_fin">Caduca el (opcional)</label>
          <input type="date" name="fecha_fin" id="fc_fecha_fin">
        </div>

        <div class="campo campo-full">
          <div class="fila-interruptor">
            <span>Cadena activa (visible en el sitio)</span>
            <label class="interruptor">
              <input type="checkbox" name="activa" id="fc_activa">
              <span class="interruptor-riel"></span>
            </label>
          </div>
        </div>
      </div>

      <div class="modal-pie">
        <button type="button" class="btn btn-ghost" onclick="cerrarModalCadena()">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="fc_submit">Crear cadena</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal de requisitos de entrada, abierto directamente si ?requisitos=ID viene en la URL -->
<div class="modal<?= $cadenaRequisitos ? ' is-abierto' : '' ?>" id="modalRequisitos" role="dialog" aria-modal="true" aria-labelledby="modalRequisitosTitulo" aria-hidden="<?= $cadenaRequisitos ? 'false' : 'true' ?>">
  <div class="modal-caja">
    <div class="modal-head">
      <h2 id="modalRequisitosTitulo">Requisitos — <?= $cadenaRequisitos ? htmlspecialchars($cadenaRequisitos['nombre']) : '' ?></h2>
      <a class="modal-cerrar" href="cadenas.php" aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </a>
    </div>

    <?php if ($cadenaRequisitos): ?>
    <?php if (($_GET['error'] ?? '') === 'ciclo_requisito'): ?>
    <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--space-4);">
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span>Ese requisito cerraría un ciclo: esa cadena (directa o indirectamente) ya exige completar esta. Ninguna de las dos se podría empezar nunca.</span>
    </div>
    <?php endif; ?>
    <div class="tabla-wrap">
      <table class="tabla">
        <thead><tr><th>Tipo</th><th>Exige</th><th style="text-align:right;">Quitar</th></tr></thead>
        <tbody>
          <?php foreach ($requisitos as $r): ?>
          <tr>
            <td><?= $r['tipo'] === 'cadena' ? 'Completar cadena' : 'Tener carta' ?></td>
            <td><?= htmlspecialchars($r['nombre_valor'] ?? ('#' . $r['valor'])) ?></td>
            <td style="text-align:right;">
              <a class="icon-btn es-peligro" title="Quitar"
                 href="cadenas.php?eliminar_requisito=<?= (int) $r['id_requisito'] ?>&id_cadena=<?= (int) $cadenaRequisitos['id_cadena'] ?>&csrf=<?= urlencode(csrfToken()) ?>">
                <i class="ph ph-trash" aria-hidden="true"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($requisitos)): ?>
          <tr><td colspan="3" style="text-align:center; color:var(--frost-dim);">Sin requisitos: se puede entrar libremente.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <form method="POST" action="cadenas.php" class="form-grid" style="margin-top:var(--space-4);">
      <?= csrfCampo() ?>
      <input type="hidden" name="nuevo_requisito" value="1">
      <input type="hidden" name="id_cadena" value="<?= (int) $cadenaRequisitos['id_cadena'] ?>">

      <div class="campo">
        <label for="fr_tipo">Tipo</label>
        <select name="tipo" id="fr_tipo" onchange="SRF.cadenasAlternarTipoRequisito(this.value)">
          <option value="cadena">Haber completado otra cadena</option>
          <option value="cromo">Tener una carta concreta</option>
        </select>
      </div>

      <div class="campo" id="fr_grupo_cadena">
        <label for="fr_valor_cadena">Cadena exigida</label>
        <select name="valor_cadena" id="fr_valor_cadena">
          <?php foreach ($todasCadenas as $tc):
              if ((int) $tc['id_cadena'] === (int) $cadenaRequisitos['id_cadena']) continue;
          ?>
          <option value="<?= (int) $tc['id_cadena'] ?>"><?= htmlspecialchars($tc['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="campo" id="fr_grupo_cromo" hidden>
        <label for="fr_valor_cromo">Carta exigida</label>
        <select name="valor_cromo" id="fr_valor_cromo">
          <?php foreach ($cromosParaRequisito as $cr): ?>
          <option value="<?= (int) $cr['id_cromo'] ?>"><?= htmlspecialchars($cr['nombre']) ?> (<?= htmlspecialchars($cr['equipo']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="campo campo-full">
        <button type="submit" class="btn btn-plano">Añadir requisito</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../partials/confirmar.php'; ?>

<?php $pieCompleto = false; include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= htmlspecialchars(assetUrl($base, 'panel/assets/js/scriptCadenas.js')) ?>"></script>
</body>
</html>
