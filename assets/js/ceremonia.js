/* ==========================================================================
   CEREMONIA DE APERTURA DE SOBRES
   Tres tiempos: dorsos en la mesa → volteo secuencial (carta a carta) →
   resultado. Cada carta enciende su aura de rareza justo antes de voltearse
   (§14.4); legendaria y SRF disparan además una secuencia FUT propia (§14.6).
   Orquestado con GSAP (vendorizado en assets/js/vendor/gsap/), sin build.

   Compromisos que no se negocian:
   · Saltable carta a carta ("Saltar carta") o de golpe ("Saltar todo").
   · Con prefers-reduced-motion no hay aura, FUT ni volteo animado: las
     cartas aparecen ya reveladas.
   · El resultado se anuncia por una región aria-live.

   Expone SRF.ceremonia(cartas), donde cada carta es
   { nombre, rareza, id_rareza, html }. El `html` lo genera en servidor el
   mismo componente de tarjeta que usa el resto del sitio.
   Expone además el hook SRF.onExclusiveReveal(carta), vacío por defecto,
   listo para engancharle audio en el futuro sin tocar esta timeline.

   Requiere en la página el marcado de partials/ceremonia.php (que ya carga
   gsap.min.js antes que este fichero).
   ========================================================================== */
(function () {
  'use strict';

  var mesa = document.getElementById('ceremoniaMesa');
  var caja = document.getElementById('ceremoniaCaja');
  var anuncio = document.getElementById('ceremoniaAnuncio');
  var btnSaltarCarta = document.getElementById('ceremoniaSaltarCarta');
  var btnSaltar = document.getElementById('ceremoniaSaltar');
  var apertura = document.getElementById('ceremoniaApertura');
  var sobre3d = document.getElementById('sobre3d');
  if (!mesa) return;

  var reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Ritmo por rareza: cuanto más rara, más se hace esperar la carta. */
  var ESPERA = { 1: .26, 2: .3, 3: .38, 4: .52, 5: 2.4, 6: 3 };
  var RZ_COLOR = { 1: 'var(--rz1)', 2: 'var(--rz2)', 3: 'var(--rz3)', 4: 'var(--rz4)', 5: 'var(--rz5)', 6: 'var(--rz6)' };

  var cartaActualTl = null;
  var resolverActual = null;
  var aperturaTl = null;
  var resolverApertura = null;
  var saltarTodo = false;

  /* ---- construcción de la mesa: una ranura por carta, boca abajo ---- */
  function prepararMesa(cartas) {
    mesa.innerHTML = '';

    cartas.forEach(function (carta) {
      var ranura = document.createElement('div');
      ranura.className = 'ranura';
      ranura.dataset.rareza = carta.id_rareza;

      var aura = document.createElement('div');
      aura.className = 'ranura-aura';

      var dorso = document.createElement('div');
      dorso.className = 'ranura-cara ranura-dorso';
      dorso.innerHTML = '<div class="carta-dorso"><i class="ph ph-soccer-ball"></i></div>';

      var frente = document.createElement('div');
      frente.className = 'ranura-cara ranura-frente';
      frente.innerHTML = carta.html;

      ranura.appendChild(aura);
      ranura.appendChild(dorso);
      ranura.appendChild(frente);
      mesa.appendChild(ranura);
    });
  }

  function destelloPantalla() {
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

  /* ---- estado final instantáneo de una carta: usado por ambos saltos ---- */
  function finalizarCarta(ranura) {
    var aura = ranura.querySelector('.ranura-aura');
    if (aura) gsap.set(aura, { opacity: 0 });
    var fut = mesa.querySelector('.ceremonia-fut');
    if (fut) fut.remove();
    voltear(ranura);
  }

  /* ---- secuencia FUT (§14.6): solo legendaria y SRF ---- */
  function construirSecuenciaFut(tl, ranura, carta, aura) {
    var overlay = document.createElement('div');
    overlay.className = 'ceremonia-fut';
    overlay.style.setProperty('--aura-color', RZ_COLOR[carta.id_rareza] || 'var(--amber)');
    overlay.innerHTML =
      '<div class="ceremonia-fut-rayos"></div>' +
      '<div class="ceremonia-fut-nombre">' +
        '<span class="fut-rareza"></span>' +
        '<span class="fut-nombre"></span>' +
      '</div>';
    overlay.querySelector('.fut-rareza').textContent = carta.rareza;
    overlay.querySelector('.fut-nombre').textContent = carta.nombre;
    mesa.appendChild(overlay);

    var rayos   = overlay.querySelector('.ceremonia-fut-rayos');
    var nombre  = overlay.querySelector('.ceremonia-fut-nombre');

    tl.set(overlay, { opacity: 0 })
      .to(overlay, { opacity: 1, duration: .4 })
      .to(rayos, { rotate: 360, duration: 2.6, ease: 'none' }, '<')
      .fromTo(nombre, { scale: .82, opacity: 0 }, { scale: 1, opacity: 1, duration: .5 }, '<+=.2')
      .to(overlay, { opacity: 0, duration: .35 }, '+=.5')
      .call(function () { overlay.remove(); })
      .to(aura, { opacity: 1, scale: 1.3, duration: .3 })
      .call(function () {
        voltear(ranura);
        if (typeof SRF.onExclusiveReveal === 'function') SRF.onExclusiveReveal(carta);
      })
      .to(caja, { x: -6, duration: .045, yoyo: true, repeat: 5, ease: 'none' }, '<')
      .to(aura, { opacity: 0, duration: .5 }, '+=.25');
  }

  /* ---- apertura del sobre en 3D (§14.4): rasgado en dos mitades + destello,
     antes de que aparezca la mesa. `sobre` es { nombre, imagen } o undefined. ---- */
  function reproducirApertura(sobre) {
    return new Promise(function (resolve) {
      var mitadArriba = sobre3d.querySelector('.sobre-3d-arriba');
      var mitadAbajo  = sobre3d.querySelector('.sobre-3d-abajo');
      var imgArriba   = mitadArriba.querySelector('.sobre-3d-img');
      var imgAbajo    = mitadAbajo.querySelector('.sobre-3d-img');
      var destello    = sobre3d.querySelector('.sobre-3d-destello');

      if (sobre && sobre.imagen) {
        imgArriba.src = sobre.imagen;
        imgAbajo.src = sobre.imagen;
        imgArriba.hidden = false;
        imgAbajo.hidden = false;
      } else {
        imgArriba.hidden = true;
        imgAbajo.hidden = true;
      }

      apertura.hidden = false;
      gsap.set(sobre3d, { scale: .4, opacity: 0, rotateY: -25, rotateZ: 0 });
      gsap.set([mitadArriba, mitadAbajo], { rotateX: 0, y: 0 });
      gsap.set(destello, { opacity: 0, scale: .6 });

      var tl = gsap.timeline({
        onComplete: function () {
          aperturaTl = null;
          resolverApertura = null;
          apertura.hidden = true;
          resolve();
        }
      });
      aperturaTl = tl;
      resolverApertura = resolve;

      tl.to(sobre3d, { scale: 1, opacity: 1, rotateY: 0, duration: .5, ease: 'back.out(1.6)' })
        .to(sobre3d, { rotateZ: -3, duration: .09, yoyo: true, repeat: 5, ease: 'none' }, '+=.12')
        .to(destello, { opacity: 1, scale: 1.5, duration: .22 }, '-=.08')
        .to(mitadArriba, { rotateX: -110, y: -26, duration: .45, ease: 'power2.in' }, '<')
        .to(mitadAbajo, { rotateX: 110, y: 26, duration: .45, ease: 'power2.in' }, '<')
        .call(destelloPantalla, [], '<+=.08')
        .to(destello, { opacity: 0, duration: .3 }, '-=.15')
        .to(sobre3d, { opacity: 0, duration: .25 }, '-=.1');
    });
  }

  /* ---- salto instantáneo de la apertura: usado por "Saltar todo" ---- */
  function finalizarApertura() {
    if (!aperturaTl) return;
    aperturaTl.kill();
    aperturaTl = null;
    apertura.hidden = true;
    if (resolverApertura) { var r = resolverApertura; resolverApertura = null; r(); }
  }

  /* ---- una carta: aura → volteo → (opcional) pausa. Saltable a mitad ---- */
  function reproducirCarta(carta, ranura) {
    return new Promise(function (resolve) {
      var aura = ranura.querySelector('.ranura-aura');
      var exclusiva = carta.id_rareza >= 5;

      var tl = gsap.timeline({
        onComplete: function () {
          cartaActualTl = null;
          resolverActual = null;
          resolve();
        }
      });
      cartaActualTl = tl;
      resolverActual = resolve;

      if (exclusiva) {
        construirSecuenciaFut(tl, ranura, carta, aura);
        return;
      }

      var total = ESPERA[carta.id_rareza] || .3;
      var resto = Math.max(total - .28 - .35, .15);
      tl.to(aura, { opacity: 1, scale: 1.12, duration: .28, ease: 'power1.out' })
        .call(function () { voltear(ranura); })
        .to(aura, { opacity: 0, duration: .35 }, '+=.1')
        .to({}, { duration: resto });
    });
  }

  var indice = 0;

  async function reproducirTodas(cartas) {
    var ranuras = mesa.querySelectorAll('.ranura');
    for (indice = 0; indice < cartas.length; indice++) {
      if (saltarTodo) {
        finalizarCarta(ranuras[indice]);
        continue;
      }
      await reproducirCarta(cartas[indice], ranuras[indice]);
    }
    anunciar(cartas);
    btnSaltarCarta.disabled = true;
    btnSaltar.disabled = true;
  }

  function anunciar(cartas) {
    var texto = cartas.map(function (c) { return c.nombre + ', ' + c.rareza; }).join('. ');
    anuncio.textContent = 'Has conseguido ' + cartas.length +
      (cartas.length === 1 ? ' carta: ' : ' cartas: ') + texto + '.';
  }

  /* `sobre` es opcional: { nombre, imagen } del sobre comprado, para que la
     apertura 3D muestre su propio arte. Sin él, la escena usa el degradado
     por defecto (caso de la previsualización de styleguide.php). */
  function ceremonia(cartas, sobre) {
    if (!cartas || !cartas.length) return;

    saltarTodo = false;
    mesa.innerHTML = '';
    caja.classList.remove('es-legendario', 'es-srf');

    var maxRareza = cartas.reduce(function (max, c) { return Math.max(max, c.id_rareza); }, 1);
    if (maxRareza === 5) caja.classList.add('es-legendario');
    if (maxRareza === 6) caja.classList.add('es-srf');

    SRF.abrirModal('modalSobre');

    if (reducido) {
      prepararMesa(cartas);
      Array.prototype.forEach.call(mesa.querySelectorAll('.ranura'), function (r) { r.classList.add('esta-volteada'); });
      anunciar(cartas);
      btnSaltarCarta.disabled = true;
      btnSaltar.disabled = true;
      return;
    }

    btnSaltarCarta.disabled = true;   // solo tiene sentido durante el reparto
    btnSaltar.disabled = false;       // puede saltar la apertura entera

    reproducirApertura(sobre).then(function () {
      prepararMesa(cartas);
      btnSaltarCarta.disabled = false;
      reproducirTodas(cartas);
    });
  }

  function detener() {
    finalizarApertura();
    if (cartaActualTl) { cartaActualTl.kill(); cartaActualTl = null; resolverActual = null; }
    saltarTodo = true;
    var fut = mesa.querySelector('.ceremonia-fut');
    if (fut) fut.remove();
  }

  btnSaltarCarta.addEventListener('click', function () {
    if (!cartaActualTl) return;
    var ranuras = mesa.querySelectorAll('.ranura');
    var ranura = ranuras[indice];
    cartaActualTl.kill();
    cartaActualTl = null;
    finalizarCarta(ranura);
    if (resolverActual) { var r = resolverActual; resolverActual = null; r(); }
  });

  btnSaltar.addEventListener('click', function () {
    saltarTodo = true;
    if (aperturaTl) {
      finalizarApertura();
      return;
    }
    if (cartaActualTl) {
      var ranuras = mesa.querySelectorAll('.ranura');
      var ranura = ranuras[indice];
      cartaActualTl.kill();
      cartaActualTl = null;
      finalizarCarta(ranura);
      if (resolverActual) { var r = resolverActual; resolverActual = null; r(); }
    }
  });

  /* al cerrar el modal se corta cualquier animación pendiente */
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-cerrar-modal]')) detener();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') detener();
  });

  SRF.onExclusiveReveal = SRF.onExclusiveReveal || function () {};
  SRF.ceremonia = ceremonia;
})();
