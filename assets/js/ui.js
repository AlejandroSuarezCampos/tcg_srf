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

  function abrirModal(id) {
    var modal = typeof id === 'string' ? document.getElementById(id) : id;
    if (!modal) return;

    focoPrevio = document.activeElement;
    modal.classList.add('is-abierto');
    modal.removeAttribute('aria-hidden');
    document.body.style.overflow = 'hidden';
    modalActivo = modal;

    var lista = focusables(modal);
    if (lista.length) lista[0].focus();
  }

  function cerrarModal(id) {
    var modal = id ? (typeof id === 'string' ? document.getElementById(id) : id) : modalActivo;
    if (!modal) return;

    modal.classList.remove('is-abierto');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    modalActivo = null;

    if (focoPrevio && typeof focoPrevio.focus === 'function') focoPrevio.focus();
    focoPrevio = null;
  }

  document.addEventListener('keydown', function (e) {
    if (!modalActivo) return;

    if (e.key === 'Escape') {
      e.preventDefault();
      cerrarModal();
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
      cerrarModal(e.target);
      return;
    }
    var cerrar = e.target.closest && e.target.closest('[data-cerrar-modal]');
    if (cerrar) {
      e.preventDefault();
      cerrarModal(cerrar.closest('.modal'));
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

    if (!('IntersectionObserver' in window) ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
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
    if (afinidad) { afinidad.remove(); return; }

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

  document.addEventListener('DOMContentLoaded', function () {
    iniciarReveal();
    iniciarNav();
    iniciarTabs();
    iniciarPlegables();
    iniciarConfirmar();
  });

  /* ------------------------------------------------------------------------
     ¿Hay que reducir el movimiento?

     Por defecto manda la preferencia del sistema (WCAG 2.2): quien la tenga
     activada no verá ceremonias ni volteos animados. Pero esa preferencia se
     activa muchas veces por rendimiento, no por sensibilidad al movimiento —
     en Windows basta con apagar "Efectos de animación" y Chrome ya reporta
     `prefers-reduced-motion: reduce`—, y entonces el jugador se pierde la
     apertura de sobres sin saber por qué. Por eso hay una preferencia propia
     que puede FORZAR cualquiera de los dos lados desde configuracion.php:

       'si'  -> animaciones completas, ignorando al sistema (opt-in explícito)
       'no'  -> sin animaciones, aunque el sistema no lo pida
       null  -> automático: lo que diga el sistema

     Se consulta en cada uso, nunca se cachea: así cambiarla surte efecto sin
     recargar la página.
     ------------------------------------------------------------------------ */
  var CLAVE_MOVIMIENTO = 'srf-animaciones';

  function preferenciaMovimiento() {
    try { return localStorage.getItem(CLAVE_MOVIMIENTO); } catch (e) { return null; }
  }

  function movimientoReducido() {
    var pref = preferenciaMovimiento();
    if (pref === 'si') return false;
    if (pref === 'no') return true;
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function fijarPreferenciaMovimiento(valor) {
    try {
      if (valor === null) localStorage.removeItem(CLAVE_MOVIMIENTO);
      else localStorage.setItem(CLAVE_MOVIMIENTO, valor);
    } catch (e) { /* modo privado sin almacenamiento: se queda en automático */ }
  }

  /* API pública */
  window.SRF = {
    abrirModal: abrirModal,
    cerrarModal: cerrarModal,
    toast: toast,
    confirmar: confirmar,
    movimientoReducido: movimientoReducido,
    preferenciaMovimiento: preferenciaMovimiento,
    fijarPreferenciaMovimiento: fijarPreferenciaMovimiento
  };
})();
