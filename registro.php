<?php

session_start();

require_once __DIR__ . '/db/conexion.php';

// Si ya hay sesión iniciada, no tiene sentido ver el registro de nuevo
if (!empty($_SESSION['id_usuario'])) {
	header('Location: landing.php');
	exit;
}

$error = '';
$campoError = '';
$nombreEnviado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$nombreEnviado    = trim($_POST['nombre'] ?? '');
	$password         = $_POST['password'] ?? '';
	$passwordConfirm  = $_POST['password_confirm'] ?? '';

	if ($nombreEnviado === '' || $password === '' || $passwordConfirm === '') {
		$error = 'Rellena todos los campos.';
	} elseif (mb_strlen($nombreEnviado) > 50) {
		$error = 'El nombre no puede pasar de 50 caracteres.';
		$campoError = 'nombre';
	} elseif (mb_strlen($password) < 6) {
		$error = 'La contraseña debe tener al menos 6 caracteres.';
		$campoError = 'password';
	} elseif ($password !== $passwordConfirm) {
		$error = 'Las dos contraseñas no coinciden.';
		$campoError = 'password_confirm';
	} elseif ($db->comprobarEmailExiste($nombreEnviado)) {
		$error = 'Ese nombre ya está en uso.';
		$campoError = 'nombre';
	} else {
		$idUsuario = $db->registrarUsuario($nombreEnviado, $password);
		$usuario   = $db->obtenerUsuarioPorNombre($nombreEnviado);

		// Iniciamos sesión automáticamente tras el registro
		session_regenerate_id(true);

		$_SESSION['id_usuario'] = $usuario['id_usuario'];
		$_SESSION['nombre']     = $usuario['nombre'];
		$_SESSION['foto']       = $usuario['foto'];
		$_SESSION['monedas']    = $usuario['monedas'];
		$_SESSION['dictador']   = (bool) $usuario['dictador'];

		header('Location: landing.php');
		exit;
	}
}

$paginaTitulo = 'Crear cuenta';
$paginaDesc   = 'Crea tu cuenta y empieza tu colección de la Superliga Frontier.';
include __DIR__ . '/partials/head.php';
?>

<main id="contenido" class="auth">
  <div class="linea-campo" aria-hidden="true"></div>

  <div class="auth-caja">
    <a class="auth-volver" href="landing.php">
      <i class="ph ph-arrow-left" aria-hidden="true"></i> Volver al inicio
    </a>

    <div class="panel auth-panel">
      <div class="auth-cabecera">
        <span class="logo">Superliga Frontier<span class="logo-punto">·</span>TCG</span>
        <h1>Crear cuenta</h1>
        <p class="t-body-sm t-dim">Solo para participantes de la Superliga Frontier.</p>
      </div>

      <?php if ($error !== ''): ?>
      <div class="alerta alerta-danger" role="alert">
        <i class="ph ph-warning-circle" aria-hidden="true"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" action="registro.php" class="stack stack-5">
        <div class="campo<?= $campoError === 'nombre' ? ' is-error' : '' ?>">
          <label for="nombre">Nombre</label>
          <input type="text" id="nombre" name="nombre"
                 value="<?= htmlspecialchars($nombreEnviado) ?>"
                 maxlength="50" required autocomplete="username" autofocus
                 aria-describedby="hint-nombre">
          <span class="campo-hint" id="hint-nombre">Así te verán el resto de participantes. Podrás cambiarlo después.</span>
        </div>

        <div class="campo<?= $campoError === 'password' ? ' is-error' : '' ?>">
          <label for="password">Contraseña</label>
          <input type="password" id="password" name="password"
                 minlength="6" required autocomplete="new-password"
                 aria-describedby="hint-password">
          <span class="campo-hint" id="hint-password">Mínimo 6 caracteres.</span>
        </div>

        <div class="campo<?= $campoError === 'password_confirm' ? ' is-error' : '' ?>">
          <label for="password_confirm">Repetir contraseña</label>
          <input type="password" id="password_confirm" name="password_confirm"
                 minlength="6" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-bloque">Crear cuenta</button>
      </form>

      <p class="auth-cambio t-body-sm t-dim">
        ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
      </p>
    </div>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

</body>
</html>
