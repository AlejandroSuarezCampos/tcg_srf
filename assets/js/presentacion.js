/* ==========================================================================
   PRESENTACIÓN DE ALINEACIONES — el motor de la intro previa al partido.

   Se ejecuta entre la elección del aumento y el pitido inicial. `duelo.js` la
   espera antes de abrir el partido; si este archivo no está, o el duelo no
   trae datos, la promesa se resuelve al instante y el partido empieza igual.
   La intro NUNCA puede impedir que se juegue.

   Cómo está montado, que es lo que pedía el encargo:

     presentacion()                 la secuencia entera
       ├── fondo()                  las capas de luz (viven en CSS)
       ├── presentarEquipo(lado)    un equipo, por turnos
       │     ├── identidad()        etiqueta, retrato, nombre
       │     ├── mazo()             mazo y formación
       │     ├── aumento()          la placa del aumento, con su destello
       │     └── alineacion()       las líneas, una a una
       ├── enfrentamiento()         el VS
       └── arranque()               "El partido comienza"

   Los datos llegan del servidor ya resueltos (`Tcg::datosPresentacionDuelo`).
   Aquí no se decide si enfrente hay una persona o la máquina: se lee `tipo`.
   ========================================================================== */
(function () {
  'use strict';

  var caja = document.getElementById('presentacionDuelo');
  var bruto = document.getElementById('presDatos');

  /* La API se publica SIEMPRE, exista la intro o no. `duelo.js` la llama a
     ciegas y espera una promesa; devolver `undefined` en la mitad de los
     partidos obligaría a comprobarlo en el otro lado. */
  window.SRF = window.SRF || {};

  if (!caja || !bruto || !window.gsap) {
    window.SRF.presentacionPartido = function () { return Promise.resolve(); };
    return;
  }

  var datos;
  try { datos = JSON.parse(bruto.textContent); } catch (e) { datos = null; }
  if (!datos || !datos.local || !datos.visitante) {
    window.SRF.presentacionPartido = function () { return Promise.resolve(); };
    return;
  }

  // ---------------------------------------------------------------- ajustes
  var quieto = SRF.movimientoReducido && SRF.movimientoReducido();

  /* Los tiempos, en un solo sitio. Con movimiento reducido se acortan además
     de quitarse el desplazamiento: un fundido no necesita lo mismo que una
     entrada con rebote, y alargarlo solo hace esperar. */
  /* ⚠️ SE ALARGÓ LO QUE SE LEE, NO EL FLOREO.
     Con los tiempos de antes la presentación entera duraba 7,4 s para dos
     onces: no daba tiempo a leer el nombre del rival, su formación, sus cuatro
     líneas y su aumento antes de que la pantalla pasara a lo siguiente.

     Lo que se sube es la ficha de cada equipo (`bloque`), el desgrane de sus
     líneas (`linea`) y sobre todo la PAUSA ENTRE LOS DOS EQUIPOS
     (`entreEquipos`), que es donde se termina de leer al primero. El VS y el
     "comienza el partido" se quedan casi igual: son la parte más vistosa y la
     que menos información lleva, y es la que se hacía larga a la décima vez.

     ⚠️ EL TECHO SIGUE SIENDO `partido_espera_seg` (15 s por defecto, ajustable
        desde el panel): pasado ese tiempo el servidor arranca el reloj haya
        quien haya mirando, así que una intro más larga se comería minutos del
        partido de verdad. Con estos números ronda los 11 s y queda margen. Si
        se vuelve a alargar, hay que subir también esa configuración. */
  var T = quieto
    ? { entrada: .28, bloque: .24, linea: .14, entreEquipos: .40, vs: .40, arranque: .40, cierre: .20 }
    : { entrada: .45, bloque: .45, linea: .26, entreEquipos: .55, vs: .60, arranque: .60, cierre: .30 };


  var STATS  = { ataque: 'Ataque', defensa: 'Defensa', tecnica: 'Técnica' };
  var EFECTO = {
    ataque:  'Sube tu línea de Ataque',
    defensa: 'Sube Portería y Defensa',
    tecnica: 'Sube tu línea de Medio',
  };
  var ICONO_STAT = { ataque: 'ph-sword', defensa: 'ph-shield', tecnica: 'ph-sparkle' };

  // ---------------------------------------------------------------- nodos
  var n = {
    equipo:     document.getElementById('presEquipo'),
    etiqueta:   document.getElementById('presLadoEtiqueta'),
    retrato:    document.getElementById('presRetrato'),
    retratoImg: document.getElementById('presRetratoImg'),
    retratoNo:  document.getElementById('presRetratoVacio'),
    nombre:     document.getElementById('presLadoNombre'),
    mazoBloque: document.getElementById('presMazoBloque'),
    mazo:       document.getElementById('presMazo'),
    formacion:  document.getElementById('presFormacion'),
    aumento:    document.getElementById('presAumento'),
    aumHalo:    caja.querySelector('.pres-aumento-halo'),
    aumIco:     document.getElementById('presAumentoIco'),
    aumPct:     document.getElementById('presAumentoPct'),
    aumStat:    document.getElementById('presAumentoStat'),
    aumDesc:    document.getElementById('presAumentoDesc'),
    lineas: {
      local:     document.getElementById('presLineasLocal'),
      visitante: document.getElementById('presLineasVisitante'),
    },
    vs:         document.getElementById('presVs'),
    vsLocal:    document.getElementById('presVsLocal'),
    vsVisit:    document.getElementById('presVsVisitante'),
    vsLocalImg: document.getElementById('presVsLocalImg'),
    vsVisitImg: document.getElementById('presVsVisitanteImg'),
    arranque:   document.getElementById('presArranque'),
    barrido:    caja.querySelector('.pres-barrido'),
    saltar:     document.getElementById('presSaltar'),
    narracion:  document.getElementById('presNarracion'),
  };

  // ---------------------------------------------------------------- ayudas
  function decir(texto) { n.narracion.textContent = texto; }

  /** Escapa el texto que se mete por innerHTML. Los nombres los pone gente. */
  function esc(t) {
    return String(t == null ? '' : t)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /**
   * Precarga una imagen. NUNCA rechaza: una foto que falta no puede tumbar la
   * presentación, solo hace que salga el hueco de repuesto.
   */
  function precargar(url) {
    return new Promise(function (listo) {
      if (!url) { return listo(); }
      var img = new Image();
      img.onload = img.onerror = function () { listo(); };
      img.src = url;
    });
  }

  /**
   * Todas las imágenes de la intro, antes de empezar.
   *
   * Con un TECHO de 2,5 s: si el servidor va lento, se arranca igual y las que
   * falten entrarán al vuelo. Esperar indefinidamente a una foto convertiría
   * la intro en la pantalla de carga que precisamente no queremos.
   */
  function precargarTodo() {
    /* Solo los dos retratos. Las cartas están en el documento desde que cargó
       la página, así que el navegador lleva pidiéndolas desde antes de que
       esto se ejecute: volver a esperarlas aquí sería esperar dos veces por lo
       mismo, y con el doble de riesgo de atascarse. */
    var urls = [datos.local.imagen, datos.visitante.imagen].filter(Boolean);
    return Promise.race([
      Promise.all(urls.map(precargar)),
      new Promise(function (listo) { setTimeout(listo, 2500); }),
    ]);
  }

  // ------------------------------------------------------- pintar un equipo
  /** Rellena la escena con los datos de un lado. No anima: solo escribe. */
  function montarEquipo(lado) {
    caja.dataset.lado = lado.lado;
    n.etiqueta.textContent = lado.lado === 'local' ? 'LOCAL' : 'VISITANTE';
    n.nombre.textContent = lado.nombre;

    /* El retrato. Una persona enseña su foto; la máquina, su escudo. Y si la
       imagen no está —o la fila de la base apunta a un archivo que ya no
       existe— se cae a la silueta en vez de dejar un cuadro roto. */
    var esIa = lado.tipo === 'ia';
    n.retrato.classList.toggle('pres-retrato--ia', esIa);
    if (lado.imagen) {
      n.retratoImg.hidden = false;
      n.retratoNo.hidden = true;
      n.retratoImg.onerror = function () {
        n.retratoImg.hidden = true;
        n.retratoNo.hidden = false;
      };
      n.retratoImg.src = lado.imagen;
      n.retratoImg.alt = '';
    } else {
      n.retratoImg.hidden = true;
      n.retratoNo.hidden = false;
    }

    // Un mazo puede no existir (borrado después del duelo): el bloque se va.
    n.mazoBloque.hidden = !lado.mazo;
    if (lado.mazo) {
      n.mazo.textContent = lado.mazo;
      n.mazo.title = lado.mazo;      // el nombre completo, si se ha recortado
    }
    n.formacion.textContent = lado.formacion || '';

    // ---- aumento
    if (lado.aumento) {
      n.aumento.hidden = false;
      n.aumPct.textContent = '+' + lado.aumento.porcentaje.toFixed(2).replace('.', ',') + ' %';
      n.aumStat.textContent = STATS[lado.aumento.stat] || lado.aumento.stat;
      n.aumDesc.textContent = EFECTO[lado.aumento.stat] || '';
      n.aumIco.innerHTML = '<i class="ph ' + (ICONO_STAT[lado.aumento.stat] || 'ph-lightning') + '"></i>';
    } else {
      n.aumento.hidden = true;
    }

    // ---- alineación: viene pintada del servidor, solo se enseña la que toca
    n.lineas.local.hidden     = lado.lado !== 'local';
    n.lineas.visitante.hidden = lado.lado !== 'visitante';
  }

  // ------------------------------------------------------------ la secuencia
  /**
   * Añade a la línea de tiempo la presentación de un lado.
   *
   * El orden es el del encargo y también el que tiene sentido leído en voz
   * alta: quién juega en casa, quién es, con qué mazo, con qué aumento y con
   * qué once. La alineación va SIEMPRE al final porque es lo que más tarda en
   * entrar y lo que se queda en pantalla mientras se pasa al otro equipo.
   */
  function escenaEquipo(tl, lado) {
    var desde = lado.lado === 'local' ? -1 : 1;   // por qué lado entra
    var dx = quieto ? 0 : 42 * desde;

    tl.call(function () {
      montarEquipo(lado);
      n.equipo.hidden = false;
      decir((lado.lado === 'local' ? 'Local: ' : 'Visitante: ') + lado.nombre
          + (lado.mazo ? '. Mazo ' + lado.mazo : '')
          + (lado.aumento ? '. Aumento ' + (STATS[lado.aumento.stat] || '') : '')
          + '. Formación ' + (lado.formacion || '') + '.');
    });

    // Entrada del bloque de identidad, escalonada.
    tl.fromTo([n.etiqueta, n.retrato, n.nombre],
      { opacity: 0, x: dx, filter: quieto ? 'none' : 'blur(6px)' },
      { opacity: 1, x: 0, filter: 'blur(0px)', duration: T.bloque,
        stagger: T.bloque * 0.45, ease: 'power3.out' });

    tl.fromTo(caja.querySelectorAll('.pres-dato'),
      { opacity: 0, y: quieto ? 0 : 10 },
      { opacity: 1, y: 0, duration: T.bloque, stagger: T.bloque * 0.3, ease: 'power2.out' },
      '-=' + (T.bloque * 0.4));

    /* EL AUMENTO SE DESTACA. Entra con un pequeño golpe de escala y un
       destello que se apaga solo: es la decisión que la persona acaba de
       tomar, y tiene que notarse que el juego se ha enterado. */
    if (lado.aumento) {
      tl.fromTo(n.aumento,
        { opacity: 0, scale: quieto ? 1 : .92, y: quieto ? 0 : 14 },
        { opacity: 1, scale: 1, y: 0, duration: T.bloque * 1.2,
          ease: quieto ? 'none' : 'back.out(2)' });
      if (!quieto) {
        tl.fromTo(n.aumHalo, { opacity: 0, scale: .6 },
          { opacity: 1, scale: 1, duration: .18, ease: 'power2.out' }, '-=' + (T.bloque * .6))
          .to(n.aumHalo, { opacity: 0, duration: .5, ease: 'power2.in' });
      }
    }

    // La alineación, línea a línea.
    var lineas = n.lineas[lado.lado].querySelectorAll('.pres-linea');
    /* La alineación empieza a entrar mientras el aumento todavía se está
       asentando. Encadenarlas de punta a punta sumaba casi un segundo muerto
       por lado sin que pasara nada en pantalla. */
    tl.fromTo(lineas,
      { opacity: 0, x: quieto ? 0 : dx * 1.4 },
      { opacity: 1, x: 0, duration: T.linea * 1.6, stagger: T.linea, ease: 'power3.out' },
      '-=' + (T.bloque * 1.2));

    // Un respiro para que se vea el equipo montado antes de pasar al otro.
    tl.to({}, { duration: T.entreEquipos });
  }

  /** Barrido de luz + salida del equipo. Es la transición entre los dos. */
  function transicion(tl) {
    if (!quieto) {
      tl.fromTo(n.barrido,
        { opacity: 0, x: '-30vw' },
        { opacity: 1, x: '130vw', duration: .55, ease: 'power2.inOut' }, '<')
        .set(n.barrido, { opacity: 0 });
    }
    tl.to(n.equipo, { opacity: 0, duration: T.cierre, ease: 'power2.in' }, '<')
      .set(n.equipo, { hidden: true, clearProps: 'opacity' });
  }

  function escenaVs(tl) {
    tl.call(function () {
      n.vsLocal.textContent = datos.local.nombre;
      n.vsVisit.textContent = datos.visitante.nombre;
      n.vsLocalImg.innerHTML = escudoHtml(datos.local);
      n.vsVisitImg.innerHTML = escudoHtml(datos.visitante);
      n.vs.hidden = false;
      decir(datos.local.nombre + ' contra ' + datos.visitante.nombre + '.');
    });

    var marca = caja.querySelector('.pres-vs-marca');
    tl.fromTo(caja.querySelectorAll('.pres-vs-lado'),
      { opacity: 0, x: function (i) { return quieto ? 0 : (i === 0 ? -70 : 70); } },
      { opacity: 1, x: 0, duration: T.vs * .6, ease: 'power3.out' })
      .fromTo(marca,
        { opacity: 0, scale: quieto ? 1 : 1.8 },
        { opacity: 1, scale: 1, duration: T.vs * .5, ease: quieto ? 'none' : 'back.out(1.8)' },
        '-=' + (T.vs * .3))
      .to({}, { duration: T.vs * .5 })
      .to(n.vs, { opacity: 0, duration: T.cierre, ease: 'power2.in' })
      .set(n.vs, { hidden: true, clearProps: 'opacity' });
  }

  /** El escudo del VS: foto de persona o icono de la máquina. */
  function escudoHtml(lado) {
    if (!lado.imagen) { return '<i class="ph ph-user" aria-hidden="true"></i>'; }
    var clase = lado.tipo === 'ia' ? ' class="es-icono"' : '';
    return '<img src="' + esc(lado.imagen) + '"' + clase + ' alt="">';
  }

  function escenaArranque(tl) {
    tl.call(function () {
      n.arranque.hidden = false;
      n.saltar.hidden = true;      // ya no hay nada que saltar
      decir('El partido comienza.');
    });

    tl.fromTo(caja.querySelectorAll('.pres-arranque-linea'),
      { scaleX: quieto ? 1 : 0, opacity: 0 },
      { scaleX: 1, opacity: 1, duration: T.arranque * .5, ease: 'power3.out' })
      .fromTo(caja.querySelector('.pres-arranque-texto'),
        { opacity: 0, letterSpacing: quieto ? '.12em' : '.4em' },
        { opacity: 1, letterSpacing: '.12em', duration: T.arranque * .7, ease: 'power2.out' },
        '-=' + (T.arranque * .3))
      .to({}, { duration: T.arranque * .45 });
  }

  // ------------------------------------------------------------------- API
  var linea = null;
  var terminada = false;

  /**
   * Lanza la presentación. Devuelve una promesa que se resuelve cuando termina
   * o cuando la saltan — quien la espera no tiene que distinguir los casos.
   *
   * Si algo falla por el camino se resuelve igual: el partido tiene que
   * empezar sí o sí.
   */
  function presentacion() {
    return new Promise(function (listo) {
      function acabar() {
        if (terminada) { return; }
        terminada = true;
        if (linea) { linea.kill(); }
        caja.hidden = true;
        document.removeEventListener('keydown', porTecla);
        listo();
      }

      function porTecla(e) { if (e.key === 'Escape') { acabar(); } }

      n.saltar.addEventListener('click', acabar);
      document.addEventListener('keydown', porTecla);

      /* ⚠️ LA RED DE SEGURIDAD SE ARMA LA PRIMERA, ANTES DE ESPERAR NADA.
         Esto es lo único que hay entre el jugador y su partido: si la precarga
         de imágenes se atasca —o el navegador estrangula los temporizadores
         por tener la pestaña de fondo, que es real y se ha visto midiendo—,
         pasado este plazo se corta y se juega igual. Estaba después de la
         precarga y ahí no servía de nada: un atasco EN la precarga se lo
         saltaba entero y dejaba al jugador mirando una pantalla negra sin
         partido detrás. */
      /* 20 s y no 15: la intro pasó a durar ~11 s y la precarga puede sumar
         2,5 más, así que el corte de seguridad de antes se le echaba encima y
         podía cortar una presentación que iba bien. Sigue por debajo de lo que
         espera el servidor (`partido_espera_seg`, subido a 22 en la 049). */
      setTimeout(acabar, 20000);

      /* Las alineaciones ya vienen pintadas del servidor, con `render_carta()`
         (partials/presentacion_duelo.php): son las cartas de verdad, con su
         plantilla y su arte, y sus imágenes las pide el navegador desde que
         cargó la página. Aquí no queda nada que montar. */
      precargarTodo().then(function () {
        if (terminada) { return; }
        arrancar(acabar);
      });
    }).catch(function () { caja.hidden = true; });
  }

  /** Monta y lanza la línea de tiempo. Se llama con todo ya en su sitio. */
  function arrancar(acabar) {
    caja.hidden = false;
    caja.classList.toggle('es-quieto', !!quieto);
    n.saltar.focus({ preventScroll: true });

    linea = gsap.timeline({ onComplete: acabar });

    // FASE 1 — la pantalla entra.
    linea.fromTo(caja, { opacity: 0 }, { opacity: 1, duration: T.entrada, ease: 'power2.out' });

    escenaEquipo(linea, datos.local);
    transicion(linea);
    escenaEquipo(linea, datos.visitante);
    transicion(linea);
    escenaVs(linea);
    escenaArranque(linea);

    // Y se apaga dando paso al partido.
    linea.to(caja, { opacity: 0, duration: T.cierre, ease: 'power2.in' });

    /* Segunda red, más ajustada que la de 15 s: GSAP corre sobre
       requestAnimationFrame, que NO se ejecuta con la pestaña en segundo
       plano. Quien lance el partido y se cambie de pestaña volvería a una
       intro congelada. Pasado el doble de lo que debería durar, se corta. */
    setTimeout(acabar, (linea.duration() * 2 + 3) * 1000);
  }

  /* ⚠️ UNA SOLA INTRO, LA PIDA QUIEN LA PIDA. Desde que el motor de partido
     jugable tiene su propio cliente (`partido.js`), esta función la llaman DOS
     archivos en la misma carga: `duelo.js` (modal narrado viejo, vivo hasta la
     Task 17) y `partido.js`. Sin este memo, la segunda llamada montaba una
     segunda línea de tiempo sobre la misma caja y —peor— se quedaba colgada
     para siempre: `terminada` es de módulo, así que en cuanto una de las dos
     terminaba, el `acabar()` de la otra salía por la puerta de atrás sin
     resolver su promesa, y quien la esperaba (el sondeo del partido) no
     arrancaba nunca. Con el memo las dos esperan LA MISMA intro y las dos se
     enteran de que acabó. La animación en sí no cambia. */
  var enMarcha = null;
  window.SRF.presentacionPartido = function () {
    if (!enMarcha) { enMarcha = presentacion(); }
    return enMarcha;
  };
})();
