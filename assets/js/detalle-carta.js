/* ==========================================================================
   MODAL DE DETALLE DE CARTA — §16.14.1
   Delegado en document: clic en cualquier .carta--detalle rellena
   #modalDetalleCarta con sus data-detalle-* y lo abre con SRF.abrirModal.
   ========================================================================== */
(function () {
  'use strict';

  document.addEventListener('click', function (e) {
    var carta = e.target.closest && e.target.closest('.carta--detalle');
    if (!carta) return;

    var d = carta.dataset;

    var foto = document.getElementById('detalleCartaFoto');
    foto.src = d.detalleFoto || '';
    foto.alt = 'Ilustración de ' + (d.detalleNombre || '');

    document.getElementById('detalleCartaNombre').textContent = d.detalleNombre || '';
    document.getElementById('detalleCartaEquipo').textContent = d.detalleEquipo || '';
    document.getElementById('detalleCartaPosicion').textContent = d.detallePosicion || '';
    document.getElementById('detalleCartaAta').textContent = d.detalleAta || '';
    document.getElementById('detalleCartaDef').textContent = d.detalleDef || '';
    document.getElementById('detalleCartaTec').textContent = d.detalleTec || '';

    if (window.SRF && typeof window.SRF.abrirModal === 'function') {
      window.SRF.abrirModal('modalDetalleCarta');
    }
  });
})();
