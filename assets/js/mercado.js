/* ==========================================================================
   MERCADO
   Comprar y retirar sin recargar. Toda acción con consecuencia económica pasa
   por un modal de confirmación explícito (no por un confirm() del navegador,
   que no se puede estilar ni queda dentro del sistema).
   ========================================================================== */
(function () {
  'use strict';

  var modal = document.getElementById('modalConfirmar');
  var texto = document.getElementById('confirmarTexto');
  var btnSi = document.getElementById('confirmarSi');

  var formPendiente = null;

  function pedirConfirmacion(form) {
    formPendiente = form;
    texto.textContent = form.dataset.confirmar || '¿Confirmas esta acción?';

    /* El modal es COMPARTIDO y SRF.confirmar() puede haberle dejado puesto un
       botón extra y otro rótulo en el principal (los sobres lo usan para
       "Abrir 1 / Abrir 10"). Esta vía no pasa por ahí, así que lo devuelve a
       su estado de sí/no antes de enseñarlo. Hoy las dos pantallas no
       coinciden nunca, pero depender de eso es cómo aparece un "Abrir 10" en
       una compra del mercado el día que alguien las junte. */
    if (btnSi) btnSi.textContent = 'Confirmar';
    var extra = document.getElementById('confirmarExtra');
    if (extra) extra.hidden = true;

    SRF.abrirModal(modal);
  }

  if (btnSi) {
    btnSi.addEventListener('click', function () {
      var form = formPendiente;
      formPendiente = null;
      SRF.cerrarModal(modal);
      if (form) enviar(form);
    });
  }

  async function enviar(form) {
    var boton = form.querySelector('button[type="submit"]');
    boton.disabled = true;
    boton.classList.add('is-cargando');

    try {
      var res = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(form)
      });
      var data = await res.json();

      if (!data.ok) {
        boton.classList.remove('is-cargando');
        boton.disabled = false;
        SRF.toast(data.error || 'No se pudo completar la acción.', 'danger');
        return;
      }

      if (typeof data.monedas === 'number') actualizarMonedasNav(data.monedas);

      var esCompra = form.querySelector('[name="accion"]').value === 'comprar';
      SRF.toast(esCompra ? 'Compra completada. La carta ya es tuya.' : 'Anuncio retirado.', 'success');

      /* La carta sale del listado con una salida corta. Vale para las dos
         vistas: en rejilla el formulario cuelga de `.carta`, en lista de
         `.carta-fila` — sin el segundo selector, comprar desde la lista
         dejaba el anuncio en pantalla como si no hubiera pasado nada. */
      var carta = form.closest('.carta, .carta-fila');
      if (carta) {
        carta.style.transition = 'opacity 200ms var(--ease), transform 200ms var(--ease)';
        carta.style.opacity = '0';
        carta.style.transform = 'scale(.96)';
        setTimeout(function () { carta.remove(); }, 200);
      }
    } catch (err) {
      console.error(err);
      boton.classList.remove('is-cargando');
      boton.disabled = false;
      SRF.toast('No se pudo conectar con el servidor.', 'danger');
    }
  }

  Array.prototype.forEach.call(document.querySelectorAll('.js-mercado'), function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      /* sin modal de confirmación no se ejecuta nada a ciegas: el formulario
         se envía por la vía normal, que confirma en servidor */
      if (!modal) { form.submit(); return; }
      pedirConfirmacion(form);
    });
  });

  /* ------------------------------------------------------------------------
     SELECTOR VISUAL DE CARTA PARA VENDER
     Sustituye al <select>, que con una colección grande era una lista
     interminable donde no se reconocía nada.
     ------------------------------------------------------------------------ */
  (function selectorVenta() {
    var formulario = document.getElementById('formVender');
    if (!formulario) return;

    var buscar  = document.getElementById('v-buscar');
    var lista   = document.getElementById('v-lista');
    var oculto  = document.getElementById('v-carta');
    var conteo  = document.getElementById('v-conteo');
    var vacio   = lista.querySelector('.selector-vacio');
    var error   = document.getElementById('v-error');
    var items   = lista.querySelectorAll('.selector-item');
    var total   = items.length;

    function textoConteo(n) {
      return n + (n === 1 ? ' carta disponible' : ' cartas disponibles');
    }

    /* selección */
    lista.addEventListener('change', function (e) {
      if (!e.target.matches('input[type="radio"]')) return;

      Array.prototype.forEach.call(items, function (item) {
        var marcado = item.contains(e.target);
        item.classList.toggle('esta-elegida', marcado);
        var carta = item.querySelector('.carta');
        if (carta) carta.classList.toggle('is-seleccionada', marcado);
      });

      oculto.value = e.target.value;
      error.hidden = true;
    });

    /* búsqueda */
    var temporizador = null;
    buscar.addEventListener('input', function () {
      clearTimeout(temporizador);
      temporizador = setTimeout(function () {
        var texto = buscar.value.trim().toLowerCase();
        var visibles = 0;

        Array.prototype.forEach.call(items, function (item) {
          var d = item.dataset;
          var coincide = texto === '' ||
            (d.nombre + ' ' + d.equipo + ' ' + d.rarezaNombre).toLowerCase().indexOf(texto) !== -1;
          item.hidden = !coincide;
          if (coincide) visibles++;
        });

        vacio.hidden = visibles !== 0;
        conteo.textContent = texto === '' ? textoConteo(total) : textoConteo(visibles);
      }, 160);
    });

    /* no se publica nada sin carta elegida */
    formulario.addEventListener('submit', function (e) {
      if (!oculto.value) {
        e.preventDefault();
        error.hidden = false;
        buscar.focus();
      }
    });
  })();
})();
