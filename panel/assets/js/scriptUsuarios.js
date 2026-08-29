/**
 * Lógica de panel/usuarios.php: rellenar el modal de creación/edición, el
 * modal separado de "restablecer contraseña", y pedir confirmación antes de
 * navegar al borrado real (usuarios.php?eliminar=ID).
 *
 * Los dos modales los lleva SRF.abrirModal()/cerrarModal() de assets/js/ui.js.
 */

let usuarioActualId = null;

function abrirModalUsuario(usuario) {
  const titulo = document.getElementById('modalUsuarioTitulo');
  const submitBtn = document.getElementById('fu_submit');
  const passwordWrap = document.getElementById('fu_password_wrap');
  const passwordInput = document.getElementById('fu_password');
  const resetFooter = document.getElementById('fu_reset_footer');
  const form = document.getElementById('formUsuario');

  if (usuario) {
    usuarioActualId = usuario.id_usuario;
    titulo.textContent = 'Editar usuario';
    submitBtn.textContent = 'Guardar cambios';
    document.getElementById('fu_id_usuario').value = usuario.id_usuario || '';
    document.getElementById('fu_nombre').value = usuario.nombre || '';
    document.getElementById('fu_monedas').value = usuario.monedas ?? 0;
    document.getElementById('fu_dictador').checked = parseInt(usuario.dictador, 10) === 1;
    passwordWrap.style.display = 'none';
    passwordInput.removeAttribute('required');
    resetFooter.style.display = 'flex';
  } else {
    usuarioActualId = null;
    titulo.textContent = 'Nuevo usuario';
    submitBtn.textContent = 'Crear usuario';
    form.reset();
    document.getElementById('fu_id_usuario').value = '';
    passwordWrap.style.display = '';
    passwordInput.setAttribute('required', 'required');
    resetFooter.style.display = 'none';
  }

  SRF.abrirModal('modalUsuario');
}

function cerrarModalUsuario() {
  SRF.cerrarModal('modalUsuario');
}

function abrirResetPassword() {
  document.getElementById('rp_id_usuario').value = usuarioActualId || '';
  document.getElementById('rp_password').value = '';
  SRF.abrirModal('modalResetPassword');
}

function cerrarResetPassword() {
  SRF.cerrarModal('modalResetPassword');
}

/* Auditoría de seguridad: el borrado viajaba por GET (`?eliminar=ID&csrf=...`).
   El token se validaba bien, pero un secreto de sesión no debería acabar en
   la barra de direcciones, el historial o el Referer de un recurso externo —
   y cualquier precarga del navegador podía dispararlo sin que nadie hiciera
   clic. Ahora es un formulario POST real, montado con el DOM (nunca con
   innerHTML) para que el nombre del usuario no tenga ni ocasión de acabar
   interpretado como HTML. */
function pedirBorrado(nombre, id) {
  SRF.confirmar('¿Seguro que quieres eliminar al usuario "' + nombre + '"? Perderá su colección y su acceso.', function () {
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = 'usuarios.php';

    var campos = { accion: 'eliminar', id_usuario: id, csrf: SRF.csrfToken() };
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
