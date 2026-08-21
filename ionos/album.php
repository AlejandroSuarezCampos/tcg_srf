<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';

$coleccion = $db->listarColeccionCompleta();

// Si hay sesión, marcamos cuáles tiene ya el usuario: el álbum muestra TODAS
// las cartas del juego, y las que aún no se poseen salen apagadas.
$poseidas = [];
if (!empty($_SESSION['id_usuario'])) {
    foreach ($db->listarColeccionUsuario($_SESSION['id_usuario']) as $c) {
        $poseidas[(int) $c['id_cromo']] = true;
    }
}

$equipos     = [];
$afinidades  = [];
$rarezas     = [];
$totalCromos = 0;

foreach ($coleccion as $expansion) {
    foreach ($expansion['cromos'] as $cromo) {
        $totalCromos++;
        $equipos[$cromo['equipo']]       = $cromo['equipo'];
        $afinidades[$cromo['afinidad']]  = $cromo['afinidad'];
        $rarezas[$cromo['id_rareza']]    = $cromo['rareza'];
    }
}

ksort($rarezas);
sort($equipos);
sort($afinidades);

$totalPoseidas = count($poseidas);

$paginaTitulo = 'Álbum';
$paginaDesc   = 'Todas las cartas del juego, organizadas por expansión.';
include __DIR__ . '/partials/head.php';

$activePage = 'album';
include __DIR__ . '/navbar.php';
?>

<header class="cabecera">
  <div class="linea-campo" aria-hidden="true"></div>
  <div class="wrap cabecera-contenido">
    <h1>Álbum</h1>
    <p>Todas las cartas del juego, organizadas por expansión.</p>
    <div class="cabecera-datos">
      <div class="dato"><b><?= $totalCromos ?></b><span>Cartas en el juego</span></div>
      <?php if (!empty($_SESSION['id_usuario'])): ?>
      <div class="dato"><b><?= $totalPoseidas ?></b><span>Ya las tienes</span></div>
      <?php endif; ?>
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

      <div class="stack stack-5 filtros-cuerpo">
        <div class="campo">
          <label for="f-buscar">Buscar por nombre</label>
          <input type="search" id="f-buscar" placeholder="Ej. Mark Evans">
        </div>

        <div class="campo">
          <label for="f-equipo">Equipo</label>
          <select id="f-equipo">
            <option value="">Todos los equipos</option>
            <?php foreach ($equipos as $equipo): ?>
            <option value="<?= htmlspecialchars($equipo) ?>"><?= htmlspecialchars($equipo) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="f-afinidad">Afinidad</label>
          <select id="f-afinidad">
            <option value="">Todas las afinidades</option>
            <?php foreach ($afinidades as $afinidad): ?>
            <option value="<?= htmlspecialchars($afinidad) ?>"><?= htmlspecialchars($afinidad) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <fieldset class="campo">
          <legend class="campo-label">Rareza</legend>
          <?php foreach ($rarezas as $idRareza => $rareza): ?>
          <label class="casilla">
            <input type="checkbox" class="f-rareza" value="<?= $idRareza ?>">
            <?= htmlspecialchars($rareza) ?>
          </label>
          <?php endforeach; ?>
        </fieldset>

        <p class="campo-hint" id="albumConteo" role="status" aria-live="polite"></p>
      </div>
    </details>

    <div id="albumListado">
      <?php foreach ($coleccion as $expansion): ?>
        <?php
        $total = count($expansion['cromos']);
        $tengo = 0;
        foreach ($expansion['cromos'] as $cromo) {
            if (isset($poseidas[(int) $cromo['id_cromo']])) { $tengo++; }
        }
        $pct = $total > 0 ? round($tengo / $total * 100) : 0;
        ?>
        <section class="expansion-grupo">
          <div class="expansion-cabecera">
            <div>
              <h2><?= htmlspecialchars($expansion['info']['nombre']) ?></h2>
              <span class="t-caption t-dim"><span class="mono"><?= $total ?></span> cartas</span>
            </div>

            <?php if (!empty($_SESSION['id_usuario'])): ?>
            <div class="progreso">
              <div class="progreso-riel">
                <div class="progreso-relleno" style="width:<?= $pct ?>%"></div>
              </div>
              <span class="progreso-label"><?= $tengo ?> / <?= $total ?></span>
            </div>
            <?php endif; ?>
          </div>

          <div class="carta-grid">
            <?php foreach ($expansion['cromos'] as $cromo): ?>
              <?php
              // Sin sesión el álbum es un catálogo: todo se muestra normal.
              $tienePoseida = empty($_SESSION['id_usuario'])
                  || isset($poseidas[(int) $cromo['id_cromo']]);
              $esJugador = in_array($cromo['posicion'], Tcg::POSICIONES_JUGABLES, true);
              render_carta($cromo, [
                  'poseida' => $tienePoseida,
                  'stats'   => $esJugador
                      ? ['ATA' => $cromo['ataque'], 'DEF' => $cromo['defensa'], 'TÉC' => $cromo['tecnica']]
                      : null,
                  'datos'   => [
                      'nombre'   => $cromo['nombre'],
                      'equipo'   => $cromo['equipo'],
                      'afinidad' => $cromo['afinidad'],
                      'rareza'   => $cromo['id_rareza'],
                  ],
              ]);
              ?>
            <?php endforeach; ?>
          </div>

          <div class="vacio expansion-vacia" hidden>
            <span class="vacio-ico"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></span>
            <h3>Ninguna carta de esta expansión coincide</h3>
            <p>Prueba a cambiar los filtros.</p>
          </div>
        </section>
      <?php endforeach; ?>
    </div>

  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

<?= assetScript($base ?? '', 'assets/js/album.js') ?>

</body>
</html>
