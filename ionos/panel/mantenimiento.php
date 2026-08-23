<?php
/**
 * MANTENIMIENTO — las tareas que antes solo se podían lanzar por consola.
 *
 * Son dos, y las dos hacen falta después de un despliegue:
 *   · pasar a WebP las imágenes que se hayan subido en otro formato;
 *   · asignar su compo a las cartas que se quedaron sin ninguna.
 *
 * Vivían en `db/herramientas/*.php` y había que entrar por SSH con la ruta
 * exacta del PHP de XAMPP. Aquí es un botón. La lógica NO se duplica: la de
 * verdad está en `db/herramientas/mantenimiento.php` y la usan tanto esta
 * pantalla como los guiones de consola, que se quedan por si hace falta
 * lanzarlas sin navegador.
 *
 * Las dos empiezan SIEMPRE simulando. Borrar archivos del servidor no puede
 * ser lo que pasa por pulsar el primer botón que uno ve.
 */

session_start();
require_once __DIR__ . '/../db/conexion.php';
require_once __DIR__ . '/../partials/csrf.php';

if (empty($_SESSION['dictador']) || $_SESSION['dictador'] != 1) {
    header('Location: ../landing.php');
    exit;
}

$base         = '../';
$paginaTitulo = 'Mantenimiento — Panel';
$paginaDesc   = 'Tareas de mantenimiento del catálogo y de las imágenes.';
$cssExtra     = ['panel/assets/css/admin.css'];
include __DIR__ . '/../partials/head.php';

$activeAdmin = 'mantenimiento';
?>

<div class="admin-shell">
  <?php include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main" id="contenido">
    <div class="admin-head">
      <div>
        <h1>Mantenimiento</h1>
        <p>
          Tareas que se pasan de vez en cuando, sobre todo después de un despliegue.
          Todas empiezan enseñándote lo que harían; nada se toca hasta que lo confirmas.
        </p>
      </div>
    </div>

    <!-- ==================================================================
         IMÁGENES A WEBP
         ================================================================== -->
    <section class="panel mantenimiento-tarea" data-tarea="webp">
      <div class="panel-head">
        <div>
          <h2 class="panel-titulo"><i class="ph ph-image" aria-hidden="true"></i> Pasar las imágenes a WebP</h2>
          <p class="t-body-sm t-dim" style="margin-top:var(--space-2);">
            Convierte a WebP los PNG y JPG que queden en <span class="mono">assets/img</span> y
            arregla las rutas guardadas en la base. Un arte de carta en PNG ronda 1,5&nbsp;MB
            y en WebP se queda en 200&nbsp;KB sin diferencia a la vista; multiplicado por las
            cartas de una pantalla de colección, es la diferencia entre que un móvil de gama
            baja cargue el mercado o se quede pensando.
          </p>
          <p class="t-caption t-dim" style="margin-top:var(--space-2);">
            No se tocan los iconos de Apple ni los favicon —iOS no acepta WebP ahí— ni los
            originales de las plantillas 3D, que son la fuente de la que se recorta.
            Las subidas nuevas ya nacen en WebP; esto es solo para lo viejo.
          </p>
        </div>
      </div>

      <div class="mantenimiento-botones">
        <button type="button" class="btn btn-plano" data-accion="webp" data-aplica="0">
          <i class="ph ph-eye" aria-hidden="true"></i> Ver qué haría
        </button>
        <button type="button" class="btn btn-primary" data-accion="webp" data-aplica="1" disabled>
          <i class="ph ph-play" aria-hidden="true"></i> Convertir de verdad
        </button>
      </div>

      <div class="mantenimiento-salida" data-salida="webp" role="status" aria-live="polite"></div>
    </section>

    <!-- ==================================================================
         COMPOS QUE FALTAN
         ================================================================== -->
    <section class="panel mantenimiento-tarea" data-tarea="compos">
      <div class="panel-head">
        <div>
          <h2 class="panel-titulo"><i class="ph ph-puzzle-piece" aria-hidden="true"></i> Asignar las compos que falten</h2>
          <p class="t-body-sm t-dim" style="margin-top:var(--space-2);">
            Busca las cartas jugables sin rasgo de configuración —sin contraataque, brecha,
            vínculo ni justicia— y les asigna el que les toca por posición&nbsp;×&nbsp;afinidad.
            Una carta sin compo no aporta nada a las compos de la alineación en la que juega,
            y eso no se ve al mirarla: solo se nota cuando ya está jugando.
          </p>
          <p class="t-caption t-dim" style="margin-top:var(--space-2);">
            No pisa las compos puestas a mano desde el alta de cromos. Se puede pasar las
            veces que haga falta: la segunda no cambia nada.
          </p>
        </div>
      </div>

      <div class="mantenimiento-botones">
        <button type="button" class="btn btn-plano" data-accion="compos" data-aplica="0">
          <i class="ph ph-eye" aria-hidden="true"></i> Ver cuáles faltan
        </button>
        <button type="button" class="btn btn-primary" data-accion="compos" data-aplica="1" disabled>
          <i class="ph ph-play" aria-hidden="true"></i> Asignarlas
        </button>
      </div>

      <div class="mantenimiento-salida" data-salida="compos" role="status" aria-live="polite"></div>
    </section>
  </main>
</div>

<?php include __DIR__ . '/../partials/confirmar.php'; ?>

<?php $pieCompleto = false; include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= htmlspecialchars(assetUrl($base, 'panel/assets/js/scriptMantenimiento.js')) ?>"></script>
</body>
</html>
