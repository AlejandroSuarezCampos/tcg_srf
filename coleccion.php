<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/rareza-clases.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = $_SESSION['id_usuario'];

// ----- Bloquear / desbloquear un cromo (POST desde el botón de cada carta) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'toggle_bloqueo') {
    $db->alternarBloqueoCromo((int) $_POST['id_coleccion'], $id_usuario);
    $query = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: coleccion.php' . ($query !== '' ? '?' . $query : ''));
    exit;
}

$equipos     = $db->listarEquipos();
$expansiones = $db->listarExpansiones();
$rarezasDB   = $db->listarRarezas();

$rarezas = [];
foreach ($rarezasDB as $r) {
    $rarezas[$r['id_rareza']] = $r['nombre'];
}

// ----- Filtros -----
$filtroNombre     = trim($_GET['q'] ?? '');
$filtroEquipo     = $_GET['id_equipo'] ?? '';
$filtroExpansion  = $_GET['id_expansion'] ?? '';
$filtroRarezas    = $_GET['rareza'] ?? [];
$filtroEstado     = $_GET['estado'] ?? '';

$filtros = [
    'nombre'      => $filtroNombre,
    'id_equipo'   => $filtroEquipo,
    'id_expansion'=> $filtroExpansion,
    'rarezas'     => is_array($filtroRarezas) ? $filtroRarezas : [],
    'bloqueada'   => $filtroEstado === 'bloqueada' ? 1 : ($filtroEstado === 'desbloqueada' ? 0 : ''),
];

$cromos = $db->listarColeccionUsuario($id_usuario, $filtros);

$totalColeccion = $db->contarCromosTotales();
$totalObtenidas = $db->contarColeccionUsuario($id_usuario);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi colección — Inazuma TCG</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" type="image/png" href="assets/img/iconos/favicon.ico">
</head>
<body>

<?php $activePage = 'coleccion'; include __DIR__ . '/navbar.php'; ?>

<div class="page-banner">
  <div class="hero-grid"></div>
  <div class="page-banner-content">
    <h1>Tu colección</h1>
    <p>Explora, filtra y organiza todos los cromos que has conseguido.</p>
    <div class="page-banner-stats">
      <div class="pb-stat"><b><?= $totalObtenidas ?> / <?= $totalColeccion ?></b><span>Cromos obtenidos</span></div>
      <div class="pb-stat"><b><?= $totalColeccion > 0 ? round($totalObtenidas / $totalColeccion * 100) : 0 ?>%</b><span>Completado</span></div>
    </div>
  </div>
</div>

<section>
  <div class="collection-layout">

    <aside class="filters-panel reveal">
      <form method="GET" id="formFiltros">
        <h3>Filtrar cromos</h3>

        <div class="filter-block">
          <label class="f-label" for="f-buscar">Buscar por nombre</label>
          <input type="text" name="q" id="f-buscar" value="<?= htmlspecialchars($filtroNombre) ?>" placeholder="Ej. Mark Evans">
        </div>

        <div class="filter-block">
          <label class="f-label" for="f-equipo">Equipo</label>
          <select name="id_equipo" id="f-equipo">
            <option value="">Todos los equipos</option>
            <?php foreach ($equipos as $eq): ?>
            <option value="<?= $eq['id_equipo'] ?>" <?= (string) $filtroEquipo === (string) $eq['id_equipo'] ? 'selected' : '' ?>><?= htmlspecialchars($eq['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-block">
          <label class="f-label" for="f-expansion">Expansión</label>
          <select name="id_expansion" id="f-expansion">
            <option value="">Todas las expansiones</option>
            <?php foreach ($expansiones as $ex): ?>
            <option value="<?= $ex['id_expansion'] ?>" <?= (string) $filtroExpansion === (string) $ex['id_expansion'] ? 'selected' : '' ?>><?= htmlspecialchars($ex['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-block">
          <span class="f-label">Rareza</span>
          <?php foreach ($rarezas as $idRareza => $r): ?>
          <label class="checkbox-row">
            <input type="checkbox" name="rareza[]" value="<?= $idRareza ?>" <?= in_array((string) $idRareza, array_map('strval', $filtroRarezas)) ? 'checked' : '' ?>>
            <?= htmlspecialchars($r) ?>
          </label>
          <?php endforeach; ?>
        </div>

        <div class="filter-block">
          <label class="f-label" for="f-estado">Estado</label>
          <select name="estado" id="f-estado">
            <option value="">Todas</option>
            <option value="bloqueada" <?= $filtroEstado === 'bloqueada' ? 'selected' : '' ?>>Bloqueadas</option>
            <option value="desbloqueada" <?= $filtroEstado === 'desbloqueada' ? 'selected' : '' ?>>Desbloqueadas</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary filter-apply">Aplicar filtros</button>
      </form>
    </aside>

    <div>
      <div class="collection-toolbar reveal">
        <span class="collection-count"><b><?= count($cromos) ?></b> cromos mostrados</span>
      </div>

      <div class="card-grid">
        <?php foreach ($cromos as $c):
            $imagenPanel = $c['imagen'] ? $c['imagen'] : null;
        ?>
        <div class="tcard">
          <div class="tcard-inner" style="position:relative;">
            <form method="POST" action="coleccion.php<?= $_SERVER['QUERY_STRING'] !== '' ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>" style="position:absolute; top:10px; left:10px; z-index:4;">
              <input type="hidden" name="accion" value="toggle_bloqueo">
              <input type="hidden" name="id_coleccion" value="<?= $c['id_coleccion'] ?>">
              <button type="submit" class="lock-badge" style="border:none; cursor:pointer;" title="<?= $c['bloqueada'] ? 'Desbloquear' : 'Bloquear' ?>">
                <?= $c['bloqueada'] ? '🔒' : '🔓' ?>
              </button>
            </form>
            <span class="rarity <?= $claseRarezaPorId[$c['id_rareza']] ?? 'r-comun' ?>"><?= htmlspecialchars($c['rareza']) ?></span>
            <div class="portrait">
              <?php if ($imagenPanel): ?>
              <img src="<?= htmlspecialchars($imagenPanel) ?>" alt="">
              <?php else: ?>
              <?= strtoupper(substr($c['nombre'],0,2)) ?>
              <?php endif; ?>
            </div>
            <h3><?= htmlspecialchars($c['nombre']) ?></h3>
            <div class="meta-row"><span><?= htmlspecialchars($c['equipo']) ?></span></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($cromos)): ?>
        <div class="empty-state" style="grid-column:1/-1;">
          <h3>No hay cromos con esos filtros</h3>
          <p>Prueba a cambiar los filtros, o abre algún sobre para hacer crecer tu colección.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</section>

<script src="./assets/js/scriptColeccion.js"></script>

</body>
</html>