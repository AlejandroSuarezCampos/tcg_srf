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
          <span class="cer-walkout-nombre" id="cerWalkoutNombre"></span>
        </div>
      </div>

      <!-- El sobre, construido como uno de verdad. El orden importa poco: lo
           que decide qué tapa a qué es el z-index en components.css.
             · cuerpo   → el film con la textura de la plantilla
             · sellado  → la banda termosellada de abajo, con su estriado fino
             · boca     → el hueco oscuro del interior. Se descubre de
                          IZQUIERDA A DERECHA según avanza el rasgado, que es
                          lo que hace que se lea como un desgarro y no como una
                          tapa que se abre de golpe.
             · luz      → el resplandor que escapa del interior al abrirse
             · tira     → la banda termosellada de ARRIBA, la que se arranca
             · brillo   → reflejo del film, barre al aparecer
             · flecos   → los trocitos de plástico que saltan al rasgar; los
                          crea y los tira ceremonia.js -->
      <div class="cer-sobre" id="cerSobre">
        <span class="cer-sobre-boca" aria-hidden="true"></span>
        <span class="cer-sobre-luz" aria-hidden="true"></span>
        <span class="cer-sobre-cuerpo" aria-hidden="true"></span>
        <span class="cer-sobre-sellado" aria-hidden="true"></span>
        <span class="cer-sobre-brillo" aria-hidden="true"></span>
        <span class="cer-sobre-tira" aria-hidden="true"></span>
        <span class="cer-sobre-corte" aria-hidden="true"></span>
        <span class="cer-sobre-flecos" id="cerSobreFlecos" aria-hidden="true"></span>
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

    <!-- ESCENA 3: resumen.
         La tira de recuento va ARRIBA y fuera de la mesa: la mesa tiene scroll
         propio (ver components.css) y un resumen dentro se iría con ella
         justo cuando más se mira, que es al llegar abajo del todo. -->
    <div class="cer-recuento" id="ceremoniaRecuento" hidden></div>
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

    <?php /* EL PIE CAMBIA SEGÚN LA FASE, y eso es deliberado.

             Antes tenía siempre los mismos tres botones y al acabar el reparto
             los dos de saltar se quedaban ahí, desactivados: dos controles
             muertos ocupando el sitio justo donde la persona busca qué hacer
             ahora. Ahora los de saltar solo existen mientras hay algo que
             saltar, y al terminar salen los de seguir abriendo.

             «Abrir otro» y «Abrir 10» reabren SIN pasar por la caja: la
             animación de la caja está para elegir sobre, y ya lo has elegido.
             Los pinta ceremonia.js solo si quien la abrió le pasó `repetir`
             —el sobre de bienvenida, por ejemplo, no se repite—. */ ?>
    <div class="modal-pie">
      <div class="cer-pie-grupo" id="ceremoniaPieSaltar">
        <button type="button" class="btn btn-ghost" id="ceremoniaSaltarCarta">Saltar carta</button>
        <button type="button" class="btn btn-ghost" id="ceremoniaSaltar">Saltar todo</button>
      </div>
      <div class="cer-pie-grupo" id="ceremoniaPieFinal" hidden>
        <button type="button" class="btn btn-ghost" id="ceremoniaOtro" hidden>
          <i class="ph ph-arrow-counter-clockwise" aria-hidden="true"></i> Abrir otro
        </button>
        <button type="button" class="btn btn-ghost" id="ceremoniaOtrosDiez" hidden>
          <i class="ph ph-stack" aria-hidden="true"></i> Abrir 10
        </button>
      </div>
      <button type="button" class="btn btn-primary" data-cerrar-modal>Continuar</button>
    </div>
  </div>
</div>

<?php /* Con versión, como todo lo demás: el `.htaccess` cachea los .js un año
         con `immutable`, así que un GSAP sin `?v=` se quedaría congelado en el
         navegador de la gente el día que se actualice la librería. */ ?>
<?= assetScript($base, "assets/js/vendor/gsap/gsap.min.js") ?>
<script src="<?= $base ?>assets/js/ceremonia.js?v=<?= @filemtime(__DIR__ . '/../assets/js/ceremonia.js') ?>"></script>
