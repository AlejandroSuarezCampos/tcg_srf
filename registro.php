<?php

session_start();

require_once __DIR__ . '/db/conexion.php';

// Si ya hay sesión iniciada, no tiene sentido ver el registro de nuevo
if (!empty($_SESSION['id_usuario'])) {
	header('Location: landing.php');
	exit;
}

$error = '';
$nombreEnviado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$nombreEnviado    = trim($_POST['nombre'] ?? '');
	$password         = $_POST['password'] ?? '';
	$passwordConfirm  = $_POST['password_confirm'] ?? '';

	if ($nombreEnviado === '' || $password === '' || $passwordConfirm === '') {
		$error = 'Rellena todos los campos.';
	} elseif (mb_strlen($nombreEnviado) > 50) {
		$error = 'El nombre de invocador es demasiado largo (máx. 50 caracteres).';
	} elseif (mb_strlen($password) < 6) {
		$error = 'La contraseña debe tener al menos 6 caracteres.';
	} elseif ($password !== $passwordConfirm) {
		$error = 'Las contraseñas no coinciden.';
	} elseif ($db->comprobarEmailExiste($nombreEnviado)) {
		$error = 'Ese nombre de invocador ya está en uso.';
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear cuenta — Inazuma TCG</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" type="image/png" href="./assets/img/iconos/favicon.ico">
</head>
<body>

<a href="landing.php" class="auth-back">&larr; Volver al inicio</a>

<div class="auth-shell">
  <div class="hero-grid"></div>
  <svg class="auth-bolt" width="90" height="150" style="top:12%; right:9%;" viewBox="0 0 60 100"><polygon points="35,0 10,55 28,55 20,100 55,40 35,40" fill="#6E93FF"/></svg>
  <svg class="auth-bolt" width="70" height="120" style="bottom:12%; left:8%;" viewBox="0 0 60 100"><polygon points="35,0 10,55 28,55 20,100 55,40 35,40" fill="#FFD23F"/></svg>

  <div class="auth-card">
    <div class="auth-card-inner">
      <div class="auth-logo">
        <h1>Crea tu colección</h1>
        <p class="auth-sub">Regístrate y empieza a formar tu equipo definitivo.</p>
      </div>

      <div class="auth-error<?= $error !== '' ? ' show' : '' ?>" id="authError"><?= htmlspecialchars($error) ?></div>

      <form method="POST" action="registro.php">
        <div class="field">
          <label for="nombre">Nombre de invocador</label>
          <input type="text" id="nombre" name="nombre" placeholder="Payo Water" value="<?= htmlspecialchars($nombreEnviado) ?>" required autocomplete="username">
          <p class="field-hint">Así te verán en el ranking. Podrás cambiarlo más adelante.</p>
        </div>
        <div class="field">
          <label for="password">Contraseña</label>
          <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="new-password" minlength="6">
        </div>
        <div class="field">
          <label for="password_confirm">Confirmar contraseña</label>
          <input type="password" id="password_confirm" name="password_confirm" placeholder="••••••••" required autocomplete="new-password" minlength="6">
        </div>

        <div class="auth-submit">
          <button type="submit" class="btn btn-primary" style="width:100%;">Crear cuenta</button>
        </div>
      </form>

      <div class="auth-divider">o</div>

      <p class="auth-switch">¿Ya tienes cuenta? <a href="./login.php">Inicia sesión</a></p>
    </div>
  </div>
</div>

</body>
</html>
