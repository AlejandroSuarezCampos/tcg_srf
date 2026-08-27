/* ==========================================================================
   SOBRES — sistema de cajas/sobres en pseudo-3D (rediseño completo).
   Caja de expansión → (si hay 2+ tipos) submenú de tipos → tapa que se abre →
   sobres dentro de la caja → hover/click de sobre individual → compra real.

   Todo el volumen es CSS 3D falso (perspective + preserve-3d + transform);
   GSAP solo orquesta timing/easing (tilt, apertura de tapa, stagger).

   "Portal" (.pack3d-portal, ver components/caja3d.php y components.css): la
   MISMA caja que vive en la vitrina o el submenú pasa a position:fixed a
   pantalla completa al abrirse — nunca se clona ni se mueve de sitio en el
   DOM, solo cambia de clase. Los sobres viven siempre dentro de
   .pack3d-interior-sobres, en el mismo árbol preserve-3d que la caja
   (Fase 3, criterio bloqueante).
   ========================================================================== */
(function () {
  'use strict';

  // Se consulta en cada uso, no una vez al cargar: así cambiar la preferencia
  // (la del sistema o la propia de configuracion.php) surte efecto sin
  // recargar. SRF.movimientoReducido vive en ui.js, que se carga antes.
  // La define partials/head.php inline, antes que cualquier script externo.
  function reducido() {
    return SRF.movimientoReducido();
  }

  /* ------------------------------------------------------------------------
     FASE 1 — idle (CSS, @keyframes pack3dFlota) + tilt al cursor con GSAP.
     Reutilizable en cualquier .pack3d (vitrina, submenú, vista previa admin):
     3+ cajas en la misma página no comparten estado, cada una registra su
     propio listener sobre su propio nodo.
     ------------------------------------------------------------------------ */
  // Orientación de reposo: la misma que fija el CSS en .pack3d-tilt. El tilt
  // al cursor oscila ALREDEDOR de ella, no desde cero — si no, al pasar el
  // ratón la caja daba un salto brusco hasta quedar de frente.
  var GIRO_X = -18, GIRO_Y = -32;

  /* Cuántos sobres abre "Abrir 10". Tiene que coincidir con
     Tcg::SOBRES_POR_TANDA, que es quien acota de verdad: si aquí se pidieran
     más, el servidor recortaría la tanda en silencio. */
  var TANDA = 10;

  function initPackBox(cajaEl) {
    if (reducido()) return;
    // tilt sobre .pack3d-tilt, NUNCA sobre .pack3d-volumen: esa capa lleva la
    // animación CSS de idle (@keyframes pack3dFlota) sobre la misma
    // propiedad "transform", y un tween de GSAP ahí competiría con ella.
    var tilt = cajaEl.querySelector('.pack3d-tilt');
    if (!tilt) return;

    gsap.set(tilt, { rotateX: GIRO_X, rotateY: GIRO_Y });

    var girarX = gsap.quickTo(tilt, 'rotateX', { duration: .4, ease: 'power2.out' });
    var girarY = gsap.quickTo(tilt, 'rotateY', { duration: .4, ease: 'power2.out' });

    cajaEl.addEventListener('mousemove', function (e) {
      if (cajaEl.classList.contains('pack3d--abierta')) return;  // abierta la controla la timeline
      var r = cajaEl.getBoundingClientRect();
      var px = (e.clientX - r.left) / r.width - .5;
      var py = (e.clientY - r.top) / r.height - .5;
      girarY(GIRO_Y + px * 26);
      girarX(GIRO_X + py * -20);
    });
    cajaEl.addEventListener('mouseleave', function () {
      if (cajaEl.classList.contains('pack3d--abierta')) return;
      girarX(GIRO_X);
      girarY(GIRO_Y);
    });
  }

  Array.prototype.forEach.call(document.querySelectorAll('.pack3d'), initPackBox);

  /* ------------------------------------------------------------------------
     FASE 2 — click en caja de expansión → submenú de tipos, o directo a
     Fase 3 si solo hay un tipo. onEnvelopeTypeSelected(envelopeTypeId) es el
     callback que dispara la Fase 3 en ambos casos.
     ------------------------------------------------------------------------ */
  var vitrina = document.getElementById('vitrina');
  var vistaExpansiones = document.getElementById('vistaExpansiones');

  function onExpansionBoxClick(expansionId, cajaBtn) {
    if (cajaBtn.dataset.idSobreUnico) {
      onEnvelopeTypeSelected(cajaBtn.dataset.idSobreUnico);
      return;
    }
    showEnvelopeTypeBoxes(expansionId, cajaBtn);
  }

  function showEnvelopeTypeBoxes(expansionId, cajaBtn) {
    var submenu = document.getElementById('submenu-' + expansionId);
    if (!submenu) return;

    // Al entrar en una expansión se OCULTA la vista de expansiones entera
    // (cabecera + rejilla de cajas): la pantalla pasa a ser solo los tipos de
    // sobre, sin repetir debajo la caja de la que vienes.
    if (vistaExpansiones) vistaExpansiones.hidden = true;
    submenu.hidden = false;
    var cajas = submenu.querySelectorAll('.submenu-tipo');
    if (reducido()) {
      gsap.set(cajas, { clearProps: 'transform,opacity' });
    } else {
      gsap.fromTo(cajas, { opacity: 0, y: 30, scale: .8 },
        {
          opacity: 1, y: 0, scale: 1, duration: .4, stagger: .07, ease: 'back.out(1.5)',
          // IMPRESCINDIBLE limpiar el transform al terminar: .submenu-tipo es
          // ANCESTRO de .pack3d-portal, y un ancestro con transform pasa a ser
          // el bloque contenedor de sus descendientes position:fixed. Con el
          // transform residual de GSAP, el portal de pantalla completa se
          // encogía al tamaño de la cajita del submenú.
          onComplete: function () { gsap.set(cajas, { clearProps: 'transform,opacity' }); }
        });
    }
  }

  function cerrarSubmenu(submenu) {
    submenu.hidden = true;
    if (vistaExpansiones) vistaExpansiones.hidden = false;
    if (!reducido()) {
      gsap.fromTo(vitrina.querySelectorAll('.vitrina-item'),
        { opacity: 0, y: 18 },
        {
          opacity: 1, y: 0, duration: .35, stagger: .05, ease: 'power2.out',
          onComplete: function () {
            gsap.set(vitrina.querySelectorAll('.vitrina-item'), { clearProps: 'transform,opacity' });
          }
        });
    }
  }

  Array.prototype.forEach.call(document.querySelectorAll('.js-caja-expansion'), function (btn) {
    btn.addEventListener('click', function () { onExpansionBoxClick(btn.dataset.expansion, btn); });
  });
  Array.prototype.forEach.call(document.querySelectorAll('.js-tipo-sobre'), function (btn) {
    btn.addEventListener('click', function () { onEnvelopeTypeSelected(btn.dataset.idSobre); });
  });
  Array.prototype.forEach.call(document.querySelectorAll('.js-cerrar-submenu'), function (btn) {
    btn.addEventListener('click', function () { cerrarSubmenu(btn.closest('.submenu-tipos')); });
  });

  /* ------------------------------------------------------------------------
     FASE 3 — onEnvelopeTypeSelected: la caja se centra/escala hacia cámara,
     la tapa gira sobre su eje trasero, se revela el interior y aparecen los
     sobres con stagger, todo dentro del mismo árbol preserve-3d.
     ------------------------------------------------------------------------ */
  var portalAbierto = null;

  /* La apertura de la caja en curso, si la hay.
     ⚠️ SIN ESTO LA ANIMACIÓN SE QUEDA EN BUCLE: cada apertura arranca su
     propia línea de tiempo, y si se vuelve a pulsar antes de que termine
     quedan DOS corriendo sobre los mismos elementos. La segunda hace
     `gsap.set(caja, {scale:.6, opacity:0})` mientras la primera sigue
     llevándola a escala 1, así que la caja se encoge y crece sin parar y ya no
     para nunca sola. Se guarda para poder matar la anterior antes de empezar
     otra. */
  var animacionApertura = null;

  /**
   * Mete los sobres en la caja la PRIMERA vez que se abre.
   *
   * Vienen en un <template> (components/caja3d.php): ahí el navegador no los
   * maqueta ni descarga sus imágenes, así que la pantalla de sobres carga sin
   * 150 botones 3D que nadie está mirando. Al clonarlos hay que volver a
   * enganchar el click, porque los manejadores se pusieron uno a uno sobre los
   * elementos que existían al cargar la página.
   */
  /* CUÁNTOS SOBRES SE METEN EN LA CAJA.
     Cincuenta en pantalla grande, diez en móvil.

     ⚠️ NO ES SOLO ESTÉTICA. En una pantalla de 375 px los cincuenta sobres se
        apilan en unos pocos píxeles de profundidad: no se distingue uno de
        otro, no hay dónde acertar con el dedo, y son cincuenta nodos 3D con su
        textura compuestos por una GPU de móvil cada vez que la caja gira. Con
        diez se ven los cantos, se pueden tocar y la caja va suelta.

     Se decide en el CLIENTE y no en PHP porque el servidor no sabe el tamaño
     de la pantalla: la plantilla trae siempre los cincuenta y aquí se clonan
     los que caben. El `<template>` no maqueta ni descarga nada, así que los
     cuarenta que no se usan no cuestan. */
  var SOBRES_MOVIL = 10;
  var ANCHO_MOVIL = 700;

  function cuantosSobres() {
    return window.innerWidth <= ANCHO_MOVIL ? SOBRES_MOVIL : Infinity;
  }

  function poblarBahia(portal) {
    var bahia = portal.querySelector('.pack3d-interior-sobres');
    var plantilla = portal.querySelector('.pack3d-sobres-plantilla');
    if (!bahia || !plantilla || bahia.children.length) { return; }

    var copia = plantilla.content.cloneNode(true);
    var tope  = cuantosSobres();
    var todos = copia.querySelectorAll('.js-sobre-individual');

    if (todos.length > tope) {
      for (var i = tope; i < todos.length; i++) { todos[i].remove(); }

      /* ⚠️ HAY QUE REESCRIBIR --i Y --n. El CSS coloca cada sobre en
         profundidad con `--z: (i - (n-1)/2) * (d*.88/n)`, y `--n` lo escribió
         PHP con el total de la plantilla. Quedándose diez sobres que siguen
         diciendo `--n:50`, los diez se amontonan en la quinta parte delantera
         de la caja en vez de repartirse por dentro: parecía que la caja se
         había quedado medio vacía y torcida. */
      var quedan = copia.querySelectorAll('.js-sobre-individual');
      Array.prototype.forEach.call(quedan, function (el, n) {
        el.style.setProperty('--i', n);
        el.style.setProperty('--n', quedan.length);
      });
    }

    bahia.appendChild(copia);

    var nuevos = bahia.querySelectorAll('.js-sobre-individual');
    Array.prototype.forEach.call(nuevos, engancharSobre);
    /* El estado de "no te llega" venía calculado del servidor al cargar la
       página; si desde entonces se han gastado o ganado monedas, estaría
       mintiendo. Se recalcula con el saldo que se ve ahora. */
    aplicarSaldo(nuevos, saldoActual());
  }

  function encontrarPortal(idSobre) {
    var disparador = document.querySelector(
      '[data-id-sobre="' + idSobre + '"], [data-id-sobre-unico="' + idSobre + '"]'
    );
    return disparador ? disparador.closest('.pack3d-portal') : null;
  }

  function onEnvelopeTypeSelected(envelopeTypeId) {
    var portal = encontrarPortal(envelopeTypeId);
    if (!portal) return;

    /* ⚠️ EL BUCLE DE LA CAJA SE CORTA AQUÍ, NO EN LOS SOBRES.

       Los sobres ya estaban protegidos (`engancharSobre` mira
       `.esta-animando`), pero el bucle no venía de pulsar un sobre: venía de
       pulsar DONDE TODAVÍA NO HAY NINGUNO. Mientras la caja se abre y los
       sobres aún no han subido, ese trozo de pantalla sigue siendo la caja, y
       el clic caía en este manejador — que mata la animación en curso y vuelve
       a montarla entera desde el principio. Pulsando mientras se abre, la
       apertura se reiniciaba una y otra vez y la caja no llegaba nunca a
       terminar: el bucle que se veía.

       Con la caja ya abierta o abriéndose, volver a pulsarla no significa
       nada, así que no se hace nada. Cerrar sigue siendo cosa del botón de
       cerrar, que llama a `cerrarPortal()` y sí levanta el candado. */
    if (portal.classList.contains('esta-animando') ||
        portal.classList.contains('esta-abierta')) {
      return;
    }

    // Se corta lo que estuviera corriendo antes de montar la siguiente.
    if (animacionApertura) { animacionApertura.kill(); animacionApertura = null; }
    portalAbierto = portal;

    var caja    = portal.querySelector('.pack3d');
    var volumen = portal.querySelector('.pack3d-volumen');
    var tilt    = portal.querySelector('.pack3d-tilt');
    var tapa    = portal.querySelector('.pack3d-tapa');
    var bahia   = portal.querySelector('.pack3d-interior-sobres');

    poblarBahia(portal);

    bahia.classList.remove('con-seleccion');
    portal.classList.remove('con-seleccion');
    Array.prototype.forEach.call(bahia.querySelectorAll('.pack3d-sobre'), function (el) {
      el.classList.remove('es-seleccionado');
    });

    portal.classList.add('esta-abierta');
    caja.classList.add('pack3d--abierta');
    volumen.classList.add('en-portal');

    // Abierta se mira MUY desde arriba (rotateX ~-52°): es lo que deja ver el
    // interior y los cantos de los sobres, como una caja de cromos real
    // abierta sobre la mesa. La tapa va de 90° (tumbada, cerrada) a 205°
    // (de pie, inclinada hacia atrás) girando sobre su charnela trasera.
    /* Mientras la caja se abre y los sobres suben, la bahía NO acepta clics.
       Pulsar un sobre a medio camino dejaba la animación de entrada corriendo
       por debajo de la ceremonia y la caja no volvía nunca a su sitio.

       Se pide la ficha ANTES de la bifurcación de movimiento reducido: sin
       animación el candado dura un suspiro, pero invalidar la apertura
       anterior hay que hacerlo igual — si no, el final tardío de la que
       acabamos de matar podría llegar después. */
    var ficha = bloquearBahia(portal);

    if (reducido()) {
      gsap.set(caja, { scale: 1, opacity: 1 });
      gsap.set(tilt, { rotateX: -52, rotateY: -20 });
      gsap.set(tapa, { rotateX: 205 });
      revealEnvelopesInsideBox(portal, ficha);
      return;
    }

    gsap.set(caja, { scale: .6, opacity: 0 });
    gsap.set(tilt, { rotateX: GIRO_X, rotateY: GIRO_Y });
    gsap.set(tapa, { rotateX: 90 });

    animacionApertura = gsap.timeline({
      onComplete: function () { animacionApertura = null; },
    })
      .to(caja, { scale: 1, opacity: 1, duration: .5, ease: 'back.out(1.3)' })
      .to(tilt, { rotateX: -52, rotateY: -20, duration: .7, ease: 'power2.inOut' }, '-=.25')
      .to(tapa, { rotateX: 205, duration: .65, ease: 'power2.inOut' }, '-=.4')
      .call(function () { revealEnvelopesInsideBox(portal, ficha); });
  }

  // Deja el sobre exactamente como lo describe el CSS, sin inline residual.
  // NO se puede usar clearProps:'all': --i y --n van en el atributo style
  // (los escribe pack3d_sobre_html) y son los que colocan cada sobre en
  // profundidad — borrarlos amontonaría los 50 en el centro de la caja.
  function limpiarInline(sobres) {
    Array.prototype.forEach.call(sobres, function (el) {
      el.style.removeProperty('opacity');
      el.style.removeProperty('--alza');
    });
  }

  /* --------------------------------------------------------------------
     EL BLOQUEO SIEMPRE SE LEVANTA — Y SOLO LO LEVANTA QUIEN LO PUSO

     La bahía se cierra a los clics mientras entran los sobres, y se abre en
     cuanto terminan. El problema es "en cuanto terminan": GSAP corre sobre
     requestAnimationFrame, y rAF NO SE EJECUTA con la pestaña en segundo
     plano. Alguien que abra una caja y se cambie de pestaña deja la animación
     congelada a medias; si además la pestaña se recupera rara, el bloqueo se
     queda puesto y esa caja ya no se puede usar más.

     Así que además del final normal hay un TECHO de reloj de pared: pasado
     ese tiempo la bahía se abre pase lo que pase. Nunca hay un estado del que
     no se pueda salir.

     ⚠️ CADA APERTURA LLEVA SU FICHA, Y ESO ES LO QUE ARREGLA EL CLIC
        PREMATURO. Antes el candado era un booleano —la clase `esta-animando`—
        con UN SOLO temporizador para toda la página, y el tween de entrada
        llamaba a `desbloquearBahia` desde `onInterrupt`. Bastaba con volver a
        pulsar la caja mientras entraban los sobres: la apertura nueva ponía el
        candado, la vieja moría, su `onInterrupt` lo quitaba, y a partir de ahí
        se podía pulsar un sobre con la caja todavía a medio abrir. De ahí la
        ceremonia lanzándose sobre una animación viva y la caja que ya no
        volvía a su sitio.

        Ahora cada apertura saca un número (`portal._srfApertura`) y solo puede
        levantar el candado quien lo puso. El desbloqueo de una apertura
        muerta es un no-op, y el temporizador es POR PORTAL: dos cajas abiertas
        no se pisan el reloj la una a la otra.
     -------------------------------------------------------------------- */
  var TECHO_BLOQUEO = 4000;

  /** Abre una apertura nueva sobre este portal y devuelve su ficha. */
  function bloquearBahia(portal) {
    var ficha = (portal._srfApertura || 0) + 1;
    portal._srfApertura = ficha;

    portal.classList.add('esta-animando');
    if (portal._srfReloj) { clearTimeout(portal._srfReloj); }
    portal._srfReloj = setTimeout(function () { desbloquearBahia(portal, ficha); }, TECHO_BLOQUEO);

    return ficha;
  }

  /**
   * Levanta el candado. Con `ficha`, solo si esa apertura sigue siendo la
   * vigente; sin ella (cierre del portal) a la fuerza, invalidando de paso
   * cualquier apertura en vuelo para que su final tardío no toque nada.
   */
  function desbloquearBahia(portal, ficha) {
    if (ficha !== undefined && ficha !== portal._srfApertura) { return; }
    if (ficha === undefined) { portal._srfApertura = (portal._srfApertura || 0) + 1; }

    if (portal._srfReloj) { clearTimeout(portal._srfReloj); portal._srfReloj = null; }
    portal.classList.remove('esta-animando');
  }

  function revealEnvelopesInsideBox(portal, ficha) {
    var sobres = portal.querySelectorAll('.pack3d-sobre');
    var abrir = function () { desbloquearBahia(portal, ficha); };

    // El estado de reposo ya lo da el CSS (.pack3d--abierta .pack3d-sobre):
    // con reduced-motion no hace falta escribir nada.
    if (reducido()) { limpiarInline(sobres); abrir(); return; }

    // Se anima --alza, NUNCA "transform"/"y": el transform de .pack3d-sobre
    // lleva su translateZ(var(--z)) de colocación, y un transform en línea de
    // GSAP lo sustituiría entero, apilando todos los sobres en el mismo sitio.
    gsap.fromTo(sobres,
      { opacity: 0, '--alza': '70px' },
      {
        opacity: 1, '--alza': '0px',
        duration: .5, stagger: .012, ease: 'power3.out',
        /* `onInterrupt` además de `onComplete`: si la caja se cierra a mitad,
           GSAP mata el tween y `onComplete` NO se llama. Sin esto la bahía se
           quedaba bloqueada para siempre y no se podía volver a abrir. La
           ficha hace que, si quien interrumpe es una apertura NUEVA, este
           desbloqueo no le quite el candado a ella. */
        onComplete: function () { limpiarInline(sobres); abrir(); },
        onInterrupt: abrir
      });
  }

  function cerrarPortal(portal) {
    if (!portal) return;
    var caja    = portal.querySelector('.pack3d');
    var volumen = portal.querySelector('.pack3d-volumen');
    var tilt    = portal.querySelector('.pack3d-tilt');
    var tapa    = portal.querySelector('.pack3d-tapa');
    var bahia   = portal.querySelector('.pack3d-interior-sobres');

    /* El candado se levanta ANTES de matar nada, no después: `desbloquearBahia`
       sin ficha invalida la apertura en vuelo, así que el `onInterrupt` que
       van a disparar los `kill` de abajo llega ya caducado y no toca el estado
       de una apertura posterior. Al revés —matar primero— funcionaba de
       casualidad y dependía del orden. */
    desbloquearBahia(portal);
    if (animacionApertura) { animacionApertura.kill(); animacionApertura = null; }
    gsap.killTweensOf([caja, tilt, tapa]);
    gsap.killTweensOf(bahia.querySelectorAll('.pack3d-sobre'));

    portal.classList.remove('esta-abierta', 'con-seleccion');
    caja.classList.remove('pack3d--abierta');
    volumen.classList.remove('en-portal');
    bahia.classList.remove('con-seleccion');
    Array.prototype.forEach.call(bahia.querySelectorAll('.pack3d-sobre'), function (el) {
      el.classList.remove('es-seleccionado');
    });
    limpiarInline(bahia.querySelectorAll('.pack3d-sobre'));

    gsap.set(caja, { clearProps: 'transform,opacity' });
    gsap.set(tilt, { rotateX: GIRO_X, rotateY: GIRO_Y });
    gsap.set(tapa, { rotateX: 90 });
    if (portal === portalAbierto) portalAbierto = null;
  }

  Array.prototype.forEach.call(document.querySelectorAll('.js-cerrar-blister'), function (btn) {
    btn.addEventListener('click', function () { cerrarPortal(btn.closest('.pack3d-portal')); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && portalAbierto) cerrarPortal(portalAbierto);
  });

  /* ------------------------------------------------------------------------
     FASE 4 — liftEnvelopeOnHover / selectEnvelope.
     El levantado al pasar el cursor lo resuelve CSS puro (:hover/
     :focus-visible en components.css: 50 mousemove por caja sería más caro y
     menos fiable que dejarlo al navegador — el criterio de aceptación pide
     "eleva únicamente el sobre bajo el cursor", que :hover garantiza gratis).
     liftEnvelopeOnHover() existe para que el modo "Vista previa" del panel
     pueda forzar el efecto sin un ratón real.

     El click dispara la compra real (fetch a sobres.php, acción existente
     "comprar_sobre") y reutiliza SRF.ceremonia — la ceremonia de reveal de
     cartas por rareza ya existente en el proyecto (partials/ceremonia.php +
     assets/js/ceremonia.js), sin reimplementarla aquí.
     ------------------------------------------------------------------------ */
  function liftEnvelopeOnHover(sobreEl, activo) {
    sobreEl.classList.toggle('es-hover-forzado', !!activo);
  }

  function selectEnvelope(btn) {
    var portal = btn.closest('.pack3d-portal');
    var bahia  = btn.closest('.pack3d-interior-sobres');

    btn.classList.add('es-seleccionado');
    bahia.classList.add('con-seleccion');
    portal.classList.add('con-seleccion');

    var precio = parseInt(btn.dataset.precio, 10) || 0;
    var saldo  = parseInt((document.getElementById('saldoMonedas').textContent || '0').replace(/\D/g, ''), 10) || 0;

    if (saldo < precio) {
      SRF.toast('No tienes monedas suficientes.', 'danger');
      revertirSeleccion(btn, bahia, portal);
      return;
    }

    var precioTanda = precio * TANDA;
    var llegaTanda  = saldo >= precioTanda;

    var texto = 'Vas a abrir «' + btn.dataset.nombre + '» por ' + precio.toLocaleString('es-ES') +
      ' monedas. Tienes ' + saldo.toLocaleString('es-ES') + '. ' +
      (llegaTanda
        ? 'Abrir ' + TANDA + ' cuesta ' + precioTanda.toLocaleString('es-ES') + '.'
        : 'Para abrir ' + TANDA + ' necesitas ' + precioTanda.toLocaleString('es-ES') + '.');

    function limpiar() {
      document.removeEventListener('click', alCancelarClick);
      document.removeEventListener('keydown', alCancelarTecla);
    }
    function alCancelarClick(e) {
      if (e.target.closest && e.target.closest('[data-cerrar-modal]')) { limpiar(); revertirSeleccion(btn, bahia, portal); }
    }
    function alCancelarTecla(e) {
      if (e.key === 'Escape') { limpiar(); revertirSeleccion(btn, bahia, portal); }
    }
    document.addEventListener('click', alCancelarClick);
    document.addEventListener('keydown', alCancelarTecla);

    SRF.confirmar(texto, function () {
      limpiar();
      comprar(btn, bahia, portal, 1);
    }, {
      aceptar: 'Abrir 1',
      extra: {
        texto: 'Abrir ' + TANDA,
        desactivado: !llegaTanda,
        titulo: llegaTanda ? '' : 'No tienes monedas suficientes para ' + TANDA + ' sobres',
        alPulsar: function () { limpiar(); comprar(btn, bahia, portal, TANDA); }
      }
    });
  }

  function revertirSeleccion(btn, bahia, portal) {
    btn.classList.remove('es-seleccionado');
    bahia.classList.remove('con-seleccion');
    portal.classList.remove('con-seleccion');
  }

  // "el resto de sobres y la caja se desvanecen al iniciarse la selección"
  // (Fase 4): ya lo resuelve .con-seleccion en components.css (opacity 0/.2)
  // en el momento del click (selectEnvelope), antes de la confirmación.
  async function comprar(btn, bahia, portal, veces) {
    veces = Math.max(1, parseInt(veces, 10) || 1);
    btn.disabled = true;
    try {
      var res = await fetch('sobres', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({
          accion: 'comprar_sobre', id_sobre: btn.dataset.idSobre,
          veces: veces, csrf: SRF.csrfToken()
        })
      });
      var data = await res.json();

      revertirSeleccion(btn, bahia, portal);
      btn.disabled = false;

      if (!data.ok) {
        SRF.toast(data.error || 'No se pudo abrir el sobre.', 'danger');
        return;
      }

      // Se pidieron diez y salieron siete porque se acabaron las monedas: hay
      // que decirlo, o parece que faltan tres sobres.
      if (data.aviso) { SRF.toast(data.aviso, 'warning'); }

      if (typeof data.monedas === 'number') actualizarSaldo(data.monedas);
      cerrarPortal(portal);
      mostrarBotin(btn, data);
    } catch (err) {
      console.error(err);
      revertirSeleccion(btn, bahia, portal);
      btn.disabled = false;
      SRF.toast('No se pudo conectar con el servidor.', 'danger');
    }
  }

  /**
   * Enseña lo que ha tocado y deja enganchada la reapertura.
   *
   * `repetir` vuelve a `comprar()` con el MISMO botón de sobre, así que la
   * reapertura pasa por el mismo camino de siempre —mismo cobro, mismo
   * saldo, mismo aviso si se corta— pero sin la animación de la caja: el
   * sobre ya está elegido y volver a elegirlo no aporta nada.
   */
  function mostrarBotin(btn, data) {
    var sobres = data.sobres || [];
    var todas  = sobres.length ? [].concat.apply([], sobres) : (data.cartas || []);
    if (!todas.length) return;

    SRF.ceremonia(todas, {
      nombre:  btn.dataset.nombre,
      imagen:  btn.dataset.imagen,
      frente:  btn.dataset.frente,
      reverso: btn.dataset.reverso
    }, {
      paquetes: Math.max(1, sobres.length),
      repetir: function (veces) {
        var bahia  = btn.closest('.pack3d-interior-sobres');
        var portal = btn.closest('.pack3d-portal');
        var precio = parseInt(btn.dataset.precio, 10) || 0;

        // El saldo pudo quedarse corto entre una tanda y la siguiente.
        if (saldoActual() < precio * veces) {
          SRF.toast('No tienes monedas suficientes.', 'danger');
          return;
        }
        comprar(btn, bahia, portal, veces);
      }
    });
  }

  /** Cuántas monedas hay ahora mismo, según lo que se ve en pantalla. */
  function saldoActual() {
    var el = document.getElementById('saldoMonedas');
    return parseInt((el ? el.textContent : '0').replace(/\D/g, ''), 10) || 0;
  }

  /* Un sobre que no te puedes permitir sale apagado. Se aplica sobre la lista
     que se le pase y no sobre toda la página: los sobres de una caja que aún
     no se ha abierto viven en un <template> y no están en el documento, así
     que hay que repasarlos también al clonarlos. */
  function aplicarSaldo(nodos, monedas) {
    Array.prototype.forEach.call(nodos, function (b) {
      var precio = parseInt(b.dataset.precio, 10) || 0;
      b.disabled = precio > monedas;
      b.title = precio > monedas ? 'No tienes monedas suficientes' : '';
    });
  }

  function actualizarSaldo(monedas) {
    if (typeof actualizarMonedasNav === 'function') actualizarMonedasNav(monedas);
    var saldo = document.getElementById('saldoMonedas');
    if (saldo) saldo.textContent = monedas.toLocaleString('es-ES');

    aplicarSaldo(document.querySelectorAll('.js-sobre-individual'), monedas);
  }

  function engancharSobre(btn) {
    btn.addEventListener('click', function (e) {
      // Los 50 sobres viven a propósito DENTRO de .js-tipo-sobre/.js-caja-expansion
      // (Fase 3, mismo árbol preserve-3d). Sin cortar la burbuja aquí, el click
      // reabriría la caja por encima (onEnvelopeTypeSelected), que resetea
      // .es-seleccionado justo después de aplicarla.
      e.stopPropagation();
      if (btn.disabled) return;
      // Cinturón además del CSS: un clic sintético o un Enter con el teclado
      // no lo para `pointer-events`.
      if (btn.closest('.pack3d-portal.esta-animando')) return;
      selectEnvelope(btn);
    });
  }

  // Los que ya estén en la página al cargar (la vista previa del panel).
  Array.prototype.forEach.call(document.querySelectorAll('.js-sobre-individual'), engancharSobre);

  /* API pública: el modo "Vista previa" del panel de admin (Fase 5) llama a
     estas mismas funciones sobre su propio marcado (mismo componente PHP),
     sin reimplementar el motor 3D del juego. */
  window.SRF = window.SRF || {};
  window.SRF.pack3d = {
    initPackBox: initPackBox,
    onExpansionBoxClick: onExpansionBoxClick,
    showEnvelopeTypeBoxes: showEnvelopeTypeBoxes,
    onEnvelopeTypeSelected: onEnvelopeTypeSelected,
    revealEnvelopesInsideBox: revealEnvelopesInsideBox,
    liftEnvelopeOnHover: liftEnvelopeOnHover,
    selectEnvelope: selectEnvelope
  };
})();


/* --------------------------------------------------------------------------
   SOBRE DE BIENVENIDA (migración `039`)

   Va en su propio bloque arriba de la página, fuera de las cajas de expansión:
   es gratis, se abre una sola vez y el tutorial lo pide. Enterrado entre los
   de pago no lo encontraba nadie.

   Se abre por el MISMO camino que los demás —`comprar_sobre`— para que tenga
   su ceremonia. Es el primer sobre que ve una persona; justo el que no puede
   abrirse con una recarga y once cartas apareciendo de golpe en una lista.
   -------------------------------------------------------------------------- */
(function () {
  'use strict';

  var boton = document.getElementById('btnSobreInicial');
  if (!boton) return;

  boton.addEventListener('click', async function () {
    boton.disabled = true;
    try {
      var res = await fetch('sobres', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({
          accion: 'comprar_sobre',
          id_sobre: boton.dataset.idSobre,
          csrf: SRF.csrfToken(),
        }),
      });
      var data = await res.json();

      if (!data.ok) {
        boton.disabled = false;
        SRF.toast(data.error || 'No se pudo abrir el sobre.', 'danger');
        return;
      }

      /* El bloque entero desaparece: el sobre de bienvenida no se abre dos
         veces, y dejar el botón ahí solo invita a intentarlo. */
      var bloque = boton.closest('.sobre-inicial');
      if (bloque) { bloque.remove(); }

      SRF.ceremonia(data.cartas || [], {
        nombre: boton.dataset.nombre,
        imagen: boton.dataset.imagen,
      });
    } catch (e) {
      boton.disabled = false;
      SRF.toast('No se pudo abrir el sobre.', 'danger');
    }
  });
})();
