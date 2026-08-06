// nav scroll state
const nav = document.getElementById('nav');
if (nav) {
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 40);
  });
}

// Vista previa de la foto de perfil antes de subirla
const inputFoto = document.getElementById('f_foto');
const previewImg = document.getElementById('fotoPreview');
const previewIniciales = document.getElementById('fotoIniciales');
const nombreArchivoEl = document.getElementById('fotoNombreArchivo');

if (inputFoto) {
  inputFoto.addEventListener('change', () => {
    const archivo = inputFoto.files[0];
    if (!archivo) return;

    nombreArchivoEl.textContent = archivo.name;

    const lector = new FileReader();
    lector.onload = (e) => {
      previewImg.src = e.target.result;
      previewImg.style.display = 'block';
      if (previewIniciales) previewIniciales.style.display = 'none';
    };
    lector.readAsDataURL(archivo);
  });
}

// Canje de códigos desde configuración y perfil
const formCanjearCodigo = document.getElementById('formCanjearCodigo');
const inputCodigo = document.getElementById('inputCodigo');
const codigoFeedback = document.getElementById('codigoFeedback');

if (formCanjearCodigo && inputCodigo && codigoFeedback) {
  formCanjearCodigo.addEventListener('submit', async (e) => {
    e.preventDefault();

    const codigo = inputCodigo.value.trim().toUpperCase();
    if (!codigo) {
      codigoFeedback.textContent = 'Escribe un código antes de canjearlo.';
      codigoFeedback.className = 'redeem-feedback error';
      return;
    }

    codigoFeedback.textContent = 'Canjeando...';
    codigoFeedback.className = 'redeem-feedback';

    const formData = new FormData();
    formData.append('codigo', codigo);

    try {
      const respuesta = await fetch('./assets/ajax/canjear_codigo.php', {
        method: 'POST',
        body: formData,
      });

      const resultado = await respuesta.json();

      if (resultado.ok) {
        codigoFeedback.textContent = `¡Código canjeado! Ganaste ${resultado.monedas_ganadas} monedas.`;
        codigoFeedback.className = 'redeem-feedback success';
        formCanjearCodigo.reset();
      } else {
        codigoFeedback.textContent = resultado.error || 'No se pudo canjear el código.';
        codigoFeedback.className = 'redeem-feedback error';
      }
    } catch (error) {
      codigoFeedback.textContent = 'No se pudo conectar con el servidor.';
      codigoFeedback.className = 'redeem-feedback error';
    }
  });
}