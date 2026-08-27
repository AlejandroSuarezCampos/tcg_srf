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
  var URL_AJAX = '../assets/ajax/cadena_admin';

  /* ⚠️ LOS NODOS YA NO VIVEN EN UNA REJILLA (migración `044`).
     Antes se colocaban en casillas de 190x120: meter un nodo entre dos
     obligaba a empujar a todos los demás, y los mapas ramificados y densos que
     se quieren montar eran imposibles.
     Ahora se guardan las coordenadas en píxeles y la rejilla es solo un IMÁN
     de 10 px, para que las cosas queden alineadas sin pelearse con ellas. Con
     Shift pulsado el imán se apaga y se coloca al píxel.
     COL_W/FILA_H se quedan porque son de donde salen las posiciones de las
     cadenas anteriores a esta migración. */
  var COL_W = 190, FILA_H = 120, MARGEN = 40, NODO_W = 150, NODO_H = 64;
  var IMAN = 10;
  var LIENZO_MIN_W = 900, LIENZO_MIN_H = 460;

  /* Cuánto tiene que acercarse un nodo a una línea para que el imán lo agarre.
     8 px: por debajo hay que apuntar y deja de ayudar; por encima cuesta
     colocar algo A PROPÓSITO cerca de otra cosa sin que se pegue. */
  var IMAN_GUIA = 8;

  /** Dónde está un nodo, en píxeles. Cae a la rejilla vieja si no tiene. */
  function nodoX(n) {
    return (n.pos_x !== null && n.pos_x !== undefined && n.pos_x !== '')
      ? parseInt(n.pos_x, 10)
      : MARGEN + parseInt(n.columna || 0, 10) * COL_W;
  }
  function nodoY(n) {
    return (n.pos_y !== null && n.pos_y !== undefined && n.pos_y !== '')
      ? parseInt(n.pos_y, 10)
      : MARGEN + parseInt(n.fila || 0, 10) * FILA_H;
  }
  function imantar(v, libre) { return libre ? Math.round(v) : Math.round(v / IMAN) * IMAN; }

  var estado = {
    nodos: DATOS.nodos.slice(),
    aristas: DATOS.aristas.slice(),
    rivales: DATOS.rivales.slice(),
    cromos: DATOS.cromos.slice(),
    formaciones: DATOS.formaciones,
    huecosPorFormacion: DATOS.huecosPorFormacion,
    coordsPorFormacion: DATOS.coordsPorFormacion,
    dificultades: DATOS.dificultades,
    idCadena: DATOS.idCadena,
    nodoActual: null,
    // Alineación en edición: qué estilo, qué formación y qué cromo hay en cada
    // hueco. Vive aquí y no en el DOM para que repintar el campo (al cambiar
    // de formación, al filtrar) no pierda lo ya colocado.
    estiloActual: null,
    formacionActual: null,
    huecoActivo: null,
    porHueco: {},
  };

  var ETIQUETA_LINEA = { POR: 'Portería', DF: 'Defensa', MC: 'Medio', DC: 'Ataque' };
  var ETIQUETA_DIFICULTAD = {
    facil: 'Fácil', medio: 'Medio', dificil: 'Difícil',
    muy_dificil: 'Muy difícil', extremo: 'Extremo',
  };

  function post(accion, params) {
    var body = new URLSearchParams(Object.assign({ accion: accion, csrf: SRF.csrfToken() }, params || {}));
    return fetch(URL_AJAX, { method: 'POST', body: body })
      .then(function (r) { return r.json(); });
  }

  // Igual que post(), pero en multipart: hace falta cuando el escudo se manda
  // como archivo (crear_rival / actualizar_rival), URLSearchParams no puede
  // llevar binarios.
  function postConEscudo(accion, params, archivoEscudo) {
    var body = new FormData();
    body.append('accion', accion);
    body.append('csrf', SRF.csrfToken());
    Object.keys(params || {}).forEach(function (k) { body.append(k, params[k]); });
    if (archivoEscudo) body.append('escudo_archivo', archivoEscudo);
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

  /* ------------------------------------------------------------------------
     GUÍAS E IMÁN DE ALINEACIÓN

     Lo que hace Photoshop al arrastrar: si el centro de lo que mueves se
     acerca al centro del lienzo o al de otro elemento, se pega ahí y aparece
     una línea que dice por qué. Sin esto, dejar dos nodos alineados era
     cuestión de pulso, y basta un píxel de desvío para que el mapa se vea
     torcido —que es justo lo que se nota en la pantalla del jugador, donde
     todo se pinta más pequeño.

     Dos familias de líneas, con colores distintos porque significan cosas
     distintas:
       · el CENTRO del lienzo (magenta), que es donde hay que poner lo que
         quieras centrado en la cadena;
       · la alineación con OTRO nodo (cian): mismo centro horizontal o
         vertical que un vecino.

     Con Shift pulsado no hay imán de ninguna clase: ni rejilla ni guías. Es la
     salida para cuando lo que quieres es justo lo que el imán te impide.
     ------------------------------------------------------------------------ */
  function guiaEl(cual) {
    var lienzo = document.getElementById('cadenaLienzo');
    var id = 'guia-' + cual;
    var el = document.getElementById(id);
    if (!el) {
      el = document.createElement('div');
      el.id = id;
      el.className = 'cadena-guia cadena-guia--' + (cual.indexOf('x') === 0 ? 'v' : 'h');
      el.hidden = true;
      lienzo.appendChild(el);
    }
    return el;
  }

  function pintarGuias(guias) {
    ['x', 'y'].forEach(function (eje) {
      var g = guias[eje];
      var el = guiaEl(eje);
      if (!g) { el.hidden = true; return; }

      el.hidden = false;
      el.classList.toggle('es-centro', g.centro);
      if (eje === 'x') { el.style.left = g.px + 'px'; }
      else             { el.style.top  = g.px + 'px'; }
    });
  }

  function ocultarGuias() {
    ['x', 'y'].forEach(function (eje) { guiaEl(eje).hidden = true; });
  }

  /**
   * Ajusta la posición arrastrada a la guía más cercana, si hay alguna a tiro.
   *
   * Trabaja con el CENTRO del nodo y no con su esquina: alinear esquinas de
   * cajas del mismo tamaño da igual, pero en cuanto una es más alta que otra
   * lo que se ve torcido son los centros.
   *
   * Devuelve { x, y, guias } con la posición ya corregida (esquina superior
   * izquierda otra vez) y qué líneas hay que pintar.
   */
  function imantarAGuias(x, y, idArrastrado) {
    var lienzo = document.getElementById('cadenaLienzo');
    var cx = x + NODO_W / 2;
    var cy = y + NODO_H / 2;

    // Candidatas: el centro del lienzo y el centro de cada uno de los demás.
    var enX = [{ v: parseInt(lienzo.style.width, 10) / 2, centro: true }];
    var enY = [{ v: parseInt(lienzo.style.height, 10) / 2, centro: true }];

    estado.nodos.forEach(function (n) {
      if (String(n.id_nodo) === String(idArrastrado)) { return; }
      enX.push({ v: nodoX(n) + NODO_W / 2, centro: false });
      enY.push({ v: nodoY(n) + NODO_H / 2, centro: false });
    });

    /* La más cercana dentro del umbral. Con empate gana la del CENTRO del
       lienzo: si estás a la vez alineado con un vecino y con el centro, lo que
       querías era el centro. */
    var mejor = function (valor, lista) {
      var elegida = null;
      lista.forEach(function (c) {
        var d = Math.abs(valor - c.v);
        if (d > IMAN_GUIA) { return; }
        if (!elegida || d < elegida.d || (d === elegida.d && c.centro)) {
          elegida = { v: c.v, d: d, centro: c.centro };
        }
      });
      return elegida;
    };

    var gx = mejor(cx, enX);
    var gy = mejor(cy, enY);

    return {
      x: gx ? Math.round(gx.v - NODO_W / 2) : x,
      y: gy ? Math.round(gy.v - NODO_H / 2) : y,
      guias: {
        x: gx ? { px: gx.v, centro: gx.centro } : null,
        y: gy ? { px: gy.v, centro: gy.centro } : null,
      },
    };
  }

  function renderTodo() {
    var lienzo = document.getElementById('cadenaLienzo');
    var svg = document.getElementById('cadenaSvg');

    Array.prototype.slice.call(lienzo.querySelectorAll('.cadena-nodo')).forEach(function (el) { el.remove(); });

    /* El lienzo crece con lo que haya, más un margen de sobra por la derecha y
       por abajo: sin ese margen no habría sitio donde soltar un nodo nuevo
       fuera de lo ya dibujado. */
    var maxX = LIENZO_MIN_W, maxY = LIENZO_MIN_H;
    estado.nodos.forEach(function (n) {
      maxX = Math.max(maxX, nodoX(n) + NODO_W);
      maxY = Math.max(maxY, nodoY(n) + NODO_H);
    });
    var anchoTotal = maxX + MARGEN * 4;
    var altoTotal  = maxY + MARGEN * 4;
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
    div.style.left = nodoX(n) + 'px';
    div.style.top = nodoY(n) + 'px';

    var ICONO = { cofre: 'ph-gift', inicio: 'ph-play-circle', partido: 'ph-flag', bloqueo: 'ph-prohibit' };
    var POR_DEFECTO = { cofre: 'Cofre', inicio: 'SALIDA', partido: 'Partido', bloqueo: 'STOP' };

    div.innerHTML =
      '<div class="cadena-nodo-cabeza"><i class="ph ' + (ICONO[n.tipo] || ICONO.partido) + '" aria-hidden="true"></i>' +
      (parseInt(n.es_final, 10) === 1 ? '<i class="ph ph-star" aria-hidden="true" title="Nodo final"></i>' : '') +
      '</div>' +
      '<div class="cadena-nodo-nombre">' + escapeHtml(n.nombre || POR_DEFECTO[n.tipo] || 'Partido') + '</div>' +
      '<div class="cadena-nodo-rival">' + escapeHtml(
          n.tipo === 'inicio'  ? 'Por aquí se empieza' :
          n.tipo === 'bloqueo' ? 'No se pasa sin cumplir' :
          (n.rival || (n.tipo === 'partido' ? 'Sin rival' : ''))
      ) + '</div>' +
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
    return { x: nodoX(n) + NODO_W / 2, y: nodoY(n) + NODO_H / 2 };
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

        var crudoX = Math.max(0, left0 + dx);
        var crudoY = Math.max(0, top0 + dy);
        var px, py;

        if (e.shiftKey) {
          // Shift = sin imán de ninguna clase, al píxel.
          px = Math.round(crudoX);
          py = Math.round(crudoY);
          ocultarGuias();
        } else {
          /* ⚠️ LAS GUÍAS SE MIRAN CONTRA LA POSICIÓN CRUDA, ANTES DE LA
             REJILLA. Al revés no funciona, y se vio midiendo: la rejilla de
             10 px movía el nodo hasta 5 px, y ese empujón bastaba para sacarlo
             del alcance de la guía —quedaba a 10 px del centro con un umbral
             de 8— así que el imán del centro no llegaba a agarrar nunca.

             Y cuando una guía agarra, MANDA ELLA: se va al centro exacto
             aunque no caiga en múltiplo de 10. Un imán que te deja a 3 px de
             lo que buscabas no sirve de nada. La rejilla solo actúa en los
             ejes donde no hay ninguna guía cerca. */
          var ajuste = imantarAGuias(crudoX, crudoY, n.id_nodo);
          px = ajuste.guias.x ? ajuste.x : imantar(crudoX, false);
          py = ajuste.guias.y ? ajuste.y : imantar(crudoY, false);
          pintarGuias(ajuste.guias);
        }

        n.pos_x = px;
        n.pos_y = py;
        div.style.left = px + 'px';
        div.style.top  = py + 'px';
        renderAristas();
      }

      function soltar() {
        document.removeEventListener('mousemove', mover);
        document.removeEventListener('mouseup', soltar);
        ocultarGuias();

        if (!movido) { abrirModalNodo(n); return; }

        /* El lienzo se remide al soltar: un nodo arrastrado más allá del borde
           tiene que hacer crecer el papel, o se queda medio fuera y sin forma
           de volver a cogerlo. */
        renderTodo();
        post('mover_nodo', { id_nodo: n.id_nodo, x: n.pos_x, y: n.pos_y });
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

  /**
   * Crea un nodo en un hueco libre.
   *
   * Va a la derecha del que esté más a la derecha, no a una columna nueva de
   * la rejilla: desde la `044` no hay columnas. Se busca hacia abajo si ese
   * sitio ya está pillado, para no apilar dos nodos exactamente encima.
   */
  function nuevoNodo(tipo) {
    var x = MARGEN, y = MARGEN;
    estado.nodos.forEach(function (n) { x = Math.max(x, nodoX(n) + NODO_W + 30); });

    var ocupado = function (px, py) {
      return estado.nodos.some(function (n) {
        return Math.abs(nodoX(n) - px) < NODO_W && Math.abs(nodoY(n) - py) < NODO_H;
      });
    };
    while (ocupado(x, y) && y < 2000) { y += NODO_H + 24; }

    post('crear_nodo', { id_cadena: estado.idCadena, tipo: tipo, x: x, y: y, nombre: '' })
      .then(function (r) {
        if (!r.ok) { SRF.toast(r.error || 'No se pudo crear el nodo.', 'danger'); return; }
        estado.nodos.push(r.nodo);
        renderTodo();
        // La casilla de salida no tiene nada que configurar: no se abre nada.
        if (r.nodo.tipo !== 'inicio') { abrirModalNodo(r.nodo); }
        else { SRF.toast('Casilla de salida creada. Conéctala al primer nodo.', 'success'); }
      });
  }

  /* ------------------------------------------------------------------------
     MODAL DE NODO
     ------------------------------------------------------------------------ */

  /* ------------------------------------------------------------------------
     PESTAÑAS DEL MODAL DE NODO

     El modal pasó de un único formulario en vertical —tipo, rival, escudo,
     alta de rival, estilo, alta de estilo, once huecos, tabla de dificultad y
     botín, todo seguido— a cinco pasos. No es maquillaje: con todo a la vez no
     había forma de saber qué parte se estaba tocando, y dos formularios de
     rival y dos de estilo convivían en la misma pantalla compitiendo entre sí.

     Las pestañas de partido se DESACTIVAN en un cofre, no se esconden: una
     pestaña que desaparece se lee como un fallo, y una apagada que dice por
     qué, no. Si estabas en una de ellas al cambiar el tipo, te devuelve a
     «Nodo» — quedarse en una pestaña desactivada dejaba el modal en blanco.
     ------------------------------------------------------------------------ */

  function pestanas() {
    return Array.prototype.slice.call(document.querySelectorAll('.editor-pestana'));
  }

  function irAPestana(idPanel) {
    pestanas().forEach(function (tab) {
      var activa = tab.dataset.panel === idPanel;
      tab.setAttribute('aria-selected', activa ? 'true' : 'false');
      // tabindex móvil: dentro de un tablist solo la pestaña activa entra en
      // el orden de tabulación; entre pestañas se navega con las flechas.
      tab.tabIndex = activa ? 0 : -1;
      var panel = document.getElementById(tab.dataset.panel);
      if (panel) panel.hidden = !activa;
    });
  }

  function montarPestanas() {
    pestanas().forEach(function (tab, i, todas) {
      tab.addEventListener('click', function () {
        if (tab.disabled) return;
        irAPestana(tab.dataset.panel);
      });
      tab.addEventListener('keydown', function (e) {
        var paso = e.key === 'ArrowRight' ? 1 : (e.key === 'ArrowLeft' ? -1 : 0);
        if (!paso) return;
        e.preventDefault();
        // se salta las desactivadas en vez de aterrizar en una que no responde
        var j = i;
        do { j = (j + paso + todas.length) % todas.length; } while (todas[j].disabled && j !== i);
        todas[j].focus();
        irAPestana(todas[j].dataset.panel);
      });
    });
  }

  function abrirModalNodo(n) {
    estado.nodoActual = n;

    var TITULO = { cofre: 'Cofre', bloqueo: 'Bloqueo', inicio: 'Casilla de salida', partido: 'Partido' };
    document.getElementById('modalNodoTitulo').textContent =
      (TITULO[n.tipo] || 'Partido') + ' — nodo #' + n.id_nodo;
    document.getElementById('fn_tipo').value = n.tipo;
    document.getElementById('fn_nombre').value = n.nombre || '';
    document.getElementById('fn_es_final').checked = parseInt(n.es_final, 10) === 1;

    var selRival = document.getElementById('fn_rival');
    selRival.innerHTML =
      '<option value="">— Sin rival asignado —</option>' +
      '<option value="nuevo">＋ Crear un rival nuevo…</option>';
    estado.rivales.forEach(function (r) {
      var op = document.createElement('option');
      op.value = r.id_rival; op.textContent = r.nombre;
      selRival.appendChild(op);
    });
    selRival.value = n.id_rival || '';

    // Un bloqueo abre directamente en Requisitos: es lo único que configura, y
    // la pestaña Nodo solo le sirve para el nombre.
    irAPestana(n.tipo === 'bloqueo' ? 'pn-requisitos' : 'pn-nodo');
    alternarTipoNodo();
    cambiarRival();
    renderizarLoot();
    reiniciarSelectorCromoLoot();
    alternarTipoLoot();
    cargarDificultades(n.id_nodo);
    if (n.tipo === 'bloqueo') { cargarRequisitos(n.id_nodo); alternarTipoRequisito(); }

    SRF.abrirModal('modalNodo');
  }

  /* La línea bajo el título: dice de un vistazo en qué estado está el nodo sin
     tener que abrir las cinco pestañas para averiguarlo. */
  function refrescarResumen() {
    var el = document.getElementById('fn_resumen');
    var n = estado.nodoActual;
    if (!el || !n) return;

    var partes = [];
    if (document.getElementById('fn_tipo').value === 'cofre') {
      partes.push('Cofre: no se juega, solo entrega botín');
    } else {
      var idRival = document.getElementById('fn_rival').value;
      var rival = (idRival && idRival !== 'nuevo') ? rivalPorId(idRival) : null;
      partes.push(rival ? 'Contra ' + rival.nombre : 'Sin rival asignado');
      var puestas = Object.keys(estado.porHueco || {}).length;
      if (estado.estiloActual) {
        partes.push(puestas + ' de 11 en el campo');
      }
    }
    var loot = (n.loot || []).length;
    partes.push(loot === 0 ? 'sin botín' : loot + (loot === 1 ? ' botín' : ' botines'));
    el.textContent = partes.join(' · ');
  }

  /* ------------------------------------------------------------------------
     DIFICULTAD POR NODO (migración `029`)

     Cinco filas, una por dificultad. Cada campo en blanco significa "no pisar
     el valor general", NO cero: por eso los `<input>` van vacíos y no a 0, y
     por eso se mandan como cadena vacía. Un 0 escrito a mano SÍ pisa —"en este
     nodo, ninguna subida de rareza aunque el general diga 3"—, que es
     justamente la razón de distinguirlos.
     ------------------------------------------------------------------------ */

  function cargarDificultades(idNodo) {
    var cuerpo = document.getElementById('fn_dificultad_cuerpo');
    if (!cuerpo) return;

    /* Un cofre no se juega: no tiene dificultad que ajustar. De esconder el
       bloque ya se encarga `alternarTipoNodo()` apagando la pestaña; aquí solo
       hay que evitar pedir al servidor unos ajustes que nadie va a ver. */
    if (document.getElementById('fn_tipo').value !== 'partido') {
      cuerpo.innerHTML = '';
      return;
    }

    cargarDificultadesCadena();
    cargarTrampasCadena();
    post('listar_ajustes_nodo', { id_nodo: idNodo }).then(function (r) {
      pintarDificultades(idNodo, (r && r.ajustes) || {});
    });
  }

  function pintarDificultades(idNodo, ajustes) {
    var cuerpo = document.getElementById('fn_dificultad_cuerpo');
    cuerpo.innerHTML = '';

    // Estilos del rival de este nodo, para el desplegable de estilo forzado.
    var idRival = document.getElementById('fn_rival').value;
    var rival = idRival ? rivalPorId(idRival) : null;
    var estilos = (rival && rival.estilos) || [];

    (estado.dificultades || []).forEach(function (dif) {
      var a = ajustes[dif] || {};
      var tr = document.createElement('tr');
      var hay = !!ajustes[dif];

      /* Las dos trampas son de tres estados, no de dos: encendida, apagada, o
         "como el general". Un checkbox no sabe decir la tercera, y ahí está la
         gracia — dejar un nodo sin opinión para que lo mande la configuración
         global es lo normal. */
      function celdaTrampa(campo, valor) {
        var v = (valor === null || valor === undefined) ? '' : String(Number(valor));
        return '<td><select class="campo-inline" data-campo="' + campo + '">'
          + '<option value=""'  + (v === ''  ? ' selected' : '') + '>general</option>'
          + '<option value="1"' + (v === '1' ? ' selected' : '') + '>sí</option>'
          + '<option value="0"' + (v === '0' ? ' selected' : '') + '>no</option>'
          + '</select></td>';
      }

      function celdaNum(campo, paso, minimo, maximo) {
        var v = a[campo];
        return '<td><input type="number" class="campo-inline" data-campo="' + campo + '"'
          + ' step="' + paso + '"' + (minimo !== null ? ' min="' + minimo + '"' : '')
          + (maximo !== null ? ' max="' + maximo + '"' : '')
          + ' value="' + (v === null || v === undefined ? '' : v) + '"'
          + ' placeholder="general"></td>';
      }

      var opcionesEstilo = '<option value="">Al azar</option>';
      estilos.forEach(function (es) {
        opcionesEstilo += '<option value="' + Number(es.id_estilo) + '"'
          + (String(a.id_estilo || '') === String(es.id_estilo) ? ' selected' : '') + '>'
          + escapeHtml(es.nombre) + '</option>';
      });

      tr.dataset.dificultad = dif;
      tr.innerHTML =
        '<th scope="row">' + (ETIQUETA_DIFICULTAD[dif] || dif) + (hay ? ' <span class="pastilla pastilla-warn">ajustado</span>' : '') + '</th>' +
        '<td><label class="interruptor"><input type="checkbox" data-campo="activa"'
          + (a.activa === undefined || Number(a.activa) === 1 ? ' checked' : '')
          + '><span class="interruptor-riel"></span></label></td>' +
        celdaNum('mult_fuerza', '0.001', 0, 10) +
        celdaNum('mult_compos', '0.001', 0, 10) +
        celdaNum('subir_rareza', '1', 0, 5) +
        celdaTrampa('sin_malus', a.sin_malus) +
        celdaTrampa('compos_libres', a.compos_libres) +
        celdaNum('rareza_max', '1', 0, 6) +
        '<td><select class="campo-inline" data-campo="id_estilo">' + opcionesEstilo + '</select></td>' +
        '<td class="row-actions">' +
          '<button type="button" class="btn btn-plano btn-sm" data-guardar>Guardar</button>' +
          (hay ? '<button type="button" class="btn btn-plano btn-sm es-peligro" data-reiniciar>General</button>' : '') +
        '</td>';

      tr.querySelector('[data-guardar]').addEventListener('click', function () {
        guardarFilaDificultad(idNodo, tr);
      });
      var reiniciar = tr.querySelector('[data-reiniciar]');
      if (reiniciar) {
        reiniciar.addEventListener('click', function () {
          SRF.confirmar('¿Devolver esta dificultad a los valores generales?', function () {
            post('borrar_ajuste_nodo', { id_nodo: idNodo, dificultad: dif })
              .then(function () { cargarDificultades(idNodo); });
          });
        });
      }

      cuerpo.appendChild(tr);
    });
  }

  /* ------------------------------------------------------------------------
     DIFICULTADES DE LA CADENA ENTERA

     Un interruptor por dificultad que la enciende o la apaga en todos los
     nodos de partido de la cadena. El estado se lee del servidor porque puede
     estar A MEDIAS —alguien apagó el Extremo solo en dos nodos—, y eso hay
     que verlo: un interruptor que dice "encendido" sobre siete de nueve nodos
     estaría mintiendo.
     ------------------------------------------------------------------------ */
  function cargarDificultadesCadena() {
    var caja = document.getElementById('fn_dif_cadena');
    if (!caja || !estado.idCadena) { return; }

    post('dificultades_cadena', { id_cadena: estado.idCadena }).then(function (r) {
      if (!r.ok) { return; }
      caja.innerHTML = '';

      (estado.dificultades || []).forEach(function (dif) {
        var e = r.estado[dif] || { activos: 0, total: 0 };
        var todos = e.total > 0 && e.activos === e.total;
        var ninguno = e.activos === 0;

        var fila = document.createElement('label');
        fila.className = 'dificultad-cadena';
        fila.innerHTML =
          '<span class="interruptor"><input type="checkbox"' + (todos ? ' checked' : '')
            + '><span class="interruptor-riel"></span></span>'
          + '<span class="dificultad-cadena-nombre">' + (ETIQUETA_DIFICULTAD[dif] || dif) + '</span>'
          + '<span class="t-caption t-dim">'
            + (todos ? 'en los ' + e.total : (ninguno ? 'apagada' : e.activos + ' de ' + e.total))
          + '</span>';

        fila.querySelector('input').addEventListener('change', function () {
          var encender = this.checked;
          post('dificultad_cadena', {
            id_cadena: estado.idCadena, dificultad: dif, activa: encender ? 1 : 0,
          }).then(function (res) {
            if (!res.ok) { SRF.toast(res.error || 'No se pudo cambiar.', 'danger'); }
            else { SRF.toast((encender ? 'Activada' : 'Desactivada') + ' en ' + res.nodos + ' partidos.', 'success'); }
            cargarDificultadesCadena();
            if (estado.nodoActual) { cargarDificultades(estado.nodoActual.id_nodo); }
          });
        });

        caja.appendChild(fila);
      });
    });
  }

  /* ------------------------------------------------------------------------
     LAS TRAMPAS DEL RIVAL, PARA TODA LA CADENA

     Las mismas dos columnas que la tabla de abajo tiene por nodo, pero
     aplicadas de una vez a todos los partidos de la cadena. Sin esto había que
     abrir veinte modales y tocar dos desplegables en cada uno por cada
     dificultad: existía, pero no se usaba.

     Tres estados, como en la tabla: sí / no / "como el general". El tercero no
     es relleno — dejar un nodo sin opinión para que mande la configuración
     global es lo normal, y un interruptor de dos no sabe decirlo.
     ------------------------------------------------------------------------ */
  var TRAMPAS = [
    { columna: 'sin_malus',     titulo: 'Sin malus',     clave: 'malus_si' },
    { columna: 'compos_libres', titulo: 'Compos libres', clave: 'libres_si' },
  ];

  function cargarTrampasCadena() {
    var caja = document.getElementById('fn_trampas_cadena');
    if (!caja || !estado.idCadena) { return; }

    post('trampas_cadena', { id_cadena: estado.idCadena }).then(function (r) {
      if (!r.ok) { return; }
      caja.innerHTML = '';

      (estado.dificultades || []).forEach(function (dif) {
        var e = r.estado[dif] || { total: 0 };

        var fila = document.createElement('div');
        fila.className = 'trampa-fila';
        fila.innerHTML = '<span class="trampa-dif">'
          + (ETIQUETA_DIFICULTAD[dif] || dif) + '</span>';

        TRAMPAS.forEach(function (t) {
          var puestos = Number(e[t.clave] || 0);
          var total = Number(e.total || 0);
          /* Se dice en cuántos nodos está puesta, no un sí/no que mentiría
             cuando los nodos no coinciden entre sí. */
          var resumen = total === 0 ? 'sin partidos'
                      : (puestos === 0 ? 'en ninguno'
                      : (puestos === total ? 'en los ' + total : puestos + ' de ' + total));

          var campo = document.createElement('label');
          campo.className = 'trampa-campo';
          campo.innerHTML = '<span class="trampa-nombre">' + t.titulo + '</span>'
            + '<select class="campo-inline">'
            + '<option value="">general</option>'
            + '<option value="1">sí</option>'
            + '<option value="0">no</option>'
            + '</select>'
            + '<span class="t-caption t-dim">' + resumen + '</span>';

          campo.querySelector('select').addEventListener('change', function () {
            var valor = this.value;
            post('trampa_cadena', {
              id_cadena: estado.idCadena, dificultad: dif,
              columna: t.columna, valor: valor,
            }).then(function (res) {
              if (!res.ok) { SRF.toast(res.error || 'No se pudo cambiar.', 'danger'); }
              else {
                SRF.toast(t.titulo + ': ' + (valor === '' ? 'general' : (valor === '1' ? 'sí' : 'no'))
                  + ' en ' + res.nodos + ' partidos.', 'success');
              }
              cargarTrampasCadena();
              if (estado.nodoActual) { cargarDificultades(estado.nodoActual.id_nodo); }
            });
          });

          fila.appendChild(campo);
        });

        caja.appendChild(fila);
      });
    });
  }

  function guardarFilaDificultad(idNodo, tr) {
    var params = { id_nodo: idNodo, dificultad: tr.dataset.dificultad };
    Array.prototype.forEach.call(tr.querySelectorAll('[data-campo]'), function (el) {
      params[el.dataset.campo] = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value;
    });
    post('guardar_ajuste_nodo', params).then(function (r) {
      if (!r.ok) { window.alert(r.error || 'No se pudo guardar.'); return; }
      cargarDificultades(idNodo);
    });
  }

  /* ------------------------------------------------------------------------
     CARTA EXCLUSIVA DE CADENA (migración `030`)
     Se crea sin salir del editor y aparece al momento en la lista de la
     derecha, lista para arrastrar al campo.
     ------------------------------------------------------------------------ */

  function nuevaCartaCadena() {
    document.getElementById('fc_nombre').value = '';
    document.getElementById('fc_arte').value = '';
    explicarTipoImagen();
    SRF.abrirModal('modalCromoCadena');
  }

  /* Qué va a pasar con la imagen, dicho antes de subirla: es la diferencia
     entre una carta con marco y una sin él, y no se ve hasta que ya está
     creada. */
  function explicarTipoImagen() {
    var sel = document.getElementById('fc_tipo_imagen');
    var hint = document.getElementById('fc_tipo_imagen_hint');
    if (!sel || !hint) { return; }
    hint.textContent = sel.value === 'foto'
      ? 'Se guarda en assets/img/Cromos/Importados/Cadenas/ y saldrá con el marco de su rareza.'
      : 'Se guarda en assets/img/Cromos/Cadenas/ y ocupará la carta entera, sin marco.';
  }

  function alternarStatsCarta() {
    var manual = !document.getElementById('fc_aleatorias').checked;
    Array.prototype.forEach.call(document.querySelectorAll('[data-stats-manual]'), function (el) {
      el.hidden = !manual;
    });
  }

  function crearCartaCadena() {
    var nombre = document.getElementById('fc_nombre').value.trim();
    if (nombre === '') { window.alert('La carta necesita un nombre.'); return; }

    var body = new FormData();
    body.append('accion', 'crear_cromo_cadena');
    body.append('csrf', SRF.csrfToken());
    body.append('nombre', nombre);
    ['posicion', 'rareza', 'equipo', 'expansion', 'afinidad'].forEach(function (c) {
      body.append(c === 'rareza' ? 'id_rareza' : (c === 'posicion' ? 'posicion' : 'id_' + c),
                  document.getElementById('fc_' + c).value);
    });
    var aleatorias = document.getElementById('fc_aleatorias').checked;
    body.append('aleatorias', aleatorias ? 1 : '');
    if (!aleatorias) {
      ['ataque', 'defensa', 'tecnica'].forEach(function (c) {
        body.append(c, document.getElementById('fc_' + c).value);
      });
    }
    var arte = document.getElementById('fc_arte').files[0];
    if (arte) {
      body.append('arte_archivo', arte);
      // Decide la carpeta en el servidor y con ella si la carta lleva marco.
      body.append('tipo_imagen', document.getElementById('fc_tipo_imagen').value);
    }

    fetch(URL_AJAX, { method: 'POST', body: body })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        if (!r.ok) { window.alert(r.error || 'No se pudo crear la carta.'); return; }
        // Entra en el catálogo local para que se pueda arrastrar ya, sin recargar.
        estado.cromos.unshift(r.cromo);
        SRF.cerrarModal('modalCromoCadena');
        pintarListaCromos();
        SRF.toast('Carta exclusiva creada: ' + r.cromo.nombre, 'success');
      });
  }

  function cerrarModalNodo() {
    SRF.cerrarModal('modalNodo');
    estado.nodoActual = null;
  }

  function alternarTipoNodo() {
    var tipo      = document.getElementById('fn_tipo').value;
    var esPartido = tipo === 'partido';
    var esBloqueo = tipo === 'bloqueo';

    pestanas().forEach(function (tab) {
      if ('soloPartido' in tab.dataset) {
        tab.disabled = !esPartido;
        tab.title = esPartido ? '' : 'Ni un cofre ni un bloqueo se juegan contra nadie: no tienen rival, ni equipo, ni dificultad.';
      }
      // Los requisitos son lo único que configura un bloqueo, y no significan
      // nada en los demás: un partido se supera ganándolo, no cumpliendo cosas.
      if ('soloBloqueo' in tab.dataset) {
        tab.disabled = !esBloqueo;
        tab.title = esBloqueo ? '' : 'Solo los nodos de bloqueo piden requisitos.';
      }
    });

    // El botín se reclama abriendo un cofre, y un bloqueo no se abre: se pasa.
    // Dejar la pestaña puesta invitaría a cargarle un premio que no entrega
    // nadie, que es peor que no tenerla.
    var tabBotin = document.getElementById('tab-botin');
    if (tabBotin) {
      tabBotin.disabled = esBloqueo;
      tabBotin.title = esBloqueo ? 'Un bloqueo no se reclama, así que no entrega botín.' : '';
    }

    // Si el tipo cambia mientras se está en una pestaña que acaba de
    // desactivarse, el modal se quedaría enseñando un panel vacío.
    var activa = document.querySelector('.editor-pestana[aria-selected="true"]');
    if (activa && activa.disabled) irAPestana('pn-nodo');

    refrescarResumen();
  }

  function guardarNodo() {
    var n = estado.nodoActual;
    if (!n) return;

    n.tipo = document.getElementById('fn_tipo').value;
    n.nombre = document.getElementById('fn_nombre').value.trim();
    n.es_final = document.getElementById('fn_es_final').checked ? 1 : 0;
    var idRival = document.getElementById('fn_rival').value;
    // «nuevo» es la opción de alta del desplegable, no un id: mandarla al
    // servidor guardaba el nodo con un rival inexistente.
    if (idRival === 'nuevo') idRival = '';
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

  /* ------------------------------------------------------------------------
     RIVAL — UN formulario, no dos.

     Antes había tres bloques: el desplegable, «Escudo de este rival» (para el
     ya elegido) y «Nuevo rival» (con OTRO campo de escudo). Dos formularios
     para los mismos datos, y el de arriba solo dejaba tocar el escudo.

     Ahora el desplegable manda: con un rival elegido, los campos traen sus
     datos y el botón los guarda sobre él; con «Crear uno nuevo» salen en
     blanco y el botón crea. Un solo camino, y de paso el nombre y la
     descripción de un rival existente se pueden corregir, cosa que antes no
     se podía sin ir a otra pantalla.
     ------------------------------------------------------------------------ */

  function pintarEscudoVista(ruta) {
    var vista = document.getElementById('fn_rival_escudo_vista');
    if (!vista) return;
    if (ruta) {
      // La ruta guardada es relativa a la raíz del sitio ("./assets/..."), y
      // desde panel/ ese "./" apunta a panel/assets. Mismo ajuste que hace
      // cadena_editor.php con las miniaturas del selector.
      var src = ruta.charAt(0) === '.' ? '.' + ruta : ruta;
      vista.innerHTML = '<img src="' + escapeHtml(src) + '" alt="">';
    } else {
      vista.innerHTML = '<i class="ph ph-sword" aria-hidden="true"></i>';
    }
  }

  function cambiarRival() {
    var valor = document.getElementById('fn_rival').value;
    var ficha = document.getElementById('fn_rival_ficha');
    var boton = document.getElementById('fn_rival_guardar');
    var esNuevo = valor === 'nuevo';
    var rival = (valor && !esNuevo) ? rivalPorId(valor) : null;

    ficha.hidden = !esNuevo && !rival;
    document.getElementById('fn_rival_escudo_archivo').value = '';

    document.getElementById('fn_rival_nombre').value      = rival ? (rival.nombre || '') : '';
    document.getElementById('fn_rival_descripcion').value = rival ? (rival.descripcion || '') : '';
    document.getElementById('fn_rival_escudo').value      = rival ? (rival.escudo || '') : '';
    pintarEscudoVista(rival ? rival.escudo : '');

    boton.textContent = esNuevo ? 'Crear rival' : 'Guardar rival';

    actualizarBloqueEstilos();
    refrescarResumen();
  }

  /* Crea o actualiza según lo que haya elegido el desplegable. Es una sola
     función a propósito: son los mismos campos y las mismas validaciones, y
     tenerlas por duplicado es como acabaron divergiendo las dos versiones
     anteriores (una pedía nombre, la otra no). */
  function guardarRival() {
    var sel = document.getElementById('fn_rival');
    var esNuevo = sel.value === 'nuevo';
    if (!esNuevo && !sel.value) return;

    var nombre = document.getElementById('fn_rival_nombre').value.trim();
    if (!nombre) { SRF.toast('Ponle un nombre al rival.', 'danger'); return; }

    var archivoInput = document.getElementById('fn_rival_escudo_archivo');
    var archivo = archivoInput.files && archivoInput.files[0];
    var datos = {
      nombre: nombre,
      escudo: document.getElementById('fn_rival_escudo').value.trim(),
      descripcion: document.getElementById('fn_rival_descripcion').value.trim(),
    };
    if (!esNuevo) datos.id_rival = sel.value;

    postConEscudo(esNuevo ? 'crear_rival' : 'actualizar_rival', datos, archivo).then(function (r) {
      if (!r.ok) { SRF.toast(r.error || 'No se pudo guardar el rival.', 'danger'); return; }

      if (esNuevo) {
        r.rival.estilos = [];
        estado.rivales.push(r.rival);
        var op = document.createElement('option');
        op.value = r.rival.id_rival; op.textContent = r.rival.nombre;
        sel.appendChild(op);
        sel.value = r.rival.id_rival;
        SRF.toast('Rival creado: ' + r.rival.nombre, 'success');
      } else {
        var rival = rivalPorId(sel.value);
        if (rival) {
          rival.nombre = nombre;
          rival.descripcion = datos.descripcion;
          // El servidor devuelve la ruta definitiva cuando se ha subido un
          // archivo; si no ha subido nada, vale la que se escribió a mano.
          rival.escudo = r.escudo !== undefined ? r.escudo : datos.escudo;
        }
        sel.selectedOptions[0].textContent = nombre;
        SRF.toast('Rival guardado.', 'success');
      }

      archivoInput.value = '';
      cambiarRival();
      renderTodo();   // el mapa enseña el nombre y el escudo del rival
    });
  }

  function actualizarBloqueEstilos() {
    var idRival = document.getElementById('fn_rival').value;
    var aviso  = document.getElementById('fn_equipo_sin_rival');
    var cuerpo = document.getElementById('fn_equipo_cuerpo');
    alternarCamposEstiloNuevo(false);
    document.getElementById('fn_alineacion').hidden = true;
    vaciarCampo();

    // Con «Crear un rival nuevo…» todavía no hay a quién alinear: la pestaña
    // Equipo se queda con el aviso hasta que el rival exista de verdad.
    var rival = (idRival && idRival !== 'nuevo') ? rivalPorId(idRival) : null;
    aviso.hidden = !!rival;
    cuerpo.hidden = !rival;
    if (!rival) return;

    var selEstilo = document.getElementById('fn_estilo');
    selEstilo.innerHTML =
      '<option value="">— Elige una —</option>' +
      '<option value="nuevo">＋ Crear una nueva…</option>';
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

  /* El alta de alineación sigue el mismo patrón que el rival: no hay un
     bloque «nuevo estilo» aparte, es el propio desplegable el que ofrece
     «Crear una nueva…» y entonces aparecen sus dos campos. */
  function alternarCamposEstiloNuevo(visible) {
    ['fn_estilo_nuevo_nombre_wrap', 'fn_estilo_nuevo_formacion_wrap', 'fn_estilo_crear_wrap']
      .forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.hidden = !visible;
      });
  }

  function crearEstilo() {
    var idRival = document.getElementById('fn_rival').value;
    var nombre = document.getElementById('fn_estilo_nombre').value.trim();
    var formacion = document.getElementById('fn_estilo_formacion').value;
    if (!idRival || idRival === 'nuevo') return;
    if (!nombre) { SRF.toast('Ponle un nombre a la alineación.', 'danger'); return; }

    post('crear_estilo', { id_rival: idRival, nombre: nombre, formacion: formacion }).then(function (r) {
      if (!r.ok) { SRF.toast(r.error || 'No se pudo crear la alineación.', 'danger'); return; }

      var rival = rivalPorId(idRival);
      rival.estilos = rival.estilos || [];
      rival.estilos.push(r.estilo);

      var selEstilo = document.getElementById('fn_estilo');
      var op = document.createElement('option');
      op.value = r.estilo.id_estilo;
      op.textContent = r.estilo.nombre + ' (' + estado.formaciones[r.estilo.formacion].nombre + ')';
      op.dataset.formacion = r.estilo.formacion;
      selEstilo.appendChild(op);
      selEstilo.value = r.estilo.id_estilo;

      document.getElementById('fn_estilo_nombre').value = '';
      alternarCamposEstiloNuevo(false);

      estado.estiloActual = r.estilo.id_estilo;
      construirCampo(r.estilo.formacion, []);
      document.getElementById('fn_alineacion').hidden = false;
      SRF.toast('Alineación creada.', 'success');
    });
  }

  /**
   * Borra la alineación elegida, con sus once cartas.
   *
   * El servidor se niega si hay un partido vivo contra ella: el duelo guarda
   * el id del estilo y quedarse sin él a mitad de encuentro deja el partido
   * sin rival. Los ya resueltos no estorban, que llevan su alineación
   * congelada aparte.
   */
  function borrarEstilo() {
    var sel = document.getElementById('fn_estilo');
    var idEstilo = sel.value;
    if (!idEstilo || idEstilo === 'nuevo') { return; }
    var nombre = sel.selectedOptions[0].textContent;

    SRF.confirmar('¿Borrar la alineación «' + nombre + '»? Se van también sus once jugadores.',
      function () {
        post('borrar_estilo', { id_estilo: idEstilo }).then(function (r) {
          if (!r.ok) { SRF.toast(r.error || 'No se pudo borrar.', 'danger'); return; }

          var rival = rivalPorId(document.getElementById('fn_rival').value);
          if (rival && rival.estilos) {
            rival.estilos = rival.estilos.filter(function (e) {
              return String(e.id_estilo) !== String(idEstilo);
            });
          }
          sel.selectedOptions[0].remove();
          sel.value = '';
          cambiarEstilo();
          SRF.toast('Alineación borrada.', 'success');
        });
      });
  }

  function cambiarEstilo() {
    var sel = document.getElementById('fn_estilo');
    var idEstilo = sel.value;
    var campoWrap = document.getElementById('fn_alineacion');

    alternarCamposEstiloNuevo(idEstilo === 'nuevo');
    var borrarWrap = document.getElementById('fn_estilo_borrar_wrap');
    if (borrarWrap) { borrarWrap.hidden = !idEstilo || idEstilo === 'nuevo'; }

    if (!idEstilo || idEstilo === 'nuevo') {
      vaciarCampo();
      campoWrap.hidden = true;
      refrescarResumen();
      return;
    }

    var formacion = sel.selectedOptions[0].dataset.formacion;
    estado.estiloActual = idEstilo;
    campoWrap.hidden = false;
    post('listar_cartas_estilo', { id_estilo: idEstilo }).then(function (r) {
      construirCampo(formacion, r.cartas || []);
      refrescarResumen();
    });
  }

  /* Sin estilo elegido no hay campo que pintar: se limpia todo para que no
     quede una alineación de otro estilo a la vista, que es la forma más fácil
     de asignar cartas al equipo equivocado. */
  function vaciarCampo() {
    estado.estiloActual = null;
    estado.formacionActual = null;
    estado.porHueco = {};
    estado.huecoActivo = null;
    var campo = document.getElementById('fn_campo');
    var lista = document.getElementById('fn_c_lista');
    if (campo) campo.innerHTML = '';
    if (lista) lista.innerHTML = '';
  }

  /* ------------------------------------------------------------------------
     ALINEACIÓN DEL RIVAL SOBRE EL CAMPO

     Sustituye a la tabla de once `<select>` que había antes, donde cada uno
     repetía el catálogo entero y no se veía en ningún momento qué equipo se
     estaba montando.

     Dos formas de colocar, no una: arrastrar la carta al hueco, o pulsar el
     hueco y luego la carta. Lo segundo no es un extra — es lo que hace que
     esto se pueda usar con teclado, y la regla del proyecto es que nada
     dependa de un gesto de arrastre.
     ------------------------------------------------------------------------ */

  function cromoPorId(id) {
    id = String(id);
    for (var i = 0; i < estado.cromos.length; i++) {
      if (String(estado.cromos[i].id_cromo) === id) return estado.cromos[i];
    }
    return null;
  }

  function construirCampo(formacion, cartasExistentes) {
    estado.formacionActual = formacion;
    estado.porHueco = {};
    (cartasExistentes || []).forEach(function (c) { estado.porHueco[c.hueco] = c.id_cromo; });
    estado.huecoActivo = null;
    pintarCampo();
    pintarListaCromos();
  }

  function pintarCampo() {
    var campo = document.getElementById('fn_campo');
    var huecos = estado.huecosPorFormacion[estado.formacionActual] || [];
    var coords = estado.coordsPorFormacion[estado.formacionActual] || [];
    campo.innerHTML = '';

    huecos.forEach(function (linea, i) {
      var idCromo = estado.porHueco[i];
      var cromo = idCromo ? cromoPorId(idCromo) : null;

      var div = document.createElement('div');
      div.className = 'hueco' + (cromo ? ' esta-lleno' : '')
        + (cromo && cromo.posicion !== linea ? ' es-desubicado' : '')
        + (estado.huecoActivo === i ? ' es-activo' : '');
      div.style.left = coords[i].x + '%';
      div.style.top = coords[i].y + '%';
      div.dataset.hueco = i;
      div.dataset.linea = linea;
      if (cromo) div.dataset.rareza = cromo.id_rareza;

      var boton = document.createElement('button');
      boton.type = 'button';
      boton.className = 'hueco-boton';
      boton.setAttribute('aria-label', 'Hueco de ' + (ETIQUETA_LINEA[linea] || linea)
        + (cromo ? ': ' + cromo.nombre : ', vacío'));

      var avatar = document.createElement('span');
      avatar.className = 'hueco-avatar';
      var interior = document.createElement('span');
      interior.className = 'hueco-avatar-int';
      if (cromo && cromo.imagen) {
        var img = document.createElement('img');
        // Las rutas de BD son relativas a la raíz del sitio ("./assets/…").
        // Desde panel/ hay que subir un nivel — mismo ajuste que ya hace
        // cadena_editor.php con las miniaturas del botín.
        img.src = cromo.imagen.charAt(0) === '.' ? '.' + cromo.imagen : cromo.imagen;
        img.alt = '';
        interior.appendChild(img);
      } else {
        interior.innerHTML = '<i class="ph ph-' + (cromo ? 'user' : 'plus') + '" aria-hidden="true"></i>';
      }
      avatar.appendChild(interior);
      boton.appendChild(avatar);

      var nombre = document.createElement('span');
      nombre.className = 'hueco-nombre';
      nombre.textContent = cromo ? cromo.nombre : (ETIQUETA_LINEA[linea] || linea);
      boton.appendChild(nombre);

      div.appendChild(boton);

      if (cromo) {
        var quitar = document.createElement('button');
        quitar.type = 'button';
        quitar.className = 'hueco-quitar';
        quitar.setAttribute('aria-label', 'Quitar a ' + cromo.nombre);
        quitar.innerHTML = '<i class="ph ph-x" aria-hidden="true"></i>';
        quitar.addEventListener('click', function (e) {
          e.stopPropagation();
          asignarAHueco(i, '');
        });
        div.appendChild(quitar);
      }

      boton.addEventListener('click', function () {
        estado.huecoActivo = i;
        pintarCampo();
      });

      /* Zona de soltado. `dragover` tiene que llamar a preventDefault() o el
         navegador no considera el elemento un destino válido y nunca dispara
         `drop` — es el fallo clásico de la API. */
      div.addEventListener('dragover', function (e) {
        e.preventDefault();
        div.classList.add('es-destino');
      });
      div.addEventListener('dragleave', function () { div.classList.remove('es-destino'); });
      div.addEventListener('drop', function (e) {
        e.preventDefault();
        div.classList.remove('es-destino');
        var id = e.dataTransfer.getData('text/plain');
        if (id) asignarAHueco(i, id);
      });

      campo.appendChild(div);
    });
  }

  function asignarAHueco(hueco, idCromo) {
    if (!estado.estiloActual) return;
    if (idCromo === '') { delete estado.porHueco[hueco]; }
    else { estado.porHueco[hueco] = idCromo; }

    post('asignar_carta', {
      id_estilo: estado.estiloActual,
      hueco: hueco,
      id_cromo: idCromo === '' ? 0 : idCromo,
    });

    /* Salta solo al siguiente hueco libre: rellenar once a mano es tedioso si
       hay que volver a señalar el sitio cada vez. Mismo criterio que mazos.js. */
    var huecos = estado.huecosPorFormacion[estado.formacionActual] || [];
    estado.huecoActivo = null;
    for (var i = 0; i < huecos.length; i++) {
      if (!estado.porHueco[i]) { estado.huecoActivo = i; break; }
    }

    pintarCampo();
    pintarListaCromos();
    refrescarResumen();
  }

  /* ---- lista de cromos con filtros ---- */
  function pintarListaCromos() {
    var lista = document.getElementById('fn_c_lista');
    if (!lista) return;

    var texto = (document.getElementById('fn_c_buscar').value || '').trim().toLowerCase();
    var filtros = Array.prototype.slice.call(document.querySelectorAll('.fn-c-filtro'));
    var puestos = {};
    Object.keys(estado.porHueco).forEach(function (h) { puestos[estado.porHueco[h]] = true; });

    var visibles = estado.cromos.filter(function (c) {
      if (texto && (c.nombre || '').toLowerCase().indexOf(texto) === -1
                && (c.equipo || '').toLowerCase().indexOf(texto) === -1) return false;
      return filtros.every(function (sel) {
        if (sel.value === '') return true;
        var campo = sel.dataset.campo;
        var valor = campo === 'rareza' ? String(c.id_rareza) : String(c[campo] || '');
        return valor === sel.value;
      });
    });

    document.getElementById('fn_c_conteo').textContent =
      visibles.length + (visibles.length === 1 ? ' carta' : ' cartas');

    lista.innerHTML = '';
    visibles.slice(0, 200).forEach(function (c) {
      lista.appendChild(filaCromo(c, !!puestos[c.id_cromo]));
    });
    if (visibles.length > 200) {
      var aviso = document.createElement('li');
      aviso.className = 'editor-cromos-aviso';
      aviso.textContent = 'Se muestran 200 de ' + visibles.length + '. Afina los filtros para ver el resto.';
      lista.appendChild(aviso);
    }
    if (!visibles.length) {
      var vacio = document.createElement('li');
      vacio.className = 'editor-cromos-aviso';
      vacio.textContent = 'Ninguna carta coincide con esos filtros.';
      lista.appendChild(vacio);
    }
  }

  /* Fila de cromo. Se monta en JS y no con el componente PHP porque la lista
     se repinta con cada tecla del buscador; el marcado replica el de
     `render_carta_fila()` para que herede su CSS sin una hoja aparte. */
  function filaCromo(c, yaPuesto) {
    var li = document.createElement('li');
    li.className = 'carta-fila carta-fila--accion' + (yaPuesto ? ' esta-elegida' : '');
    li.dataset.rareza = c.id_rareza;
    li.dataset.cromo = c.id_cromo;
    li.draggable = true;
    /* ⚠️ AQUÍ ESTABA EL FALLO DE «EL ARRASTRE NO FUNCIONA».
       El `draggable` iba solo en el `<li>`, pero dentro hay un `<button>` que
       lo cubre ENTERO. Un botón es un control de formulario: el navegador se
       queda con el gesto del ratón para activarlo y NO lo convierte en un
       arrastre, aunque su padre sea arrastrable. O sea que solo se podía
       agarrar la fila por los pocos píxeles de borde que el botón no tapaba —
       que en la práctica es "no se puede arrastrar".
       Se marca también el botón, unas líneas más abajo, en cuanto existe. */

    var ruta = c.imagen ? (c.imagen.charAt(0) === '.' ? '.' + c.imagen : c.imagen) : '';
    li.innerHTML =
      '<button type="button" class="carta-fila-interior">' +
        '<span class="carta-fila-miniatura">' +
          (ruta ? '<img src="' + escapeHtml(ruta) + '" alt="" loading="lazy">'
                : '<i class="ph ph-image-square" aria-hidden="true"></i>') +
        '</span>' +
        '<span class="carta-fila-jugador">' +
          '<span class="carta-fila-nombre">' + escapeHtml(c.nombre) + '</span>' +
          '<span class="carta-fila-meta">' + escapeHtml(c.equipo || '') +
            (Number(c.solo_cadena) ? ' · exclusiva' : '') + '</span>' +
        '</span>' +
        '<span class="carta-fila-pos">' +
          '<span class="pastilla">' + escapeHtml(c.posicion) + '</span>' +
          '<span class="rz" data-rareza="' + Number(c.id_rareza) + '">' +
            '<span class="rz-texto">' + escapeHtml(c.rareza || '') + '</span></span>' +
        '</span>' +
        '<span class="carta-fila-stats">' +
          statChip('ata', 'ph-sword', 'Ataque', c.ataque) +
          statChip('def', 'ph-shield', 'Defensa', c.defensa) +
          statChip('tec', 'ph-lightning', 'Técnica', c.tecnica) +
        '</span>' +
      '</button>';

    // el botón interior también arrastra: ver la nota de arriba
    var interior = li.querySelector('.carta-fila-interior');
    interior.draggable = true;

    function empezarArrastre(e) {
      e.dataTransfer.setData('text/plain', String(c.id_cromo));
      e.dataTransfer.effectAllowed = 'copy';
      li.classList.add('se-arrastra');
    }
    li.addEventListener('dragstart', empezarArrastre);
    interior.addEventListener('dragstart', empezarArrastre);

    function finArrastre() { li.classList.remove('se-arrastra'); }
    li.addEventListener('dragend', finArrastre);
    interior.addEventListener('dragend', finArrastre);

    /* Pulsar coloca en el hueco activo. Es el camino de teclado y también el
       más rápido con ratón cuando ya sabes dónde va. */
    interior.addEventListener('click', function () {
      var hueco = estado.huecoActivo;
      if (hueco === null || hueco === undefined) {
        var huecos = estado.huecosPorFormacion[estado.formacionActual] || [];
        for (var i = 0; i < huecos.length; i++) {
          if (!estado.porHueco[i]) { hueco = i; break; }
        }
      }
      if (hueco === null || hueco === undefined) return;
      asignarAHueco(hueco, c.id_cromo);
    });

    return li;
  }

  function statChip(mod, icono, largo, valor) {
    return '<span class="carta-fila-stat carta-fila-stat--' + mod + '">' +
      '<i class="ph-fill ' + icono + '" aria-hidden="true"></i>' +
      '<b class="mono">' + (Number(valor) || 0) + '</b>' +
      '<span class="sr-only">' + largo + '</span></span>';
  }

  /* ------------------------------------------------------------------------
     BOTÍN del nodo
     ------------------------------------------------------------------------ */

  /* Selector visual del cromo del botín: el catálogo entero no cabe en un
     <select> legible (mismo problema que se resolvió en mercado.php, "Elige
     la carta"), así que aquí se busca y se elige viendo la carta. El
     marcado ya sale de cadena_editor.php con TODOS los cromos —esto solo
     busca dentro de lo ya pintado, no repuebla nada. */
  function selectorCromoLoot() {
    var buscar = document.getElementById('fl_buscar_cromo');
    var lista  = document.getElementById('fl_lista_cromos');
    if (!buscar || !lista) return;

    var oculto = document.getElementById('fl_cromo');
    var conteo = document.getElementById('fl_conteo_cromo');
    var vacio  = lista.querySelector('.selector-vacio');
    var items  = lista.querySelectorAll('.selector-item');
    var total  = items.length;

    function textoConteo(n) {
      return n + (n === 1 ? ' cromo' : ' cromos');
    }

    lista.addEventListener('change', function (e) {
      if (!e.target.matches('input[type="radio"]')) return;
      Array.prototype.forEach.call(items, function (item) {
        var marcado = item.contains(e.target);
        item.classList.toggle('esta-elegida', marcado);
        var carta = item.querySelector('.carta');
        if (carta) carta.classList.toggle('is-seleccionada', marcado);
      });
      oculto.value = e.target.value;
    });

    var temporizador = null;
    buscar.addEventListener('input', function () {
      clearTimeout(temporizador);
      temporizador = setTimeout(function () {
        var texto = buscar.value.trim().toLowerCase();
        var visibles = 0;
        Array.prototype.forEach.call(items, function (item) {
          var d = item.dataset;
          var coincide = texto === '' ||
            (d.nombre + ' ' + d.equipo + ' ' + d.rarezaNombre).toLowerCase().indexOf(texto) !== -1;
          item.hidden = !coincide;
          if (coincide) visibles++;
        });
        vacio.hidden = visibles !== 0;
        conteo.textContent = texto === '' ? textoConteo(total) : textoConteo(visibles);
      }, 160);
    });
  }

  /* Se llama cada vez que se abre el modal de un nodo: limpia la elección
     (y la búsqueda) que pudiera quedar de la entrada de botín anterior. */
  function reiniciarSelectorCromoLoot() {
    var buscar = document.getElementById('fl_buscar_cromo');
    var lista  = document.getElementById('fl_lista_cromos');
    var oculto = document.getElementById('fl_cromo');
    if (!buscar || !lista) return;

    buscar.value = '';
    oculto.value = '';
    Array.prototype.forEach.call(lista.querySelectorAll('.selector-item'), function (item) {
      item.hidden = false;
      item.classList.remove('esta-elegida');
      var radio = item.querySelector('input[type="radio"]');
      if (radio) radio.checked = false;
      var carta = item.querySelector('.carta');
      if (carta) carta.classList.remove('is-seleccionada');
    });
    lista.querySelector('.selector-vacio').hidden = true;
    document.getElementById('fl_conteo_cromo').textContent =
      lista.querySelectorAll('.selector-item').length + ' cromos';
  }

  function alternarTipoLoot() {
    var tipo = document.getElementById('fl_tipo').value;
    document.getElementById('fl_grupo_monedas').hidden = tipo !== 'monedas';
    document.getElementById('fl_grupo_cromo').hidden = tipo === 'monedas';
  }

  function renderizarLoot() {
    var n = estado.nodoActual;
    var cont = document.getElementById('fn_loot_lista');
    if (!n.loot || !n.loot.length) {
      cont.innerHTML = '<p class="t-caption t-dim">Sin botín todavía. Lo que añadas aquí se entrega al superar el nodo.</p>';
      return;
    }

    cont.innerHTML = '';
    cont.appendChild(resumenLoot(n.loot));
    n.loot.forEach(function (l) { cont.appendChild(filaLoot(l)); });
  }

  /* ------------------------------------------------------------------------
     EL BOTÍN, EN CRISTIANO

     Antes era una tabla con el enum crudo ('cromo_limitado'), un porcentaje y
     una letra suelta en la columna de rango. Con eso no se puede juzgar un
     nodo: hay que multiplicar y sumar a mano, y encima acordarse de que cada
     premio SE TIRA POR SEPARADO —no es una ruleta de la que sale uno—, así que
     tres cartas al 50 % no son "el 150 %", son el 87,5 % de sacar alguna.

     Ahora cada premio se lee de un vistazo y arriba va lo único que de verdad
     contesta "¿esto reparte mucho?": las monedas ESPERADAS y la probabilidad
     de sacar alguna carta, rango por rango. Los números los calcula el
     servidor (`resumenLootNodo`), con el mismo filtro que usa el reparto real.
     ------------------------------------------------------------------------ */

  var ETIQUETA_TIPO_LOOT = {
    monedas: 'Monedas',
    cromo: 'Carta',
    cromo_limitado: 'Carta numerada',
  };
  var ICONO_TIPO_LOOT = {
    monedas: 'ph-coins',
    cromo: 'ph-cards',
    cromo_limitado: 'ph-seal-check',
  };

  /** "solo con rango A o mejor", o "siempre que se supere". */
  function condicionLoot(rango) {
    if (!rango) { return 'Siempre que se supere el nodo'; }
    return rango === 'S' ? 'Solo ganando con rango S'
                         : 'Solo con rango ' + rango + ' o mejor';
  }

  function resumenLoot(loot) {
    var caja = document.createElement('div');
    caja.className = 'loot-resumen';
    caja.innerHTML = '<p class="loot-resumen-titulo">Lo que reparte este nodo</p>'
      + '<div class="loot-resumen-filas" id="fn_loot_resumen">'
      + '<p class="t-caption t-dim">Calculando…</p></div>';

    post('resumen_loot', { id_nodo: estado.nodoActual.id_nodo }).then(function (r) {
      var dentro = caja.querySelector('#fn_loot_resumen');
      if (!r.ok || !dentro) { return; }

      dentro.innerHTML = ['S', 'A', 'B'].map(function (rg) {
        var x = r.resumen[rg] || { premios: 0, monedas_media: 0, prob_carta: 0 };
        return '<div class="loot-resumen-rango">'
             + '<span class="loot-rango-letra loot-rango-' + rg + '">' + rg + '</span>'
             + '<span class="loot-resumen-dato"><b>' + x.monedas_media.toLocaleString('es-ES')
               + '</b> monedas de media</span>'
             + '<span class="loot-resumen-dato"><b>' + x.prob_carta + ' %</b> de sacar alguna carta</span>'
             + '<span class="t-caption t-dim">' + x.premios
               + (x.premios === 1 ? ' premio' : ' premios') + ' en juego</span>'
             + '</div>';
      }).join('');
    });

    return caja;
  }

  function filaLoot(l) {
    var fila = document.createElement('div');
    fila.className = 'loot-fila';

    var que = l.tipo === 'monedas'
      ? Number(l.monedas).toLocaleString('es-ES') + ' monedas'
      : (l.cromo_nombre || ('carta #' + l.id_cromo));

    var prob = Number(l.probabilidad);

    fila.innerHTML =
      '<span class="loot-fila-ico"><i class="ph ' + (ICONO_TIPO_LOOT[l.tipo] || 'ph-gift') + '"></i></span>' +
      '<div class="loot-fila-texto">' +
        '<p class="loot-fila-que">' + escapeHtml(que) + '</p>' +
        '<p class="loot-fila-cuando">' + (ETIQUETA_TIPO_LOOT[l.tipo] || l.tipo) + ' · ' +
          escapeHtml(condicionLoot(l.rango_minimo)) + '</p>' +
      '</div>' +
      '<div class="loot-fila-prob">' +
        '<span class="loot-prob-barra"><span style="width:' + prob + '%"></span></span>' +
        '<span class="mono">' + prob.toFixed(prob % 1 === 0 ? 0 : 2) + ' %</span>' +
      '</div>' +
      '<button type="button" class="icon-btn es-peligro" title="Quitar este premio">' +
        '<i class="ph ph-trash" aria-hidden="true"></i></button>';

    fila.querySelector('button').addEventListener('click', function () { eliminarLoot(l.id_loot); });
    return fila;
  }

  function crearLoot() {
    var n = estado.nodoActual;
    var tipo = document.getElementById('fl_tipo').value;
    var idCromo = document.getElementById('fl_cromo').value;
    var itemElegido = document.querySelector('#fl_lista_cromos .selector-item.esta-elegida');
    var cromoTexto = itemElegido ? itemElegido.dataset.nombre : '';

    // Con el <select> de antes siempre había un cromo preseleccionado (el
    // primero de la lista); con el selector visual puede no haber ninguno
    // elegido todavía, y eso no puede llegar al servidor como "sin cromo".
    if (tipo !== 'monedas' && !idCromo) {
      SRF.toast('Elige primero un cromo de la lista.', 'danger');
      document.getElementById('fl_buscar_cromo').focus();
      return;
    }

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
      reiniciarSelectorCromoLoot();
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

  /* ------------------------------------------------------------------------
     REQUISITOS DE UN NODO DE BLOQUEO (`045`)

     El STOP del mapa. Se leen del servidor al abrir el nodo y se repintan
     enteros tras cada alta o baja: son pocas filas y así la lista nunca puede
     discrepar de lo que hay guardado, que es el fallo típico de mantener una
     copia local "optimista" como sí hace el botín.
     ------------------------------------------------------------------------ */

  /* Qué campo usa cada tipo. Los que no salen aquí van con `fr_numero`, que es
     el caso mayoritario ("cuántos"). Es el mismo criterio que en el botín:
     cada tipo lee SU campo y no un `valor` compartido que el servidor tendría
     que adivinar. */
  var CAMPO_REQUISITO = {
    rango_previos: 'rango',
    cadena: 'cadena',
    cromo: 'cromo',
    rareza: 'rareza',
  };

  /* El texto del "cuántos" cambia bastante según el tipo, y una etiqueta que no
     dice la unidad ("40" ¿de qué?) es la que hace que se configure mal. */
  var ETIQUETA_NUMERO = {
    nodos_cadena: 'Cuántos partidos distintos',
    victorias: 'Cuántas victorias',
    goles_partido: 'Cuántos goles en un mismo partido',
    porteria_cero: 'Cuántos partidos sin encajar',
    nivel_album: 'Porcentaje del álbum',
    monedas: 'Cuántas monedas',
    duelos: 'Cuántos duelos jugados',
    rareza: 'Cuántas cartas de esa rareza',
  };

  /* La dificultad solo significa algo en lo que se juega DENTRO de la cadena.
     Tener monedas o cartas no se hace en ninguna dificultad. */
  var USA_DIFICULTAD = ['rango_previos', 'nodos_cadena', 'victorias', 'goles_partido', 'porteria_cero'];

  function alternarTipoRequisito() {
    var tipo  = document.getElementById('fr_tipo').value;
    var campo = CAMPO_REQUISITO[tipo] || 'numero';

    ['rango', 'numero', 'cadena', 'cromo', 'rareza'].forEach(function (c) {
      var g = document.getElementById('fr_grupo_' + c);
      if (!g) return;
      // `rareza` necesita ADEMÁS el número (cuántas cartas de esa rareza).
      g.hidden = !(c === campo || (c === 'numero' && (campo === 'numero' || tipo === 'rareza')));
    });

    var etiqueta = document.getElementById('fr_numero_label');
    if (etiqueta) { etiqueta.textContent = ETIQUETA_NUMERO[tipo] || 'Cuántos'; }

    var grupoDif = document.getElementById('fr_grupo_dificultad');
    if (grupoDif) { grupoDif.hidden = USA_DIFICULTAD.indexOf(tipo) === -1; }
  }

  function cargarRequisitos(idNodo) {
    post('listar_requisitos_nodo', { id_nodo: idNodo }).then(function (r) {
      if (!r.ok) return;
      renderizarRequisitos(r.requisitos || []);
    });
  }

  /* La frase de cada requisito, tal cual la va a leer quien monta la cadena.
     No se reutiliza la del servidor a propósito: aquella lleva el progreso de
     UN jugador ("llevas 2"), y aquí no hay jugador ninguno. */
  function textoRequisito(r) {
    var RANGOS = { 1: 'S', 2: 'A o mejor', 3: 'B o mejor' };
    var v = parseInt(r.valor, 10);
    var dif = r.dificultad ? ' · solo en ' + r.dificultad.replace('_', ' ') : '';

    var frase;
    switch (r.tipo) {
      case 'rango_previos': frase = 'Superar lo anterior en rango ' + (RANGOS[v] || v); break;
      case 'nodos_cadena':  frase = 'Ganar ' + v + ' partidos distintos de la cadena'; break;
      case 'victorias':     frase = 'Ganar ' + v + ' partidos de la cadena'; break;
      case 'goles_partido': frase = 'Meter ' + v + ' goles en un mismo partido'; break;
      case 'porteria_cero': frase = 'Ganar ' + v + ' partidos sin encajar'; break;
      case 'nivel_album':   frase = 'Tener el álbum al ' + v + ' %'; break;
      case 'monedas':       frase = 'Tener ' + v + ' monedas'; break;
      case 'duelos':        frase = 'Haber jugado ' + v + ' duelos'; break;
      case 'cadena':        frase = 'Completar la cadena #' + v; break;
      case 'cromo':         frase = 'Tener el cromo #' + v; break;
      case 'rareza':        frase = 'Tener ' + (r.cantidad || 1) + ' cartas de la rareza #' + v; break;
      default:              frase = r.tipo;
    }
    return frase + dif;
  }

  function renderizarRequisitos(lista) {
    var cont = document.getElementById('fn_req_lista');
    if (!cont) return;

    if (!lista.length) {
      cont.innerHTML = '<p class="t-caption t-dim">Sin requisitos: este bloqueo deja pasar a cualquiera. '
        + 'Añade abajo lo que quieras exigir.</p>';
      return;
    }

    cont.innerHTML = '';
    var ul = document.createElement('ul');
    ul.className = 'editor-lista-requisitos';

    lista.forEach(function (r) {
      var li = document.createElement('li');
      li.className = 'editor-requisito';

      var txt = document.createElement('span');
      txt.textContent = textoRequisito(r);

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-ghost btn-sm';
      btn.innerHTML = '<i class="ph ph-trash" aria-hidden="true"></i>';
      btn.title = 'Quitar este requisito';
      btn.addEventListener('click', function () { eliminarRequisito(r.id_requisito); });

      li.appendChild(txt);
      li.appendChild(btn);
      ul.appendChild(li);
    });

    cont.appendChild(ul);
  }

  function crearRequisito() {
    var n = estado.nodoActual;
    if (!n) return;

    var tipo  = document.getElementById('fr_tipo').value;
    var campo = CAMPO_REQUISITO[tipo] || 'numero';

    var valor;
    if (campo === 'rango')       { valor = document.getElementById('fr_rango').value; }
    else if (campo === 'cadena') { valor = document.getElementById('fr_cadena').value; }
    else if (campo === 'cromo')  { valor = document.getElementById('fr_cromo').value; }
    else if (campo === 'rareza') { valor = document.getElementById('fr_rareza').value; }
    else                         { valor = document.getElementById('fr_numero').value; }

    if (!valor) {
      SRF.toast('Rellena el valor del requisito.', 'danger');
      return;
    }

    post('crear_requisito_nodo', {
      id_nodo: n.id_nodo,
      tipo: tipo,
      valor: valor,
      cantidad: tipo === 'rareza' ? document.getElementById('fr_numero').value : '',
      dificultad: USA_DIFICULTAD.indexOf(tipo) === -1 ? '' : document.getElementById('fr_dificultad').value,
    }).then(function (r) {
      if (!r.ok) { SRF.toast(r.error || 'No se pudo añadir el requisito.', 'danger'); return; }
      renderizarRequisitos(r.requisitos || []);
    });
  }

  function eliminarRequisito(idRequisito) {
    var n = estado.nodoActual;
    if (!n) return;
    SRF.confirmar('¿Quitar este requisito?', function () {
      post('eliminar_requisito_nodo', { id_requisito: idRequisito, id_nodo: n.id_nodo })
        .then(function (r) {
          if (!r.ok) return;
          renderizarRequisitos(r.requisitos || []);
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
    /* Un solo método para el rival —crea o guarda según el desplegable— y otro
       para la alineación. Antes había cuatro: crear/mostrar-alta por cada uno,
       más `guardarEscudoRival`, que era el mismo guardado recortado a un campo. */
    guardarRival: guardarRival,
    cambiarRival: cambiarRival,
    // Requisitos del nodo de bloqueo (`045`)
    alternarTipoRequisito: alternarTipoRequisito,
    crearRequisito: crearRequisito,
    crearEstilo: crearEstilo,
    borrarEstilo: borrarEstilo,
    explicarTipoImagen: explicarTipoImagen,
    cambiarEstilo: cambiarEstilo,
    alternarTipoLoot: alternarTipoLoot,
    crearLoot: crearLoot,
    nuevaCartaCadena: nuevaCartaCadena,
    alternarStatsCarta: alternarStatsCarta,
    crearCartaCadena: crearCartaCadena,
  };

  /* ------------------------------------------------------------------------
     CALIBRAR LA CADENA ENTERA (migración `033`)

     Cada nodo se mide contra el MISMO jugador de referencia, así que todos los
     Extremos de la cadena acaban pidiendo lo mismo aunque sus alineaciones
     rivales sean muy distintas. Con un multiplicador común no pasaría eso: un
     Extremo contra un equipo flojo se gana y el mismo Extremo contra uno bueno
     es imposible, y la etiqueta deja de significar nada.

     Tarda segundos —se simulan miles de partidos con el motor real—, de ahí
     que el botón se bloquee y lo diga.
     ------------------------------------------------------------------------ */
  /* Calibrar UN nodo, desde su pestaña de dificultad. Misma máquina que la de
     la cadena entera; lo que cambia es el alcance. Al terminar se repinta la
     tabla de dificultad para que se vea el resultado en el sitio donde luego
     se puede retocar a mano. */
  function montarCalibracionNodo() {
    var boton = document.getElementById('btnCalibrarNodo');
    if (!boton) return;

    var aviso = document.getElementById('calibrarNodoEstado');

    Array.prototype.forEach.call(document.querySelectorAll('input[name="nodo_preset"]'), function (r) {
      r.addEventListener('change', function () {
        Array.prototype.forEach.call(
          document.querySelectorAll('#pn-dificultad .calibracion-preset'), function (l) {
            var i = l.querySelector('input');
            l.classList.toggle('esta-elegido', !!i && i.checked);
          });
      });
    });

    boton.addEventListener('click', function () {
      var n = estado.nodoActual;
      var elegido = document.querySelector('input[name="nodo_preset"]:checked');
      if (!n || !elegido) return;

      boton.disabled = true;
      aviso.textContent = 'Simulando partidos contra este rival… unos segundos.';

      post('calibrar_pve_nodo', { id_nodo: n.id_nodo, preset: elegido.value })
        .then(function (r) {
          boton.disabled = false;
          if (!r.ok) { aviso.textContent = r.error || 'No se pudo calibrar.'; return; }

          /* Se dice el porcentaje MEDIDO del Extremo, que es el que se pactó y
             el que de verdad distingue un preset de otro. La tabla de abajo se
             recarga sola con los cinco multiplicadores ya escritos. */
          var ext = r.dificultades.extremo;
          aviso.textContent = 'Listo. En Extremo se gana el '
            + (ext.medido * 100).toFixed(1).replace('.', ',') + ' %.';
          cargarDificultades(n.id_nodo);
        })
        .catch(function () {
          boton.disabled = false;
          aviso.textContent = 'No se pudo calibrar: la petición falló.';
        });
    });
  }

  function montarCalibracion() {
    var boton = document.getElementById('btnCalibrarCadena');
    if (!boton) return;

    /* `aviso`, no `estado`: `estado` es el objeto con todo el estado del
       editor —incluido `idCadena`— y declarar aquí una variable con ese nombre
       lo tapaba dentro de esta función. El envío salía con `id_cadena:
       undefined`, el servidor lo leía como 0 y devolvía "0 nodos calibrados"
       tan campante, sin error que mirar. */
    var aviso  = document.getElementById('calibrarEstado');
    var salida = document.getElementById('calibrarResultado');

    Array.prototype.forEach.call(document.querySelectorAll('input[name="cal_preset"]'), function (r) {
      r.addEventListener('change', function () {
        Array.prototype.forEach.call(document.querySelectorAll('.calibracion-preset'), function (l) {
          var i = l.querySelector('input');
          l.classList.toggle('esta-elegido', !!i && i.checked);
        });
      });
    });

    boton.addEventListener('click', function () {
      var elegido = document.querySelector('input[name="cal_preset"]:checked');
      if (!elegido) return;

      boton.disabled = true;
      salida.innerHTML = '';
      aviso.textContent = 'Simulando partidos nodo a nodo… esto tarda unos segundos.';

      post('calibrar_pve_cadena', { id_cadena: estado.idCadena, preset: elegido.value })
        .then(function (r) {
          boton.disabled = false;
          if (!r.ok) { aviso.textContent = r.error || 'No se pudo calibrar.'; return; }

          aviso.textContent = r.nodos.length + (r.nodos.length === 1 ? ' nodo calibrado.' : ' nodos calibrados.');

          /* Los nodos SALTADOS se dicen uno a uno. Un nodo sin alineación rival
             no se puede medir —no hay contra qué simular— y callárselo dejaría
             agujeros de dificultad sin que nadie se entere. */
          if (r.saltados.length) {
            var ul = document.createElement('ul');
            ul.className = 'calibracion-saltados';
            r.saltados.forEach(function (s) {
              var li = document.createElement('li');
              li.textContent = s.nombre + ': ' + s.motivo;
              ul.appendChild(li);
            });
            var titulo = document.createElement('p');
            titulo.className = 't-caption';
            titulo.textContent = 'Sin calibrar (' + r.saltados.length + '):';
            salida.appendChild(titulo);
            salida.appendChild(ul);
          }

          SRF.toast('Cadena calibrada: ' + elegido.value, 'success');
        })
        .catch(function () {
          boton.disabled = false;
          aviso.textContent = 'No se pudo calibrar: la petición falló.';
        });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    renderTodo();
    montarPestanas();
    montarCalibracion();
    montarCalibracionNodo();
    selectorCromoLoot();

    /* Filtros del selector de cromos de la alineación. Se enganchan una vez;
       la lista se repinta entera en cada cambio, que con unos cientos de
       cromos es instantáneo y evita tener que sincronizar estados por fila. */
    var buscar = document.getElementById('fn_c_buscar');
    if (buscar) { buscar.addEventListener('input', pintarListaCromos); }
    Array.prototype.forEach.call(document.querySelectorAll('.fn-c-filtro'), function (sel) {
      sel.addEventListener('change', pintarListaCromos);
    });

    /* El tipo de nodo decide si hay bloque de dificultad: un cofre no se
       juega, así que no tiene dificultad que ajustar. */
    var tipo = document.getElementById('fn_tipo');
    if (tipo) {
      tipo.addEventListener('change', function () {
        if (estado.nodoActual) { cargarDificultades(estado.nodoActual.id_nodo); }
      });
    }
  });
})();
