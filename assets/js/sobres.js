/* ==========================================================================
   SOBRES — compra y arranque de la ceremonia
   La ceremonia en sí vive en assets/js/ceremonia.js (SRF.ceremonia).
   ========================================================================== */
(function () {
  'use strict';

  Array.prototype.forEach.call(document.querySelectorAll('.js-sobre'), function (form) {
    form.addEventListener('submit', async function (e) {
      e.preventDefault();

      var boton = form.querySelector('button[type="submit"]');
      if (boton.disabled) return;

      boton.disabled = true;
      boton.classList.add('is-cargando');

      try {
        var res = await fetch(form.action, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new FormData(form)
        });
        var data = await res.json();

        boton.classList.remove('is-cargando');

        if (!data.ok) {
          SRF.toast(data.error || 'No se pudo abrir el sobre.', 'danger');
          boton.disabled = false;
          return;
        }

        if (typeof data.monedas === 'number') {
          actualizarMonedasNav(data.monedas);
          var saldo = document.getElementById('saldoMonedas');
          if (saldo) saldo.textContent = data.monedas.toLocaleString('es-ES');

          /* se recalcula qué sobres siguen siendo asequibles */
          Array.prototype.forEach.call(document.querySelectorAll('.js-sobre'), function (f) {
            var precio = parseInt(f.dataset.precio, 10) || 0;
            var btn = f.querySelector('button[type="submit"]');
            if (!btn) return;
            if (precio > data.monedas) {
              btn.disabled = true;
              btn.title = 'No tienes monedas suficientes';
            } else {
              btn.disabled = false;
              btn.removeAttribute('title');
            }
          });
        } else {
          boton.disabled = false;
        }

        SRF.ceremonia(data.cartas || []);
      } catch (err) {
        console.error(err);
        boton.classList.remove('is-cargando');
        boton.disabled = false;
        SRF.toast('No se pudo conectar con el servidor.', 'danger');
      }
    });
  });
})();
