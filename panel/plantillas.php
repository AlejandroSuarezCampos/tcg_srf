<?php
/**
 * PANEL — PLANTILLAS 3D de cajas y sobres (prompt-claude-code-sobres-3d.md, Fase 5).
 * Descarga de la plantilla-guía (GD, coordenadas en Tcg::ZONAS_CAJA/ZONAS_SOBRE),
 * subida + recorte + vista previa con el MISMO componente que usa el juego real
 * (components/caja3d.php) — por eso esta página también carga tokens.css y
 * components.css del juego, antes de admin.css para que admin.css gane en las
 * clases que comparten nombre (.btn, etc.) y no se rompa el resto del panel.
 */
session_start();
require_once __DIR__ . '/../db/conexion.php';
require_once __DIR__ . '/../components/caja3d.php';

if (isset($_SESSION['dictador'])) {
    if ($_SESSION['dictador'] != 1) {
        header("Location: ../landing.php");
        exit;
    }
} else {
    header("Location: ../landing.php");
    exit;
}

// ----- descarga de la plantilla-guía (PNG generado al vuelo con GD) -----
if (isset($_GET['descargar'])) {
    $tipo = $_GET['descargar'];
    if (!in_array($tipo, ['caja_expansion', 'caja_sobre', 'sobre'], true)) {
        http_response_code(400);
        exit('Tipo de plantilla inválido.');
    }
    $img = $db->generarGuiaPlantilla($tipo);
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="plantilla_' . $tipo . '.png"');
    imagepng($img);
    imagedestroy($img);
    exit;
}

// ----- subida de una plantilla ya recortada por el admin -----
$mensaje = null;
$mensajeTipo = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'subir_plantilla') {
    $tipo         = $_POST['tipo'] ?? '';
    $idReferencia = (int) ($_POST['id_referencia'] ?? 0);

    if (!in_array($tipo, ['caja_expansion', 'caja_sobre', 'sobre'], true) || $idReferencia <= 0) {
        $mensaje = 'Petición inválida.';
        $mensajeTipo = 'danger';
    } elseif (empty($_FILES['plantilla']) || $_FILES['plantilla']['error'] !== UPLOAD_ERR_OK) {
        $mensaje = 'No se ha podido subir el archivo (¿se ha seleccionado un PNG?).';
        $mensajeTipo = 'danger';
    } else {
        $resultado = $db->subirPlantilla($tipo, $idReferencia, $_FILES['plantilla']['tmp_name']);
        $mensaje     = $resultado['ok'] ? 'Plantilla actualizada correctamente.' : $resultado['error'];
        $mensajeTipo = $resultado['ok'] ? 'success' : 'danger';
    }
}

$expansiones = $db->listarExpansiones();
$sobresAdmin = $db->listarSobresAdmin();

$sobresPorExpansion = [];
foreach ($sobresAdmin as $s) {
    $sobresPorExpansion[$s['id_expansion']][] = $s;
}

// Franjas de tamaño para que el admin sepa qué recortar sin adivinar
$lienzoCaja  = Tcg::LIENZO_CAJA;
$lienzoSobre = Tcg::LIENZO_SOBRE;

// Tcg::rutasPlantilla() devuelve rutas relativas a la raíz del sitio
// ("./assets/..."), pensadas para páginas como sobres.php. panel/ está un
// nivel por debajo, así que hace falta el mismo ajuste que ya usan
// panel/sobres.php y panel/cromos.php: anteponer un "." más a cada ruta.
function rutasPanel(array $rutas): array {
    return array_map(fn($r) => '.' . $r, $rutas);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Plantillas 3D — Panel de control</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/tokens.css?v=<?= @filemtime(__DIR__ . '/../assets/css/tokens.css') ?>">
<link rel="stylesheet" href="../assets/css/components.css?v=<?= @filemtime(__DIR__ . '/../assets/css/components.css') ?>">
<link rel="stylesheet" href="./assets/css/admin.css?v=<?= @filemtime(__DIR__ . '/assets/css/admin.css') ?>">
<style>
  /* layout propio de esta página, no un componente del sistema de diseño */
  .plantillas-expansion { border: 1px solid var(--line); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 24px; }
  .plantillas-fila { display: flex; flex-wrap: wrap; gap: 24px; margin-top: 16px; }
  .plantillas-item { display: flex; flex-direction: column; gap: 10px; padding: 16px; border: 1px solid var(--line); border-radius: var(--radius-md); background: rgba(255,255,255,.02); min-width: 240px; }
  .plantillas-item h4 { margin: 0; font-size: 14px; }
  .plantillas-item .preview { display: flex; justify-content: center; padding: 12px 0; }
  .plantillas-acciones { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
  .plantillas-acciones input[type="file"] { max-width: 190px; font-size: 12px; color: var(--frost-dim); }
</style>
</head>
<body>

<div class="admin-shell">
  <?php $activeAdmin = 'plantillas'; include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main">
    <div class="admin-head">
      <div>
        <h1>Plantillas 3D</h1>
        <p>Descarga la plantilla-guía de cada caja/sobre, sustituye su arte en Photoshop sin mover las zonas, y súbela aquí. La vista previa usa el motor 3D real del juego.</p>
      </div>
    </div>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?= htmlspecialchars($mensajeTipo) ?>"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php foreach ($expansiones as $ex): ?>
    <?php
      $idExp = $ex['id_expansion'];
      $rutasCajaExp = $db->rutasPlantilla('caja_expansion', $idExp);
      $tiposDeEsta  = $sobresPorExpansion[$idExp] ?? [];
    ?>
    <section class="plantillas-expansion">
      <h2 style="margin:0;"><?= htmlspecialchars($ex['nombre']) ?></h2>
      <p style="color:var(--frost-dim); font-size:13px; margin:4px 0 0;">
        Caja de la expansión (<?= $lienzoCaja['w'] ?>×<?= $lienzoCaja['h'] ?>px) y, por cada sobre, su caja pequeña
        y el propio sobre (<?= $lienzoSobre['w'] ?>×<?= $lienzoSobre['h'] ?>px).
      </p>

      <div class="plantillas-fila">
        <div class="plantillas-item">
          <h4>Caja de la expansión</h4>
          <div class="preview"><?= pack3d_caja_html(rutasPanel($rutasCajaExp), ['escala' => 'pequena']) ?></div>
          <div class="plantillas-acciones">
            <a class="btn btn-ghost" href="plantillas.php?descargar=caja_expansion">
              <i class="bi bi-download"></i> Guía
            </a>
            <form method="POST" action="plantillas.php" enctype="multipart/form-data" style="display:flex; gap:8px; align-items:center;">
              <input type="hidden" name="accion" value="subir_plantilla">
              <input type="hidden" name="tipo" value="caja_expansion">
              <input type="hidden" name="id_referencia" value="<?= $idExp ?>">
              <input type="file" name="plantilla" accept="image/png" required>
              <button type="submit" class="btn btn-primary btn-sm">Subir</button>
            </form>
          </div>
        </div>

        <?php foreach ($tiposDeEsta as $s): ?>
        <?php
          $idSobre       = $s['id_sobre'];
          $rutasCajaTipo = $db->rutasPlantilla('caja_sobre', $idSobre);
          $rutasSobre    = $db->rutasPlantilla('sobre', $idSobre);
        ?>
        <?php
          // vista previa completa (Fase 5 del prompt): 6 sobres de muestra
          // dentro de la caja, para poder validar la apertura de tapa con el
          // motor real sin tener que ir al juego a comprar nada.
          $rutasSobrePanel = rutasPanel($rutasSobre);
          $interiorPreview = '';
          for ($i = 0; $i < 12; $i++) {
              $interiorPreview .= pack3d_sobre_html($rutasSobrePanel, ['indice' => $i, 'total' => 12]);
          }
        ?>
        <?php $idPreview = 'preview-' . $idSobre; ?>
        <div class="plantillas-item">
          <h4><?= htmlspecialchars($s['nombre']) ?> — caja pequeña</h4>
          <div class="preview">
            <div class="pack3d-portal">
              <!-- data-id-sobre="preview-N": misma convención que sobres.php,
                   así SRF.pack3d.onEnvelopeTypeSelected() (sin modificar) la
                   encuentra sin ningún código nuevo. El prefijo evita
                   colisionar con un id_sobre real. -->
              <div class="js-tipo-sobre" role="button" tabindex="0" data-id-sobre="<?= htmlspecialchars($idPreview) ?>">
                <?= pack3d_caja_html(rutasPanel($rutasCajaTipo), ['escala' => 'pequena', 'interiorHtml' => $interiorPreview]) ?>
              </div>
              <button type="button" class="btn btn-ghost pack3d-portal-cerrar js-cerrar-blister">
                <i class="bi bi-x-lg"></i> Cerrar
              </button>
            </div>
          </div>
          <p class="t-caption t-dim" style="margin:0;">Clic en la caja para previsualizar la apertura con el motor real.</p>
          <div class="plantillas-acciones">
            <a class="btn btn-ghost" href="plantillas.php?descargar=caja_sobre">
              <i class="bi bi-download"></i> Guía
            </a>
            <form method="POST" action="plantillas.php" enctype="multipart/form-data" style="display:flex; gap:8px; align-items:center;">
              <input type="hidden" name="accion" value="subir_plantilla">
              <input type="hidden" name="tipo" value="caja_sobre">
              <input type="hidden" name="id_referencia" value="<?= $idSobre ?>">
              <input type="file" name="plantilla" accept="image/png" required>
              <button type="submit" class="btn btn-primary btn-sm">Subir</button>
            </form>
          </div>
        </div>

        <div class="plantillas-item">
          <h4><?= htmlspecialchars($s['nombre']) ?> — sobre</h4>
          <div class="preview"><?= pack3d_sobre_html(rutasPanel($rutasSobre)) ?></div>
          <div class="plantillas-acciones">
            <a class="btn btn-ghost" href="plantillas.php?descargar=sobre">
              <i class="bi bi-download"></i> Guía
            </a>
            <form method="POST" action="plantillas.php" enctype="multipart/form-data" style="display:flex; gap:8px; align-items:center;">
              <input type="hidden" name="accion" value="subir_plantilla">
              <input type="hidden" name="tipo" value="sobre">
              <input type="hidden" name="id_referencia" value="<?= $idSobre ?>">
              <input type="file" name="plantilla" accept="image/png" required>
              <button type="submit" class="btn btn-primary btn-sm">Subir</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($tiposDeEsta)): ?>
        <p style="color:var(--frost-dim); font-size:13px;">Esta expansión todavía no tiene sobres.</p>
        <?php endif; ?>
      </div>
    </section>
    <?php endforeach; ?>

    <?php if (empty($expansiones)): ?>
    <p style="color:var(--frost-dim);">Todavía no hay expansiones creadas.</p>
    <?php endif; ?>
  </main>
</div>

<script src="../assets/js/vendor/gsap/gsap.min.js"></script>
<!-- Fase 5, restricción explícita: "no reescribir el componente 3D del juego
     que ya funciona". La vista previa carga el MISMO sobres.js del juego: el
     tilt (initPackBox), el click de caja → apertura de tapa → stagger de
     sobres (onEnvelopeTypeSelected) y el cierre (js-cerrar-blister) quedan
     cableados solos, sin una sola línea de JS propia de este panel. -->
<script src="../assets/js/sobres.js?v=<?= @filemtime(__DIR__ . '/../assets/js/sobres.js') ?>"></script>

</body>
</html>
