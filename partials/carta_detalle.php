<?php
/**
 * MODAL DE DETALLE DE CARTA — §16.14.1
 * Muestra en grande la foto y las estadísticas de una carta al hacer clic
 * en ella (clase .carta--detalle, ver components/carta.php). Mismo patrón
 * de modal que partials/confirmar.php: SRF.abrirModal/cerrarModal de ui.js,
 * clases .modal/.modal-caja/.modal-head/.modal-cerrar ya existentes.
 * Lo rellena assets/js/detalle-carta.js a partir de los data-detalle-*.
 */
?>
<div class="modal" id="modalDetalleCarta" role="dialog" aria-modal="true"
     aria-labelledby="detalleCartaTitulo" aria-hidden="true">
  <div class="modal-caja">
    <div class="modal-head">
      <h2 id="detalleCartaTitulo">Detalle de la carta</h2>
      <button class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <img id="detalleCartaFoto" src="" alt="" style="width:100%;max-height:360px;object-fit:contain;">

    <div class="stack stack-3" style="margin-top:var(--space-3);">
      <h3 id="detalleCartaNombre" style="margin:0;"></h3>
      <p class="t-body-sm t-dim" style="margin:0;">
        <span id="detalleCartaEquipo"></span> ·
        <span id="detalleCartaPosicion"></span>
      </p>

      <div style="display:flex;gap:var(--space-3);">
        <div style="flex:1;text-align:center;padding:var(--space-2);border-radius:var(--radius-sm);background:var(--success);color:#fff;">
          <b id="detalleCartaAta" style="display:block;font-size:20px;font-family:var(--font-mono);"></b>
          <span style="font-size:11px;">ATA</span>
        </div>
        <div style="flex:1;text-align:center;padding:var(--space-2);border-radius:var(--radius-sm);background:var(--danger);color:#fff;">
          <b id="detalleCartaDef" style="display:block;font-size:20px;font-family:var(--font-mono);"></b>
          <span style="font-size:11px;">DEF</span>
        </div>
        <div style="flex:1;text-align:center;padding:var(--space-2);border-radius:var(--radius-sm);background:var(--info);color:#fff;">
          <b id="detalleCartaTec" style="display:block;font-size:20px;font-family:var(--font-mono);"></b>
          <span style="font-size:11px;">TÉC</span>
        </div>
      </div>
    </div>
  </div>
</div>
