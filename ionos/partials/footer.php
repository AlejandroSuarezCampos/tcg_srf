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
          <li><a href="<?= $base ?>album.php">Álbum</a></li>
          <li><a href="<?= $base ?>coleccion.php">Colección</a></li>
          <li><a href="<?= $base ?>mercado.php">Mercado</a></li>
        </ul>
      </nav>

      <nav class="pie-col" aria-label="Jugar">
        <h2 class="t-caption">Jugar</h2>
        <ul>
          <li><a href="<?= $base ?>sobres.php">Sobres</a></li>
          <li><a href="<?= $base ?>perfil.php">Perfil</a></li>
          <li><a href="https://superligafrontier.es">La liga</a></li>
        </ul>
      </nav>
    </div>
    <?php endif; ?>

    <div class="pie-legal">
      <p class="t-caption-sm t-dim">
        Proyecto fan-made sin ánimo de lucro y sin monetización.
        Inazuma Eleven es propiedad de Level-5. Sin afiliación ni respaldo oficial.
      </p>
      <p class="mono t-caption-sm t-dim">«Al agonzalo ese le gano fácil» · Payo Aguao</p>
    </div>

  </div>
</footer>

<?= assetScript($base, 'assets/js/ui.js') ?>
