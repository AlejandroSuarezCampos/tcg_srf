<?php
/**
 * COMPONENTE DE TARJETA DE CARTA — pieza central del sistema de diseño.
 *
 * Un único componente reutilizado literalmente en sobres, colección, álbum,
 * mercado, deck builder y duelos. Si una pantalla necesita algo distinto, se
 * añade una opción aquí; nunca se copia el marcado con variaciones.
 *
 * Reglas que este componente garantiza y que ninguna pantalla puede saltarse:
 *   1. El arte se muestra SIEMPRE completo (object-fit: contain), sobre una
 *      placa con halo de color según rareza. Nunca se recorta.
 *   2. La rareza lleva señal no cromática además del color (chevrones para
 *      poco común/raro/épico, corona para legendario, destello para SRF).
 *   3. Todo arte de carta lleva texto alternativo.
 *
 * Uso:
 *   require_once __DIR__ . '/components/carta.php';
 *   render_carta($cromo, ['href' => 'carta.php?id=' . $cromo['id_cromo']]);
 *
 * $cromo espera las claves que ya devuelven las consultas existentes:
 *   nombre, imagen, posicion, equipo, id_rareza, rareza, afinidad,
 *   afinidad_imagen. Todo lo demás es opcional.
 *
 * Opciones ($opts):
 *   tamano       'sm' | 'md' (por defecto) | 'lg'
 *   href         si se pasa, la carta se renderiza como enlace interactivo
 *   poseida      false ⇒ silueta apagada con candado (vista de álbum)
 *   protegida    true  ⇒ insignia de carta bloqueada para venta
 *   precio       int   ⇒ insignia de precio (mercado)
 *   seleccionada true  ⇒ anillo ámbar (deck builder)
 *   stats        array ['ATA' => 82, ...] hasta 3 pares etiqueta/valor
 *   clase        clases CSS extra
 *   datos        ['nombre' => 'x'] ⇒ atributos data-* para filtros de cliente
 *   lazy         false ⇒ carga inmediata de la imagen (cartas sobre el pliegue)
 *   acciones     HTML flotante sobre la carta (p. ej. el botón de proteger)
 *   pie          HTML al final del marco (p. ej. vendedor y botón de compra)
 *
 * `acciones` y `pie` reciben HTML ya generado en servidor. Existen para que
 * cada pantalla añada lo suyo sin duplicar el marcado de la carta.
 */

/**
 * Marca redundante no cromática de cada rareza.
 * Los chevrones se dibujan en CSS puro, así que siguen siendo legibles aunque
 * la fuente de iconos no cargue.
 */
function rareza_marcas(int $idRareza): string
{
    if ($idRareza >= 2 && $idRareza <= 4) {
        // 1 marca poco común · 2 raro · 3 épico
        return '<span class="rz-marcas" aria-hidden="true">'
            . str_repeat('<span class="rz-marca"></span>', $idRareza - 1)
            . '</span>';
    }
    if ($idRareza === 5) {
        return '<span class="rz-marcas" aria-hidden="true"><i class="ph-fill ph-crown-simple"></i></span>';
    }
    if ($idRareza === 6) {
        return '<span class="rz-marcas" aria-hidden="true"><i class="ph-fill ph-sparkle"></i></span>';
    }
    // Común: sin adorno, es la base del sistema.
    return '';
}

/**
 * Etiqueta de rareza. Se usa suelta (filtros, sala de duelo, leyendas) además
 * de dentro de la carta, siempre con el mismo lenguaje visual.
 */
function render_rareza(int $idRareza, string $nombreRareza, string $clase = ''): string
{
    return '<span class="rz ' . htmlspecialchars($clase) . '" data-rareza="' . $idRareza . '">'
        . rareza_marcas($idRareza)
        . '<span class="rz-texto">' . htmlspecialchars($nombreRareza) . '</span>'
        . '</span>';
}

/**
 * Igual que render_carta(), pero devuelve el HTML en vez de imprimirlo.
 * Lo usa la ceremonia de apertura de sobres para servir por AJAX exactamente
 * el mismo marcado que el resto del sitio, sin reimplementar la carta en JS.
 */
function carta_html(array $c, array $opts = []): string
{
    ob_start();
    render_carta($c, $opts);
    return trim(ob_get_clean());
}

function render_carta(array $c, array $opts = []): void
{
    $tamano       = $opts['tamano']       ?? 'md';
    $href         = $opts['href']         ?? null;
    $poseida      = $opts['poseida']      ?? true;
    $protegida    = $opts['protegida']    ?? false;
    $precio       = $opts['precio']       ?? null;
    $seleccionada = $opts['seleccionada'] ?? false;
    $stats        = $opts['stats']        ?? null;
    $claseExtra   = $opts['clase']        ?? '';
    $datos        = $opts['datos']        ?? [];
    $lazy         = $opts['lazy']         ?? true;
    $acciones     = $opts['acciones']     ?? '';
    $pie          = $opts['pie']          ?? '';

    $idRareza = (int) ($c['id_rareza'] ?? 1);
    $nombre   = (string) ($c['nombre'] ?? 'Carta sin nombre');
    $rareza   = (string) ($c['rareza'] ?? 'Común');
    $imagen   = (string) ($c['imagen'] ?? '');
    $equipo   = (string) ($c['equipo'] ?? '');
    $posicion = (string) ($c['posicion'] ?? '');
    $afinidad = (string) ($c['afinidad'] ?? '');
    $afinidadImg = (string) ($c['afinidad_imagen'] ?? '');

    // "No-afi" es el valor que usa la base de datos para las cartas sin
    // afinidad (escudos, presidentes): no se pinta el hexágono.
    $tieneAfinidad = $afinidad !== '' && strcasecmp($afinidad, 'No-afi') !== 0 && $afinidadImg !== '';

    $clases = ['carta'];
    if ($tamano !== 'md')  { $clases[] = 'carta--' . $tamano; }
    if ($href !== null)    { $clases[] = 'carta--accion'; }
    if (!$poseida)         { $clases[] = 'is-nopos'; }
    if ($seleccionada)     { $clases[] = 'is-seleccionada'; }
    if ($claseExtra !== '') { $clases[] = $claseExtra; }

    $attrs = '';
    foreach ($datos as $clave => $valor) {
        $attrs .= ' data-' . htmlspecialchars($clave) . '="' . htmlspecialchars((string) $valor) . '"';
    }

    $etiqueta = $href !== null ? 'a' : 'article';
    $apertura = '<' . $etiqueta
        . ' class="' . implode(' ', $clases) . '"'
        . ' data-rareza="' . $idRareza . '"'
        . ($href !== null ? ' href="' . htmlspecialchars($href) . '"' : '')
        . $attrs . '>';
    ?>
    <?= $apertura ?>

      <?php if ($protegida): ?>
        <span class="carta-insignia carta-insignia--protegida" title="Protegida: no se puede vender">
          <i class="ph ph-lock-simple" aria-hidden="true"></i>
          <span class="sr-only">Carta protegida, no se puede vender</span>
        </span>
      <?php endif; ?>

      <?php if ($precio !== null): ?>
        <span class="carta-insignia carta-insignia--precio">
          <i class="ph ph-coins" aria-hidden="true"></i>
          <?= number_format((int) $precio, 0, ',', '.') ?>
          <span class="sr-only">monedas</span>
        </span>
      <?php endif; ?>

      <?= $acciones ?>

      <?php if (!$poseida): ?>
        <span class="carta-candado">
          <i class="ph ph-lock-simple" aria-hidden="true"></i>
          Sin conseguir
        </span>
      <?php endif; ?>

      <div class="carta-marco">

        <div class="carta-head">
          <?= render_rareza($idRareza, $rareza) ?>
          <?php if ($tieneAfinidad): ?>
            <span class="carta-afinidad" title="Afinidad: <?= htmlspecialchars($afinidad) ?>">
              <img src="<?= htmlspecialchars($afinidadImg) ?>" alt="Afinidad <?= htmlspecialchars($afinidad) ?>">
            </span>
          <?php endif; ?>
        </div>

        <div class="carta-placa">
          <?php if ($imagen !== ''): ?>
            <img class="carta-arte"
                 src="<?= htmlspecialchars($imagen) ?>"
                 alt="Ilustración de <?= htmlspecialchars($nombre) ?>"
                 <?= $lazy ? 'loading="lazy" decoding="async"' : '' ?>>
          <?php else: ?>
            <span class="carta-placa-vacia" aria-hidden="true"><i class="ph ph-image-square"></i></span>
            <span class="sr-only">Esta carta todavía no tiene ilustración</span>
          <?php endif; ?>

          <?php if ($posicion !== ''): ?>
            <span class="carta-pos"><?= htmlspecialchars($posicion) ?></span>
          <?php endif; ?>
        </div>

        <div class="carta-cuerpo">
          <h3 class="carta-nombre"><?= htmlspecialchars($nombre) ?></h3>
          <p class="carta-meta">
            <span class="carta-equipo"><?= htmlspecialchars($equipo) ?></span>
          </p>

          <?php if (!empty($stats)): ?>
            <div class="carta-stats">
              <?php foreach (array_slice($stats, 0, 3, true) as $etiquetaStat => $valorStat): ?>
                <div class="carta-stat">
                  <b><?= htmlspecialchars((string) $valorStat) ?></b>
                  <span><?= htmlspecialchars((string) $etiquetaStat) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($pie !== ''): ?>
          <div class="carta-pie"><?= $pie ?></div>
        <?php endif; ?>

      </div>
    </<?= $etiqueta ?>>
    <?php
}
