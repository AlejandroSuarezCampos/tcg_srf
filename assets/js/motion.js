/* ==========================================================================
   TCG FRONTIER — PRIMITIVAS DE MOVIMIENTO
   --------------------------------------------------------------------------
   Las cuatro cosas que no se pueden hacer solo con CSS: partir un título en
   palabras, saber cuándo un bloque entra en pantalla, rodar una cifra y
   seguir el puntero sobre una carta.

   El NIVEL lo decide el bloque inline de partials/head.php, que corre antes
   del primer pintado y deja `data-motion` en <html>. Aquí solo se consulta.

   Marcado que reconoce:
     [data-revela]              título que se revela palabra a palabra
     .escalona                  bloque cuyos hijos entran escalonados
     .sube                      elemento suelto que entra al hacer scroll
     [data-cifra="312"]         cifra que rueda hasta ese valor
     .inclina (dentro de .inclina-escena)   carta que sigue al puntero

   Sin dependencias. Va con `defer`: nada de lo que hace es urgente, y todo lo
   que toca arranca desde un estado ya legible si el script no llegara nunca.
   ========================================================================== */
(function () {
  'use strict';

  var raiz  = document.documentElement;
  var SRF   = window.SRF || {};
  var nivel = function () { return raiz.dataset.motion || 'full'; };

  /* Con el nivel mínimo no se monta nada: el CSS ya deja todo visible y
     colocado, y montar observadores para luego no usarlos es trabajo tirado
     justo en los aparatos a los que menos les sobra.

     Pero la función SÍ se expone, vacía: quien inserte contenido por AJAX la
     llama sin preguntar, y si aquí no existiera reventaría precisamente en el
     aparato más flojo. */
  if (nivel() === 'reduce') {
    SRF.montarMovimiento = function () {};
    window.SRF = SRF;
    return;
  }

  var ESCALON = 70;   // ms entre hermanos, igual que --escalon

  /* ======================================================================
     REVELADO POR PALABRAS
     Se parte con split(' '), no por carácter: partir por letra multiplica los
     nodos del DOM por veinte y en gama baja se nota. (SplitText de GSAP,
     además, es de pago.)
     ====================================================================== */
  function partirEnPalabras(el) {
    var texto = el.textContent.trim();
    if (!texto) return;
    var palabras = texto.split(/\s+/);
    var base = parseInt(el.dataset.revela, 10) || 0;

    el.textContent = '';
    palabras.forEach(function (palabra, i) {
      var caja = document.createElement('span');
      caja.className = 'palabra';
      var interior = document.createElement('i');
      interior.textContent = palabra;
      interior.style.transitionDelay = (base + i * ESCALON) + 'ms';
      caja.appendChild(interior);
      el.appendChild(caja);
      if (i < palabras.length - 1) el.appendChild(document.createTextNode(' '));
    });
  }

  /* ======================================================================
     ENTRADA AL HACER SCROLL
     IntersectionObserver, NUNCA un listener de scroll. Se deja de observar en
     cuanto entra: la entrada ocurre una sola vez.
     ====================================================================== */
  var ojo = ('IntersectionObserver' in window)
    ? new IntersectionObserver(function (entradas) {
        entradas.forEach(function (e) {
          if (!e.isIntersecting) return;
          entrar(e.target);
          ojo.unobserve(e.target);
        });
      }, { rootMargin: '0px 0px -12% 0px' })
    : null;

  function entrar(el) {
    /* El retardo se reparte solo entre los primeros hijos: una rejilla de
       doscientas cartas con escalón de 70 ms tardaría catorce segundos en
       terminar de aparecer, y por encima de ~60 elementos animándose a la vez
       un móvil barato empieza a soltar fotogramas (§5.4). El resto entra ya
       colocado, que es justo lo que se quiere. */
    var hijos = el.children;
    for (var i = 0; i < hijos.length && i < 12; i++) {
      hijos[i].style.transitionDelay = (i * ESCALON) + 'ms';
    }
    el.classList.add('esta-dentro');
    if (el.dataset.cifra !== undefined) rodarCifra(el);
  }

  function observar(el) {
    if (ojo) ojo.observe(el);
    else entrar(el);   // sin soporte: se enseña sin animar, nunca se esconde
  }

  /* ======================================================================
     CIFRAS RODANDO
     requestAnimationFrame y `tabular-nums` en CSS, para que el ancho no baile
     mientras sube. Solo en nivel completo: en `lite` la cifra aparece puesta.
     ====================================================================== */
  function rodarCifra(el) {
    var hasta = parseFloat(el.dataset.cifra);
    if (isNaN(hasta)) return;

    var sufijo = el.dataset.cifraSufijo || '';
    var pinta = function (v) {
      el.textContent = v.toLocaleString('es-ES') + sufijo;
    };

    if (nivel() !== 'full') { pinta(hasta); return; }

    /* El primer fotograma se PIDE, no se ejecuta a mano.
       Antes esto arrancaba con `paso(t0)` síncrono, y como en ese instante
       k = 0 lo primero que hacía era pintar un CERO. En una pestaña de fondo
       requestAnimationFrame no corre nunca, así que la cuenta se quedaba ahí:
       el jugador volvía a la pestaña y su saldo decía 0 monedas.
       Pidiéndolo, si rAF no llega no pasa nada y se queda el valor que ya
       venía escrito del servidor, que es el correcto. */
    var dur = 900, t0 = null;
    requestAnimationFrame(function paso(t) {
      if (t0 === null) t0 = t;
      var k = Math.min((t - t0) / dur, 1);
      pinta(Math.round(hasta * (1 - Math.pow(1 - k, 3))));   // easeOutCubic
      if (k < 1) requestAnimationFrame(paso);
    });
  }

  /* ======================================================================
     CARTA INCLINADA
     Solo puntero fino: en táctil no hay hover, y un estado de hover que se
     queda pegado tras un toque es un fallo, no un efecto.
     ====================================================================== */
  function montarInclinacion(escena) {
    var carta = escena.querySelector('.inclina');
    if (!carta) return;

    var pendiente = null;

    escena.addEventListener('pointermove', function (ev) {
      if (nivel() !== 'full') return;
      /* Se acumula el evento y se pinta una vez por fotograma: pointermove
         dispara muy por encima de 60 Hz en un ratón decente, y escribir el
         transform en cada uno es trabajo tirado. */
      if (pendiente) return;
      pendiente = requestAnimationFrame(function () {
        pendiente = null;
        var r = carta.getBoundingClientRect();
        var x = (ev.clientX - r.left) / r.width  - .5;
        var y = (ev.clientY - r.top)  / r.height - .5;
        carta.style.willChange = 'transform';
        carta.style.setProperty('--gx', (x * 16).toFixed(2) + 'deg');
        carta.style.setProperty('--gy', (-y * 16).toFixed(2) + 'deg');
      });
    });

    escena.addEventListener('pointerleave', function () {
      if (pendiente) { cancelAnimationFrame(pendiente); pendiente = null; }
      carta.style.removeProperty('--gx');
      carta.style.removeProperty('--gy');
      /* will-change SE QUITA al terminar. Dejarlo fijo en cincuenta cartas
         reserva cincuenta capas de GPU y es exactamente cómo se tumba un
         móvil de gama baja (§5.4). */
      carta.style.willChange = '';
    });
  }

  /* ======================================================================
     ARRANQUE
     ====================================================================== */
  function montar(ambito) {
    ambito = ambito || document;

    ambito.querySelectorAll('[data-revela]').forEach(function (el) {
      if (el.dataset.revelaMontado) return;
      el.dataset.revelaMontado = '1';
      partirEnPalabras(el);
      observar(el);
    });

    ambito.querySelectorAll('.escalona, .sube, [data-cifra]').forEach(function (el) {
      if (el.dataset.motionMontado) return;
      el.dataset.motionMontado = '1';
      observar(el);
    });

    if (window.matchMedia('(hover:hover) and (pointer:fine)').matches) {
      ambito.querySelectorAll('.inclina-escena').forEach(function (el) {
        if (el.dataset.motionMontado) return;
        el.dataset.motionMontado = '1';
        montarInclinacion(el);
      });
    }
  }

  /* Se expone para el contenido que llega por AJAX (rejillas al filtrar,
     resultados del mercado): montar(nodoNuevo) y ya. */
  SRF.montarMovimiento = montar;
  window.SRF = SRF;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { montar(); });
  } else {
    montar();
  }
})();
