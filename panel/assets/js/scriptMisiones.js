/**
 * Lógica de panel/misiones.php: modal de creación/edición y confirmación de
 * borrado. El modal en sí lo lleva SRF.abrirModal()/cerrarModal() de
 * assets/js/ui.js.
 */

function abrirModalMision(mision) {
  const titulo = document.getElementById('modalMisionTitulo');
  const submitBtn = document.getElementById('fm_submit');

  if (mision) {
    titulo.textContent = 'Editar misión';
    submitBtn.textContent = 'Guardar cambios';
    document.getElementById('fm_id_mision').value = mision.id_mision || '';
    document.getElementById('fm_nombre').value = mision.nombre || '';
    document.getElementById('fm_descripcion').value = mision.descripcion || '';
    document.getElementById('fm_tipo').value = mision.tipo || 'cartas_distintas';
    document.getElementById('fm_ciclo').value = mision.ciclo || 'unica';
    document.getElementById('fm_objetivo').value = mision.objetivo || 1;
    document.getElementById('fm_recompensa_monedas').value = mision.recompensa_monedas || 0;
    document.getElementById('fm_activo').checked = parseInt(mision.activo, 10) === 1;
  } else {
    titulo.textContent = 'Nueva misión';
    submitBtn.textContent = 'Crear misión';
    document.getElementById('formMision').reset();
    document.getElementById('fm_id_mision').value = '';
    document.getElementById('fm_objetivo').value = 1;
    document.getElementById('fm_recompensa_monedas').value = 100;
    document.getElementById('fm_activo').checked = true;
  }

  SRF.misiones.alCambiarCiclo();
  SRF.abrirModal('modalMision');
}

function cerrarModalMision() {
  SRF.cerrarModal('modalMision');
}

function pedirBorradoMision(nombre, id) {
  SRF.confirmar('¿Seguro que quieres eliminar la misión "' + nombre + '"? Se borrará también el historial de quién la reclamó. No se puede deshacer.', function () {
    window.location.href = 'misiones.php?eliminar=' + encodeURIComponent(id);
  });
}

window.SRF = window.SRF || {};
SRF.misiones = {
  // "Expansiones completadas" es un hito de estado sin fecha propia: el
  // servidor lo rechaza si el ciclo no es "unica" (combinacionMisionValida()
  // en consultas.php). Aquí solo se evita que el admin lo intente y se
  // encuentre el error después de rellenar todo el formulario.
  alCambiarCiclo: function () {
    var esUnica = document.getElementById('fm_ciclo').value === 'unica';
    var opcion = document.getElementById('fm_opcion_expansiones');
    opcion.disabled = !esUnica;
    if (!esUnica && document.getElementById('fm_tipo').value === 'expansiones_completas') {
      document.getElementById('fm_tipo').value = 'cartas_distintas';
    }
  },
};
