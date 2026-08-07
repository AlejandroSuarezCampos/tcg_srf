<?php
session_start();
require_once __DIR__ . '/db/conexion.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = $_SESSION['id_usuario'];

$usuario = $db->obtenerUsuario($id_usuario);
if (!$usuario) {
    header('Location: logout.php');
    exit;
}

// Mensajes flash (sobreviven a un único redirect, luego se borran)
$ok    = $_SESSION['config_ok']    ?? '';
$error = $_SESSION['config_error'] ?? '';
unset($_SESSION['config_ok'], $_SESSION['config_error']);

const FOTO_MAX_BYTES   = 4 * 1024 * 1024; // 4 MB
const FOTO_EXTENSIONES = ['jpg', 'jpeg', 'png', 'webp'];
const FOTO_CARPETA_WEB = './assets/img/perfil/';
const FOTO_POR_DEFECTO = './assets/img/perfil/apple-icon-120x120.png';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // ----- Cambiar nombre de invocador -----
    if ($accion === 'cambiar_nombre') {
        $nuevoNombre = trim($_POST['nombre'] ?? '');

        if ($nuevoNombre === '' || mb_strlen($nuevoNombre) > 50) {
            $_SESSION['config_error'] = 'El nombre debe tener entre 1 y 50 caracteres.';
        } elseif ($nuevoNombre === $usuario['nombre']) {
            $_SESSION['config_error'] = 'Ese ya es tu nombre actual.';
        } elseif ($db->comprobarEmailExiste($nuevoNombre)) {
            $_SESSION['config_error'] = 'Ese nombre de invocador ya está en uso.';
        } else {
            $db->actualizarNombreUsuario($id_usuario, $nuevoNombre);
            $_SESSION['nombre']    = $nuevoNombre;
            $_SESSION['config_ok'] = 'Nombre actualizado correctamente.';
        }

        header('Location: configuracion.php');
        exit;
    }

    // ----- Cambiar contraseña -----
    if ($accion === 'cambiar_password') {
        $actual  = $_POST['password_actual'] ?? '';
        $nueva   = $_POST['password_nueva'] ?? '';
        $repetir = $_POST['password_repetir'] ?? '';

        if (!password_verify($actual, $usuario['password_hash'])) {
            $_SESSION['config_error'] = 'La contraseña actual no es correcta.';
        } elseif (mb_strlen($nueva) < 6) {
            $_SESSION['config_error'] = 'La nueva contraseña debe tener al menos 6 caracteres.';
        } elseif ($nueva !== $repetir) {
            $_SESSION['config_error'] = 'Las dos contraseñas nuevas no coinciden.';
        } else {
            $db->restablecerPasswordUsuario($id_usuario, $nueva);
            $_SESSION['config_ok'] = 'Contraseña actualizada correctamente.';
        }

        header('Location: configuracion.php');
        exit;
    }

    // ----- Cambiar foto de perfil -----
    if ($accion === 'cambiar_foto') {
        if (empty($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
            $_SESSION['config_error'] = 'Selecciona antes una imagen.';
            header('Location: configuracion.php');
            exit;
        }

        $archivo = $_FILES['foto'];

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['config_error'] = 'No se pudo subir la imagen. Inténtalo de nuevo.';
        } elseif ($archivo['size'] > FOTO_MAX_BYTES) {
            $_SESSION['config_error'] = 'La imagen pesa demasiado (máximo 4 MB).';
        } else {
            $infoImagen = @getimagesize($archivo['tmp_name']);
            $extension  = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

            if ($infoImagen === false || !in_array($extension, FOTO_EXTENSIONES, true)) {
                $_SESSION['config_error'] = 'El archivo debe ser una imagen JPG, PNG o WEBP.';
            } else {
                $carpetaDisco = __DIR__ . '/' . ltrim(FOTO_CARPETA_WEB, './');
                if (!is_dir($carpetaDisco)) {
                    mkdir($carpetaDisco, 0755, true);
                }

                $nombreArchivo = 'usuario_' . $id_usuario . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                $rutaDisco     = $carpetaDisco . $nombreArchivo;
                $rutaWeb       = FOTO_CARPETA_WEB . $nombreArchivo;

                if (move_uploaded_file($archivo['tmp_name'], $rutaDisco)) {
                    // Borramos la foto anterior SOLO si era una subida previa nuestra
                    // (nunca tocamos el icono por defecto, que es un recurso compartido)
                    $fotoAnterior = $usuario['foto'] ?? '';
                    if (strpos($fotoAnterior, FOTO_CARPETA_WEB . 'usuario_') === 0) {
                        $anteriorDisco = __DIR__ . '/' . ltrim($fotoAnterior, './');
                        if (is_file($anteriorDisco)) {
                            @unlink($anteriorDisco);
                        }
                    }

                    $db->actualizarFotoUsuario($id_usuario, $rutaWeb);
                    $_SESSION['foto']      = $rutaWeb;
                    $_SESSION['config_ok'] = 'Foto de perfil actualizada.';
                } else {
                    $_SESSION['config_error'] = 'No se pudo guardar la imagen en el servidor.';
                }
            }
        }

        header('Location: configuracion.php');
        exit;
    }
}

$fotoWeb   = $usuario['foto'] ?? '';
$fotoDisco = $fotoWeb !== '' ? __DIR__ . '/' . ltrim($fotoWeb, './') : '';
$tieneFoto = $fotoWeb !== '' && is_file($fotoDisco);
$iniciales = mb_strtoupper(mb_substr($usuario['nombre'], 0, 2));

$paginaTitulo = 'Configuración';
$paginaDesc   = 'Gestiona tu nombre, tu contraseña y tu foto de perfil.';
include __DIR__ . '/partials/head.php';

$activePage   = 'perfil';
$navIniciales = $iniciales;
$navMonedas   = $usuario['monedas'];
include __DIR__ . '/navbar.php';
?>

<header class="cabecera">
  <div class="linea-campo" aria-hidden="true"></div>
  <div class="wrap cabecera-contenido">
    <h1>Configuración</h1>
    <p>Gestiona tu nombre, tu contraseña y tu foto de perfil.</p>
  </div>
</header>

<main id="contenido" class="seccion wrap">

  <?php if ($ok): ?>
  <div class="alerta alerta-success" role="status" style="margin-bottom:var(--space-5);">
    <i class="ph ph-check-circle" aria-hidden="true"></i>
    <span><?= htmlspecialchars($ok) ?></span>
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--space-5);">
    <i class="ph ph-warning-circle" aria-hidden="true"></i>
    <span><?= htmlspecialchars($error) ?></span>
  </div>
  <?php endif; ?>

  <div class="ajustes-grid">

    <!-- Foto de perfil -->
    <section class="panel">
      <div class="panel-head">
        <div>
          <h2 class="panel-titulo">Foto de perfil</h2>
          <p class="t-body-sm t-dim" style="margin-top:var(--space-2);">JPG, PNG o WEBP. Máximo 4 MB.</p>
        </div>
      </div>

      <form method="POST" action="configuracion.php" enctype="multipart/form-data" class="stack stack-5">
        <input type="hidden" name="accion" value="cambiar_foto">

        <div class="ajustes-foto">
          <span class="avatar avatar--lg">
            <?php if ($tieneFoto): ?>
              <img src="<?= htmlspecialchars($fotoWeb) ?>" alt="" id="fotoPreview">
            <?php else: ?>
              <img src="" alt="" id="fotoPreview" hidden>
              <span id="fotoIniciales"><?= htmlspecialchars($iniciales) ?></span>
            <?php endif; ?>
          </span>

          <div class="campo">
            <label for="f_foto">Nueva imagen</label>
            <input type="file" name="foto" id="f_foto"
                   accept="image/png,image/jpeg,image/webp" required
                   aria-describedby="fotoNombreArchivo">
            <span class="campo-hint" id="fotoNombreArchivo">Ningún archivo seleccionado.</span>
          </div>
        </div>

        <div><button type="submit" class="btn btn-primary">Guardar foto</button></div>
      </form>
    </section>

    <!-- Nombre -->
    <section class="panel">
      <div class="panel-head">
        <div>
          <h2 class="panel-titulo">Nombre</h2>
          <p class="t-body-sm t-dim" style="margin-top:var(--space-2);">
            Es el nombre que ve el resto de participantes.
          </p>
        </div>
      </div>

      <form method="POST" action="configuracion.php" class="stack stack-5">
        <input type="hidden" name="accion" value="cambiar_nombre">
        <div class="campo">
          <label for="c-nombre">Nombre</label>
          <input type="text" id="c-nombre" name="nombre"
                 value="<?= htmlspecialchars($usuario['nombre']) ?>" maxlength="50" required>
        </div>
        <div><button type="submit" class="btn btn-primary">Guardar nombre</button></div>
      </form>
    </section>

    <!-- Contraseña -->
    <section class="panel">
      <div class="panel-head">
        <div>
          <h2 class="panel-titulo">Contraseña</h2>
          <p class="t-body-sm t-dim" style="margin-top:var(--space-2);">Mínimo 6 caracteres.</p>
        </div>
      </div>

      <form method="POST" action="configuracion.php" class="stack stack-5">
        <input type="hidden" name="accion" value="cambiar_password">
        <div class="campo">
          <label for="c-actual">Contraseña actual</label>
          <input type="password" id="c-actual" name="password_actual" required autocomplete="current-password">
        </div>
        <div class="campo">
          <label for="c-nueva">Nueva contraseña</label>
          <input type="password" id="c-nueva" name="password_nueva" minlength="6" required autocomplete="new-password">
        </div>
        <div class="campo">
          <label for="c-repetir">Repetir nueva contraseña</label>
          <input type="password" id="c-repetir" name="password_repetir" minlength="6" required autocomplete="new-password">
        </div>
        <div><button type="submit" class="btn btn-primary">Guardar contraseña</button></div>
      </form>
    </section>

    <!-- Códigos -->
    <section class="panel">
      <div class="panel-head">
        <div>
          <h2 class="panel-titulo">Canjear un código</h2>
          <p class="t-body-sm t-dim" style="margin-top:var(--space-2);">
            Introduce un código de evento para recibir su recompensa.
          </p>
        </div>
      </div>

      <form id="formCodigo" class="fila" style="align-items:flex-end;">
        <div class="campo" style="flex:1; min-width:180px;">
          <label for="inputCodigo">Código</label>
          <input type="text" id="inputCodigo" name="codigo" maxlength="50" required
                 placeholder="BIENVENIDA2026" style="text-transform:uppercase;">
        </div>
        <button type="submit" class="btn btn-primary">Canjear</button>
      </form>

      <p id="codigoFeedback" class="campo-hint" role="status" aria-live="polite"
         style="margin-top:var(--space-3);"></p>
    </section>

    <!-- Movimiento y animaciones -->
    <section class="panel">
      <div class="panel-head">
        <div>
          <h2 class="panel-titulo">Animaciones</h2>
          <p class="t-body-sm t-dim" style="margin-top:var(--space-2);">
            La apertura de sobres, el walkout de las cartas raras y las cajas 3D.
            Por defecto se respeta la preferencia de tu sistema operativo.
          </p>
        </div>
      </div>

      <div class="campo">
        <label for="selectAnimaciones">Ceremonias y efectos</label>
        <select id="selectAnimaciones">
          <option value="auto">Automático — seguir a mi sistema</option>
          <option value="si">Activadas siempre</option>
          <option value="no">Desactivadas siempre</option>
        </select>
        <p class="campo-hint" id="animacionesEstado" role="status" aria-live="polite"></p>
      </div>
    </section>

  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

<?= assetScript($base ?? '', 'assets/js/perfil.js') ?>
<?= assetScript($base ?? '', 'assets/js/configuracion.js') ?>

</body>
</html>