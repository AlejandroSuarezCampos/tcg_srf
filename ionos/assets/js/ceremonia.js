/* ==========================================================================
   CEREMONIA DE APERTURA DE SOBRES
   Tres escenas: el sobre se rasga → las cartas salen DE UNA EN UNA boca abajo
   y el jugador hace clic para voltearlas → resumen con todas.

   Las rarezas altas (legendaria=5, SRF=6) no se voltean sin más: al pedir el
   volteo se dispara antes una secuencia tipo "walkout" (oscurecido, rayos de
   luz girando, nombre de la rareza) y la carta se da la vuelta al final, con
   destello y temblor. Todo saltable.

   Orquestado con GSAP (vendorizado), sin build.

   Compromisos que no se negocian:
   · Saltable carta a carta ("Saltar carta") o de golpe ("Saltar todo").
   · Operable por teclado: la carta es un <button> real (Enter/Espacio).
   · Con prefers-reduced-motion no hay rasgado, walkout ni volteo animado:
     se va directo al resumen con todas las cartas ya reveladas.
   · El resultado se anuncia por una región aria-live.

   Expone SRF.ceremonia(cartas, sobre), donde cada carta es
   { nombre, rareza, id_rareza, html } — el `html` lo genera en servidor el
   mismo componente de tarjeta que usa el resto del sitio — y `sobre` es
   { nombre, imagen, frente, reverso } con las texturas de SU plantilla.
   Expone también el hook SRF.onExclusiveReveal(carta), vacío por defecto,
   listo para engancharle audio sin tocar esta timeline.
   ========================================================================== */
(function () {
  'use strict';

  var modal      = document.getElementById('modalSobre');
  var caja       = document.getElementById('ceremoniaCaja');
  var mesa       = document.getElementById('ceremoniaMesa');
  var anuncio    = document.getElementById('ceremoniaAnuncio');
  var escena     = document.getElementById('ceremoniaEscena');
  var cerSobre   = document.getElementById('cerSobre');
  var cerCarta   = document.getElementById('cerCarta');
  var cerFrente  = document.getElementById('cerCartaFrente');
  var walkout    = document.getElementById('cerWalkout');
  var walkoutRz  = document.getElementById('cerWalkoutRareza');
  var walkoutNom = document.getElementById('cerWalkoutNombre');
  var pista      = document.getElementById('ceremoniaPista');
  var contador   = document.getElementById('ceremoniaContador');
  var btnSaltarCarta = document.getElementById('ceremoniaSaltarCarta');
  var btnSaltar      = document.getElementById('ceremoniaSaltar');
  var btnSaltarEscena = document.getElementById('cerSaltarEscena');
  var avisoMotion    = document.getElementById('cerAvisoMotion');
  var btnActivarMotion  = document.getElementById('cerActivarMotion');
  var btnRechazarMotion = document.getElementById('cerRechazarMotion');
  if (!mesa || !cerCarta) return;

  // Se consulta EN CADA APERTURA, no una vez al cargar: cambiar la preferencia
  // (la del sistema o la propia de configuracion.php) surte efecto sin
  // recargar. SRF.movimientoReducido vive en ui.js, que se carga antes.
  // La define partials/head.php inline, antes que cualquier script externo.
  function reducido() {
    return SRF.movimientoReducido();
  }

  var RZ_COLOR = {
    1: 'var(--rz1)', 2: 'var(--rz2)', 3: 'var(--rz3)',
    4: 'var(--rz4)', 5: 'var(--rz5)', 6: 'var(--rz6)'
  };

  // Estado de la sesión de apertura en curso
  var cartas = [];
  var indice = 0;
  var tlActual = null;       // timeline de la escena en curso (saltable)
  var esperandoClic = false; // true mientras la carta espera el clic del jugador
  var cartaRevelada = false; // la carta actual ya está boca arriba
  var saltandoTodo = false;
  var avanzar = null;        // resolve() de la carta actual
  var sobreActual = null;    // para poder repetir la apertura con animación
  var enReparto = false;     // false mientras se abre el sobre, true al repartir

  // Cierra el modal (X, Escape, "Continuar") a media ceremonia: detener() no
  // puede cancelar de verdad la promesa pendiente de escenaCarta() (avanzar),
  // así que repartir() la resuelve y sigue corriendo en segundo plano hasta
  // llamar a terminar() igualmente. Si para entonces ya se ha abierto un
  // sobre NUEVO, ese terminar() tardío pisaba su estado (ocultaba su escena,
  // repintaba la mesa con las cartas viejas, podía reabrir el aviso de
  // movimiento). Cada llamada a ceremonia() saca un número de sesión nuevo;
  // repartir() comprueba que sigue siendo la vigente antes de cerrar nada.
  var sesion = 0;

  /* ---------------------------------------------------------------------
     Utilidades
     --------------------------------------------------------------------- */
  function destelloPantalla() {
    var d = document.createElement('div');
    d.className = 'ceremonia-destello';
    document.body.appendChild(d);
    d.addEventListener('animationend', function () { d.remove(); });
  }

  function matarTimeline() {
    if (tlActual) { tlActual.kill(); tlActual = null; }
  }

  /* ---------------------------------------------------------------------
     APERTURA DEL SOBRE — el sobre aparece con SU textura, se le arranca la
     tira de arriba y SE QUEDA en pantalla, abierto, para que las cartas
     salgan de él. Nada de partirlo en dos y hacerlo desaparecer.
     --------------------------------------------------------------------- */
  // El centrado del sobre vive aquí, no en el CSS: ver nota en components.css.
  var SOBRE_BASE = { xPercent: -50, yPercent: -50 };

  var sobreCuerpo, sobreTira, sobreBoca, sobreBrillo, sobreLuz, sobreFlecos, sobreCorte;

  function partesSobre() {
    sobreCuerpo = sobreCuerpo || cerSobre.querySelector('.cer-sobre-cuerpo');
    sobreTira   = sobreTira   || cerSobre.querySelector('.cer-sobre-tira');
    sobreBoca   = sobreBoca   || cerSobre.querySelector('.cer-sobre-boca');
    sobreBrillo = sobreBrillo || cerSobre.querySelector('.cer-sobre-brillo');
    sobreLuz    = sobreLuz    || cerSobre.querySelector('.cer-sobre-luz');
    sobreFlecos = sobreFlecos || cerSobre.querySelector('.cer-sobre-flecos');
    sobreCorte  = sobreCorte  || cerSobre.querySelector('.cer-sobre-corte');
  }

  /* ---------------------------------------------------------------------
     GEOMETRÍA CARTA↔SOBRE — medida, no escrita a mano.

     Aquí había píxeles fijos (`y: 150`, `scale: .58`, `y: -34`) y ninguno
     cuadraba, porque las dos piezas se dimensionan con `clamp(..vh..)` y su
     tamaño real depende de la pantalla. Lo que se veía:
       · la carta "escondida dentro del sobre" asomaba 79px por DEBAJO —el
         bloque a rayas que salía colgando bajo el sobre en la apertura—;
       · y en reposo la carta se quedaba 22px por encima del borde inferior
         del sobre, así que la banda termosellada de abajo cruzaba la carta
         como si fuera un código de barras pegado encima.

     Con las alturas reales (`offsetHeight`, que es la de maquetación y no
     lleva transformaciones) las tres posiciones salen solas y son correctas
     en cualquier pantalla.
     --------------------------------------------------------------------- */
  var Y_SOBRE_ABIERTO = 14;   // dónde se asienta el sobre tras rasgarlo

  function geometriaCarta() {
    var hSobre = cerSobre.offsetHeight;
    var hCarta = cerCarta.offsetHeight;
    if (!hSobre || !hCarta) {   // por si se pide antes de maquetar
      return { dentro: .58, oculta: Y_SOBRE_ABIERTO, asoma: -90, reposo: Y_SOBRE_ABIERTO };
    }

    // Escala a la que la carta cabe ENTERA detrás del sobre. El 84% deja
    // margen: las dos proporciones no son idénticas y el sobre tiene que
    // taparla también de ancho.
    var dentro = Math.min(0.62, (hSobre * 0.84) / hCarta);

    // Asomando: su borde superior sobrepasa el del sobre en un 10% de la
    // altura del sobre. Menos y no se ve salir; más y sale de golpe.
    var asoma = Y_SOBRE_ABIERTO - hSobre * 0.60 + (hCarta * dentro) / 2;

    /* En reposo la carta va CENTRADA en el sobre. Es la única posición en la
       que lo tapa entero por arriba y por abajo —la carta es más alta y más
       ancha que el sobre a escala 1—, y taparlo entero es justo lo que hace
       falta para que no asome ni la banda de sellado ni el filo. */
    return { dentro: dentro, oculta: Y_SOBRE_ABIERTO, asoma: asoma, reposo: Y_SOBRE_ABIERTO };
  }

  /* Los trocitos de plástico que saltan del corte. Se crean al vuelo y se
     tiran al terminar: son ocho nodos que viven medio segundo, y tenerlos
     siempre en el DOM solo para animarlos una vez por sobre no sale a cuenta.
     Van dentro del sobre, así que heredan su transform y saltan desde el
     punto correcto por muy grande o pequeña que sea la pantalla. */
  function lanzarFlecos() {
    if (!sobreFlecos) return;
    sobreFlecos.innerHTML = '';

    var trozos = [];
    for (var i = 0; i < 8; i++) {
      var f = document.createElement('span');
      f.className = 'cer-fleco';
      // repartidos a lo ancho del corte, no todos del mismo punto
      f.style.left = (6 + i * 11) + '%';
      sobreFlecos.appendChild(f);
      trozos.push(f);
    }

    gsap.set(trozos, { opacity: 1, x: 0, y: 0, rotate: 0, scale: 1 });
    gsap.to(trozos, {
      // cada fleco sale por su lado: sin el azar se ve una fila de copias
      x: function () { return gsap.utils.random(-70, 70); },
      y: function () { return gsap.utils.random(-90, -20); },
      rotate: function () { return gsap.utils.random(-220, 220); },
      scale: function () { return gsap.utils.random(.4, 1); },
      opacity: 0,
      duration: function () { return gsap.utils.random(.45, .8); },
      ease: 'power2.out',
      stagger: .015,
      onComplete: function () { if (sobreFlecos) sobreFlecos.innerHTML = ''; }
    });
  }

  /* Modo inmersivo: mientras dura la apertura el modal se queda sin caja,
     cabecera ni pie — solo el fondo negro y el sobre. Al llegar al resumen
     vuelve el modal normal, con su título y su botón de Continuar. */
  function inmersivo(activo) {
    modal.classList.toggle('es-inmersiva', !!activo);
  }

  function abrirSobre(sobre) {
    return new Promise(function (resolve) {
      partesSobre();

      var textura = (sobre && (sobre.frente || sobre.imagen)) || '';
      var url = textura ? "url('" + textura + "')" : '';
      sobreCuerpo.style.backgroundImage = url;
      sobreTira.style.backgroundImage   = url;

      // SOBRE_BASE lleva el centrado: GSAP es dueño del transform del sobre
      gsap.set(cerSobre,  Object.assign({}, SOBRE_BASE, {
        scale: .62, opacity: 0, rotateY: -26, rotateX: 6, y: 30
      }));
      gsap.set(sobreTira, { clearProps: 'transform', opacity: 1 });
      gsap.set(sobreBoca, { opacity: 1 });
      gsap.set(sobreLuz,  { opacity: 0 });
      gsap.set(sobreCorte, { opacity: 0 });
      gsap.set(sobreBrillo, { opacity: 0, xPercent: -60 });
      // El desgarro empieza a cero. La boca y la luz están recortadas contra
      // esta variable (components.css), así que a 0% no se ve nada de las dos
      // aunque tengan opacidad: no hace falta apagarlas por separado.
      gsap.set(escena, { '--cer-rasgado': '0%' });
      if (sobreFlecos) sobreFlecos.innerHTML = '';

      var tl = gsap.timeline({
        onComplete: function () { tlActual = null; resolve(); }
      });
      tlActual = tl;

      /* EL RASGADO, EN CINCO TIEMPOS.

         Lo que hacía que la versión anterior no se leyera como rasgar era que
         la tira giraba entera de golpe, como una tapa con bisagra, y la boca
         aparecía a la vez de un extremo al otro. Un plástico no se abre así:
         cede primero por una esquina y el corte CORRE hacia el otro lado.
         Por eso ahora la esquina izquierda se levanta sola, y solo después
         avanza `--cer-rasgado` de 0 a 100 arrastrando consigo la boca, la luz
         de dentro y el despegue de la tira. */
      tl
        // 1. el sobre entra girando hasta ponerse de frente
        .to(cerSobre, { scale: 1, opacity: 1, rotateY: 0, rotateX: 0, y: 0,
                        duration: .75, ease: 'back.out(1.35)' })
        // 2. barrido de luz sobre el plástico
        .to(sobreBrillo, { opacity: 1, xPercent: 60, duration: .7, ease: 'power2.inOut' }, '-=.35')
        .to(sobreBrillo, { opacity: 0, duration: .25 }, '-=.15')

        /* 3. TENSIÓN. Se tira del plástico y NO cede: dos intentos, el segundo
              más largo, para que el tercero se sienta ganado. El sobre se
              estrecha mientras se estira (scaleX baja cuando scaleY sube), que
              es lo que hace un film tensado y no una goma. */
        .to(cerSobre,  { scaleY: 1.015, scaleX: .992, duration: .16, ease: 'power2.out' })
        .to(sobreTira, { y: -2, rotate: -1.2, duration: .16, ease: 'power2.out' }, '<')
        .to([cerSobre, sobreTira], { scaleY: 1, scaleX: 1, y: 0, rotate: 0, duration: .12, ease: 'power2.in' })
        .to(cerSobre,  { scaleY: 1.024, scaleX: .986, duration: .22, ease: 'power2.out' })
        .to(sobreTira, { y: -3, rotate: -2, duration: .22, ease: 'power2.out' }, '<')

        /* 4. CEDE POR LA ESQUINA. Se levanta una PUNTA, no la banda entera: de
              ahí el `skewY` junto al `rotate`, con la bisagra a la derecha. Y
              el sobre suelta de golpe la deformación del tirón — sin esto se
              quedaba estirado el resto de la ceremonia (medido: scaleX .988 /
              scaleY 1.022 congelados hasta el resumen). */
        .to(cerSobre,  { scaleX: 1, scaleY: 1, duration: .12, ease: 'power3.out' })
        .to(sobreTira, { rotate: -6, skewY: -5, y: -4, x: -3, duration: .12, ease: 'power3.out' }, '<')
        .call(lanzarFlecos)
        .to(sobreCorte, { opacity: 1, duration: .1 }, '<')

        /* 5. EL CORTE CORRE de izquierda a derecha.
              `power1.in`: un desgarro arranca despacio y se acelera cuando ya
              ha empezado; con una curva lineal parecía una persiana bajando.
              Todo lo de este tramo cuelga de `--cer-rasgado`, así que la boca,
              la luz de dentro y la mancha blanca de tensión van clavadas entre
              sí sin sincronizar nada a mano. */
        .to(escena, { '--cer-rasgado': '100%', duration: .5, ease: 'power1.in' })
        //    la luz del interior asoma por donde ya está roto
        .to(sobreLuz, { opacity: 1, duration: .42, ease: 'power2.out' }, '<+=.08')
        /*  La tira se despega SIN irse de canto. Aquí estaba lo que se veía
            "como un palo": con `rotateX: 58` una banda de 26px de alto queda
            casi de perfil y en pantalla es una línea diagonal. Se queda en 26°
            —lo justo para que se lea el volumen— y el enrollado lo hace el
            `scaleY`, que la comprime como se comprime un plástico al curvarse. */
        .to(sobreTira, { rotateX: 26, rotate: -14, scaleY: .82, y: -8,
                         duration: .44, ease: 'power1.in' }, '<')
        //    el sobre acusa el tirón
        .to(cerSobre, { rotate: 1.6, duration: .22, yoyo: true, repeat: 1, ease: 'sine.inOut' }, '<')
        //    al llegar al final ya no hay nada que tensar
        .to(sobreCorte, { opacity: 0, duration: .18 }, '>-.16')

        /* 6. La tira, suelta del todo, cae girando. Se va hacia ABAJO y no
              hacia arriba: nadie tira de ella ya, la suelta la mano. */
        .to(sobreTira, { y: 120, x: 54, rotate: -46, rotateX: 52, scaleY: .7, opacity: 0,
                         duration: .55, ease: 'power2.in' })
        //    el resplandor del interior se calma hasta un rescoldo: la boca
        //    tiene que seguir insinuando que dentro hay algo
        .to(sobreLuz, { opacity: .5, duration: .45, ease: 'power2.out' }, '<+=.1')

        /* 7. Respiro: el sobre abierto se asienta para dejar sitio arriba a las
              cartas que van a salir. */
        .to(cerSobre, { y: Y_SOBRE_ABIERTO, duration: .4, ease: 'power2.out' }, '-=.35');
    });
  }

  function saltarAperturaSobre() {
    matarTimeline();
    partesSobre();
    gsap.set(cerSobre, Object.assign({}, SOBRE_BASE, {
      scale: 1, opacity: 1, rotateY: 0, rotateX: 0, rotate: 0,
      scaleX: 1, scaleY: 1, y: Y_SOBRE_ABIERTO
    }));
    gsap.set(sobreTira, { opacity: 0 });
    gsap.set(sobreBoca, { opacity: 1 });
    gsap.set(sobreLuz, { opacity: .5 });
    gsap.set(sobreCorte, { opacity: 0 });
    gsap.set(sobreBrillo, { opacity: 0 });
    // El sobre queda abierto DEL TODO: si el rasgado se quedara a medias, la
    // boca saldría recortada y las cartas parecerían atravesar el plástico.
    gsap.set(escena, { '--cer-rasgado': '100%' });
    if (sobreFlecos) sobreFlecos.innerHTML = '';
  }

  /* ---------------------------------------------------------------------
     ESCENA 2 — una carta: aparece boca abajo y ESPERA EL CLIC.
     --------------------------------------------------------------------- */
  // Estado base de la carta. xPercent/yPercent hacen el centrado DENTRO del
  // transform de GSAP: si se dejara translate(-50%,-50%) en el CSS, el primer
  // tween lo machacaría y la carta saltaría de sitio.
  var CARTA_BASE = { xPercent: -50, yPercent: -50, rotationY: 0, rotate: 0 };

  function pintarDorso(carta) {
    cerCarta.dataset.rareza = carta.id_rareza;
    cerCarta.style.setProperty('--rz-aura', RZ_COLOR[carta.id_rareza] || 'var(--amber)');
    cerFrente.innerHTML = carta.html;
    cerCarta.disabled = false;
    cartaRevelada = false;
    contador.textContent = (indice + 1) + ' / ' + cartas.length;
    pista.textContent = 'Toca la carta para darle la vuelta';
    pista.hidden = false;
  }

  /* La carta arranca DENTRO del sobre (y positiva, z-index por debajo del
     cuerpo) y sube hasta quedar centrada delante. El cambio de z-index a
     mitad de subida es lo que vende que "sale de dentro". */
  function escenaCarta(carta) {
    return new Promise(function (resolve) {
      avanzar = resolve;
      pintarDorso(carta);

      var g = geometriaCarta();

      // Arranca CENTRADA en el sobre y a la escala a la que cabe entera detrás
      // de él: así, mientras está "dentro", no asoma por ningún borde.
      gsap.set(cerCarta, Object.assign({}, CARTA_BASE, {
        opacity: 1, scale: g.dentro, y: g.oculta, zIndex: 2
      }));
      gsap.set(cerCarta.querySelector('.cer-carta-aura'), { opacity: 0 });

      var tl = gsap.timeline({
        onComplete: function () { tlActual = null; esperandoClic = true; }
      });
      tlActual = tl;

      /* Sube hasta asomar por la boca SIN cambiar de escala: mientras esté
         detrás del sobre no puede crecer, o sus bordes saldrían por los lados
         y se leería como si atravesara el plástico. El z-index cambia justo
         cuando ya asoma, que es el instante en que el salto no se nota. */
      tl.to(cerCarta, { y: g.asoma, duration: .38, ease: 'power2.out' })
        .set(cerCarta, { zIndex: 6 })
        .to(cerCarta, { y: g.reposo, scale: 1, duration: .62, ease: 'power3.out' })
        // pequeño balanceo al asentarse, para que no parezca un sprite pegado
        .to(cerCarta, { rotate: 1.6, duration: .18, yoyo: true, repeat: 1, ease: 'sine.inOut' }, '-=.16');
    });
  }

  /* ---------------------------------------------------------------------
     El volteo. Para rareza >= 5 se antepone el walkout.
     --------------------------------------------------------------------- */
  /* Marca la carta como revelada. El GIRO en sí lo hace GSAP (rotationY), no
     una clase: GSAP es el dueño del transform de .cer-carta y su estilo en
     línea le ganaría siempre a cualquier regla de clase — así es como la
     carta se quedaba sin voltear aunque tuviera la clase puesta. */
  function voltearAhora(carta) {
    cartaRevelada = true;
    pista.textContent = 'Toca otra vez para continuar';
    // pedirVolteo() esconde la pista al empezar el giro (estorba durante la
    // animación y durante el walkout). Hay que volver a mostrarla AQUÍ: sin
    // esto se cambiaba el texto de un elemento que seguía con [hidden], así
    // que la carta se quedaba destapada y sin ninguna indicación de que hay
    // que tocarla otra vez para sacar la siguiente — la ceremonia parecía
    // colgada justo en el momento que el jugador tiene que actuar.
    pista.hidden = false;
    if (carta.id_rareza >= 5 && typeof SRF.onExclusiveReveal === 'function') {
      SRF.onExclusiveReveal(carta);
    }
  }

  function pedirVolteo(carta) {
    if (!esperandoClic) return;
    esperandoClic = false;
    pista.hidden = true;

    var exclusiva = carta.id_rareza >= 5;
    var aura = cerCarta.querySelector('.cer-carta-aura');

    var tl = gsap.timeline({
      onComplete: function () { tlActual = null; esperandoClic = true; }
    });
    tlActual = tl;

    if (!exclusiva) {
      tl.to(aura, { opacity: 1, scale: 1.15, duration: .22, ease: 'power1.out' })
        // el giro: se levanta un poco al voltear, como una carta de verdad
        .to(cerCarta, { scale: 1.06, duration: .3, ease: 'power2.out' }, '<')
        .to(cerCarta, { rotationY: 180, duration: .62, ease: 'power2.inOut' }, '<+=.1')
        .call(function () { voltearAhora(carta); }, [], '<+=.31')
        .to(cerCarta, { scale: 1, duration: .3, ease: 'power2.out' })
        .to(aura, { opacity: 0, duration: .4 }, '<');
      return;
    }

    // ---- WALKOUT (rareza 5 y 6) ----
    walkout.hidden = false;
    walkoutRz.textContent = carta.rareza;
    if (walkoutNom) walkoutNom.textContent = carta.nombre || '';
    walkout.style.setProperty('--rz-aura', RZ_COLOR[carta.id_rareza] || 'var(--amber)');
    caja.classList.add('en-walkout');

    var rayos = walkout.querySelector('.cer-walkout-rayos');
    var texto = walkout.querySelector('.cer-walkout-texto');

    tl.set(walkout, { opacity: 0 })
      .set([rayos, texto], { opacity: 0 })
      // la carta se hunde y se oscurece mientras sube la tensión
      .to(cerCarta, { scale: .82, duration: .5, ease: 'power2.out' }, 0)
      .to(walkout, { opacity: 1, duration: .45 }, 0)
      .to(rayos, { opacity: 1, duration: .6 }, '<')
      .to(rayos, { rotate: 360, duration: 3.4, ease: 'none' }, '<')
      .fromTo(texto, { opacity: 0, scale: .75, y: 14 },
        { opacity: 1, scale: 1, y: 0, duration: .55, ease: 'back.out(1.7)' }, '<+=.35')
      // latido del texto: es el "aguanta la respiración" del walkout
      .to(texto, { scale: 1.06, duration: .5, yoyo: true, repeat: 2, ease: 'sine.inOut' })
      .to(texto, { opacity: 0, scale: 1.35, duration: .35, ease: 'power2.in' })
      .to(aura, { opacity: 1, scale: 1.5, duration: .3 }, '<')
      // el giro, amplificado: la carta se lanza hacia cámara mientras rota
      .to(cerCarta, { scale: 1.18, duration: .45, ease: 'power2.out' }, '<')
      .to(cerCarta, { rotationY: 180, duration: .8, ease: 'power2.inOut' }, '<')
      .call(function () { voltearAhora(carta); destelloPantalla(); }, [], '<+=.4')
      // temblor de pantalla al destaparse
      .to(caja, { x: -9, duration: .05, yoyo: true, repeat: 7, ease: 'none' }, '<')
      .to(cerCarta, { scale: 1, duration: .5, ease: 'power2.out' }, '<')
      .to(walkout, { opacity: 0, duration: .5 }, '<+=.25')
      .to(aura, { opacity: 0, duration: .6 }, '<')
      .call(function () {
        walkout.hidden = true;
        caja.classList.remove('en-walkout');
        gsap.set(caja, { clearProps: 'transform' });
      });
  }

  /* Deja la carta actual en su estado final (fuera del sobre y ya girada),
     sin animación. Lo usan "Saltar carta" y "Saltar todo". */
  function finalizarCartaActual() {
    matarTimeline();
    walkout.hidden = true;
    caja.classList.remove('en-walkout');
    gsap.set(caja, { clearProps: 'transform' });
    gsap.set(cerCarta, Object.assign({}, CARTA_BASE, {
      opacity: 1, scale: 1, y: geometriaCarta().reposo, zIndex: 6, rotationY: 180
    }));
    gsap.set(cerCarta.querySelector('.cer-carta-aura'), { opacity: 0 });
    if (!cartaRevelada) voltearAhora(cartas[indice]);
  }

  /* ---------------------------------------------------------------------
     Clic / teclado sobre la carta: primero la voltea, después avanza.
     --------------------------------------------------------------------- */
  cerCarta.addEventListener('click', function () {
    if (saltandoTodo) return;
    if (!cartaRevelada) { pedirVolteo(cartas[indice]); return; }
    if (!esperandoClic) return;      // walkout aún en curso
    siguienteCarta();
  });

  function siguienteCarta() {
    esperandoClic = false;
    if (!avanzar) return;
    var r = avanzar;
    avanzar = null;
    // la carta vista se aparta hacia un lado y se desvanece, dejando el sobre
    // libre para la siguiente
    if (reducido()) { r(); return; }
    gsap.to(cerCarta, {
      opacity: 0, scale: .82, y: -120, x: 130, rotate: 12,
      duration: .38, ease: 'power2.in',
      onComplete: function () { gsap.set(cerCarta, { x: 0, rotate: 0 }); r(); }
    });
  }

  /* ---------------------------------------------------------------------
     ESCENA 3 — resumen: todas las cartas ya reveladas
     --------------------------------------------------------------------- */
  function pintarMesa() {
    mesa.innerHTML = '';
    cartas.forEach(function (carta) {
      var ranura = document.createElement('div');
      ranura.className = 'ranura esta-volteada';
      ranura.dataset.rareza = carta.id_rareza;

      var frente = document.createElement('div');
      frente.className = 'ranura-cara ranura-frente';
      frente.innerHTML = carta.html;

      ranura.appendChild(frente);
      mesa.appendChild(ranura);
    });
    mesa.hidden = false;
    if (!reducido()) {
      gsap.fromTo(mesa.children,
        { opacity: 0, y: 16 },
        {
          opacity: 1, y: 0, duration: .3, stagger: .04, ease: 'power2.out',
          onComplete: function () { gsap.set(mesa.children, { clearProps: 'transform,opacity' }); }
        });
    }
  }

  function anunciar() {
    var texto = cartas.map(function (c) { return c.nombre + ', ' + c.rareza; }).join('. ');
    anuncio.textContent = 'Has conseguido ' + cartas.length +
      (cartas.length === 1 ? ' carta: ' : ' cartas: ') + texto + '.';
  }

  /* El aviso de "puedes activar las animaciones" solo tiene sentido si:
       · la ceremonia se ha saltado de verdad (movimiento reducido), Y
       · el jugador NO ha elegido nada todavía en esta web.
     En cuanto elige cualquier cosa —'si' desde configuracion.php o 'no' desde
     el propio aviso— no vuelve a aparecer nunca. */
  function ofrecerAnimaciones() {
    if (!avisoMotion) return;
    var yaEligio = !!SRF.preferenciaMovimiento();
    avisoMotion.hidden = yaEligio || !reducido();
  }

  function terminar() {
    escena.hidden = true;
    enReparto = false;
    inmersivo(false);
    pintarMesa();
    anunciar();
    btnSaltarCarta.disabled = true;
    btnSaltar.disabled = true;
    ofrecerAnimaciones();
  }

  if (btnActivarMotion) {
    btnActivarMotion.addEventListener('click', function () {
      SRF.fijarPreferenciaMovimiento('si');
      avisoMotion.hidden = true;
      // se repite la apertura, ahora sí con ceremonia: las cartas ya están
      // en la colección, esto solo vuelve a reproducir el espectáculo
      ceremonia(cartas, sobreActual);
    });
  }
  if (btnRechazarMotion) {
    btnRechazarMotion.addEventListener('click', function () {
      SRF.fijarPreferenciaMovimiento('no');
      avisoMotion.hidden = true;
    });
  }

  /* ---------------------------------------------------------------------
     Orquestación
     --------------------------------------------------------------------- */
  async function repartir(miSesion) {
    for (indice = 0; indice < cartas.length; indice++) {
      if (saltandoTodo) break;
      await escenaCarta(cartas[indice]);
    }
    if (miSesion !== sesion) return;  // el modal se cerró y ya hay otra ceremonia en curso
    terminar();
  }

  function ceremonia(listaCartas, sobre) {
    if (!listaCartas || !listaCartas.length) return;

    var miSesion = ++sesion;

    cartas = listaCartas;
    sobreActual = sobre || null;
    indice = 0;
    saltandoTodo = false;
    enReparto = false;
    esperandoClic = false;
    cartaRevelada = false;
    avanzar = null;
    matarTimeline();

    mesa.innerHTML = '';
    mesa.hidden = true;
    escena.hidden = true;
    walkout.hidden = true;
    inmersivo(false);

    /* ⚠️ LA CARTA SE APAGA AQUÍ, Y NO ES REDUNDANTE.
       GSAP deja sus valores escritos en el estilo EN LÍNEA del elemento, y la
       carta es una sola —se reutiliza para las cinco del sobre y para todos los
       sobres siguientes—. Al usar «Saltar todo», `terminar()` esconde la escena
       con la carta todavía en `opacity: 1` y `rotationY: 0`, o sea boca abajo.
       Al abrir el sobre SIGUIENTE, la escena se vuelve a enseñar con ese estilo
       intacto: aparecía una carta dada la vuelta flotando sobre el sobre sin
       abrir. Se reinicia al empezar cada ceremonia, que es el único punto por
       el que pasan todos los caminos. */
    partesSobre();
    gsap.set(cerCarta, Object.assign({}, CARTA_BASE, { opacity: 0, zIndex: 2 }));
    gsap.set(cerCarta.querySelector('.cer-carta-aura'), { opacity: 0 });
    if (avisoMotion) avisoMotion.hidden = true;
    caja.classList.remove('es-legendario', 'es-srf', 'en-walkout');

    var maxRareza = cartas.reduce(function (m, c) { return Math.max(m, c.id_rareza); }, 1);
    if (maxRareza === 5) caja.classList.add('es-legendario');
    if (maxRareza === 6) caja.classList.add('es-srf');

    SRF.abrirModal('modalSobre');

    if (reducido()) {
      terminar();
      return;
    }

    btnSaltarCarta.disabled = false;
    btnSaltar.disabled = false;

    escena.hidden = false;
    inmersivo(true);
    abrirSobre(sobre).then(function () {
      if (miSesion !== sesion) return;
      enReparto = true;
      repartir(miSesion);
    });
  }

  /* ---- saltos ---- */
  btnSaltarCarta.addEventListener('click', function () {
    if (saltandoTodo || escena.hidden) return;
    // aún rasgando el sobre: lo que se salta es la apertura, no una carta
    if (!enReparto) { saltarAperturaSobre(); return; }
    if (!cartaRevelada) { finalizarCartaActual(); esperandoClic = true; return; }
    siguienteCarta();
  });

  function saltarTodo() {
    saltandoTodo = true;
    matarTimeline();
    walkout.hidden = true;
    caja.classList.remove('en-walkout');
    gsap.set(caja, { clearProps: 'transform' });
    if (avanzar) { var r = avanzar; avanzar = null; r(); }
    else terminar();
  }

  btnSaltar.addEventListener('click', saltarTodo);
  // el mismo salto, desde el único botón visible durante la escena
  if (btnSaltarEscena) btnSaltarEscena.addEventListener('click', saltarTodo);

  /* al cerrar el modal se corta cualquier animación pendiente */
  function detener() {
    saltandoTodo = true;
    matarTimeline();
    walkout.hidden = true;
    caja.classList.remove('en-walkout');
    gsap.set(caja, { clearProps: 'transform' });
    if (avanzar) { var r = avanzar; avanzar = null; r(); }
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-cerrar-modal]')) detener();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal && !modal.hasAttribute('hidden')) detener();
  });

  SRF.onExclusiveReveal = SRF.onExclusiveReveal || function () {};
  SRF.ceremonia = ceremonia;
})();
