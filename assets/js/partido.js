/**
 * EL PARTIDO JUGABLE — cliente del bucle.
 *
 * Este archivo NO puntúa nada. Recoge lo que hace el dedo y lo manda; la nota la
 * pone el servidor. Si algún día aparece aquí un cálculo de rendimiento que se
 * envíe al backend, el juego está roto.
 */
(function () {
  'use strict';

  var cfg = null;
  var jugada = null;          // la jugada abierta que ya hemos pintado
  var reloj = { minuto: 0, corriendo: false, desde: 0, base: 0 };
  var alAbrir = function () {};

  var elMinuto  = null;
  var elZona    = null;
  var elAcciones = null;
  var elLona    = null;
  var elEspera  = null;

  /* ---------------------------------------------------------------------
     EL RELOJ. Corre en vivo entre jugadas y se CONGELA en cuanto hay algo que
     decidir o ejecutar. Esa es la decisión de diseño: el minuto no puede
     avanzar mientras el jugador piensa, porque entonces pensar despacio —o
     tener peor móvil— costaría partido.
     --------------------------------------------------------------------- */
  function arrancarReloj(minutoObjetivo) {
    reloj.base = reloj.minuto;
    reloj.desde = Date.now();
    reloj.corriendo = true;
    reloj.objetivo = minutoObjetivo;
  }

  function congelarReloj() {
    if (reloj.corriendo) { reloj.minuto = leerMinuto(); }
    reloj.corriendo = false;
  }

  function leerMinuto() {
    if (!reloj.corriendo) return reloj.minuto;
    var t = (Date.now() - reloj.desde) / 1000;
    var avance = (reloj.objetivo - reloj.base) * Math.min(1, t / cfg.narracionSeg);
    return Math.min(reloj.objetivo, Math.round(reloj.base + avance));
  }

  function pintarReloj() {
    if (elMinuto) { elMinuto.textContent = leerMinuto() + "'"; }
    window.requestAnimationFrame(pintarReloj);
  }

  /* ---------------------------------------------------------------------
     SONDEO. Reusa el estado que ya viajaba; no abre una petición nueva.
     --------------------------------------------------------------------- */
  function sondear() {
    fetch(cfg.base + 'assets/ajax/duelo_estado.php?id_duelo=' + cfg.idDuelo,
          { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d && d.partido) { aplicar(d.partido); } })
      .catch(function () { /* un sondeo perdido no es un error: cae el siguiente */ });
  }

  function aplicar(p) {
    if (p.fin) { congelarReloj(); mostrarFinal(); return; }
    if (!p.jugada) return;

    var esNueva = !jugada || jugada.numero !== p.jugada.numero;
    if (esNueva) {
      /* Jugada nueva: el reloj corre hasta su minuto mientras se narra, y se
         congela justo cuando toca decidir. */
      arrancarReloj(p.jugada.minuto);
      window.setTimeout(congelarReloj, cfg.narracionSeg * 1000);
      jugada = p.jugada;
      elZona.dataset.zona = p.jugada.zona;
    }

    if (p.decido_yo) {
      congelarReloj();
      pintarAcciones(p.acciones);
      return;
    }
    ocultarAcciones();

    if (p.jugada.accion && p.jugada.minijuego && !p.jugada.ya_jugue) {
      congelarReloj();
      alAbrir(p.jugada);          // la Task 12 monta aquí la primitiva
    } else if (p.jugada.ya_jugue) {
      esperando('Esperando al rival…');
    }
  }

  /* ---------------------------------------------------------------------
     LAS ACCIONES: lo que convierte ver el partido en jugarlo.
     --------------------------------------------------------------------- */
  function pintarAcciones(acciones) {
    elAcciones.innerHTML = '';
    Object.keys(acciones).forEach(function (clave) {
      var a = acciones[clave];
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'partido-accion';
      b.dataset.efecto = a.efecto;
      b.innerHTML = '<span class="partido-accion-nombre"></span>';
      b.querySelector('.partido-accion-nombre').textContent = a.nombre;
      b.addEventListener('click', function () { elegirAccion(clave); });
      elAcciones.appendChild(b);
    });
    elAcciones.hidden = false;
    elEspera.hidden = true;
  }

  function ocultarAcciones() { elAcciones.hidden = true; }

  function esperando(texto) {
    elEspera.textContent = texto;
    elEspera.hidden = false;
  }

  function elegirAccion(clave) {
    ocultarAcciones();
    esperando('…');
    enviar({ que: 'accion', accion: clave });
  }

  function enviar(datos) {
    var cuerpo = new FormData();
    cuerpo.append('csrf', cfg.csrf);
    cuerpo.append('id_duelo', cfg.idDuelo);
    cuerpo.append('numero', jugada.numero);
    Object.keys(datos).forEach(function (k) { cuerpo.append(k, datos[k]); });

    return fetch(cfg.base + 'assets/ajax/partido_jugada.php',
                 { method: 'POST', body: cuerpo, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { sondear(); return d; });
  }

  function mostrarFinal() {
    esperando('Final del partido.');
    elAcciones.hidden = true;
    if (elLona) { elLona.hidden = true; }
  }

  /* --------------------------------------------------------------------- */
  window.Partido = {
    iniciar: function (opciones) {
      cfg = opciones;
      elMinuto   = document.getElementById('partido-minuto');
      elZona     = document.getElementById('partido-zona');
      elAcciones = document.getElementById('partido-acciones');
      elLona     = document.getElementById('partido-lona');
      elEspera   = document.getElementById('partido-espera');
      if (!elZona) return;                       // no estamos en la pantalla del partido

      window.setInterval(sondear, 1000);
      sondear();
      pintarReloj();
    },
    alAbrirMinijuego: function (fn) { alAbrir = fn; },
    enviar: function (datos) { return enviar(datos); },
    jugadaActual: function () { return jugada; },
    congelarReloj: congelarReloj
  };
})();
