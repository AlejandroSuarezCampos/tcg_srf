<?php
session_start();
require_once __DIR__ . '/db/conexion.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = (int) $_SESSION['id_usuario'];

// Misma red perezosa que en cadena.php y duelos.php: un partido de cadena a
// medias se cierra al volver, y aquí se vuelve tanto como al mapa. Va antes de
// leer el progreso para que el recuento de nodos ya lo incluya.
$db->cerrarPartidosPendientes($id_usuario);

$titular = $db->obtenerMazoTitular($id_usuario);
$cadenas = $db->listarCadenas();

// El estado de cada cadena se resuelve aquí y no en la plantilla para que el
// marcado quede limpio: cuántos nodos lleva, si está cerrada y qué le falta.
$estado = [];
foreach ($cadenas as $c) {
    $id = (int) $c['id_cadena'];
    $mapa = $db->mapaCadena($id, $id_usuario);

    $total = 0;
    $hechos = 0;
    foreach ($mapa['nodos'] as $n) {
        if ($n['tipo'] !== 'partido') { continue; }
        $total++;
        if ($n['superado']) { $hechos++; }
    }

    $estado[$id] = [
        'pendientes' => $db->requisitosPendientes($id, $id_usuario),
        'completada' => $db->cadenaCompletada($id, $id_usuario),
        'total'      => $total,
        'hechos'     => $hechos,
    ];
}

$paginaTitulo = 'Cadenas de Partido';
$paginaDesc   = 'Rutas de partidos contra rivales del sistema.';
include __DIR__ . '/partials/head.php';

$activePage = 'cadenas';
include __DIR__ . '/navbar.php';
?>

<header class="cabecera">
  <div class="linea-campo" aria-hidden="true"></div>
  <div class="wrap cabecera-contenido">
    <h1>Cadenas de Partido</h1>
    <p>Rutas de partidos contra rivales del sistema. Se juega con tu mazo titular y no se apuesta nada.</p>
  </div>
</header>

<main id="contenido" class="seccion wrap">

  <?php if (!$titular): ?>
    <div class="vacio">
      <span class="vacio-ico"><i class="ph ph-list-checks" aria-hidden="true"></i></span>
      <h3>Necesitas un mazo titular</h3>
      <p>Las cadenas se juegan con la alineación que tengas marcada como titular, con sus 11 huecos cubiertos.</p>
      <a class="btn btn-primary" href="mazos.php">Ir a mazos</a>
    </div>

  <?php elseif (empty($cadenas)): ?>
    <div class="vacio">
      <span class="vacio-ico"><i class="ph ph-path" aria-hidden="true"></i></span>
      <h3>Todavía no hay cadenas</h3>
      <p>Aún no se ha publicado ninguna ruta.</p>
    </div>

  <?php else: ?>
    <div class="lista-cadenas">
      <?php foreach ($cadenas as $c): ?>
        <?php
        $id = (int) $c['id_cadena'];
        $e  = $estado[$id];
        $bloqueada = !empty($e['pendientes']);
        $pct = $e['total'] > 0 ? (int) round($e['hechos'] / $e['total'] * 100) : 0;
        ?>
        <article class="cadena-tarjeta<?= $bloqueada ? ' es-bloqueada' : '' ?>">
          <div class="cadena-cabecera">
            <h2 class="t-h3"><?= htmlspecialchars($c['nombre']) ?></h2>
            <?php if ($e['completada']): ?>
              <span class="pastilla pastilla-on">Completada</span>
            <?php elseif ($bloqueada): ?>
              <span class="pastilla">
                <i class="ph ph-lock-simple" aria-hidden="true"></i> Bloqueada
              </span>
            <?php endif; ?>
          </div>

          <?php if ($c['descripcion']): ?>
            <p class="t-body-sm t-dim"><?= htmlspecialchars($c['descripcion']) ?></p>
          <?php endif; ?>

          <div class="progreso">
            <div class="progreso-riel">
              <div class="progreso-relleno" style="width:<?= $pct ?>%"></div>
            </div>
            <span class="progreso-label"><?= $e['hechos'] ?> / <?= $e['total'] ?></span>
          </div>

          <?php if ($c['formacion_recompensa'] && isset(Tcg::FORMACIONES[$c['formacion_recompensa']])): ?>
            <p class="t-caption t-dim">
              <i class="ph ph-gift" aria-hidden="true"></i>
              Su cofre final desbloquea la formación
              <b><?= htmlspecialchars(Tcg::FORMACIONES[$c['formacion_recompensa']]['nombre']) ?></b>.
            </p>
          <?php endif; ?>

          <div class="cadena-pie">
            <?php if ($bloqueada): ?>
              <!-- El requisito se enseña al INTENTAR entrar, no en la lista: la
                   cadena se ve, se sabe que existe, y el modal explica qué falta. -->
              <button type="button" class="btn btn-ghost"
                      data-abrir-modal="modalBloqueo<?= $id ?>">
                Ver requisitos
              </button>
            <?php else: ?>
              <a class="btn btn-primary" href="cadena.php?id=<?= $id ?>">
                <?= $e['hechos'] > 0 ? 'Continuar' : 'Empezar' ?>
              </a>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php foreach ($cadenas as $c): ?>
      <?php
      $id = (int) $c['id_cadena'];
      if (empty($estado[$id]['pendientes'])) { continue; }
      ?>
      <div class="modal" id="modalBloqueo<?= $id ?>" role="dialog" aria-modal="true"
           aria-labelledby="bloqueoTitulo<?= $id ?>" aria-hidden="true">
        <div class="modal-caja">
          <div class="modal-head">
            <h2 id="bloqueoTitulo<?= $id ?>">Esta cadena está bloqueada</h2>
            <button class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">
              <i class="ph ph-x" aria-hidden="true"></i>
            </button>
          </div>

          <p class="t-body-sm">Necesitas estos requisitos:</p>
          <ul class="lista-requisitos">
            <?php foreach ($estado[$id]['pendientes'] as $r): ?>
              <li><i class="ph ph-lock-simple" aria-hidden="true"></i> <?= htmlspecialchars($r) ?></li>
            <?php endforeach; ?>
          </ul>

          <div class="modal-pie">
            <button type="button" class="btn btn-ghost" data-cerrar-modal>Entendido</button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

</body>
</html>
