<?php
session_start();
require_once __DIR__ . '/../db/conexion.php';

if(isset($_SESSION['dictador'])){
  if($_SESSION['dictador']!=1){
    header("Location: ../landing.php");
  }
}else{
  header("Location: ../landing.php");
}

// ----- Borrado (?eliminar=ID) -----
if (isset($_GET['eliminar'])) {
    $db->eliminarExpansion((int) $_GET['eliminar']);
    header('Location: expansiones.php');
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de expansiones — Panel de control</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="./assets/css/admin.css">
<link rel="icon" type="image/png" href="../assets/img/iconos/favicon.ico">
</head>
<body>

<div class="admin-shell">
  <?php $activeAdmin = 'expansiones'; include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main">
    <div class="admin-head">
      <div>
        <h1>Expansiones</h1>
        <p>Crea, edita y elimina las expansiones (sets) del juego.</p>
      </div>
      <button class="btn btn-primary" onclick="abrirModalExpansion()">+ Nueva expansión</button>
    </div>

    <form method="GET" class="admin-toolbar">
      <div class="admin-search">
        🔍 <input type="text" name="q" value="<?= htmlspecialchars($filtroTexto) ?>" placeholder="Buscar expansión por nombre...">
      </div>
      <button type="submit" class="btn btn-ghost" style="padding:10px 20px; font-size:12px;">Buscar</button>
    </form>

    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Expansión</th>
            <th>Fecha de salida</th>
            <th>Cromos incluidos</th>
            <th>Estado</th>
            <th style="text-align:right;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($expansiones as $ex):
              $totalCromos = $db->cartasExpansion($ex['id_expansion']);
          ?>
          <tr>
            <td>
              <div class="admin-cell-title"><?= htmlspecialchars($ex['nombre']) ?></div>
              <div class="admin-cell-sub">ID #<?= $ex['id_expansion'] ?></div>
            </td>
            <td><?= date('d/m/Y', strtotime($ex['fecha_salida'])) ?></td>
            <td><?= $totalCromos ?> cromos</td>
            <td>
              <?php if ($ex['activo']): ?>
              <span class="status-pill status-on">Activa</span>
              <?php else: ?>
              <span class="status-pill status-off">Inactiva</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <button class="icon-btn" title="Editar" onclick='abrirModalExpansion(<?= htmlspecialchars(json_encode($ex), ENT_QUOTES) ?>)'><i class="bi bi-pencil-square"></i></button>
                <button class="icon-btn danger" title="Eliminar" onclick="confirmarBorrado('<?= htmlspecialchars($ex['nombre'], ENT_QUOTES) ?>', <?= $ex['id_expansion'] ?>)"><i class="bi bi-trash"></i></button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($expansiones)): ?>
          <tr><td colspan="5" style="text-align:center; color:var(--frost-dim); padding:40px;">No se encontraron expansiones con ese nombre.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="admin-pagination">
      <span class="collection-count"><b><?= count($expansiones) ?></b> expansiones mostradas</span>
    </div>
  </main>
</div>

<!-- Modal crear / editar expansión -->
<div class="modal-backdrop" id="modalExpansion">
  <div class="modal-box">
    <div class="modal-box-inner">
      <div class="modal-head">
        <h2 id="modalExpansionTitulo">Nueva expansión</h2>
        <button class="modal-close" onclick="cerrarModalExpansion()">✕</button>
      </div>

      <form method="POST" action="expansiones.php" id="formExpansion">
        <input type="hidden" name="id_expansion" id="fe_id_expansion">

        <div class="form-grid">
          <div class="field field-full">
            <label>Nombre de la expansión</label>
            <input type="text" name="nombre" id="fe_nombre" placeholder="Ej. Tormenta de Invierno" required>
          </div>

          <div class="field field-full">
            <label>Fecha de salida</label>
            <input type="date" name="fecha_salida" id="fe_fecha_salida">
          </div>

          <div class="field field-full">
            <div class="toggle-row">
              <span>Expansión activa (visible en el sitio)</span>
              <label class="switch">
                <input type="checkbox" name="activo" id="fe_activo">
                <span class="switch-track"></span>
              </label>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="cerrarModalExpansion()">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="fe_submit">Crear expansión</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="./assets/js/scriptExpansiones.js"></script>

</body>
</html>