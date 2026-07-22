/**
 * Lógica de panel/sobres.php: abrir/cerrar el modal de creación/edición,
 * rellenarlo con los datos del sobre al pulsar "Editar", y confirmar el
 * borrado antes de navegar a la eliminación real (sobres.php?eliminar=ID).
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
  const modal = document.getElementById('modalSobre');
  if (!modal) { console.error('No se encontró #modalSobre en la página'); return; }

  const titulo = document.getElementById('modalSobreTitulo');
  const submitBtn = document.getElementById('fs_submit');
  const form = document.getElementById('formSobre');
  const preview = document.getElementById('fs_preview');

  if (sobre) {
    if (titulo) titulo.textContent = 'Editar sobre';
    if (submitBtn) submitBtn.textContent = 'Guardar cambios';
    const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };
    setVal('fs_id_sobre', sobre.id_sobre);
    setVal('fs_nombre', sobre.nombre);
    setVal('fs_imagen', sobre.imagen);
    if (preview) preview.src = rutaPreviewSobre(sobre.imagen);
    setVal('fs_id_expansion', sobre.id_expansion);
    setVal('fs_cantidad', sobre.cantidad);
    setVal('fs_precio', sobre.precio);
    const activoInput = document.getElementById('fs_activo');
    if (activoInput) activoInput.checked = parseInt(sobre.activo) === 1;
  } else {
    if (titulo) titulo.textContent = 'Nuevo sobre';
    if (submitBtn) submitBtn.textContent = 'Crear sobre';
    if (form) form.reset();
    const idInput = document.getElementById('fs_id_sobre');
    if (idInput) idInput.value = '';
    if (preview) preview.src = DEFAULT_PREVIEW_SOBRE;
    const activoInput = document.getElementById('fs_activo');
    if (activoInput) activoInput.checked = true;
  }

  modal.classList.add('open');
}

function cerrarModalSobre() {
  document.getElementById('modalSobre')?.classList.remove('open');
}

document.getElementById('fs_imagen')?.addEventListener('input', (e) => {
  const preview = document.getElementById('fs_preview');
  if (preview) preview.src = rutaPreviewSobre(e.target.value);
});

document.getElementById('modalSobre')?.addEventListener('click', (e) => {
  if (e.target.id === 'modalSobre') cerrarModalSobre();
});

function confirmarBorrado(nombre, id) {
  if (confirm(`¿Seguro que quieres eliminar el sobre "${nombre}"? Esta acción no se puede deshacer.`)) {
    window.location.href = 'sobres.php?eliminar=' + encodeURIComponent(id);
  }
}