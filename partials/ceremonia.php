<?php
/**
 * MARCADO DE LA CEREMONIA DE APERTURA
 * Lo usan sobres.php (apertura real) y styleguide.php (previsualización).
 * El comportamiento vive en assets/js/ceremonia.js.
 */
$base = $base ?? '';
?>
<div class="modal ceremonia" id="modalSobre" role="dialog" aria-modal="true"
     aria-labelledby="ceremoniaTitulo" aria-hidden="true">
  <div class="modal-caja modal-caja--ancha" id="ceremoniaCaja">
    <div class="modal-head">
      <h2 id="ceremoniaTitulo">Sobre abierto</h2>
      <button class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <!-- §14.4: el sobre en sí, en 3D, antes de repartir las cartas -->
    <div class="ceremonia-apertura" id="ceremoniaApertura" hidden>
      <div class="sobre-3d" id="sobre3d">
        <div class="sobre-3d-mitad sobre-3d-arriba">
          <img class="sobre-3d-img" alt="" hidden>
        </div>
        <div class="sobre-3d-mitad sobre-3d-abajo">
          <img class="sobre-3d-img" alt="" hidden>
        </div>
        <div class="sobre-3d-destello"></div>
      </div>
    </div>

    <div class="ceremonia-mesa" id="ceremoniaMesa"></div>

    <p class="sr-only" id="ceremoniaAnuncio" role="status" aria-live="polite"></p>

    <div class="modal-pie">
      <button type="button" class="btn btn-ghost" id="ceremoniaSaltarCarta">Saltar carta</button>
      <button type="button" class="btn btn-ghost" id="ceremoniaSaltar">Saltar todo</button>
      <button type="button" class="btn btn-primary" data-cerrar-modal>Continuar</button>
    </div>
  </div>
</div>

<script src="<?= $base ?>assets/js/vendor/gsap/gsap.min.js"></script>
<script src="<?= $base ?>assets/js/ceremonia.js"></script>
