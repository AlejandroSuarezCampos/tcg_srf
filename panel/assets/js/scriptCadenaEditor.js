/**
 * Editor visual del mapa de una Cadena PvE (panel/cadena_editor.php).
 *
 * Sin build, sin dependencias — mismo patrón que el resto del panel (IIFE +
 * 'use strict'). Cada acción persiste por AJAX a assets/ajax/cadena_admin.php
 * y actualiza solo su trozo de estado local; nunca se recarga la página
 * entera, porque eso destrozaría lo que ya está dibujado en el lienzo.
 */
(function () {
  'use strict';

  var DATOS = window.CADENA_EDITOR_DATOS;
  var URL_AJAX = '../assets/ajax/cadena_admin.php';

  var COL_W = 190, FILA_H = 120, MARGEN = 40, NODO_W = 150, NODO_H = 64;

  var estado = {
    nodos: DATOS.nodos.slice(),
    aristas: DATOS.aristas.slice(),
    rivales: DATOS.rivales.slice(),
    cromos: DATOS.cromos.slice(),
    formaciones: DATOS.formaciones,
    huecosPorFormacion: DATOS.huecosPorFormacion,
    idCadena: DATOS.idCadena,
    nodoActual: null,
  };

  function post(accion, params) {
    var body = new URLSearchParams(Object.assign({ accion: accion }, params || {}));
    return fetch(URL_AJAX, { method: 'POST', body: body })
      .then(function (r) { return r.json(); });
  }

  function nodoPorId(id) {
    id = parseInt(id, 10);
    for (var i = 0; i < estado.nodos.length; i++) {
      if (parseInt(estado.nodos[i].id_nodo, 10) === id) return estado.nodos[i];
    }
    return null;
  }

  function rivalPorId(id) {
    id = parseInt(id, 10);
    for (var i = 0; i < estado.rivales.length; i++) {
      if (parseInt(estado.rivales[i].id_rival, 10) === id) return estado.rivales[i];
    }
    return null;
  }

  /* ------------------------------------------------------------------------
     LIENZO: pintado de nodos y aristas
     ------------------------------------------------------------------------ */

  function px(n, campo) { return MARGEN + parseInt(n[campo], 10) * (campo === 'columna' ? COL_W : FILA_H); }

  function renderTodo() {
    var lienzo = document.getElementById('cadenaLienzo');
    var svg = document.getElementById('cadenaSvg');

    Array.prototype.slice.call(lienzo.querySelectorAll('.cadena-nodo')).forEach(function (el) { el.remove(); });

    var maxCol = 4, maxFila = 3;
    estado.nodos.forEach(function (n) {
      maxCol = Math.max(maxCol, parseInt(n.columna, 10));
      maxFila = Math.max(maxFila, parseInt(n.fila, 10));
    });
    var anchoTotal = MARGEN * 2 + (maxCol + 1) * COL_W;
    var altoTotal = MARGEN * 2 + (maxFila + 1) * FILA_H;
    lienzo.style.width = anchoTotal + 'px';
    lienzo.style.height = altoTotal + 'px';
    svg.setAttribute('width', anchoTotal);
    svg.setAttribute('height', altoTotal);

    estado.nodos.forEach(function (n) { lienzo.appendChild(crearElementoNodo(n)); });
    renderAristas();
  }

  function crearElementoNodo(n) {
    var div = document.createElement('div');
    div.className = 'cadena-nodo cadena-nodo--' + n.tipo + (parseInt(n.es_final, 10) === 1 ? ' cadena-nodo--final' : '');
    div.dataset.id = n.id_nodo;
    div.style.left = px(n, 'columna') + 'px';
    div.style.top = px(n, 'fila') + 'px';

    var icono = n.tipo === 'cofre' ? 'ph-gift' : 'ph-flag';
    div.innerHTML =
      '<div class="cadena-nodo-cabeza"><i class="ph ' + icono + '" aria-hidden="true"></i>' +
      (parseInt(n.es_final, 10) === 1 ? '<i class="ph ph-star" aria-hidden="true" title="Nodo final"></i>' : '') +
      '</div>' +
      '<div class="cadena-nodo-nombre">' + escapeHtml(n.nombre || (n.tipo === 'cofre' ? 'Cofre' : 'Partido')) + '</div>' +
      '<div class="cadena-nodo-rival">' + escapeHtml(n.rival || (n.tipo === 'partido' ? 'Sin rival' : '')) + '</div>' +
      '<div class="cadena-nodo-anilla" title="Arrastra hasta otro nodo para conectarlos"></div>';

    activarArrastreNodo(div, n);
    activarAnillaConexion(div.querySelector('.cadena-nodo-anilla'), n);

    return div;
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function renderAristas() {
    var svg = document.getElementById('cadenaSvg');
    svg.innerHTML = '';
    estado.aristas.forEach(function (a) { svg.appendChild(crearLineaArista(a)); });
  }

  function centroDe(id) {
    var n = nodoPorId(id);
    if (!n) return null;
    return { x: px(n, 'columna') + NODO_W / 2, y: px(n, 'fila') + NODO_H / 2 };
  }

  function crearLineaArista(a) {
    var g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    var o = centroDe(a.id_origen), d = centroDe(a.id_destino);
    if (!o || !d) return g;

    var visible = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    visible.setAttribute('x1', o.x); visible.setAttribute('y1', o.y);
    visible.setAttribute('x2', d.x); visible.setAttribute('y2', d.y);
    visible.setAttribute('class', 'cadena-editor-linea');

    var golpe = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    golpe.setAttribute('x1', o.x); golpe.setAttribute('y1', o.y);
    golpe.setAttribute('x2', d.x); golpe.setAttribute('y2', d.y);
    golpe.setAttribute('class', 'cadena-editor-linea-golpe');
    golpe.addEventListener('click', function () {
      SRF.confirmar('¿Quitar esta conexión del mapa?', function () {
        post('eliminar_arista', { id_origen: a.id_origen, id_destino: a.id_destino }).then(function () {
          estado.aristas = estado.aristas.filter(function (x) {
            return !(x.id_origen == a.id_origen && x.id_destino == a.id_destino);
          });
          renderAristas();
        });
      });
    });

    g.appendChild(visible);
    g.appendChild(golpe);
    return g;
  }

  /* ------------------------------------------------------------------------
     ARRASTRE de nodos (mover posición) y de la anilla (conectar aristas)
     ------------------------------------------------------------------------ */

  function activarArrastreNodo(div, n) {
    div.addEventListener('mousedown', function (ev) {
      if (ev.target.classList.contains('cadena-nodo-anilla')) return;
      ev.preventDefault();

      var inicioX = ev.clientX, inicioY = ev.clientY;
      var left0 = parseInt(div.style.left, 10), top0 = parseInt(div.style.top, 10);
      var movido = false;

      function mover(e) {
        var dx = e.clientX - inicioX, dy = e.clientY - inicioY;
        if (Math.abs(dx) > 4 || Math.abs(dy) > 4) movido = true;
        div.style.left = (left0 + dx) + 'px';
        div.style.top = (top0 + dy) + 'px';
        n.columna = Math.max(0, Math.round((left0 + dx - MARGEN) / COL_W));
        n.fila = Math.max(0, Math.round((top0 + dy - MARGEN) / FILA_H));
        renderAristas();
      }

      function soltar() {
        document.removeEventListener('mousemove', mover);
        document.removeEventListener('mouseup', soltar);

        if (!movido) { abrirModalNodo(n); return; }

        div.style.left = px(n, 'columna') + 'px';
        div.style.top = px(n, 'fila') + 'px';
        renderAristas();
        post('mover_nodo', { id_nodo: n.id_nodo, columna: n.columna, fila: n.fila });
      }

      document.addEventListener('mousemove', mover);
      document.addEventListener('mouseup', soltar);
    });
  }

  function activarAnillaConexion(anilla, origen) {
    anilla.addEventListener('mousedown', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();

      var svg = document.getElementById('cadenaSvg');
      var lienzo = document.getElementById('cadenaLienzo');
      var temporal = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      temporal.setAttribute('class', 'cadena-editor-linea cadena-editor-linea--temporal');
      var c0 = centroDe(origen.id_nodo);
      temporal.setAttribute('x1', c0.x); temporal.setAttribute('y1', c0.y);
      temporal.setAttribute('x2', c0.x); temporal.setAttribute('y2', c0.y);
      svg.appendChild(temporal);

      function mover(e) {
        var rect = lienzo.getBoundingClientRect();
        temporal.setAttribute('x2', e.clientX - rect.left);
        temporal.setAttribute('y2', e.clientY - rect.top);
      }

      function soltar(e) {
        document.removeEventListener('mousemove', mover);
        document.removeEventListener('mouseup', soltar);
        temporal.remove();

        var destinoEl = document.elementFromPoint(e.clientX, e.clientY);
        var destinoDiv = destinoEl ? destinoEl.closest('.cadena-nodo') : null;
        if (!destinoDiv) return;
        var idDestino = parseInt(destinoDiv.dataset.id, 10);
        if (idDestino === parseInt(origen.id_nodo, 10)) return;

        var yaExiste = estado.aristas.some(function (a) {
          return a.id_origen == origen.id_nodo && a.id_destino == idDestino;
        });
        if (yaExiste) return;

        post('crear_arista', { id_origen: origen.id_nodo, id_destino: idDestino }).then(function () {
          estado.aristas.push({ id_origen: origen.id_nodo, id_destino: idDestino });
          renderAristas();
        });
      }

      document.addEventListener('mousemove', mover);
      document.addEventListener('mouseup', soltar);
    });
  }

  /* ------------------------------------------------------------------------
     ALTA DE NODO
     ------------------------------------------------------------------------ */

  function nuevoNodo(tipo) {
    var maxCol = -1;
    estado.nodos.forEach(function (n) { maxCol = Math.max(maxCol, parseInt(n.columna, 10)); });
    var columna = maxCol + 1, fila = 0;

    post('crear_nodo', { id_cadena: estado.idCadena, tipo: tipo, columna: columna, fila: fila, nombre: '' })
      .then(function (r) {
        if (!r.ok) return;
        estado.nodos.push(r.nodo);
        renderTodo();
        abrirModalNodo(r.nodo);
      });
  }

  /* ------------------------------------------------------------------------
     MODAL DE NODO
     ------------------------------------------------------------------------ */

  function abrirModalNodo(n) {
    estado.nodoActual = n;

    document.getElementById('modalNodoTitulo').textContent = (n.tipo === 'cofre' ? 'Cofre' : 'Partido') + ' — nodo #' + n.id_nodo;
    document.getElementById('fn_tipo').value = n.tipo;
    document.getElementById('fn_nombre').value = n.nombre || '';
    document.getElementById('fn_es_final').checked = parseInt(n.es_final, 10) === 1;

    document.getElementById('fn_nuevo_rival').hidden = true;
    document.getElementById('fn_nuevo_estilo').hidden = true;

    var selRival = document.getElementById('fn_rival');
    selRival.innerHTML = '<option value="">— Sin rival asignado —</option>';
    estado.rivales.forEach(function (r) {
      var op = document.createElement('option');
      op.value = r.id_rival; op.textContent = r.nombre;
      selRival.appendChild(op);
    });
    selRival.value = n.id_rival || '';

    alternarTipoNodo();
    actualizarBloqueEstilos();
    renderizarLoot();
    poblarSelectCromos(document.getElementById('fl_cromo'));
    alternarTipoLoot();

    SRF.abrirModal('modalNodo');
  }

  function cerrarModalNodo() {
    SRF.cerrarModal('modalNodo');
    estado.nodoActual = null;
  }

  function alternarTipoNodo() {
    document.getElementById('fn_bloque_rival').hidden = document.getElementById('fn_tipo').value !== 'partido';
  }

  function guardarNodo() {
    var n = estado.nodoActual;
    if (!n) return;

    n.tipo = document.getElementById('fn_tipo').value;
    n.nombre = document.getElementById('fn_nombre').value.trim();
    n.es_final = document.getElementById('fn_es_final').checked ? 1 : 0;
    var idRival = document.getElementById('fn_rival').value;
    n.id_rival = idRival || null;
    var rival = idRival ? rivalPorId(idRival) : null;
    n.rival = rival ? rival.nombre : null;

    post('actualizar_nodo', {
      id_nodo: n.id_nodo, tipo: n.tipo, nombre: n.nombre,
      es_final: n.es_final ? 1 : '', id_rival: n.id_rival || '',
    }).then(function () {
      renderTodo();
      cerrarModalNodo();
    });
  }

  function borrarNodo() {
    var n = estado.nodoActual;
    if (!n) return;
    SRF.confirmar('¿Eliminar este nodo? También se quitarán sus conexiones, su botín, y el progreso real que tuvieran los jugadores en él. No se puede deshacer.', function () {
      post('eliminar_nodo', { id_nodo: n.id_nodo }).then(function () {
        estado.nodos = estado.nodos.filter(function (x) { return x.id_nodo != n.id_nodo; });
        estado.aristas = estado.aristas.filter(function (a) { return a.id_origen != n.id_nodo && a.id_destino != n.id_nodo; });
        cerrarModalNodo();
        renderTodo();
      });
    });
  }

  /* ------------------------------------------------------------------------
     RIVAL / ESTILO / CARTAS, dentro del modal de nodo
     ------------------------------------------------------------------------ */

  function mostrarNuevoRival() {
    document.getElementById('fn_nuevo_rival').hidden = false;
  }

  function crearRival() {
    var nombre = document.getElementById('fn_rival_nombre').value.trim();
    if (!nombre) { window.alert('Ponle un nombre al rival.'); return; }

    post('crear_rival', {
      nombre: nombre,
      escudo: document.getElementById('fn_rival_escudo').value.trim(),
      descripcion: document.getElementById('fn_rival_descripcion').value.trim(),
    }).then(function (r) {
      if (!r.ok) { window.alert(r.error || 'No se pudo crear el rival.'); return; }
      r.rival.estilos = [];
      estado.rivales.push(r.rival);

      var selRival = document.getElementById('fn_rival');
      var op = document.createElement('option');
      op.value = r.rival.id_rival; op.textContent = r.rival.nombre;
      selRival.appendChild(op);
      selRival.value = r.rival.id_rival;

      document.getElementById('fn_nuevo_rival').hidden = true;
      document.getElementById('fn_rival_nombre').value = '';
      document.getElementById('fn_rival_escudo').value = '';
      document.getElementById('fn_rival_descripcion').value = '';

      actualizarBloqueEstilos();
    });
  }

  function cambiarRival() {
    document.getElementById('fn_nuevo_rival').hidden = true;
    actualizarBloqueEstilos();
  }

  function actualizarBloqueEstilos() {
    var idRival = document.getElementById('fn_rival').value;
    var bloque = document.getElementById('fn_bloque_estilo');
    document.getElementById('fn_nuevo_estilo').hidden = true;
    document.getElementById('fn_cartas_wrap').innerHTML = '';

    if (!idRival) { bloque.hidden = true; return; }
    bloque.hidden = false;

    var rival = rivalPorId(idRival);
    var selEstilo = document.getElementById('fn_estilo');
    selEstilo.innerHTML = '<option value="">— Elige un estilo —</option>';
    (rival.estilos || []).forEach(function (es) {
      var op = document.createElement('option');
      op.value = es.id_estilo; op.textContent = es.nombre + ' (' + estado.formaciones[es.formacion].nombre + ')';
      op.dataset.formacion = es.formacion;
      selEstilo.appendChild(op);
    });

    var selFormacion = document.getElementById('fn_estilo_formacion');
    selFormacion.innerHTML = '';
    Object.keys(estado.formaciones).forEach(function (clave) {
      var op = document.createElement('option');
      op.value = clave; op.textContent = estado.formaciones[clave].nombre;
      selFormacion.appendChild(op);
    });
  }

  function mostrarNuevoEstilo() {
    document.getElementById('fn_nuevo_estilo').hidden = false;
  }

  function crearEstilo() {
    var idRival = document.getElementById('fn_rival').value;
    var nombre = document.getElementById('fn_estilo_nombre').value.trim();
    var formacion = document.getElementById('fn_estilo_formacion').value;
    if (!idRival) return;

    post('crear_estilo', { id_rival: idRival, nombre: nombre, formacion: formacion }).then(function (r) {
      if (!r.ok) { window.alert(r.error || 'No se pudo crear el estilo.'); return; }

      var rival = rivalPorId(idRival);
      rival.estilos = rival.estilos || [];
      rival.estilos.push(r.estilo);

      var selEstilo = document.getElementById('fn_estilo');
      var op = document.createElement('option');
      op.value = r.estilo.id_estilo; op.textContent = r.estilo.nombre + ' (' + estado.formaciones[r.estilo.formacion].nombre + ')';
      op.dataset.formacion = r.estilo.formacion;
      selEstilo.appendChild(op);
      selEstilo.value = r.estilo.id_estilo;

      document.getElementById('fn_nuevo_estilo').hidden = true;

      construirTablaCartas(r.huecos, []);
    });
  }

  function cambiarEstilo() {
    var idEstilo = document.getElementById('fn_estilo').value;
    if (!idEstilo) { document.getElementById('fn_cartas_wrap').innerHTML = ''; return; }

    var op = document.getElementById('fn_estilo').selectedOptions[0];
    var formacion = op.dataset.formacion;

    post('listar_cartas_estilo', { id_estilo: idEstilo }).then(function (r) {
      construirTablaCartas(estado.huecosPorFormacion[formacion], r.cartas || []);
    });
  }

  function construirTablaCartas(huecos, cartasExistentes) {
    var idEstilo = document.getElementById('fn_estilo').value;
    var porHueco = {};
    cartasExistentes.forEach(function (c) { porHueco[c.hueco] = c.id_cromo; });

    var wrap = document.getElementById('fn_cartas_wrap');
    var tabla = document.createElement('table');
    tabla.className = 'tabla';
    tabla.innerHTML = '<thead><tr><th>Hueco</th><th>Posición</th><th>Carta</th></tr></thead>';
    var tbody = document.createElement('tbody');

    huecos.forEach(function (posicion, i) {
      var tr = document.createElement('tr');
      var select = crearSelectCromos(posicion, porHueco[i]);
      select.addEventListener('change', function () {
        post('asignar_carta', { id_estilo: idEstilo, hueco: i, id_cromo: select.value });
      });

      var tdSelect = document.createElement('td');
      tdSelect.appendChild(select);

      tr.innerHTML = '<td class="mono">#' + i + '</td><td>' + posicion + '</td>';
      tr.appendChild(tdSelect);
      tbody.appendChild(tr);
    });

    tabla.appendChild(tbody);
    wrap.innerHTML = '';
    wrap.appendChild(tabla);
  }

  function crearSelectCromos(posicionPreferida, idSeleccionado) {
    var select = document.createElement('select');
    select.appendChild(new Option('— Vacío —', ''));

    var mismos = estado.cromos.filter(function (c) { return c.posicion === posicionPreferida; });
    var resto = estado.cromos.filter(function (c) { return c.posicion !== posicionPreferida; });

    [mismos, resto].forEach(function (grupo) {
      grupo.forEach(function (c) {
        var op = new Option(c.nombre + ' (' + c.equipo + ', ' + c.rareza + ')', c.id_cromo);
        select.appendChild(op);
      });
    });

    select.value = idSeleccionado || '';
    return select;
  }

  function poblarSelectCromos(select) {
    select.innerHTML = '';
    estado.cromos.forEach(function (c) {
      select.appendChild(new Option(c.nombre + ' (' + c.equipo + ')', c.id_cromo));
    });
  }

  /* ------------------------------------------------------------------------
     BOTÍN del nodo
     ------------------------------------------------------------------------ */

  function alternarTipoLoot() {
    var tipo = document.getElementById('fl_tipo').value;
    document.getElementById('fl_grupo_monedas').hidden = tipo !== 'monedas';
    document.getElementById('fl_grupo_cromo').hidden = tipo === 'monedas';
  }

  function renderizarLoot() {
    var n = estado.nodoActual;
    var cont = document.getElementById('fn_loot_lista');
    if (!n.loot || !n.loot.length) {
      cont.innerHTML = '<p class="t-caption t-dim">Sin botín todavía.</p>';
      return;
    }

    var tabla = document.createElement('table');
    tabla.className = 'tabla';
    tabla.innerHTML = '<thead><tr><th>Tipo</th><th>Qué</th><th>Prob.</th><th>Rango mínimo</th><th></th></tr></thead>';
    var tbody = document.createElement('tbody');

    n.loot.forEach(function (l) {
      var tr = document.createElement('tr');
      var que = l.tipo === 'monedas' ? (l.monedas + ' monedas') : (l.cromo_nombre || ('cromo #' + l.id_cromo));
      tr.innerHTML =
        '<td>' + l.tipo + '</td><td>' + escapeHtml(que) + '</td>' +
        '<td class="mono">' + l.probabilidad + '%</td><td>' + (l.rango_minimo || '—') + '</td>' +
        '<td style="text-align:right;"><button type="button" class="icon-btn es-peligro" title="Quitar"><i class="ph ph-trash" aria-hidden="true"></i></button></td>';
      tr.querySelector('button').addEventListener('click', function () { eliminarLoot(l.id_loot); });
      tbody.appendChild(tr);
    });

    tabla.appendChild(tbody);
    cont.innerHTML = '';
    cont.appendChild(tabla);
  }

  function crearLoot() {
    var n = estado.nodoActual;
    var tipo = document.getElementById('fl_tipo').value;
    var idCromo = document.getElementById('fl_cromo').value;
    var cromoTexto = document.getElementById('fl_cromo').selectedOptions[0] ? document.getElementById('fl_cromo').selectedOptions[0].textContent : '';

    post('crear_loot', {
      id_nodo: n.id_nodo, tipo: tipo,
      id_cromo: tipo === 'monedas' ? '' : idCromo,
      monedas: tipo === 'monedas' ? document.getElementById('fl_monedas').value : '',
      probabilidad: document.getElementById('fl_probabilidad').value,
      rango_minimo: document.getElementById('fl_rango').value,
    }).then(function (r) {
      if (!r.ok) return;
      n.loot = n.loot || [];
      n.loot.push({
        id_loot: r.id_loot, tipo: tipo,
        monedas: document.getElementById('fl_monedas').value,
        id_cromo: idCromo, cromo_nombre: cromoTexto,
        probabilidad: document.getElementById('fl_probabilidad').value,
        rango_minimo: document.getElementById('fl_rango').value,
      });
      renderizarLoot();
    });
  }

  function eliminarLoot(idLoot) {
    var n = estado.nodoActual;
    SRF.confirmar('¿Quitar esta entrada de botín?', function () {
      post('eliminar_loot', { id_loot: idLoot }).then(function () {
        n.loot = n.loot.filter(function (l) { return l.id_loot != idLoot; });
        renderizarLoot();
      });
    });
  }

  /* ------------------------------------------------------------------------ */

  window.SRF = window.SRF || {};
  SRF.cadenaEditor = {
    nuevoNodo: nuevoNodo,
    cerrarModalNodo: cerrarModalNodo,
    alternarTipoNodo: alternarTipoNodo,
    guardarNodo: guardarNodo,
    borrarNodo: borrarNodo,
    mostrarNuevoRival: mostrarNuevoRival,
    crearRival: crearRival,
    cambiarRival: cambiarRival,
    mostrarNuevoEstilo: mostrarNuevoEstilo,
    crearEstilo: crearEstilo,
    cambiarEstilo: cambiarEstilo,
    alternarTipoLoot: alternarTipoLoot,
    crearLoot: crearLoot,
  };

  document.addEventListener('DOMContentLoaded', renderTodo);
})();
