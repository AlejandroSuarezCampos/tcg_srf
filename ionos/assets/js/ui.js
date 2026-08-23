/* ==========================================================================
   SUPERLIGA FRONTIER TCG — COMPORTAMIENTO COMPARTIDO DE INTERFAZ
   Modales accesibles, avisos (toasts) y revelado al scroll.
   Sin dependencias. Se carga en todas las páginas desde partials/head.php.
   ========================================================================== */
(function () {
  'use strict';

  var FOCUSABLES = [
    'a[href]', 'button:not([disabled])', 'input:not([disabled])',
    'select:not([disabled])', 'textarea:not([disabled])', '[tabindex]:not([tabindex="-1"])'
  ].join(',');

  /* ------------------------------------------------------------------------
     MODAL
     Atrapa el foco, cierra con Esc y devuelve el foco al elemento que lo
     abrió. Requisito no negociable del sistema (WCAG 2.2).
     ------------------------------------------------------------------------ */
  var modalActivo = null;
  var focoPrevio = null;

  function focusables(modal) {
    return Array.prototype.filter.call(
      modal.querySelectorAll(FOCUSABLES),
      function (el) { return el.offsetParent !== null; }
    );
  }

  /* Todo lo que está FUERA del modal abierto se marca `inert` mientras dura.
     Sin esto, el fondo seguía siendo alcanzable: el atrapado de foco de aquí
     abajo solo intercepta la tecla Tab, así que un lector de pantalla, la
     navegación por gestos de un móvil o cualquier atajo que mueva el foco sin
     pasar por Tab llegaban igual a los 17 enlaces y botones de la página de
     detrás. Se notaba sobre todo en la ceremonia del sobre, que ocupa la
     pantalla entera y parece que no hay nada más.
     `inert` lo hace de verdad: quita del árbol de accesibilidad, impide el
     foco y anula los clics de todo lo que cuelgue del elemento. */
  var inertizados = [];

  function inertizarFondo(modal) {
    liberarFondo();
    Array.prototype.forEach.call(document.body.children, function (hijo) {
      if (hijo === modal || hijo.contains(modal)) return;
      // Se respeta lo que ya estuviera inerte por su cuenta: al cerrar, solo
      // se devuelve el estado a lo que nosotros tocamos.
      if (hijo.inert) return;
      hijo.inert = true;
      inertizados.push(hijo);
    });
  }

  function liberarFondo() {
    inertizados.forEach(function (el) { el.inert = false; });
    inertizados = [];
  }

  function abrirModal(id) {
    var modal = typeof id === 'string' ? document.getElementById(id) : id;
    if (!modal) return;

    focoPrevio = document.activeElement;
    modal.classList.add('is-abierto');
    modal.removeAttribute('aria-hidden');
    document.body.style.overflow = 'hidden';
    modalActivo = modal;
    inertizarFondo(modal);

    var lista = focusables(modal);
    if (lista.length) lista[0].focus();

    /* Aviso de que hay un modal delante. Lo escucha el tutorial para apartarse:
       su globo vive por encima de los modales —tiene que poder explicarlos— y
       sin esto se plantaba encima de la ceremonia del sobre justo mientras se
       revelaban las cartas. */
    document.dispatchEvent(new CustomEvent('srf:modal', { detail: { abierto: true, modal: modal } }));
  }

  function cerrarModal(id) {
    var modal = id ? (typeof id === 'string' ? document.getElementById(id) : id) : modalActivo;
    if (!modal) return;

    modal.classList.remove('is-abierto');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    modalActivo = null;
    liberarFondo();

    if (focoPrevio && typeof focoPrevio.focus === 'function') focoPrevio.focus();
    focoPrevio = null;

    document.dispatchEvent(new CustomEvent('srf:modal', { detail: { abierto: false, modal: modal } }));
  }

  /**
   * ¿Este modal se puede cerrar?
   *
   * Casi todos sí, y un modal que no se cierra es normalmente un error de
   * diseño. La excepción es el PARTIDO EN JUEGO: no es un diálogo, es el
   * encuentro. Salirse a medias deja el duelo corriendo en el servidor sin
   * nadie mirando —los minijuegos se resuelven solos con la opción segura, el
   * rival espera y el marcador sigue— y quien lo cierra pierde jugadas que sí
   * contaban. Se marca con `data-sin-cerrar` en el propio modal.
   *
   * No deja a nadie encerrado: el partido dura lo que dura y se cierra solo al
   * terminar, y el navegador sigue teniendo su botón de atrás.
   */
  function sePuedeCerrar(modal) {
    return !(modal && modal.hasAttribute('data-sin-cerrar'));
  }

  document.addEventListener('keydown', function (e) {
    if (!modalActivo) return;

    if (e.key === 'Escape') {
      e.preventDefault();
      if (sePuedeCerrar(modalActivo)) { cerrarModal(); }
      return;
    }

    if (e.key !== 'Tab') return;

    var lista = focusables(modalActivo);
    if (!lista.length) return;

    var primero = lista[0];
    var ultimo = lista[lista.length - 1];

    if (e.shiftKey && document.activeElement === primero) {
      e.preventDefault();
      ultimo.focus();
    } else if (!e.shiftKey && document.activeElement === ultimo) {
      e.preventDefault();
      primero.focus();
    }
  });

  /* clic en el fondo y en cualquier [data-cerrar-modal] */
  document.addEventListener('click', function (e) {
    if (e.target.classList && e.target.classList.contains('modal')) {
      if (sePuedeCerrar(e.target)) { cerrarModal(e.target); }
      return;
    }
    var cerrar = e.target.closest && e.target.closest('[data-cerrar-modal]');
    if (cerrar) {
      e.preventDefault();
      var suyo = cerrar.closest('.modal');
      if (sePuedeCerrar(suyo)) { cerrarModal(suyo); }
      return;
    }
    var abrir = e.target.closest && e.target.closest('[data-abrir-modal]');
    if (abrir) {
      e.preventDefault();
      abrirModal(abrir.getAttribute('data-abrir-modal'));
    }
  });

  /* ------------------------------------------------------------------------
     TOAST
     Confirmación visual inmediata de acciones de mercado, sobre o duelo.
     Se anuncia por aria-live: nunca es solo un cambio visual silencioso.
     ------------------------------------------------------------------------ */
  var ICONOS = {
    success: 'ph-check-circle',
    danger: 'ph-warning-circle',
    info: 'ph-info'
  };

  function zonaToast() {
    var zona = document.querySelector('.toast-zona');
    if (!zona) {
      zona = document.createElement('div');
      zona.className = 'toast-zona';
      zona.setAttribute('role', 'status');
      zona.setAttribute('aria-live', 'polite');
      document.body.appendChild(zona);
    }
    return zona;
  }

  function toast(mensaje, tipo, duracion) {
    tipo = tipo || 'info';
    var el = document.createElement('div');
    el.className = 'toast toast-' + tipo;
    el.innerHTML = '<i class="ph ' + (ICONOS[tipo] || ICONOS.info) + '" aria-hidden="true"></i>' +
                   '<span></span>';
    el.querySelector('span').textContent = mensaje;
    zonaToast().appendChild(el);

    setTimeout(function () { el.remove(); }, duracion || 4200);
  }

  /* ------------------------------------------------------------------------
     REVELADO AL SCROLL — decorativo, discreto y respetuoso con el usuario
     ------------------------------------------------------------------------ */
  function iniciarReveal() {
    var elementos = document.querySelectorAll('.reveal');
    if (!elementos.length) return;

    if (!('IntersectionObserver' in window) || SRF.movimientoReducido()) {
      Array.prototype.forEach.call(elementos, function (el) { el.classList.add('in'); });
      return;
    }

    var observador = new IntersectionObserver(function (entradas) {
      entradas.forEach(function (entrada) {
        if (entrada.isIntersecting) {
          entrada.target.classList.add('in');
          observador.unobserve(entrada.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    Array.prototype.forEach.call(elementos, function (el) { observador.observe(el); });
  }

  /* ------------------------------------------------------------------------
     ARTE QUE NO CARGA
     Si el fichero de una ilustración no está en disco, la placa cae al mismo
     marcador de posición que usa una carta sin arte, en vez de enseñar el
     icono de imagen rota del navegador. El hexágono de afinidad simplemente
     desaparece: es información secundaria.
     El evento `error` de <img> no burbujea, así que se escucha en captura.
     ------------------------------------------------------------------------ */
  document.addEventListener('error', function (e) {
    var img = e.target;
    if (!img || img.tagName !== 'IMG' || !img.classList) return;

    if (img.classList.contains('carta-arte')) {
      var placa = img.parentElement;
      img.remove();
      if (placa && !placa.querySelector('.carta-placa-vacia')) {
        var hueco = document.createElement('span');
        hueco.className = 'carta-placa-vacia';
        hueco.setAttribute('aria-hidden', 'true');
        hueco.innerHTML = '<i class="ph ph-image-square"></i>';
        placa.insertBefore(hueco, placa.firstChild);
      }
      return;
    }

    var afinidad = img.closest && img.closest('.carta-afinidad');
    if (afinidad) {
      /* Un hexágono con `id` es REUTILIZABLE —el del modal de ficha es uno
         solo para todas las cartas—, así que se esconde en vez de borrarse.
         Borrarlo dejaba el modal sin icono para el resto de la sesión: bastaba
         con que UNA carta tuviera el gráfico de su afinidad mal para perderlo
         en todas las demás. Los de las cartas no llevan id y se siguen
         quitando, que ahí es lo correcto: cada carta tiene el suyo. */
      if (afinidad.id) { afinidad.hidden = true; }
      else             { afinidad.remove(); }
      return;
    }

    if (img.closest && img.closest('.avatar')) {
      /* el avatar cae de vuelta a las iniciales, que ya están en el marcado */
      img.remove();
    }
  }, true);

  /* ------------------------------------------------------------------------
     MENÚ DE NAVEGACIÓN EN MÓVIL
     ------------------------------------------------------------------------ */
  function iniciarNav() {
    var burger = document.querySelector('.nav-burger');
    var menu = document.getElementById('nav-menu');
    if (!burger || !menu) return;

    function cerrar() {
      burger.setAttribute('aria-expanded', 'false');
      menu.classList.remove('is-abierto');
    }

    burger.addEventListener('click', function () {
      var abierto = burger.getAttribute('aria-expanded') === 'true';
      burger.setAttribute('aria-expanded', String(!abierto));
      menu.classList.toggle('is-abierto', !abierto);
    });

    /* Esc cierra el menú y devuelve el foco al botón */
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && burger.getAttribute('aria-expanded') === 'true') {
        cerrar();
        burger.focus();
      }
    });

    /* si se navega con teclado fuera del menú abierto, se cierra */
    document.addEventListener('click', function (e) {
      if (burger.getAttribute('aria-expanded') !== 'true') return;
      if (!menu.contains(e.target) && !burger.contains(e.target)) cerrar();
    });
  }

  /* ------------------------------------------------------------------------
     PESTAÑAS
     Patrón ARIA completo: flechas para moverse, Inicio/Fin para los extremos.
     Cada .tab apunta a su panel con aria-controls.
     ------------------------------------------------------------------------ */
  function iniciarTabs() {
    Array.prototype.forEach.call(document.querySelectorAll('[role="tablist"]'), function (lista) {
      var tabs = Array.prototype.slice.call(lista.querySelectorAll('[role="tab"]'));
      if (!tabs.length) return;

      function activar(tab, moverFoco) {
        tabs.forEach(function (t) {
          var seleccionado = t === tab;
          t.setAttribute('aria-selected', String(seleccionado));
          t.tabIndex = seleccionado ? 0 : -1;
          var panel = document.getElementById(t.getAttribute('aria-controls'));
          if (panel) panel.hidden = !seleccionado;
        });
        if (moverFoco) tab.focus();
      }

      tabs.forEach(function (tab, i) {
        tab.tabIndex = tab.getAttribute('aria-selected') === 'true' ? 0 : -1;

        tab.addEventListener('click', function () { activar(tab); });

        tab.addEventListener('keydown', function (e) {
          var destino = null;
          if (e.key === 'ArrowRight') destino = tabs[(i + 1) % tabs.length];
          else if (e.key === 'ArrowLeft') destino = tabs[(i - 1 + tabs.length) % tabs.length];
          else if (e.key === 'Home') destino = tabs[0];
          else if (e.key === 'End') destino = tabs[tabs.length - 1];
          if (!destino) return;
          e.preventDefault();
          activar(destino, true);
        });
      });
    });
  }

  /* ------------------------------------------------------------------------
     PANELES PLEGABLES SOLO EN MÓVIL
     En escritorio el <summary> se oculta por CSS y el panel va siempre
     desplegado; en móvil arranca plegado para no empujar el contenido.
     Solo se sincroniza al cruzar el punto de corte, nunca en cada resize, para
     no cerrarle el panel al usuario mientras lo está usando.
     ------------------------------------------------------------------------ */
  function iniciarPlegables() {
    var elementos = document.querySelectorAll('[data-plegable-movil]');
    if (!elementos.length) return;

    var mq = window.matchMedia('(min-width: 1024px)');

    function sincronizar() {
      Array.prototype.forEach.call(elementos, function (el) { el.open = mq.matches; });
    }

    sincronizar();
    if (mq.addEventListener) mq.addEventListener('change', sincronizar);
    else mq.addListener(sincronizar);
  }

  /* ------------------------------------------------------------------------
     CONFIRMACIÓN GENÉRICA
     Envuelve el modal de partials/confirmar.php para que cualquier pantalla
     pida confirmación sin volver a montar un modal propio.

     mercado.js gestiona ese mismo modal por su cuenta (además envía por AJAX),
     así que el manejador de aquí solo actúa si hay una petición viva puesta por
     SRF.confirmar; si no, se aparta y deja trabajar a mercado.js.
     ------------------------------------------------------------------------ */
  var confirmarPendiente = null;

  function confirmar(texto, alAceptar) {
    var modal = document.getElementById('modalConfirmar');
    if (!modal) { alAceptar(); return; }   // sin modal no se bloquea la acción

    var parrafo = document.getElementById('confirmarTexto');
    if (parrafo) parrafo.textContent = texto || '¿Confirmas esta acción?';

    confirmarPendiente = alAceptar;
    abrirModal(modal);
  }

  function iniciarConfirmar() {
    var btnSi = document.getElementById('confirmarSi');
    if (!btnSi) return;

    btnSi.addEventListener('click', function () {
      if (!confirmarPendiente) return;      // la petición es de mercado.js
      var alAceptar = confirmarPendiente;
      confirmarPendiente = null;
      cerrarModal(document.getElementById('modalConfirmar'));
      alAceptar();
    });
  }

  /* cancelar (Esc, fondo o botón) descarta la petición: si no, la siguiente
     confirmación heredaría la anterior y ejecutaría algo que ya se rechazó */
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-cerrar-modal]')) confirmarPendiente = null;
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') confirmarPendiente = null;
  });

  /* COPIAR AL PORTAPAPELES — cualquier elemento con data-copiar.
     Vive aquí y no en la pantalla que lo estrena porque no tiene nada de
     específico: en cuanto haya un segundo sitio que quiera ofrecer "copia
     esto", ya está resuelto.

     navigator.clipboard solo existe en contexto seguro (https o localhost), y
     este proyecto se sirve por http en red local, así que hace falta el camino
     viejo con un textarea temporal. Sin él, copiar fallaría en silencio justo
     en el escenario en el que se juega. */
  function copiarConCaja(texto) {
    return new Promise(function (resolver, rechazar) {
      var caja = document.createElement('textarea');
      caja.value = texto;
      // fuera de la vista pero seleccionable: display:none no se puede copiar
      caja.setAttribute('readonly', '');
      caja.style.position = 'fixed';
      caja.style.left = '-9999px';
      document.body.appendChild(caja);
      caja.select();
      try {
        document.execCommand('copy') ? resolver() : rechazar();
      } catch (e) { rechazar(e); }
      caja.remove();
    });
  }

  function copiarTexto(texto) {
    /* La API moderna solo existe en contexto seguro, y AUN ASÍ rechaza si el
       documento no tiene el foco o si el permiso está denegado — comprobado:
       falla en una pestaña de fondo aunque todo lo demás esté bien. Por eso el
       camino viejo no es solo para http: es el respaldo cuando la moderna dice
       que no. Sin esta cadena, copiar fallaba en silencio en casos normales. */
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(texto).catch(function () {
        return copiarConCaja(texto);
      });
    }
    return copiarConCaja(texto);
  }

  function iniciarCopiar() {
    document.addEventListener('click', function (e) {
      var boton = e.target.closest && e.target.closest('[data-copiar]');
      if (!boton) return;
      copiarTexto(boton.dataset.copiar)
        .then(function () { toast('Resumen copiado.', 'success'); })
        .catch(function () { toast('No se ha podido copiar.', 'danger'); });
    });
  }

  /* ------------------------------------------------------------------------
     FICHA DE CARTA
     Rellena y abre el modal de partials/ficha_carta.php desde los `data-*`
     que el componente de carta ya escribe en modo 'arte'. Toda la información
     está en el HTML: ni segunda consulta, ni AJAX, ni un JSON del catálogo.
     ------------------------------------------------------------------------ */
  function texto(id, valor) {
    var el = document.getElementById(id);
    if (el) { el.textContent = valor; }
  }

  function bloque(id, valor) {
    var wrap = document.getElementById(id + 'Bloque');
    if (!wrap) return;
    wrap.hidden = !valor;
    if (valor) { texto(id, valor); }
  }

  function rellenarFicha(carta) {
    var d = carta.dataset;
    var modal = document.getElementById('modalFicha');
    if (!modal) return false;

    texto('fichaNombre', d.nombre || '');
    texto('fichaMeta', [d.equipo, d.posicion].filter(Boolean).join(' · '));

    /* Insignia de universo. Se construye aquí y no en el marcado del modal
       porque el modal es UNO para todas las cartas: tiene que poder cambiar de
       universo (y quedarse sin ninguno) entre una carta y la siguiente. */
    var universo = document.getElementById('fichaUniverso');
    if (universo) {
      /* NOMBRE COMPLETO, no la abreviatura. Aquí es el único sitio donde se
         enseña el universo —la carta ya no lo lleva— así que hay espacio de
         sobra y «SRF» no le decía nada a quien no supiera ya qué significa. */
      var UNIV = {
        srf: 'Superliga Frontier',
        ie:  'Inazuma Eleven Canonical Series',
      };
      var u = UNIV[d.universo];
      universo.hidden = !u;
      universo.innerHTML = '';
      if (u) {
        var chip = document.createElement('span');
        chip.className = 'universo universo--' + d.universo;
        chip.textContent = u;
        universo.appendChild(chip);
      }
    }

    /* La etiqueta de rareza se clona de la propia carta en vez de rehacerse:
       las marcas no cromáticas (chevrones, corona, destello) las dibuja el
       CSS a partir de data-rareza, y duplicar esa lógica aquí sería la
       segunda implementación que el componente existe para evitar. */
    var rz = document.getElementById('fichaRareza');
    if (rz) {
      rz.className = 'rz';
      rz.setAttribute('data-rareza', d.rareza || '1');   /* el ID numérico */
      rz.innerHTML = '<span class="rz-texto"></span>';
      rz.firstChild.textContent = d.rarezaNombre || '';
    }

    /* La carta del modal es una copia literal de la pulsada, sin el botón:
       mismo marcado, mismo borde de rareza, mismo arcoíris de la SRF. */
    var hueco = document.getElementById('fichaCarta');
    if (hueco) {
      var plano = document.createElement('div');
      plano.setAttribute('data-rareza', d.rareza || '1');

      if (carta.querySelector('.carta-marco')) {
        /* Viene de la rejilla: se copia tal cual, así el borde de rareza
           —arcoíris de la SRF incluido— sale sin escribir una línea. */
        plano.className = carta.className.replace('carta--accion', '');
        plano.innerHTML = carta.innerHTML;
      } else {
        /* Viene de una FILA de lista, que solo tiene una miniatura: hay que
           montar la carta a mano.

           ⚠️ Y hay que montarla CON SU PLANTILLA. Aquí estaba el fallo que se
           veía en el mercado: la vista de lista es la que trae por defecto, así
           que abrir la ficha de un jugador desde ahí construía la carta sin
           marco —solo la foto a sangre— mientras que la misma carta abierta
           desde la rejilla sí lo tenía. La misma carta con dos aspectos según
           por dónde la abrieras.

           Si la rareza tiene marco se replica la maqueta del componente: hueco
           cuadrado para la foto y rectángulo blanco para el nombre, en las
           mismas clases, que son las que llevan las coordenadas medidas. Si no
           lo tiene —legendaria, SRF, numerada mientras no exista su
           plantilla—, la ilustración va a sangre como en el servidor.
           `data-marco` lo decide el servidor: es quien sabe si el archivo
           existe. */
        plano.className = 'carta carta--arte';

        var mini = carta.querySelector('.carta-fila-miniatura');
        var conMarco = !!mini && mini.dataset.marco === '1';

        var img = d.imagen
          ? '<img class="carta-arte" src="' + d.imagen + '" alt="">'
          : '<span class="carta-placa-vacia"><i class="ph ph-image-square"></i></span>';

        if (conMarco) {
          var nombre = document.createElement('span');
          nombre.textContent = d.nombre || '';
          plano.innerHTML =
            '<div class="carta-marco">' +
              '<div class="carta-placa carta-placa--marco">' +
                '<div class="carta-foto-hueco">' + img + '</div>' +
                '<h3 class="carta-nombre-marco"><span>' + nombre.innerHTML + '</span></h3>' +
              '</div>' +
            '</div>';
        } else {
          plano.innerHTML =
            '<div class="carta-marco"><div class="carta-placa">' + img + '</div></div>';
        }
      }

      /* Dentro del modal la copia es decorativa: nada de lo que lleva debe
         poder enfocarse (sería un salto de tabulación a un control muerto) ni
         repetir lo que el propio modal ya dice al lado. */
      Array.prototype.forEach.call(
        plano.querySelectorAll('.carta-hitbox, .sr-only, .carta-leyenda, .carta-pie, form, button, a'),
        function (el) { el.remove(); }
      );
      /* El arte del modal ya lo describe el nombre que hay justo al lado. */
      Array.prototype.forEach.call(plano.querySelectorAll('img'), function (el) {
        el.setAttribute('alt', '');
      });
      hueco.innerHTML = '';
      hueco.appendChild(plano);
    }

    ['ataque', 'defensa', 'tecnica'].forEach(function (clave) {
      var caja = document.querySelector('#fichaStats [data-stat="' + clave + '"]');
      if (!caja) return;
      var valor = parseInt(d[clave], 10) || 0;
      caja.querySelector('[data-valor]').textContent = valor;
      /* 99 es el techo del juego, no 100: una carta al máximo llena la barra. */
      caja.querySelector('[data-barra]').style.width = Math.min(100, valor / 99 * 100) + '%';
    });

    bloque('fichaAfinidad', d.afinidad || '');
    bloque('fichaRasgo', d.rasgo || '');

    /* El hexágono de afinidad, el mismo que lleva la carta. Sin imagen se
       oculta el icono pero el nombre se sigue viendo: el dato no depende de
       que exista el gráfico. */
    var icono = document.getElementById('fichaAfinidadIcono');
    if (icono) {
      var ruta = d.afinidadImg || '';
      icono.hidden = !(d.afinidad && ruta);
      if (!icono.hidden) {
        var img = icono.querySelector('img');
        img.src = ruta;
        img.alt = 'Afinidad ' + d.afinidad;
      }
    }

    /* Acciones contextuales: cada pantalla declara las suyas en la propia
       carta como JSON (`data-acciones`), y aquí solo se pintan. Así el modal
       no sabe nada de mazos, mercado ni colección. */
    var acciones = document.getElementById('fichaAcciones');
    if (acciones) {
      acciones.innerHTML = '';
      var lista = [];
      try { lista = JSON.parse(d.acciones || '[]'); } catch (e) { lista = []; }
      lista.forEach(function (a, i) {
        var el = document.createElement('a');
        el.href = a.href || '#';
        el.className = 'btn ' + (i === 0 ? 'btn-primary' : 'btn-ghost');
        el.textContent = a.texto || '';
        acciones.appendChild(el);
      });
    }

    return true;
  }

  function iniciarFichaCarta() {
    document.addEventListener('click', function (e) {
      var disparador = e.target.closest && e.target.closest('[data-ficha-carta]');
      if (!disparador) return;
      /* Los `data-*` viven en la carta —o en la fila, si es la vista de
         lista—, no en el control que abre la ficha. */
      var carta = disparador.closest('.carta, .carta-fila');
      if (carta && rellenarFicha(carta)) { abrirModal('modalFicha'); }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    iniciarReveal();
    iniciarNav();
    iniciarTabs();
    iniciarPlegables();
    iniciarConfirmar();
    iniciarCopiar();
    iniciarFichaCarta();
  });

  /* API pública.
     Se AMPLÍA el objeto existente, nunca se reasigna: partials/head.php ya ha
     dejado ahí las funciones de preferencia de movimiento (inline, antes del
     primer pintado) y un `window.SRF = {...}` las borraría. */
  var SRF = (window.SRF = window.SRF || {});
  SRF.abrirModal = abrirModal;
  SRF.cerrarModal = cerrarModal;
  SRF.toast = toast;
  SRF.confirmar = confirmar;
  SRF.copiar = copiarTexto;
})();
