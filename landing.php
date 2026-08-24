<?php
session_start();

require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';

$cromos      = $db->listarDestacados();
$expansiones = $db->listarExpansionesActivas();

$paginaTitulo = 'Inicio';
$paginaDesc   = 'El registro coleccionable de la Superliga Frontier: cada jugador, presidente y escudo de la liga, convertido en carta.';
include __DIR__ . '/partials/head.php';

$activePage = 'landing';
include __DIR__ . '/navbar.php';
?>

<main id="contenido">

  <section class="hero">
    <div class="linea-campo" aria-hidden="true"></div>

    <div class="wrap hero-contenido">
      <a class="eyebrow" href="https://superligafrontier.es">Temporada 03 · Ya disponible</a>

      <h1 class="t-display">El registro coleccionable<br>de los Super Ruinosos Fronteras</h1>

      <p class="hero-sub">
        Cada jugador, presidente y escudo de la liga, convertido en carta.
      </p>

      <div class="hero-acciones">
        <?php if (empty($_SESSION['id_usuario'])): ?>
          <a class="btn btn-primary btn-lg" href="login.php">Empieza tu colección</a>
        <?php else: ?>
          <a class="btn btn-primary btn-lg" href="sobres.php">Abrir un sobre</a>
        <?php endif; ?>
        <a class="btn btn-ghost btn-lg" href="album.php">Ver el álbum</a>
      </div>
    </div>
  </section>

  <section class="seccion wrap" id="cartas">
    <div class="seccion-head reveal">
      <div>
        <span class="seccion-tag">Colección activa</span>
        <h2>Cartas destacadas</h2>
      </div>
      <a class="btn btn-ghost btn-sm" href="album.php">Ver todas</a>
    </div>

    <?php if (empty($cromos)): ?>
      <div class="vacio">
        <span class="vacio-ico"><i class="ph ph-cards" aria-hidden="true"></i></span>
        <h3>Todavía no hay cartas publicadas</h3>
        <p>La primera expansión de la temporada está en preparación.</p>
      </div>
    <?php else: ?>
      <div class="carta-grid reveal">
        <?php foreach ($cromos as $carta): ?>
          <?php render_carta($carta, ['href' => 'album.php', 'lazy' => false]); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="seccion wrap" id="expansiones">
    <div class="seccion-head reveal">
      <div>
        <span class="seccion-tag">Nuevo contenido</span>
        <h2>Últimas expansiones</h2>
      </div>
    </div>

    <?php if (empty($expansiones)): ?>
      <div class="vacio">
        <span class="vacio-ico"><i class="ph ph-folder-open" aria-hidden="true"></i></span>
        <h3>No hay expansiones activas</h3>
        <p>Cuando se abra una expansión nueva, aparecerá aquí con su recuento de cartas.</p>
      </div>
    <?php else: ?>
      <div class="expansion-fila reveal">
        <?php foreach ($expansiones as $expansion): ?>
          <?php $ncartas = $db->cartasExpansion($expansion['id_expansion']); ?>
          <a class="expansion-tarjeta" href="album.php">
            <span class="t-caption t-dim mono"><?= htmlspecialchars($expansion['fecha_salida']) ?></span>
            <h3><?= htmlspecialchars($expansion['nombre']) ?></h3>
            <span class="t-body-sm t-dim"><span class="mono"><?= (int) $ncartas ?></span> cartas</span>
            <span class="expansion-link">
              Explorar <i class="ph ph-arrow-right" aria-hidden="true"></i>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

</main>

<?php $pieCompleto = true; include __DIR__ . '/partials/footer.php'; ?>

</body>
</html>
