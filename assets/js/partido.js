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
  var elTanda   = null;   // la caja entera de la tanda; dentro, los seis trozos
  var tandaEl   = {};

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
  var sondeo = null;    // el id del setInterval, para poder pararlo al acabar

  function sondear() {
    /* ⚠️ POST con csrf, no GET: `duelo_estado.php` es un endpoint que ya
       existía antes de este motor y siempre exigió `$_POST['csrf']` y
       `$_POST['id_duelo']` — nunca leyó `$_GET`. Un GET aquí vuelve
       "Token CSRF inválido" en todas las peticiones y el partido nunca se
       pinta. Tampoco lleva `.php` en la URL: el `.htaccess` del proyecto
       redirige 301 quien lo pida con extensión, y un navegador real
       convierte ese 301 en un GET sin cuerpo — el POST se perdería entero. */
    var cuerpo = new FormData();
    cuerpo.append('csrf', cfg.csrf);
    cuerpo.append('id_duelo', cfg.idDuelo);
    fetch(cfg.base + 'assets/ajax/duelo_estado',
          { method: 'POST', body: cuerpo, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d) { return; }
        if (d.partido) { aplicar(d.partido); }
        /* `estado` viaja en la RAÍZ de la respuesta, no dentro de `partido`: es
           el estado del duelo, no el del encuentro. Se mira siempre, incluso si
           `partido` no vino, porque es lo único que dice si ya se liquidó. */
        comprobarResultado(d);
      })
      .catch(function () { /* un sondeo perdido no es un error: cae el siguiente */ });
  }

  function aplicar(p) {
    if (p.fin) {
      congelarReloj();
      /* ⚠️ EL FINAL DE LAS 12 JUGADAS NO ES EL FINAL DEL DUELO. Si quedó
         empatado, la tanda es una fase más y hay que jugarla antes de que haya
         ganador; solo cuando `p.tanda` no viene (o no la hay) esto es de verdad
         el final. */
      /* `cfg.decidido` = esta página YA es la de resultado. La tanda sigue
         llegando en el sondeo (las filas de `duelo_penaltis` no se borran al
         liquidar), pero pintarla aquí sería una portería y un historial encima
         del "Victoria/Derrota" que el servidor ya renderizó. */
      if (p.tanda && !cfg.decidido) { pintarTanda(p.tanda); }
      else { ocultarTanda(); mostrarFinal(); }
      return;
    }
    ocultarTanda();
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

    /* Sin `.php`: mismo motivo que en sondear(), un 301 aquí convertiría este
       POST en un GET sin cuerpo y la decisión o la ejecución se perdería. */
    return fetch(cfg.base + 'assets/ajax/partido_jugada',
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
     LA TANDA DE PENALTIS (§15.11)

     Cuatro huecos: eliges uno, el rival elige otro, y si coincidís es parada.
     Es la ÚNICA pantalla donde los dos decidís A LA VEZ y uno contra otro, así
     que aquí el dato oculto no es una carta: es la cabeza del otro.

     ⚠️ ESTE CÓDIGO NO PUEDE FILTRAR LA ELECCIÓN DEL RIVAL PORQUE NO LA TIENE.
     `Tcg::tandaParaCliente()` solo manda, del tiro en curso, si YO ya elegí
     (`ya_elegi`); las dos zonas viajan únicamente en el HISTORIAL, y ahí el
     tiro ya está resuelto y leerlas es justo la gracia. Tampoco se calcula
     nada aquí: se manda la zona cruda y el servidor decide si fue gol.
     ===================================================================== */
  var NOMBRE_HUECO = {
    arriba_izq: 'Arriba a la izquierda',
    arriba_der: 'Arriba a la derecha',
    abajo_izq:  'Abajo a la izquierda',
    abajo_der:  'Abajo a la derecha'
  };

  /* Qué tiro hay pintado y en qué estado, para no repintar en cada sondeo:
     repintar cada segundo robaría el foco del teclado a media decisión y
     reharía los botones bajo el dedo. */
  var tandaPuesta = null;
  var tandaEnviando = false;
  var huboTanda = false;      // ¿llegó a haber tanda? lo usa la espera final

  function botonesTanda(desactivar) {
    Array.prototype.forEach.call(tandaEl.porteria.children, function (b) {
      b.disabled = !!desactivar;
    });
  }

  function elegirHueco(zona) {
    /* UN SOLO TIRO ABIERTO A LA VEZ, y una sola elección por tiro: en cuanto
       sale una petición se apagan los cuatro botones y no se vuelven a encender
       hasta que el sondeo traiga un tiro DISTINTO. Que no se pueda elegir dos
       veces lo garantiza además el `zona_X IS NULL` del UPDATE del servidor;
       esto es solo para que la pantalla no mienta. */
    if (tandaEnviando) return;
    tandaEnviando = true;
    botonesTanda(true);
    tandaEl.orden.textContent = 'Elegido. Esperando al rival…';

    var cuerpo = new FormData();
    cuerpo.append('id_duelo', cfg.idDuelo);
    cuerpo.append('zona', zona);
    cuerpo.append('csrf', cfg.csrf);
    /* Sin `.php`: mismo motivo que en sondear() y enviar() — el `.htaccess`
       redirige 301 quien lo pida con extensión y el POST se perdería. */
    fetch(cfg.base + 'assets/ajax/duelo_penalti',
          { method: 'POST', body: cuerpo, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { /* el sondeo se encarga: el plazo decide solo */ })
      .then(function () { tandaEnviando = false; sondear(); });
  }

  function pintarTanda(t) {
    if (!elTanda) { mostrarFinal(); return; }

    huboTanda = true;
    // La tanda ocupa el sitio del partido: fuera acciones, lona y avisos.
    elAcciones.hidden = true;
    if (elLona) { elLona.hidden = true; }
    elEspera.hidden = true;
    elTanda.hidden = false;

    tandaEl.marcador.textContent = t.marcador[0] + ' – ' + t.marcador[1];

    /* La firma es lo que decide si hay algo nuevo que pintar. Cambia con cada
       tiro resuelto y con cada cambio de estado del tiro en curso; el reloj no
       entra en ella a propósito, porque baja cada segundo y repintaría siempre. */
    var firma = t.historial.length + ':'
      + (t.tiro ? t.tiro.ronda + ':' + t.tiro.tiro_yo + ':' + t.tiro.ya_elegi : 'fin');

    if (firma !== tandaPuesta) {
      tandaEl.historial.innerHTML = '';
      t.historial.forEach(function (h) {
        var li = document.createElement('li');
        li.className = (h.gol ? 'es-gol' : 'es-parada') + (h.mio ? ' es-mio' : '');
        li.textContent = (h.mio ? 'Tú' : 'Él') + ' · ' + (h.gol ? 'gol' : 'parada');
        li.title = 'Tiro a ' + (NOMBRE_HUECO[h.tirador] || '?')
                 + ', portero a ' + (NOMBRE_HUECO[h.portero] || '?')
                 + (h.auto ? ' (automático)' : '');
        tandaEl.historial.appendChild(li);
      });
    }

    if (!t.tiro) {
      /* Tanda terminada: ya hay ganador, pero el duelo todavía no está
         liquidado. Se deja el marcador y el historial a la vista y se espera a
         que `comprobarResultado()` vea el `resuelto`. */
      tandaEl.ronda.textContent = 'Tanda terminada';
      tandaEl.orden.textContent = '';
      tandaEl.reloj.textContent = '';
      tandaEl.porteria.innerHTML = '';
      tandaPuesta = firma;
      return;
    }

    tandaEl.ronda.textContent = (t.muerte_subita ? 'Muerte súbita · tiro ' : 'Penalti ')
      + t.tiro.ronda;
    tandaEl.reloj.textContent = t.tiro.restante + ' s';

    if (firma === tandaPuesta) return;   // mismo tiro y mismo estado: no repintar
    tandaPuesta = firma;

    if (t.tiro.ya_elegi) {
      tandaEl.orden.textContent = 'Elegido. Esperando al rival…';
      botonesTanda(true);
      return;
    }

    tandaEl.orden.textContent = t.tiro.tiro_yo
      ? 'Tiras tú: elige dónde la pones'
      : 'Paras tú: elige dónde te lanzas';

    tandaEl.porteria.innerHTML = '';
    t.zonas.forEach(function (z) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'partido-hueco';
      b.style.gridArea = z;
      b.textContent = NOMBRE_HUECO[z] || z;
      b.addEventListener('click', function () { elegirHueco(z); });
      tandaEl.porteria.appendChild(b);
    });
  }

  function ocultarTanda() {
    if (!elTanda || elTanda.hidden) return;
    elTanda.hidden = true;
    tandaPuesta = null;
  }

  /* ---------------------------------------------------------------------
     IR A POR LA PANTALLA DE RESULTADO.

     ⚠️ EL DISPARADOR NO ES `fin`, ES `estado === 'resuelto'`. `fin` solo dice
     que las 12 jugadas están jugadas; si el partido quedó empatado el duelo
     sigue `en_juego` con la tanda por delante, y recargar ahí enseñaría
     "Partido en juego" donde debería decir Victoria o Derrota. Solo cuando el
     servidor dice `resuelto` está el ganador escrito y el bote entregado.

     Y `cfg.decidido` evita el bucle: si esta página ya se cargó con el duelo
     decidido, la pantalla de abajo YA es la buena y no hay nada que recargar.
     Mismo guardia que usaba `irAlResultado()` con `data-decidido`.
     --------------------------------------------------------------------- */
  var yendoAlResultado = false;

  function comprobarResultado(d) {
    if (yendoAlResultado || cfg.decidido) return;
    if (d.estado !== 'resuelto') return;
    yendoAlResultado = true;
    if (sondeo) { window.clearInterval(sondeo); sondeo = null; }

    /* Un duelo decidido en los penaltis se queda un momento más antes de irse:
       el último tiro es el desenlace y merece verse, no aparecer y desaparecer. */
    window.setTimeout(function () {
      /* replace() y no href: el partido no es un paso al que volver con Atrás.
         `revelar=1` hace que la pantalla de resultado llegue ya destapada. */
      window.location.replace(cfg.base + 'duelo.php?id='
        + encodeURIComponent(cfg.idDuelo) + '&revelar=1');
    }, huboTanda ? 2800 : 1500);
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
    fetch(cfg.base + 'assets/ajax/partido_jugada?ficha='
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
      elTanda    = document.getElementById('partido-penaltis');
      if (elTanda) {
        tandaEl = {
          ronda:     document.getElementById('partido-penaltis-ronda'),
          marcador:  document.getElementById('partido-penaltis-marcador'),
          orden:     document.getElementById('partido-penaltis-orden'),
          porteria:  document.getElementById('partido-penaltis-porteria'),
          reloj:     document.getElementById('partido-penaltis-reloj'),
          historial: document.getElementById('partido-penaltis-historial')
        };
      }
      if (!elZona) return;                       // no estamos en la pantalla del partido

      alAbrir = montar;

      /* El nivel visual y el reloj arrancan YA, sin esperar a la intro: no
         piden nada al servidor y medir tarda 20 frames. Lo único que espera es
         el sondeo. */
      medirNivel();
      pintarReloj();

      /* PRIMERO LA PRESENTACIÓN, DESPUÉS EL PARTIDO.
         La intro de alineaciones (assets/js/presentacion.js) tapa la pantalla
         entera; si el sondeo empezara por debajo, las primeras jugadas se
         jugarían sin que nadie las viera —y con el plazo corriendo—, así que se
         espera a que termine o a que la salten.

         `presentacionPartido()` está siempre definida cuando presentacion.js
         está cargado, haya intro o no: cuando no la hay devuelve una promesa ya
         resuelta. Y si el archivo no está en la página (el caso normal: solo se
         carga con `?nuevo`), el fallback de aquí hace lo mismo. */
      var intro = (window.SRF && window.SRF.presentacionPartido)
        ? window.SRF.presentacionPartido()
        : Promise.resolve();

      intro.then(function () {
        sondeo = window.setInterval(sondear, 1000);
        sondear();
      });
    },
    alAbrirMinijuego: function (fn) { alAbrir = fn; },
    enviar: function (datos) { return enviar(datos); },
    jugadaActual: function () { return jugada; },
    congelarReloj: congelarReloj,
    nivelVisual: function () { return nivel; }
  };
})();
