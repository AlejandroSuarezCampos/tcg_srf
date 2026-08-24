<?php
session_start();

http_response_code(404);

$paginaTitulo = 'Página no encontrada';
$paginaDesc   = 'Esta página no existe o se ha movido.';
include __DIR__ . '/partials/head.php';

$activePage = '';
include __DIR__ . '/navbar.php';
?>

<main id="contenido" class="seccion wrap">
  <div class="vacio">
    <span class="vacio-ico"><i class="ph ph-compass" aria-hidden="true"></i></span>
    <h3>Esta página no existe</h3>
    <p>Al Gonzalo ese le ganarás fácil, pero por aqui no hay nada.</p>
    <a class="btn btn-primary" href="<?= $haySesion ? 'coleccion.php' : 'landing.php' ?>">
      <?= $haySesion ? 'Ir a mi colección' : 'Ir al inicio' ?>
    </a>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
