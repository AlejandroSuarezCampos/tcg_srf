<?php

session_start();

require_once __DIR__ . '/db/conexion.php';

// Si ya hay sesión iniciada, no tiene sentido ver el login de nuevo
if (!empty($_SESSION['id_usuario'])) {
	header('Location: landing.php');
	exit;
}

$error = '';
$nombreEnviado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$nombreEnviado = trim($_POST['nombre'] ?? '');
	$password      = $_POST['password'] ?? '';

	if ($nombreEnviado === '' || $password === '') {
		$error = 'Rellena tu nombre de invocador y tu contraseña.';
	} else {
		$usuario = $db->verificarLogin($nombreEnviado, $password);

		if ($usuario) {
			// Regeneramos el id de sesión para evitar session fixation
			session_regenerate_id(true);

			$_SESSION['id_usuario'] = $usuario['id_usuario'];
			$_SESSION['nombre']     = $usuario['nombre'];
			$_SESSION['foto']       = $usuario['foto'];
			$_SESSION['monedas']    = $usuario['monedas'];
			$_SESSION['dictador']   = (bool) $usuario['dictador'];

			header('Location: landing.php');
			exit;
		}

		$error = 'Nombre de invocador o contraseña incorrectos.';
	}
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión — Inazuma TCG</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" type="image/png" href="assets/img/iconos/favicon.ico">
</head>
<body>

<a href="landing.php" class="auth-back">&larr; Volver al inicio</a>

<div class="auth-shell">
  <div class="hero-grid"></div>
  <svg class="auth-bolt" width="90" height="150" style="top:14%; left:8%;" viewBox="0 0 60 100"><polygon points="35,0 10,55 28,55 20,100 55,40 35,40" fill="#FFD23F"/></svg>
  <svg class="auth-bolt" width="70" height="120" style="bottom:10%; right:9%;" viewBox="0 0 60 100"><polygon points="35,0 10,55 28,55 20,100 55,40 35,40" fill="#6E93FF"/></svg>

  <div class="auth-card">
    <div class="auth-card-inner">
      <div class="auth-logo">
        <span class="auth-eyebrow">Bienvenido de nuevo</span>
        <h1>Iniciar sesión</h1>
        <p class="auth-sub">Accede a tu colección, tus mazos y tu progreso.</p>
      </div>

      <div class="auth-error<?= $error !== '' ? ' show' : '' ?>" id="authError"><?= htmlspecialchars($error) ?></div>

      <form method="POST" action="login.php">
        <div class="field">
          <label for="nombre">Nombre de invocador</label>
          <input type="text" id="nombre" name="nombre" placeholder="Payo Water" value="<?= htmlspecialchars($nombreEnviado) ?>" required autocomplete="username">
        </div>
        <div class="field">
          <label for="password">Contraseña</label>
          <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
        </div>

        <div class="auth-submit">
          <button type="submit" class="btn btn-primary" style="width:100%;">Entrar</button>
        </div>
      </form>

      <div class="auth-divider">o</div>

      <p class="auth-switch">¿Aún no tienes cuenta? <a href="registro.php">Crea tu colección</a></p>
    </div>
  </div>
</div>

</body>
</html>
