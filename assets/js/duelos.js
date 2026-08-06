/* ==========================================================================
   DUELOS — lobby
   Solo dos cosas: enseñar los campos que tocan según el tipo de apuesta, y
   confirmar antes de entrar en una sala, porque entrar cuesta monedas o una
   carta y eso no se hace a ciegas.
   ========================================================================== */
(function () {
  'use strict';

  var form = document.getElementById('formCrearDuelo');
  if (!form) return;

  var tipos   = form.querySelectorAll('[data-tipo]');
  var rareza  = document.getElementById('d-rareza');
  var lista   = document.getElementById('d-cartas');
  var items   = lista ? Array.prototype.slice.call(lista.querySelectorAll('.selector-item')) : [];
  var vacio   = lista ? lista.querySelector('.selector-vacio') : null;

  function tipoElegido() {
    var marcado = form.querySelector('[data-tipo]:checked');
    return marcado ? marcado.value : 'monedas';
  }

  /* Solo se ven las cartas de la rareza pactada: mezclar todas convertiría el
     selector en un muro donde no se encuentra nada. */
  function filtrarPorRareza() {
    if (!lista) return;
    var rz = rareza.value;
    var visibles = 0;

    items.forEach(function (item) {
      var coincide = item.dataset.rareza === rz;
      item.hidden = !coincide;
      if (coincide) visibles++;
      /* una carta oculta no puede quedarse elegida por detrás */
      if (!coincide) item.querySelector('input').checked = false;
    });

    if (vacio) vacio.hidden = visibles !== 0;

    /* se preselecciona la primera para que el formulario nunca salga sin carta */
    var primera = items.find(function (i) { return !i.hidden; });
    if (primera && !items.some(function (i) { return i.querySelector('input').checked; })) {
      primera.querySelector('input').checked = true;
    }
  }

  function refrescarBloques() {
    var tipo = tipoElegido();
    Array.prototype.forEach.call(form.querySelectorAll('[data-bloque]'), function (bloque) {
      bloque.hidden = bloque.dataset.bloque !== tipo;
    });
    if (tipo === 'carta') filtrarPorRareza();
  }

  Array.prototype.forEach.call(tipos, function (t) {
    t.addEventListener('change', refrescarBloques);
  });
  if (rareza) rareza.addEventListener('change', filtrarPorRareza);

  /* marcar la tarjeta elegida, mismo lenguaje visual que el mercado */
  if (lista) {
    lista.addEventListener('change', function () {
      items.forEach(function (item) {
        item.classList.toggle('esta-elegida', item.querySelector('input').checked);
      });
    });
  }

  refrescarBloques();
})();


/* --------------------------------------------------------------------------
   Entrar en una sala mueve monedas o una carta: confirmación explícita antes,
   con el modal del sistema (nunca confirm() del navegador).
   -------------------------------------------------------------------------- */
(function () {
  'use strict';

  Array.prototype.forEach.call(document.querySelectorAll('form.js-aceptar'), function (form) {
    form.addEventListener('submit', function (e) {
      if (form.dataset.confirmado === '1') return;
      e.preventDefault();

      SRF.confirmar(form.dataset.confirmar, function () {
        form.dataset.confirmado = '1';
        form.submit();
      });
    });
  });
})();
