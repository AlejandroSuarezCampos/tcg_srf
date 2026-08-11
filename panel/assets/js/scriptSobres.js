/**
 * Lógica de panel/sobres.php: rellenar el modal de creación/edición con los
 * datos del sobre al pulsar "Editar", y pedir confirmación antes de navegar
 * al borrado real (sobres.php?eliminar=ID).
 *
 * El modal en sí lo lleva SRF.abrirModal()/cerrarModal() de assets/js/ui.js,
 * igual que en el resto del sitio.
 *
 * Misma convención de imágenes que en cromos: en la BD se guardan como
 * "./assets/img/..." (relativas a la raíz del proyecto), así que para
 * previsualizar dentro de panel/ hay que anteponer un "." extra.
 */

const DEFAULT_PREVIEW_SOBRE = '../assets/img/perfil/apple-icon-120x120.png';

function rutaPreviewSobre(imagen) {
  return imagen ? ('.' + imagen) : DEFAULT_PREVIEW_SOBRE;
}

function abrirModalSobre(sobre) {
  const titulo = document.getElementById('modalSobreTitulo');
  const submitBtn = document.getElementById('fs_submit');
  const form = document.getElementById('formSobre');
  const preview = document.getElementById('fs_preview');

  if (sobre) {
    titulo.textContent = 'Editar sobre';
    submitBtn.textContent = 'Guardar cambios';
    const setVal = (id, val) => { document.getElementById(id).value = val ?? ''; };
    setVal('fs_id_sobre', sobre.id_sobre);
    setVal('fs_nombre', sobre.nombre);
    setVal('fs_imagen', sobre.imagen);
    preview.src = rutaPreviewSobre(sobre.imagen);
    setVal('fs_id_expansion', sobre.id_expansion);
    setVal('fs_cantidad', sobre.cantidad);
    setVal('fs_precio', sobre.precio);
    document.getElementById('fs_activo').checked = parseInt(sobre.activo, 10) === 1;
  } else {
    titulo.textContent = 'Nuevo sobre';
    submitBtn.textContent = 'Crear sobre';
    form.reset();
    document.getElementById('fs_id_sobre').value = '';
    preview.src = DEFAULT_PREVIEW_SOBRE;
    document.getElementById('fs_activo').checked = true;
  }

  SRF.abrirModal('modalSobre');
}

function cerrarModalSobre() {
  SRF.cerrarModal('modalSobre');
}

document.getElementById('fs_imagen')?.addEventListener('input', (e) => {
  document.getElementById('fs_preview').src = rutaPreviewSobre(e.target.value);
});

function pedirBorrado(nombre, id) {
  SRF.confirmar('¿Seguro que quieres eliminar el sobre "' + nombre + '"? Esta acción no se puede deshacer.', function () {
    window.location.href = 'sobres.php?eliminar=' + encodeURIComponent(id);
  });
}
