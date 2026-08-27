/**
 * panel/importar.php, paso 2 (confirmación). "Crear cartas" ya no es un
 * submit normal: dispara un fetch() a assets/ajax/importacion_ejecutar.php
 * y, mientras esa petición sigue en vuelo (puede tardar varios minutos por
 * la descarga de fotos), sondea assets/ajax/importacion_progreso.php cada
 * ~800ms para ir llenando la barra. Ver §16.11 del CLAUDE.md de branding.
 */
(function () {
  'use strict';

  var btn = document.getElementById('btnConfirmarImportacion');
  if (btn) {
    var form = document.getElementById('formPrevisualizacion');
    var cajaProgreso = document.getElementById('importacionProgreso');
    var barra = document.getElementById('importacionProgresoBarra');
    var textoProgreso = document.getElementById('importacionProgresoTexto');
    var cajaResultado = document.getElementById('importacionResultado');

    var URL_EJECUTAR = '../assets/ajax/importacion_ejecutar';
    var URL_PROGRESO = '../assets/ajax/importacion_progreso';
    var INTERVALO = 800;
    var temporizador = null;

    var detenerSondeo = function () {
      if (temporizador) { clearInterval(temporizador); temporizador = null; }
    };

    var sondearProgreso = async function () {
      try {
        var res = await fetch(URL_PROGRESO);
        var datos = await res.json();
        var total = datos.total || 0;
        barra.max = total > 0 ? total : 1;
        barra.value = datos.actual || 0;
        textoProgreso.textContent = total > 0 ? (datos.actual + ' de ' + total) : 'Importando…';
      } catch (err) {
        console.error(err);
      }
    };

    var pintarAlerta = function (contenedor, tipo, texto) {
      var div = document.createElement('div');
      div.className = 'alerta alerta-' + tipo;
      div.setAttribute('role', tipo === 'danger' ? 'alert' : 'status');
      var icono = document.createElement('i');
      icono.className = 'ph ' + (tipo === 'danger' ? 'ph-warning-circle' : 'ph-warning');
      icono.setAttribute('aria-hidden', 'true');
      var span = document.createElement('span');
      span.textContent = texto;
      div.appendChild(icono);
      div.appendChild(span);
      contenedor.appendChild(div);
    };

    var pintarResultado = function (resultado) {
      cajaResultado.innerHTML = '';

      var titulo = document.createElement('h2');
      titulo.className = 't-h3';
      titulo.textContent = resultado.creados + ' cartas creadas';
      cajaResultado.appendChild(titulo);

      var lista = document.createElement('ul');
      lista.className = 't-body-sm';
      lista.style.margin = 'var(--e-3) 0 var(--e-4)';
      lista.style.paddingLeft = '1.2em';
      var liOmitidos = document.createElement('li');
      liOmitidos.textContent = resultado.omitidos + ' omitidas (ya existían)';
      var liEquipos = document.createElement('li');
      liEquipos.textContent = resultado.equipos_creados + ' equipos nuevos creados';
      lista.appendChild(liOmitidos);
      lista.appendChild(liEquipos);
      cajaResultado.appendChild(lista);

      if (resultado.fotos_fallidas && resultado.fotos_fallidas.length > 0) {
        pintarAlerta(cajaResultado, 'warning', 'No se pudo descargar la foto de: ' + resultado.fotos_fallidas.join(', ') + '. Esas cartas se crearon sin imagen.');
      }
      if (resultado.posiciones_desconocidas && resultado.posiciones_desconocidas.length > 0) {
        pintarAlerta(cajaResultado, 'warning', 'No se crearon por posición no reconocida: ' + resultado.posiciones_desconocidas.join(', ') + '.');
      }

      cajaResultado.style.display = '';
    };

    btn.addEventListener('click', async function () {
      form.style.display = 'none';
      cajaProgreso.style.display = '';

      var datosFormulario = new FormData(form);

      temporizador = setInterval(sondearProgreso, INTERVALO);
      sondearProgreso();

      try {
        var res = await fetch(URL_EJECUTAR, { method: 'POST', body: datosFormulario });
        var resultado = await res.json();

        detenerSondeo();
        cajaProgreso.style.display = 'none';

        if (!res.ok || resultado.error) {
          pintarAlerta(cajaResultado, 'danger', resultado.error || 'Ha ocurrido un error al importar.');
          cajaResultado.style.display = '';
          return;
        }

        pintarResultado(resultado);
      } catch (err) {
        detenerSondeo();
        cajaProgreso.style.display = 'none';
        pintarAlerta(cajaResultado, 'danger', 'Error de red al importar: ' + err.message);
        cajaResultado.style.display = '';
      }
    });
  }

  // Borrado de cartas importadas por expansión, desde la tabla del paso 1.
  window.pedirBorradoImportados = function (idExpansion, nombre, total) {
    SRF.confirmar(
      '¿Borrar las ' + total + ' cartas importadas de "' + nombre + '"? Esta acción no se puede deshacer.',
      function () { window.location.href = 'importar.php?borrar_importadas=1&id_expansion=' + idExpansion + '&csrf=' + encodeURIComponent(SRF.csrfToken()); }
    );
  };
})();
