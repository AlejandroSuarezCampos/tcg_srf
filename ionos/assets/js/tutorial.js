/* ==========================================================================
   TUTORIAL DE BIENVENIDA — el motor.

   El guion vive en el servidor (Tcg::TUTORIAL_PASOS) y llega en
   `window.TUTORIAL`. Aquí solo se pinta, se mueve y se guarda por dónde va.

   Cómo funciona, que no es un carrusel:
   el tutorial RECORRE LA WEB. Cada paso pertenece a una pantalla, así que
   avanzar a veces significa navegar de verdad a otra página — y al cargarla,
   el tutorial se retoma donde estaba. Por eso el paso se guarda en el servidor
   en cada avance y no en memoria: entre un paso y el siguiente puede haber una
   recarga completa.

   Dos pasos exigen haber HECHO algo (montar el mazo titular, jugar un
   partido). Eso lo decide el servidor y llega en `logros`; aquí solo se
   bloquea el botón y se dice qué falta.
   ========================================================================== */
(function () {
  'use strict';

  var T = window.TUTORIAL;
  var caja = document.getElementById('tutorial');
  if (!T || !caja || !T.pasos || !T.pasos.length) return;

  var velo       = document.getElementById('tutorialVelo');
  var foco       = document.getElementById('tutorialFoco');
  var globo      = document.getElementById('tutorialGlobo');
  var elNumero   = document.getElementById('tutorialNumero');
  var elTitulo   = document.getElementById('tutorialTitulo');
  var elTexto    = document.getElementById('tutorialTexto');
  var elPendiente = document.getElementById('tutorialPendiente');
  var btnSaltar  = document.getElementById('tutorialSaltar');
  var btnAtras   = document.getElementById('tutorialAtras');
  var btnSig     = document.getElementById('tutorialSiguiente');
  var btnAccion  = document.getElementById('tutorialAccion');

  var indice = Math.max(0, T.pasos.findIndex(function (p) { return p.clave === T.paso; }));
  var objetivo = null;   // el elemento señalado ahora mismo

  var PENDIENTE = {
    sobre:   'Todavía no has abierto el sobre de bienvenida. Es gratis y te deja el once completo para el paso siguiente.',
    mazo:    'Todavía no tienes un mazo titular con los once huecos cubiertos. Móntalo aquí abajo y esto se desbloquea solo.',
    partido: 'Todavía no has jugado ningún partido de prueba. Pulsa el botón de aquí arriba y termina el encuentro.',
  };

  /* Cómo se llama cada pantalla, para el botón de "Ir a…". El guion trae la
     clave técnica de la página, no un nombre presentable. */
  var NOMBRE_PAGINA = {
    landing: 'Inicio', sobres: 'Sobres', coleccion: 'Colección', album: 'Álbum',
    mazos: 'Mazos', duelos: 'Duelos', cadenas: 'Cadenas', mercado: 'Mercado',
    misiones: 'Misiones',
  };

  function paso() { return T.pasos[indice]; }

  /** ¿Está cumplido lo que este paso exige? Sin requisito, siempre sí. */
  function cumplido(p) {
    return !p.requiere || !!T.logros[p.requiere];
  }

  /* --------------------------------------------------------------------
     Guardar el avance. Se manda y NO se espera: si la petición falla, lo
     peor que pasa es que al recargar el tutorial se retome un paso antes.
     Bloquear la interfaz por esto sería peor que el fallo.
     -------------------------------------------------------------------- */
  /**
   * Apunta en qué paso va. DEVUELVE la promesa: quien navegue después tiene
   * que esperarla.
   *
   * `keepalive` para el caso de que aun así se escape: el navegador termina de
   * mandarla aunque la página se esté cerrando.
   */
  function guardar(clave) {
    return fetch(T.url, {
      method: 'POST',
      keepalive: true,
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ accion: 'paso', csrf: SRF.csrfToken(), paso: clave }),
    }).catch(function () {});
  }

  function terminar(como) {
    caja.hidden = true;
    fetch(T.url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ accion: como, csrf: SRF.csrfToken() }),
    }).catch(function () {});
  }

  /* --------------------------------------------------------------------
     Colocar el globo y el foco sobre el elemento señalado.
     -------------------------------------------------------------------- */
  function buscarObjetivo(selector) {
    if (!selector) return null;
    /* El selector puede traer varias alternativas separadas por coma: se coge
       la PRIMERA que exista y se vea. Así un paso funciona igual en una página
       llena que en una vacía, donde el elemento fino no está y hay que caer al
       `main`. */
    var partes = selector.split(',');
    for (var i = 0; i < partes.length; i++) {
      var el = document.querySelector(partes[i].trim());
      if (!el) { continue; }
      var r = el.getBoundingClientRect();
      /* 24 px y no "más de cero": en móvil el menú de navegación está plegado
         con `max-height: 0`, pero su borde le deja 17 px de alto. Con el
         listón en cero se daba por bueno y el tutorial señalaba una raya de
         375x17 en vez de caer al botón de menú, que es lo que se ve y lo que
         hay que pulsar. */
      if (r.height >= 24 && r.width >= 24) { return el; }
    }
    return null;
  }

  /**
   * Deja lo señalado ARRIBA de la pantalla, no en el centro.
   *
   * Centrarlo parece lo natural y es justo lo que no funciona: con el objetivo
   * en mitad de la ventana no queda sitio ni encima ni debajo para el globo, y
   * acaba yéndose a una esquina y tapándolo. Dejándolo arriba, todo lo que hay
   * por debajo queda libre — que es donde el globo va a querer ponerse.
   *
   * Se descuenta la altura de la barra de navegación, que es fija: sin eso, lo
   * señalado se queda justo debajo de ella y el anillo sale medio tapado.
   */
  function acercarObjetivo(el) {
    var nav = document.querySelector('.nav');
    var alto = nav ? nav.getBoundingClientRect().height : 0;
    var destino = window.scrollY + el.getBoundingClientRect().top - alto - 20;
    window.scrollTo({ top: Math.max(0, destino), behavior: 'auto' });
  }

  /* En pantalla estrecha el globo lo coloca el CSS: es una hoja pegada abajo,
     a lo ancho. Buscarle un hueco "al lado" de lo señalado no tiene sentido
     cuando el ancho entero son 375 px —no cabe nada al lado de nada— y el JS
     escribiendo `top`/`left`/`max-height` en línea solo se pelearía con la
     hoja. Aquí se calculan el recorte y el anillo, que sí valen igual, y se
     deja el globo en paz. */
  function esEstrecho() { return window.innerWidth <= 640; }

  function colocar() {
    var margen = 8;

    if (!objetivo) {
      // Paso sin objetivo: globo centrado y velo entero, sin recorte.
      velo.style.clipPath = '';
      foco.hidden = true;
      globo.classList.add('esta-centrado');
      globo.style.top = '';
      globo.style.left = '';
      globo.style.maxHeight = '';
      return;
    }

    var r = objetivo.getBoundingClientRect();

    /* ¿SIGUE VIÉNDOSE LO QUE SEÑALAMOS?
       Si la persona se va con el scroll hasta perderlo de vista, seguir
       apuntando a sus coordenadas es apuntar fuera de la pantalla: el anillo
       se salía por arriba, el hueco del velo dejaba de coincidir con nada y el
       globo se iba detrás. Se veía exactamente como "se queda bugueado".
       Cuando pasa, se recoge el señalamiento y el globo se pega abajo hasta
       que lo señalado vuelva a entrar — `colocar()` corre en cada scroll, así
       que se recupera solo. */
    if (r.bottom <= 0 || r.top >= window.innerHeight ||
        r.right <= 0 || r.left >= window.innerWidth) {
      velo.style.clipPath = '';
      foco.hidden = true;
      globo.classList.remove('esta-centrado');
      globo.style.maxHeight = '';
      if (esEstrecho()) { globo.style.top = ''; globo.style.left = ''; return; }
      globo.style.top = (window.innerHeight - globo.offsetHeight - 8) + 'px';
      globo.style.left = (window.innerWidth - globo.offsetWidth - 8) + 'px';
      return;
    }

    var x = Math.max(0, r.left - margen);
    var y = Math.max(0, r.top - margen);
    var w = Math.min(window.innerWidth, r.width + margen * 2);
    var h = Math.min(window.innerHeight, r.height + margen * 2);

    /* El hueco del velo. `evenodd` con dos rectángulos: el de fuera cubre la
       pantalla y el de dentro la perfora. Es lo que deja ver lo señalado a su
       brillo real en vez de a través de una capa oscura. */
    velo.style.clipPath =
      'polygon(evenodd, 0 0, 100vw 0, 100vw 100vh, 0 100vh, 0 0, ' +
      x + 'px ' + y + 'px, ' + (x + w) + 'px ' + y + 'px, ' +
      (x + w) + 'px ' + (y + h) + 'px, ' + x + 'px ' + (y + h) + 'px, ' +
      x + 'px ' + y + 'px)';

    foco.hidden = false;
    foco.style.top = y + 'px';
    foco.style.left = x + 'px';
    foco.style.width = w + 'px';
    foco.style.height = h + 'px';

    globo.classList.remove('esta-centrado');
    globo.style.maxHeight = '';   // se remide limpio, sin el tope del paso anterior

    if (esEstrecho()) {
      // La hoja de abajo la coloca el CSS. Se limpia lo que hubiera escrito
      // una medida anterior en horizontal, que si no se queda pegado.
      globo.style.top = '';
      globo.style.left = '';
      return;
    }

    var alto = globo.offsetHeight || 200;
    var ancho = globo.offsetWidth || 320;
    var hueco = 12;
    var borde = 8;

    function cabe(t, l) {
      return t >= borde && l >= borde &&
             t + alto <= window.innerHeight - borde &&
             l + ancho <= window.innerWidth - borde;
    }
    function solapa(t, l) {
      return !(l + ancho <= x || l >= x + w || t + alto <= y || t >= y + h);
    }

    /* 1. Pegado a lo señalado, si cabe entero y sin taparlo. */
    var sitios = [
      { top: y + h + hueco,          left: r.left + r.width / 2 - ancho / 2 },
      { top: y - alto - hueco,       left: r.left + r.width / 2 - ancho / 2 },
      { top: y + h / 2 - alto / 2,   left: x + w + hueco },
      { top: y + h / 2 - alto / 2,   left: x - ancho - hueco },
    ];
    for (var i = 0; i < sitios.length; i++) {
      var t = sitios[i].top, l = sitios[i].left;
      // se centra sobre el objetivo pero sin salirse de la ventana
      l = Math.min(Math.max(borde, l), window.innerWidth - ancho - borde);
      if (cabe(t, l) && !solapa(t, l)) {
        globo.style.top = t + 'px';
        globo.style.left = l + 'px';
        return;
      }
    }

    /* 2. NO CABE ENTERO: se encoge a lo que quede libre encima o debajo.
       En una ventana estrecha —o con un móvil— el globo y una carta no caben
       lado a lado por pura aritmética: 320 del globo + 240 de la carta ya se
       pasan del ancho. Antes se iba a una esquina y acababa tapándola igual.
       Recortarlo es mejor que taparla: el texto ya se desplaza solo dentro del
       globo, así que no se pierde nada, y lo señalado se ve entero.
       El suelo de 150px es donde deja de ser legible; por debajo de eso no
       merece la pena y se pasa al plan C. */
    var suelo = 150;
    var libreDebajo = window.innerHeight - (y + h) - hueco - borde;
    var libreEncima = y - hueco - borde;
    var centrado = Math.min(
      Math.max(borde, r.left + r.width / 2 - ancho / 2),
      window.innerWidth - ancho - borde
    );

    if (libreDebajo >= suelo || libreEncima >= suelo) {
      var abajo = libreDebajo >= libreEncima;
      var tope = Math.min(alto, abajo ? libreDebajo : libreEncima);
      globo.style.maxHeight = tope + 'px';
      globo.style.top = (abajo ? y + h + hueco : y - tope - hueco) + 'px';
      globo.style.left = centrado + 'px';
      return;
    }

    /* 3. Ni encogido cabe: a la esquina que menos tape, NUNCA al centro.
       Centrarlo es exactamente lo que hacía que el globo se plantara encima de
       lo que estaba explicando —medido en el paso del sobre: tapaba el banner
       entero y el botón que pedía pulsar—. */
    var esquinas = [
      { top: window.innerHeight - alto - borde, left: window.innerWidth - ancho - borde },
      { top: borde,                             left: window.innerWidth - ancho - borde },
      { top: window.innerHeight - alto - borde, left: borde },
      { top: borde,                             left: borde },
    ];
    var mejor = null, mejorArea = Infinity;
    for (var j = 0; j < esquinas.length; j++) {
      var et = esquinas[j].top, el = esquinas[j].left;
      var ancho1 = Math.max(0, Math.min(el + ancho, x + w) - Math.max(el, x));
      var alto1  = Math.max(0, Math.min(et + alto, y + h) - Math.max(et, y));
      var area = ancho1 * alto1;
      if (area < mejorArea) { mejorArea = area; mejor = esquinas[j]; }
      if (area === 0) { break; }
    }
    globo.style.top = mejor.top + 'px';
    globo.style.left = mejor.left + 'px';
  }

  /* --------------------------------------------------------------------
     Pintar el paso actual
     -------------------------------------------------------------------- */
  /**
   * @param {boolean} navegar  true solo cuando el usuario ha pulsado
   *   Siguiente/Atrás. Al CARGAR una página nunca se navega sola: si el paso
   *   guardado fuera de otra pantalla, el tutorial devolvería al usuario allí
   *   en cuanto intentara ir a cualquier otro sitio, y quedaría atrapado en un
   *   bucle sin más salida que saltárselo. En vez de eso se le ofrece el
   *   camino con un botón y decide él.
   */
  function pintar(navegar) {
    var p = paso();
    var otraPantalla = p.pagina && T.pagina && p.pagina !== T.pagina;

    if (otraPantalla && navegar) {
      window.location.href = p.url;
      return;
    }

    caja.hidden = false;

    elNumero.textContent = indice + 1;
    elTitulo.textContent = p.titulo;
    elTexto.textContent = p.texto;

    /* En otra pantalla no hay nada que señalar: el globo va centrado y el
       único avance posible es ir a donde toca. */
    objetivo = otraPantalla ? null : buscarObjetivo(p.selector);
    if (objetivo) { acercarObjetivo(objetivo); }

    var falta = !cumplido(p);
    elPendiente.hidden = !falta;
    if (falta) { elPendiente.textContent = PENDIENTE[p.requiere] || ''; }

    /* MODO DISCRETO: sin velo mientras el paso pide HACER algo.
       Un paso con requisito sin cumplir no es una explicación, es un encargo:
       la persona tiene que abrir el sobre, montar el mazo o jugar el partido
       AHÍ MISMO. Oscurecer el 72 % de la pantalla justo entonces es tapar el
       sitio de trabajo — con el editor de mazos abierto, el tutorial se comía
       la lista de jugadores y el campo. El anillo se queda (solo es un
       contorno, no estorba) y el globo se encoge y se aparta. */
    caja.classList.toggle('es-discreto', falta);

    /* Los pasos con acción propia (crear el partido de prueba) enseñan su
       botón mientras el requisito siga sin cumplirse: una vez hecho, el botón
       sobra y solo invita a repetirlo. */
    if (btnAccion) {
      btnAccion.hidden = !(p.accion && falta && !otraPantalla);
      btnAccion.textContent = p.accion_texto || '';
      btnAccion.dataset.accion = p.accion || '';
    }

    if (otraPantalla) {
      btnSig.disabled = false;
      btnSig.textContent = 'Ir a ' + (NOMBRE_PAGINA[p.pagina] || 'la pantalla');
      btnIr = true;
    } else {
      btnSig.disabled = falta;
      btnSig.textContent = (indice === T.pasos.length - 1) ? 'Terminar' : 'Siguiente';
      btnIr = false;
    }
    btnAtras.hidden = indice === 0;
    vigilarRequisito();

    /* ⚠️ EL VELO NUNCA ATRAPA EL RATÓN.
       Dos pasos piden HACER algo —montar el mazo, jugar un partido— y con el
       velo capturando los clics era imposible: el tutorial decía "móntalo y
       esto se desbloquea solo" encima de una página que no dejaba tocar nada.
       Se resuelve para todos los pasos, no solo para esos dos: una explicación
       que impide probar lo que explica no sirve de mucho. El globo sí recoge
       sus propios clics; el resto de la página sigue viva. */
    caja.classList.toggle('esta-bloqueando', false);

    colocar();
  }

  var btnIr = false;

  /**
   * Avanza o retrocede un paso.
   *
   * SE ESPERA AL GUARDADO ANTES DE NAVEGAR, y esa espera es el arreglo de "los
   * pasos no se completan": guardar y cambiar de página en la misma línea es
   * una carrera —la navegación aborta la petición a medio mandar—, así que el
   * servidor seguía teniendo apuntado el paso viejo y la pantalla nueva
   * repintaba el paso del que acabas de salir. Como la mitad de los pasos
   * cambian de pantalla, salía roto un sí y otro no, según quién llegara antes.
   *
   * El tope de 1200 ms es para no dejar a nadie mirando un botón que no
   * responde si la red se cae: se sigue adelante y como mucho se pierde el
   * apunte de ese paso, que se recupera al siguiente.
   */
  function ir(nuevo) {
    if (nuevo < 0 || nuevo >= T.pasos.length) return;
    indice = nuevo;
    var espera = new Promise(function (r) { setTimeout(r, 1200); });
    Promise.race([guardar(paso().clave), espera]).then(function () { pintar(true); });
  }

  /* Ejecuta la acción del paso. La monta el servidor y devuelve a dónde ir:
     el cliente no puede crear un partido por su cuenta. */
  if (btnAccion) {
    btnAccion.addEventListener('click', function () {
      var accion = btnAccion.dataset.accion;
      if (!accion) return;
      btnAccion.disabled = true;
      btnAccion.textContent = 'Preparando…';

      fetch(T.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ accion: accion, csrf: SRF.csrfToken() }),
      })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          if (!r.ok) {
            btnAccion.disabled = false;
            btnAccion.textContent = paso().accion_texto || '';
            elPendiente.hidden = false;
            elPendiente.textContent = r.error || 'No se pudo preparar el partido.';
            return;
          }
          window.location.href = r.url;
        })
        .catch(function () {
          btnAccion.disabled = false;
          btnAccion.textContent = paso().accion_texto || '';
        });
    });
  }

  btnSig.addEventListener('click', function () {
    // Si el paso vive en otra pantalla, este botón lleva allí en vez de avanzar.
    if (btnIr) { window.location.href = paso().url; return; }
    if (indice === T.pasos.length - 1) { terminar('hecho'); return; }
    ir(indice + 1);
  });
  btnAtras.addEventListener('click', function () { ir(indice - 1); });
  btnSaltar.addEventListener('click', function () {
    SRF.confirmar('¿Saltar el tutorial? Puedes volver a verlo desde tu perfil cuando quieras.',
      function () { terminar('saltado'); });
  });

  /* --------------------------------------------------------------------
     APARTARSE CUANDO HAY UN MODAL DELANTE

     El tutorial vive por encima de los modales a propósito: tiene que poder
     explicarlos. Pero la ceremonia del sobre es un modal a pantalla completa,
     y ahí el globo se plantaba justo encima de las cartas revelándose.
     Mientras haya cualquier modal abierto, el tutorial se esconde entero y
     vuelve al cerrarse. No pierde el paso: solo deja de estorbar.
     -------------------------------------------------------------------- */
  var escondidoPorModal = false;

  document.addEventListener('srf:modal', function (e) {
    escondidoPorModal = !!(e.detail && e.detail.abierto);
    caja.hidden = escondidoPorModal;
    if (!escondidoPorModal) {
      /* Se REPINTA SIEMPRE al volver, no solo si la consulta trae novedades.
         Mientras el tutorial estaba escondido pudo cambiar todo —abrir el
         sobre completa un requisito— y de hecho el sondeo de fondo suele
         enterarse ANTES de que se cierre el modal. Ese fue el fallo: el sondeo
         actualizaba los logros con el tutorial oculto, se saltaba el repintado
         por estar oculto, y al reaparecer ya no había "novedad" que repintar.
         El botón se quedaba bloqueado con el requisito cumplido, que es
         exactamente lo de "los pasos no se completan". */
      refrescarLogros();
      /* Al cerrarse la ceremonia del sobre el requisito ya está cumplido: se
         repinta y, si tocaba, se pasa de paso. */
      if (paso().requiere && cumplido(paso())) { avanzarPorCumplido(); }
      else { pintar(false); }
    }
  });

  /* --------------------------------------------------------------------
     RELEER LOS REQUISITOS SIN RECARGAR

     Las dos puertas del tutorial se cruzan por AJAX: el sobre de bienvenida se
     abre sin recargar y el mazo se guarda igual. Sin esto, el tutorial se
     quedaba diciendo "todavía no lo has hecho" encima de algo que la persona
     acababa de hacer, y el botón de Siguiente no se desbloqueaba nunca — que
     es lo que hacía que los pasos "no se completaran".

     Se consulta solo mientras el paso de turno EXIGE algo, y se deja de
     consultar en cuanto está cumplido: un tutorial no tiene por qué estar
     preguntando al servidor todo el rato.
     -------------------------------------------------------------------- */
  var reloj = null;

  /**
   * Se ha cumplido lo que pedía el paso: se pasa al siguiente sin más.
   *
   * Antes había que pulsar "Siguiente" a mano después de abrir el sobre, y
   * como el texto del paso seguía en pantalla parecía que no se había
   * enterado. Si acabas de hacer lo que te pedía, el tutorial no tiene nada
   * más que decir de ese paso.
   *
   * El respiro de 900 ms es para que dé tiempo a ver que la tarea se ha dado
   * por buena antes de que la pantalla cambie.
   */
  function avanzarPorCumplido() {
    if (!paso().requiere || !cumplido(paso())) { return; }
    if (indice >= T.pasos.length - 1) { pintar(false); return; }
    pintar(false);                       // se ve el paso ya sin el aviso...
    setTimeout(function () {
      if (cumplido(paso())) { ir(indice + 1); }   // ...y entonces se avanza
    }, 900);
  }

  function refrescarLogros() {
    var p = paso();
    if (!p.requiere || cumplido(p)) { return; }

    fetch(T.url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ accion: 'logros', csrf: SRF.csrfToken() }),
    })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        if (!r.ok || !r.logros) { return; }
        var antes = cumplido(paso());
        T.logros = r.logros;
        /* Solo se avanza si hay algo nuevo Y el tutorial está a la vista. Con
           un modal delante NO se toca nada a propósito: lo hace el cierre del
           modal, que vuelve a mirar siempre. */
        if (!antes && cumplido(paso()) && !escondidoPorModal) { avanzarPorCumplido(); }
      })
      .catch(function () {});
  }

  function vigilarRequisito() {
    if (reloj) { clearInterval(reloj); reloj = null; }
    var p = paso();
    if (!p.requiere || cumplido(p)) { return; }
    reloj = setInterval(refrescarLogros, 3000);
  }

  // También al volver a la pestaña: lo típico es hacer la tarea y volver.
  window.addEventListener('focus', refrescarLogros);

  /* El foco se recoloca al mover la página: sin esto, basta hacer scroll para
     que el hueco del velo se quede señalando el sitio equivocado. */
  window.addEventListener('resize', colocar);
  window.addEventListener('scroll', colocar, { passive: true });

  /* Escape NO cierra el tutorial. Cerrar es una decisión —se pierde el hilo de
     la explicación— y para eso está el botón de saltar, que además pregunta. */
  pintar(false);
})();
