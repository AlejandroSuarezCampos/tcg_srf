<?php
require_once __DIR__ . '.\db\conexion.php';

/**
 * Aquí procesarás el envío del formulario cuando lo tengas listo, algo como:
 *
 * if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 *     $nombre   = $_POST['nombre']   ?? '';
 *     $password = $_POST['password'] ?? '';
 *     // ... tu lógica con $db (verificar credenciales, iniciar sesión, etc.)
 * }
 */
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

      <div class="auth-error" id="authError">Nombre de invocador o contraseña incorrectos.</div>

      <form method="POST" action="login.php">
        <div class="field">
          <label for="nombre">Nombre de invocador</label>
          <input type="text" id="nombre" name="nombre" placeholder="KazeStorm_7" required autocomplete="username">
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
