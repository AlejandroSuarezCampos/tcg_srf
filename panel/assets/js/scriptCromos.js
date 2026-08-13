/**
 * Lógica de panel/cromos.php: rellenar el modal de creación/edición con los
 * datos del cromo al pulsar "Editar", y pedir confirmación antes de navegar
 * al borrado real (cromos.php?eliminar=ID).
 *
 * El modal en sí (abrir/cerrar, Esc, clic fuera, foco atrapado) lo lleva
 * SRF.abrirModal()/cerrarModal() de assets/js/ui.js — igual que en el resto
 * del sitio, no hay nada propio de este panel.
 *
 * Nota sobre imágenes: en la base de datos las rutas se guardan en formato
 * "./assets/img/..." (relativo a la raíz del proyecto). Como este panel
 * vive en panel/, para previsualizar hay que anteponer un "." extra
 * ("." + "./assets/..." = "../assets/..."), pero el valor que se guarda
 * en el campo de texto (y que se envía al servidor) es siempre la ruta
 * original, sin tocar.
 */

const DEFAULT_PREVIEW = '../assets/img/perfil/apple-icon-120x120.png';

function rutaPreview(imagen) {
  return imagen ? ('.' + imagen) : DEFAULT_PREVIEW;
}

function abrirModalCromo(cromo) {
  const titulo = document.getElementById('modalCromoTitulo');
  const submitBtn = document.getElementById('f_submit');

  if (cromo) {
    titulo.textContent = 'Editar cromo';
    submitBtn.textContent = 'Guardar cambios';
    document.getElementById('f_id_cromo').value = cromo.id_cromo || '';
    document.getElementById('f_nombre').value = cromo.nombre || '';
    document.getElementById('f_imagen_archivo').value = '';
    document.getElementById('f_imagen').value = cromo.imagen || '';
    document.getElementById('f_preview').src = rutaPreview(cromo.imagen);
    document.getElementById('f_id_equipo').value = cromo.id_equipo || '';
    document.getElementById('f_id_expansion').value = cromo.id_expansion || '';
    document.getElementById('f_posicion').value = cromo.posicion || '';
    document.getElementById('f_id_rareza').value = cromo.id_rareza || '';
    document.getElementById('f_id_afinidad').value = cromo.id_afinidad || '';
    document.getElementById('f_descripcion').value = cromo.descripcion || '';
  } else {
    titulo.textContent = 'Nuevo cromo';
    submitBtn.textContent = 'Crear cromo';
    document.getElementById('formCromo').reset();
    document.getElementById('f_id_cromo').value = '';
    document.getElementById('f_preview').src = DEFAULT_PREVIEW;
  }

  SRF.abrirModal('modalCromo');
}

function cerrarModalCromo() {
  SRF.cerrarModal('modalCromo');
}

// Actualiza la miniatura al escribir/pegar una ruta de imagen
document.getElementById('f_imagen')?.addEventListener('input', (e) => {
  document.getElementById('f_preview').src = rutaPreview(e.target.value);
});

// Elegir un archivo previsualiza ESE archivo (aún sin subir) y manda por
// delante a la ruta escrita a mano, que es lo que hace también el servidor.
document.getElementById('f_imagen_archivo')?.addEventListener('change', (e) => {
  const archivo = e.target.files && e.target.files[0];
  if (!archivo) return;
  document.getElementById('f_preview').src = URL.createObjectURL(archivo);
});

function pedirBorrado(nombre, id) {
  SRF.confirmar('¿Seguro que quieres eliminar "' + nombre + '"? Se quitará de la colección y de los mazos de CUALQUIERA que la tenga, y de su rastro en duelos ya jugados. No se puede deshacer.', function () {
    window.location.href = 'cromos.php?eliminar=' + encodeURIComponent(id) + '&csrf=' + encodeURIComponent(SRF.csrfToken());
  });
}
