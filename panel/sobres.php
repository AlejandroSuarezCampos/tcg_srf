<?php
require_once __DIR__ . '/../db/conexion.php';

// ----- Borrado (?eliminar=ID) -----
if (isset($_GET['eliminar'])) {
    $db->eliminarSobre((int) $_GET['eliminar']);
    header('Location: sobres.php');
    exit;
}

// ----- Creación / edición (POST desde el modal) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_sobre     = $_POST['id_sobre'] ?? '';
    $nombre       = trim($_POST['nombre'] ?? '');
    $cantidad     = (int) ($_POST['cantidad'] ?? 1);
    $precio       = (int) ($_POST['precio'] ?? 0);
    $imagen       = trim($_POST['imagen'] ?? '');
    $id_expansion = (int) ($_POST['id_expansion'] ?? 0);
    $activo       = isset($_POST['activo']) ? 1 : 0;

    if ($id_sobre !== '') {
        $db->actualizarSobre((int) $id_sobre, $nombre, $cantidad, $precio, $imagen, $id_expansion, $activo);
    } else {
        $db->crearSobre($nombre, $cantidad, $precio, $imagen, $id_expansion, $activo);
    }

    header('Location: sobres.php');
    exit;
}

$expansiones = $db->listarExpansiones();
$sobres      = $db->listarSobresAdmin();

$filtroTexto = trim($_GET['q'] ?? '');
if ($filtroTexto !== '') {
    $sobres = array_values(array_filter($sobres, fn($s) => stripos($s['nombre'], $filtroTexto) !== false));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de sobres — Panel de control</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="./assets/css/admin.css">

</head>
<body>

<div class="admin-shell">
  <?php $activeAdmin = 'sobres'; include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main">
    <div class="admin-head">
      <div>
        <h1>Sobres</h1>
        <p>Crea, edita y elimina los sobres que se pueden comprar en la tienda.</p>
      </div>
      <button class="btn btn-primary" onclick="abrirModalSobre()">+ Nuevo sobre</button>
    </div>

    <form method="GET" class="admin-toolbar">
      <div class="admin-search">
        🔍 <input type="text" name="q" value="<?= htmlspecialchars($filtroTexto) ?>" placeholder="Buscar sobre por nombre...">
      </div>
      <button type="submit" class="btn btn-ghost" style="padding:10px 20px; font-size:12px;">Buscar</button>
    </form>

    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Sobre</th>
            <th>Expansión</th>
            <th>Cartas</th>
            <th>Precio</th>
            <th>Estado</th>
            <th style="text-align:right;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sobres as $s):
              $imagenPanel = $s['imagen'] ? '.' . $s['imagen'] : '../assets/img/perfil/apple-icon-120x120.png';
          ?>
          <tr>
            <td>
              <div class="admin-row-main">
                <img class="admin-thumb" src="<?= htmlspecialchars($imagenPanel) ?>" alt="">
                <div>
                  <div class="admin-cell-title"><?= htmlspecialchars($s['nombre']) ?></div>
                  <div class="admin-cell-sub">ID #<?= $s['id_sobre'] ?></div>
                </div>
              </div>
            </td>
            <td><?= htmlspecialchars($s['expansion']) ?></td>
            <td><?= (int) $s['cantidad'] ?> cartas</td>
            <td><?= number_format($s['precio']) ?> ⛁</td>
            <td>
              <?php if ($s['activo']): ?>
              <span class="status-pill status-on">Activo</span>
              <?php else: ?>
              <span class="status-pill status-off">Inactivo</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <button class="icon-btn" title="Editar" onclick='abrirModalSobre(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)'><i class="bi bi-pencil-square"></i></button>
                <button class="icon-btn danger" title="Eliminar" onclick="confirmarBorrado('<?= htmlspecialchars($s['nombre'], ENT_QUOTES) ?>', <?= $s['id_sobre'] ?>)"><i class="bi bi-trash"></i></button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($sobres)): ?>
          <tr><td colspan="6" style="text-align:center; color:var(--frost-dim); padding:40px;">No se encontraron sobres con ese nombre.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="admin-pagination">
      <span class="collection-count"><b><?= count($sobres) ?></b> sobres mostrados</span>
    </div>
  </main>
</div>

<!-- Modal crear / editar sobre -->
<div class="modal-backdrop" id="modalSobre">
  <div class="modal-box">
    <div class="modal-box-inner">
      <div class="modal-head">
        <h2 id="modalSobreTitulo">Nuevo sobre</h2>
        <button class="modal-close" onclick="cerrarModalSobre()">✕</button>
      </div>

      <form method="POST" action="sobres.php" id="formSobre">
        <input type="hidden" name="id_sobre" id="fs_id_sobre">

        <div class="form-grid">
          <div class="field field-full">
            <label>Nombre del sobre</label>
            <input type="text" name="nombre" id="fs_nombre" placeholder="Ej. Sobre Legendario" required>
          </div>

          <div class="field field-full">
            <label>Imagen</label>
            <div class="thumb-upload">
              <img class="admin-thumb" id="fs_preview" src="../assets/img/perfil/apple-icon-120x120.png" alt="">
              <div class="thumb-upload-text">
                <b>Pega la ruta de la imagen</b>
                Igual que en el resto del proyecto: <code>./assets/img/Sobres/...</code>
              </div>
            </div>
            <input type="text" name="imagen" id="fs_imagen" placeholder="./assets/img/Sobres/..." style="margin-top:10px;">
          </div>

          <div class="field">
            <label>Expansión</label>
            <select name="id_expansion" id="fs_id_expansion">
              <?php foreach ($expansiones as $ex): ?>
              <option value="<?= $ex['id_expansion'] ?>"><?= htmlspecialchars($ex['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Cartas por sobre</label>
            <input type="number" name="cantidad" id="fs_cantidad" min="1" placeholder="5" required>
          </div>

          <div class="field field-full">
            <label>Precio (monedas)</label>
            <input type="number" name="precio" id="fs_precio" min="1" placeholder="Ej. 150" required>
          </div>

          <div class="field field-full">
            <div class="toggle-row">
              <span>Sobre activo (visible en la tienda)</span>
              <label class="switch">
                <input type="checkbox" name="activo" id="fs_activo">
                <span class="switch-track"></span>
              </label>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="cerrarModalSobre()">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="fs_submit">Crear sobre</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="./assets/js/scriptSobres.js"></script>

</body>
</html>