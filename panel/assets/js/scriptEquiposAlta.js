/**
 * ALTA RÁPIDA DE EQUIPOS desde cualquier formulario de cromo.
 *
 * Lo usan el panel de cromos (`f_*`) y el modal de carta exclusiva del editor
 * de cadenas (`fc_*`). El prefijo llega como argumento en vez de duplicar el
 * archivo: son el mismo desplegable con otro nombre.
 *
 * Existe porque el orden natural al meter contenido es el contrario del que
 * permitía la web: aparece un jugador de un equipo que todavía no está dado de
 * alta, y no había forma de crearlo sin abandonar el formulario a medias.
 */
(function () {
  'use strict';

  var SRF = (window.SRF = window.SRF || {});

  function el(prefijo, sufijo) { return document.getElementById(prefijo + sufijo); }

  /* Enseña u oculta los campos del alta según lo elegido en el desplegable. */
  function alternarAlta(prefijo) {
    var sel  = el(prefijo, '_id_equipo') || el(prefijo, '_equipo');
    var alta = el(prefijo, '_equipo_alta');
    if (!sel || !alta) return;
    alta.hidden = sel.value !== 'nuevo';
    if (!alta.hidden) {
      var nombre = el(prefijo, '_equipo_nombre');
      if (nombre) nombre.focus();
    }
  }

  function crear(prefijo) {
    var sel    = el(prefijo, '_id_equipo') || el(prefijo, '_equipo');
    var nombre = el(prefijo, '_equipo_nombre');
    var uni    = el(prefijo, '_equipo_universo');
    var aviso  = el(prefijo, '_equipo_aviso');
    if (!sel || !nombre) return;

    var texto = nombre.value.trim();
    if (texto === '') {
      if (aviso) aviso.textContent = 'Ponle un nombre al equipo.';
      nombre.focus();
      return;
    }

    var body = new URLSearchParams({
      accion: 'crear_equipo',
      csrf: SRF.csrfToken(),
      nombre: texto,
      universo: uni ? uni.value : 'srf',
    });

    /* La ruta es relativa a la página, y las dos que usan esto viven en
       panel/, así que `../assets/…` vale para ambas. */
    fetch('../assets/ajax/equipos.php', {
      method: 'POST', body: body,
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        if (!r.ok) { if (aviso) aviso.textContent = r.error || 'No se pudo crear.'; return; }

        /* El equipo nuevo se mete ANTES de la opción de alta —que es siempre la
           última— y queda seleccionado. Así se sigue con la carta donde se
           estaba, sin recargar y sin perder lo ya escrito. */
        var op = document.createElement('option');
        op.value = r.equipo.id_equipo;
        op.textContent = r.equipo.nombre;
        sel.insertBefore(op, sel.options[sel.options.length - 1]);
        sel.value = r.equipo.id_equipo;

        nombre.value = '';
        alternarAlta(prefijo);
        if (typeof SRF.toast === 'function') {
          SRF.toast('Equipo creado: ' + r.equipo.nombre, 'success');
        }
      })
      .catch(function () {
        if (aviso) aviso.textContent = 'No se pudo crear: la petición falló.';
      });
  }

  SRF.equipos = { alternarAlta: alternarAlta, crear: crear };
})();
