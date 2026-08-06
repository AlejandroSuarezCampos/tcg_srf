/**
 * Lógica de panel/usuarios.php: abrir/cerrar el modal de creación/edición,
 * el modal separado de "restablecer contraseña", y confirmar el borrado
 * antes de navegar a la eliminación real (usuarios.php?eliminar=ID).
 */

let usuarioActualId = null;

function abrirModalUsuario(usuario) {
  const modal = document.getElementById('modalUsuario');
  if (!modal) { console.error('No se encontró #modalUsuario en la página'); return; }

  const titulo = document.getElementById('modalUsuarioTitulo');
  const submitBtn = document.getElementById('fu_submit');
  const passwordWrap = document.getElementById('fu_password_wrap');
  const passwordInput = document.getElementById('fu_password');
  const resetFooter = document.getElementById('fu_reset_footer');
  const form = document.getElementById('formUsuario');

  if (usuario) {
    usuarioActualId = usuario.id_usuario;
    if (titulo) titulo.textContent = 'Editar usuario';
    if (submitBtn) submitBtn.textContent = 'Guardar cambios';
    const idInput = document.getElementById('fu_id_usuario');
    if (idInput) idInput.value = usuario.id_usuario || '';
    const nombreInput = document.getElementById('fu_nombre');
    if (nombreInput) nombreInput.value = usuario.nombre || '';
    const monedasInput = document.getElementById('fu_monedas');
    if (monedasInput) monedasInput.value = usuario.monedas ?? 0;
    const dictadorInput = document.getElementById('fu_dictador');
    if (dictadorInput) dictadorInput.checked = !!parseInt(usuario.dictador);
    if (passwordWrap) passwordWrap.style.display = 'none';
    if (passwordInput) passwordInput.removeAttribute('required');
    if (resetFooter) resetFooter.style.display = 'flex';
  } else {
    usuarioActualId = null;
    if (titulo) titulo.textContent = 'Nuevo usuario';
    if (submitBtn) submitBtn.textContent = 'Crear usuario';
    if (form) form.reset();
    const idInput = document.getElementById('fu_id_usuario');
    if (idInput) idInput.value = '';
    if (passwordWrap) passwordWrap.style.display = '';
    if (passwordInput) passwordInput.setAttribute('required', 'required');
    if (resetFooter) resetFooter.style.display = 'none';
  }

  modal.classList.add('open');
}

function cerrarModalUsuario() {
  document.getElementById('modalUsuario')?.classList.remove('open');
}

document.getElementById('modalUsuario')?.addEventListener('click', (e) => {
  if (e.target.id === 'modalUsuario') cerrarModalUsuario();
});

function abrirResetPassword() {
  const idInput = document.getElementById('rp_id_usuario');
  const passInput = document.getElementById('rp_password');
  if (idInput) idInput.value = usuarioActualId || '';
  if (passInput) passInput.value = '';
  document.getElementById('modalResetPassword')?.classList.add('open');
}

function cerrarResetPassword() {
  document.getElementById('modalResetPassword')?.classList.remove('open');
}

document.getElementById('modalResetPassword')?.addEventListener('click', (e) => {
  if (e.target.id === 'modalResetPassword') cerrarResetPassword();
});

function confirmarBorrado(nombre, id) {
  if (confirm(`¿Seguro que quieres eliminar al usuario "${nombre}"? Perderá su colección y su acceso.`)) {
    window.location.href = 'usuarios.php?eliminar=' + encodeURIComponent(id);
  }
}