/* ==========================================================================
   SOBRES — caja de expansión → tipos de sobre → tapa que se abre → sobres
   dentro de la caja (prompt-claude-code-sobres-3d.md, v2). Todo el volumen es
   CSS 3D falso, GSAP solo orquesta timing/easing. Cada función de fase es
   reutilizable a propósito: el modo "Vista previa" de panel/plantillas.php
   llama a las mismas funciones sobre el mismo componente que usa el juego.

   "Portal" (ver components/caja3d.php y components.css): la MISMA caja que
   vive en la vitrina o el submenú pasa a position:fixed a pantalla completa
   al abrirse — nunca se clona ni se mueve de sitio en el DOM, solo cambia de
   clase. Los 50 sobres viven siempre dentro de .caja3d-interior-scroll, en el
   mismo árbol preserve-3d que la caja (requisito técnico del prompt).
   ========================================================================== */
(function () {
  'use strict';

  var reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ------------------------------------------------------------------------
     TILT AL CURSOR: reutilizable en cualquier .caja3d (vitrina, submenú,
     vista previa del admin). Gira .caja3d-tilt, nunca .caja3d-caras (esa la
     anima la animación CSS de flotación, o GSAP directamente si la caja está
     abierta — ver openBlisterLid).
     ------------------------------------------------------------------------ */
  function iniciarTiltCaja(cajaEl) {
    if (reducido) return;
    var tilt = cajaEl.querySelector('.caja3d-tilt');
    if (!tilt) return;

    var girarX = gsap.quickTo(tilt, 'rotateX', { duration: .3, ease: 'power2.out' });
    var girarY = gsap.quickTo(tilt, 'rotateY', { duration: .3, ease: 'power2.out' });

    cajaEl.addEventListener('mousemove', function (e) {
      var r = cajaEl.getBoundingClientRect();
      var px = (e.clientX - r.left) / r.width - .5;
      var py = (e.clientY - r.top) / r.height - .5;
      girarY(px * 26);
      girarX(py * -22);
    });
    cajaEl.addEventListener('mouseleave', function () {
      girarX(0);
      girarY(0);
    });
  }

  Array.prototype.forEach.call(document.querySelectorAll('.caja3d'), iniciarTiltCaja);

  /* ------------------------------------------------------------------------
     FASE 1→2 — openMainBoxAnimation / showEnvelopeTypeBoxes
     ------------------------------------------------------------------------ */
  var vitrina = document.getElementById('vitrina');

  function openMainBoxAnimation(expansionId, cajaBtn) {
    if (cajaBtn.dataset.idSobreUnico) {
      openBlisterLid(cajaBtn.dataset.idSobreUnico);
      return;
    }
    showEnvelopeTypeBoxes(expansionId, cajaBtn);
  }

  function showEnvelopeTypeBoxes(expansionId, cajaBtn) {
    var submenu = document.getElementById('submenu-' + expansionId);
    if (!submenu) return;

    Array.prototype.forEach.call(vitrina.querySelectorAll('.js-caja-expansion'), function (btn) {
      if (btn === cajaBtn) {
        gsap.to(btn, { x: -60, scale: .75, duration: reducido ? 0 : .4, ease: 'power2.out' });
      } else {
        gsap.to(btn, { opacity: .15, duration: reducido ? 0 : .3 });
      }
      desactivarComoBoton(btn);
    });

    submenu.hidden = false;
    var cajas = submenu.querySelectorAll('.submenu-tipo');
    if (reducido) {
      gsap.set(cajas, { opacity: 1, y: 0 });
    } else {
      gsap.fromTo(cajas, { opacity: 0, y: 30, scale: .8 },
        { opacity: 1, y: 0, scale: 1, duration: .4, stagger: .07, ease: 'back.out(1.5)' });
    }
  }

  function cerrarSubmenu(submenu) {
    submenu.hidden = true;
    Array.prototype.forEach.call(vitrina.querySelectorAll('.js-caja-expansion'), function (btn) {
      gsap.to(btn, { x: 0, scale: 1, opacity: 1, duration: reducido ? 0 : .35, ease: 'power2.out' });
      btn.disabled = false;
    });
  }

  Array.prototype.forEach.call(document.querySelectorAll('.js-caja-expansion'), function (btn) {
    btn.addEventListener('click', function () { openMainBoxAnimation(btn.dataset.expansion, btn); });
  });
  Array.prototype.forEach.call(document.querySelectorAll('.js-tipo-sobre'), function (btn) {
    btn.addEventListener('click', function () { openBlisterLid(btn.dataset.idSobre); });
  });
  Array.prototype.forEach.call(document.querySelectorAll('.js-cerrar-submenu'), function (btn) {
    btn.addEventListener('click', function () { cerrarSubmenu(btn.closest('.submenu-tipos')); });
  });

  /* ------------------------------------------------------------------------
     FASE 3 — openBlisterLid / revealEnvelopesInsideBox
     ------------------------------------------------------------------------ */
  var portalAbierto = null;

  function encontrarPortal(idSobre) {
    var disparador = document.querySelector(
      '[data-id-sobre="' + idSobre + '"], [data-id-sobre-unico="' + idSobre + '"]'
    );
    return disparador ? disparador.closest('.caja3d-portal') : null;
  }

  function openBlisterLid(idSobre) {
    var portal = encontrarPortal(idSobre);
    if (!portal) return;
    portalAbierto = portal;

    var caja   = portal.querySelector('.caja3d');
    var caras  = portal.querySelector('.caja3d-caras');
    var tapa   = portal.querySelector('.caja3d-tapa');
    var scroll = portal.querySelector('.caja3d-interior-scroll');

    scroll.classList.remove('con-seleccion');
    portal.classList.remove('con-seleccion');
    Array.prototype.forEach.call(scroll.querySelectorAll('.sobre3d-mini'), function (el) {
      el.classList.remove('es-seleccionado');
    });

    portal.classList.add('esta-abierta');
    caja.classList.add('caja3d--abierta');
    caras.classList.add('en-portal');

    if (reducido) {
      gsap.set(caja, { scale: 1, opacity: 1 });
      gsap.set(caras, { rotateX: -50, rotateY: -12 });
      gsap.set(tapa, { rotateX: -165, opacity: 0 });
      revealEnvelopesInsideBox(portal);
      return;
    }

    gsap.set(caja, { scale: .55, opacity: 0 });
    gsap.set(caras, { rotateX: -8, rotateY: -24 });
    gsap.set(tapa, { rotateX: 0, opacity: 1 });

    var tl = gsap.timeline();
    tl.to(caja, { scale: 1, opacity: 1, duration: .5, ease: 'back.out(1.4)' })
      .to(caras, { rotateX: -50, rotateY: -12, duration: .6, ease: 'power2.inOut' }, '-=.3')
      .to(tapa, { rotateX: -165, opacity: 0, duration: .5, ease: 'power2.inOut' }, '-=.3')
      .call(function () { revealEnvelopesInsideBox(portal); });
  }

  function revealEnvelopesInsideBox(portal) {
    var sobres = portal.querySelectorAll('.sobre3d-mini');
    if (reducido) {
      gsap.set(sobres, { opacity: 1, scale: 1, y: 0 });
      return;
    }
    gsap.fromTo(sobres,
      { opacity: 0, scale: .5, y: 14 },
      { opacity: 1, scale: 1, y: 0, duration: .35, stagger: .01, ease: 'back.out(1.5)' });
  }

  function cerrarBlister(portal) {
    if (!portal) return;
    var caja  = portal.querySelector('.caja3d');
    var caras = portal.querySelector('.caja3d-caras');
    var tapa  = portal.querySelector('.caja3d-tapa');

    portal.classList.remove('esta-abierta', 'con-seleccion');
    caja.classList.remove('caja3d--abierta');
    caras.classList.remove('en-portal');
    gsap.set(caras, { clearProps: 'transform' });
    gsap.set(tapa, { rotateX: 0, opacity: 1 });
    if (portal === portalAbierto) portalAbierto = null;
  }

  Array.prototype.forEach.call(document.querySelectorAll('.js-cerrar-blister'), function (btn) {
    btn.addEventListener('click', function () { cerrarBlister(btn.closest('.caja3d-portal')); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && portalAbierto) cerrarBlister(portalAbierto);
  });

  /* ------------------------------------------------------------------------
     FASE 4 — liftEnvelopeOnHover / selectEnvelope
     El levantado al pasar el cursor lo resuelve CSS puro (:hover/:focus-visible
     en components.css: 50 mousemove por caja sería más caro y menos fiable
     que dejarlo al navegador). Esta función existe para que el modo "Vista
     previa" del panel pueda forzar el efecto sin un ratón real.
     ------------------------------------------------------------------------ */
  function liftEnvelopeOnHover(sobreEl, activo) {
    sobreEl.classList.toggle('es-hover-forzado', !!activo);
  }

  function selectEnvelope(btn) {
    var portal = btn.closest('.caja3d-portal');
    var scroll = btn.closest('.caja3d-interior-scroll');

    btn.classList.add('es-seleccionado');
    scroll.classList.add('con-seleccion');
    portal.classList.add('con-seleccion');

    var precio = parseInt(btn.dataset.precio, 10) || 0;
    var saldo  = parseInt((document.getElementById('saldoMonedas').textContent || '0').replace(/\D/g, ''), 10) || 0;

    if (saldo < precio) {
      SRF.toast('No tienes monedas suficientes.', 'danger');
      revertirSeleccion(btn, scroll, portal);
      return;
    }

    var texto = 'Vas a abrir «' + btn.dataset.nombre + '» por ' + precio.toLocaleString('es-ES') +
      ' monedas. Tienes ' + saldo.toLocaleString('es-ES') + '.';

    function limpiar() {
      document.removeEventListener('click', alCancelarClick);
      document.removeEventListener('keydown', alCancelarTecla);
    }
    function alCancelarClick(e) {
      if (e.target.closest && e.target.closest('[data-cerrar-modal]')) { limpiar(); revertirSeleccion(btn, scroll, portal); }
    }
    function alCancelarTecla(e) {
      if (e.key === 'Escape') { limpiar(); revertirSeleccion(btn, scroll, portal); }
    }
    document.addEventListener('click', alCancelarClick);
    document.addEventListener('keydown', alCancelarTecla);

    SRF.confirmar(texto, function () {
      limpiar();
      comprar(btn, scroll, portal);
    });
  }

  function revertirSeleccion(btn, scroll, portal) {
    btn.classList.remove('es-seleccionado');
    scroll.classList.remove('con-seleccion');
    portal.classList.remove('con-seleccion');
  }

  async function comprar(btn, scroll, portal) {
    btn.disabled = true;
    try {
      var res = await fetch('sobres.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ accion: 'comprar_sobre', id_sobre: btn.dataset.idSobre })
      });
      var data = await res.json();

      revertirSeleccion(btn, scroll, portal);
      btn.disabled = false;

      if (!data.ok) {
        SRF.toast(data.error || 'No se pudo abrir el sobre.', 'danger');
        return;
      }

      if (typeof data.monedas === 'number') actualizarSaldo(data.monedas);
      cerrarBlister(portal);
      SRF.ceremonia(data.cartas || [], { nombre: btn.dataset.nombre, imagen: btn.dataset.imagen });
    } catch (err) {
      console.error(err);
      revertirSeleccion(btn, scroll, portal);
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
    btn.addEventListener('click', function () {
      if (btn.disabled) return;
      selectEnvelope(btn);
    });
  });

  /* API pública: el modo "Vista previa" del panel de admin llama a estas
     mismas funciones sobre su propio marcado (mismo componente PHP). */
  window.SRF = window.SRF || {};
  window.SRF.sobres3d = {
    iniciarTiltCaja: iniciarTiltCaja,
    openMainBoxAnimation: openMainBoxAnimation,
    showEnvelopeTypeBoxes: showEnvelopeTypeBoxes,
    openBlisterLid: openBlisterLid,
    revealEnvelopesInsideBox: revealEnvelopesInsideBox,
    liftEnvelopeOnHover: liftEnvelopeOnHover,
    selectEnvelope: selectEnvelope
  };
})();
