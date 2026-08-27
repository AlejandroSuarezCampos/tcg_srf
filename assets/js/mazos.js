/* ==========================================================================
   DECK BUILDER — asignación de jugadores a huecos
   La alineación son siempre 11 huecos, repartidos según la formación elegida,
   y CUALQUIER carta puede ir en CUALQUIER hueco: no hay reglas de posición.
   Lo que cambia según dónde la pongas es con qué estadística puntúa, así que
   colocar es la decisión interesante, no un trámite.

   Interacción: elegir hueco → elegir jugador. Todo con tap/clic y con teclado
   (huecos y jugadores son <button>), sin ningún gesto de arrastre que haya que
   ofrecer por duplicado (SC 2.5.7).

   El servidor revalida todo en guardarCartasMazo(); esto es comodidad.
   ========================================================================== */
(function () {
  'use strict';

  var form = document.getElementById('formMazo');
  if (!form) return;

  var alineacion = document.getElementById('m-alineacion');
  var lista      = document.getElementById('m-lista');
  var buscar     = document.getElementById('m-buscar');
  var conteoBusca = document.getElementById('m-conteo');
  var conteo     = document.getElementById('mazoConteo');
  var conteoBoton = document.getElementById('mazoConteoBoton');
  var guardar    = document.getElementById('mazoGuardar');
  if (!alineacion || !lista) return;

  var huecos = Array.prototype.slice.call(alineacion.querySelectorAll('.hueco'));
  /* El selector pasó de rejilla de tarjetas a lista horizontal: cada jugador
     es un `<li class="carta-fila--accion">` con sus `data-*`, y el botón que
     se pulsa (y se deshabilita) es el `.carta-fila-interior` de dentro. Los
     datos siguen leyéndose del `<li>`, que es quien los lleva. */
  var items  = Array.prototype.slice.call(lista.querySelectorAll('.carta-fila--accion'));
  var vacio  = lista.querySelector('.selector-vacio');

  function botonDe(item) { return item.querySelector('.carta-fila-interior'); }

  var TAMANO = huecos.length;
  var activo = null;

  function campo(hueco) { return hueco.querySelector('input[type="hidden"]'); }
  function ocupado(hueco) { return campo(hueco).value !== ''; }

  function primerVacio() {
    for (var i = 0; i < huecos.length; i++) {
      if (!ocupado(huecos[i])) return huecos[i];
    }
    return null;
  }

  function marcarActivo(hueco) {
    activo = hueco;
    huecos.forEach(function (h) { h.classList.toggle('es-activo', h === hueco); });
  }

  /* Qué cromo es cada copia, para poder bloquear a un jugador entero y no solo
     la copia concreta que se ha usado. */
  var cromoDeCopia = {};
  items.forEach(function (item) { cromoDeCopia[item.dataset.carta] = item.dataset.cromo; });

  /* Dos bloqueos distintos:
     · la copia exacta que ya está en un hueco (es un objeto físico, está en un
       sitio o en otro);
     · cualquier otra copia del MISMO jugador, porque un once no puede alinear
       dos veces a la misma persona aunque tengas su cromo repetido. */
  function refrescarDisponibles() {
    var puestas = {};
    var jugadoresPuestos = {};

    huecos.forEach(function (h) {
      var v = campo(h).value;
      if (v === '') return;
      puestas[v] = true;
      if (cromoDeCopia[v]) jugadoresPuestos[cromoDeCopia[v]] = true;
    });

    items.forEach(function (item) {
      var estaCopia   = !!puestas[item.dataset.carta];
      var esteJugador = !!jugadoresPuestos[item.dataset.cromo];
      var boton = botonDe(item);
      if (boton) { boton.disabled = estaCopia || esteJugador; }
      item.classList.toggle('esta-elegida', estaCopia);
      /* repetido de alguien ya alineado: se distingue de "esta misma copia" */
      item.classList.toggle('es-repetido', esteJugador && !estaCopia);
    });

    var n = Object.keys(puestas).length;
    conteo.textContent = n;
    conteoBoton.textContent = n;
    guardar.disabled = n !== TAMANO;
  }

  var ETIQUETA_LINEA = { POR: 'Portería', DF: 'Defensa', MC: 'Medio', DC: 'Ataque' };

  /* Copia de Tcg::RENDIMIENTO_FUERA_DE_PUESTO: cuánto rinde una carta de la
     posición X colocada en la línea Y. Ojo al mantenerla — si se desincroniza
     del servidor, la previsualización promete una fuerza que el duelo no da. */
  var RENDIMIENTO = {
    POR: { POR: 1.00, DF: 0.75, MC: 0.62, DC: 0.50 },
    DF:  { POR: 0.60, DF: 1.00, MC: 0.90, DC: 0.75 },
    MC:  { POR: 0.55, DF: 0.90, MC: 1.00, DC: 0.90 },
    DC:  { POR: 0.50, DF: 0.75, MC: 0.90, DC: 1.00 }
  };

  /**
   * Cuánto aporta una carta a la línea del hueco donde se coloca.
   *
   * Los pesos vienen del servidor (Tcg::PESOS_LINEA vía `data-pesos`), así que
   * esto no es una segunda definición del balance: es la misma cuenta hecha
   * aquí para no recargar la página cada vez que cambias a un jugador.
   *
   * ⚠️ LA PENALIZACIÓN POR JUGAR FUERA DE PUESTO TAMBIÉN VA AQUÍ. Sin ella,
   *    el número que se ve al colocar y el que usa el servidor al resolver el
   *    partido dejarían de coincidir justo en el caso que importa —un portero
   *    puesto de delantero— y la pantalla estaría prometiendo una fuerza que
   *    el duelo no va a dar.
   */
  function aporte(item, pesos, linea) {
    var bruto = (parseFloat(item.dataset.ataque)  || 0) * pesos.ataque +
                (parseFloat(item.dataset.defensa) || 0) * pesos.defensa +
                (parseFloat(item.dataset.tecnica) || 0) * pesos.tecnica;
    return Math.round(bruto * rendimientoPuesto(item.dataset.posicion, linea));
  }

  function rendimientoPuesto(posicion, linea) {
    return (RENDIMIENTO[posicion] && RENDIMIENTO[posicion][linea]) || 1;
  }

  /* El hueco pinta un retrato compacto (no la tarjeta completa: en 11 sitios
     de un campo no cabría legible). La tarjeta completa, con rareza y
     estadísticas, se ve en el selector de abajo — es donde hace falta el
     detalle para elegir, no aquí. */
  function pintarHueco(hueco, item) {
    var linea = hueco.dataset.linea;
    var pesos = JSON.parse(hueco.dataset.pesos);

    campo(hueco).value = item.dataset.carta;
    hueco.dataset.rareza = item.dataset.rareza;

    var boton = hueco.querySelector('.hueco-boton');
    boton.innerHTML = '';

    var avatar = document.createElement('span');
    avatar.className = 'hueco-avatar';
    var interior = document.createElement('span');
    interior.className = 'hueco-avatar-int';
    if (item.dataset.imagen) {
      var img = document.createElement('img');
      img.src = item.dataset.imagen;
      img.alt = '';
      img.loading = 'lazy';
      interior.appendChild(img);
    } else {
      interior.innerHTML = '<i class="ph ph-user" aria-hidden="true"></i>';
    }
    avatar.appendChild(interior);
    boton.appendChild(avatar);

    var nombre = document.createElement('span');
    nombre.className = 'hueco-nombre';
    nombre.textContent = item.dataset.nombre;
    boton.appendChild(nombre);

    boton.setAttribute('aria-label', 'Hueco de ' + (ETIQUETA_LINEA[linea] || linea) + ': ' + item.dataset.nombre
      + ', ' + aporte(item, pesos, linea) + ' puntos');

    hueco.classList.add('esta-lleno');
    hueco.classList.toggle('es-desubicado', item.dataset.posicion !== linea);

    if (!hueco.querySelector('.hueco-quitar')) {
      var quitar = document.createElement('button');
      quitar.type = 'button';
      quitar.className = 'hueco-quitar';
      quitar.dataset.quitar = hueco.dataset.hueco;
      quitar.setAttribute('aria-label', 'Quitar a ' + item.dataset.nombre);
      quitar.innerHTML = '<i class="ph ph-x" aria-hidden="true"></i>';
      hueco.appendChild(quitar);
    }
  }

  function vaciarHueco(hueco) {
    campo(hueco).value = '';
    delete hueco.dataset.rareza;
    hueco.classList.remove('esta-lleno', 'es-desubicado');

    var linea = hueco.dataset.linea;
    var boton = hueco.querySelector('.hueco-boton');
    boton.innerHTML = '';

    var avatar = document.createElement('span');
    avatar.className = 'hueco-avatar';
    avatar.innerHTML = '<span class="hueco-avatar-int"><i class="ph ph-plus" aria-hidden="true"></i></span>';
    boton.appendChild(avatar);

    var nombre = document.createElement('span');
    nombre.className = 'hueco-nombre';
    nombre.textContent = ETIQUETA_LINEA[linea] || linea;
    boton.appendChild(nombre);

    boton.setAttribute('aria-label', 'Hueco de ' + (ETIQUETA_LINEA[linea] || linea) + ', vacío');

    var quitar = hueco.querySelector('.hueco-quitar');
    if (quitar) quitar.remove();
  }

  /* ---- elegir hueco ---- */
  alineacion.addEventListener('click', function (e) {
    var quitar = e.target.closest('.hueco-quitar');
    if (quitar) {
      var h = huecos[parseInt(quitar.dataset.quitar, 10)];
      vaciarHueco(h);
      marcarActivo(h);
      refrescarDisponibles();
      return;
    }

    var boton = e.target.closest('.hueco-boton');
    if (boton) marcarActivo(boton.closest('.hueco'));
  });

  /* ---- elegir jugador para el hueco activo ---- */
  lista.addEventListener('click', function (e) {
    var item = e.target.closest('.carta-fila--accion');
    if (!item) return;
    var boton = botonDe(item);
    if (boton && boton.disabled) return;

    if (!activo) marcarActivo(primerVacio() || huecos[0]);
    if (!activo) return;

    pintarHueco(activo, item);
    refrescarDisponibles();

    /* salta solo al siguiente hueco libre: rellenar 11 a mano es tedioso si
       hay que volver a señalar el sitio cada vez */
    var siguiente = primerVacio();
    if (siguiente) marcarActivo(siguiente);
  });

  /* ---- búsqueda y filtros ----
     Todo en cliente: la colección jugable ya está entera en la página, así que
     filtrar es esconder filas. Cada `<select>` declara en `data-campo` contra
     qué `data-*` de la fila compara, así que añadir un filtro nuevo es añadir
     un `<select>` en el HTML y nada más aquí. */
  var filtros = Array.prototype.slice.call(document.querySelectorAll('.m-filtro'));
  var limpiar = document.getElementById('m-limpiar');

  function filtrar() {
    var texto = buscar ? buscar.value.trim().toLowerCase() : '';
    var visibles = 0;

    items.forEach(function (item) {
      var d = item.dataset;
      var coincide = texto === '' ||
        (d.nombre || '').toLowerCase().indexOf(texto) !== -1 ||
        (d.equipo || '').toLowerCase().indexOf(texto) !== -1 ||
        (d.posicion || '').toLowerCase().indexOf(texto) !== -1;

      if (coincide) {
        coincide = filtros.every(function (sel) {
          if (sel.value === '') return true;
          return (item.dataset[sel.dataset.campo] || '') === sel.value;
        });
      }

      item.hidden = !coincide;
      if (coincide) visibles++;
    });

    if (vacio) vacio.hidden = visibles !== 0;
    conteoBusca.textContent = visibles + (visibles === 1 ? ' jugador disponible' : ' jugadores disponibles');
  }

  /* ---- ordenar por estadística ----
     Reordena el DOM de verdad en vez de esconder nada: convive con los filtros
     sin saber nada de ellos, y el orden se mantiene al filtrar porque las
     filas ya están colocadas.

     ⚠️ SE GUARDA EL ORDEN ORIGINAL AL ARRANCAR. Sin él, "Sin ordenar" no
        podría volver atrás: una vez movidas las filas, el orden con el que
        vinieron de la colección se habría perdido. Es también el que manda al
        vaciar los filtros.

     ⚠️ SE MUEVEN LAS FILAS, NO SE REPINTAN. Cada `<li>` lleva sus `data-*` y
        los manejadores de clic que engancha marcarActivo/colocar; recrearlas
        obligaría a volver a enganchar todo y a recuperar el estado de
        "elegida". appendChild sobre un nodo que ya existe lo MUEVE, que es
        justo lo que hace falta. */
  var orden = document.getElementById('m-orden');
  var ordenOriginal = items.slice();

  function ordenarLista() {
    if (!orden || !lista) return;

    var elegido = orden.value;
    var secuencia;

    if (!elegido) {
      secuencia = ordenOriginal;
    } else {
      var partes = elegido.split('-');
      var campo  = partes[0];
      var signo  = partes[1] === 'asc' ? 1 : -1;

      secuencia = ordenOriginal.slice().sort(function (a, b) {
        var va = parseInt(a.dataset[campo], 10) || 0;
        var vb = parseInt(b.dataset[campo], 10) || 0;
        // A igualdad de estadística manda el orden de la colección, para que
        // la lista no baile entre dos ordenaciones equivalentes.
        if (va !== vb) { return (va - vb) * signo; }
        return ordenOriginal.indexOf(a) - ordenOriginal.indexOf(b);
      });
    }

    /* El aviso de "ninguno coincide" es el último hijo y tiene que seguir
       siéndolo: se reinserta después de mover las filas. */
    secuencia.forEach(function (item) { lista.appendChild(item); });
    if (vacio) lista.appendChild(vacio);
  }

  if (buscar) buscar.addEventListener('input', filtrar);
  filtros.forEach(function (sel) { sel.addEventListener('change', filtrar); });
  if (orden) orden.addEventListener('change', ordenarLista);

  if (limpiar) {
    limpiar.addEventListener('click', function () {
      if (buscar) buscar.value = '';
      filtros.forEach(function (sel) { sel.value = ''; });
      // El orden también es un filtro para quien lo usa: "quitar filtros" que
      // deja la lista ordenada por ataque no ha quitado todos los filtros.
      if (orden) { orden.value = ''; ordenarLista(); }
      filtrar();
      if (buscar) buscar.focus();
    });
  }

  /* ---- cambio de formación ----
     No mueve a nadie de hueco: los once se quedan donde están y lo que cambia
     es la línea de cada hueco, o sea con qué estadística puntúa cada carta y
     dónde se dibuja sobre el campo. Así se puede probar una formación y volver
     atrás sin perder la alineación que llevabas montada.

     Huecos, coordenadas y estadísticas vienen serializados desde PHP: aquí no
     se recalcula ninguna, para que no haya una segunda definición de las
     formaciones que se pueda desincronizar de la de servidor. */
  var selectorFormacion = document.getElementById('m-formacion');
  var desubicados       = document.getElementById('mazoDesubicados');
  var bloqueDesubicados = document.getElementById('mazoDesubicadosBloque');

  var formaciones = {};
  try { formaciones = JSON.parse(alineacion.dataset.formaciones || '{}'); } catch (err) { formaciones = {}; }

  function itemDeCopia(idCopia) {
    for (var i = 0; i < items.length; i++) {
      if (items[i].dataset.carta === idCopia) return items[i];
    }
    return null;
  }

  function refrescarDesubicados() {
    var n = huecos.filter(function (h) { return h.classList.contains('es-desubicado'); }).length;
    if (desubicados) desubicados.textContent = n;
    if (bloqueDesubicados) bloqueDesubicados.hidden = n === 0;
  }

  function aplicarFormacion(clave) {
    var f = formaciones[clave];
    if (!f) return;

    huecos.forEach(function (hueco, i) {
      hueco.style.left = f.coords[i].x + '%';
      hueco.style.top  = f.coords[i].y + '%';
      hueco.dataset.linea = f.huecos[i];
      hueco.dataset.pesos = JSON.stringify(f.pesos[i]);

      /* se repinta aunque la carta no cambie: un hueco lleno puede pasar a
         estar fuera de posición, y uno vacío cambia de etiqueta */
      var copia = campo(hueco).value;
      if (copia === '') { vaciarHueco(hueco); return; }

      var item = itemDeCopia(copia);
      if (item) pintarHueco(hueco, item);
    });

    refrescarDesubicados();
  }

  if (selectorFormacion) {
    selectorFormacion.addEventListener('change', function () {
      aplicarFormacion(selectorFormacion.value);
    });
  }

  marcarActivo(primerVacio() || huecos[0]);
  refrescarDisponibles();
})();


/* --------------------------------------------------------------------------
   Confirmación para acciones destructivas del mazo.
   Usa el modal propio del sistema (SRF.confirmar), nunca confirm() del
   navegador: es la regla que ya sigue el mercado para todo lo que tiene
   consecuencias.
   -------------------------------------------------------------------------- */
(function () {
  'use strict';

  Array.prototype.forEach.call(document.querySelectorAll('form[data-confirmar]'), function (form) {
    form.addEventListener('submit', function (e) {
      if (form.dataset.confirmado === '1') return;
      e.preventDefault();

      SRF.confirmar(form.dataset.confirmar, function () {
        form.dataset.confirmado = '1';
        form.submit();
      });
    });
  });
})();


/* --------------------------------------------------------------------------
   FICHA AL VUELO SOBRE LOS TITULARES

   Pasar el ratón por un hueco ocupado enseña lo relevante del jugador y de su
   carta sin salir del campo: rareza, afinidad, las tres estadísticas y —lo que
   de verdad importa aquí— CUÁNTO APORTA EN ESE HUECO y por qué.

   ⚠️ EL APORTE NO SE CALCULA AQUÍ. Viaja ya resuelto en `data-detalle`, hecho
      en servidor con `Tcg::aportarCarta()`, que es la misma función que puntúa
      el duelo. Recalcularlo en JavaScript sería tener dos fuentes de la misma
      verdad; el día que cambien los pesos, la ficha diría una cosa y el
      partido otra, y no habría forma de notarlo hasta perderlo.

   Solo ratón y teclado: en táctil el hueco ya hace otra cosa al tocarlo (abrir
   el selector), y robarle el primer toque para enseñar una ficha es el patrón
   que obliga a tocar dos veces todo. En móvil el detalle sigue estando en la
   tarjeta completa del selector de abajo.
   -------------------------------------------------------------------------- */
(function () {
  'use strict';

  var alineacion = document.getElementById('m-alineacion');
  var ficha      = document.getElementById('m-huecoFicha');
  if (!alineacion || !ficha) return;

  var ETIQUETA = { ataque: 'ATA', defensa: 'DEF', tecnica: 'TEC' };

  function escapar(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : s;
    return d.innerHTML;
  }

  function filaStat(clave, valor, peso) {
    /* El peso se enseña junto a la estadística porque es lo que explica el
       total: un 90 de defensa en el ataque vale 0,15 de lo que parece, y sin
       verlo escrito la cifra de abajo no se entiende. */
    return '<span class="hf-stat">'
      + '<b class="mono">' + valor + '</b>'
      + '<span class="hf-stat-et">' + ETIQUETA[clave] + '</span>'
      + '<span class="hf-stat-peso mono">×' + peso + '</span>'
      + '</span>';
  }

  function pintar(d) {
    var partes = [];

    partes.push('<span class="hf-nombre">' + escapar(d.nombre) + '</span>');

    partes.push('<span class="hf-meta">'
      + '<span class="rz rz-' + d.idRareza + '">' + escapar(d.rareza) + '</span>'
      + '<span class="hf-pos">' + escapar(d.posicion) + '</span>'
      + (d.equipo ? '<span class="hf-equipo">' + escapar(d.equipo) + '</span>' : '')
      + '</span>');

    /* El hexágono, con el mismo marcado que la carta para que herede su
       estilo. Sin gráfico se enseña solo el nombre de la afinidad: el dato no
       depende de que exista el icono. */
    if (d.afinidad) {
      partes.push('<span class="hf-afinidad">'
        + (d.afinidadImg
            ? '<span class="carta-afinidad"><img src="' + escapar(d.afinidadImg)
              + '" alt=""></span>'
            : '')
        + escapar(d.afinidad) + '</span>');
    }

    partes.push('<span class="hf-stats">'
      + filaStat('ataque',  d.ataque,  d.pesos.ataque)
      + filaStat('defensa', d.defensa, d.pesos.defensa)
      + filaStat('tecnica', d.tecnica, d.pesos.tecnica)
      + '</span>');

    partes.push('<span class="hf-aporte">'
      + 'Aporta <b class="mono">' + d.aporte + '</b> en ' + escapar(d.lineaTexto)
      + '</span>');

    if (d.desubicado) {
      /* Se dice CUÁNTO cuesta, no solo que está fuera de sitio. Antes era un
         aviso sin consecuencia visible y no explicaba el número de arriba. */
      var merma = Math.round((1 - (d.rendimiento != null ? d.rendimiento : 1)) * 100);
      partes.push('<span class="hf-aviso">'
        + '<i class="ph ph-warning" aria-hidden="true"></i> '
        + 'Es ' + escapar(d.posicion) + ' jugando de ' + escapar(d.lineaTexto).toLowerCase()
        + (merma > 0 ? ': rinde un <b>' + merma + ' % menos</b>' : '')
        + '</span>');
    }

    if (d.rasgo) {
      partes.push('<span class="hf-rasgo">'
        + '<i class="ph ph-users-three" aria-hidden="true"></i> ' + escapar(d.rasgo)
        + '</span>');
    }

    ficha.innerHTML = partes.join('');
    ficha.dataset.rareza = d.idRareza;
  }

  function mostrar(hueco) {
    var crudo = hueco.dataset.detalle;
    if (!crudo) return;

    var d;
    try { d = JSON.parse(crudo); } catch (e) { return; }

    pintar(d);

    /* Se ancla al hueco en las MISMAS unidades en que está colocado el hueco
       (% sobre el campo), así que no hace falta medir nada y sigue cuadrando
       si el campo cambia de tamaño. `es-abajo` cuando el hueco está en la
       mitad superior: si no, la ficha se saldría por arriba del campo. */
    var x = parseFloat(hueco.style.left) || 50;
    var y = parseFloat(hueco.style.top) || 50;

    ficha.style.left = x + '%';
    ficha.style.top  = y + '%';
    ficha.classList.toggle('es-abajo', y < 45);
    /* Pegada a una banda se saldría de lado: se ancla por su borde en vez de
       por el centro. */
    ficha.classList.toggle('es-izquierda', x < 22);
    ficha.classList.toggle('es-derecha',  x > 78);

    ficha.hidden = false;
  }

  function ocultar() { ficha.hidden = true; }

  /* Delegación en el contenedor: los huecos se repintan al cambiar de
     formación (aplicarFormacion), y con listeners uno a uno habría que volver
     a engancharlos cada vez — el clásico "funciona hasta que cambias algo". */
  alineacion.addEventListener('mouseover', function (e) {
    var hueco = e.target.closest && e.target.closest('.hueco.esta-lleno');
    if (hueco) { mostrar(hueco); }
  });
  alineacion.addEventListener('mouseout', function (e) {
    var hueco = e.target.closest && e.target.closest('.hueco.esta-lleno');
    // Solo se esconde al salir del hueco de verdad, no al pasar de un hijo a
    // otro dentro del mismo (mouseout burbujea desde cada descendiente).
    if (hueco && !hueco.contains(e.relatedTarget)) { ocultar(); }
  });

  // Teclado: la ficha aparece al tabular hasta el hueco, igual que con el
  // ratón. Sin esto sería información accesible solo con un puntero.
  alineacion.addEventListener('focusin', function (e) {
    var hueco = e.target.closest && e.target.closest('.hueco.esta-lleno');
    if (hueco) { mostrar(hueco); }
  });
  alineacion.addEventListener('focusout', function (e) {
    var hueco = e.target.closest && e.target.closest('.hueco.esta-lleno');
    if (hueco && !hueco.contains(e.relatedTarget)) { ocultar(); }
  });

  // Al tocar se abre el selector: la ficha ahí solo estorbaría.
  alineacion.addEventListener('click', ocultar);
})();
