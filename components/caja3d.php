<?php
/**
 * COMPONENTE — Caja/sobre en pseudo-3D. Volumen 100% CSS: perspective +
 * preserve-3d + translateZ/rotateX/rotateY. Nunca WebGL ni modelos reales.
 *
 * Referencia visual: una caja de cromos tipo Panini/blaster — se ven tres
 * caras (frente, tapa superior, lateral), con grosor real, y al abrirla los
 * sobres están DE PIE dentro, apretados de delante hacia atrás, sobresaliendo
 * un poco por encima del borde.
 *
 * GEOMETRÍA (importante, no la "simplifiques" a transform-origin + pliegue):
 * cada cara se centra primero en el volumen con translate(-50%,-50%) y luego
 * se empuja a su sitio con translateX/Y/Z + rotate. Es la única forma fiable
 * de armar un prisma no cúbico. Plegar las caras desde su arista con
 * transform-origin (lo que hacía la versión anterior) las dejaba casi de
 * canto: la caja se veía como un rectángulo plano, sin volumen.
 *
 *   .pack3d                     host: define --pack3d-w/h/d y la perspectiva
 *     .pack3d-sombra            sombra de contacto en el suelo
 *     .pack3d-volumen           preserve-3d; idle CSS en reposo
 *       .pack3d-tilt            preserve-3d; SOLO el tilt al cursor (GSAP)
 *         .pack3d-cara--front|--top|--side
 *         .pack3d-bisagra       línea de charnela en la arista trasera-superior
 *           .pack3d-tapa        gira 90°→205° para abrirse hacia arriba y atrás
 *         .pack3d-interior      suelo de la caja (se ve al abrir)
 *         .pack3d-interior-sobres
 *           .pack3d-sobre × N   de pie, escalonados en Z por --i
 *
 * Sin plantilla subida (Fase 5) faltan las claves de $rutas y el CSS cae al
 * degradado por defecto: el render nunca se rompe por falta de assets.
 */
function pack3d_caja_html(array $rutas, array $opts = []): string
{
    $escala = $opts['escala'] ?? 'grande';
    $clase  = trim('pack3d pack3d--' . $escala . ' ' . ($opts['clase'] ?? ''));
    $idAttr = isset($opts['id']) ? ' id="' . htmlspecialchars($opts['id']) . '"' : '';
    $interiorHtml = $opts['interiorHtml'] ?? '';

    $datosAttr = '';
    foreach ($opts['datos'] ?? [] as $clave => $valor) {
        $datosAttr .= ' data-' . htmlspecialchars($clave) . '="' . htmlspecialchars($valor) . '"';
    }

    $fondo = function (string $zona) use ($rutas): string {
        return isset($rutas[$zona])
            ? ' style="background-image:url(\'' . htmlspecialchars($rutas[$zona]) . '\')"'
            : '';
    };

    ob_start();
    ?>
<div class="<?= $clase ?>"<?= $idAttr . $datosAttr ?>>
  <div class="pack3d-sombra" aria-hidden="true"></div>
  <div class="pack3d-volumen">
    <div class="pack3d-tilt">
      <div class="pack3d-cara pack3d-cara--front"<?= $fondo('front') ?>></div>
      <div class="pack3d-cara pack3d-cara--side"<?= $fondo('side') ?>></div>
      <div class="pack3d-cara pack3d-cara--top"<?= $fondo('top') ?>></div>
      <div class="pack3d-interior"<?= $fondo('interior') ?>></div>
      <?php /* ⚠️ LOS SOBRES VAN EN UN <template>, NO SUELTOS EN LA PÁGINA.
               Son 50 por caja, y con tres tipos en pantalla eran 150 botones
               con su fondo y su transform 3D pintándose de golpe en una caja
               que está CERRADA y de la que no se ve ni uno. En un móvil de
               gama baja eso es medio segundo largo de trabajo tirado.
               Dentro de un <template> el navegador no los maqueta ni pide sus
               imágenes; sobres.js los clona la primera vez que se abre esa
               caja, que es cuando de verdad hacen falta. */ ?>
      <div class="pack3d-interior-sobres"></div>
      <template class="pack3d-sobres-plantilla"><?= $interiorHtml ?></template>
      <div class="pack3d-bisagra">
        <div class="pack3d-tapa"<?= $fondo('lid') ?>></div>
      </div>
    </div>
  </div>
</div>
    <?php
    return trim(ob_get_clean());
}

/**
 * Sobre individual, de pie dentro de la caja. $opts['indice'] es su posición
 * en la fila (0..N-1) y $opts['total'] cuántos hay: el CSS los reparte en
 * profundidad con esos dos números, sin generar una regla por sobre.
 */
function pack3d_sobre_html(array $rutas, array $opts = []): string
{
    $clase  = trim('pack3d-sobre ' . ($opts['clase'] ?? ''));
    $idAttr = isset($opts['id']) ? ' id="' . htmlspecialchars($opts['id']) . '"' : '';
    $deshabilitado = !empty($opts['disabled']);

    $indice = (int) ($opts['indice'] ?? 0);
    $total  = max(1, (int) ($opts['total'] ?? 1));
    // --i y --n los consume .pack3d-sobre para su translateZ. El "-1" reparte
    // los N sobres simétricos respecto al centro de la caja.
    $estilo = ' style="--i:' . $indice . ';--n:' . $total . '"';

    $datosAttr = '';
    foreach ($opts['datos'] ?? [] as $clave => $valor) {
        $datosAttr .= ' data-' . htmlspecialchars($clave) . '="' . htmlspecialchars($valor) . '"';
    }

    $fondo = function (string $zona) use ($rutas): string {
        return isset($rutas[$zona])
            ? 'background-image:url(\'' . htmlspecialchars($rutas[$zona]) . '\');'
            : '';
    };

    ob_start();
    ?>
<button type="button" class="<?= $clase ?>"<?= $idAttr . $datosAttr . $estilo ?>
        <?= $deshabilitado ? 'disabled title="No tienes monedas suficientes"' : '' ?>>
  <span class="pack3d-sobre-frente" style="<?= $fondo('frente') ?>"></span>
  <span class="pack3d-sobre-canto" aria-hidden="true"></span>
</button>
    <?php
    return trim(ob_get_clean());
}
