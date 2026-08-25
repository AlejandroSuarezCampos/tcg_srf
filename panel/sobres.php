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

// ----- Borrado (?eliminar=ID) -----
if (isset($_GET['eliminar'])) {
    if (!csrfValido($_GET['csrf'] ?? null)) {
        header('Location: sobres.php?error=csrf');
        exit;
    }
    $db->eliminarSobre((int) $_GET['eliminar']);
    header('Location: sobres.php');
    exit;
}

// ----- Creación / edición (POST desde el modal) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        header('Location: sobres.php?error=csrf');
        exit;
    }
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

$base         = '../';
$paginaTitulo = 'Sobres — Panel';
$paginaDesc   = 'Crea, edita y elimina los sobres que se pueden comprar en la tienda.';
$cssExtra     = ['panel/assets/css/admin.css'];
include __DIR__ . '/../partials/head.php';

$activeAdmin = 'sobres';
?>

<div class="admin-shell">
  <?php include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main" id="contenido">
    <div class="admin-head">
      <div>
        <h1>Sobres</h1>
        <p>Crea, edita y elimina los sobres que se pueden comprar en la tienda.</p>
      </div>
      <button type="button" class="btn btn-primary" onclick="abrirModalSobre()">
        <i class="ph ph-plus" aria-hidden="true"></i> Nuevo sobre
      </button>
    </div>

    <form method="GET" class="barra-filtros">
      <div class="campo">
        <label for="s-buscar">Buscar</label>
        <input type="search" name="q" id="s-buscar" value="<?= htmlspecialchars($filtroTexto) ?>"
               placeholder="Nombre del sobre">
      </div>
      <div class="barra-filtros-acciones">
        <button type="submit" class="btn btn-ghost">Filtrar</button>
        <?php if ($filtroTexto !== ''): ?>
        <a class="btn btn-plano" href="sobres.php">Quitar</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="tabla-wrap">
      <table class="tabla">
        <thead>
          <tr>
            <th scope="col">Sobre</th>
            <th scope="col">Expansión</th>
            <th scope="col">Cartas</th>
            <th scope="col">Precio</th>
            <th scope="col">Estado</th>
            <th scope="col" style="text-align:right;">Acciones</th>
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
                  <div class="admin-cell-sub">ID #<?= (int) $s['id_sobre'] ?></div>
                </div>
              </div>
            </td>
            <td><?= htmlspecialchars($s['expansion']) ?></td>
            <td class="mono"><?= (int) $s['cantidad'] ?> cartas</td>
            <td class="mono"><i class="ph ph-coins" aria-hidden="true"></i> <?= number_format($s['precio'], 0, ',', '.') ?></td>
            <td>
              <?php if ($s['activo']): ?>
              <span class="status-pill esta-activo">Activo</span>
              <?php else: ?>
              <span class="status-pill esta-inactivo">Inactivo</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <button type="button" class="icon-btn" title="Editar"
                        onclick='abrirModalSobre(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)'>
                  <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                </button>
                <button type="button" class="icon-btn es-peligro" title="Eliminar"
                        onclick="pedirBorrado('<?= htmlspecialchars($s['nombre'], ENT_QUOTES) ?>', <?= (int) $s['id_sobre'] ?>)">
                  <i class="ph ph-trash" aria-hidden="true"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($sobres)): ?>
          <tr><td colspan="6" style="text-align:center; color:var(--ceniza); padding:40px;">No se encontraron sobres con ese nombre.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <p class="t-caption t-dim" style="margin-top:var(--e-4);"><b class="mono"><?= count($sobres) ?></b> sobres mostrados</p>
  </main>
</div>

<!-- Modal crear / editar sobre -->
<div class="modal" id="modalSobre" role="dialog" aria-modal="true" aria-labelledby="modalSobreTitulo" aria-hidden="true">
  <div class="modal-caja modal-caja--ancha">
    <div class="modal-head">
      <h2 id="modalSobreTitulo">Nuevo sobre</h2>
      <button type="button" class="modal-cerrar" onclick="cerrarModalSobre()" aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <form method="POST" action="sobres.php" id="formSobre">
      <?= csrfCampo() ?>
      <input type="hidden" name="id_sobre" id="fs_id_sobre">

      <div class="form-grid">
        <div class="campo campo-full">
          <label for="fs_nombre">Nombre del sobre</label>
          <input type="text" name="nombre" id="fs_nombre" placeholder="Ej. Sobre Legendario" required>
        </div>

        <div class="campo campo-full">
          <label>Imagen</label>
          <div class="thumb-upload">
            <img class="admin-thumb" id="fs_preview" src="../assets/img/perfil/apple-icon-120x120.png" alt="">
            <div class="thumb-upload-text">
              <b>Pega la ruta de la imagen</b>
              Igual que en el resto del proyecto: <code>./assets/img/Sobres/...</code>
            </div>
          </div>
          <input type="text" name="imagen" id="fs_imagen" placeholder="./assets/img/Sobres/..." style="margin-top:var(--e-2);">
        </div>

        <div class="campo">
          <label for="fs_id_expansion">Expansión</label>
          <select name="id_expansion" id="fs_id_expansion">
            <?php foreach ($expansiones as $ex): ?>
            <option value="<?= (int) $ex['id_expansion'] ?>"><?= htmlspecialchars($ex['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="fs_cantidad">Cartas por sobre</label>
          <input type="number" name="cantidad" id="fs_cantidad" min="1" placeholder="5" required>
        </div>

        <div class="campo campo-full">
          <label for="fs_precio">Precio (monedas)</label>
          <input type="number" name="precio" id="fs_precio" min="1" placeholder="Ej. 150" required>
        </div>

        <div class="campo campo-full">
          <div class="fila-interruptor">
            <span>Sobre activo (visible en la tienda)</span>
            <label class="interruptor">
              <input type="checkbox" name="activo" id="fs_activo">
              <span class="interruptor-riel"></span>
            </label>
          </div>
        </div>
      </div>

      <div class="modal-pie">
        <button type="button" class="btn btn-ghost" onclick="cerrarModalSobre()">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="fs_submit">Crear sobre</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../partials/confirmar.php'; ?>

<?php $pieCompleto = false; include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= htmlspecialchars(assetUrl($base, 'panel/assets/js/scriptSobres.js')) ?>"></script>
</body>
</html>
