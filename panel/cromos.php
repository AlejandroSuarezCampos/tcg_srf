<?php
session_start();
require_once __DIR__ . '/../db/conexion.php';
require_once __DIR__ . '/../components/carta.php';
require_once __DIR__ . '/../partials/csrf.php';
require_once __DIR__ . '/../partials/subida_imagen.php';

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
        header('Location: cromos.php?error=csrf');
        exit;
    }
    $borrado = $db->eliminarCromo((int) $_GET['eliminar']);
    header('Location: cromos.php' . ($borrado ? '' : '?error=cromo_en_uso'));
    exit;
}

// ----- Creación / edición (POST desde el modal) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        header('Location: cromos.php?error=csrf');
        exit;
    }
    $id_cromo     = $_POST['id_cromo'] ?? '';
    $nombre       = trim($_POST['nombre'] ?? '');
    $posicion     = $_POST['posicion'] ?? '';
    $descripcion  = trim($_POST['descripcion'] ?? '');
    $imagen       = trim($_POST['imagen'] ?? '');
    $id_expansion = (int) ($_POST['id_expansion'] ?? 0);
    $id_equipo    = (int) ($_POST['id_equipo'] ?? 0);
    $id_rareza    = (int) ($_POST['id_rareza'] ?? 0);
    $id_afinidad  = (int) ($_POST['id_afinidad'] ?? 0);
    $errorSubida  = '';

    // Si se ha subido un archivo, gana a la ruta escrita a mano: se guarda en
    // assets/img/Cromos/<expansión>/ (misma carpeta que usan ya las cartas de
    // esa expansión) con un nombre generado, nunca el que trae el navegador.
    if (!empty($_FILES['imagen_archivo']) && $_FILES['imagen_archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $nombreExpansion = '';
        foreach ($db->listarExpansiones() as $e) {
            if ((int) $e['id_expansion'] === $id_expansion) { $nombreExpansion = $e['nombre']; break; }
        }
        $carpeta = slugCarpetaExpansion($nombreExpansion !== '' ? $nombreExpansion : 'Sin_expansion');
        $carpetaDisco = __DIR__ . '/../assets/img/Cromos/' . $carpeta . '/';
        $carpetaWeb   = './assets/img/Cromos/' . $carpeta . '/';

        $subida = subirImagenPanel($_FILES['imagen_archivo'], $carpetaDisco, $carpetaWeb, $nombre !== '' ? $nombre : 'cromo');
        if ($subida['ok']) {
            $imagen = $subida['ruta'];
        } else {
            $errorSubida = $subida['error'];
        }
    }

    if ($errorSubida !== '') {
        header('Location: cromos.php?error=' . urlencode($errorSubida));
        exit;
    }

    if ($id_cromo !== '') {
        $db->actualizarCromo((int) $id_cromo, $nombre, $posicion, $descripcion, $imagen, $id_expansion, $id_equipo, $id_rareza, $id_afinidad);
    } else {
        $db->crearCromo($nombre, $posicion, $descripcion, $imagen, $id_expansion, $id_equipo, $id_rareza, $id_afinidad);
    }

    // Capa 2: el rasgo de configuración sale del cruce puesto × afinidad, así
    // que cambiar cualquiera de los dos lo invalida. Se rederiva aquí para que
    // una carta nueva nunca se quede sin rasgo y una editada no conserve el que
    // le correspondía antes. No pisa las asignaciones marcadas como manuales.
    $db->derivarRasgosConfiguracion();

    header('Location: cromos.php');
    exit;
}

// ----- Datos para la tabla y los selects del formulario -----
$equipos     = $db->listarEquipos();
$expansiones = $db->listarExpansiones();
$rarezasDB   = $db->listarRarezas();
$afinidades  = $db->listarAfinidades();
$posiciones  = ['POR', 'DF', 'MC', 'DC', 'ENT', 'GER', 'ESCUDO', 'PRESIDENTE'];

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

$base         = '../';
$paginaTitulo = 'Cromos — Panel';
$paginaDesc   = 'Crea, edita y elimina los cromos disponibles en el juego.';
$cssExtra     = ['panel/assets/css/admin.css'];
include __DIR__ . '/../partials/head.php';

$activeAdmin = 'cromos';
?>

<div class="admin-shell">
  <?php include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main" id="contenido">
    <div class="admin-head">
      <div>
        <h1>Cromos</h1>
        <p>Crea, edita y elimina los cromos disponibles en el juego.</p>
      </div>
      <button type="button" class="btn btn-primary" onclick="abrirModalCromo()">
        <i class="ph ph-plus" aria-hidden="true"></i> Nuevo cromo
      </button>
    </div>

    <?php if (($_GET['error'] ?? '') === 'cromo_en_uso'): ?>
    <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--space-5);">
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span>No se pudo eliminar por un error inesperado. Inténtalo de nuevo.</span>
    </div>
    <?php elseif (($_GET['error'] ?? '') === 'csrf'): ?>
    <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--space-5);">
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span>La página ha caducado, inténtalo de nuevo.</span>
    </div>
    <?php elseif (!empty($_GET['error'])): ?>
    <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--space-5);">
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span><?= htmlspecialchars($_GET['error']) ?></span>
    </div>
    <?php endif; ?>

    <form method="GET" class="barra-filtros">
      <div class="campo">
        <label for="c-buscar">Buscar</label>
        <input type="search" name="q" id="c-buscar" value="<?= htmlspecialchars($filtroTexto) ?>"
               placeholder="Nombre del cromo">
      </div>
      <div class="campo">
        <label for="c-expansion">Expansión</label>
        <select name="id_expansion" id="c-expansion" onchange="this.form.submit()">
          <option value="">Todas</option>
          <?php foreach ($expansiones as $ex): ?>
          <option value="<?= (int) $ex['id_expansion'] ?>" <?= (string) $filtroExpansion === (string) $ex['id_expansion'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($ex['nombre']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="barra-filtros-acciones">
        <button type="submit" class="btn btn-ghost">Filtrar</button>
        <?php if ($filtroTexto !== '' || $filtroExpansion !== ''): ?>
        <a class="btn btn-plano" href="cromos.php">Quitar</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="tabla-wrap">
      <table class="tabla">
        <thead>
          <tr>
            <th scope="col">Cromo</th>
            <th scope="col">Equipo</th>
            <th scope="col">Expansión</th>
            <th scope="col">Posición</th>
            <th scope="col">Rareza</th>
            <th scope="col" style="text-align:right;">Acciones</th>
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
                  <div class="admin-cell-sub">ID #<?= (int) $c['id_cromo'] ?></div>
                </div>
              </div>
            </td>
            <td><?= htmlspecialchars($c['equipo']) ?></td>
            <td><?= htmlspecialchars($c['expansion']) ?></td>
            <td><?= htmlspecialchars($c['posicion']) ?></td>
            <td><?= render_rareza((int) $c['id_rareza'], $rarezas[$c['id_rareza']] ?? $c['rareza']) ?></td>
            <td>
              <div class="row-actions">
                <button type="button" class="icon-btn" title="Editar"
                        onclick='abrirModalCromo(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'>
                  <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                </button>
                <button type="button" class="icon-btn es-peligro" title="Eliminar"
                        onclick="pedirBorrado('<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>', <?= (int) $c['id_cromo'] ?>)">
                  <i class="ph ph-trash" aria-hidden="true"></i>
                </button>
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

    <p class="t-caption t-dim" style="margin-top:var(--space-4);"><b class="mono"><?= count($cromos) ?></b> cromos mostrados</p>
  </main>
</div>

<!-- Modal crear / editar cromo -->
<div class="modal" id="modalCromo" role="dialog" aria-modal="true" aria-labelledby="modalCromoTitulo" aria-hidden="true">
  <div class="modal-caja modal-caja--ancha">
    <div class="modal-head">
      <h2 id="modalCromoTitulo">Nuevo cromo</h2>
      <button type="button" class="modal-cerrar" onclick="cerrarModalCromo()" aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <form method="POST" action="cromos.php" id="formCromo" enctype="multipart/form-data">
      <?= csrfCampo() ?>
      <input type="hidden" name="id_cromo" id="f_id_cromo">

      <div class="form-grid">
        <div class="campo campo-full">
          <label for="f_nombre">Nombre del cromo</label>
          <input type="text" name="nombre" id="f_nombre" placeholder="Ej. Mark Evans" required>
        </div>

        <div class="campo campo-full">
          <label>Imagen</label>
          <div class="thumb-upload">
            <img class="admin-thumb" id="f_preview" src="../assets/img/perfil/apple-icon-120x120.png" alt="">
            <div class="thumb-upload-text">
              <b>Sube un archivo o pega la ruta de una imagen ya subida</b>
              <code>./assets/img/Cromos/...</code>
            </div>
          </div>
          <input type="file" name="imagen_archivo" id="f_imagen_archivo" accept="image/png,image/jpeg,image/webp" style="margin-top:var(--space-2);">
          <input type="text" name="imagen" id="f_imagen" placeholder="./assets/img/Cromos/..." style="margin-top:var(--space-2);">
          <span class="campo-hint">Si eliges un archivo, se guarda en <code>assets/img/Cromos/&lt;expansión&gt;/</code> y sustituye a la ruta de abajo.</span>
        </div>

        <div class="campo">
          <label for="f_id_equipo">Equipo</label>
          <select name="id_equipo" id="f_id_equipo">
            <?php foreach ($equipos as $eq): ?>
            <option value="<?= (int) $eq['id_equipo'] ?>"><?= htmlspecialchars($eq['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="f_id_expansion">Expansión</label>
          <select name="id_expansion" id="f_id_expansion">
            <?php foreach ($expansiones as $ex): ?>
            <option value="<?= (int) $ex['id_expansion'] ?>"><?= htmlspecialchars($ex['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="f_posicion">Posición</label>
          <select name="posicion" id="f_posicion">
            <?php foreach ($posiciones as $p): ?>
            <option value="<?= $p ?>"><?= $p ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="f_id_rareza">Rareza</label>
          <select name="id_rareza" id="f_id_rareza">
            <?php foreach ($rarezas as $id => $nombre): ?>
            <option value="<?= (int) $id ?>"><?= htmlspecialchars($nombre) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo campo-full">
          <label for="f_id_afinidad">Afinidad</label>
          <select name="id_afinidad" id="f_id_afinidad">
            <?php foreach ($afinidades as $af): ?>
            <option value="<?= (int) $af['id'] ?>"><?= htmlspecialchars($af['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo campo-full">
          <label for="f_descripcion">Descripción</label>
          <textarea name="descripcion" id="f_descripcion" placeholder="Breve descripción o lore del cromo..."></textarea>
        </div>
      </div>

      <div class="modal-pie">
        <button type="button" class="btn btn-ghost" onclick="cerrarModalCromo()">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="f_submit">Crear cromo</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../partials/confirmar.php'; ?>

<?php $pieCompleto = false; include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= htmlspecialchars(assetUrl($base, 'panel/assets/js/scriptCromos.js')) ?>"></script>
</body>
</html>
