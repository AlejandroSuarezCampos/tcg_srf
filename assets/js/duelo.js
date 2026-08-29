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
  var url     = 'assets/ajax/duelo_estado';

  var INTERVALO = 3000;   // el servidor da la sala por muerta a los 15 s
  var saliendoAdrede = false;
  var temporizador = null;

  function detener() {
    if (temporizador) { clearInterval(temporizador); temporizador = null; }
  }

  /* `setInterval` NO espera a que termine la vuelta anterior: si una petición
     tarda más que el intervalo, se solapan y la cola crece sola. Con la base de
     datos en otra máquina eso pasa en cuanto hay un pico. El testigo hace que
     un latido lento simplemente se salte el siguiente, en vez de acumularlos. */
  var latiendo = false;

  async function latir() {
    if (latiendo) return;
    latiendo = true;
    try {
      var cuerpo = new FormData();
      cuerpo.append('id_duelo', idDuelo);
      cuerpo.append('accion', 'latir');
      cuerpo.append('csrf', SRF.csrfToken());

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
    } finally {
      latiendo = false;
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
    cuerpo.append('csrf', SRF.csrfToken());
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
  var url      = 'assets/ajax/duelo_estado';

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
  var sondeando = false;
  var sondeo = setInterval(async function () {
    if (sondeando) return;   // mismo motivo que el latido: no solapar vueltas
    sondeando = true;
    try {
      var cuerpo = new FormData();
      cuerpo.append('id_duelo', idDuelo);
      cuerpo.append('accion', 'estado');
      cuerpo.append('csrf', SRF.csrfToken());

      var res = await fetch(url, { method: 'POST', body: cuerpo });
      var datos = await res.json();

      /* 'en_juego' es el estado normal de un PvP recién montado: el partido
         empieza sin ganador y lo decide el propio encuentro. 'resuelto' se
         sigue aceptando porque es lo que devuelven las cadenas (PvE), que se
         resuelven de una vez. Comprobar solo 'resuelto' dejaba a los dos
         jugadores esperando en la pantalla de aumento hasta que el reloj
         llegase a cero, con el partido ya montado al otro lado. */
      if (datos.ok && (datos.estado === 'en_juego' || datos.estado === 'resuelto')) {
        clearInterval(sondeo);
        clearInterval(reloj);
        window.location.href = 'duelo.php?id=' + idDuelo + '&nuevo=1';
      }
    } catch (err) {
      console.error(err);
    } finally {
      sondeando = false;
    }
  }, 2500);
})();


/* --------------------------------------------------------------------------
   DESTAPAR EL RESULTADO

   Lo único que queda aquí de la pantalla de partido. El partido en sí lo lleva
   ahora `assets/js/partido.js` (motor jugable): se juega EN la página, no en un
   modal, y el relato minuto a minuto que lo alimentaba se retiró entero con el
   motor narrado viejo.

   Este trozo sobrevive porque el motor nuevo lo usa: cuando el duelo queda
   `resuelto`, `partido.js` hace `location.replace(... &revelar=1)` y esta carga
   ya trae la pantalla de resultado de verdad. Solo queda destaparla con su
   animación y llevar el foco al veredicto, que es lo que hace que un lector de
   pantalla anuncie quién ganó.
   -------------------------------------------------------------------------- */
(function () {
  'use strict';

  var partido = document.getElementById('partido');
  if (!partido) return;

  /* Sin `?revelar=1` no hay nada que destapar: o el partido se está jugando
     —y de eso se encarga partido.js— o se está mirando un duelo antiguo, que
     enseña el resultado sin teatro. */
  if (partido.dataset.revelar !== '1') return;

  partido.classList.add('es-revelando');
  window.setTimeout(function () {
    partido.classList.remove('es-revelando');
  }, 1400);

  var veredicto = partido.querySelector('.partido-veredicto');
  if (veredicto) veredicto.focus({ preventScroll: false });
})();
