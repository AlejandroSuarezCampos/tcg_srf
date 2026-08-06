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

  function encontrarPortal(idSobre) {
    var disparador = document.querySelector(
      '[data-id-sobre="' + idSobre + '"], [data-id-sobre-unico="' + idSobre + '"]'
    );
    return disparador ? disparador.closest('.pack3d-portal') : null;
  }

  function onEnvelopeTypeSelected(envelopeTypeId) {
    var portal = encontrarPortal(envelopeTypeId);
    if (!portal) return;
    portalAbierto = portal;

    var caja    = portal.querySelector('.pack3d');
    var volumen = portal.querySelector('.pack3d-volumen');
    var tilt    = portal.querySelector('.pack3d-tilt');
    var tapa    = portal.querySelector('.pack3d-tapa');
    var bahia   = portal.querySelector('.pack3d-interior-sobres');

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
    if (reducido()) {
      gsap.set(caja, { scale: 1, opacity: 1 });
      gsap.set(tilt, { rotateX: -52, rotateY: -20 });
      gsap.set(tapa, { rotateX: 205 });
      revealEnvelopesInsideBox(portal);
      return;
    }

    gsap.set(caja, { scale: .6, opacity: 0 });
    gsap.set(tilt, { rotateX: GIRO_X, rotateY: GIRO_Y });
    gsap.set(tapa, { rotateX: 90 });

    gsap.timeline()
      .to(caja, { scale: 1, opacity: 1, duration: .5, ease: 'back.out(1.3)' })
      .to(tilt, { rotateX: -52, rotateY: -20, duration: .7, ease: 'power2.inOut' }, '-=.25')
      .to(tapa, { rotateX: 205, duration: .65, ease: 'power2.inOut' }, '-=.4')
      .call(function () { revealEnvelopesInsideBox(portal); });
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

  function revealEnvelopesInsideBox(portal) {
    var sobres = portal.querySelectorAll('.pack3d-sobre');
    // El estado de reposo ya lo da el CSS (.pack3d--abierta .pack3d-sobre):
    // con reduced-motion no hace falta escribir nada.
    if (reducido()) { limpiarInline(sobres); return; }

    // Se anima --alza, NUNCA "transform"/"y": el transform de .pack3d-sobre
    // lleva su translateZ(var(--z)) de colocación, y un transform en línea de
    // GSAP lo sustituiría entero, apilando todos los sobres en el mismo sitio.
    gsap.fromTo(sobres,
      { opacity: 0, '--alza': '70px' },
      {
        opacity: 1, '--alza': '0px',
        duration: .5, stagger: .012, ease: 'power3.out',
        onComplete: function () { limpiarInline(sobres); }
      });
  }

  function cerrarPortal(portal) {
    if (!portal) return;
    var caja    = portal.querySelector('.pack3d');
    var volumen = portal.querySelector('.pack3d-volumen');
    var tilt    = portal.querySelector('.pack3d-tilt');
    var tapa    = portal.querySelector('.pack3d-tapa');
    var bahia   = portal.querySelector('.pack3d-interior-sobres');

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

    var texto = 'Vas a abrir «' + btn.dataset.nombre + '» por ' + precio.toLocaleString('es-ES') +
      ' monedas. Tienes ' + saldo.toLocaleString('es-ES') + '.';

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
      comprar(btn, bahia, portal);
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
  async function comprar(btn, bahia, portal) {
    btn.disabled = true;
    try {
      var res = await fetch('sobres.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ accion: 'comprar_sobre', id_sobre: btn.dataset.idSobre })
      });
      var data = await res.json();

      revertirSeleccion(btn, bahia, portal);
      btn.disabled = false;

      if (!data.ok) {
        SRF.toast(data.error || 'No se pudo abrir el sobre.', 'danger');
        return;
      }

      if (typeof data.monedas === 'number') actualizarSaldo(data.monedas);
      cerrarPortal(portal);
      SRF.ceremonia(data.cartas || [], {
        nombre:  btn.dataset.nombre,
        imagen:  btn.dataset.imagen,
        frente:  btn.dataset.frente,
        reverso: btn.dataset.reverso
      });
    } catch (err) {
      console.error(err);
      revertirSeleccion(btn, bahia, portal);
      btn.disabled = false;
      SRF.toast('No se pudo conectar con el servidor.', 'danger');
    }
  }

  function actualizarSaldo(monedas) {
    if (typeof actualizarMonedasNav === 'function') actualizarMonedasNav(monedas);
    var saldo = document.getElementById('saldoMonedas');
    if (saldo) saldo.textContent = monedas.toLocaleString('es-ES');

    Array.prototype.forEach.call(document.querySelectorAll('.js-sobre-individual'), function (b) {
      var precio = parseInt(b.dataset.precio, 10) || 0;
      b.disabled = precio > monedas;
      b.title = precio > monedas ? 'No tienes monedas suficientes' : '';
    });
  }

  Array.prototype.forEach.call(document.querySelectorAll('.js-sobre-individual'), function (btn) {
    btn.addEventListener('click', function (e) {
      // Los 50 sobres viven a propósito DENTRO de .js-tipo-sobre/.js-caja-expansion
      // (Fase 3, mismo árbol preserve-3d). Sin cortar la burbuja aquí, el click
      // reabriría la caja por encima (onEnvelopeTypeSelected), que resetea
      // .es-seleccionado justo después de aplicarla.
      e.stopPropagation();
      if (btn.disabled) return;
      selectEnvelope(btn);
    });
  });

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
