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

/* Auditoría de seguridad: mismo motivo que pedirBorrado() en scriptUsuarios.js
   — el borrado viajaba por GET con el token de sesión en la URL. Ahora es un
   formulario POST montado con el DOM, nunca con innerHTML. */
function pedirBorradoCodigo(codigo, id) {
  SRF.confirmar('¿Seguro que quieres eliminar el código "' + codigo + '"? Su historial de canjes se queda, pero nadie podrá volver a usarlo.', function () {
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = 'codigos.php';

    var campos = { accion: 'eliminar', id_codigo: id, csrf: SRF.csrfToken() };
    Object.keys(campos).forEach(function (nombreCampo) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = nombreCampo;
      input.value = campos[nombreCampo];
      f.appendChild(input);
    });

    document.body.appendChild(f);
    f.submit();
  });
}

document.addEventListener('DOMContentLoaded', function () {
  var modalCanjes = document.getElementById('modalCanjes');
  if (modalCanjes && modalCanjes.classList.contains('is-abierto')) {
    SRF.abrirModal('modalCanjes');
  }
});
