/* ==========================================================================
   CEREMONIA DE APERTURA DE COFRES (Cadenas, §15.12 del CLAUDE.md)
   Hermana de ceremonia.js: mismo motor de volteo de cartas carta a carta,
   pero con una escena de apertura propia (un cofre que abre la tapa, no un
   sobre que se rasga) y un resumen que además dice monedas y bonus.

   Antes el botín se enseñaba como un <p class="alerta"> arriba de la
   página; con el botón "Abrir cofre" al fondo del panel, quedaba fuera de
   vista y los encargados de pruebas no lo veían las primeras veces. Aquí el
   modal se abre solo, centrado, y no hay página que recargar de por medio.

   Expone SRF.ceremoniaCofre(datos), con
   datos = { monedas, camino_perfecto, formacion, cartas } — cada carta
   { nombre, rareza, id_rareza, html }, igual que en la ceremonia de sobres.
   A diferencia de un sobre, un cofre puede no traer ninguna carta (solo
   monedas): la ceremonia sigue abriéndose igual, solo que sin escena de
   volteo.
   ========================================================================== */
(function () {
  'use strict';

  var modal      = document.getElementById('modalCofre');
  var caja       = document.getElementById('ceremoniaCofreCaja');
  var bonus      = document.getElementById('ceremoniaCofreBonus');
  var mesa       = document.getElementById('ceremoniaCofreMesa');
  var anuncio    = document.getElementById('ceremoniaCofreAnuncio');
  var escena     = document.getElementById('ceremoniaCofreEscena');
  var cerCofre   = document.getElementById('cerCofre');
  var cerCarta   = document.getElementById('cerCofreCarta');
  var cerFrente  = document.getElementById('cerCofreCartaFrente');
  var walkout    = document.getElementById('cerCofreWalkout');
  var walkoutRz  = document.getElementById('cerCofreWalkoutRareza');
  var walkoutNom = document.getElementById('cerCofreWalkoutNombre');
  var pista      = document.getElementById('ceremoniaCofrePista');
  var contador   = document.getElementById('ceremoniaCofreContador');
  var btnSaltarCarta = document.getElementById('ceremoniaCofreSaltarCarta');
  var btnSaltar      = document.getElementById('ceremoniaCofreSaltar');
  var btnSaltarEscena = document.getElementById('cerCofreSaltarEscena');
  var avisoMotion    = document.getElementById('cerCofreAvisoMotion');
  var btnActivarMotion  = document.getElementById('cerCofreActivarMotion');
  var btnRechazarMotion = document.getElementById('cerCofreRechazarMotion');
  if (!mesa || !cerCarta) return;

  function reducido() {
    return SRF.movimientoReducido();
  }

  var RZ_COLOR = {
    1: 'var(--rz1)', 2: 'var(--rz2)', 3: 'var(--rz3)',
    4: 'var(--rz4)', 5: 'var(--rz5)', 6: 'var(--rz6)'
  };

  var cartas = [];
  var datosActuales = null;
  var indice = 0;
  var tlActual = null;
  var esperandoClic = false;
  var cartaRevelada = false;
  var saltandoTodo = false;
  var avanzar = null;
  var enReparto = false;
  var sesion = 0;   // mismo motivo que en ceremonia.js: sesiones que se pisan

  function destelloPantalla() {
    var d = document.createElement('div');
    d.className = 'ceremonia-destello';
    document.body.appendChild(d);
    d.addEventListener('animationend', function () { d.remove(); });
  }

  function matarTimeline() {
    if (tlActual) { tlActual.kill(); tlActual = null; }
  }

  /* ---------------------------------------------------------------------
     APERTURA DEL COFRE — la tapa gira sobre su bisagra trasera y el
     interior brilla; el cofre se queda en pantalla, abierto, para que las
     cartas salgan de él (si trae alguna).
     --------------------------------------------------------------------- */
  var COFRE_BASE = { xPercent: -50, yPercent: -50 };

  function abrirCofre() {
    return new Promise(function (resolve) {
      var tapa    = cerCofre.querySelector('.cer-cofre-tapa');
      var brillo  = cerCofre.querySelector('.cer-cofre-brillo');

      gsap.set(cerCofre, Object.assign({}, COFRE_BASE, {
        scale: .68, opacity: 0, y: 24
      }));
      gsap.set(tapa,   { rotateX: 0, transformPerspective: 700 });
      gsap.set(brillo, { opacity: 0 });

      var tl = gsap.timeline({
        onComplete: function () { tlActual = null; resolve(); }
      });
      tlActual = tl;

      tl
        // 1. el cofre entra
        .to(cerCofre, { scale: 1, opacity: 1, y: 0, duration: .6, ease: 'back.out(1.4)' })
        // 2. tensión: un par de sacudidas antes de abrirse
        .to(cerCofre, { rotate: -1.4, duration: .07, yoyo: true, repeat: 5, ease: 'none' })
        // 3. la tapa se levanta sobre su bisagra y el interior se enciende
        .to(tapa, { rotateX: -108, duration: .5, ease: 'power2.out' }, '>-.05')
        .to(brillo, { opacity: 1, duration: .35 }, '<+.1')
        .call(function () { destelloPantalla(); })
        .to(cerCofre, { y: -6, duration: .3, ease: 'power2.out' }, '<');
    });
  }

  function saltarAperturaCofre() {
    matarTimeline();
    var tapa   = cerCofre.querySelector('.cer-cofre-tapa');
    var brillo = cerCofre.querySelector('.cer-cofre-brillo');
    gsap.set(cerCofre, Object.assign({}, COFRE_BASE, { scale: 1, opacity: 1, rotate: 0, y: -6 }));
    gsap.set(tapa,   { rotateX: -108 });
    gsap.set(brillo, { opacity: 1 });
  }

  /* ---------------------------------------------------------------------
     ESCENA 2 — una carta: idéntica a la de ceremonia.js (misma clase
     .cer-carta), solo que "sale" del cofre en vez de un sobre.
     --------------------------------------------------------------------- */
  var CARTA_BASE = { xPercent: -50, yPercent: -50, rotationY: 0, rotate: 0 };

  function pintarDorso(carta) {
    cerCarta.dataset.rareza = carta.id_rareza;
    cerCarta.style.setProperty('--rz-aura', RZ_COLOR[carta.id_rareza] || 'var(--brasa)');
    cerFrente.innerHTML = carta.html;
    cerCarta.disabled = false;
    cartaRevelada = false;
    contador.textContent = (indice + 1) + ' / ' + cartas.length;
    pista.textContent = 'Toca la carta para darle la vuelta';
    pista.hidden = false;
  }

  function escenaCarta(carta) {
    return new Promise(function (resolve) {
      avanzar = resolve;
      pintarDorso(carta);

      gsap.set(cerCarta, Object.assign({}, CARTA_BASE, {
        opacity: 1, scale: .58, y: 150, zIndex: 2
      }));
      gsap.set(cerCarta.querySelector('.cer-carta-aura'), { opacity: 0 });

      var tl = gsap.timeline({
        onComplete: function () { tlActual = null; esperandoClic = true; }
      });
      tlActual = tl;

      tl.to(cerCarta, { y: 34, scale: .60, duration: .34, ease: 'power2.out' })
        .set(cerCarta, { zIndex: 6 })
        .to(cerCarta, { y: -34, scale: 1, duration: .62, ease: 'power3.out' })
        .to(cerCarta, { rotate: 1.6, duration: .18, yoyo: true, repeat: 1, ease: 'sine.inOut' }, '-=.16');
    });
  }

  function voltearAhora(carta) {
    cartaRevelada = true;
    pista.textContent = 'Toca otra vez para continuar';
    pista.hidden = false;
    if (carta.id_rareza >= 5 && typeof SRF.onExclusiveReveal === 'function') {
      SRF.onExclusiveReveal(carta);
    }
  }

  function pedirVolteo(carta) {
    if (!esperandoClic) return;
    esperandoClic = false;
    pista.hidden = true;

    var exclusiva = carta.id_rareza >= 5;
    var aura = cerCarta.querySelector('.cer-carta-aura');

    var tl = gsap.timeline({
      onComplete: function () { tlActual = null; esperandoClic = true; }
    });
    tlActual = tl;

    if (!exclusiva) {
      tl.to(aura, { opacity: 1, scale: 1.15, duration: .22, ease: 'power1.out' })
        .to(cerCarta, { scale: 1.06, duration: .3, ease: 'power2.out' }, '<')
        .to(cerCarta, { rotationY: 180, duration: .62, ease: 'power2.inOut' }, '<+=.1')
        .call(function () { voltearAhora(carta); }, [], '<+=.31')
        .to(cerCarta, { scale: 1, duration: .3, ease: 'power2.out' })
        .to(aura, { opacity: 0, duration: .4 }, '<');
      return;
    }

    walkout.hidden = false;
    walkoutRz.textContent = carta.rareza;
    if (walkoutNom) walkoutNom.textContent = carta.nombre || '';
    walkout.style.setProperty('--rz-aura', RZ_COLOR[carta.id_rareza] || 'var(--brasa)');
    caja.classList.add('en-walkout');

    var rayos = walkout.querySelector('.cer-walkout-rayos');
    var texto = walkout.querySelector('.cer-walkout-texto');

    tl.set(walkout, { opacity: 0 })
      .set([rayos, texto], { opacity: 0 })
      .to(cerCarta, { scale: .82, duration: .5, ease: 'power2.out' }, 0)
      .to(walkout, { opacity: 1, duration: .45 }, 0)
      .to(rayos, { opacity: 1, duration: .6 }, '<')
      .to(rayos, { rotate: 360, duration: 3.4, ease: 'none' }, '<')
      .fromTo(texto, { opacity: 0, scale: .75, y: 14 },
        { opacity: 1, scale: 1, y: 0, duration: .55, ease: 'back.out(1.7)' }, '<+=.35')
      .to(texto, { scale: 1.06, duration: .5, yoyo: true, repeat: 2, ease: 'sine.inOut' })
      .to(texto, { opacity: 0, scale: 1.35, duration: .35, ease: 'power2.in' })
      .to(aura, { opacity: 1, scale: 1.5, duration: .3 }, '<')
      .to(cerCarta, { scale: 1.18, duration: .45, ease: 'power2.out' }, '<')
      .to(cerCarta, { rotationY: 180, duration: .8, ease: 'power2.inOut' }, '<')
      .call(function () { voltearAhora(carta); destelloPantalla(); }, [], '<+=.4')
      .to(caja, { x: -9, duration: .05, yoyo: true, repeat: 7, ease: 'none' }, '<')
      .to(cerCarta, { scale: 1, duration: .5, ease: 'power2.out' }, '<')
      .to(walkout, { opacity: 0, duration: .5 }, '<+=.25')
      .to(aura, { opacity: 0, duration: .6 }, '<')
      .call(function () {
        walkout.hidden = true;
        caja.classList.remove('en-walkout');
        gsap.set(caja, { clearProps: 'transform' });
      });
  }

  function finalizarCartaActual() {
    matarTimeline();
    walkout.hidden = true;
    caja.classList.remove('en-walkout');
    gsap.set(caja, { clearProps: 'transform' });
    gsap.set(cerCarta, Object.assign({}, CARTA_BASE, {
      opacity: 1, scale: 1, y: -34, zIndex: 6, rotationY: 180
    }));
    gsap.set(cerCarta.querySelector('.cer-carta-aura'), { opacity: 0 });
    if (!cartaRevelada) voltearAhora(cartas[indice]);
  }

  cerCarta.addEventListener('click', function () {
    if (saltandoTodo || !cartas.length) return;
    if (!cartaRevelada) { pedirVolteo(cartas[indice]); return; }
    if (!esperandoClic) return;
    siguienteCarta();
  });

  function siguienteCarta() {
    esperandoClic = false;
    if (!avanzar) return;
    var r = avanzar;
    avanzar = null;
    if (reducido()) { r(); return; }
    gsap.to(cerCarta, {
      opacity: 0, scale: .82, y: -120, x: 130, rotate: 12,
      duration: .38, ease: 'power2.in',
      onComplete: function () { gsap.set(cerCarta, { x: 0, rotate: 0 }); r(); }
    });
  }

  /* ---------------------------------------------------------------------
     ESCENA 3 — resumen: monedas + bonus + las cartas ya reveladas.
     A diferencia de un sobre, un cofre puede no traer ninguna carta.
     --------------------------------------------------------------------- */
  function pintarBonus() {
    var lineas = [];
    if (datosActuales.monedas > 0) {
      lineas.push('+' + datosActuales.monedas.toLocaleString('es-ES') + ' monedas');
    }
    if (datosActuales.camino_perfecto) {
      lineas.push('Camino perfecto: llegaste con todos los partidos en rango S en Extremo');
    }
    if (datosActuales.formacion) {
      lineas.push('Formación desbloqueada: ' + datosActuales.formacion);
    }
    if (!lineas.length) { bonus.hidden = true; return; }
    bonus.innerHTML = lineas.map(function (l) {
      return '<span><i class="ph-fill ph-sparkle" aria-hidden="true"></i> ' + l + '</span>';
    }).join('');
    bonus.hidden = false;
  }

  function pintarMesa() {
    mesa.innerHTML = '';
    if (!cartas.length) { mesa.hidden = true; return; }
    cartas.forEach(function (carta) {
      var ranura = document.createElement('div');
      ranura.className = 'ranura esta-volteada';
      ranura.dataset.rareza = carta.id_rareza;

      var frente = document.createElement('div');
      frente.className = 'ranura-cara ranura-frente';
      frente.innerHTML = carta.html;

      ranura.appendChild(frente);
      mesa.appendChild(ranura);
    });
    mesa.hidden = false;
    if (!reducido()) {
      gsap.fromTo(mesa.children,
        { opacity: 0, y: 16 },
        {
          opacity: 1, y: 0, duration: .3, stagger: .04, ease: 'power2.out',
          onComplete: function () { gsap.set(mesa.children, { clearProps: 'transform,opacity' }); }
        });
    }
  }

  function anunciar() {
    var partes = [];
    if (datosActuales.monedas > 0) { partes.push(datosActuales.monedas + ' monedas'); }
    if (cartas.length) {
      partes.push(cartas.length + (cartas.length === 1 ? ' carta' : ' cartas') + ': ' +
        cartas.map(function (c) { return c.nombre + ', ' + c.rareza; }).join('. '));
    }
    if (datosActuales.camino_perfecto) { partes.push('premio de camino perfecto'); }
    if (datosActuales.formacion) { partes.push('formación ' + datosActuales.formacion + ' desbloqueada'); }
    anuncio.textContent = partes.length
      ? 'Cofre abierto. Has conseguido ' + partes.join('. ') + '.'
      : 'Cofre abierto.';
  }

  function ofrecerAnimaciones() {
    if (!avisoMotion) return;
    var yaEligio = !!SRF.preferenciaMovimiento();
    avisoMotion.hidden = yaEligio || !reducido();
  }

  function terminar() {
    escena.hidden = true;
    enReparto = false;
    modal.classList.remove('es-inmersiva');
    pintarBonus();
    pintarMesa();
    anunciar();
    btnSaltarCarta.disabled = true;
    btnSaltar.disabled = true;
    ofrecerAnimaciones();
  }

  if (btnActivarMotion) {
    btnActivarMotion.addEventListener('click', function () {
      SRF.fijarPreferenciaMovimiento('si');
      avisoMotion.hidden = true;
      ceremoniaCofre(datosActuales);
    });
  }
  if (btnRechazarMotion) {
    btnRechazarMotion.addEventListener('click', function () {
      SRF.fijarPreferenciaMovimiento('no');
      avisoMotion.hidden = true;
    });
  }

  /* ---------------------------------------------------------------------
     Orquestación
     --------------------------------------------------------------------- */
  async function repartir(miSesion) {
    for (indice = 0; indice < cartas.length; indice++) {
      if (saltandoTodo) break;
      await escenaCarta(cartas[indice]);
    }
    if (miSesion !== sesion) return;
    terminar();
  }

  function ceremoniaCofre(datos) {
    if (!datos) return;

    var miSesion = ++sesion;

    datosActuales = {
      monedas: datos.monedas || 0,
      camino_perfecto: !!datos.camino_perfecto,
      formacion: datos.formacion || null,
    };
    cartas = datos.cartas || [];
    indice = 0;
    saltandoTodo = false;
    enReparto = false;
    esperandoClic = false;
    cartaRevelada = false;
    avanzar = null;
    matarTimeline();

    mesa.innerHTML = '';
    mesa.hidden = true;
    bonus.hidden = true;
    escena.hidden = true;
    walkout.hidden = true;
    modal.classList.remove('es-inmersiva');
    if (avisoMotion) avisoMotion.hidden = true;
    caja.classList.remove('es-legendario', 'es-srf', 'en-walkout');

    var maxRareza = cartas.reduce(function (m, c) { return Math.max(m, c.id_rareza); }, 0);
    if (maxRareza === 5) caja.classList.add('es-legendario');
    if (maxRareza === 6) caja.classList.add('es-srf');

    SRF.abrirModal('modalCofre');

    if (reducido()) {
      terminar();
      return;
    }

    btnSaltarCarta.disabled = false;
    btnSaltar.disabled = false;

    escena.hidden = false;
    modal.classList.add('es-inmersiva');
    abrirCofre().then(function () {
      if (miSesion !== sesion) return;
      enReparto = true;
      repartir(miSesion);
    });
  }

  btnSaltarCarta.addEventListener('click', function () {
    if (saltandoTodo || escena.hidden) return;
    if (!enReparto) { saltarAperturaCofre(); return; }
    if (!cartas.length) return;
    if (!cartaRevelada) { finalizarCartaActual(); esperandoClic = true; return; }
    siguienteCarta();
  });

  function saltarTodo() {
    saltandoTodo = true;
    matarTimeline();
    walkout.hidden = true;
    caja.classList.remove('en-walkout');
    gsap.set(caja, { clearProps: 'transform' });
    if (avanzar) { var r = avanzar; avanzar = null; r(); }
    else terminar();
  }

  btnSaltar.addEventListener('click', saltarTodo);
  if (btnSaltarEscena) btnSaltarEscena.addEventListener('click', saltarTodo);

  function detener() {
    saltandoTodo = true;
    matarTimeline();
    walkout.hidden = true;
    caja.classList.remove('en-walkout');
    gsap.set(caja, { clearProps: 'transform' });
    if (avanzar) { var r = avanzar; avanzar = null; r(); }
  }
  document.addEventListener('click', function (e) {
    if (!e.target.closest) return;
    if (e.target.closest('#modalCofre [data-cerrar-modal]')) detener();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal && modal.classList.contains('is-abierto')) detener();
  });

  SRF.ceremoniaCofre = ceremoniaCofre;
})();
