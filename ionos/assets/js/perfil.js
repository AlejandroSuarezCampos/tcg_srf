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
    aviso.style.color = tipo === 'ok' ? 'var(--success-text)'
      : tipo === 'error' ? 'var(--danger-text)'
      : 'var(--frost-dim)';
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
