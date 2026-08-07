<?php
session_start();
require_once __DIR__ . '/../db/conexion.php';

if (isset($_SESSION['dictador'])) {
    if ($_SESSION['dictador'] != 1) { header("Location: ../landing.php"); exit; }
} else {
    header("Location: ../landing.php"); exit;
}

$error = '';
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar']) && isset($_SESSION['import_datos'])) {
    $decisiones = [];
    foreach ($_POST['equipo_eleccion'] ?? [] as $idEquipoJson => $eleccion) {
        $decisiones[$idEquipoJson] = ['eleccion' => $eleccion, 'texto' => $_POST['equipo_texto'][$idEquipoJson] ?? ''];
    }
    $resultado = $db->ejecutarImportacion($_SESSION['import_datos'], (int) $_SESSION['import_id_expansion'], $decisiones);
    unset($_SESSION['import_datos'], $_SESSION['import_id_expansion']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar'])) {
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Importar datos oficiales — Panel de control</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="./assets/css/admin.css">
<link rel="icon" type="image/png" href="../assets/img/iconos/favicon.ico">
</head>
<body>

<div class="admin-shell">
  <?php $activeAdmin = 'importar'; include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main">
    <div class="admin-head">
      <div>
        <h1>Importar datos oficiales</h1>
        <p>Crea cartas de jugadores, escudos, entrenadores y gerentes a partir del datos_oficiales.json de la Superliga Frontier.</p>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($resultado): ?>
      <div class="field-full">
        <h2><?= $resultado['creados'] ?> cartas creadas</h2>
        <ul>
          <li><?= $resultado['omitidos'] ?> omitidas (ya existían)</li>
          <li><?= $resultado['equipos_creados'] ?> equipos nuevos creados</li>
        </ul>
        <?php if (!empty($resultado['fotos_fallidas'])): ?>
        <div class="alert alert-warning">No se pudo descargar la foto de: <?= htmlspecialchars(implode(', ', $resultado['fotos_fallidas'])) ?>. Esas cartas se crearon sin imagen.</div>
        <?php endif; ?>
        <?php if (!empty($resultado['posiciones_desconocidas'])): ?>
        <div class="alert alert-warning">No se crearon por posición no reconocida: <?= htmlspecialchars(implode(', ', $resultado['posiciones_desconocidas'])) ?>.</div>
        <?php endif; ?>
      </div>

    <?php elseif ($previsualizacion): ?>
      <form method="POST">
        <h2>Previsualización</h2>
        <ul>
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
        <h3>Equipos que necesitan tu confirmación</h3>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Del JSON</th><th>¿Con cuál equipo es?</th></tr></thead>
            <tbody>
            <?php foreach ($previsualizacion['equipos_ambiguos'] as $amb): ?>
            <tr>
              <td><?= htmlspecialchars($amb['nombre_json']) ?> <small>(<?= $amb['porcentaje'] ?>% parecido)</small></td>
              <td>
                <label><input type="radio" name="equipo_eleccion[<?= htmlspecialchars($amb['id']) ?>]" value="db" checked> Es "<?= htmlspecialchars($amb['candidato_db']['nombre']) ?>" (ya existe)</label><br>
                <label><input type="radio" name="equipo_eleccion[<?= htmlspecialchars($amb['id']) ?>]" value="json"> Es un equipo nuevo, llámalo "<?= htmlspecialchars($amb['nombre_json']) ?>"</label><br>
                <label><input type="radio" name="equipo_eleccion[<?= htmlspecialchars($amb['id']) ?>]" value="otro"> Otro nombre:
                  <input type="text" name="equipo_texto[<?= htmlspecialchars($amb['id']) ?>]" placeholder="Nombre correcto"></label>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <div class="modal-footer">
          <button type="submit" name="cancelar" value="1" class="btn btn-ghost">Cancelar</button>
          <button type="submit" name="confirmar" value="1" class="btn btn-primary">Crear cartas</button>
        </div>
      </form>

    <?php else: ?>
      <form method="POST" enctype="multipart/form-data">
        <div class="field field-full">
          <label>Archivo datos_oficiales.json</label>
          <input type="file" name="json_datos" accept=".json,application/json" required>
        </div>
        <div class="field field-full">
          <label>Expansión destino</label>
          <select name="id_expansion" required>
            <?php foreach ($expansiones as $ex): ?>
            <option value="<?= $ex['id_expansion'] ?>"><?= htmlspecialchars($ex['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Previsualizar</button>
        </div>
      </form>
    <?php endif; ?>
  </main>
</div>

</body>
</html>
