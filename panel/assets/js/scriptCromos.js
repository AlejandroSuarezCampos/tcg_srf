/**
 * Lógica de panel/cromos.php: abrir/cerrar el modal de creación/edición,
 * rellenarlo con los datos del cromo al pulsar "Editar", y confirmar el
 * borrado antes de navegar a la eliminación real (cromos.php?eliminar=ID).
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
  const modal = document.getElementById('modalCromo');
  const titulo = document.getElementById('modalCromoTitulo');
  const submitBtn = document.getElementById('f_submit');

  if (cromo) {
    titulo.textContent = 'Editar cromo';
    submitBtn.textContent = 'Guardar cambios';
    document.getElementById('f_id_cromo').value = cromo.id_cromo || '';
    document.getElementById('f_nombre').value = cromo.nombre || '';
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

  modal.classList.add('open');
}

function cerrarModalCromo() {
  document.getElementById('modalCromo').classList.remove('open');
}

// Actualiza la miniatura al escribir/pegar una ruta de imagen
document.getElementById('f_imagen')?.addEventListener('input', (e) => {
  document.getElementById('f_preview').src = rutaPreview(e.target.value);
});

// Cierra el modal al hacer clic fuera del cuadro
document.getElementById('modalCromo')?.addEventListener('click', (e) => {
  if (e.target.id === 'modalCromo') cerrarModalCromo();
});

function confirmarBorrado(nombre, id) {
  if (confirm(`¿Seguro que quieres eliminar "${nombre}"? Esta acción no se puede deshacer.`)) {
    window.location.href = 'cromos.php?eliminar=' + encodeURIComponent(id);
  }
}