/* ==========================================================================
   CONFIGURACIÓN — vista previa de la foto antes de subirla
   El canje de códigos lo cubre assets/js/perfil.js, que comparte el mismo
   marcado en las dos pantallas.
   ========================================================================== */
(function () {
  'use strict';

  var input      = document.getElementById('f_foto');
  var vista      = document.getElementById('fotoPreview');
  var iniciales  = document.getElementById('fotoIniciales');
  var nombreArch = document.getElementById('fotoNombreArchivo');
  if (!input || !vista) return;

  input.addEventListener('change', function () {
    var archivo = input.files[0];
    if (!archivo) {
      nombreArch.textContent = 'Ningún archivo seleccionado.';
      return;
    }

    nombreArch.textContent = archivo.name;

    var lector = new FileReader();
    lector.onload = function (e) {
      vista.src = e.target.result;
      vista.hidden = false;
      if (iniciales) iniciales.hidden = true;
    };
    lector.readAsDataURL(archivo);
  });
})();

/* ==========================================================================
   Preferencia de animaciones.

   Existe porque `prefers-reduced-motion` se activa muchas veces por
   rendimiento y no por sensibilidad al movimiento: en Windows basta con
   apagar "Efectos de animación" para que Chrome lo reporte, y entonces el
   jugador se queda sin ceremonia de sobres sin entender por qué. El valor
   vive en localStorage (SRF.fijarPreferenciaMovimiento, en ui.js) y por
   defecto sigue al sistema.
   ========================================================================== */
(function () {
  'use strict';

  var select = document.getElementById('selectAnimaciones');
  var estado = document.getElementById('animacionesEstado');
  if (!select || !SRF.preferenciaMovimiento) return;

  function describir() {
    var delSistema = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var pref = SRF.preferenciaMovimiento();
    if (pref === 'si') return 'Verás las animaciones completas, aunque tu sistema pida reducirlas.';
    if (pref === 'no') return 'No verás animaciones.';
    return delSistema
      ? 'Tu sistema pide reducir el movimiento, así que ahora mismo NO verás animaciones. ' +
        'Elige «Activadas siempre» si quieres verlas igualmente.'
      : 'Tu sistema permite el movimiento, así que verás las animaciones completas.';
  }

  select.value = SRF.preferenciaMovimiento() || 'auto';
  estado.textContent = describir();

  select.addEventListener('change', function () {
    SRF.fijarPreferenciaMovimiento(select.value === 'auto' ? null : select.value);
    estado.textContent = describir();
    SRF.toast('Preferencia de animaciones guardada.', 'success');
  });
})();
