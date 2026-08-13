<?php
/**
 * MARCADO DE LA CEREMONIA DE APERTURA DE COFRES (§15.12 del CLAUDE.md).
 * Hermana de partials/ceremonia.php: mismo motor de volteo de cartas
 * (assets/js/ceremonia_cofre.js reutiliza las clases .cer-carta/.cer-walkout/
 * .ceremonia-mesa tal cual), pero con SU escena de apertura —un cofre con
 * tapa que se abre, no un sobre que se rasga— y un resumen que además dice
 * las monedas y los bonus (camino perfecto, formación) que un sobre nunca
 * tiene.
 *
 * Antes el botín del cofre se enseñaba como un simple <p class="alerta">
 * arriba de la página: con el botón "Abrir cofre" al fondo del panel de
 * selección, el aviso quedaba fuera de vista y los encargados de pruebas no
 * lo veían las primeras veces. Un modal que se abre solo, centrado, no deja
 * ese hueco.
 */
$base = $base ?? '';
?>
<div class="modal ceremonia ceremonia-cofre" id="modalCofre" role="dialog" aria-modal="true"
     aria-labelledby="ceremoniaCofreTitulo" aria-hidden="true">
  <div class="modal-caja modal-caja--ancha" id="ceremoniaCofreCaja">
    <div class="modal-head">
      <h2 id="ceremoniaCofreTitulo">Cofre abierto</h2>
      <button class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <!-- ESCENA DE APERTURA — el cofre se abre y las cartas salen de él de una
         en una, boca abajo, igual que en la ceremonia de sobres. -->
    <div class="cer-escena" id="ceremoniaCofreEscena" hidden>

      <button type="button" class="cer-saltar" id="cerCofreSaltarEscena">
        Saltar <i class="ph ph-fast-forward" aria-hidden="true"></i>
      </button>

      <div class="cer-walkout" id="cerCofreWalkout" hidden>
        <div class="cer-walkout-rayos"></div>
        <div class="cer-walkout-texto">
          <span class="cer-walkout-rareza" id="cerCofreWalkoutRareza"></span>
          <span class="cer-walkout-nombre" id="cerCofreWalkoutNombre"></span>
        </div>
      </div>

      <!-- El cofre: base + tapa con bisagra trasera. La tapa gira con GSAP
           (rotateX), nunca con una clase — mismo motivo que la carta. -->
      <div class="cer-cofre" id="cerCofre">
        <span class="cer-cofre-brillo" aria-hidden="true"></span>
        <span class="cer-cofre-cuerpo" aria-hidden="true"></span>
        <span class="cer-cofre-cierre" aria-hidden="true"></span>
        <span class="cer-cofre-tapa" aria-hidden="true"></span>
      </div>

      <button type="button" class="cer-carta" id="cerCofreCarta">
        <span class="cer-carta-aura" aria-hidden="true"></span>
        <span class="cer-carta-cara cer-carta-dorso">
          <span class="carta-dorso"><i class="ph ph-soccer-ball" aria-hidden="true"></i></span>
        </span>
        <span class="cer-carta-cara cer-carta-frente" id="cerCofreCartaFrente"></span>
      </button>

      <p class="cer-pista" id="ceremoniaCofrePista">Toca la carta para darle la vuelta</p>
      <p class="cer-contador mono" id="ceremoniaCofreContador"></p>
    </div>

    <!-- ESCENA 3: resumen — monedas, bonus y las cartas ya reveladas -->
    <div id="ceremoniaCofreResumen">
      <p class="cer-cofre-bonus" id="ceremoniaCofreBonus" hidden></p>
      <div class="ceremonia-mesa" id="ceremoniaCofreMesa" hidden></div>
    </div>

    <div class="cer-aviso-motion" id="cerCofreAvisoMotion" hidden>
      <p>
        <i class="ph ph-sparkle" aria-hidden="true"></i>
        Te has saltado la ceremonia porque tu sistema pide reducir el movimiento.
        Puedes activarla solo para esta web.
      </p>
      <div class="cer-aviso-motion-botones">
        <button type="button" class="btn btn-primary btn-sm" id="cerCofreActivarMotion">
          Activar animaciones aquí
        </button>
        <button type="button" class="btn btn-ghost btn-sm" id="cerCofreRechazarMotion">
          No, gracias
        </button>
      </div>
    </div>

    <p class="sr-only" id="ceremoniaCofreAnuncio" role="status" aria-live="polite"></p>

    <div class="modal-pie">
      <button type="button" class="btn btn-ghost" id="ceremoniaCofreSaltarCarta">Saltar carta</button>
      <button type="button" class="btn btn-ghost" id="ceremoniaCofreSaltar">Saltar todo</button>
      <button type="button" class="btn btn-primary" data-cerrar-modal>Continuar</button>
    </div>
  </div>
</div>

<script src="<?= $base ?>assets/js/vendor/gsap/gsap.min.js"></script>
<script src="<?= $base ?>assets/js/ceremonia_cofre.js?v=<?= @filemtime(__DIR__ . '/../assets/js/ceremonia_cofre.js') ?>"></script>
