<?php
/**
 * ACCESO — entrar y crear cuenta, en una pantalla.
 *
 * Funde `login.php` y `registro.php`, que eran dos páginas casi idénticas
 * enlazadas entre sí: cambiar de una a otra costaba un viaje al servidor para
 * ver el mismo formulario con un campo más.
 *
 * Los dos formularios van en el marcado y se conmuta con `?modo=`. Con
 * JavaScript el cambio es instantáneo (los dos están en el DOM, uno con
 * `hidden`); sin JavaScript las pestañas son enlaces normales y recarga, que es
 * exactamente lo que hacía antes. Nada depende de que el script llegue.
 *
 * NO SE HA TOCADO NADA DE LA SEGURIDAD, y conviene que siga así:
 *   · el freno por intentos fallidos (minutosBloqueoLogin / registrarIntento…)
 *   · session_regenerate_id(true) tras autenticar, contra session fixation
 *   · el guardia de la carrera en el registro: si alguien coge el nombre entre
 *     la comprobación y el INSERT, se aborta en vez de leer al usuario por
 *     nombre — que iniciaría sesión DENTRO DE LA CUENTA DE ESA OTRA PERSONA
 */
session_start();
require_once __DIR__ . '/db/conexion.php';

if (!empty($_SESSION['id_usuario'])) {
    header('Location: hoy');
    exit;
}

$modo          = ($_GET['modo'] ?? '') === 'crear' ? 'crear' : 'entrar';
$error         = '';
$campoError    = '';
$nombreEnviado = '';
$ip            = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

/** Arranca la sesión del usuario recién autenticado y lo manda a su portada. */
function acceso_entrar(array $usuario): void {
    // Contra session fixation: el id con el que llegó no vale ya nada.
    session_regenerate_id(true);
    $_SESSION['id_usuario'] = $usuario['id_usuario'];
    $_SESSION['nombre']     = $usuario['nombre'];
    $_SESSION['foto']       = $usuario['foto'];
    $_SESSION['monedas']    = $usuario['monedas'];
    $_SESSION['dictador']   = (bool) $usuario['dictador'];
    header('Location: hoy');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion        = $_POST['accion'] ?? 'entrar';
    $modo          = $accion === 'crear' ? 'crear' : 'entrar';
    $nombreEnviado = trim($_POST['nombre'] ?? '');
    $password      = $_POST['password'] ?? '';

    if ($accion === 'crear') {
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if ($nombreEnviado === '' || $password === '' || $passwordConfirm === '') {
            $error = 'Rellena los tres campos.';
        } elseif (mb_strlen($nombreEnviado) > 50) {
            $error = 'El nombre no puede pasar de 50 caracteres.';
            $campoError = 'nombre';
        } elseif (mb_strlen($password) < 6) {
            $error = 'La contraseña necesita al menos 6 caracteres.';
            $campoError = 'password';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Las dos contraseñas no coinciden.';
            $campoError = 'password_confirm';
        } elseif ($db->comprobarEmailExiste($nombreEnviado)) {
            $error = 'Ese nombre ya está cogido.';
            $campoError = 'nombre';
        } else {
            $idUsuario = $db->registrarUsuario($nombreEnviado, $password);

            if ($idUsuario === null) {
                /* Alguien registró este mismo nombre en el instante entre la
                   comprobación de arriba y el INSERT (visto en auditoría con
                   registros simultáneos). NO se sigue: leer el usuario por
                   nombre aquí encontraría la cuenta de ESA OTRA PERSONA y la
                   sesión actual iniciaría dentro de ella. */
                $error = 'Ese nombre ya está cogido.';
                $campoError = 'nombre';
            } else {
                acceso_entrar($db->obtenerUsuarioPorNombre($nombreEnviado));
            }
        }
    } else {
        if ($nombreEnviado === '' || $password === '') {
            $error = 'Escribe tu nombre y tu contraseña.';
        } elseif (($minutos = $db->minutosBloqueoLogin($ip, $nombreEnviado)) > 0) {
            $error = 'Demasiados intentos fallidos. Vuelve en ' . $minutos
                   . ' minuto' . ($minutos === 1 ? '' : 's') . '.';
        } else {
            $usuario = $db->verificarLogin($nombreEnviado, $password);
            if ($usuario) {
                $db->limpiarIntentosLogin($ip, $nombreEnviado);
                acceso_entrar($usuario);
            }
            $db->registrarIntentoLoginFallido($ip, $nombreEnviado);
            $error = 'Nombre o contraseña incorrectos.';
        }
    }
}

$paginaTitulo = $modo === 'crear' ? 'Crear cuenta' : 'Entrar';

/* Una description POR MODO. Antes las dos variantes de esta pantalla —que son
   dos URL distintas— compartían la misma frase, así que para un buscador eran
   dos páginas duplicadas describiéndose igual. Y ninguna de las dos decía lo
   que de verdad se hace en ella. */
$paginaDesc = $modo === 'crear'
    ? 'Crea tu cuenta gratis en Superliga Frontier TCG: abre sobres, monta tu once y compite contra otros jugadores.'
    : 'Entra en tu cuenta de Superliga Frontier TCG y vuelve a tu plantilla, tus sobres y tus duelos.';

/* UN SOLO H1 POR PÁGINA. Los dos formularios viven siempre en el HTML (el que
   no toca va con `hidden` y el JS los conmuta sin recargar), así que había DOS
   <h1> en cada carga y ningún rastreador podía saber de qué iba la página.
   El titular del modo activo se queda como h1 y el otro baja a h2: quien llega
   por /acceso?modo=crear ve «Salta al campo» como encabezado principal, que es
   exactamente lo que dice su URL y su título. */
$etqEntrar = $modo === 'entrar' ? 'h1' : 'h2';
$etqCrear  = $modo === 'crear'  ? 'h1' : 'h2';
$cssExtra     = ['assets/css/acceso.css'];
include __DIR__ . '/partials/head.php';
?>

<main id="contenido" class="acceso">
  <div class="rescoldo" aria-hidden="true"></div>
  <div class="trama" aria-hidden="true"></div>

  <div class="ac-caja">
    <a class="ac-volver" href="landing">
      <i class="ph ph-arrow-left" aria-hidden="true"></i> Volver a la portada
    </a>

    <div class="ac-panel filo">
      <a class="marca ac-marca" href="landing">
        <span class="marca-chispa" aria-hidden="true"></span>
        Frontier<span class="marca-tcg">TCG</span>
      </a>

      <?php /* Pestañas. Son ENLACES, no botones: sin JavaScript navegan y la
               pantalla sigue funcionando igual que antes. Con JavaScript
               (`assets/js/acceso.js`) el cambio es instantáneo. */ ?>
      <nav class="ac-pestanas" aria-label="Entrar o crear cuenta">
        <a class="ac-pestana<?= $modo === 'entrar' ? ' es-activa' : '' ?>"
           href="acceso?modo=entrar" data-modo="entrar"
           <?= $modo === 'entrar' ? 'aria-current="page"' : '' ?>>Entrar</a>
        <a class="ac-pestana<?= $modo === 'crear' ? ' es-activa' : '' ?>"
           href="acceso?modo=crear" data-modo="crear"
           <?= $modo === 'crear' ? 'aria-current="page"' : '' ?>>Crear cuenta</a>
      </nav>

      <?php if ($error !== ''): ?>
        <p class="ac-error" role="alert">
          <i class="ph ph-warning-circle" aria-hidden="true"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </p>
      <?php endif; ?>

      <?php /* ---------- ENTRAR ---------- */ ?>
      <form method="POST" action="acceso" class="ac-form" data-panel="entrar" <?= $modo === 'entrar' ? '' : 'hidden' ?>>
        <input type="hidden" name="accion" value="entrar">

        <<?= $etqEntrar ?> class="ac-titulo">Vuelve al campo</<?= $etqEntrar ?>>
        <p class="ac-sub">Tu plantilla está donde la dejaste.</p>

        <div class="campo">
          <label for="e-nombre">Nombre</label>
          <input type="text" id="e-nombre" name="nombre" required autocomplete="username"
                 value="<?= $modo === 'entrar' ? htmlspecialchars($nombreEnviado) : '' ?>">
        </div>

        <div class="campo">
          <label for="e-password">Contraseña</label>
          <input type="password" id="e-password" name="password" required autocomplete="current-password">
        </div>

        <button type="submit" class="ac-boton">Entrar</button>

        <p class="ac-cambio">
          ¿Todavía no juegas?
          <a href="acceso?modo=crear" data-modo="crear">Crea tu cuenta</a>
        </p>
      </form>

      <?php /* ---------- CREAR CUENTA ---------- */ ?>
      <form method="POST" action="acceso" class="ac-form" data-panel="crear" <?= $modo === 'crear' ? '' : 'hidden' ?>>
        <input type="hidden" name="accion" value="crear">

        <<?= $etqCrear ?> class="ac-titulo">Salta al campo</<?= $etqCrear ?>>
        <p class="ac-sub">Treinta segundos y el primer sobre es gratis.</p>

        <div class="campo">
          <label for="c-nombre">Nombre</label>
          <input type="text" id="c-nombre" name="nombre" required autocomplete="username" maxlength="50"
                 value="<?= $modo === 'crear' ? htmlspecialchars($nombreEnviado) : '' ?>"
                 <?= $campoError === 'nombre' ? 'aria-invalid="true"' : '' ?>>
          <span class="campo-ayuda">Así te verán los demás. Hasta 50 caracteres.</span>
        </div>

        <div class="campo">
          <label for="c-password">Contraseña</label>
          <input type="password" id="c-password" name="password" required autocomplete="new-password" minlength="6"
                 <?= $campoError === 'password' ? 'aria-invalid="true"' : '' ?>>
          <span class="campo-ayuda">Mínimo 6 caracteres.</span>
        </div>

        <div class="campo">
          <label for="c-password2">Repite la contraseña</label>
          <input type="password" id="c-password2" name="password_confirm" required autocomplete="new-password"
                 <?= $campoError === 'password_confirm' ? 'aria-invalid="true"' : '' ?>>
        </div>

        <button type="submit" class="ac-boton">Crear cuenta y abrir el sobre</button>

        <p class="ac-cambio">
          ¿Ya tienes cuenta?
          <a href="acceso?modo=entrar" data-modo="entrar">Entra</a>
        </p>
      </form>
    </div>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
<?= assetScript('', 'assets/js/acceso.js') ?>

</body>
</html>
