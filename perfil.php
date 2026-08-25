<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';
require_once __DIR__ . '/partials/cabecera.php';
require_once __DIR__ . '/partials/csrf.php';
// convertirAWebp(): la foto de perfil se guarda comprimida como el resto.
require_once __DIR__ . '/partials/subida_imagen.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = $_SESSION['id_usuario'];

$usuario = $db->obtenerUsuario($id_usuario);
if (!$usuario) {
    // Sesión de un usuario que ya no existe en la BD (p. ej. borrado desde el panel)
    header('Location: logout.php');
    exit;
}

/* ---------------------------------------------------------------------------
   AJUSTES DE LA CUENTA — venían de `configuracion.php`, ahora fusionada aquí.
   Eran dos pantallas que decían las dos «tu cuenta», y las dos tenían el mismo
   bloque «Canjear un código» repetido palabra por palabra. Ahora son una, con
   la pestaña «Ajustes», y el canje vive una sola vez (pestaña «Códigos»).
   Los manejadores van intactos: mismo CSRF, mismos flash, mismas validaciones.
   --------------------------------------------------------------------------- */
// Mensajes flash (sobreviven a un único redirect, luego se borran)
$ok    = $_SESSION['config_ok']    ?? '';
$error = $_SESSION['config_error'] ?? '';
unset($_SESSION['config_ok'], $_SESSION['config_error']);

const FOTO_MAX_BYTES   = 4 * 1024 * 1024; // 4 MB
const FOTO_EXTENSIONES = ['jpg', 'jpeg', 'png', 'webp'];
const FOTO_CARPETA_WEB = './assets/img/perfil/';
const FOTO_POR_DEFECTO = './assets/img/perfil/apple-icon-120x120.png';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        $_SESSION['config_error'] = 'La página ha caducado, inténtalo de nuevo.';
        header('Location: perfil.php#panel-ajustes');
        exit;
    }

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

        header('Location: perfil.php#panel-ajustes');
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

        header('Location: perfil.php#panel-ajustes');
        exit;
    }

    // ----- Cambiar foto de perfil -----
    if ($accion === 'cambiar_foto') {
        if (empty($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
            $_SESSION['config_error'] = 'Selecciona antes una imagen.';
            header('Location: perfil.php#panel-ajustes');
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

                /* La foto de perfil se guarda en WEBP como todo lo demás
                   (ver partials/subida_imagen.php): sale en la barra de
                   navegación de todas las pantallas, así que es de las pocas
                   imágenes que carga absolutamente siempre. */
                $nombreArchivo = 'usuario_' . $id_usuario . '_' . bin2hex(random_bytes(4)) . '.webp';
                $rutaDisco     = $carpetaDisco . $nombreArchivo;
                $guardada      = convertirAWebp($archivo['tmp_name'], $rutaDisco);

                if (!$guardada) {
                    // Sin WebP en el servidor se guarda tal cual: mejor pesada que perdida.
                    $nombreArchivo = 'usuario_' . $id_usuario . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                    $rutaDisco     = $carpetaDisco . $nombreArchivo;
                    $guardada      = move_uploaded_file($archivo['tmp_name'], $rutaDisco);
                }
                $rutaWeb = FOTO_CARPETA_WEB . $nombreArchivo;

                if ($guardada) {
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

        header('Location: perfil.php#panel-ajustes');
        exit;
    }
}

$fotoWeb   = $usuario['foto'] ?? '';
$fotoDisco = $fotoWeb !== '' ? __DIR__ . '/' . ltrim($fotoWeb, './') : '';
$tieneFoto = $fotoWeb !== '' && is_file($fotoDisco);
$iniciales = mb_strtoupper(mb_substr($usuario['nombre'], 0, 2));


$totalCartas          = $db->contarColeccionUsuario($id_usuario);
$totalBloqueadas      = $db->contarBloqueadasUsuario($id_usuario);
$expansionesCompletas = $db->contarExpansionesCompletas($id_usuario);

$coleccionReciente = $db->listarColeccionRecienteUsuario($id_usuario, 8);
$bloqueadas        = $db->listarBloqueadasUsuario($id_usuario);
$anunciosUsuario   = $db->listarAnunciosUsuario($id_usuario);

// Solo mostramos la <img> si el archivo existe de verdad en disco; si no,
// caemos de vuelta a las iniciales para no romper el layout con un icono roto.
$fotoWeb   = $usuario['foto'] ?? '';
$fotoDisco = $fotoWeb !== '' ? __DIR__ . '/' . ltrim($fotoWeb, './') : '';
$tieneFoto = $fotoWeb !== '' && is_file($fotoDisco);
$iniciales = mb_strtoupper(mb_substr($usuario['nombre'], 0, 2));

$paginaTitulo = 'Tu perfil';
$paginaDesc   = 'Tu colección, tus anuncios y tu progreso en la Superliga Frontier.';
include __DIR__ . '/partials/head.php';

$activePage   = 'perfil';
$navIniciales = $iniciales;
$navMonedas   = $usuario['monedas'];
include __DIR__ . '/navbar.php';
?>

<?php
$avatarHtml = '<span class="avatar avatar--lg">'
  . ($tieneFoto ? '<img src="' . htmlspecialchars($fotoWeb) . '" alt="">' : htmlspecialchars($iniciales))
  . '</span>';

cabecera([
  'rotulo'   => 'Tu cuenta',
  'titulo'   => $usuario['nombre'],
  'texto'    => 'En la liga desde el ' . date('d/m/Y', strtotime($usuario['fecha_registro'])) . '.',
  'avatar'   => $avatarHtml,
  'pastilla' => !empty($usuario['dictador']) ? 'Administración' : '',
  'accion'   => '<a class="btn btn-ghost btn-sm" href="#panel-ajustes" data-ir-a-tab="tab-ajustes">'
              . '<i class="ph ph-gear-six" aria-hidden="true"></i> Ajustes</a>',
  'datos'    => [
    [number_format($totalCartas, 0, ',', '.'),  'fichas distintas'],
    [number_format($totalBloqueadas, 0, ',', '.'), 'protegidas'],
    [(int) $expansionesCompletas,               'expansiones completas'],
    [number_format($usuario['monedas'], 0, ',', '.'), 'monedas'],
  ],
]);
?>

<main id="contenido" class="seccion wrap">

  <div class="tabs" role="tablist" aria-label="Secciones del perfil">
    <button class="tab" role="tab" id="tab-reciente" aria-controls="panel-reciente" aria-selected="true">Reciente</button>
    <button class="tab" role="tab" id="tab-protegidas" aria-controls="panel-protegidas" aria-selected="false">Protegidas</button>
    <button class="tab" role="tab" id="tab-anuncios" aria-controls="panel-anuncios" aria-selected="false">Tus anuncios</button>
    <button class="tab" role="tab" id="tab-codigos" aria-controls="panel-codigos" aria-selected="false">Códigos</button>
    <button class="tab" role="tab" id="tab-ajustes" aria-controls="panel-ajustes" aria-selected="false">Ajustes</button>
  </div>

  <div class="tab-panel" role="tabpanel" id="panel-reciente" aria-labelledby="tab-reciente" tabindex="0"
       style="padding-top:var(--e-6);">
    <?php if (empty($coleccionReciente)): ?>
      <div class="vacio">
        <span class="vacio-ico"><i class="ph ph-cards" aria-hidden="true"></i></span>
        <h3>Todavía no tienes cartas</h3>
        <p>Abre tu primer sobre y aparecerán aquí, empezando por la más reciente.</p>
        <a class="btn btn-primary" href="sobres.php">Ir a sobres</a>
      </div>
    <?php else: ?>
      <div class="carta-grid">
        <?php foreach ($coleccionReciente as $c): ?>
          <?php render_carta($c, ['protegida' => !empty($c['bloqueada'])]); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="tab-panel" role="tabpanel" id="panel-protegidas" aria-labelledby="tab-protegidas" tabindex="0"
       style="padding-top:var(--e-6);" hidden>
    <?php if (empty($bloqueadas)): ?>
      <div class="vacio">
        <span class="vacio-ico"><i class="ph ph-lock-simple-open" aria-hidden="true"></i></span>
        <h3>No tienes cartas protegidas</h3>
        <p>Protege una carta desde tu colección para no ponerla a la venta por error.</p>
        <a class="btn btn-ghost" href="plantilla.php">Ir a tu plantilla</a>
      </div>
    <?php else: ?>
      <div class="carta-grid">
        <?php foreach ($bloqueadas as $c): ?>
          <?php render_carta($c, ['protegida' => true]); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="tab-panel" role="tabpanel" id="panel-anuncios" aria-labelledby="tab-anuncios" tabindex="0"
       style="padding-top:var(--e-6);" hidden>
    <?php if (empty($anunciosUsuario)): ?>
      <div class="vacio">
        <span class="vacio-ico"><i class="ph ph-tag" aria-hidden="true"></i></span>
        <h3>Todavía no has puesto nada a la venta</h3>
        <p>Publica tu primer anuncio desde el mercado.</p>
        <a class="btn btn-primary" href="mercado.php">Ir al mercado</a>
      </div>
    <?php else: ?>
      <div class="tabla-wrap">
        <table class="tabla">
          <thead>
            <tr>
              <th scope="col">Carta</th>
              <th scope="col" class="num">Precio</th>
              <th scope="col">Publicado</th>
              <th scope="col">Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($anunciosUsuario as $a): ?>
            <tr>
              <td><?= htmlspecialchars($a['carta']) ?></td>
              <td class="num"><?= number_format($a['precio'], 0, ',', '.') ?></td>
              <td class="mono t-dim"><?= date('d/m/Y', strtotime($a['fecha_publicacion'])) ?></td>
              <td>
                <?php if ($a['activa']): ?>
                  <span class="pastilla pastilla-on">En venta</span>
                <?php else: ?>
                  <span class="pastilla">Cerrado</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="tab-panel" role="tabpanel" id="panel-codigos" aria-labelledby="tab-codigos" tabindex="0"
       style="padding-top:var(--e-6);" hidden>
    <div class="panel" style="max-width:520px;">
      <div class="panel-head">
        <div>
          <h2 class="panel-titulo">Canjear un código</h2>
          <p class="t-body-sm t-dim" style="margin-top:var(--e-2);">
            Introduce un código de evento para recibir su recompensa.
          </p>
        </div>
      </div>

      <form id="formCodigo" class="fila" style="align-items:flex-end;">
        <div class="campo" style="flex:1; min-width:200px;">
          <label for="inputCodigo">Código</label>
          <input type="text" id="inputCodigo" name="codigo" maxlength="50" required
                 placeholder="BIENVENIDA2026" style="text-transform:uppercase;">
        </div>
        <button type="submit" class="btn btn-primary">Canjear</button>
      </form>

      <p id="codigoFeedback" class="campo-hint" role="status" aria-live="polite"
         style="margin-top:var(--e-3);"></p>
    </div>

    <?php /* Volver a ver el tutorial. El propio tutorial lo promete en su
             último paso —"puedes volver a verlo desde tu perfil"— así que
             tiene que estar, y aquí es donde la gente busca lo que es suyo y
             no del juego. */ ?>
    <div class="panel" style="max-width:520px; margin-top:var(--e-5);">
      <div class="panel-head">
        <div>
          <h2 class="panel-titulo">Tutorial de bienvenida</h2>
          <p class="t-body-sm t-dim" style="margin-top:var(--e-2);">
            La vuelta guiada por la web: qué es cada sección, cómo se monta un mazo
            y qué se puede apostar en un duelo.
          </p>
        </div>
      </div>
      <button type="button" class="btn btn-ghost" id="btnRepetirTutorial">
        <i class="ph ph-graduation-cap" aria-hidden="true"></i> Volver a verlo
      </button>
      <p class="campo-hint" id="tutorialFeedback" role="status" aria-live="polite"
         style="margin-top:var(--e-3);"></p>
    </div>
  </div>

  <div class="tab-panel" role="tabpanel" id="panel-ajustes" aria-labelledby="tab-ajustes" tabindex="0"
       style="padding-top:var(--e-6);" hidden>

  <?php if ($ok): ?>
  <div class="alerta alerta-success" role="status" style="margin-bottom:var(--e-5);">
    <i class="ph ph-check-circle" aria-hidden="true"></i>
    <span><?= htmlspecialchars($ok) ?></span>
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--e-5);">
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
          <p class="t-body-sm t-dim" style="margin-top:var(--e-2);">JPG, PNG o WEBP. Máximo 4 MB.</p>
        </div>
      </div>

      <form method="POST" action="perfil.php" enctype="multipart/form-data" class="stack stack-5">
        <?= csrfCampo() ?>
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
          <p class="t-body-sm t-dim" style="margin-top:var(--e-2);">
            Es el nombre que ve el resto de participantes.
          </p>
        </div>
      </div>

      <form method="POST" action="perfil.php" class="stack stack-5">
        <?= csrfCampo() ?>
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
          <p class="t-body-sm t-dim" style="margin-top:var(--e-2);">Mínimo 6 caracteres.</p>
        </div>
      </div>

      <form method="POST" action="perfil.php" class="stack stack-5">
        <?= csrfCampo() ?>
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

    <!-- Movimiento y animaciones -->
    <section class="panel">
      <div class="panel-head">
        <div>
          <h2 class="panel-titulo">Movimiento</h2>
          <p class="t-body-sm t-dim" style="margin-top:var(--e-2);">
            La apertura de sobres, el walkout de las cartas raras y las cajas 3D.
            Elegimos el nivel por ti según tu dispositivo, pero mandas tú:
            si tienes un móvil justo y quieres el espectáculo, pídelo.
          </p>
        </div>
      </div>

      <div class="campo">
        <label for="selectAnimaciones">Nivel de movimiento</label>
        <select id="selectAnimaciones">
          <option value="auto">Automático — el recomendado para tu dispositivo</option>
          <option value="full">Completo — todos los efectos</option>
          <option value="lite">Ligero — sin 3D ni efectos de fondo</option>
          <option value="reduce">Mínimo — sin animaciones</option>
        </select>
        <p class="campo-hint" id="animacionesEstado" role="status" aria-live="polite"></p>
      </div>
    </section>
    </div>
  </div>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

<?= assetScript($base ?? '', 'assets/js/perfil.js') ?>
<?= assetScript($base ?? '', 'assets/js/configuracion.js') ?>

</body>
</html>
