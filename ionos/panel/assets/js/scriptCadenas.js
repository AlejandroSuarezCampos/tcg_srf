/**
 * Lógica de panel/cadenas.php: modal de creación/edición de la cadena,
 * confirmación de borrado, y el pequeño formulario de requisitos de entrada.
 *
 * El modal en sí lo lleva SRF.abrirModal()/cerrarModal() de assets/js/ui.js.
 */

function abrirModalCadena(cadena) {
  const titulo = document.getElementById('modalCadenaTitulo');
  const submitBtn = document.getElementById('fc_submit');

  if (cadena) {
    titulo.textContent = 'Editar cadena';
    submitBtn.textContent = 'Guardar cambios';
    document.getElementById('fc_id_cadena').value = cadena.id_cadena || '';
    document.getElementById('fc_nombre').value = cadena.nombre || '';
    document.getElementById('fc_descripcion').value = cadena.descripcion || '';
    document.getElementById('fc_anfitrion').value = cadena.anfitrion || '';
    document.getElementById('fc_orden').value = cadena.orden || 0;
    document.getElementById('fc_formacion_recompensa').value = cadena.formacion_recompensa || '';
    document.getElementById('fc_fecha_fin').value = (cadena.fecha_fin || '').substring(0, 10);
    document.getElementById('fc_activa').checked = parseInt(cadena.activa, 10) === 1;
  } else {
    titulo.textContent = 'Nueva cadena';
    submitBtn.textContent = 'Crear cadena';
    document.getElementById('formCadena').reset();
    document.getElementById('fc_id_cadena').value = '';
    document.getElementById('fc_activa').checked = true;
  }

  SRF.abrirModal('modalCadena');
}

function cerrarModalCadena() {
  SRF.cerrarModal('modalCadena');
}

function pedirBorradoCadena(nombre, id) {
  SRF.confirmar('¿Seguro que quieres eliminar la cadena "' + nombre + '"? Se perderá también su mapa, sus requisitos, su botín, y el progreso real que tuvieran los jugadores en ella. No se puede deshacer.', function () {
    window.location.href = 'cadenas.php?eliminar=' + encodeURIComponent(id) + '&csrf=' + encodeURIComponent(SRF.csrfToken());
  });
}

window.SRF = window.SRF || {};
SRF.cadenasAlternarTipoRequisito = function (tipo) {
  document.getElementById('fr_grupo_cadena').hidden = tipo !== 'cadena';
  document.getElementById('fr_grupo_cromo').hidden = tipo !== 'cromo';
};

document.addEventListener('DOMContentLoaded', function () {
  // El modal de requisitos puede llegar ya abierto por la URL (?requisitos=ID);
  // registrarlo en SRF.abrirModal() es lo que activa el atrapado de foco y Esc.
  var modalReq = document.getElementById('modalRequisitos');
  if (modalReq && modalReq.classList.contains('is-abierto')) {
    SRF.abrirModal('modalRequisitos');
  }
});
