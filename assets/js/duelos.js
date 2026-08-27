/* ==========================================================================
   DUELOS — lobby

   Tres cosas, y ninguna más: enseñar los campos que tocan según el tipo de
   apuesta, dejar elegir el LOTE de cartas que se pone sobre la mesa, y
   confirmar antes de entrar en una sala, porque entrar cuesta monedas o
   cartas y eso no se hace a ciegas.

   Desde la migración 031 una apuesta puede llevar varias cartas, así que las
   opciones son casillas y no radios: el navegador ya no desmarca la anterior
   al marcar una nueva, y todo lo que aquí abajo cuenta, limita y avisa existe
   por eso.
   ========================================================================== */

/* Marca visualmente las filas elegidas de un conjunto. El anillo ámbar va en
   el `<li>`, que es quien lleva la clase, no en el `<label>` de dentro. */
function marcarFilasElegidas(filas) {
  filas.forEach(function (fila) {
    var control = fila.querySelector('input');
    fila.classList.toggle('esta-elegida', !!control && control.checked);
  });
}

/* Las filas visibles y marcadas de una lista. Se cuenta sobre las visibles a
   propósito: una carta que un filtro ha escondido pero sigue marcada viajaría
   igualmente en el envío, así que quien llama la desmarca. */
function filasMarcadas(filas) {
  return filas.filter(function (f) {
    var c = f.querySelector('input');
    return c && c.checked;
  });
}


/* --------------------------------------------------------------------------
   ABRIR UNA SALA
   -------------------------------------------------------------------------- */
(function () {
  'use strict';

  var form = document.getElementById('formCrearDuelo');
  if (!form) return;

  var tipos   = Array.prototype.slice.call(form.querySelectorAll('[data-tipo]'));
  var rareza  = document.getElementById('d-rareza');
  var lista   = document.getElementById('d-cartas');
  var items   = lista ? Array.prototype.slice.call(lista.querySelectorAll('.carta-fila')) : [];
  var vacio   = lista ? lista.querySelector('.selector-vacio') : null;
  var buscar  = document.getElementById('d-buscar');
  var filtros = Array.prototype.slice.call(document.querySelectorAll('.d-filtro'));

  var contador  = document.getElementById('d-contador');
  var contadorN = document.getElementById('d-contador-n');
  /* El techo lo fija el servidor (configuración `duelo_cartas_max`) y viaja en
     `data-max`. Se lee de ahí en vez de repetirlo aquí: dos sitios con el mismo
     número acaban discrepando, y el que se queda corto es siempre este. */
  var maximo = contador ? (parseInt(contador.dataset.max, 10) || 5) : 5;

  function tipoElegido() {
    var marcado = form.querySelector('[data-tipo]:checked');
    return marcado ? marcado.value : 'monedas';
  }

  /* Solo se ven las cartas de la rareza pactada —mezclar todas convertiría el
     selector en un muro donde no se encuentra nada— y de esas, las que pasen
     los filtros propios del selector. */
  function filtrar() {
    if (!lista) return;
    var rz = rareza ? rareza.value : '';
    var texto = buscar ? buscar.value.trim().toLowerCase() : '';
    var visibles = 0;

    items.forEach(function (item) {
      var casilla = item.querySelector('input');
      if (!casilla) return;
      var d = item.dataset;

      var coincide = d.rareza === rz;
      if (coincide && texto !== '') {
        coincide = (d.nombre || '').toLowerCase().indexOf(texto) !== -1
                || (d.equipo || '').toLowerCase().indexOf(texto) !== -1;
      }
      if (coincide) {
        coincide = filtros.every(function (sel) {
          return sel.value === '' || (d[sel.dataset.campo] || '') === sel.value;
        });
      }

      item.hidden = !coincide;
      if (coincide) { visibles++; }
    });

    if (vacio) vacio.hidden = visibles !== 0;

    /* Cambiar de rareza vacía la selección entera. No es comodidad: todas las
       cartas de una apuesta tienen que ser de la MISMA rareza, así que dejar
       marcadas las de la rareza anterior compondría un lote mezclado que el
       servidor rechaza —y el jugador no vería por qué, porque esas filas ya no
       están en pantalla—. Los demás filtros (nombre, equipo, posición) no
       desmarcan: ahí esconder no cambia lo que es válido. */
    items.forEach(function (item) {
      var casilla = item.querySelector('input');
      if (casilla && casilla.checked && item.dataset.rareza !== rz) {
        casilla.checked = false;
      }
    });

    refrescarContador();
  }

  /* El techo se aplica aquí y no con `disabled` en cada casilla: una casilla
     desactivada no se puede ni enfocar, y quien llegara con teclado a la lista
     llena no entendería por qué la mitad de las filas ya no responden. Así,
     marcar de más simplemente no prende, y el contador dice cuántas caben. */
  function refrescarContador() {
    var elegidas = filasMarcadas(items);
    if (elegidas.length > maximo) {
      elegidas.slice(maximo).forEach(function (f) { f.querySelector('input').checked = false; });
      elegidas = filasMarcadas(items);
    }
    if (contadorN) contadorN.textContent = elegidas.length;
    if (contador)  contador.classList.toggle('esta-lleno', elegidas.length >= maximo);
    marcarFilasElegidas(items);
  }

  function refrescarBloques() {
    var tipo = tipoElegido();
    Array.prototype.forEach.call(form.querySelectorAll('[data-bloque]'), function (bloque) {
      bloque.hidden = bloque.dataset.bloque !== tipo;
    });

    if (tipo === 'carta') {
      filtrar();
    } else if (lista) {
      /* Apostando monedas no se pinta ninguna carta. Se ocultan TODAS y se
         desmarcan: además de ser ruido visual, una casilla marcada dentro de
         un bloque escondido seguiría viajando en el envío. */
      items.forEach(function (item) {
        var casilla = item.querySelector('input');
        item.hidden = true;
        if (casilla) { casilla.checked = false; }
      });
      refrescarContador();
    }
  }

  tipos.forEach(function (t) { t.addEventListener('change', refrescarBloques); });
  if (rareza) rareza.addEventListener('change', filtrar);
  if (buscar) buscar.addEventListener('input', filtrar);
  filtros.forEach(function (sel) { sel.addEventListener('change', filtrar); });
  if (lista) lista.addEventListener('change', refrescarContador);

  /* No se envía una apuesta de cartas vacía: el servidor la rechaza, pero
     enterarse después de recargar es peor que no dejar enviarla. */
  form.addEventListener('submit', function (e) {
    if (tipoElegido() !== 'carta') return;
    if (filasMarcadas(items).length === 0) {
      e.preventDefault();
      if (contador) contador.classList.add('esta-vacio');
      SRF.toast('Elige al menos una carta para apostar.');
    }
  });

  /* La opción de apuesta elegida se resalta. El radio es real pero está
     oculto, así que la señal visual la lleva la etiqueta que lo envuelve. */
  function marcarTipo() {
    Array.prototype.forEach.call(form.querySelectorAll('.apuesta-opcion'), function (op) {
      var radio = op.querySelector('input[type="radio"]');
      op.classList.toggle('esta-elegida', !!radio && radio.checked);
    });
  }
  tipos.forEach(function (t) { t.addEventListener('change', marcarTipo); });
  marcarTipo();

  refrescarBloques();
})();


/* --------------------------------------------------------------------------
   ENTRAR EN UNA SALA DE CARTAS

   Un único modal para todas las salas: lo que cambia es el id del duelo, qué
   filas se ven (solo la rareza que pide esa sala) y CUÁNTAS hay que poner.
   Ese número es condición de la sala, no elección de quien entra: si el
   creador puso tres, se ponen tres o no se entra.
   -------------------------------------------------------------------------- */
(function () {
  'use strict';

  var modal = document.getElementById('modalApostar');
  if (!modal) return;

  var campoDuelo = document.getElementById('apostarDuelo');
  var subtitulo  = document.getElementById('apostarSubtitulo');
  var contador   = document.getElementById('apostarContador');
  var enviar     = document.getElementById('apostarEnviar');
  var listaModal = document.getElementById('apostarCartas');
  var filas = Array.prototype.slice.call(listaModal.querySelectorAll('.carta-fila'));

  var exige = 1;   // cuántas pide la sala abierta ahora mismo

  function refrescar() {
    var n = filasMarcadas(filas).length;

    /* Igual que en el formulario de abrir sala: pasarse no prende, en vez de
       desactivar casillas que dejarían de ser alcanzables con teclado. */
    if (n > exige) {
      filasMarcadas(filas).slice(exige).forEach(function (f) {
        f.querySelector('input').checked = false;
      });
      n = filasMarcadas(filas).length;
    }

    marcarFilasElegidas(filas);
    if (contador) {
      contador.textContent = n + ' de ' + exige + (exige === 1 ? ' elegida' : ' elegidas');
      contador.classList.toggle('esta-lleno', n === exige);
    }
    // Entrar con menos de las pactadas lo rechaza el servidor; el botón lo
    // dice antes.
    if (enviar) enviar.disabled = n !== exige;
  }
  listaModal.addEventListener('change', refrescar);

  document.addEventListener('click', function (e) {
    var boton = e.target.closest && e.target.closest('[data-entrar-carta]');
    if (!boton) return;

    var rz = boton.dataset.rareza;
    exige = Math.max(1, parseInt(boton.dataset.cartas, 10) || 1);
    campoDuelo.value = boton.dataset.entrarCarta;
    subtitulo.textContent = 'Contra ' + boton.dataset.creador + ' · los dos ponéis '
      + (exige === 1 ? 'una carta ' : exige + ' cartas ') + boton.dataset.rarezaNombre + '.';

    filas.forEach(function (f) {
      var casilla = f.querySelector('input');
      var coincide = f.dataset.rareza === rz;
      f.hidden = !coincide;
      /* Se desmarca TODO al abrir, también lo que coincide: el modal es uno
         solo y reutilizado, así que sin esto la selección de la sala anterior
         reaparecería en la siguiente. */
      if (casilla) { casilla.checked = false; }
    });
    refrescar();

    SRF.abrirModal('modalApostar');
  });
})();


/* --------------------------------------------------------------------------
   Entrar en una sala mueve monedas o cartas: confirmación explícita antes,
   con el modal del sistema (nunca confirm() del navegador).
   -------------------------------------------------------------------------- */
(function () {
  'use strict';

  Array.prototype.forEach.call(document.querySelectorAll('form.js-aceptar'), function (form) {
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
