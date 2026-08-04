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

    <div class="ceremonia-mesa" id="ceremoniaMesa"></div>

    <p class="sr-only" id="ceremoniaAnuncio" role="status" aria-live="polite"></p>

    <div class="modal-pie">
      <button type="button" class="btn btn-ghost" id="ceremoniaSaltar">Mostrar todas</button>
      <button type="button" class="btn btn-primary" data-cerrar-modal>Continuar</button>
    </div>
  </div>
</div>

<script src="<?= $base ?>assets/js/ceremonia.js"></script>
