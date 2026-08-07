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
    mjOpciones.innerHTML = '';
    panel.hidden = false;

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

    arrancarCuenta(mj.plazo);
  }

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
      if (quedan <= 0) pararCuenta();
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
           Hay dos vocabularios porque hay dos cosas que adivinar — el remate
           que te llega si defiendes, y cómo sale el portero si atacas. */
        var mote = {
          potente: 'un cañonazo', colocado: 'un tiro colocado', raso: 'un tiro raso',
          achica: 'a comerte el ángulo', tierra: 'al suelo', espera: 'plantado'
        }[d.remate] || d.remate;
        var defendia = ['potente', 'colocado', 'raso'].indexOf(d.remate) !== -1;

        var dice;
        if (d.resultado === 'acierto') {
          dice = defendia ? 'Leíste ' + mote + '.' : 'Salió ' + mote + ' y se la adivinaste.';
        } else {
          dice = defendia ? 'Llegó ' + mote + '. No te dio tiempo.'
                          : 'Salió ' + mote + '. Se te fue fuera.';
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
