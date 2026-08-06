<?php
/**
 * MARCADO DE LA CEREMONIA DE APERTURA
 * Lo usan sobres.php (apertura real) y styleguide.php (previsualización).
 * El comportamiento vive en assets/js/ceremonia.js.
 *
 * Tres escenas, en este orden:
 *   1. #ceremoniaApertura — el sobre (con SU plantilla) rasgándose en dos.
 *   2. #ceremoniaFoco     — las cartas de una en una, boca abajo; el jugador
 *                           hace clic para darles la vuelta. Las rarezas
 *                           altas disparan antes la secuencia tipo "walkout".
 *   3. #ceremoniaMesa     — resumen con todas las cartas ya reveladas.
 */
$base = $base ?? '';
?>
<div class="modal ceremonia" id="modalSobre" role="dialog" aria-modal="true"
     aria-labelledby="ceremoniaTitulo" aria-hidden="true">
  <div class="modal-caja modal-caja--ancha" id="ceremoniaCaja">
    <div class="modal-head">
      <h2 id="ceremoniaTitulo">Sobre abierto</h2>
      <button class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <!-- ESCENA DE APERTURA — el sobre permanece en pantalla y las cartas
         SALEN DE ÉL por la boca, de una en una (tipo Pokémon / Adrenalyn).
         El orden del DOM no decide qué tapa a qué: eso lo fija el z-index en
         components.css, porque la carta tiene que empezar DETRÁS del cuerpo
         del sobre (para que se lea que sale de dentro) y acabar DELANTE. -->
    <div class="cer-escena" id="ceremoniaEscena" hidden>

      <!-- Único control visible durante la apertura: todo lo demás (cabecera,
           pie, botones) se oculta para no competir con la escena. -->
      <button type="button" class="cer-saltar" id="cerSaltarEscena">
        Saltar <i class="ph ph-fast-forward" aria-hidden="true"></i>
      </button>

      <div class="cer-walkout" id="cerWalkout" hidden>
        <div class="cer-walkout-rayos"></div>
        <div class="cer-walkout-texto">
          <span class="cer-walkout-rareza" id="cerWalkoutRareza"></span>
        </div>
      </div>

      <!-- El sobre: proporción y acabado de un sobre real (Adrenalyn/Pokémon).
           · cuerpo   → el plástico con la textura de la plantilla
           · sellado  → la banda termosellada de abajo, con su estriado fino
           · boca     → el hueco oscuro que queda al arrancar la tira
           · tira     → la banda termosellada de ARRIBA, la que se rasga
           · brillo   → reflejo del film, se barre al aparecer -->
      <div class="cer-sobre" id="cerSobre">
        <span class="cer-sobre-boca" aria-hidden="true"></span>
        <span class="cer-sobre-cuerpo" aria-hidden="true"></span>
        <span class="cer-sobre-sellado" aria-hidden="true"></span>
        <span class="cer-sobre-brillo" aria-hidden="true"></span>
        <span class="cer-sobre-tira" aria-hidden="true"></span>
      </div>

      <button type="button" class="cer-carta" id="cerCarta">
        <span class="cer-carta-aura" aria-hidden="true"></span>
        <span class="cer-carta-cara cer-carta-dorso">
          <span class="carta-dorso"><i class="ph ph-soccer-ball" aria-hidden="true"></i></span>
        </span>
        <span class="cer-carta-cara cer-carta-frente" id="cerCartaFrente"></span>
      </button>

      <p class="cer-pista" id="ceremoniaPista">Toca la carta para darle la vuelta</p>
      <p class="cer-contador mono" id="ceremoniaContador"></p>
    </div>

    <!-- ESCENA 3: resumen -->
    <div class="ceremonia-mesa" id="ceremoniaMesa"></div>

    <!-- Se te ha saltado la ceremonia por la preferencia del SISTEMA y aún no
         has elegido nada en configuracion.php: se ofrece aquí, que es donde se
         nota, en vez de dejarlo enterrado en los ajustes. -->
    <div class="cer-aviso-motion" id="cerAvisoMotion" hidden>
      <p>
        <i class="ph ph-sparkle" aria-hidden="true"></i>
        Te has saltado la ceremonia porque tu sistema pide reducir el movimiento.
        Puedes activarla solo para esta web.
      </p>
      <div class="cer-aviso-motion-botones">
        <button type="button" class="btn btn-primary btn-sm" id="cerActivarMotion">
          Activar animaciones aquí
        </button>
        <button type="button" class="btn btn-ghost btn-sm" id="cerRechazarMotion">
          No, gracias
        </button>
      </div>
    </div>

    <p class="sr-only" id="ceremoniaAnuncio" role="status" aria-live="polite"></p>

    <div class="modal-pie">
      <button type="button" class="btn btn-ghost" id="ceremoniaSaltarCarta">Saltar carta</button>
      <button type="button" class="btn btn-ghost" id="ceremoniaSaltar">Saltar todo</button>
      <button type="button" class="btn btn-primary" data-cerrar-modal>Continuar</button>
    </div>
  </div>
</div>

<script src="<?= $base ?>assets/js/vendor/gsap/gsap.min.js"></script>
<script src="<?= $base ?>assets/js/ceremonia.js?v=<?= @filemtime(__DIR__ . '/../assets/js/ceremonia.js') ?>"></script>
