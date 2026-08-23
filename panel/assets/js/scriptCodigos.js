/**
 * Lógica de panel/codigos.php: modal de creación/edición y confirmación de
 * borrado. El modal de canjes (?canjes=ID) es de solo lectura, sin JS propio
 * salvo registrarlo en SRF.abrirModal() cuando llega ya abierto por la URL.
 */

function abrirModalCodigo(codigo) {
  const titulo = document.getElementById('modalCodigoTitulo');
  const submitBtn = document.getElementById('fk_submit');

  if (codigo) {
    titulo.textContent = 'Editar código';
    submitBtn.textContent = 'Guardar cambios';
    document.getElementById('fk_id_codigo').value = codigo.id_codigo || '';
    document.getElementById('fk_codigo').value = codigo.codigo || '';
    document.getElementById('fk_tipo').value = codigo.tipo || 'global';
    document.getElementById('fk_monedas').value = codigo.monedas || 0;
    document.getElementById('fk_activo').checked = parseInt(codigo.activo, 10) === 1;
  } else {
    titulo.textContent = 'Nuevo código';
    submitBtn.textContent = 'Crear código';
    document.getElementById('formCodigo').reset();
    document.getElementById('fk_id_codigo').value = '';
    document.getElementById('fk_monedas').value = 100;
    document.getElementById('fk_activo').checked = true;
  }

  SRF.abrirModal('modalCodigo');
}

function cerrarModalCodigo() {
  SRF.cerrarModal('modalCodigo');
}

function pedirBorradoCodigo(codigo, id) {
  SRF.confirmar('¿Seguro que quieres eliminar el código "' + codigo + '"? Su historial de canjes se queda, pero nadie podrá volver a usarlo.', function () {
    window.location.href = 'codigos.php?eliminar=' + encodeURIComponent(id) + '&csrf=' + encodeURIComponent(SRF.csrfToken());
  });
}

document.addEventListener('DOMContentLoaded', function () {
  var modalCanjes = document.getElementById('modalCanjes');
  if (modalCanjes && modalCanjes.classList.contains('is-abierto')) {
    SRF.abrirModal('modalCanjes');
  }
});
