<?php
session_start();
require_once __DIR__ . '/../db/conexion.php';
require_once __DIR__ . '/../rareza-clases.php';

if(isset($_SESSION['dictador'])){
  if($_SESSION['dictador']!=1){
    header("Location: ../landing.php");
  }
}else{
  header("Location: ../landing.php");
}

// ----- Borrado (?eliminar=ID) -----
if (isset($_GET['eliminar'])) {
    $db->eliminarCromo((int) $_GET['eliminar']);
    header('Location: cromos.php');
    exit;
}

// ----- Creación / edición (POST desde el modal) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_cromo     = $_POST['id_cromo'] ?? '';
    $nombre       = trim($_POST['nombre'] ?? '');
    $posicion     = $_POST['posicion'] ?? '';
    $descripcion  = trim($_POST['descripcion'] ?? '');
    $imagen       = trim($_POST['imagen'] ?? '');
    $id_expansion = (int) ($_POST['id_expansion'] ?? 0);
    $id_equipo    = (int) ($_POST['id_equipo'] ?? 0);
    $id_rareza    = (int) ($_POST['id_rareza'] ?? 0);
    $id_afinidad  = (int) ($_POST['id_afinidad'] ?? 0);

    if ($id_cromo !== '') {
        $db->actualizarCromo((int) $id_cromo, $nombre, $posicion, $descripcion, $imagen, $id_expansion, $id_equipo, $id_rareza, $id_afinidad);
    } else {
        $db->crearCromo($nombre, $posicion, $descripcion, $imagen, $id_expansion, $id_equipo, $id_rareza, $id_afinidad);
    }

    header('Location: cromos.php');
    exit;
}

// ----- Datos para la tabla y los selects del formulario -----
$equipos     = $db->listarEquipos();
$expansiones = $db->listarExpansiones();
$rarezasDB   = $db->listarRarezas();
$afinidades  = $db->listarAfinidades();
$posiciones  = ['POR','DF','MC','DC','ENT','GER','ESCUDO','PRESIDENTE'];

$rarezas = [];
foreach ($rarezasDB as $r) {
    $rarezas[$r['id_rareza']] = $r['nombre'];
}

$cromos = $db->listarCromosAdmin();

// ----- Filtros opcionales (buscar por nombre / expansión) -----
$filtroTexto     = trim($_GET['q'] ?? '');
$filtroExpansion = $_GET['id_expansion'] ?? '';

if ($filtroTexto !== '') {
    $cromos = array_values(array_filter($cromos, fn($c) => stripos($c['nombre'], $filtroTexto) !== false));
}
if ($filtroExpansion !== '') {
    $cromos = array_values(array_filter($cromos, fn($c) => (string) $c['id_expansion'] === (string) $filtroExpansion));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de cromos — Panel de control</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="./assets/css/admin.css">
<link rel="icon" type="image/png" href="../assets/img/iconos/favicon.ico">
</head>
<body>

<div class="admin-shell">
  <?php $activeAdmin = 'cromos'; include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main">
    <div class="admin-head">
      <div>
        <h1>Cromos</h1>
        <p>Crea, edita y elimina los cromos disponibles en el juego.</p>
      </div>
      <button class="btn btn-primary" onclick="abrirModalCromo()">+ Nuevo cromo</button>
    </div>

    <form method="GET" class="admin-toolbar">
      <div class="admin-search">
        🔍 <input type="text" name="q" value="<?= htmlspecialchars($filtroTexto) ?>" placeholder="Buscar cromo por nombre...">
      </div>
      <select class="field-inline" name="id_expansion" style="width:200px;" onchange="this.form.submit()">
        <option value="">Todas las expansiones</option>
        <?php foreach ($expansiones as $ex): ?>
        <option value="<?= $ex['id_expansion'] ?>" <?= (string) $filtroExpansion === (string) $ex['id_expansion'] ? 'selected' : '' ?>><?= htmlspecialchars($ex['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-ghost" style="padding:10px 20px; font-size:12px;">Buscar</button>
    </form>

    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Cromo</th>
            <th>Equipo</th>
            <th>Expansión</th>
            <th>Posición</th>
            <th>Rareza</th>
            <th style="text-align:right;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cromos as $c):
              $imagenPanel = $c['imagen'] ? '.' . $c['imagen'] : '../assets/img/perfil/apple-icon-120x120.png';
          ?>
          <tr>
            <td>
              <div class="admin-row-main">
                <img class="admin-thumb" src="<?= htmlspecialchars($imagenPanel) ?>" alt="">
                <div>
                  <div class="admin-cell-title"><?= htmlspecialchars($c['nombre']) ?></div>
                  <div class="admin-cell-sub">ID #<?= $c['id_cromo'] ?></div>
                </div>
              </div>
            </td>
            <td><?= htmlspecialchars($c['equipo']) ?></td>
            <td><?= htmlspecialchars($c['expansion']) ?></td>
            <td><?= htmlspecialchars($c['posicion']) ?></td>
            <td><span class="rarity <?= $claseRarezaPorId[$c['id_rareza']] ?? 'r-comun' ?>"><?= htmlspecialchars($rarezas[$c['id_rareza']] ?? $c['rareza']) ?></span></td>
            <td>
              <div class="row-actions">
                <button class="icon-btn" title="Editar" onclick='abrirModalCromo(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'><i class="bi bi-pencil-square"></i></button>
                <button class="icon-btn danger" title="Eliminar" onclick="confirmarBorrado('<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>', <?= $c['id_cromo'] ?>)"><i class="bi bi-trash"></i></button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($cromos)): ?>
          <tr><td colspan="6" style="text-align:center; color:var(--frost-dim); padding:40px;">No se encontraron cromos con esos filtros.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="admin-pagination">
      <span class="collection-count"><b><?= count($cromos) ?></b> cromos mostrados</span>
    </div>
  </main>
</div>

<!-- Modal crear / editar cromo -->
<div class="modal-backdrop" id="modalCromo">
  <div class="modal-box">
    <div class="modal-box-inner">
      <div class="modal-head">
        <h2 id="modalCromoTitulo">Nuevo cromo</h2>
        <button class="modal-close" onclick="cerrarModalCromo()">✕</button>
      </div>

      <form method="POST" action="cromos.php" id="formCromo">
        <input type="hidden" name="id_cromo" id="f_id_cromo">

        <div class="form-grid">
          <div class="field field-full">
            <label>Nombre del cromo</label>
            <input type="text" name="nombre" id="f_nombre" placeholder="Ej. Mark Evans" required>
          </div>

          <div class="field field-full">
            <label>Imagen</label>
            <div class="thumb-upload">
              <img class="admin-thumb" id="f_preview" src="../assets/img/perfil/apple-icon-120x120.png" alt="">
              <div class="thumb-upload-text">
                <b>Pega la ruta de la imagen</b>
                <code>./assets/img/Cromos/...</code>
              </div>
            </div>
            <input type="text" name="imagen" id="f_imagen" placeholder="./assets/img/Cromos/..." style="margin-top:10px;">
          </div>

          <div class="field">
            <label>Equipo</label>
            <select name="id_equipo" id="f_id_equipo">
              <?php foreach ($equipos as $eq): ?>
              <option value="<?= $eq['id_equipo'] ?>"><?= htmlspecialchars($eq['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Expansión</label>
            <select name="id_expansion" id="f_id_expansion">
              <?php foreach ($expansiones as $ex): ?>
              <option value="<?= $ex['id_expansion'] ?>"><?= htmlspecialchars($ex['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Posición</label>
            <select name="posicion" id="f_posicion">
              <?php foreach ($posiciones as $p): ?>
              <option value="<?= $p ?>"><?= $p ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Rareza</label>
            <select name="id_rareza" id="f_id_rareza">
              <?php foreach ($rarezas as $id => $nombre): ?>
              <option value="<?= $id ?>"><?= htmlspecialchars($nombre) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field field-full">
            <label>Afinidad</label>
            <select name="id_afinidad" id="f_id_afinidad">
              <?php foreach ($afinidades as $af): ?>
              <option value="<?= $af['id'] ?>"><?= htmlspecialchars($af['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field field-full">
            <label>Descripción</label>
            <textarea name="descripcion" id="f_descripcion" placeholder="Breve descripción o lore del cromo..."></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="cerrarModalCromo()">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="f_submit">Crear cromo</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="./assets/js/scriptCromos.js"></script>

</body>
</html>