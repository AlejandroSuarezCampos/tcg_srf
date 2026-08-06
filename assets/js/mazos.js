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
  var items  = Array.prototype.slice.call(lista.querySelectorAll('.selector-item'));
  var vacio  = lista.querySelector('.selector-vacio');

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
      item.disabled = estaCopia || esteJugador;
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

  /* Cuánto aporta una carta a la línea del hueco donde se coloca. Los tres
     pesos vienen del servidor (Tcg::PESOS_LINEA vía data-pesos), así que esto
     no es una segunda definición del balance, solo la misma cuenta hecha aquí
     para no tener que recargar la página cada vez que cambias a un jugador. */
  function aporte(item, pesos) {
    return Math.round(
      (parseFloat(item.dataset.ataque)  || 0) * pesos.ataque +
      (parseFloat(item.dataset.defensa) || 0) * pesos.defensa +
      (parseFloat(item.dataset.tecnica) || 0) * pesos.tecnica
    );
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
      + ', ' + aporte(item, pesos) + ' puntos');

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
    var item = e.target.closest('.selector-item');
    if (!item || item.disabled) return;

    if (!activo) marcarActivo(primerVacio() || huecos[0]);
    if (!activo) return;

    pintarHueco(activo, item);
    refrescarDisponibles();

    /* salta solo al siguiente hueco libre: rellenar 11 a mano es tedioso si
       hay que volver a señalar el sitio cada vez */
    var siguiente = primerVacio();
    if (siguiente) marcarActivo(siguiente);
  });

  /* ---- búsqueda ---- */
  function filtrar() {
    var texto = buscar.value.trim().toLowerCase();
    var visibles = 0;

    items.forEach(function (item) {
      var d = item.dataset;
      var coincide = texto === '' ||
        (d.nombre || '').toLowerCase().indexOf(texto) !== -1 ||
        (d.equipo || '').toLowerCase().indexOf(texto) !== -1 ||
        (d.posicion || '').toLowerCase().indexOf(texto) !== -1;

      item.hidden = !coincide;
      if (coincide) visibles++;
    });

    if (vacio) vacio.hidden = visibles !== 0;
    conteoBusca.textContent = visibles + (visibles === 1 ? ' jugador disponible' : ' jugadores disponibles');
  }

  if (buscar) buscar.addEventListener('input', filtrar);

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
