/* ==========================================================================
   PERFIL — canje de códigos
   Las pestañas las gestiona assets/js/ui.js (patrón ARIA compartido).
   ========================================================================== */
(function () {
  'use strict';

  var form  = document.getElementById('formCodigo');
  var input = document.getElementById('inputCodigo');
  var aviso = document.getElementById('codigoFeedback');
  if (!form || !input || !aviso) return;

  function pintar(mensaje, tipo) {
    aviso.textContent = mensaje;
    aviso.style.color = tipo === 'ok' ? 'var(--cesped-txt)'
      : tipo === 'error' ? 'var(--roja-txt)'
      : 'var(--ceniza)';
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    var codigo = input.value.trim().toUpperCase();
    if (!codigo) {
      pintar('Escribe un código antes de canjearlo.', 'error');
      input.focus();
      return;
    }

    var boton = form.querySelector('button[type="submit"]');
    boton.disabled = true;
    boton.classList.add('is-cargando');
    pintar('Comprobando el código…');

    var datos = new FormData();
    datos.append('codigo', codigo);
    datos.append('csrf', SRF.csrfToken());

    try {
      var res = await fetch('./assets/ajax/canjear_codigo.php', { method: 'POST', body: datos });
      var resultado = await res.json();

      if (resultado.ok) {
        pintar('Código canjeado: ' + resultado.monedas_ganadas + ' monedas añadidas a tu saldo.', 'ok');
        SRF.toast('Código canjeado correctamente.', 'success');
        form.reset();
        actualizarMonedasNav();
      } else {
        pintar(resultado.error || 'No se pudo canjear el código.', 'error');
      }
    } catch (err) {
      console.error(err);
      pintar('No se pudo conectar con el servidor.', 'error');
    } finally {
      boton.disabled = false;
      boton.classList.remove('is-cargando');
    }
  });
})();


/* --------------------------------------------------------------------------
   VOLVER A VER EL TUTORIAL
   El último paso del tutorial promete que se puede repetir desde el perfil,
   así que la promesa tiene que existir en algún sitio. Reiniciarlo lo deja en
   el primer paso; recargando, el propio tutorial se encarga del resto.
   -------------------------------------------------------------------------- */
(function () {
  'use strict';

  var boton = document.getElementById('btnRepetirTutorial');
  if (!boton) return;
  var aviso = document.getElementById('tutorialFeedback');

  boton.addEventListener('click', function () {
    boton.disabled = true;
    fetch('assets/ajax/tutorial.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ accion: 'reiniciar', csrf: SRF.csrfToken() }),
    })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        if (!r.ok) { boton.disabled = false; aviso.textContent = r.error || 'No se pudo.'; return; }
        aviso.textContent = 'Listo. Te llevo al inicio para empezarlo.';
        window.location.href = 'landing.php';
      })
      .catch(function () { boton.disabled = false; aviso.textContent = 'No se pudo reiniciar.'; });
  });
})();
