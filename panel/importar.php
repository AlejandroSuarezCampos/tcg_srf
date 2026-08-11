<?php
session_start();
require_once __DIR__ . '/../db/conexion.php';

if (isset($_SESSION['dictador'])) {
    if ($_SESSION['dictador'] != 1) { header('Location: ../landing.php'); exit; }
} else {
    header('Location: ../landing.php'); exit;
}

// ----- Borrado de cartas importadas (?borrar_importadas=1) -----
if (isset($_GET['borrar_importadas'])) {
    $id_expansion_borrar = isset($_GET['id_expansion']) ? (int) $_GET['id_expansion'] : null;
    $borradoResultado = $db->borrarCartasImportadas($id_expansion_borrar);
    header('Location: importar.php?importados_borrados=1&n=' . $borradoResultado['borrados'] . '&retenidas=' . $borradoResultado['en_uso']);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar'])) {
    unset($_SESSION['import_datos'], $_SESSION['import_id_expansion']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['json_datos'])) {
    $contenido = file_get_contents($_FILES['json_datos']['tmp_name']);
    $datos = json_decode($contenido, true);

    if (!is_array($datos) || !isset($datos['equipos']) || !is_array($datos['equipos'])) {
        $error = 'El archivo no parece un datos_oficiales.json válido: falta la clave "equipos".';
    } else {
        $_SESSION['import_datos'] = $datos;
        $_SESSION['import_id_expansion'] = (int) ($_POST['id_expansion'] ?? 0);
    }
}

$previsualizacion = isset($_SESSION['import_datos'])
    ? $db->previsualizarImportacion($_SESSION['import_datos'], (int) $_SESSION['import_id_expansion'])
    : null;

$expansiones = $db->listarExpansiones();
$expansionesImportadas = $db->listarExpansionesConCartasImportadas();

$base         = '../';
$paginaTitulo = 'Importar datos oficiales — Panel';
$paginaDesc   = 'Crea cartas de jugadores, escudos, entrenadores y gerentes a partir del datos_oficiales.json.';
$cssExtra     = ['panel/assets/css/admin.css'];
include __DIR__ . '/../partials/head.php';

$activeAdmin = 'importar';
?>

<div class="admin-shell">
  <?php include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main" id="contenido">
    <div class="admin-head">
      <div>
        <h1>Importar datos oficiales</h1>
        <p>Crea cartas de jugadores, escudos, entrenadores y gerentes a partir del datos_oficiales.json de la Superliga Frontier.</p>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--space-5);">
        <i class="ph ph-warning-circle" aria-hidden="true"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['importados_borrados'])): ?>
      <?php $nBorradas = (int) ($_GET['n'] ?? 0); $nRetenidas = (int) ($_GET['retenidas'] ?? 0); ?>
      <div class="alerta <?= $nRetenidas > 0 ? 'alerta-warning' : 'alerta-success' ?>" role="status" style="margin-bottom:var(--space-5);">
        <i class="ph <?= $nRetenidas > 0 ? 'ph-warning' : 'ph-check-circle' ?>" aria-hidden="true"></i>
        <span>
          <?= $nBorradas ?> cartas importadas borradas.
          <?php if ($nRetenidas > 0): ?>
            <?= $nRetenidas ?> se conservaron por estar en uso (en una colección o en un duelo).
          <?php endif; ?>
        </span>
      </div>
    <?php endif; ?>

    <?php if ($previsualizacion): ?>
      <form method="POST" id="formPrevisualizacion">
        <h2 class="t-h3">Previsualización</h2>
        <ul class="t-body-sm" style="margin:var(--space-3) 0 var(--space-5); padding-left:1.2em;">
          <li><?= $previsualizacion['jugadores_a_crear'] ?> jugadores a crear</li>
          <li><?= $previsualizacion['jugadores_omitidos'] ?> jugadores omitidos (ya existen en esta expansión)</li>
          <li><?= $previsualizacion['equipos_exactos'] ?> equipos ya reconocidos</li>
          <li><?= count($previsualizacion['equipos_nuevos']) ?> equipos nuevos: <?= htmlspecialchars(implode(', ', $previsualizacion['equipos_nuevos'])) ?></li>
          <li><?= $previsualizacion['afinidades_desconocidas'] ?> jugadores con afinidad no reconocida (irán como "no-afi")</li>
          <li><?= $previsualizacion['cartas_equipo_a_crear'] ?> cartas de escudo/entrenador/gerente a crear</li>
          <?php if (!empty($previsualizacion['posiciones_desconocidas'])): ?>
          <li><?= count($previsualizacion['posiciones_desconocidas']) ?> jugadores con posición no reconocida (no se crearán): <?= htmlspecialchars(implode(', ', $previsualizacion['posiciones_desconocidas'])) ?></li>
          <?php endif; ?>
        </ul>

        <?php if (!empty($previsualizacion['equipos_ambiguos'])): ?>
        <h3 class="t-h3" style="margin-bottom:var(--space-2);">Confirma el nombre correcto de estos equipos</h3>
        <p class="t-body-sm t-dim" style="margin-bottom:var(--space-4);">
          Los datos oficiales traen el nombre de este equipo escrito de forma distinta a como ya está en el catálogo
          — probablemente una errata en uno de los dos sitios. ¿Cuál es el nombre correcto?
        </p>
        <div class="tabla-wrap" style="margin-bottom:var(--space-5);">
          <table class="tabla">
            <thead><tr><th scope="col">Equipo</th><th scope="col">¿Cuál es el nombre correcto?</th></tr></thead>
            <tbody>
            <?php foreach ($previsualizacion['equipos_ambiguos'] as $amb): ?>
            <tr>
              <td>
                <?= htmlspecialchars($amb['nombre_json']) ?>
                <span class="t-caption-sm t-dim">(<?= $amb['porcentaje'] ?>% parecido a "<?= htmlspecialchars($amb['candidato_db']['nombre']) ?>")</span>
              </td>
              <td>
                <label class="casilla"><input type="radio" name="equipo_eleccion[<?= htmlspecialchars($amb['id']) ?>]" value="db" checked> "<?= htmlspecialchars($amb['candidato_db']['nombre']) ?>" (como está en el catálogo)</label><br>
                <label class="casilla"><input type="radio" name="equipo_eleccion[<?= htmlspecialchars($amb['id']) ?>]" value="json"> "<?= htmlspecialchars($amb['nombre_json']) ?>" (como viene en los datos oficiales)</label><br>
                <label class="casilla"><input type="radio" name="equipo_eleccion[<?= htmlspecialchars($amb['id']) ?>]" value="otro"> Ninguno de los dos, es:
                  <input type="text" name="equipo_texto[<?= htmlspecialchars($amb['id']) ?>]" placeholder="Nombre correcto" style="width:auto;"></label>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <div style="display:flex; gap:var(--space-3); justify-content:flex-end;">
          <button type="submit" name="cancelar" value="1" class="btn btn-ghost">Cancelar</button>
          <button type="button" id="btnConfirmarImportacion" class="btn btn-primary">Crear cartas</button>
        </div>
      </form>

      <div id="importacionProgreso" style="display:none; margin-top:var(--space-5);">
        <progress id="importacionProgresoBarra" value="0" max="1" style="width:100%"></progress>
        <p id="importacionProgresoTexto" class="t-caption t-dim" style="margin-top:var(--space-2);">Importando…</p>
      </div>

      <div id="importacionResultado" style="display:none; margin-top:var(--space-5);"></div>

    <?php else: ?>
      <form method="POST" enctype="multipart/form-data">
        <div class="campo campo-full">
          <label for="i-json">Archivo datos_oficiales.json</label>
          <input type="file" name="json_datos" id="i-json" accept=".json,application/json" required>
        </div>
        <div class="campo campo-full">
          <label for="i-expansion">Expansión destino</label>
          <select name="id_expansion" id="i-expansion" required>
            <?php foreach ($expansiones as $ex): ?>
            <option value="<?= (int) $ex['id_expansion'] ?>"><?= htmlspecialchars($ex['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:flex; justify-content:flex-end;">
          <button type="submit" class="btn btn-primary">Previsualizar</button>
        </div>
      </form>

      <?php if (!empty($expansionesImportadas)): ?>
      <div class="admin-section-gap">
        <h3 class="t-h3" style="margin-bottom:var(--space-4);">Cartas importadas por expansión</h3>
        <div class="tabla-wrap">
          <table class="tabla">
            <thead><tr><th scope="col">Expansión</th><th scope="col" class="num">Cartas importadas</th><th scope="col"></th></tr></thead>
            <tbody>
            <?php foreach ($expansionesImportadas as $exImp): ?>
            <tr>
              <td><?= htmlspecialchars($exImp['nombre']) ?></td>
              <td class="num mono"><?= (int) $exImp['total'] ?></td>
              <td style="text-align:right;">
                <button type="button" class="btn btn-ghost btn-sm"
                        onclick="pedirBorradoImportados(<?= (int) $exImp['id_expansion'] ?>, '<?= htmlspecialchars(addslashes($exImp['nombre'])) ?>', <?= (int) $exImp['total'] ?>)">
                  Borrar
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</div>

<?php include __DIR__ . '/../partials/confirmar.php'; ?>

<?php $pieCompleto = false; include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= htmlspecialchars(assetUrl($base, 'panel/assets/js/scriptImportar.js')) ?>"></script>
</body>
</html>
