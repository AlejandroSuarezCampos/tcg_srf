<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = $_SESSION['id_usuario'];

// ----- Proteger / desproteger un cromo (POST desde el botón de cada carta) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'toggle_bloqueo') {
    $db->alternarBloqueoCromo((int) $_POST['id_coleccion'], $id_usuario);
    $query = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: coleccion.php' . ($query !== '' ? '?' . $query : ''));
    exit;
}

// Igual, pero para una carta agrupada en pantalla que representa varias copias
// repetidas a la vez (ver agrupación más abajo).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'toggle_bloqueo_grupo') {
    $db->alternarBloqueoGrupoCromo((int) $_POST['id_cromo'], $id_usuario, (int) $_POST['estado_actual']);
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

// Agrupa copias repetidas del mismo cromo con el mismo estado de protección
// en una sola carta con insignia "×N": con cientos de copias, renderizar una
// tarjeta completa por copia es lo que hacía lenta la página. El orden de
// $cromos ya viene por fecha de obtención (más reciente primero), así que la
// primera copia de cada grupo que aparece es la que se usa de representante.
$grupos = [];
foreach ($cromos as $c) {
    $clave = $c['id_cromo'] . '-' . $c['bloqueada'];
    if (!isset($grupos[$clave])) {
        $grupos[$clave] = ['fila' => $c, 'cantidad' => 0];
    }
    $grupos[$clave]['cantidad']++;
}
$grupos = array_values($grupos);

$totalColeccion = $db->contarCromosTotales();
$totalObtenidas = $db->contarColeccionUsuario($id_usuario);
$porcentaje     = $totalColeccion > 0 ? round($totalObtenidas / $totalColeccion * 100) : 0;

$hayFiltros = $filtroNombre !== '' || $filtroEquipo !== '' || $filtroExpansion !== ''
    || !empty($filtroRarezas) || $filtroEstado !== '';

$queryActual = $_SERVER['QUERY_STRING'] ?? '';
$accionForm  = 'coleccion.php' . ($queryActual !== '' ? '?' . htmlspecialchars($queryActual) : '');

$paginaTitulo = 'Tu colección';
$paginaDesc   = 'Explora, filtra y organiza los cromos que has conseguido.';
include __DIR__ . '/partials/head.php';

$activePage = 'coleccion';
include __DIR__ . '/navbar.php';
?>

<header class="cabecera">
  <div class="linea-campo" aria-hidden="true"></div>
  <div class="wrap cabecera-contenido">
    <h1>Tu colección</h1>
    <p>Todos los cromos que has conseguido. Protege los que no quieras vender por error.</p>
    <div class="cabecera-datos">
      <div class="dato">
        <b><?= $totalObtenidas ?> / <?= $totalColeccion ?></b>
        <span>Cromos conseguidos</span>
      </div>
      <div class="dato"><b><?= $porcentaje ?> %</b><span>Completado</span></div>
    </div>
  </div>
</header>

<main id="contenido" class="seccion wrap">
  <div class="con-filtros">

    <details class="filtros" data-plegable-movil>
      <summary class="filtros-resumen">
        <span><i class="ph ph-funnel" aria-hidden="true"></i> Filtrar</span>
        <i class="ph ph-caret-down filtros-caret" aria-hidden="true"></i>
      </summary>

      <form method="GET" class="stack stack-5 filtros-cuerpo">
        <div class="campo">
          <label for="f-buscar">Buscar por nombre</label>
          <input type="search" name="q" id="f-buscar" value="<?= htmlspecialchars($filtroNombre) ?>"
                 placeholder="Ej. Mark Evans">
        </div>

        <div class="campo">
          <label for="f-equipo">Equipo</label>
          <select name="id_equipo" id="f-equipo">
            <option value="">Todos los equipos</option>
            <?php foreach ($equipos as $eq): ?>
            <option value="<?= $eq['id_equipo'] ?>" <?= (string) $filtroEquipo === (string) $eq['id_equipo'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($eq['nombre']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="f-expansion">Expansión</label>
          <select name="id_expansion" id="f-expansion">
            <option value="">Todas las expansiones</option>
            <?php foreach ($expansiones as $ex): ?>
            <option value="<?= $ex['id_expansion'] ?>" <?= (string) $filtroExpansion === (string) $ex['id_expansion'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($ex['nombre']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <fieldset class="campo">
          <legend class="campo-label">Rareza</legend>
          <?php foreach ($rarezas as $idRareza => $r): ?>
          <label class="casilla">
            <input type="checkbox" name="rareza[]" value="<?= $idRareza ?>"
                   <?= in_array((string) $idRareza, array_map('strval', (array) $filtroRarezas), true) ? 'checked' : '' ?>>
            <?= htmlspecialchars($r) ?>
          </label>
          <?php endforeach; ?>
        </fieldset>

        <div class="campo">
          <label for="f-estado">Protección</label>
          <select name="estado" id="f-estado">
            <option value="">Todas</option>
            <option value="bloqueada" <?= $filtroEstado === 'bloqueada' ? 'selected' : '' ?>>Solo protegidas</option>
            <option value="desbloqueada" <?= $filtroEstado === 'desbloqueada' ? 'selected' : '' ?>>Solo sin proteger</option>
          </select>
        </div>

        <div class="stack stack-2">
          <button type="submit" class="btn btn-primary btn-bloque">Aplicar filtros</button>
          <?php if ($hayFiltros): ?>
          <a class="btn btn-plano btn-bloque" href="coleccion.php">Quitar filtros</a>
          <?php endif; ?>
        </div>
      </form>
    </details>

    <div>
      <div class="fila fila-entre" style="margin-bottom:var(--space-5);">
        <p class="t-body-sm t-dim">
          <b class="mono" style="color:var(--frost);"><?= count($cromos) ?></b>
          <?= count($cromos) === 1 ? 'cromo mostrado' : 'cromos mostrados' ?>
        </p>
      </div>

      <?php if (empty($grupos)): ?>
        <div class="vacio">
          <span class="vacio-ico"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></span>
          <?php if ($hayFiltros): ?>
            <h3>Ningún cromo con esos filtros</h3>
            <p>Prueba a quitar alguna rareza o a buscar por otro nombre.</p>
            <a class="btn btn-ghost" href="coleccion.php">Quitar filtros</a>
          <?php else: ?>
            <h3>Todavía no tienes cromos</h3>
            <p>Abre tu primer sobre para empezar la colección.</p>
            <a class="btn btn-primary" href="sobres.php">Ir a sobres</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="carta-grid">
          <?php foreach ($grupos as $g): ?>
            <?php
            $c = $g['fila'];
            $cantidad = $g['cantidad'];
            $protegida = (bool) $c['bloqueada'];
            if ($cantidad > 1) {
                $accion = '<form method="POST" action="' . $accionForm . '">'
                    . '<input type="hidden" name="accion" value="toggle_bloqueo_grupo">'
                    . '<input type="hidden" name="id_cromo" value="' . (int) $c['id_cromo'] . '">'
                    . '<input type="hidden" name="estado_actual" value="' . (int) $protegida . '">'
                    . '<button type="submit" class="carta-accion-flotante' . ($protegida ? ' esta-activa' : '') . '">'
                    . '<i class="ph ' . ($protegida ? 'ph-lock-simple' : 'ph-lock-simple-open') . '" aria-hidden="true"></i>'
                    . '<span class="sr-only">' . ($protegida ? 'Quitar protección de las ' : 'Proteger las ')
                    . $cantidad . ' copias de ' . htmlspecialchars($c['nombre']) . '</span>'
                    . '</button></form>';
            } else {
                $accion = '<form method="POST" action="' . $accionForm . '">'
                    . '<input type="hidden" name="accion" value="toggle_bloqueo">'
                    . '<input type="hidden" name="id_coleccion" value="' . (int) $c['id_coleccion'] . '">'
                    . '<button type="submit" class="carta-accion-flotante' . ($protegida ? ' esta-activa' : '') . '">'
                    . '<i class="ph ' . ($protegida ? 'ph-lock-simple' : 'ph-lock-simple-open') . '" aria-hidden="true"></i>'
                    . '<span class="sr-only">' . ($protegida ? 'Quitar protección de ' : 'Proteger ')
                    . htmlspecialchars($c['nombre']) . '</span>'
                    . '</button></form>';
            }
            ?>
            <?php render_carta($c, ['acciones' => $accion, 'cantidad' => $cantidad]); ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

<script src="assets/js/coleccion.js"></script>

</body>
</html>
