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

   Con movimiento normal, antes de dejar ver el resultado se muestra un modal
   de "partido en juego": un reloj avanzando y los goles apareciendo uno a uno
   hasta llegar al marcador real, que ya está renderizado (oculto) debajo. Es
   ceremonia, no cálculo: los números finales sales de los que el servidor ya
   escribió en el marcador oculto, esto solo dramatiza el camino hasta ellos.
   -------------------------------------------------------------------------- */
(function () {
  'use strict';

  var partido = document.getElementById('partido');
  if (!partido) return;

  /* solo se hace ceremonia al llegar recién resuelto; volver a mirar un duelo
     antiguo enseña el resultado sin teatro */
  if (partido.dataset.nuevo !== '1') return;

  var veredicto = partido.querySelector('.partido-veredicto');

  function revelarResultado() {
    partido.classList.add('es-revelando');
    window.setTimeout(function () {
      partido.classList.remove('es-revelando');
    }, 1400);
    /* mueve el foco al veredicto para que un lector de pantalla lo anuncie
       sin tener que ir a buscarlo: hasta ahora nadie apuntaba el foco aquí */
    if (veredicto) veredicto.focus({ preventScroll: false });
  }

  var reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var simulacion = document.getElementById('simulacionPartido');

  if (reducido || !simulacion) {
    revelarResultado();
    return;
  }

  /* --------------------------------------------------------------------
     Números finales: se leen del marcador YA renderizado (oculto detrás
     del modal), nunca se recalculan aquí. El orden en el DOM es siempre
     mío primero, del rival después (ver duelo.php).
     -------------------------------------------------------------------- */
  var goles = partido.querySelectorAll('.partido-goles');
  var misGolesFinal = parseInt(goles[0].textContent, 10) || 0;
  var susGolesFinal = parseInt(goles[1].textContent, 10) || 0;

  var nombreYo   = partido.querySelector('.partido-lado .partido-nombre').textContent.trim();
  var nombreOtro = partido.querySelectorAll('.partido-lado .partido-nombre')[1].textContent.trim();

  var DURACION_MS = 7000;   // dentro del rango de 5–10 s pedido
  var MINUTO_MAX  = 93;     // 90' + un pellizco de descuento

  /* Reparte los goles en franjas del partido para que no se agrupen todos
     al principio o al final; el minuto exacto dentro de cada franja es al
     azar, así que dos recargas de la misma simulación no se ven idénticas.
     Es puramente decorativo: no cambia el resultado, solo cuándo se ve. */
  function programarEventos() {
    var eventos = [];
    var i;
    for (i = 0; i < misGolesFinal; i++) eventos.push({ mio: true });
    for (i = 0; i < susGolesFinal; i++) eventos.push({ mio: false });

    var n = eventos.length;
    // orden al azar de qué equipo marca en qué franja
    for (i = eventos.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var tmp = eventos[i]; eventos[i] = eventos[j]; eventos[j] = tmp;
    }
    for (i = 0; i < n; i++) {
      var inicio = Math.floor((i / n) * 88) + 1;
      var fin    = Math.floor(((i + 1) / n) * 88) + 1;
      eventos[i].minuto = inicio + Math.floor(Math.random() * Math.max(1, fin - inicio));
    }
    eventos.sort(function (a, b) { return a.minuto - b.minuto; });

    var acumMio = 0, acumSuyo = 0;
    eventos.forEach(function (e) {
      if (e.mio) acumMio++; else acumSuyo++;
      e.marcadorMio = acumMio;
      e.marcadorSuyo = acumSuyo;
    });
    return eventos;
  }

  var eventos = programarEventos();

  var reloj      = document.getElementById('simReloj');
  var barra      = document.getElementById('simBarra');
  var golesYoEl  = document.getElementById('simGolesYo');
  var golesOtroEl = document.getElementById('simGolesOtro');
  var zonaEventos = document.getElementById('simEventos');

  function mostrarEvento(e) {
    if (e.mio) { golesYoEl.textContent = e.marcadorMio; }
    else { golesOtroEl.textContent = e.marcadorSuyo; }

    var pill = document.createElement('span');
    pill.className = 'simulacion-evento';
    pill.textContent = 'Gol de ' + (e.mio ? nombreYo : nombreOtro);
    zonaEventos.innerHTML = '';
    zonaEventos.appendChild(pill);
    pill.addEventListener('animationend', function () {
      if (pill.parentNode) pill.remove();
    });
  }

  var raf = null;
  var terminado = false;

  function paso(marcaTiempo) {
    if (!paso.inicio) paso.inicio = marcaTiempo;
    var t = Math.min(1, (marcaTiempo - paso.inicio) / DURACION_MS);
    var minutoActual = t * MINUTO_MAX;

    reloj.textContent = Math.floor(minutoActual) + "'";
    barra.style.width = (t * 100) + '%';

    eventos.forEach(function (e) {
      if (!e.mostrado && minutoActual >= e.minuto) {
        e.mostrado = true;
        mostrarEvento(e);
      }
    });

    if (t < 1) {
      raf = window.requestAnimationFrame(paso);
    } else {
      reloj.textContent = 'Final';
      window.setTimeout(terminar, 700);
    }
  }

  function terminar() {
    if (terminado) return;
    terminado = true;
    if (raf) window.cancelAnimationFrame(raf);
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

  SRF.abrirModal('simulacionPartido');
  raf = window.requestAnimationFrame(paso);
})();
