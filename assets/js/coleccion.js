/* ==========================================================================
   FILTROS DE COLECCIÓN — se aplican solos, sin botón
   A diferencia del álbum (que filtra en cliente sobre cartas ya servidas),
   aquí cada filtro es una consulta real a la base de datos: el formulario se
   sigue enviando por GET, solo que automáticamente al cambiar cualquier
   campo, igual que ya hace album.js con los suyos.
   ========================================================================== */
(function () {
  'use strict';

  var form = document.querySelector('.filtros-cuerpo');
  if (!form) return;

  var buscar = form.querySelector('#f-buscar');
  var boton  = form.querySelector('button[type="submit"]');

  var temporizador = null;

  function enviar() {
    clearTimeout(temporizador);
    form.submit();
  }

  function enviarConRetardo() {
    clearTimeout(temporizador);
    temporizador = setTimeout(enviar, 450);
  }

  if (buscar) buscar.addEventListener('input', enviarConRetardo);

  Array.prototype.forEach.call(form.querySelectorAll('select, input[type="checkbox"]'), function (campo) {
    campo.addEventListener('change', enviar);
  });

  /* el botón sigue en el DOM para quien no tenga JS; con JS ya no hace falta */
  if (boton) boton.hidden = true;
})();
