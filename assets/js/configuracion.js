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
   Nivel de movimiento — tres niveles, no un interruptor.

   Existe por dos motivos que se juntan:
   · `prefers-reduced-motion` se activa muchas veces por RENDIMIENTO y no por
     sensibilidad al movimiento (en Windows basta con apagar "Efectos de
     animación" para que Chrome lo reporte), y entonces el jugador se queda
     sin ceremonia de sobres sin entender por qué.
   · Un móvil de gama baja no necesita quedarse sin animaciones: necesita
     otras. Para eso está el nivel intermedio.

   La detección la hace SRF.nivelDetectado() en el bloque inline de
   partials/head.php; lo que se elija aquí manda siempre sobre ella.
   ========================================================================== */
(function () {
  'use strict';

  var select = document.getElementById('selectAnimaciones');
  var estado = document.getElementById('animacionesEstado');
  // SRF.nivelMovimiento la define partials/head.php inline, así que a estas
  // alturas SIEMPRE existe. Si algún día no, es preferible saberlo por consola
  // que dejar un selector que no guarda nada en silencio.
  if (!select) return;
  if (!SRF.nivelMovimiento) {
    console.error('SRF.nivelMovimiento no está definida: revisa el bloque inline de partials/head.php');
    return;
  }

  var NOMBRES = { full: 'Completo', lite: 'Ligero', reduce: 'Mínimo' };

  function describir() {
    var elegido   = SRF.preferenciaMovimiento();
    var detectado = SRF.nivelDetectado();

    if (elegido === 'full')   return 'Verás todos los efectos, aunque tu dispositivo pida menos.';
    if (elegido === 'lite')   return 'Sin efectos 3D ni fondos animados. Las ceremonias se acortan.';
    if (elegido === 'reduce') return 'Sin animaciones. No te pierdes nada del juego: solo el movimiento.';

    if (detectado === 'reduce') {
      return 'Tu sistema pide reducir el movimiento, así que ahora mismo no verás animaciones. ' +
             'Elige «Completo» si quieres verlas igualmente.';
    }
    return 'Automático: hemos elegido el nivel ' + NOMBRES[detectado].toLowerCase() +
           ' para tu dispositivo. Puedes cambiarlo cuando quieras.';
  }

  select.value = SRF.preferenciaMovimiento() || 'auto';
  estado.textContent = describir();

  select.addEventListener('change', function () {
    SRF.fijarNivelMovimiento(select.value === 'auto' ? null : select.value);
    estado.textContent = describir();
    SRF.toast('Nivel de movimiento guardado.', 'success');
  });
})();
