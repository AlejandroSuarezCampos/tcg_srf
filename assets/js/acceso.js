/* ==========================================================================
   ACCESO — conmutar entre «Entrar» y «Crear cuenta» sin recargar.
   --------------------------------------------------------------------------
   MEJORA PROGRESIVA, no requisito: las pestañas son enlaces de verdad y sin
   este script la pantalla funciona igual, recargando. Aquí solo se intercepta
   el clic para evitar el viaje al servidor.
   ========================================================================== */
(function () {
  'use strict';

  var panel = document.querySelector('.ac-panel');
  if (!panel) return;

  var formularios = panel.querySelectorAll('[data-panel]');
  var pestanas    = panel.querySelectorAll('.ac-pestana');
  if (!formularios.length) return;

  function mostrar(modo, moverFoco) {
    Array.prototype.forEach.call(formularios, function (f) {
      f.hidden = f.dataset.panel !== modo;
    });
    Array.prototype.forEach.call(pestanas, function (p) {
      var activa = p.dataset.modo === modo;
      p.classList.toggle('es-activa', activa);
      if (activa) p.setAttribute('aria-current', 'page');
      else p.removeAttribute('aria-current');
    });

    /* La URL se actualiza sin recargar: así el botón de atrás del navegador
       deshace el cambio de pestaña, y recargar o compartir el enlace cae en la
       pestaña que se estaba viendo. */
    try {
      history.replaceState(null, '', 'acceso.php?modo=' + modo);
    } catch (e) { /* file:// o navegador antiguo: no pasa nada */ }

    /* El foco se lleva al primer campo del formulario que se acaba de enseñar.
       Sin esto, quien navega con teclado cambia de pestaña y se queda con el
       foco en un formulario que ya no está visible. */
    if (moverFoco) {
      var primero = panel.querySelector('[data-panel="' + modo + '"] input:not([type="hidden"])');
      if (primero) primero.focus();
    }
  }

  panel.addEventListener('click', function (e) {
    var enlace = e.target.closest('[data-modo]');
    if (!enlace) return;
    e.preventDefault();
    mostrar(enlace.dataset.modo, true);
  });
})();
