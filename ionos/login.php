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
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$nombreEnviado = trim($_POST['nombre'] ?? '');
	$password      = $_POST['password'] ?? '';

	if ($nombreEnviado === '' || $password === '') {
		$error = 'Escribe tu nombre y tu contraseña.';
	} elseif (($minutos = $db->minutosBloqueoLogin($ip, $nombreEnviado)) > 0) {
		$error = "Demasiados intentos fallidos. Vuelve a intentarlo en $minutos minuto" . ($minutos === 1 ? '' : 's') . '.';
	} else {
		$usuario = $db->verificarLogin($nombreEnviado, $password);

		if ($usuario) {
			$db->limpiarIntentosLogin($ip, $nombreEnviado);

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

		$db->registrarIntentoLoginFallido($ip, $nombreEnviado);
		$error = 'Nombre o contraseña incorrectos.';
	}
}

$paginaTitulo = 'Iniciar sesión';
$paginaDesc   = 'Accede a tu colección de la Superliga Frontier.';
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
        <h1>Iniciar sesión</h1>
        <p class="t-body-sm t-dim">Accede a tu colección, tu álbum y tu actividad en el mercado.</p>
      </div>

      <?php if ($error !== ''): ?>
      <div class="alerta alerta-danger" role="alert">
        <i class="ph ph-warning-circle" aria-hidden="true"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" action="login.php" class="stack stack-5">
        <div class="campo">
          <label for="nombre">Nombre</label>
          <input type="text" id="nombre" name="nombre"
                 value="<?= htmlspecialchars($nombreEnviado) ?>"
                 required autocomplete="username" autofocus>
        </div>

        <div class="campo">
          <label for="password">Contraseña</label>
          <input type="password" id="password" name="password"
                 required autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-bloque">Entrar</button>
      </form>

      <p class="auth-cambio t-body-sm t-dim">
        ¿Todavía no tienes cuenta? <a href="registro.php">Crea la tuya</a>
      </p>
    </div>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

</body>
</html>
