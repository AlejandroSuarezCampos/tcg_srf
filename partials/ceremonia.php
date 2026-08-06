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

    <!-- ESCENA 1: el sobre en 3D, rasgado en dos mitades -->
    <div class="ceremonia-apertura" id="ceremoniaApertura" hidden>
      <div class="cer-sobre" id="cerSobre">
        <div class="cer-sobre-mitad cer-sobre-arriba"></div>
        <div class="cer-sobre-mitad cer-sobre-abajo"></div>
        <div class="cer-sobre-luz"></div>
      </div>
    </div>

    <!-- ESCENA 2: carta a carta, boca abajo, clic para voltear -->
    <div class="ceremonia-foco" id="ceremoniaFoco" hidden>
      <div class="cer-foco-escena">
        <div class="cer-walkout" id="cerWalkout" hidden>
          <div class="cer-walkout-rayos"></div>
          <div class="cer-walkout-texto">
            <span class="cer-walkout-rareza" id="cerWalkoutRareza"></span>
          </div>
        </div>
        <button type="button" class="cer-carta" id="cerCarta">
          <span class="cer-carta-aura" aria-hidden="true"></span>
          <span class="cer-carta-cara cer-carta-dorso">
            <span class="carta-dorso"><i class="ph ph-soccer-ball" aria-hidden="true"></i></span>
          </span>
          <span class="cer-carta-cara cer-carta-frente" id="cerCartaFrente"></span>
        </button>
      </div>
      <p class="cer-pista" id="ceremoniaPista">Toca la carta para darle la vuelta</p>
      <p class="cer-contador mono" id="ceremoniaContador"></p>
    </div>

    <!-- ESCENA 3: resumen -->
    <div class="ceremonia-mesa" id="ceremoniaMesa"></div>

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
