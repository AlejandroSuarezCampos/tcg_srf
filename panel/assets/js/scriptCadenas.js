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

    var restringida = cadena.visibilidad === 'elegidos';
    document.getElementById('fc_vis_elegidos').checked = restringida;
    document.getElementById('fc_vis_todos').checked = !restringida;
    marcarInvitados(cadena.invitados || []);
  } else {
    titulo.textContent = 'Nueva cadena';
    submitBtn.textContent = 'Crear cadena';
    document.getElementById('formCadena').reset();
    document.getElementById('fc_id_cadena').value = '';
    document.getElementById('fc_activa').checked = true;
    document.getElementById('fc_vis_todos').checked = true;
    marcarInvitados([]);
  }

  document.getElementById('fc_buscar_invitado').value = '';
  filtrarInvitados('');
  alternarInvitados();
  SRF.abrirModal('modalCadena');
}

/* --------------------------------------------------------------------------
   INVITADOS DE UNA CADENA RESTRINGIDA

   La lista es de casillas y no un <select multiple> a propósito: un select
   múltiple exige Ctrl+clic para marcar varias —y un clic normal borra toda la
   selección anterior sin avisar—, que es justo la trampa que hace perder
   invitados al editar.
   -------------------------------------------------------------------------- */

function casillasInvitado() {
  return Array.prototype.slice.call(
    document.querySelectorAll('#fc_invitados input[type="checkbox"]'));
}

function marcarInvitados(ids) {
  var elegidos = (ids || []).map(Number);
  casillasInvitado().forEach(function (c) {
    c.checked = elegidos.indexOf(Number(c.value)) !== -1;
  });
  contarInvitados();
}

function contarInvitados() {
  var n = casillasInvitado().filter(function (c) { return c.checked; }).length;
  var resumen = document.getElementById('fc_invitados_resumen');
  if (!resumen) return;
  /* Cero invitados en una cadena restringida significa que no la ve nadie. Es
     un estado válido —y a veces el que se quiere— pero nunca debe pasar
     desapercibido. */
  resumen.textContent = n === 0
    ? 'Nadie elegido: con esto la cadena no la verá ningún jugador.'
    : n + (n === 1 ? ' persona podrá verla.' : ' personas podrán verla.');
  resumen.classList.toggle('es-aviso', n === 0);
}

/* Filtrar solo esconde: una persona ya marcada sigue marcada aunque el
   buscador la oculte, y su casilla sigue viajando en el envío. Desmarcar al
   filtrar sería perder invitados por escribir. */
function filtrarInvitados(texto) {
  var t = (texto || '').trim().toLowerCase();
  Array.prototype.forEach.call(document.querySelectorAll('#fc_invitados li'), function (li) {
    li.hidden = t !== '' && li.dataset.nombre.indexOf(t) === -1;
  });
}

function alternarInvitados() {
  var restringida = document.getElementById('fc_vis_elegidos').checked;
  document.getElementById('fc_grupo_invitados').hidden = !restringida;
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
/* Un grupo de campos por tipo de requisito: se enseña el del elegido y se
   esconden los demás. Se listan todos aquí para que añadir un tipo sea añadir
   una línea y no ir buscando `hidden` sueltos por el archivo. */
var GRUPOS_REQUISITO = {
  cadena:      'fr_grupo_cadena',
  cromo:       'fr_grupo_cromo',
  nivel_album: 'fr_grupo_album',
  monedas:     'fr_grupo_monedas',
  duelos:      'fr_grupo_duelos',
  rareza:      'fr_grupo_rareza',
};

SRF.cadenasAlternarTipoRequisito = function (tipo) {
  Object.keys(GRUPOS_REQUISITO).forEach(function (k) {
    var el = document.getElementById(GRUPOS_REQUISITO[k]);
    if (el) { el.hidden = k !== tipo; }
  });
  if (tipo === 'cromo') { SRF.cadenasFiltrarCromos(); }
};

/**
 * Filtra el catálogo de cartas del requisito por texto, rareza, posición y
 * equipo.
 *
 * Se hace en el navegador y no en el servidor a propósito: las cartas ya están
 * todas en el desplegable, así que ir al servidor a cada tecla sería pedir por
 * red algo que está en la memoria. Con 469 cartas el bucle es instantáneo.
 *
 * Las opciones se OCULTAN, no se borran: si se quitaran del DOM habría que
 * reconstruirlas al limpiar el filtro, y con ellas se iría la selección.
 */
SRF.cadenasFiltrarCromos = function () {
  var lista = document.getElementById('fr_valor_cromo');
  if (!lista) { return; }

  var texto    = (document.getElementById('fr_buscar_cromo').value || '').trim().toLowerCase();
  var rareza   = document.getElementById('fr_f_rareza').value;
  var posicion = document.getElementById('fr_f_posicion').value;
  var equipo   = document.getElementById('fr_f_equipo').value;

  var visibles = 0, primera = null;
  Array.prototype.forEach.call(lista.options, function (op) {
    var pasa =
      (!texto    || (op.dataset.busca || '').indexOf(texto) !== -1) &&
      (!rareza   || op.dataset.rareza === rareza) &&
      (!posicion || op.dataset.posicion === posicion) &&
      (!equipo   || op.dataset.equipo === equipo);

    op.hidden = !pasa;
    if (pasa) { visibles++; if (!primera) { primera = op; } }
  });

  /* SIEMPRE tiene que haber una carta elegida y VISIBLE.
     Dos motivos, y los dos han pasado midiendo:
       · un <select size="8"> arranca SIN nada seleccionado, al revés que un
         desplegable, así que el formulario se enviaba con la carta vacía y el
         requisito no se creaba sin decir por qué;
       · y si lo que estaba elegido deja de pasar el filtro, enviarlo guardaría
         una carta que la persona ya no está viendo. */
  var elegida = lista.selectedOptions[0];
  if ((!elegida || elegida.hidden) && primera) { primera.selected = true; }

  var conteo = document.getElementById('fr_conteo_cromos');
  if (conteo) {
    conteo.textContent = visibles === 0
      ? 'Ninguna carta con esos filtros.'
      : visibles + (visibles === 1 ? ' carta' : ' cartas') + ' — elige una de la lista.';
  }
};

document.addEventListener('DOMContentLoaded', function () {
  // El modal de requisitos puede llegar ya abierto por la URL (?requisitos=ID);
  // registrarlo en SRF.abrirModal() es lo que activa el atrapado de foco y Esc.
  var modalReq = document.getElementById('modalRequisitos');
  if (modalReq && modalReq.classList.contains('is-abierto')) {
    SRF.abrirModal('modalRequisitos');
    // Deja a la vista solo los campos del tipo que viene seleccionado.
    SRF.cadenasAlternarTipoRequisito(document.getElementById('fr_tipo').value);
  }
});


/* --------------------------------------------------------------------------
   CALIBRACIÓN DE DIFICULTAD PvE (migración `033`)

   El botón manda un preset y el servidor devuelve, por dificultad, el
   multiplicador que ha encontrado y el porcentaje de victorias que ha MEDIDO
   con él. Lo segundo es lo importante: sin enseñarlo, aplicar un preset sigue
   siendo un acto de fe.

   Tarda segundos, no milisegundos: se simulan miles de partidos con el motor
   real. Por eso el botón se bloquea y avisa, en vez de dejar que parezca que
   no ha pasado nada y que alguien lo pulse cinco veces.
   -------------------------------------------------------------------------- */
(function () {
  'use strict';

  var boton = document.getElementById('btnCalibrarGlobal');
  if (!boton) return;

  var estado = document.getElementById('calibracionEstado');
  var cuerpo = document.getElementById('calibracionCuerpo');

  function presetElegido() {
    var r = document.querySelector('input[name="pve_preset"]:checked');
    return r ? r.value : 'normal';
  }

  function pct(v) {
    return v === null || v === undefined ? '—' : (v * 100).toFixed(1).replace('.', ',') + ' %';
  }

  function pintar(dificultades) {
    Object.keys(dificultades).forEach(function (dif) {
      var fila = cuerpo.querySelector('[data-dificultad="' + dif + '"]');
      if (!fila) return;
      var d = dificultades[dif];
      fila.querySelector('[data-celda="mult"]').textContent = '×' + d.mult;
      fila.querySelector('[data-celda="objetivo"]').textContent = pct(d.objetivo);
      fila.querySelector('[data-celda="medido"]').textContent = pct(d.medido);
    });
  }

  /* El resaltado del preset elegido. `:has()` no vale aquí porque el resto del
     panel usa clases para esto y mezclar los dos criterios en la misma página
     acaba en dos verdades distintas sobre qué está elegido. */
  function marcarPreset() {
    Array.prototype.forEach.call(document.querySelectorAll('.calibracion-preset'), function (l) {
      var r = l.querySelector('input');
      l.classList.toggle('esta-elegido', !!r && r.checked);
    });
  }
  Array.prototype.forEach.call(document.querySelectorAll('input[name="pve_preset"]'), function (r) {
    r.addEventListener('change', marcarPreset);
  });

  boton.addEventListener('click', function () {
    var preset = presetElegido();
    boton.disabled = true;
    estado.textContent = 'Simulando partidos para medir cada dificultad… esto tarda unos segundos.';

    var body = new URLSearchParams({
      accion: 'calibrar_pve_global', csrf: SRF.csrfToken(), preset: preset,
    });

    fetch('../assets/ajax/cadena_admin.php', {
      method: 'POST', body: body,
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        boton.disabled = false;
        if (!r.ok) { estado.textContent = r.error || 'No se pudo calibrar.'; return; }
        pintar(r.dificultades);
        estado.innerHTML = 'Aplicado: <b>' + preset + '</b>';
        SRF.toast('Dificultad recalibrada en todo el juego.', 'success');
      })
      .catch(function () {
        boton.disabled = false;
        estado.textContent = 'No se pudo calibrar: la petición falló.';
      });
  });
})();
