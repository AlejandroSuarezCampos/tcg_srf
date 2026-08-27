/* ==========================================================================
   INTERCAMBIOS

   Dos cosas, y ninguna es imprescindible: el formulario funciona sin este
   archivo (el servidor valida el tope, la propiedad y las cartas libres) y las
   acciones son formularios POST normales. Esto solo evita que te enteres de
   que te has pasado de diez DESPUÉS de enviar.

     1. Los contadores de cada lado y el tope, mientras eliges.
     2. La confirmación antes de aceptar, rechazar o retirar — cambiar cartas
        de dueño no se deshace.
   ========================================================================== */
(function () {
  'use strict';

  /* ------------------------------------------------------------------------
     CONFIRMAR ANTES DE MOVER CARTAS
     Mismo modal compartido que usa el mercado, y por el mismo motivo: un
     confirm() del navegador no se puede estilar ni queda dentro del sistema.
     ------------------------------------------------------------------------ */
  var modal = document.getElementById('modalConfirmar');
  var texto = document.getElementById('confirmarTexto');
  var btnSi = document.getElementById('confirmarSi');
  var formPendiente = null;

  if (btnSi) {
    btnSi.addEventListener('click', function () {
      var form = formPendiente;
      formPendiente = null;
      SRF.cerrarModal(modal);
      if (form) form.submit();
    });
  }

  Array.prototype.forEach.call(document.querySelectorAll('.js-intercambio'), function (form) {
    form.addEventListener('submit', function (e) {
      // Sin modal, el formulario sigue su camino: el servidor confirma igual.
      if (!modal || !texto) return;
      e.preventDefault();
      formPendiente = form;
      texto.textContent = form.dataset.confirmar || '¿Confirmas esta acción?';
      if (btnSi) btnSi.textContent = 'Confirmar';
      var extra = document.getElementById('confirmarExtra');
      if (extra) extra.hidden = true;
      SRF.abrirModal(modal);
    });
  });

  /* ------------------------------------------------------------------------
     EL FORMULARIO DEL TRATO
     ------------------------------------------------------------------------ */
  var form = document.getElementById('formTrato');
  if (!form) return;

  var maxLado = parseInt(form.dataset.maxLado, 10) || 10;
  var error   = document.getElementById('trato-error');

  /** Suma de un lado y refresco de su contador. */
  function recuenta(campo) {
    var total = 0;
    Array.prototype.forEach.call(
      form.querySelectorAll('input[name^="' + campo + '["]'),
      function (input) {
        var n = parseInt(input.value, 10) || 0;
        total += n;
        // La carta se apaga en cero: se ve de un vistazo qué entra en el trato.
        var item = input.closest('.selector-item');
        if (item) item.classList.toggle('esta-elegida', n > 0);
      }
    );
    var marcador = form.querySelector('[data-conteo="' + campo + '"]');
    if (marcador) marcador.textContent = total;
    return total;
  }

  /** Lo que suma ese lado SIN contar el campo que se está tocando. */
  function sumaOtros(campo, excluir) {
    var total = 0;
    Array.prototype.forEach.call(
      form.querySelectorAll('input[name^="' + campo + '["]'),
      function (input) {
        if (input !== excluir) total += parseInt(input.value, 10) || 0;
      }
    );
    return total;
  }

  /**
   * Recorta lo que se acaba de escribir para que el lado no pase del tope.
   * Se recorta en vez de rechazar porque el usuario ya ha dicho lo que quiere;
   * bajarle el número y enseñarle el contador dice más que un aviso.
   *
   * El hueco disponible se mide sumando LOS DEMÁS campos, no restando el valor
   * anterior de este del total: el total ya incluye lo que se acaba de teclear,
   * así que restar el anterior lo descuenta dos veces y el tope se aplica
   * antes de tiempo — con diez por lado, se quedaba clavado en siete.
   */
  function ajusta(input, campo) {
    var n = parseInt(input.value, 10);
    if (!isFinite(n) || n < 0) n = 0;

    var tope = parseInt(input.max, 10);
    if (isFinite(tope) && n > tope) n = tope;

    var resto = maxLado - sumaOtros(campo, input);
    if (n > resto) n = Math.max(0, resto);

    input.value = n;
    recuenta(campo);
  }

  form.addEventListener('input', function (e) {
    if (!e.target.classList.contains('selector-cant')) return;
    var campo = e.target.name.indexOf('da[') === 0 ? 'da' : 'busca';
    ajusta(e.target, campo);
    if (error) error.hidden = true;
  });

  /* Un clic sobre el arte suma una copia. El <label> ya lleva el foco al
     campo; esto es lo que hace que elegir sea pulsar la carta, que es como se
     usa de verdad — teclear un número por carta sería insufrible. */
  form.addEventListener('click', function (e) {
    if (e.target.classList.contains('selector-cant')) return;
    var item = e.target.closest('.selector-item');
    if (!item) return;
    var input = item.querySelector('.selector-cant');
    if (!input) return;
    e.preventDefault();
    input.value = (parseInt(input.value, 10) || 0) + 1;
    ajusta(input, input.name.indexOf('da[') === 0 ? 'da' : 'busca');
    input.focus();
  });

  /* Buscar dentro de cada lado. Filtra los que ya están elegidos también: si
     escondiera lo elegido, se perdería de vista lo que ya has puesto. */
  Array.prototype.forEach.call(form.querySelectorAll('[data-busca-en]'), function (buscador) {
    var lista = document.getElementById(buscador.dataset.buscaEn);
    if (!lista) return;
    var items = lista.querySelectorAll('.selector-item');
    var vacio = lista.querySelector('.selector-vacio');
    var temporizador = null;

    buscador.addEventListener('input', function () {
      clearTimeout(temporizador);
      temporizador = setTimeout(function () {
        var q = buscador.value.trim().toLowerCase();
        var visibles = 0;
        Array.prototype.forEach.call(items, function (item) {
          var d = item.dataset;
          var coincide = q === '' || item.classList.contains('esta-elegida') ||
            (d.nombre + ' ' + d.equipo + ' ' + d.rarezaNombre).toLowerCase().indexOf(q) !== -1;
          item.hidden = !coincide;
          if (coincide) visibles++;
        });
        if (vacio) vacio.hidden = visibles !== 0;
      }, 160);
    });
  });

  form.addEventListener('submit', function (e) {
    var da = recuenta('da');
    if (da > 0) return;
    e.preventDefault();
    if (error) {
      error.querySelector('span').textContent = 'Pon al menos una carta de tu parte.';
      error.hidden = false;
      error.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  });

  recuenta('da');
  recuenta('busca');
})();
