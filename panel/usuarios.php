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
    header('Location: usuarios.php?error=csrf');
    exit;
}
/**
 * Nota de seguridad: no se muestra ni se edita el hash de contraseña de un
 * usuario existente desde este formulario. Al editar, el campo de
 * contraseña se oculta; al crear, se usa para fijar la contraseña inicial.
 * Si necesitas poder resetear la contraseña de alguien, usa el botón
 * "Restablecer contraseña" (llama a $db->restablecerPasswordUsuario()).
 */

// ----- Borrado (?eliminar=ID) -----
if (isset($_GET['eliminar'])) {
    $db->eliminarUsuario((int) $_GET['eliminar']);
    header('Location: usuarios.php');
    exit;
}

// ----- Restablecer contraseña (POST separado del formulario principal) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'reset_password') {
    $id_usuario = (int) ($_POST['id_usuario'] ?? 0);
    $nuevaClave = $_POST['nueva_password'] ?? '';
    if ($id_usuario && strlen($nuevaClave) >= 6) {
        $db->restablecerPasswordUsuario($id_usuario, $nuevaClave);
    }
    header('Location: usuarios.php');
    exit;
}

// ----- Creación / edición (POST desde el modal principal) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario = $_POST['id_usuario'] ?? '';
    $nombre     = trim($_POST['nombre'] ?? '');
    $monedas    = (int) ($_POST['monedas'] ?? 0);
    $dictador   = isset($_POST['dictador']) ? 1 : 0;
    $password   = $_POST['password'] ?? '';

    if ($id_usuario !== '') {
        $db->actualizarUsuarioAdmin((int) $id_usuario, $nombre, $monedas, $dictador);
    } else {
        $db->crearUsuarioAdmin($nombre, $password, $monedas, $dictador);
    }

    header('Location: usuarios.php');
    exit;
}

$usuarios = $db->listarUsuarios();

$filtroTexto = trim($_GET['q'] ?? '');
if ($filtroTexto !== '') {
    $usuarios = array_values(array_filter($usuarios, fn($u) => stripos($u['nombre'], $filtroTexto) !== false));
}

$base         = '../';
$paginaTitulo = 'Usuarios — Panel';
$paginaDesc   = 'Gestiona las cuentas registradas, sus monedas y permisos.';
$cssExtra     = ['panel/assets/css/admin.css'];
include __DIR__ . '/../partials/head.php';

$activeAdmin = 'usuarios';
?>

<div class="admin-shell">
  <?php include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main" id="contenido">
    <div class="admin-head">
      <div>
        <h1>Usuarios</h1>
        <p>Gestiona las cuentas registradas, sus monedas y permisos.</p>
      </div>
      <button type="button" class="btn btn-primary" onclick="abrirModalUsuario()">
        <i class="ph ph-plus" aria-hidden="true"></i> Nuevo usuario
      </button>
    </div>

    <form method="GET" class="barra-filtros">
      <div class="campo">
        <label for="u-buscar">Buscar</label>
        <input type="search" name="q" id="u-buscar" value="<?= htmlspecialchars($filtroTexto) ?>"
               placeholder="Nombre de invocador">
      </div>
      <div class="barra-filtros-acciones">
        <button type="submit" class="btn btn-ghost">Filtrar</button>
        <?php if ($filtroTexto !== ''): ?>
        <a class="btn btn-plano" href="usuarios.php">Quitar</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="tabla-wrap">
      <table class="tabla">
        <thead>
          <tr>
            <th scope="col">Usuario</th>
            <th scope="col">Monedas</th>
            <th scope="col">Rol</th>
            <th scope="col">Registrado</th>
            <th scope="col" style="text-align:right;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usuarios as $u):
              $fotoPanel = $u['foto'] ? '.' . $u['foto'] : '../assets/img/perfil/apple-icon-120x120.png';
          ?>
          <tr>
            <td>
              <div class="admin-row-main">
                <img class="admin-thumb" style="border-radius:50%;" src="<?= htmlspecialchars($fotoPanel) ?>" alt="">
                <div>
                  <div class="admin-cell-title"><?= htmlspecialchars($u['nombre']) ?></div>
                  <div class="admin-cell-sub">ID #<?= (int) $u['id_usuario'] ?></div>
                </div>
              </div>
            </td>
            <td class="mono"><i class="ph ph-coins" aria-hidden="true"></i> <?= number_format($u['monedas'], 0, ',', '.') ?></td>
            <td>
              <?php if ($u['dictador']): ?>
              <span class="status-pill esta-activo">Dictador</span>
              <?php else: ?>
              <span class="status-pill esta-inactivo">Jugador</span>
              <?php endif; ?>
            </td>
            <td class="mono"><?= date('d/m/Y', strtotime($u['fecha_registro'])) ?></td>
            <td>
              <div class="row-actions">
                <button type="button" class="icon-btn" title="Editar"
                        onclick='abrirModalUsuario(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)'>
                  <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                </button>
                <button type="button" class="icon-btn es-peligro" title="Eliminar"
                        onclick="pedirBorrado('<?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>', <?= (int) $u['id_usuario'] ?>)">
                  <i class="ph ph-trash" aria-hidden="true"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($usuarios)): ?>
          <tr><td colspan="5" style="text-align:center; color:var(--frost-dim); padding:40px;">No se encontraron usuarios con ese nombre.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <p class="t-caption t-dim" style="margin-top:var(--space-4);"><b class="mono"><?= count($usuarios) ?></b> usuarios mostrados</p>
  </main>
</div>

<!-- Modal crear / editar usuario -->
<div class="modal" id="modalUsuario" role="dialog" aria-modal="true" aria-labelledby="modalUsuarioTitulo" aria-hidden="true">
  <div class="modal-caja modal-caja--ancha">
    <div class="modal-head">
      <h2 id="modalUsuarioTitulo">Nuevo usuario</h2>
      <button type="button" class="modal-cerrar" onclick="cerrarModalUsuario()" aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <form method="POST" action="usuarios.php" id="formUsuario">
      <?= csrfCampo() ?>
      <input type="hidden" name="id_usuario" id="fu_id_usuario">

      <div class="form-grid">
        <div class="campo campo-full">
          <label for="fu_nombre">Nombre de invocador</label>
          <input type="text" name="nombre" id="fu_nombre" placeholder="Ej. KazeStorm_7" required>
        </div>

        <div class="campo campo-full" id="fu_password_wrap">
          <label for="fu_password">Contraseña</label>
          <input type="password" name="password" id="fu_password" placeholder="Mínimo 6 caracteres" minlength="6">
        </div>

        <div class="campo">
          <label for="fu_monedas">Monedas</label>
          <input type="number" name="monedas" id="fu_monedas" placeholder="0" min="0">
        </div>

        <div class="campo">
          <span class="campo-label">&nbsp;</span>
          <div class="fila-interruptor">
            <span>Es dictador</span>
            <label class="interruptor">
              <input type="checkbox" name="dictador" id="fu_dictador">
              <span class="interruptor-riel"></span>
            </label>
          </div>
        </div>
      </div>

      <div class="modal-pie" id="fu_reset_footer" style="display:none; justify-content:flex-start; margin-top:0; margin-bottom:var(--space-5);">
        <button type="button" class="btn btn-ghost btn-sm" onclick="abrirResetPassword()">Restablecer contraseña</button>
      </div>

      <div class="modal-pie">
        <button type="button" class="btn btn-ghost" onclick="cerrarModalUsuario()">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="fu_submit">Crear usuario</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal restablecer contraseña (acción separada, no toca el resto del usuario) -->
<div class="modal" id="modalResetPassword" role="dialog" aria-modal="true" aria-labelledby="modalResetPasswordTitulo" aria-hidden="true">
  <div class="modal-caja" style="max-width:420px;">
    <div class="modal-head">
      <h2 id="modalResetPasswordTitulo">Restablecer contraseña</h2>
      <button type="button" class="modal-cerrar" onclick="cerrarResetPassword()" aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <form method="POST" action="usuarios.php" id="formResetPassword">
      <?= csrfCampo() ?>
      <input type="hidden" name="accion" value="reset_password">
      <input type="hidden" name="id_usuario" id="rp_id_usuario">

      <div class="campo campo-full">
        <label for="rp_password">Nueva contraseña</label>
        <input type="password" name="nueva_password" id="rp_password" placeholder="Mínimo 6 caracteres" minlength="6" required>
      </div>

      <div class="modal-pie">
        <button type="button" class="btn btn-ghost" onclick="cerrarResetPassword()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar nueva contraseña</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../partials/confirmar.php'; ?>

<?php $pieCompleto = false; include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= htmlspecialchars(assetUrl($base, 'panel/assets/js/scriptUsuarios.js')) ?>"></script>
</body>
</html>
