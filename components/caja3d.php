<?php
/**
 * COMPONENTE DE CAJA/SOBRE 3D — prompt-claude-code-sobres-3d.md.
 *
 * Todo el volumen es CSS falso (perspective + preserve-3d + rotate/translateZ
 * sobre capas planas), nunca WebGL ni modelos reales. Las texturas de cada
 * cara vienen de Tcg::rutasPlantilla(), y si el admin no ha subido ninguna,
 * las claves faltan en $rutas y el CSS (assets/css/components.css) cae solo
 * al degradado por defecto — el render nunca se rompe por falta de assets.
 *
 * Reutilizado en tres sitios con el MISMO marcado, a propósito:
 *   · sobres.php       — el juego real (Fases 1-4 del prompt).
 *   · panel/plantillas.php (vista previa) — mismo componente que producción,
 *     para que el admin valide el resultado con el motor real, no una maqueta.
 *
 * Uso:
 *   require_once __DIR__ . '/components/caja3d.php';
 *   caja3d_html($db->rutasPlantilla('caja_expansion', $idExpansion), [...]);
 */

/**
 * Caja con front/top/side + tapa con bisagra propia + interior (el fondo que
 * se ve al levantar la tapa). Sirve tanto para la caja grande de una
 * expansión como para la caja pequeña de un tipo de sobre: la única
 * diferencia es la clase de escala.
 *
 * La tapa (.caja3d-tapa) reposa plegada sobre el propio "front" (misma
 * técnica que .sobre-3d-mitad en la ceremonia: transform-origin en su borde
 * superior, pegada sin necesidad de traslación). sobres.js la abre girándola
 * más allá de 90° (rotateX hasta ~-170) y desvaneciéndola a la vez, así que
 * "desaparece de la vista" en vez de perseguir un punto de bisagra físico
 * exacto en la cara "top" — es una ilusión 3D, no una junta real.
 *
 * $opts['interiorHtml']: HTML ya renderizado (normalmente N × sobre3d_mini_html())
 * que se inserta DENTRO de .caja3d-interior-scroll — a propósito en el mismo
 * árbol preserve-3d que la caja, nunca en un contenedor hermano, para que
 * "levantar" un sobre se perciba dentro del hueco de la caja (requisito
 * técnico del prompt de sobres 3D). .caja3d-interior-scroll es el único punto
 * con overflow-y:auto de toda la caja: es la única forma de tener scroll
 * interno Y transforms 3D a la vez sin que el navegador aplane el resto de
 * caras (overflow != visible fuerza un contexto de recorte que rompe
 * preserve-3d en los DESCENDIENTES de ESE elemento, no en sus hermanos).
 *
 * $rutas viene de Tcg::rutasPlantilla('caja_expansion'|'caja_sobre', $id).
 * Opciones: escala ('grande'|'pequena'), clase, id, datos (data-*), interiorHtml.
 */
function caja3d_html(array $rutas, array $opts = []): string
{
    $escala = $opts['escala'] ?? 'grande';
    $clase  = trim('caja3d caja3d--' . $escala . ' ' . ($opts['clase'] ?? ''));
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
  <div class="caja3d-caras">
    <div class="caja3d-tilt">
      <div class="caja3d-cara caja3d-front"<?= $fondo('front') ?>></div>
      <div class="caja3d-cara caja3d-side"<?= $fondo('side') ?>></div>
      <div class="caja3d-cara caja3d-top"<?= $fondo('top') ?>></div>
      <div class="caja3d-tapa"<?= $fondo('lid') ?>></div>
      <div class="caja3d-interior"<?= $fondo('interior') ?>>
        <div class="caja3d-interior-scroll"><?= $interiorHtml ?></div>
      </div>
    </div>
  </div>
</div>
    <?php
    return trim(ob_get_clean());
}

/**
 * Sobre individual con frente y reverso (relieve simulado con gradientes,
 * no geometría real: un sobre es plano). $rutas viene de
 * Tcg::rutasPlantilla('sobre', $idSobre). Opciones: clase, datos (data-*).
 */
function sobre3d_mini_html(array $rutas, array $opts = []): string
{
    $clase  = trim('sobre3d-mini ' . ($opts['clase'] ?? ''));
    $idAttr = isset($opts['id']) ? ' id="' . htmlspecialchars($opts['id']) . '"' : '';
    $deshabilitado = !empty($opts['disabled']);

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
<button type="button" class="<?= $clase ?>"<?= $idAttr . $datosAttr ?>
        <?= $deshabilitado ? 'disabled title="No tienes monedas suficientes"' : '' ?>>
  <div class="sobre3d-mini-frente"<?= $fondo('frente') ?>></div>
  <div class="sobre3d-mini-reverso"<?= $fondo('reverso') ?>></div>
</button>
    <?php
    return trim(ob_get_clean());
}
