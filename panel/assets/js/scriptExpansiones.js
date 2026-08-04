/**
 * Lógica de panel/expansiones.php: abrir/cerrar el modal de creación/edición
 * y confirmar el borrado antes de navegar a la eliminación real
 * (expansiones.php?eliminar=ID).
 */

function abrirModalExpansion(expansion) {
  const modal = document.getElementById('modalExpansion');
  const titulo = document.getElementById('modalExpansionTitulo');
  const submitBtn = document.getElementById('fe_submit');

  if (expansion) {
    titulo.textContent = 'Editar expansión';
    submitBtn.textContent = 'Guardar cambios';
    document.getElementById('fe_id_expansion').value = expansion.id_expansion || '';
    document.getElementById('fe_nombre').value = expansion.nombre || '';
    // fecha_salida viene como "YYYY-MM-DD HH:MM:SS"; el <input type="date"> solo quiere "YYYY-MM-DD"
    document.getElementById('fe_fecha_salida').value = (expansion.fecha_salida || '').substring(0, 10);
    document.getElementById('fe_activo').checked = parseInt(expansion.activo) === 1;
  } else {
    titulo.textContent = 'Nueva expansión';
    submitBtn.textContent = 'Crear expansión';
    document.getElementById('formExpansion').reset();
    document.getElementById('fe_id_expansion').value = '';
    document.getElementById('fe_activo').checked = true;
  }

  modal.classList.add('open');
}

function cerrarModalExpansion() {
  document.getElementById('modalExpansion').classList.remove('open');
}

document.getElementById('modalExpansion')?.addEventListener('click', (e) => {
  if (e.target.id === 'modalExpansion') cerrarModalExpansion();
});

function confirmarBorrado(nombre, id) {
  if (confirm(`¿Seguro que quieres eliminar "${nombre}"? Se perderá también la relación con sus cromos.`)) {
    window.location.href = 'expansiones.php?eliminar=' + encodeURIComponent(id);
  }
}