/* ==========================================================================
   CEREMONIA DE APERTURA DE SOBRES
   Tres tiempos: dorsos en la mesa → volteo escalonado → resultado.
   La intensidad (ritmo, resplandor, destello) escala con la rareza más alta
   que haya salido; la SRF recibe el tratamiento más largo.

   Compromisos que no se negocian:
   · Es saltable en cualquier momento ("Mostrar todas").
   · Con prefers-reduced-motion no hay volteo ni destello: las cartas
     aparecen ya reveladas.
   · El resultado se anuncia por una región aria-live.

   Expone SRF.ceremonia(cartas), donde cada carta es
   { nombre, rareza, id_rareza, html }. El `html` lo genera en servidor el
   mismo componente de tarjeta que usa el resto del sitio.

   Requiere en la página el marcado de partials/ceremonia.php.
   ========================================================================== */
(function () {
  'use strict';

  var mesa      = document.getElementById('ceremoniaMesa');
  var caja      = document.getElementById('ceremoniaCaja');
  var anuncio   = document.getElementById('ceremoniaAnuncio');
  var btnSaltar = document.getElementById('ceremoniaSaltar');
  if (!mesa) return;

  var reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var temporizadores = [];

  /* Ritmo por rareza: cuanto más rara, más se hace esperar la carta. */
  var ESPERA = { 1: 260, 2: 300, 3: 380, 4: 520, 5: 760, 6: 1100 };

  function limpiarTemporizadores() {
    temporizadores.forEach(clearTimeout);
    temporizadores = [];
  }

  function programar(fn, ms) {
    temporizadores.push(setTimeout(fn, ms));
  }

  /* ---- construcción de la mesa: una ranura por carta, boca abajo ---- */
  function prepararMesa(cartas) {
    mesa.innerHTML = '';
    caja.classList.remove('es-legendario', 'es-srf');

    cartas.forEach(function (carta) {
      var ranura = document.createElement('div');
      ranura.className = 'ranura';
      ranura.dataset.rareza = carta.id_rareza;

      var dorso = document.createElement('div');
      dorso.className = 'ranura-cara ranura-dorso';
      dorso.innerHTML = '<div class="carta-dorso"><i class="ph ph-soccer-ball"></i></div>';

      var frente = document.createElement('div');
      frente.className = 'ranura-cara ranura-frente';
      frente.innerHTML = carta.html;

      ranura.appendChild(dorso);
      ranura.appendChild(frente);
      mesa.appendChild(ranura);
    });
  }

  function destelloPantalla() {
    if (reducido) return;
    var destello = document.createElement('div');
    destello.className = 'ceremonia-destello';
    document.body.appendChild(destello);
    destello.addEventListener('animationend', function () { destello.remove(); });
  }

  function voltear(ranura) {
    ranura.classList.add('esta-volteada');

    var rareza = parseInt(ranura.dataset.rareza, 10);
    if (rareza >= 5) {
      ranura.classList.add('con-fanfarria');
      if (rareza === 6) destelloPantalla();
    }
  }

  /* ---- revelar todo de golpe: el botón de saltar y el modo reducido ---- */
  function revelarTodo() {
    limpiarTemporizadores();
    Array.prototype.forEach.call(mesa.querySelectorAll('.ranura'), function (r) {
      r.classList.add('esta-volteada');
    });
    btnSaltar.disabled = true;
  }

  function anunciar(cartas) {
    var texto = cartas.map(function (c) { return c.nombre + ', ' + c.rareza; }).join('. ');
    anuncio.textContent = 'Has conseguido ' + cartas.length +
      (cartas.length === 1 ? ' carta: ' : ' cartas: ') + texto + '.';
  }

  function ceremonia(cartas) {
    if (!cartas || !cartas.length) return;

    prepararMesa(cartas);

    var maxRareza = cartas.reduce(function (max, c) { return Math.max(max, c.id_rareza); }, 1);
    if (maxRareza === 5) caja.classList.add('es-legendario');
    if (maxRareza === 6) caja.classList.add('es-srf');

    SRF.abrirModal('modalSobre');

    if (reducido) {
      revelarTodo();
      anunciar(cartas);
      return;
    }

    btnSaltar.disabled = false;

    var ranuras = mesa.querySelectorAll('.ranura');
    var reloj = 260;

    cartas.forEach(function (carta, i) {
      reloj += ESPERA[carta.id_rareza] || 300;
      programar(function () { voltear(ranuras[i]); }, reloj);
    });

    programar(function () {
      anunciar(cartas);
      btnSaltar.disabled = true;
    }, reloj + 400);
  }

  btnSaltar.addEventListener('click', revelarTodo);

  /* al cerrar el modal se corta cualquier animación pendiente */
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-cerrar-modal]')) limpiarTemporizadores();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') limpiarTemporizadores();
  });

  SRF.ceremonia = ceremonia;
})();
