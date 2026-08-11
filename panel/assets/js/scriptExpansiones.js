/**
 * Lógica de panel/expansiones.php: rellenar el modal de creación/edición y
 * pedir confirmación antes de navegar al borrado real
 * (expansiones.php?eliminar=ID).
 *
 * El modal en sí lo lleva SRF.abrirModal()/cerrarModal() de assets/js/ui.js.
 */

function abrirModalExpansion(expansion) {
  const titulo = document.getElementById('modalExpansionTitulo');
  const submitBtn = document.getElementById('fe_submit');

  if (expansion) {
    titulo.textContent = 'Editar expansión';
    submitBtn.textContent = 'Guardar cambios';
    document.getElementById('fe_id_expansion').value = expansion.id_expansion || '';
    document.getElementById('fe_nombre').value = expansion.nombre || '';
    // fecha_salida viene como "YYYY-MM-DD HH:MM:SS"; el <input type="date"> solo quiere "YYYY-MM-DD"
    document.getElementById('fe_fecha_salida').value = (expansion.fecha_salida || '').substring(0, 10);
    document.getElementById('fe_activo').checked = parseInt(expansion.activo, 10) === 1;
  } else {
    titulo.textContent = 'Nueva expansión';
    submitBtn.textContent = 'Crear expansión';
    document.getElementById('formExpansion').reset();
    document.getElementById('fe_id_expansion').value = '';
    document.getElementById('fe_activo').checked = true;
  }

  SRF.abrirModal('modalExpansion');
}

function cerrarModalExpansion() {
  SRF.cerrarModal('modalExpansion');
}

function pedirBorrado(nombre, id) {
  SRF.confirmar('¿Seguro que quieres eliminar "' + nombre + '"? Se perderá también la relación con sus cromos.', function () {
    window.location.href = 'expansiones.php?eliminar=' + encodeURIComponent(id);
  });
}
