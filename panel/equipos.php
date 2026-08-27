<?php
/**
 * EQUIPOS — alta, edición y borrado.
 *
 * Esta pantalla no existía. `equipos` era una tabla de id y nombre sin ninguna
 * forma de tocarla desde la web: para dar de alta un equipo había que entrar
 * en la base de datos a mano, y por tanto también para crear el primer cromo
 * de un equipo nuevo.
 *
 * El UNIVERSO no está aquí: vive en cada carta (`cromos.universo`, migración
 * `037`). Estuvo en el equipo durante una versión y se movió porque un equipo
 * puede alinear a un personaje del Inazuma original junto a jugadores propios,
 * y con el dato en el equipo eso no se podía contar.
 */
session_start();
require_once __DIR__ . '/../db/conexion.php';
require_once __DIR__ . '/../partials/csrf.php';
require_once __DIR__ . '/../partials/subida_imagen.php';

if (empty($_SESSION['dictador']) || $_SESSION['dictador'] != 1) {
    header('Location: ../landing.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['eliminar']))
    && !csrfValido($_REQUEST['csrf'] ?? null)) {
    header('Location: equipos.php?error=csrf');
    exit;
}

$error = '';

// ----- Borrado (?eliminar=ID) -----
if (isset($_GET['eliminar'])) {
    $r = $db->eliminarEquipoAdmin((int) $_GET['eliminar']);
    header('Location: equipos.php' . ($r['ok'] ? '' : '?error=' . urlencode($r['error'])));
    exit;
}

// ----- Alta / edición -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'])) {
    $id          = trim((string) ($_POST['id_equipo'] ?? ''));
    $nombre      = trim((string) $_POST['nombre']);
    $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
    $escudo      = trim((string) ($_POST['escudo'] ?? ''));

    /* Un archivo subido gana al campo de texto: si alguien sube una imagen Y
       deja una ruta escrita, lo que quiere es la que acaba de subir. */
    if (!empty($_FILES['escudo_archivo']) && $_FILES['escudo_archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $subida = subirImagenPanel(
            $_FILES['escudo_archivo'],
            __DIR__ . '/../assets/img/Escudos/',
            './assets/img/Escudos/',
            $nombre ?: 'equipo'
        );
        if (!$subida['ok']) {
            header('Location: equipos.php?error=' . urlencode($subida['error']));
            exit;
        }
        $escudo = $subida['ruta'];
    }

    $r = $id !== ''
        ? $db->actualizarEquipoAdmin((int) $id, $nombre, $escudo, $descripcion)
        : $db->crearEquipoAdmin($nombre, $escudo, $descripcion);

    header('Location: equipos.php' . ($r['ok'] ? '' : '?error=' . urlencode($r['error'])));
    exit;
}

$equipos = $db->listarEquiposAdmin();

$base         = '../';
$paginaTitulo = 'Equipos — Panel';
$paginaDesc   = 'Alta y edición de los equipos del juego.';
$cssExtra     = ['panel/assets/css/admin.css'];
include __DIR__ . '/../partials/head.php';

$activeAdmin = 'equipos';
?>

<div class="admin-shell">
  <?php include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main" id="contenido">
    <div class="admin-head">
      <div>
        <h1>Equipos</h1>
        <p>
          Cada cromo pertenece a un equipo.
          El universo (Superliga Frontier o Inazuma Eleven) va en cada <b>carta</b>,
          no en el equipo: un equipo puede alinear personajes de los dos.
        </p>
      </div>
      <button type="button" class="btn btn-primary" onclick="abrirModalEquipo()">
        <i class="ph ph-plus" aria-hidden="true"></i> Nuevo equipo
      </button>
    </div>

    <?php if (!empty($_GET['error'])): ?>
    <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--e-5);">
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span><?= htmlspecialchars($_GET['error'] === 'csrf' ? 'La página había caducado. Inténtalo otra vez.' : $_GET['error']) ?></span>
    </div>
    <?php endif; ?>

    <div class="tabla-wrap">
      <table class="tabla">
        <thead>
          <tr>
            <th scope="col">Equipo</th>
            <th scope="col">Cromos</th>
            <th scope="col" style="text-align:right;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($equipos as $eq): ?>
          <tr>
            <td>
              <div class="equipo-celda">
                <span class="equipo-escudo" aria-hidden="true">
                  <?php if (!empty($eq['escudo'])): ?>
                    <img src="<?= htmlspecialchars('.' . $eq['escudo']) ?>" alt="">
                  <?php else: ?>
                    <i class="ph ph-shield"></i>
                  <?php endif; ?>
                </span>
                <span>
                  <span class="admin-cell-title"><?= htmlspecialchars($eq['nombre']) ?></span>
                  <?php if (!empty($eq['descripcion'])): ?>
                    <span class="admin-cell-sub"><?= htmlspecialchars($eq['descripcion']) ?></span>
                  <?php endif; ?>
                </span>
              </div>
            </td>
            <td class="mono"><?= (int) $eq['total_cromos'] ?></td>
            <td>
              <div class="row-actions">
                <button type="button" class="icon-btn" title="Editar"
                        onclick='abrirModalEquipo(<?= htmlspecialchars(json_encode($eq), ENT_QUOTES) ?>)'>
                  <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                </button>
                <?php /* Un equipo con cromos no se puede borrar —`cromos.id_equipo`
                         no admite NULL— así que el botón lo dice en vez de dejar
                         que el intento falle. */ ?>
                <button type="button" class="icon-btn es-peligro"
                        title="<?= (int) $eq['total_cromos'] > 0 ? 'Tiene cromos: cámbialos de equipo antes' : 'Eliminar' ?>"
                        <?= (int) $eq['total_cromos'] > 0 ? 'disabled' : '' ?>
                        onclick="pedirBorradoEquipo('<?= htmlspecialchars($eq['nombre'], ENT_QUOTES) ?>', <?= (int) $eq['id_equipo'] ?>)">
                  <i class="ph ph-trash" aria-hidden="true"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($equipos)): ?>
          <tr><td colspan="3" style="text-align:center; color:var(--ceniza); padding:40px;">
            Todavía no hay ningún equipo. Crea el primero con el botón de arriba.
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <p class="t-caption t-dim" style="margin-top:var(--e-4);">
      <b class="mono"><?= count($equipos) ?></b> equipos
    </p>
  </main>
</div>

<!-- Modal crear / editar equipo -->
<div class="modal" id="modalEquipo" role="dialog" aria-modal="true" aria-labelledby="modalEquipoTitulo" aria-hidden="true">
  <div class="modal-caja">
    <div class="modal-head">
      <h2 id="modalEquipoTitulo">Nuevo equipo</h2>
      <button type="button" class="modal-cerrar" onclick="cerrarModalEquipo()" aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <form method="POST" action="equipos" enctype="multipart/form-data" id="formEquipo">
      <?= csrfCampo() ?>
      <input type="hidden" name="id_equipo" id="fe_id_equipo">

      <div class="form-grid">
        <div class="campo campo-full">
          <label for="fe_nombre">Nombre</label>
          <input type="text" name="nombre" id="fe_nombre" maxlength="100" required
                 placeholder="Ej. Instituto Raimon">
        </div>

        <div class="campo campo-full">
          <label for="fe_descripcion">Descripción (opcional)</label>
          <input type="text" name="descripcion" id="fe_descripcion" maxlength="255">
        </div>

        <div class="campo campo-full">
          <label for="fe_escudo_archivo">Escudo (opcional)</label>
          <div class="editor-escudo">
            <span class="editor-escudo-vista" id="fe_escudo_vista" aria-hidden="true">
              <i class="ph ph-shield"></i>
            </span>
            <div class="editor-escudo-campos">
              <input type="file" name="escudo_archivo" id="fe_escudo_archivo" accept="image/png,image/jpeg,image/webp">
              <input type="text" name="escudo" id="fe_escudo" placeholder="…o pega una ruta ya subida: ./assets/img/Escudos/…">
            </div>
          </div>
        </div>
      </div>

      <div class="modal-pie">
        <button type="button" class="btn btn-ghost" onclick="cerrarModalEquipo()">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="fe_submit">Crear equipo</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../partials/confirmar.php'; ?>
<?php $pieCompleto = false; include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= htmlspecialchars(assetUrl($base, 'panel/assets/js/scriptEquipos.js')) ?>"></script>
</body>
</html>
