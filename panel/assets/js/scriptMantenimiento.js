/**
 * Tareas de mantenimiento del panel (panel/mantenimiento.php).
 *
 * Sin build y sin dependencias, como el resto del panel.
 *
 * La regla de esta pantalla: PRIMERO SE MIRA, DESPUÉS SE HACE. El botón de
 * ejecutar nace deshabilitado y solo se enciende cuando la simulación ha dicho
 * que hay algo que hacer — y encima pide confirmación. Son tareas que borran
 * archivos del servidor y reescriben columnas; no puede ser lo que pasa por
 * pulsar el primer botón que uno ve.
 */
(function () {
  'use strict';

  var URL_AJAX = '../assets/ajax/mantenimiento';

  /** Bytes a algo que se lea. */
  function peso(b) {
    if (b >= 1048576) { return (b / 1048576).toFixed(2) + ' MB'; }
    if (b >= 1024)    { return (b / 1024).toFixed(1) + ' KB'; }
    return b + ' B';
  }

  function esc(t) {
    var d = document.createElement('div');
    d.textContent = t == null ? '' : String(t);
    return d.innerHTML;
  }

  function salidaDe(tarea) {
    return document.querySelector('[data-salida="' + tarea + '"]');
  }

  function botones(tarea) {
    return Array.prototype.slice.call(
      document.querySelectorAll('[data-accion="' + tarea + '"]')
    );
  }

  function ocupado(tarea, si) {
    botones(tarea).forEach(function (b) { b.disabled = si; });
  }

  /* ------------------------------------------------------------------------
     INFORMES
     ------------------------------------------------------------------------ */

  function pintarWebp(r) {
    if (!r.convertidas) {
      return '<p class="mantenimiento-ok"><i class="ph ph-check-circle"></i> '
           + 'No queda ninguna imagen por convertir.</p>';
    }

    var ahorro = r.antes > 0 ? Math.round((1 - r.despues / r.antes) * 100) : 0;
    var filas = r.archivos.slice(0, 40).map(function (a) {
      return '<tr><td>' + esc(a.ruta) + '</td>'
           + '<td class="mono">' + peso(a.antes) + '</td>'
           + '<td class="mono">' + peso(a.despues) + '</td></tr>';
    }).join('');

    return (r.aplicado
        ? '<p class="mantenimiento-ok"><i class="ph ph-check-circle"></i> Hecho.</p>'
        : '<p class="mantenimiento-aviso"><i class="ph ph-eye"></i> Simulación: '
          + 'todavía no se ha tocado nada.</p>')
      + '<ul class="mantenimiento-cifras">'
      + '<li><b>' + r.convertidas + '</b> ' + (r.convertidas === 1 ? 'imagen' : 'imágenes')
      +   (r.aplicado ? ' convertidas' : ' por convertir') + '</li>'
      + '<li><b>' + peso(r.antes) + '</b> → <b>' + peso(r.despues) + '</b> ('
      +   ahorro + ' % menos)</li>'
      + '<li><b>' + r.rutas + '</b> rutas ' + (r.aplicado ? 'actualizadas' : 'por actualizar')
      +   ' en la base</li>'
      + '</ul>'
      + '<div class="tabla-wrap"><table class="tabla"><thead><tr>'
      +   '<th>Archivo</th><th>Antes</th><th>Después</th></tr></thead><tbody>'
      + filas + '</tbody></table></div>'
      + (r.archivos.length > 40
          ? '<p class="t-caption t-dim">…y ' + (r.archivos.length - 40) + ' más.</p>' : '')
      + (r.fallos && r.fallos.length
          ? '<p class="mantenimiento-aviso">No se pudieron convertir (se dejan como están): '
            + esc(r.fallos.join(', ')) + '</p>'
          : '');
  }

  function pintarCompos(r) {
    if (!r.pendientes) {
      return '<p class="mantenimiento-ok"><i class="ph ph-check-circle"></i> '
           + 'Todas las cartas jugables tienen su compo.</p>';
    }

    var filas = (r.muestra || []).map(function (c) {
      return '<tr><td>' + esc(c.nombre) + '</td><td>' + esc(c.posicion) + '</td>'
           + '<td>' + esc(c.afinidad) + '</td>'
           + '<td>' + (Number(c.solo_cadena) ? 'Exclusiva de cadena' : '—') + '</td></tr>';
    }).join('');

    var cabecera = r.aplicado
      ? '<p class="mantenimiento-ok"><i class="ph ph-check-circle"></i> Hecho: '
        + r.tocadas + ' cartas repasadas.</p>'
      : '<p class="mantenimiento-aviso"><i class="ph ph-eye"></i> Simulación: '
        + 'todavía no se ha tocado nada.</p>';

    /* Las que se quedan fuera son las que no tienen afinidad real ("No-afi"):
       sin afinidad no hay cruce del que derivar nada, y forzarles una sería
       inventarse una compo. Se dice, para que no parezca que ha fallado. */
    var resto = (r.aplicado && r.quedan)
      ? '<p class="mantenimiento-aviso">' + r.quedan + ' se quedan sin compo a propósito: '
        + 'no tienen afinidad real, así que no hay ninguna que les corresponda.</p>'
      : '';

    return cabecera
      + '<ul class="mantenimiento-cifras"><li><b>' + r.pendientes + '</b> '
      + (r.pendientes === 1 ? 'carta sin compo' : 'cartas sin compo') + '</li></ul>'
      + '<div class="tabla-wrap"><table class="tabla"><thead><tr>'
      +   '<th>Carta</th><th>Posición</th><th>Afinidad</th><th></th></tr></thead><tbody>'
      + filas + '</tbody></table></div>'
      + (r.pendientes > (r.muestra || []).length
          ? '<p class="t-caption t-dim">…y ' + (r.pendientes - r.muestra.length) + ' más.</p>' : '')
      + resto;
  }

  var PINTORES = { webp: pintarWebp, compos: pintarCompos };

  /** ¿La simulación ha encontrado algo que hacer? */
  function hayTrabajo(tarea, r) {
    return tarea === 'webp' ? r.convertidas > 0 : r.pendientes > 0;
  }

  /* ------------------------------------------------------------------------
     LANZAR
     ------------------------------------------------------------------------ */

  function lanzar(tarea, aplica) {
    var salida = salidaDe(tarea);
    ocupado(tarea, true);
    salida.innerHTML = '<p class="mantenimiento-cargando">'
      + '<i class="ph ph-circle-notch"></i> '
      + (aplica ? 'Trabajando… puede tardar unos segundos.' : 'Mirando…') + '</p>';

    fetch(URL_AJAX, {
      method: 'POST',
      body: new URLSearchParams({
        accion: tarea, aplica: aplica ? '1' : '0', csrf: SRF.csrfToken(),
      }),
    })
      .then(function (res) { return res.json(); })
      .then(function (r) {
        ocupado(tarea, false);
        if (!r.ok) {
          salida.innerHTML = '<p class="mantenimiento-error"><i class="ph ph-warning-circle"></i> '
            + esc(r.error || 'La tarea falló.') + '</p>';
          return;
        }

        salida.innerHTML = PINTORES[tarea](r);

        /* El botón de ejecutar solo se enciende si de verdad hay trabajo, y se
           apaga en cuanto se ha hecho: pulsarlo dos veces sobre nada no rompe
           nada, pero invita a dudar de si funcionó la primera. */
        var ejecutar = document.querySelector(
          '[data-accion="' + tarea + '"][data-aplica="1"]'
        );
        if (ejecutar) { ejecutar.disabled = r.aplicado || !hayTrabajo(tarea, r); }
      })
      .catch(function () {
        ocupado(tarea, false);
        salida.innerHTML = '<p class="mantenimiento-error"><i class="ph ph-warning-circle"></i> '
          + 'No se pudo conectar con el servidor.</p>';
      });
  }

  var CONFIRMACION = {
    webp: 'Se van a convertir las imágenes a WebP y se BORRARÁN los archivos originales. '
        + 'Las rutas de la base se actualizan solas. ¿Seguir?',
    compos: 'Se va a asignar su compo a las cartas que no tengan ninguna. '
          + 'Las puestas a mano no se tocan. ¿Seguir?',
  };

  Array.prototype.forEach.call(document.querySelectorAll('[data-accion]'), function (btn) {
    btn.addEventListener('click', function () {
      var tarea  = btn.dataset.accion;
      var aplica = btn.dataset.aplica === '1';

      if (!aplica) { lanzar(tarea, false); return; }
      SRF.confirmar(CONFIRMACION[tarea], function () { lanzar(tarea, true); });
    });
  });
})();
