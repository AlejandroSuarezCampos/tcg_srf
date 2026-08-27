<?php
/**
 * MODAL DE CONFIRMACIÓN COMPARTIDO
 * Toda acción con consecuencia (económica o destructiva) pasa por aquí, nunca
 * por un confirm() del navegador: no se puede estilar y se sale del sistema.
 *
 * Lo consumen dos vías distintas, a propósito:
 *   · mercado.js, que tiene su propia lógica porque además envía por AJAX.
 *   · SRF.confirmar(texto, alAceptar) de ui.js, para el resto de pantallas.
 *
 * Los identificadores no se cambian: mercado.js depende de ellos.
 */
?>
<div class="modal" id="modalConfirmar" role="dialog" aria-modal="true"
     aria-labelledby="confirmarTitulo" aria-hidden="true">
  <div class="modal-caja" style="max-width:440px;">
    <div class="modal-head">
      <h2 id="confirmarTitulo">Confirmar</h2>
      <button class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>
    <p class="t-body-sm t-dim" id="confirmarTexto"></p>
    <div class="modal-pie">
      <button type="button" class="btn btn-ghost" data-cerrar-modal>Cancelar</button>
      <?php /* Segunda acción OPCIONAL, para las confirmaciones que no son
               sí/no sino "de una manera o de la otra" — hoy, abrir un sobre o
               abrir diez. Nace oculto y solo lo enseña SRF.confirmar() cuando
               se le pasa `opciones.extra`, así que las confirmaciones de
               siempre siguen viéndose con dos botones exactamente igual.
               Va ANTES del principal: el orden de lectura deja a la derecha la
               acción por defecto, que es la que se pulsa sin mirar. */ ?>
      <button type="button" class="btn btn-ghost" id="confirmarExtra" hidden></button>
      <button type="button" class="btn btn-primary" id="confirmarSi">Confirmar</button>
    </div>
  </div>
</div>
