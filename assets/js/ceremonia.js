/* ==========================================================================
   CEREMONIA DE APERTURA DE SOBRES
   Tres escenas: el sobre se rasga → las cartas salen DE UNA EN UNA boca abajo
   y el jugador hace clic para voltearlas → resumen con todas.

   Las rarezas altas (legendaria=5, SRF=6) no se voltean sin más: al pedir el
   volteo se dispara antes una secuencia tipo "walkout" (oscurecido, rayos de
   luz girando, nombre de la rareza) y la carta se da la vuelta al final, con
   destello y temblor. Todo saltable.

   Orquestado con GSAP (vendorizado), sin build.

   Compromisos que no se negocian:
   · Saltable carta a carta ("Saltar carta") o de golpe ("Saltar todo").
   · Operable por teclado: la carta es un <button> real (Enter/Espacio).
   · Con prefers-reduced-motion no hay rasgado, walkout ni volteo animado:
     se va directo al resumen con todas las cartas ya reveladas.
   · El resultado se anuncia por una región aria-live.

   Expone SRF.ceremonia(cartas, sobre), donde cada carta es
   { nombre, rareza, id_rareza, html } — el `html` lo genera en servidor el
   mismo componente de tarjeta que usa el resto del sitio — y `sobre` es
   { nombre, imagen, frente, reverso } con las texturas de SU plantilla.
   Expone también el hook SRF.onExclusiveReveal(carta), vacío por defecto,
   listo para engancharle audio sin tocar esta timeline.
   ========================================================================== */
(function () {
  'use strict';

  var modal      = document.getElementById('modalSobre');
  var caja       = document.getElementById('ceremoniaCaja');
  var mesa       = document.getElementById('ceremoniaMesa');
  var anuncio    = document.getElementById('ceremoniaAnuncio');
  var apertura   = document.getElementById('ceremoniaApertura');
  var cerSobre   = document.getElementById('cerSobre');
  var foco       = document.getElementById('ceremoniaFoco');
  var cerCarta   = document.getElementById('cerCarta');
  var cerFrente  = document.getElementById('cerCartaFrente');
  var walkout    = document.getElementById('cerWalkout');
  var walkoutRz  = document.getElementById('cerWalkoutRareza');
  var pista      = document.getElementById('ceremoniaPista');
  var contador   = document.getElementById('ceremoniaContador');
  var btnSaltarCarta = document.getElementById('ceremoniaSaltarCarta');
  var btnSaltar      = document.getElementById('ceremoniaSaltar');
  var avisoMotion    = document.getElementById('cerAvisoMotion');
  var btnActivarMotion  = document.getElementById('cerActivarMotion');
  var btnRechazarMotion = document.getElementById('cerRechazarMotion');
  if (!mesa || !cerCarta) return;

  // Se consulta EN CADA APERTURA, no una vez al cargar: cambiar la preferencia
  // (la del sistema o la propia de configuracion.php) surte efecto sin
  // recargar. SRF.movimientoReducido vive en ui.js, que se carga antes.
  function reducido() {
    return SRF.movimientoReducido
      ? SRF.movimientoReducido()
      : window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  var RZ_COLOR = {
    1: 'var(--rz1)', 2: 'var(--rz2)', 3: 'var(--rz3)',
    4: 'var(--rz4)', 5: 'var(--rz5)', 6: 'var(--rz6)'
  };

  // Estado de la sesión de apertura en curso
  var cartas = [];
  var indice = 0;
  var tlActual = null;       // timeline de la escena en curso (saltable)
  var esperandoClic = false; // true mientras la carta espera el clic del jugador
  var cartaRevelada = false; // la carta actual ya está boca arriba
  var saltandoTodo = false;
  var avanzar = null;        // resolve() de la carta actual
  var sobreActual = null;    // para poder repetir la apertura con animación

  /* ---------------------------------------------------------------------
     Utilidades
     --------------------------------------------------------------------- */
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
     ESCENA 1 — el sobre se rasga en dos mitades.
     Usa las texturas de la plantilla del propio sobre (sobre.frente /
     sobre.reverso); sin plantilla subida cae al degradado del CSS.
     --------------------------------------------------------------------- */
  function escenaSobre(sobre) {
    return new Promise(function (resolve) {
      var arriba = cerSobre.querySelector('.cer-sobre-arriba');
      var abajo  = cerSobre.querySelector('.cer-sobre-abajo');
      var luz    = cerSobre.querySelector('.cer-sobre-luz');

      var textura = (sobre && (sobre.frente || sobre.imagen)) || '';
      [arriba, abajo].forEach(function (m) {
        m.style.backgroundImage = textura ? "url('" + textura + "')" : '';
      });

      apertura.hidden = false;
      gsap.set(cerSobre, { scale: .55, opacity: 0, rotateY: -22 });
      gsap.set([arriba, abajo], { clearProps: 'transform', opacity: 1 });
      gsap.set(luz, { opacity: 0, scale: .5 });

      var tl = gsap.timeline({
        onComplete: function () {
          tlActual = null;
          apertura.hidden = true;
          resolve();
        }
      });
      tlActual = tl;

      tl.to(cerSobre, { scale: 1, opacity: 1, rotateY: 0, duration: .5, ease: 'back.out(1.5)' })
        // temblor de anticipación antes de rasgarse
        .to(cerSobre, { rotate: -2.5, duration: .07, yoyo: true, repeat: 7, ease: 'none' }, '+=.15')
        .to(luz, { opacity: 1, scale: 1.6, duration: .25 }, '-=.12')
        .to(arriba, { yPercent: -85, rotate: -9, opacity: 0, duration: .5, ease: 'power2.in' }, '<+=.05')
        .to(abajo,  { yPercent: 85,  rotate: 9,  opacity: 0, duration: .5, ease: 'power2.in' }, '<')
        .call(destelloPantalla, [], '<+=.1')
        .to(luz, { opacity: 0, duration: .3 }, '-=.2')
        .to(cerSobre, { opacity: 0, duration: .2 }, '-=.15');
    });
  }

  function saltarEscenaSobre() {
    matarTimeline();
    apertura.hidden = true;
  }

  /* ---------------------------------------------------------------------
     ESCENA 2 — una carta: aparece boca abajo y ESPERA EL CLIC.
     --------------------------------------------------------------------- */
  function pintarDorso(carta) {
    cerCarta.className = 'cer-carta';
    cerCarta.dataset.rareza = carta.id_rareza;
    cerCarta.style.setProperty('--rz-aura', RZ_COLOR[carta.id_rareza] || 'var(--amber)');
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

      // entrada del dorso
      var tl = gsap.timeline({
        onComplete: function () { tlActual = null; esperandoClic = true; }
      });
      tlActual = tl;
      tl.fromTo(cerCarta,
        { opacity: 0, scale: .7, y: 40, rotateZ: -6 },
        { opacity: 1, scale: 1, y: 0, rotateZ: 0, duration: .45, ease: 'back.out(1.5)' });
    });
  }

  /* ---------------------------------------------------------------------
     El volteo. Para rareza >= 5 se antepone el walkout.
     --------------------------------------------------------------------- */
  function voltearAhora(carta) {
    cerCarta.classList.add('esta-volteada');
    cartaRevelada = true;
    pista.textContent = 'Toca otra vez para continuar';
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
        .call(function () { voltearAhora(carta); })
        .to(aura, { opacity: 0, duration: .4 }, '+=.15');
      return;
    }

    // ---- WALKOUT (rareza 5 y 6) ----
    walkout.hidden = false;
    walkoutRz.textContent = carta.rareza;
    walkout.style.setProperty('--rz-aura', RZ_COLOR[carta.id_rareza] || 'var(--amber)');
    caja.classList.add('en-walkout');

    var rayos = walkout.querySelector('.cer-walkout-rayos');
    var texto = walkout.querySelector('.cer-walkout-texto');

    tl.set(walkout, { opacity: 0 })
      .set([rayos, texto], { opacity: 0 })
      // la carta se hunde y se oscurece mientras sube la tensión
      .to(cerCarta, { scale: .82, duration: .5, ease: 'power2.out' }, 0)
      .to(walkout, { opacity: 1, duration: .45 }, 0)
      .to(rayos, { opacity: 1, duration: .6 }, '<')
      .to(rayos, { rotate: 360, duration: 3.4, ease: 'none' }, '<')
      .fromTo(texto, { opacity: 0, scale: .75, y: 14 },
        { opacity: 1, scale: 1, y: 0, duration: .55, ease: 'back.out(1.7)' }, '<+=.35')
      // latido del texto: es el "aguanta la respiración" del walkout
      .to(texto, { scale: 1.06, duration: .5, yoyo: true, repeat: 2, ease: 'sine.inOut' })
      .to(texto, { opacity: 0, scale: 1.35, duration: .35, ease: 'power2.in' })
      .to(aura, { opacity: 1, scale: 1.45, duration: .3 }, '<')
      .call(function () { voltearAhora(carta); destelloPantalla(); })
      // temblor de pantalla al destaparse
      .to(caja, { x: -9, duration: .05, yoyo: true, repeat: 7, ease: 'none' }, '<')
      .to(walkout, { opacity: 0, duration: .5 }, '<+=.25')
      .to(aura, { opacity: 0, duration: .6 }, '<')
      .call(function () {
        walkout.hidden = true;
        caja.classList.remove('en-walkout');
        gsap.set(caja, { clearProps: 'transform' });
        gsap.set(cerCarta, { scale: 1 });
      });
  }

  /* Deja la carta actual en su estado final, sin animación. */
  function finalizarCartaActual() {
    matarTimeline();
    walkout.hidden = true;
    caja.classList.remove('en-walkout');
    gsap.set(caja, { clearProps: 'transform' });
    gsap.set(cerCarta, { clearProps: 'transform,opacity' });
    var aura = cerCarta.querySelector('.cer-carta-aura');
    gsap.set(aura, { opacity: 0 });
    if (!cartaRevelada) voltearAhora(cartas[indice]);
  }

  /* ---------------------------------------------------------------------
     Clic / teclado sobre la carta: primero la voltea, después avanza.
     --------------------------------------------------------------------- */
  cerCarta.addEventListener('click', function () {
    if (saltandoTodo) return;
    if (!cartaRevelada) { pedirVolteo(cartas[indice]); return; }
    if (!esperandoClic) return;      // walkout aún en curso
    siguienteCarta();
  });

  function siguienteCarta() {
    esperandoClic = false;
    if (!avanzar) return;
    var r = avanzar;
    avanzar = null;
    // salida de la carta ya vista
    if (reducido()) { r(); return; }
    gsap.to(cerCarta, {
      opacity: 0, scale: .8, y: -30, duration: .28, ease: 'power2.in',
      onComplete: r
    });
  }

  /* ---------------------------------------------------------------------
     ESCENA 3 — resumen: todas las cartas ya reveladas
     --------------------------------------------------------------------- */
  function pintarMesa() {
    mesa.innerHTML = '';
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
    var texto = cartas.map(function (c) { return c.nombre + ', ' + c.rareza; }).join('. ');
    anuncio.textContent = 'Has conseguido ' + cartas.length +
      (cartas.length === 1 ? ' carta: ' : ' cartas: ') + texto + '.';
  }

  /* Se ofrece activar las animaciones SOLO si la ceremonia se saltó por la
     preferencia del sistema y el jugador todavía no ha elegido nada aquí.
     Quien haya puesto "Desactivadas siempre" a mano no vuelve a ver el aviso. */
  function ofrecerAnimaciones() {
    if (!avisoMotion) return;
    var esDelSistema = reducido() && SRF.preferenciaMovimiento && !SRF.preferenciaMovimiento();
    avisoMotion.hidden = !esDelSistema;
  }

  function terminar() {
    foco.hidden = true;
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
      // se repite la apertura, ahora sí con ceremonia: las cartas ya están
      // en la colección, esto solo vuelve a reproducir el espectáculo
      ceremonia(cartas, sobreActual);
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
  async function repartir() {
    for (indice = 0; indice < cartas.length; indice++) {
      if (saltandoTodo) break;
      await escenaCarta(cartas[indice]);
    }
    terminar();
  }

  function ceremonia(listaCartas, sobre) {
    if (!listaCartas || !listaCartas.length) return;

    cartas = listaCartas;
    sobreActual = sobre || null;
    indice = 0;
    saltandoTodo = false;
    esperandoClic = false;
    cartaRevelada = false;
    avanzar = null;
    matarTimeline();

    mesa.innerHTML = '';
    mesa.hidden = true;
    foco.hidden = true;
    apertura.hidden = true;
    walkout.hidden = true;
    if (avisoMotion) avisoMotion.hidden = true;
    caja.classList.remove('es-legendario', 'es-srf', 'en-walkout');

    var maxRareza = cartas.reduce(function (m, c) { return Math.max(m, c.id_rareza); }, 1);
    if (maxRareza === 5) caja.classList.add('es-legendario');
    if (maxRareza === 6) caja.classList.add('es-srf');

    SRF.abrirModal('modalSobre');

    if (reducido()) {
      terminar();
      return;
    }

    btnSaltarCarta.disabled = false;
    btnSaltar.disabled = false;

    escenaSobre(sobre).then(function () {
      foco.hidden = false;
      repartir();
    });
  }

  /* ---- saltos ---- */
  btnSaltarCarta.addEventListener('click', function () {
    if (saltandoTodo) return;
    if (!apertura.hidden) { saltarEscenaSobre(); return; }
    if (foco.hidden) return;
    if (!cartaRevelada) { finalizarCartaActual(); esperandoClic = true; return; }
    siguienteCarta();
  });

  btnSaltar.addEventListener('click', function () {
    saltandoTodo = true;
    matarTimeline();
    walkout.hidden = true;
    apertura.hidden = true;
    caja.classList.remove('en-walkout');
    gsap.set(caja, { clearProps: 'transform' });
    if (avanzar) { var r = avanzar; avanzar = null; r(); }
    else terminar();
  });

  /* al cerrar el modal se corta cualquier animación pendiente */
  function detener() {
    saltandoTodo = true;
    matarTimeline();
    walkout.hidden = true;
    caja.classList.remove('en-walkout');
    gsap.set(caja, { clearProps: 'transform' });
    if (avanzar) { var r = avanzar; avanzar = null; r(); }
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-cerrar-modal]')) detener();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal && !modal.hasAttribute('hidden')) detener();
  });

  SRF.onExclusiveReveal = SRF.onExclusiveReveal || function () {};
  SRF.ceremonia = ceremonia;
})();
