/* ==========================================================================
   FILTRADO DEL ÁLBUM (en cliente)
   El álbum se sirve entero y se filtra sin recargar. El recuento se anuncia
   por aria-live para que el resultado del filtro no sea solo visual.
   ========================================================================== */
(function () {
  'use strict';

  var buscar   = document.getElementById('f-buscar');
  var equipo   = document.getElementById('f-equipo');
  var afinidad = document.getElementById('f-afinidad');
  var conteo   = document.getElementById('albumConteo');
  var rarezas  = document.querySelectorAll('.f-rareza');
  var cartas   = document.querySelectorAll('#albumListado .carta');
  if (!buscar || !cartas.length) return;

  var temporizador = null;

  function aplicar() {
    var texto = buscar.value.trim().toLowerCase();
    var eq = equipo.value;
    var af = afinidad.value;

    var seleccionadas = [];
    Array.prototype.forEach.call(rarezas, function (cb) {
      if (cb.checked) seleccionadas.push(cb.value);
    });

    var visibles = 0;

    Array.prototype.forEach.call(cartas, function (carta) {
      var d = carta.dataset;
      var mostrar =
        (texto === '' || (d.nombre || '').toLowerCase().indexOf(texto) !== -1) &&
        (eq === '' || d.equipo === eq) &&
        (af === '' || d.afinidad === af) &&
        (seleccionadas.length === 0 || seleccionadas.indexOf(d.rareza) !== -1);

      carta.hidden = !mostrar;
      if (mostrar) visibles++;
    });

    /* una expansión sin coincidencias enseña su propio estado vacío en vez de
       desaparecer sin explicación */
    Array.prototype.forEach.call(document.querySelectorAll('.expansion-grupo'), function (grupo) {
      var quedan = grupo.querySelectorAll('.carta:not([hidden])').length;
      var rejilla = grupo.querySelector('.carta-grid');
      var vacio = grupo.querySelector('.expansion-vacia');
      if (rejilla) rejilla.hidden = quedan === 0;
      if (vacio) vacio.hidden = quedan !== 0;
    });

    conteo.textContent = visibles === cartas.length
      ? ''
      : visibles + (visibles === 1 ? ' carta coincide' : ' cartas coinciden');
  }

  function aplicarConRetardo() {
    clearTimeout(temporizador);
    temporizador = setTimeout(aplicar, 180);
  }

  buscar.addEventListener('input', aplicarConRetardo);
  equipo.addEventListener('change', aplicar);
  afinidad.addEventListener('change', aplicar);
  Array.prototype.forEach.call(rarezas, function (cb) {
    cb.addEventListener('change', aplicar);
  });
})();
