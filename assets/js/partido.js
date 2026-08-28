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

  /* =====================================================================
     LA CAPA DE ESPECTÁCULO. Dos niveles, elegidos MIDIENDO el dispositivo.

     ⚠️ REGLA DURA: el nivel NO toca ninguna tolerancia, ningún radio de acierto
     ni ninguna ventana de tiempo. Solo cambia lo que se ve. Si algún día el
     nivel visual empieza a decidir cómo de fácil es acertar, "gama baja" se
     convierte en "peor balance", que es justo la trampa que este diseño existe
     para evitar.
     ===================================================================== */
  var nivel = 'completo';

  function medirNivel() {
    /* Quien pide menos movimiento lo recibe, sin discusión y sin medir. */
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return aplicarNivel('sobrio');
    }
    /* Pistas baratas del propio dispositivo, antes de gastar frames midiendo. */
    var hilos = navigator.hardwareConcurrency || 4;
    var ram   = navigator.deviceMemory || 4;
    if (hilos <= 4 || ram <= 2) { return aplicarNivel('sobrio'); }

    /* Y una medición real de 20 frames: las pistas mienten a menudo. */
    var frames = 0;
    var t0 = performance.now();
    (function contar() {
      frames++;
      if (frames < 20) { window.requestAnimationFrame(contar); return; }
      var fps = frames / ((performance.now() - t0) / 1000);
      aplicarNivel(fps < 40 ? 'sobrio' : 'completo');
    })();
  }

  function aplicarNivel(cual) {
    nivel = cual;
    document.body.dataset.espectaculo = cual;
    return cual;
  }

  /* =====================================================================
     LAS PRIMITIVAS. Tres, y las tres terminan en `Partido.enviar()`.

     ⚠️ NINGUNA PUNTÚA. Recogen lo que hizo el dedo y lo mandan tal cual.
     ===================================================================== */

  var LIENZO = 1000;   // espacio lógico; el servidor usa el mismo

  function aLogico(el, ev) {
    var r = el.getBoundingClientRect();
    return {
      x: (ev.clientX - r.left) / r.width * LIENZO,
      y: (ev.clientY - r.top) / r.height * LIENZO
    };
  }

  /* --- 1. ELECCIÓN: botones grandes con su pista ---------------------- */
  function montarEleccion(mj) {
    elLona.hidden = false;
    elLona.className = 'partido-lona es-eleccion';
    elLona.innerHTML = '';

    var t = document.createElement('p');
    t.className = 'partido-enunciado';
    t.textContent = mj.enunciado;
    elLona.appendChild(t);

    mj.opciones.forEach(function (o) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'partido-opcion';
      var n = document.createElement('span');
      n.className = 'partido-opcion-nombre';
      n.textContent = o.nombre;
      var p = document.createElement('span');
      p.className = 'partido-opcion-pista';
      p.textContent = o.pista;
      b.appendChild(n); b.appendChild(p);
      b.addEventListener('click', function () {
        elLona.hidden = true;
        esperando('…');
        enviar({ que: 'ejecucion', opcion: o.clave });
      });
      elLona.appendChild(b);
    });

    plazo(mj.plazo_seg, function () {
      elLona.hidden = true;
      enviar({ que: 'ejecucion', opcion: '' });   // vacío = el servidor aplica la segura
    });
  }

  /* --- 2. ZONA: las opciones sobre el marco de la portería ------------ */
  function montarZona(mj) {
    elLona.hidden = false;
    elLona.className = 'partido-lona es-zona';
    elLona.innerHTML = '<div class="partido-marco"></div>';
    var marco = elLona.querySelector('.partido-marco');

    mj.opciones.forEach(function (o, i) {
      var z = document.createElement('button');
      z.type = 'button';
      z.className = 'partido-sector';
      z.dataset.sector = o.clave;
      z.style.gridArea = 's' + i;
      z.title = o.pista;
      z.textContent = o.nombre;
      z.addEventListener('click', function () {
        elLona.hidden = true;
        esperando('…');
        enviar({ que: 'ejecucion', opcion: o.clave });
      });
      marco.appendChild(z);
    });

    plazo(mj.plazo_seg, function () {
      elLona.hidden = true;
      enviar({ que: 'ejecucion', opcion: '' });
    });
  }

  /* --- 3. TRAZO: seguir una figura con el dedo ------------------------ */
  function montarTrazo(mj) {
    elLona.hidden = false;
    elLona.className = 'partido-lona es-trazo';
    elLona.innerHTML = '<svg viewBox="0 0 1000 1000" class="partido-figura">'
      + '<path class="partido-guia"></path><path class="partido-estela"></path></svg>';

    var svg    = elLona.querySelector('svg');
    var guia   = elLona.querySelector('.partido-guia');
    var estela = elLona.querySelector('.partido-estela');

    guia.setAttribute('d', comoPath(mj.figura_puntos));

    var puntos = [];
    var t0 = 0;
    var trazando = false;

    function punto(ev) {
      var p = aLogico(svg, ev);
      /* ⚠️ El instante se toma del RELOJ, no del contador de frames. Un móvil a
         20 fps y uno a 120 mandan menos o más puntos, pero de la misma curva, y
         el servidor los remuestrea por longitud de arco. Esa es toda la
         justicia de hardware del juego, y vive en esta línea. */
      p.t = t0 ? Date.now() - t0 : 0;
      return p;
    }

    svg.addEventListener('pointerdown', function (ev) {
      trazando = true; t0 = Date.now(); puntos = [punto(ev)];
      svg.setPointerCapture(ev.pointerId);
    });
    svg.addEventListener('pointermove', function (ev) {
      if (!trazando) return;
      puntos.push(punto(ev));
      estela.setAttribute('d', comoPath(puntos));
    });
    svg.addEventListener('pointerup', function () {
      if (!trazando) return;
      trazando = false;
      elLona.hidden = true;
      esperando('…');
      enviar({ que: 'ejecucion', trazo: JSON.stringify(puntos.slice(0, 400)) });
    });

    plazo(mj.plazo_seg, function () {
      if (!trazando && puntos.length === 0) {
        elLona.hidden = true;
        enviar({ que: 'ejecucion', trazo: '[]' });   // sin trazo = rendimiento 0
      }
    });
  }

  function comoPath(pts) {
    if (!pts || !pts.length) return '';
    return 'M' + pts.map(function (p) {
      return p.x.toFixed(1) + ' ' + p.y.toFixed(1);
    }).join('L');
  }

  var temporizador = null;
  function plazo(segundos, alAgotarse) {
    if (temporizador) { window.clearTimeout(temporizador); }
    temporizador = window.setTimeout(alAgotarse, segundos * 1000);
  }

  /* Engancha las primitivas al bucle. La ficha del minijuego (opciones, pistas,
     figura) la manda el servidor: el cliente no tiene catálogo propio, para que
     no haya dos versiones de la verdad. */
  function montar(datosJugada) {
    fetch(cfg.base + 'assets/ajax/partido_jugada.php?ficha='
          + encodeURIComponent(datosJugada.minijuego)
          + '&id_duelo=' + cfg.idDuelo + '&numero=' + datosJugada.numero,
          { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (mj) {
        if (!mj || !mj.ok) return;
        if (mj.tipo === 'ejecucion')      { montarTrazo(mj); }
        else if (mj.primitiva === 'zona') { montarZona(mj); }
        else                              { montarEleccion(mj); }
      });
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

      alAbrir = montar;
      medirNivel();
      window.setInterval(sondear, 1000);
      sondear();
      pintarReloj();
    },
    alAbrirMinijuego: function (fn) { alAbrir = fn; },
    enviar: function (datos) { return enviar(datos); },
    jugadaActual: function () { return jugada; },
    congelarReloj: congelarReloj,
    nivelVisual: function () { return nivel; }
  };
})();
