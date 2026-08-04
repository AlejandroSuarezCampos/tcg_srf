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
