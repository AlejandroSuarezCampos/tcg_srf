/**
 * Lógica de panel/equipos.php: el modal de alta y edición, y la confirmación
 * de borrado. El modal en sí lo lleva SRF.abrirModal()/cerrarModal().
 */

function abrirModalEquipo(equipo) {
  var titulo = document.getElementById('modalEquipoTitulo');
  var enviar = document.getElementById('fe_submit');
  var vista  = document.getElementById('fe_escudo_vista');

  document.getElementById('formEquipo').reset();
  document.getElementById('fe_escudo_archivo').value = '';

  if (equipo) {
    titulo.textContent = 'Editar equipo';
    enviar.textContent = 'Guardar cambios';
    document.getElementById('fe_id_equipo').value = equipo.id_equipo || '';
    document.getElementById('fe_nombre').value = equipo.nombre || '';
    document.getElementById('fe_descripcion').value = equipo.descripcion || '';
    document.getElementById('fe_escudo').value = equipo.escudo || '';
    pintarEscudoEquipo(equipo.escudo);
  } else {
    titulo.textContent = 'Nuevo equipo';
    enviar.textContent = 'Crear equipo';
    document.getElementById('fe_id_equipo').value = '';
    pintarEscudoEquipo('');
  }

  SRF.abrirModal('modalEquipo');
}

/* La ruta guardada es relativa a la raíz del sitio ("./assets/…"), y desde
   panel/ ese "./" apunta a panel/assets. Mismo ajuste que hacen las
   miniaturas de cromos y el escudo de rival del editor de cadenas. */
function pintarEscudoEquipo(ruta) {
  var vista = document.getElementById('fe_escudo_vista');
  if (!vista) return;
  if (ruta) {
    var src = ruta.charAt(0) === '.' ? '.' + ruta : ruta;
    vista.innerHTML = '';
    var img = document.createElement('img');
    img.src = src; img.alt = '';
    vista.appendChild(img);
  } else {
    vista.innerHTML = '<i class="ph ph-shield" aria-hidden="true"></i>';
  }
}

function cerrarModalEquipo() {
  SRF.cerrarModal('modalEquipo');
}

function pedirBorradoEquipo(nombre, id) {
  SRF.confirmar('¿Eliminar el equipo "' + nombre + '"?', function () {
    window.location.href = 'equipos.php?eliminar=' + encodeURIComponent(id)
      + '&csrf=' + encodeURIComponent(SRF.csrfToken());
  });
}
