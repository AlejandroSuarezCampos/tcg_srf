/* ==========================================================================
   SALA DE DUELO
   Mientras el creador espera rival está DENTRO de la sala: su pantalla late
   contra el servidor. Si se va (cierra, navega, cancela), la sala muere y se
   le devuelve lo apostado.

   Sin websockets, esto son dos cosas muy simples:
     · un latido periódico, que además sirve de sondeo (la misma respuesta dice
       si ya ha entrado alguien),
     · un aviso al salir, más un sendBeacon que cancela sin esperar a que
       caduque el latido.
   ========================================================================== */
(function () {
  'use strict';

  var sala = document.getElementById('salaEspera');
  if (!sala) return;

  var idDuelo = sala.dataset.duelo;
  var aviso   = document.getElementById('salaAviso');
  var cancelar = document.getElementById('salaCancelar');
  var url     = 'assets/ajax/duelo_estado.php';

  var INTERVALO = 3000;   // el servidor da la sala por muerta a los 15 s
  var saliendoAdrede = false;
  var temporizador = null;

  function detener() {
    if (temporizador) { clearInterval(temporizador); temporizador = null; }
  }

  async function latir() {
    try {
      var cuerpo = new FormData();
      cuerpo.append('id_duelo', idDuelo);
      cuerpo.append('accion', 'latir');

      var res = await fetch(url, { method: 'POST', body: cuerpo });
      var datos = await res.json();

      if (!datos.ok) { detener(); return; }

      /* ha entrado alguien (o la sala ha muerto): la pantalla se recarga y el
         servidor decide qué toca pintar */
      if (datos.listo) {
        detener();
        saliendoAdrede = true;
        aviso.textContent = 'Rival encontrado. Empieza el partido…';
        window.location.href = 'duelo.php?id=' + idDuelo + '&nuevo=1';
      }
    } catch (err) {
      /* un fallo puntual de red no debe matar la sala: el servidor aún tiene
         margen antes de darla por abandonada, así que se reintenta */
      console.error(err);
    }
  }

  temporizador = setInterval(latir, INTERVALO);
  latir();

  /* Los navegadores estrangulan setInterval en pestañas de fondo (pasa a ~1 vez
     por minuto), así que volver de otra pestaña puede llegar con el latido ya
     vencido. Se late en cuanto la pantalla vuelve a estar visible para que
     regresar rápido no cueste la sala. */
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible' && temporizador) latir();
  });

  /* cancelar a propósito no debe disparar el aviso de "vas a perder la sala" */
  if (cancelar) {
    cancelar.addEventListener('click', function () {
      saliendoAdrede = true;
      detener();
    });
  }

  /* Salir de la pantalla cancela la sala. Se avisa primero, porque perder la
     sala es una consecuencia real, no un detalle. */
  window.addEventListener('beforeunload', function (e) {
    if (saliendoAdrede) return;
    e.preventDefault();
    e.returnValue = '';
  });

  /* pagehide sí llega cuando la pestaña se cierra de verdad; sendBeacon
     sobrevive a la descarga de la página, fetch normal no */
  window.addEventListener('pagehide', function () {
    if (saliendoAdrede) return;
    detener();
    var cuerpo = new FormData();
    cuerpo.append('id_duelo', idDuelo);
    cuerpo.append('accion', 'salir');
    navigator.sendBeacon(url, cuerpo);
  });
})();


/* --------------------------------------------------------------------------
   FASE DE AUMENTO
   Cuenta atrás visible y sondeo: cuando el plazo vence o el rival termina de
   elegir, la pantalla se recarga y el servidor decide qué toca.

   Quien manda es SIEMPRE el servidor: el reloj de aquí es informativo. El
   fallback lo aplica el propio servidor al comprobar la hora, así que adelantar
   el reloj del navegador no adelanta ni cambia nada.
   -------------------------------------------------------------------------- */
(function () {
  'use strict';

  var fase = document.getElementById('faseAumento');
  if (!fase) return;

  var idDuelo  = fase.dataset.duelo;
  var segundos = parseInt(fase.dataset.restante, 10) || 0;
  var salida   = document.getElementById('aumentoSegundos');
  var url      = 'assets/ajax/duelo_estado.php';

  var reloj = setInterval(function () {
    segundos--;
    if (salida) salida.textContent = Math.max(0, segundos);
    if (segundos <= 0) {
      clearInterval(reloj);
      /* el plazo lo cierra el servidor; recargar es lo que se lo pide */
      window.location.href = 'duelo.php?id=' + idDuelo + '&nuevo=1';
    }
  }, 1000);

  /* Sondeo aparte: si el rival termina antes de que venza el plazo, no hay que
     esperar a que el reloj llegue a cero para empezar el partido. */
  var sondeo = setInterval(async function () {
    try {
      var cuerpo = new FormData();
      cuerpo.append('id_duelo', idDuelo);
      cuerpo.append('accion', 'estado');

      var res = await fetch(url, { method: 'POST', body: cuerpo });
      var datos = await res.json();

      if (datos.ok && datos.estado === 'resuelto') {
        clearInterval(sondeo);
        clearInterval(reloj);
        window.location.href = 'duelo.php?id=' + idDuelo + '&nuevo=1';
      }
    } catch (err) {
      console.error(err);
    }
  }, 2500);
})();


/* --------------------------------------------------------------------------
   PANTALLA DE PARTIDO
   El resultado ya venía decidido del servidor; todo esto es solo la ceremonia
   de enseñarlo. Con "reducir movimiento" activo no hay simulación ni cuenta
   atrás: se pinta el marcador directamente, como antes.

   DOS MODOS, los elige duelo.php con data-modo (ver el comentario de allí):

     · 'narrado'  DUELOS PvP. El servidor manda el partido entero minuto a
       minuto (assets/ajax/duelo_narracion.php) y aquí solo se reproduce.

     · 'clasico'  CADENAS PvE. El comportamiento de siempre: reloj y goles
       apareciendo hasta llegar al marcador real, que ya está renderizado
       (oculto) debajo. Los números salen de ese marcador, nunca se calculan
       aquí. Se mantiene tal cual mientras las cadenas no se trabajen aparte.

   Los dos acaban igual: cerrar el modal revela el resultado.
   -------------------------------------------------------------------------- */
(function () {
  'use strict';

  var partido = document.getElementById('partido');
  if (!partido) return;

  /* solo se hace ceremonia al llegar recién resuelto; volver a mirar un duelo
     antiguo enseña el resultado sin teatro */
  if (partido.dataset.nuevo !== '1') return;

  var veredicto = partido.querySelector('.partido-veredicto');

  /* Si un minijuego llegó a parar un gol, el servidor ya actualizó el marcador
     guardado, pero la pantalla de resultado que hay DEBAJO del modal se
     renderizó antes con el viejo. Hay que corregirla antes de destaparla o el
     jugador vería un paradón y justo después el gol que acaba de parar. */
  var marcadorFinal = null;

  function revelarResultado() {
    if (marcadorFinal) {
      var casillas = partido.querySelectorAll('.partido-goles');
      if (casillas.length === 2) {
        casillas[0].textContent = marcadorFinal[0];
        casillas[1].textContent = marcadorFinal[1];
      }
    }
    partido.classList.add('es-revelando');
    window.setTimeout(function () {
      partido.classList.remove('es-revelando');
    }, 1400);
    /* mueve el foco al veredicto para que un lector de pantalla lo anuncie
       sin tener que ir a buscarlo: hasta ahora nadie apuntaba el foco aquí */
    if (veredicto) veredicto.focus({ preventScroll: false });
  }

  var reducido = SRF.movimientoReducido();   // preferencia de la web por encima de la del sistema
  var simulacion = document.getElementById('simulacionPartido');

  if (!simulacion) {
    revelarResultado();
    return;
  }

  var esNarrado = simulacion.dataset.modo !== 'clasico';

  /* MOVIMIENTO REDUCIDO — reduce el MOVIMIENTO, nunca el juego.
     Hasta ahora esta preferencia se saltaba el partido entero, y desde que el
     partido tiene minijuegos eso dejó de ser una decisión estética: quien la
     tenía puesta no veía el encuentro, no jugaba ninguna de sus decisiones, no
     podía parar un gol ni marcar uno, y encima su rival se comía la espera de
     alguien que nunca iba a aparecer. Una desventaja competitiva atada a un
     ajuste de accesibilidad, que además crecía con cada minijuego nuevo.

     · CLÁSICO (cadenas) — sigue saltándose. Ahí el partido es pura decoración:
       el marcador ya está decidido y no hay ninguna decisión que tomar, así que
       saltarlo no quita nada.
     · NARRADO (duelos PvP) — no se salta NUNCA. Se ve entero y se juega entero;
       lo que se apaga son las animaciones, que ya tienen sus reglas escritas en
       layout.css colgando de :root[data-motion="reduce"]. */
  if (reducido && !esNarrado) {
    revelarResultado();
    return;
  }

  /* --------------------------------------------------------------------
     Común a los dos modos.
     -------------------------------------------------------------------- */
  var reloj       = document.getElementById('simReloj');
  var barra       = document.getElementById('simBarra');
  var golesYoEl   = document.getElementById('simGolesYo');
  var golesOtroEl = document.getElementById('simGolesOtro');
  var relato      = document.getElementById('simRelato');
  var aguja       = document.getElementById('simMomentumAguja');
  var zonaEventos = document.getElementById('simEventos');   // solo modo clásico

  var nombreYo   = partido.querySelector('.partido-lado .partido-nombre').textContent.trim();
  var nombreOtro = partido.querySelectorAll('.partido-lado .partido-nombre')[1].textContent.trim();

  // El partido narrado pide más aire que la insignia de gol suelta del clásico.
  var DURACION_MS = esNarrado ? 16000 : 7000;
  var minutoMax   = esNarrado ? 94 : 93;
  var eventos     = [];
  var raf = null;
  var terminado = false;

  /* Las estadísticas en vivo ya no se pintan durante el partido (decisión de
     Alejandro: distraen del relato y del momentum, que es lo que se mira).
     El sondeo las sigue trayendo en d.stats y el motor las sigue calculando,
     así que volver a enseñarlas es añadir el marcado, no rehacer nada. */

  /* El relato crece hacia abajo y solo se conservan las últimas líneas: un
     partido son ~20 eventos y dejarlos todos obligaría a hacer scroll dentro
     de un modal que no debe desplazarse. */
  var MAX_LINEAS = 4;

  function mostrarEvento(e) {
    golesYoEl.textContent   = e.marcador[0];
    golesOtroEl.textContent = e.marcador[1];

    if (!esNarrado) {
      // CLÁSICO (cadenas): una insignia de gol que aparece y se va sola.
      var pill = document.createElement('span');
      pill.className = 'simulacion-evento';
      pill.textContent = 'Gol de ' + (e.mio ? nombreYo : nombreOtro);
      zonaEventos.innerHTML = '';
      zonaEventos.appendChild(pill);
      pill.addEventListener('animationend', function () {
        if (pill.parentNode) pill.remove();
      });
      return;
    }

    if (aguja) {
      // momentum llega en -100..100 ya desde MI punto de vista
      aguja.style.left = ((e.momentum + 100) / 2) + '%';
      aguja.classList.toggle('es-mio', e.momentum > 0);
    }

    var linea = document.createElement('p');
    linea.className = 'sim-linea';
    if (e.tipo === 'gol') linea.classList.add(e.mio ? 'es-gol-mio' : 'es-gol-suyo');
    if (e.tipo === 'inicio' || e.tipo === 'final' || e.tipo === 'descanso' ||
        e.tipo === 'reanuda' || e.tipo === 'descuento') {
      linea.classList.add('es-hito');
    }

    var min = document.createElement('b');
    min.className = 'sim-linea-min mono';
    min.textContent = e.minuto + "'";
    linea.appendChild(min);
    linea.appendChild(document.createTextNode(e.texto));

    relato.appendChild(linea);
    while (relato.children.length > MAX_LINEAS) relato.removeChild(relato.firstChild);
  }

  /* Reloj local — SOLO modo clásico (cadenas). En el modo narrado el minuto lo
     manda el servidor y esta función no se usa: ver sondear(), más abajo. */
  function paso(marcaTiempo) {
    if (!paso.inicio) paso.inicio = marcaTiempo;

    var t = Math.min(1, (marcaTiempo - paso.inicio) / DURACION_MS);
    var minutoActual = t * minutoMax;

    reloj.textContent = Math.floor(minutoActual) + "'";
    barra.style.width = (t * 100) + '%';

    for (var i = 0; i < eventos.length; i++) {
      if (!eventos[i].mostrado && minutoActual >= eventos[i].minuto) {
        eventos[i].mostrado = true;
        mostrarEvento(eventos[i]);
      }
    }

    if (t < 1) {
      raf = window.requestAnimationFrame(paso);
    } else {
      reloj.textContent = 'Final';
      window.setTimeout(terminar, 900);
    }
  }

  /* ======================================================================
     MINIJUEGO — el servidor manda
     El reloj está parado en el SERVIDOR desde que la jugada toca (ver
     Tcg::estadoPartido), así que los dos jugadores están detenidos aquí, no
     solo el que decide. La cuenta atrás que se ve abajo es puramente visual:
     si se agota no la resuelve este temporizador sino el propio servidor en el
     siguiente sondeo, aplicando la opción segura (§1.5 regla 4). Dejárselo al
     navegador significaría que cerrar la pestaña congela el partido del rival
     para siempre.
     ====================================================================== */
  var panel       = document.getElementById('simMinijuego');
  var mjTitulo    = document.getElementById('simMjTitulo');
  var mjTexto     = document.getElementById('simMjEnunciado');
  var mjBarra     = document.getElementById('simMjBarra');
  var mjSegundos  = document.getElementById('simMjSegundos');
  var mjOpciones  = document.getElementById('simMjOpciones');
  var mjResultado = document.getElementById('simMjResultado');
  var mjMedidor   = document.getElementById('simMjMedidor');
  var mjPista     = document.getElementById('simMjPista');
  var mjAguja     = document.getElementById('simMjAguja');
  var mjParar     = document.getElementById('simMjParar');
  var mjZonas     = document.getElementById('simMjZonas');
  var mjLienzo    = document.getElementById('simMjLienzo');
  var mjArrastre  = document.getElementById('simMjArrastre');
  var mjLona      = document.getElementById('simMjLona');
  var mjGuia      = document.getElementById('simMjGuia');
  var mjSectorNom = document.getElementById('simMjSectorNombre');

  var panelPuesto = null;   // qué hay pintado ahora: id de evento, 'rival' o null
  var enviando = false;

  function pintarMinijuego(mj) {
    if (panelPuesto === mj.id_evento) return;   // ya está puesto, no repintar
    panelPuesto = mj.id_evento;
    enviando = false;

    mjTitulo.textContent = mj.titulo;
    // La pista va junto al enunciado: es lo único que separa leer al rival de
    // adivinar, así que tiene que verse sin buscarla.
    mjTexto.textContent = mj.enunciado + (mj.pista ? '  ' + mj.pista : '');
    mjResultado.hidden = true;
    mjResultado.className = 'sim-mj-resultado';
    panel.hidden = false;

    /* La PRIMITIVA decide cómo se elige, no qué se decide: las dos ramas mandan
       la misma clave de opción por el mismo endpoint, y el servidor no sabe ni
       le importa con qué mando se eligió.

       Con movimiento reducido se cae siempre a los botones: cazar una aguja ES
       movimiento, y sin aguja no hay medidor que jugar. Es el §7 aplicado —
       se reduce el movimiento, nunca el juego: quien tenga la preferencia
       puesta decide exactamente lo mismo, solo con otro mando. */
    if (mj.primitiva === 'medidor' && !reducido)      pintarMedidor(mj);
    else if (mj.primitiva === 'zona' && mj.lienzo)    pintarZonas(mj);
    else if (mj.primitiva === 'arrastre')             pintarArrastre(mj);
    else                                             pintarBotones(mj);

    arrancarCuenta(mj.plazo);
  }

  /* Deja el panel con el mando que toque. Es el ÚNICO sitio que enciende y apaga
     mandos, para que no haya forma de que dos queden puestos por descuido.

     'arrastre' es el único que enseña dos cosas a la vez —la lona y los botones—
     y no es un despiste: WCAG 2.2 SC 2.5.7 exige que toda función de arrastre
     tenga alternativa de un solo puntero, así que los botones son parte de la
     primitiva, no un extra. */
  function soloEsteMando(cual) {
    pararMedidor();
    pararArrastre();
    mjOpciones.hidden = !(cual === 'botones' || cual === 'arrastre');
    mjMedidor.hidden  = (cual !== 'medidor');
    mjZonas.hidden    = (cual !== 'zonas');
    mjArrastre.hidden = (cual !== 'arrastre');
  }

  /* ---- ARRASTRE (Biblia §2.2, Familia DS) -------------------------------
     Se arrastra desde el balón hacia donde se quiere jugar. El ángulo cae en uno
     de TRES SECTORES de 60° que reparten el semiplano superior, y ese sector es
     la opción. La Biblia lo describe así de explícito: el sistema interpreta el
     arrastre "como perteneciente a uno de varios sectores de dirección
     predefinidos", sin física real.

     Sectores iguales para que a ciegas las tres opciones sigan valiendo 1/3, que
     es lo que mide el verificador: izquierda −90..−30, centro −30..+30,
     derecha +30..+90, medidos desde la vertical. */
  var arrastre = null;
  var SECTORES = ['izquierda', 'centro', 'derecha'];
  var MINIMO_ARRASTRE = 24;   // px; por debajo es un toque, no un gesto

  function pintarArrastre(mj) {
    soloEsteMando('arrastre');
    pintarOpcionesEn(mj);       // la alternativa exigida por SC 2.5.7
    mjGuia.style.height = '0px';
    mjGuia.style.transform = 'rotate(0deg)';
    mjSectorNom.textContent = '';
    arrastre = { idEvento: mj.id_evento, opciones: mj.opciones, activo: false };
  }

  function sectorDeGesto(dx, dy) {
    // Ángulo desde la vertical hacia arriba; positivo a la derecha.
    var grados = Math.atan2(dx, -dy) * 180 / Math.PI;
    if (grados < -30) return 'izquierda';
    if (grados >  30) return 'derecha';
    return 'centro';
  }

  function opcionDeSector(sector) {
    if (!arrastre) return null;
    var i = SECTORES.indexOf(sector);
    // Se busca por `sector` declarado; si una entrada no lo trae, cae al orden.
    var porSector = arrastre.opciones.filter(function (o) { return o.sector === sector; })[0];
    return porSector || arrastre.opciones[i] || null;
  }

  function pararArrastre() {
    arrastre = null;
    mjLona.classList.remove('esta-arrastrando');
  }

  mjLona.addEventListener('pointerdown', function (e) {
    if (!arrastre) return;
    arrastre.activo = true;
    mjLona.classList.add('esta-arrastrando');
    if (mjLona.setPointerCapture) mjLona.setPointerCapture(e.pointerId);
  });

  mjLona.addEventListener('pointermove', function (e) {
    if (!arrastre || !arrastre.activo) return;
    var caja = mjLona.getBoundingClientRect();
    var ox = caja.left + caja.width / 2;      // el balón, abajo en el centro
    var oy = caja.bottom - 12;
    var dx = e.clientX - ox;
    var dy = e.clientY - oy;
    var largo = Math.sqrt(dx * dx + dy * dy);

    mjGuia.style.height = Math.min(largo, caja.height) + 'px';
    mjGuia.style.transform = 'rotate(' + (Math.atan2(dx, -dy) * 180 / Math.PI) + 'deg)';

    if (largo >= MINIMO_ARRASTRE) {
      var op = opcionDeSector(sectorDeGesto(dx, dy));
      mjSectorNom.textContent = op ? op.nombre : '';
    } else {
      mjSectorNom.textContent = '';
    }
  });

  mjLona.addEventListener('pointerup', function (e) {
    if (!arrastre || !arrastre.activo) return;
    arrastre.activo = false;
    mjLona.classList.remove('esta-arrastrando');

    var caja = mjLona.getBoundingClientRect();
    var dx = e.clientX - (caja.left + caja.width / 2);
    var dy = e.clientY - (caja.bottom - 12);
    if (Math.sqrt(dx * dx + dy * dy) < MINIMO_ARRASTRE) {
      /* Un toque no es un gesto: sin este mínimo, tocar la lona resolvía la
         jugada con el ángulo que saliera, que es un accidente esperando. Se
         deja la guía a cero y el jugador arrastra otra vez o usa los botones. */
      mjGuia.style.height = '0px';
      mjSectorNom.textContent = '';
      return;
    }

    var op = opcionDeSector(sectorDeGesto(dx, dy));
    var idEvento = arrastre.idEvento;
    if (!op) return;
    pararArrastre();
    decidir(idEvento, op.clave);
  });

  /* ---- CLIC-EN-ZONA (Biblia §2.1, primera primitiva) --------------------
     Las tres opciones se colocan sobre el mapa que diga `lienzo`, cada una en
     su `zona`. La zona se aplica como `grid-area` con el nombre que manda el
     servidor: los huecos de Tcg::LIENZOS_ZONA y las grid-template-areas de
     layout.css son la misma lista de nombres, y el verificador comprueba que no
     se desincronicen.

     No necesita degradarse con movimiento reducido: son <button> normales, el
     teclado funciona solo y no hay nada animado. */
  function pintarZonas(mj) {
    soloEsteMando('zonas');
    mjOpciones.innerHTML = '';
    mjLienzo.innerHTML = '';
    mjLienzo.className = 'sim-mj-lienzo es-' + mj.lienzo;

    mj.opciones.forEach(function (o) {
      var z = document.createElement('button');
      z.type = 'button';
      z.className = 'sim-mj-zona-btn';
      z.style.gridArea = o.zona;
      z.innerHTML = '<b></b><span></span>';
      z.querySelector('b').textContent = o.nombre;
      z.querySelector('span').textContent = o.pista;
      z.addEventListener('click', function () { decidir(mj.id_evento, o.clave); });
      mjLienzo.appendChild(z);
    });
  }

  function pintarBotones(mj) {
    soloEsteMando('botones');
    pintarOpcionesEn(mj);
  }

  /* Construye los tres botones de opción. Está aparte de pintarBotones() porque
     el ARRASTRE también los necesita —son su alternativa de un solo puntero
     (SC 2.5.7)— y no puede llamar a pintarBotones(), que le cambiaría el mando. */
  function pintarOpcionesEn(mj) {
    mjOpciones.innerHTML = '';

    mj.opciones.forEach(function (o) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'sim-mj-opcion';
      b.innerHTML = '<b></b><span></span>';
      b.querySelector('b').textContent = o.nombre;
      b.querySelector('span').textContent = o.pista;
      b.addEventListener('click', function () { decidir(mj.id_evento, o.clave); });
      mjOpciones.appendChild(b);
    });
  }

  /* ---- MEDIDOR (Biblia §2.1, segunda primitiva) -------------------------
     La aguja va y viene sobre las tres zonas; la zona donde se detiene es la
     opción elegida.

     El tiempo se acumula DENTRO del rAF, nunca del reloj de pared. Es
     deliberado: el navegador deja de componer fotogramas en una pestaña de
     fondo, y con reloj de pared la aguja saltaría al volver a una posición que
     el jugador no ha visto pasar. Así lo que se ve y lo que se resuelve son
     siempre lo mismo. El PLAZO sí va por reloj de pared y en el servidor, así
     que irse de la pestaña no congela la jugada: te la resuelve con la opción
     segura, igual que antes. */
  var medidor = null;

  function pintarMedidor(mj) {
    soloEsteMando('medidor');
    mjOpciones.innerHTML = '';
    mjParar.disabled = false;

    Array.prototype.forEach.call(mjPista.querySelectorAll('.sim-mj-zona'),
      function (z) { mjPista.removeChild(z); });

    // Las zonas van en el orden del catálogo, y ahí la opción segura está en el
    // MEDIO a propósito: es la única que la aguja cruza dos veces por ciclo, o
    // sea la más fácil de acertar. Fallar el pulso te deja en lo conservador.
    mj.opciones.forEach(function (o) {
      var z = document.createElement('div');
      z.className = 'sim-mj-zona';
      z.innerHTML = '<b></b><span></span>';
      z.querySelector('b').textContent = o.nombre;
      z.querySelector('span').textContent = o.pista;
      mjPista.insertBefore(z, mjAguja);
    });

    /* La aguja ARRANCA en el centro de la zona segura, no en el extremo
       izquierdo, y esto no es estético: si el navegador no llega a pintar ni un
       fotograma —pestaña en segundo plano, donde requestAnimationFrame se
       pausa— `pos` se queda con su valor inicial, y pulsar "Parar" resolvería
       con la zona que hubiera ahí. Arrancando en 0 eso era siempre la PRIMERA
       opción de la lista, que no es la conservadora: convertía un fallo de
       fotogramas en una decisión arriesgada tomada sin querer.
       Con la segura de partida, lo peor que puede pasar coincide con lo que ya
       hace el servidor al agotarse el plazo (§1.5 regla 4). */
    var iSegura = mj.opciones.length >> 1;          // el centro, por si no viene
    mj.opciones.forEach(function (o, i) { if (o.segura) iSegura = i; });
    var posInicial = (iSegura + 0.5) / mj.opciones.length;

    medidor = {
      idEvento: mj.id_evento,
      opciones: mj.opciones,
      ciclo: Math.max(700, mj.velocidad || 2200),   // ms de ida y vuelta completa
      // El tramo de ida cubre la primera mitad del ciclo, así que este desfase
      // deja la aguja justo en `posInicial` y siguiendo hacia la derecha.
      t: Math.max(700, mj.velocidad || 2200) * (posInicial / 2),
      ultimo: 0,
      pos: posInicial,
      raf: null
    };

    // Se pinta ya, sin esperar al primer fotograma: si no llega, el jugador
    // tiene que ver dónde está la aguja que va a detener.
    mjAguja.style.left = (posInicial * 100) + '%';
    marcarZonaViva(zonaDeMedidor());

    medidor.raf = window.requestAnimationFrame(latirMedidor);
  }

  function latirMedidor(ahora) {
    if (!medidor) return;
    if (!medidor.ultimo) medidor.ultimo = ahora;
    medidor.t += ahora - medidor.ultimo;
    medidor.ultimo = ahora;

    // Ida y vuelta 0 → 1 → 0. La posición es función pura del tiempo
    // acumulado, así que detenerla y recalcularla da exactamente lo mismo.
    var fase = (medidor.t % medidor.ciclo) / medidor.ciclo;
    medidor.pos = fase < 0.5 ? fase * 2 : 2 - fase * 2;

    mjAguja.style.left = (medidor.pos * 100) + '%';
    marcarZonaViva(zonaDeMedidor());

    medidor.raf = window.requestAnimationFrame(latirMedidor);
  }

  /* Zonas de ancho igual: cada una vale exactamente 1/3 de la pista, así que a
     ciegas las tres siguen valiendo lo mismo y el medidor no toca el equilibrio
     que mide el verificador — solo añade una capa de ejecución encima. */
  function zonaDeMedidor() {
    if (!medidor) return 0;
    var n = medidor.opciones.length;
    return Math.min(n - 1, Math.floor(medidor.pos * n));
  }

  function marcarZonaViva(idx) {
    Array.prototype.forEach.call(mjPista.querySelectorAll('.sim-mj-zona'),
      function (z, i) { z.classList.toggle('es-viva', i === idx); });
  }

  function pararMedidor() {
    if (medidor && medidor.raf) window.cancelAnimationFrame(medidor.raf);
    medidor = null;
  }

  mjParar.addEventListener('click', function () {
    if (!medidor) return;
    var idx      = zonaDeMedidor();
    var opcion   = medidor.opciones[idx];
    var idEvento = medidor.idEvento;
    mjParar.disabled = true;
    pararMedidor();
    marcarZonaViva(idx);          // la zona que salió se queda encendida
    decidir(idEvento, opcion.clave);
  });

  /* La cuenta atrás. El número va SIEMPRE; la barra solo se anima si el
     jugador no ha pedido reducir el movimiento. Poner la transición en línea
     sin comprobarlo pisaba la regla `transition: none` de layout.css, que es
     justo la que respeta la preferencia. */
  var cuenta = null;

  function arrancarCuenta(plazo) {
    pararCuenta();

    mjBarra.style.transition = 'none';
    mjBarra.style.transform = 'scaleX(1)';
    if (!reducido) {
      window.requestAnimationFrame(function () {
        mjBarra.style.transition = 'transform ' + plazo + 's linear';
        mjBarra.style.transform = 'scaleX(0)';
      });
    }

    var quedan = plazo;
    mjSegundos.textContent = quedan + 's';
    cuenta = window.setInterval(function () {
      quedan--;
      mjSegundos.textContent = (quedan > 0 ? quedan : 0) + 's';
      if (quedan <= 0) {
        pararCuenta();
        /* Agotado el plazo la jugada ya no es tuya: el servidor la resuelve con
           la opción segura en su siguiente sondeo. Se detiene la aguja y se
           bloquea el botón para no prometer una decisión que ya no cuenta —
           dejarla girando invitaba a pulsar y llevarse la segura sin entender
           por qué. */
        pararMedidor();
        mjParar.disabled = true;
      }
    }, 1000);
  }

  function pararCuenta() {
    if (cuenta) { window.clearInterval(cuenta); cuenta = null; }
  }

  function decidir(idEvento, clave) {
    if (enviando) return;
    enviando = true;
    pararCuenta();
    mjSegundos.textContent = '';
    Array.prototype.forEach.call(mjOpciones.querySelectorAll('button'),
      function (b) { b.disabled = true; });

    fetch('assets/ajax/duelo_minijuego.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams({
        id_duelo: simulacion.dataset.idDuelo,
        id_evento: idEvento,
        opcion: clave
      })
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) { ocultarPanel(); return; }

        /* El dato oculto solo se conoce DESPUÉS de decidir: antes no viaja.
           Cada valor trae su mote y su GRUPO, porque la frase de cierre no se
           lee igual según lo que se estaba adivinando: parar un remate, batir a
           un portero o superar a una defensa colocada son tres cosas distintas.
           El grupo hace falta de verdad, no es adorno: sin él los cuatro
           minijuegos de balón parado cerraban con "Salió salta y se la
           adivinaste", enseñando el valor crudo del servidor. */
        var vocabulario = {
          potente:  { mote: 'un cañonazo',              grupo: 'remate' },
          colocado: { mote: 'un tiro colocado',         grupo: 'remate' },
          raso:     { mote: 'un tiro raso',             grupo: 'remate' },
          achica:   { mote: 'a comerte el ángulo',      grupo: 'portero' },
          tierra:   { mote: 'al suelo',                 grupo: 'portero' },
          espera:   { mote: 'plantado',                 grupo: 'portero' },
          salta:    { mote: 'a saltar',                 grupo: 'defensa' },
          aguanta:  { mote: 'a aguantar la posición',   grupo: 'defensa' },
          sale:     { mote: 'a romper hacia el balón',  grupo: 'defensa' },
          protesta: { mote: 'se fue a por el árbitro',  grupo: 'arbitro' },
          teatro:   { mote: 'lo alargó en el suelo',    grupo: 'arbitro' },
          sigue:    { mote: 'se levantó y siguió',      grupo: 'arbitro' }
        };
        var voz   = vocabulario[d.remate] || { mote: d.remate, grupo: 'remate' };
        var mote  = voz.mote;
        var frases = {
          remate: {
            acierto: 'Leíste ' + mote + '.',
            fallo:   'Llegó ' + mote + '. No te dio tiempo.'
          },
          portero: {
            acierto: 'Salió ' + mote + ' y se la adivinaste.',
            fallo:   'Salió ' + mote + '. Se te fue fuera.'
          },
          defensa: {
            acierto: 'La defensa fue ' + mote + ' y se la colaste.',
            fallo:   'La defensa fue ' + mote + '. Ahí se quedó.'
          },
          /* Familia Árbitro: son de impacto "ninguno", así que aquí NO se puede
             prometer nada del marcador — no hay gol que mover en una tarjeta.
             Lo que se gana es puntuación de actuación (§4.6), y el texto tiene
             que reflejar eso y no dejar al jugador esperando un gol. */
          arbitro: {
            acierto: 'El rival ' + mote + ', y lo tenías leído.',
            fallo:   'El rival ' + mote + '. No lo viste venir.'
          }
        };
        var defendia = voz.grupo === 'remate';
        var dice = frases[voz.grupo][d.resultado === 'acierto' ? 'acierto' : 'fallo'];
        /* Tres desenlaces, no dos. Un acierto ya NO garantiza el gol: sube la
           probabilidad, así que se puede leer bien la jugada y que no entre. Sin
           este caso el jugador acertaba, no pasaba nada y no había nada en
           pantalla que se lo explicara — parecía que el minijuego no servía.

           `podia_mover` distingue eso de una decisión que nunca iba a tocar el
           marcador (impacto "ninguno", o una defensa sobre una jugada que ya
           acabó sin gol), donde no hay nada que justificar. */
        if (d.resultado === 'acierto' && d.podia_mover && !d.parado) {
          dice += defendia
            ? ' La tocas, pero se te escapa y entra igual.'
            : ' Le ganas la acción y la estrellas en el palo.';
        }
        if (d.parado) {
          dice += defendia
            ? ' ¡Paradón! El gol no sube al marcador.'
            : ' ¡Dentro! Ese sí cuenta.';
          marcadorFinal = d.marcador;
          golesYoEl.textContent   = d.marcador[0];
          golesOtroEl.textContent = d.marcador[1];
        }
        mjResultado.textContent = dice;
        mjResultado.classList.add('es-' + d.resultado);
        mjResultado.hidden = false;
        /* El panel se queda un momento para poder leer el desenlace; el partido
           ya se ha reanudado en el servidor. Es corto a propósito: se paga en
           CADA decisión y con varias por partido la suma se nota. */
        window.setTimeout(ocultarPanel, 1200);
      })
      .catch(ocultarPanel);
  }

  function ocultarPanel() {
    pararCuenta();
    mjSegundos.textContent = '';
    // Fuera los tres mandos: si aparece uno nuevo, soloEsteMando() es el único
    // sitio que hay que tocar.
    soloEsteMando(null);
    panel.hidden = true;
    panelPuesto = null;
  }

  /* ======================================================================
     SONDEO DEL PARTIDO (modo narrado)
     El navegador no lleva el partido: pregunta en qué minuto va. Mismo patrón
     sin websockets que ya usa la sala de espera.
     ====================================================================== */
  var vistos = {};
  var sondeo = null;

  /* Aviso sin decisión (esperando al rival, esperando a empezar, actuación
     final). No hay plazo que contar, así que la cuenta se detiene y el reloj
     se deja lleno en vez de a medias. */
  function avisoEnPanel(titulo, texto) {
    pararCuenta();
    /* Un aviso no tiene decisión: fuera TODOS los mandos, no solo los botones.
       Sin parar el medidor la aguja seguía girando bajo el texto de "esperando
       al rival", como si todavía hubiera algo que cazar. */
    soloEsteMando('botones');    // el hueco vacío de las opciones, sin nada dentro
    mjSegundos.textContent = '';
    mjTitulo.textContent = titulo;
    mjTexto.textContent = texto;
    mjOpciones.innerHTML = '';
    mjResultado.hidden = true;
    mjBarra.style.transition = 'none';
    mjBarra.style.transform = 'scaleX(1)';
    panel.hidden = false;
  }

  function pintarEstado(d) {
    if (d.fase === 'esperando') {
      reloj.textContent = '—';
      if (panelPuesto !== 'espera') {
        panelPuesto = 'espera';
        avisoEnPanel('Antes de empezar',
          'Esperando a que aparezca ' + ((d.nombres && d.nombres.suyo) || 'el rival') + '…');
      }
      return;
    }
    if (panelPuesto === 'espera') ocultarPanel();

    /* Al acabar se enseña la puntuación de actuación (§4.6): es lo que hace
       que las decisiones importen aunque el marcador no se haya podido mover,
       y lo único que le queda por optimizar a quien pierde. */
    if (d.fase === 'final' && d.actuacion && d.actuacion.jugados) {
      if (panelPuesto !== 'actuacion') {
        panelPuesto = 'actuacion';
        avisoEnPanel('Tu actuación',
          'Acertaste ' + d.actuacion.aciertos + ' de ' + d.actuacion.jugados + ' decisiones.');
      }
    }

    reloj.textContent = d.fase === 'final' ? 'Final' : d.minuto + "'";
    barra.style.width = ((d.avance || 0) * 100) + '%';

    (d.eventos || []).forEach(function (e) {
      if (vistos[e.id]) return;
      vistos[e.id] = true;
      mostrarEvento(e);
    });

    /* El marcador que se destapará al final sale SIEMPRE de aquí, del último
       sondeo, nunca del que el servidor pintó en la página al cargarla.
       Antes solo se actualizaba cuando el gol lo parabas TÚ, así que si lo
       paraba el rival tu pantalla no se enteraba y acababa enseñando un
       marcador distinto al suyo: una cuenta veía 4-2 y la otra 4-3. */
    if (d.marcador) {
      golesYoEl.textContent   = d.marcador[0];
      golesOtroEl.textContent = d.marcador[1];
      marcadorFinal = d.marcador;
    }

    if (d.minijuego) {
      pintarMinijuego(d.minijuego);
    } else if (d.esperando_rival) {
      // El partido está parado por una decisión del OTRO. Se dice, en vez de
      // dejar el reloj congelado sin ninguna explicación.
      if (panelPuesto !== 'rival') {
        panelPuesto = 'rival';
        avisoEnPanel('Ocasión',
          ((d.nombres && d.nombres.suyo) || 'El rival') + ' está decidiendo…');
      }
    } else if (panelPuesto === 'rival') {
      ocultarPanel();
    }
  }

  function sondear() {
    if (terminado) return;
    fetch('assets/ajax/duelo_narracion.php?id_duelo=' + encodeURIComponent(simulacion.dataset.idDuelo), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) { terminar(); return; }
        pintarEstado(d);
        if (d.fase === 'final') { window.setTimeout(terminar, 1500); return; }
        sondeo = window.setTimeout(sondear, 1000);
      })
      // Un fallo de red no debe matar el partido: se reintenta más despacio.
      .catch(function () { sondeo = window.setTimeout(sondear, 2000); });
  }

  function terminar() {
    if (terminado) return;
    terminado = true;
    if (raf) window.cancelAnimationFrame(raf);
    if (sondeo) window.clearTimeout(sondeo);
    SRF.cerrarModal('simulacionPartido');
  }

  /* único punto de salida: da igual si se llega por el reloj, por "Ver
     resultado", por el aspa o por Esc (el modal genérico ya lo cierra) —
     en cuanto el modal deja de estar abierto, se revela el resultado. */
  var observador = new MutationObserver(function () {
    if (!simulacion.classList.contains('is-abierto')) {
      terminar();
      observador.disconnect();
      revelarResultado();
    }
  });
  observador.observe(simulacion, { attributes: true, attributeFilter: ['class'] });

  Array.prototype.forEach.call(
    simulacion.querySelectorAll('[data-saltar-simulacion]'),
    function (btn) { btn.addEventListener('click', terminar); }
  );

  /* ---- CLÁSICO (cadenas): conserva su reloj local, intacto --------------
     Los goles se leen del marcador YA renderizado (oculto detrás del modal),
     nunca se recalculan aquí. Se reparten en franjas para que no se agrupen
     todos al principio o al final. */
  if (!esNarrado) {
    var goles = partido.querySelectorAll('.partido-goles');
    var misGolesFinal = parseInt(goles[0].textContent, 10) || 0;
    var susGolesFinal = parseInt(goles[1].textContent, 10) || 0;

    var brutos = [], i, j, tmp;
    for (i = 0; i < misGolesFinal; i++) brutos.push({ mio: true });
    for (i = 0; i < susGolesFinal; i++) brutos.push({ mio: false });

    for (i = brutos.length - 1; i > 0; i--) {
      j = Math.floor(Math.random() * (i + 1));
      tmp = brutos[i]; brutos[i] = brutos[j]; brutos[j] = tmp;
    }
    for (i = 0; i < brutos.length; i++) {
      var ini = Math.floor((i / brutos.length) * 88) + 1;
      var fin = Math.floor(((i + 1) / brutos.length) * 88) + 1;
      brutos[i].minuto = ini + Math.floor(Math.random() * Math.max(1, fin - ini));
    }
    brutos.sort(function (a, b) { return a.minuto - b.minuto; });

    var accMio = 0, accSuyo = 0;
    eventos = brutos.map(function (e) {
      if (e.mio) accMio++; else accSuyo++;
      return { minuto: e.minuto, mio: e.mio, tipo: 'gol', marcador: [accMio, accSuyo] };
    });

    SRF.abrirModal('simulacionPartido');
    raf = window.requestAnimationFrame(paso);
    return;
  }

  /* ---- NARRADO (duelos PvP): lo lleva el servidor ---------------------- */
  SRF.abrirModal('simulacionPartido');
  sondear();
})();
