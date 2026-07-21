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
    $id_usuario   = (int) ($_POST['id_usuario'] ?? 0);
    $nuevaClave   = $_POST['nueva_password'] ?? '';
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de usuarios — Panel de control</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="./assets/css/admin.css">
<link rel="icon" type="image/png" href="../assets/img/iconos/favicon.ico">
</head>
<body>

<div class="admin-shell">
  <?php $activeAdmin = 'usuarios'; include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main">
    <div class="admin-head">
      <div>
        <h1>Usuarios</h1>
        <p>Gestiona las cuentas registradas, sus monedas y permisos.</p>
      </div>
      <button class="btn btn-primary" onclick="abrirModalUsuario()">+ Nuevo usuario</button>
    </div>

    <form method="GET" class="admin-toolbar">
      <div class="admin-search">
        🔍 <input type="text" name="q" value="<?= htmlspecialchars($filtroTexto) ?>" placeholder="Buscar por nombre de invocador...">
      </div>
      <button type="submit" class="btn btn-ghost" style="padding:10px 20px; font-size:12px;">Buscar</button>
    </form>

    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Usuario</th>
            <th>Monedas</th>
            <th>Rol</th>
            <th>Registrado</th>
            <th style="text-align:right;">Acciones</th>
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
                  <div class="admin-cell-sub">ID #<?= $u['id_usuario'] ?></div>
                </div>
              </div>
            </td>
            <td><?= number_format($u['monedas']) ?> ⛁</td>
            <td>
              <?php if ($u['dictador']): ?>
              <span class="status-pill status-on">Dictador</span>
              <?php else: ?>
              <span class="status-pill status-off">Jugador</span>
              <?php endif; ?>
            </td>
            <td><?= date('d/m/Y', strtotime($u['fecha_registro'])) ?></td>
            <td>
              <div class="row-actions">
                <button class="icon-btn" title="Editar" onclick='abrirModalUsuario(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)'><i class="bi bi-pencil-square"></i></button>
                <button class="icon-btn danger" title="Eliminar" onclick="confirmarBorrado('<?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>', <?= $u['id_usuario'] ?>)"><i class="bi bi-trash"></i></button>
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

    <div class="admin-pagination">
      <span class="collection-count"><b><?= count($usuarios) ?></b> usuarios mostrados</span>
    </div>
  </main>
</div>

<!-- Modal crear / editar usuario -->
<div class="modal-backdrop" id="modalUsuario">
  <div class="modal-box">
    <div class="modal-box-inner">
      <div class="modal-head">
        <h2 id="modalUsuarioTitulo">Nuevo usuario</h2>
        <button class="modal-close" onclick="cerrarModalUsuario()">✕</button>
      </div>

      <form method="POST" action="usuarios.php" id="formUsuario">
        <input type="hidden" name="id_usuario" id="fu_id_usuario">

        <div class="form-grid">
          <div class="field field-full">
            <label>Nombre de invocador</label>
            <input type="text" name="nombre" id="fu_nombre" placeholder="Ej. KazeStorm_7" required>
          </div>

          <div class="field field-full" id="fu_password_wrap">
            <label>Contraseña</label>
            <input type="password" name="password" id="fu_password" placeholder="Mínimo 6 caracteres" minlength="6">
          </div>

          <div class="field">
            <label>Monedas</label>
            <input type="number" name="monedas" id="fu_monedas" placeholder="0" min="0">
          </div>

          <div class="field">
            <label>&nbsp;</label>
            <div class="toggle-row">
              <span>Es dictador</span>
              <label class="switch">
                <input type="checkbox" name="dictador" id="fu_dictador">
                <span class="switch-track"></span>
              </label>
            </div>
          </div>
        </div>

        <div class="modal-footer" id="fu_reset_footer" style="display:none; justify-content:flex-start; margin-top:0; margin-bottom:20px;">
          <button type="button" class="btn btn-ghost" style="font-size:12px; padding:10px 18px;" onclick="abrirResetPassword()">Restablecer contraseña</button>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="cerrarModalUsuario()">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="fu_submit">Crear usuario</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal restablecer contraseña (acción separada, no toca el resto del usuario) -->
<div class="modal-backdrop" id="modalResetPassword">
  <div class="modal-box" style="max-width:420px;">
    <div class="modal-box-inner">
      <div class="modal-head">
        <h2>Restablecer contraseña</h2>
        <button class="modal-close" onclick="cerrarResetPassword()">✕</button>
      </div>

      <form method="POST" action="usuarios.php" id="formResetPassword">
        <input type="hidden" name="accion" value="reset_password">
        <input type="hidden" name="id_usuario" id="rp_id_usuario">

        <div class="field field-full">
          <label>Nueva contraseña</label>
          <input type="password" name="nueva_password" id="rp_password" placeholder="Mínimo 6 caracteres" minlength="6" required>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="cerrarResetPassword()">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar nueva contraseña</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="./assets/js/scriptUsuarios.js"></script>

</body>
</html>