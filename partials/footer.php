<?php
/**
 * PIE COMPARTIDO — presente en todas las páginas del sitio.
 *
 * Aquí vive el aviso legal, que debe ser visible de forma consistente en todo
 * el producto y no solo en la portada (briefing, sección 37).
 *
 * Antes de incluirlo, la página puede definir:
 *   $base        -> prefijo relativo a la raíz ('' o '../')
 *   $pieCompleto -> true en la portada (columnas y redes), false en el resto
 */
require_once __DIR__ . '/assets.php';   // assetScript()

$base        = $base        ?? '';
$pieCompleto = $pieCompleto ?? false;
?>
<footer class="pie">
  <div class="wrap">

    <?php if ($pieCompleto): ?>
    <div class="pie-grid">
      <div class="stack stack-4">
        <span class="logo">Superliga Frontier<span class="logo-punto">·</span>TCG</span>
        <p class="t-body-sm t-dim" style="max-width:34ch;">
          El registro coleccionable de la Superliga Frontier: cada jugador,
          presidente y escudo de la liga, convertido en carta.
        </p>
        <div class="pie-redes">
          <a href="https://x.com/supligafrontier" aria-label="Superliga Frontier en X">
            <i class="ph ph-x-logo" aria-hidden="true"></i>
          </a>
          <a href="https://www.instagram.com/superligafrontier/" aria-label="Superliga Frontier en Instagram">
            <i class="ph ph-instagram-logo" aria-hidden="true"></i>
          </a>
          <a href="https://discord.gg/KgEBHA87fF" aria-label="Superliga Frontier en Discord">
            <i class="ph ph-discord-logo" aria-hidden="true"></i>
          </a>
        </div>
      </div>

      <nav class="pie-col" aria-label="Coleccionar">
        <h2 class="t-caption">Coleccionar</h2>
        <ul>
          <li><a href="<?= $base ?>plantilla?ver=todas">Todas las fichas</a></li>
          <li><a href="<?= $base ?>plantilla">Tu plantilla</a></li>
          <li><a href="<?= $base ?>mercado">Mercado</a></li>
        </ul>
      </nav>

      <nav class="pie-col" aria-label="Jugar">
        <h2 class="t-caption">Jugar</h2>
        <ul>
          <li><a href="<?= $base ?>sobres">Sobres</a></li>
          <li><a href="<?= $base ?>perfil">Perfil</a></li>
          <li><a href="https://superligafrontier.es">La liga</a></li>
        </ul>
      </nav>

      <?php /* EL PROYECTO. Estas tres páginas son nuevas y este es el único
               sitio del que cuelgan, así que sin este bloque no las encuentra
               nadie —ni un visitante ni un rastreador—.
               No es relleno de pie: «qué es esto» es lo primero que pregunta
               quien llega de fuera y no sabe si el juego es oficial, y las dos
               legales son obligatorias en un sitio que pide registro y publica
               nombre e imagen de personas reales. */ ?>
      <nav class="pie-col" aria-label="El proyecto">
        <h2 class="t-caption">El proyecto</h2>
        <ul>
          <li><a href="<?= $base ?>quienes-somos">Qué es esto</a></li>
          <li><a href="<?= $base ?>como-se-juega">Cómo se juega</a></li>
          <li><a href="<?= $base ?>preguntas-frecuentes">Preguntas frecuentes</a></li>
          <li><a href="<?= $base ?>legal">Aviso legal</a></li>
          <li><a href="<?= $base ?>privacidad">Privacidad</a></li>
        </ul>
      </nav>
    </div>
    <?php endif; ?>

    <?php /* AVISO DE BETA.
             Va en el pie y en TODAS las páginas —no en una alerta que se cierra
             y no vuelve— porque no es una novedad que se anuncia una vez: es la
             condición en la que está el juego mientras dure. Quien llegue por
             primera vez a cualquier pantalla tiene que poder enterarse ahí
             mismo de que su progreso puede no conservarse. */ ?>
    <div class="pie-beta" role="note" aria-labelledby="pieBetaTitulo">
      <p class="pie-beta-titulo" id="pieBetaTitulo">
        <i class="ph-fill ph-flask" aria-hidden="true"></i>
        La web está en <b>beta</b>
      </p>
      <p class="t-caption-sm">
        Todo su contenido se llevará a revisión y se seguirá añadiendo contenido nuevo.
        <b>Las cuentas no son las definitivas.</b>
        Si encuentras un error o un fallo, cuéntanoslo por los canales oficiales
        de la Superliga Frontier.
      </p>
      <p class="pie-beta-canales">
        <a href="https://discord.gg/KgEBHA87fF">
          <i class="ph ph-discord-logo" aria-hidden="true"></i> Discord
        </a>
        <a href="https://x.com/supligafrontier">
          <i class="ph ph-x-logo" aria-hidden="true"></i> X
        </a>
        <a href="https://www.instagram.com/superligafrontier/">
          <i class="ph ph-instagram-logo" aria-hidden="true"></i> Instagram
        </a>
      </p>
    </div>

    <div class="pie-legal">
      <?php /* Estos tres enlaces van AQUÍ, en el bloque que sale en todas las
               páginas, y no arriba con las columnas: aquellas solo se pintan en
               la portada (`$pieCompleto`), y el aviso legal y la privacidad
               tienen que ser alcanzables desde cualquier pantalla —también
               desde el catálogo, que es la otra página pública del sitio. */ ?>
      <p class="pie-enlaces-legales t-caption-sm">
        <a href="<?= $base ?>quienes-somos">Qué es esto</a> ·
        <a href="<?= $base ?>como-se-juega">Cómo se juega</a> ·
        <a href="<?= $base ?>preguntas-frecuentes">Preguntas frecuentes</a> ·
        <a href="<?= $base ?>legal">Aviso legal</a> ·
        <a href="<?= $base ?>privacidad">Privacidad</a>
      </p>
      <p class="t-caption-sm t-dim">
        Proyecto fan-made sin ánimo de lucro y sin monetización.
        Inazuma Eleven es propiedad de Level-5. Otras marcas que puedan aparecer
        en alguna carta (como Disney o Nike) pertenecen a sus respectivos
        titulares. Sin afiliación ni respaldo oficial de ninguna de ellas.
      </p>
      <p class="mono t-caption-sm t-dim">«Al Gonzalo ese le gano fácil» · Payo Aguao</p>
    </div>

  </div>
</footer>

<?= assetScript($base, 'assets/js/ui.js') ?>
<?php /* Primitivas de movimiento del sistema Ascua. Va después de ui.js porque
         comparte el objeto SRF, y al final del body porque nada de lo que hace
         es urgente: si no llegara, todo queda visible y colocado igual. */ ?>
<?= assetScript($base, 'assets/js/motion.js') ?>

<?php /* El tutorial de bienvenida (migración `036`). Va aquí y no en cada
         pantalla para que ninguna tenga que acordarse de incluirlo: el propio
         partial no pinta nada salvo que el usuario esté de verdad a mitad del
         tutorial, así que en el 99 % de las cargas es un `return` y ya.
         Después de ui.js porque usa SRF.confirmar() y SRF.csrfToken(). */ ?>
<?php if (isset($db) && !empty($_SESSION['id_usuario'])) { include __DIR__ . '/tutorial.php'; } ?>
