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
 *   afinidad_imagen, rasgo (el rasgo de CONFIGURACIÓN de la Capa 2 —
 *   Contraataque/Justicia/Vínculo/Brecha—, si la consulta lo trae).
 *   Todo lo demás es opcional.
 *
 * Opciones ($opts):
 *   tamano       'sm' | 'md' (por defecto) | 'lg'
 *   href         si se pasa, la carta se renderiza como enlace interactivo
 *   poseida      false ⇒ silueta apagada con candado (vista de álbum)
 *   protegida    true  ⇒ insignia de carta bloqueada para venta
 *   precio       int   ⇒ insignia de precio (mercado)
 *   seleccionada true  ⇒ anillo ámbar (deck builder)
 *   cantidad     int   ⇒ insignia "×N" junto al hexágono de afinidad, para
 *                colecciones con copias repetidas (ver coleccion.php)
 *   stats        array ['ATA' => 82, ...] hasta 3 pares etiqueta/valor
 *   clase        clases CSS extra
 *   datos        ['nombre' => 'x'] ⇒ atributos data-* para filtros de cliente
 *   lazy         false ⇒ carga inmediata de la imagen (cartas sobre el pliegue)
 *   acciones     HTML flotante sobre la carta (p. ej. el botón de proteger)
 *   pie          HTML al final del marco (p. ej. vendedor y botón de compra)
 *   modo         'ficha' (por defecto, la carta de siempre) | 'arte'
 *   ficha        true ⇒ al pulsar abre el modal de ficha (requiere modo 'arte'
 *                y que la página incluya partials/ficha_carta.php)
 *   leyenda      en modo 'arte', pie de foto bajo el marco. POR DEFECTO NO HAY:
 *                el borde de rareza y la plantilla ya dicen de qué rareza es,
 *                y repetirlo debajo en texto era ruido. Pásale un string si
 *                una pantalla concreta necesita un pie.
 *
 * `acciones` y `pie` reciben HTML ya generado en servidor. Existen para que
 * cada pantalla añada lo suyo sin duplicar el marcado de la carta.
 *
 * ── MODO 'arte' ──────────────────────────────────────────────────────────
 * El arte ocupa la carta ENTERA, a sangre, con esquinas rectas y sin ningún
 * dato encima: ni nombre, ni rareza, ni estadísticas. Toda esa información
 * pasa al modal de ficha (`partials/ficha_carta.php`), que se abre al pulsar.
 *
 * Lo que NO cambia respecto al modo 'ficha', porque son las tres reglas del
 * componente y no dependen de la variante:
 *   · el borde de rareza sigue siendo `padding + background` (nunca un
 *     `border`), así que la SRF conserva su arcoíris animado y la legendaria
 *     su degradado metálico — decisión confirmada, el mockup no lo capturó;
 *   · la marca no cromática de rareza sigue existiendo: aquí vive en el pie
 *     de foto (`leyenda`), que es texto real, no color;
 *   · el arte sigue llevando texto alternativo, y la carta interactiva su
 *     nombre accesible.
 *
 * El arte sigue en `contain`, sin excepción. No hace falta `cover` para que
 * llene la carta: el marco adopta la MISMA proporción que la placa de arte
 * (233/361), así que la ilustración la cubre entera sin recortar un píxel.
 * Si algún día llega arte con otra proporción, se verá con banda antes que
 * recortado — que es exactamente la regla 1 del componente.
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
    if ($idRareza === 7) {
        // Numerada: el hash, que es literalmente lo que la distingue.
        return '<span class="rz-marcas" aria-hidden="true"><i class="ph-bold ph-hash"></i></span>';
    }
    // Común: sin adorno, es la base del sistema.
    return '';
}

/**
 * ¿Esta carta se pinta CON MARCO?
 *
 * Dependen dos cosas, y las dos tienen que cumplirse.
 *
 * 1. QUE SU ARTE SEA LA FOTO DEL JUGADOR, Y ESO SE MIDE: ES CUADRADA.
 *    El marco no es un adorno que se pueda poner encima de cualquier cosa: es
 *    una maqueta con un hueco CUADRADO para un RETRATO. Un retrato cuadrado
 *    entra entero; un arte vertical de 451x800 metido ahí se recorta justo por
 *    donde se dibujó para llenar la carta.
 *
 *    ⚠️ ANTES SE MIRABA LA CARPETA (`/Cromos/Importados/`) Y ESO DEJABA FUERA
 *       A TODO LO DEMÁS QUE TAMBIÉN ERA UN RETRATO. Las 88 cartas cuadradas de
 *       `/Cromos/InazumaWorldCup/` se pintaban a sangre —foto de 256x256
 *       estirada a la carta entera, sin marco de rareza y sin placa de nombre—
 *       solo por vivir en otra carpeta. La carpeta era un proxy de "esto es un
 *       retrato"; la forma de la imagen ES el dato, así que ahora se mide.
 *
 *    Medido sobre la biblioteca actual: 743 de 743 en `Importados` son
 *    cuadradas (la regla vieja y la nueva coinciden ahí, por eso sigue estando
 *    el atajo sin E/S), 88 de 96 en `InazumaWorldCup`, y el resto del catálogo
 *    —ALL STARS, Apuesta Segura, Cartas Exclusivas— no tiene ni una: son artes
 *    a sangre y se quedan como estaban.
 *
 *    Es lo que decide, no la rareza. Hasta hace poco se miraba el nivel (1 a 4)
 *    y coincidía de casualidad, porque todo lo importado es de rareza baja; en
 *    cuanto exista una legendaria con foto de jugador, la regla por rareza
 *    habría fallado en los dos sentidos.
 *
 * 2. QUE EXISTA LA PLANTILLA DE SU RAREZA en `assets/img/marcos/`.
 *    Sin la imagen detrás, la maqueta deja lo que ya se vio una vez: un
 *    rectángulo negro con la foto encogida en una esquina y el nombre —tinta
 *    oscura pensada para el rectángulo blanco— invisible. Así que si falta el
 *    archivo, la carta se pinta a sangre en lugar de romperse.
 *
 * Los nombres de archivo van en minúscula y sin acentos: en un servidor Linux
 * `SRF.png` y `srf.png` son dos archivos distintos.
 */
function carta_usa_marco(int $idRareza, string $imagen): bool
{
    static $cache = null;

    if ($cache === null) {
        $nombres = [
            1 => 'comun', 2 => 'poco-comun', 3 => 'raro', 4 => 'epico',
            5 => 'legendario', 6 => 'srf', 7 => 'numerada',
        ];
        $cache = [];
        $dir = __DIR__ . '/../assets/img/marcos/';
        foreach ($nombres as $id => $slug) {
            foreach (['png', 'jpg', 'webp'] as $ext) {
                if (is_file($dir . $slug . '.' . $ext)) { $cache[$id] = true; break; }
            }
        }
    }

    if (!isset($cache[$idRareza])) { return false; }

    // Sin arte no hay retrato que enmarcar: la carta cae al marcador de
    // "todavía sin ilustración", que se ve mejor solo que dentro de un marco.
    if ($imagen === '') { return false; }

    /* Atajo sin E/S para lo importado, que son 743 cartas y TODAS cuadradas:
       es el caso mayoritario de cualquier listado, y resolverlo con una
       comparación de cadena ahorra abrir 743 ficheros por página. */
    if (stripos($imagen, '/Cromos/Importados/') !== false) { return true; }

    return carta_imagen_cuadrada($imagen);
}

/**
 * ¿La imagen es un retrato cuadrado, o sea del tipo que cabe en la plantilla?
 *
 * ⚠️ SE MEMORIZA POR PETICIÓN. Un listado repite la misma carta varias veces
 *    (rejilla y miniatura de la fila, por ejemplo) y sin caché se abriría el
 *    fichero una vez por aparición. `getimagesize()` solo lee la cabecera, pero
 *    200 cartas × 2 apariciones son 400 accesos a disco por página gratis.
 *
 * ⚠️ TOLERANCIA DEL 2 %, NO IGUALDAD EXACTA. Los retratos vienen de recortes y
 *    conversiones, y un 256x255 es tan retrato como un 256x256. Con `==` esa
 *    carta se habría quedado sin marco y nadie habría sabido por qué.
 *
 * Un fichero que no existe devuelve `false` y la carta se pinta a sangre, que
 * es lo que ya hacía: nunca se rompe por una ruta muerta.
 */
function carta_imagen_cuadrada(string $imagen): bool
{
    /* EL MANIFIESTO PRIMERO, EL DISCO SOLO SI NO ESTÁ.
       `components/cromos_cuadrados.php` trae la respuesta ya calculada para
       las 890 imágenes del catálogo, así que lo normal es no tocar el disco
       ni una vez. Se carga una sola vez por petición y son 63 KB de array,
       que PHP resuelve en microsegundos.

       El respaldo a `getimagesize()` NO sobra: una carta subida desde el panel
       no está en el manifiesto hasta que alguien lo regenere, y sin el
       respaldo saldría sin marco sin que nadie supiera por qué. */
    static $manifiesto = null;
    static $memo = [];

    if ($manifiesto === null) {
        $f = __DIR__ . '/cromos_cuadrados.php';
        $manifiesto = is_file($f) ? require $f : [];
    }
    /* `array_key_exists` y no `isset`: el manifiesto trae TODAS las imágenes
       del catálogo, también las que NO son cuadradas, y con `isset` un `false`
       se leería como "no la conozco" y volvería a abrir el fichero — que es
       justo lo que se venía a evitar para esas 58. */
    if (array_key_exists($imagen, $manifiesto)) { return $manifiesto[$imagen]; }

    if (isset($memo[$imagen])) { return $memo[$imagen]; }

    // Las rutas se guardan relativas a la raíz del sitio ('./assets/...');
    // este fichero vive en components/, así que se sube uno.
    $ruta = __DIR__ . '/../' . preg_replace('#^\./#', '', $imagen);

    $cuadrada = false;
    if (is_file($ruta)) {
        $medidas = @getimagesize($ruta);
        if ($medidas && $medidas[0] > 0 && $medidas[1] > 0) {
            $cuadrada = abs($medidas[0] - $medidas[1]) <= max($medidas[0], $medidas[1]) * 0.02;
        }
    }

    return $memo[$imagen] = $cuadrada;
}

/**
 * NÚMERO DE SERIE de una carta numerada (rareza 7, migración `038`).
 *
 * Se pinta solo si la copia TIENE número: la misma carta puede existir sin él
 * —cuando se mira en el álbum, que enseña el catálogo y no una copia concreta—
 * y ahí no hay ningún #7/50 que contar.
 *
 * El cupo se enseña junto al número. "#7" a secas no dice nada; "#7/50" dice
 * de cuántas estamos hablando, que es lo único que hace especial a una tirada
 * limitada.
 */
function render_serie($numero, $cupo): string
{
    $numero = (int) $numero;
    if ($numero <= 0) { return ''; }

    $cupo = (int) $cupo;
    $texto = '#' . $numero . ($cupo > 0 ? '/' . $cupo : '');

    return '<span class="carta-serie" title="Copia numerada">'
        . '<span class="sr-only">Copia numerada </span>'
        . htmlspecialchars($texto)
        . '</span>';
}

/* EL UNIVERSO NO SE PINTA EN LA CARTA.
   Tuvo una insignia («SRF» / «IE») en la esquina y en las filas de lista, y
   sobraba: en una rejilla de cincuenta cartas eran cincuenta etiquetas
   repitiendo casi siempre lo mismo, compitiendo con la rareza —que sí hay que
   distinguir de un vistazo— por el mismo rincón de la carta. Y abreviada no
   decía gran cosa a quien no supiera ya lo que significa.
   Sigue viajando en `data-universo` y se enseña SOLO en el modal de ficha, con
   el nombre completo, que es donde hay sitio para leerlo. Lo pinta ui.js. */

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

/**
 * CONMUTADOR DE VISTA (rejilla / lista).
 *
 * El mercado y la colección enseñan lo mismo de dos formas, y cada una gana en
 * un caso: la rejilla para mirar el arte, la lista para comparar
 * estadísticas y precios de un vistazo. La elección viaja en la URL (`vista`),
 * así que no hace falta JavaScript, la vista se puede enlazar y compartir, y
 * sobrevive a los filtros —que también son GET— sin estado que sincronizar.
 *
 * $porDefecto es distinto en cada pantalla a propósito: el mercado nace en
 * lista (se va a comparar precios) y la colección en rejilla (se va a mirar).
 */
function vista_actual(string $porDefecto): string
{
    $v = $_GET['vista'] ?? '';
    return in_array($v, ['rejilla', 'lista'], true) ? $v : $porDefecto;
}

function render_vista_conmutador(string $vistaActual): void
{
    // Se conservan los filtros que ya haya en la URL: cambiar de vista nunca
    // debe deshacer una búsqueda.
    $params = $_GET;
    $opciones = [
        'rejilla' => ['ph-squares-four', 'Rejilla'],
        'lista'   => ['ph-list-dashes', 'Lista'],
    ];
    ?>
    <div class="vista-conmutador" role="group" aria-label="Forma de ver las cartas">
      <?php foreach ($opciones as $clave => [$icono, $texto]): ?>
        <?php $params['vista'] = $clave; ?>
        <a href="?<?= htmlspecialchars(http_build_query($params)) ?>"
           aria-current="<?= $vistaActual === $clave ? 'true' : 'false' ?>">
          <i class="ph <?= $icono ?>" aria-hidden="true"></i> <?= $texto ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php
}

/**
 * FILA DE CARTA — la misma carta, en horizontal.
 *
 * La vista de lista del mercado y de la colección: miniatura del arte a la
 * izquierda, jugador y equipo, las tres estadísticas reales y un hueco a la
 * derecha para lo que cada pantalla necesite (precio y "Comprar" en el
 * mercado, acciones en la colección).
 *
 * NO es una tabla. Se probó y no sale a cuenta: con miniatura, tres
 * estadísticas y un botón, seis columnas obligan a scroll horizontal en móvil.
 * Aquí es una lista con rejilla que se apila sola en pantalla estrecha.
 *
 * Deliberadamente NO hay una valoración global. El mockup traía una, y la
 * decisión fue repartirla en las tres estadísticas de verdad: un número medio
 * esconde justo lo que diferencia a un portero de un delantero.
 *
 * $opts:
 *   ficha    true ⇒ el nombre abre el modal de ficha (mismo modal que la
 *            rejilla; requiere partials/ficha_carta.php en la página)
 *   derecha  HTML del bloque derecho (precio, botón, acciones…)
 *   meta     texto extra bajo el nombre (p. ej. "Vende Fulano")
 *   datos    atributos data-* extra
 *   accion   true ⇒ la fila ENTERA es un botón (deck builder: pulsar la fila
 *            asigna ese jugador al hueco activo). Incompatible con `ficha` y
 *            con `derecha`: un botón no puede llevar otro botón dentro, así
 *            que cuando la fila es pulsable no lleva controles propios.
 *   radio    ['name' => …, 'value' => …, 'marcado' => bool, 'tipo' => …] ⇒ la
 *            fila ENTERA es una opción de un grupo. Mismo motivo que `accion`
 *            para no llevar controles dentro. El input va oculto pero real, así
 *            que el grupo funciona con teclado y el formulario lo envía sin
 *            JavaScript.
 *            `tipo` es 'radio' (por defecto) o 'checkbox'. Con checkbox se
 *            eligen varias —apostar un lote de cartas en un duelo—; el nombre
 *            del campo lo pone quien llama, con sus corchetes si toca.
 *   elegida  marca la fila como ya usada (anillo ámbar)
 *   desactivada  la fila no se puede pulsar
 */
function render_carta_fila(array $c, array $opts = []): void
{
    $ficha       = $opts['ficha']       ?? false;
    $derecha     = $opts['derecha']     ?? '';
    $meta        = $opts['meta']        ?? '';
    $datos       = $opts['datos']       ?? [];
    $accion      = $opts['accion']      ?? false;
    $radio       = $opts['radio']       ?? null;
    $elegida     = $opts['elegida']     ?? false;
    $desactivada = $opts['desactivada'] ?? false;

    // Una fila pulsable —botón o radio— no puede contener otros controles: ni
    // el botón de ficha ni los del bloque derecho. Se resuelve aquí, una vez,
    // en vez de repetir la condición en cada llamada.
    if ($accion || $radio) { $ficha = false; $derecha = ''; }

    $idRareza = (int) ($c['id_rareza'] ?? 1);
    $nombre   = (string) ($c['nombre'] ?? 'Carta sin nombre');
    $rareza   = (string) ($c['rareza'] ?? 'Común');
    $imagen   = (string) ($c['imagen'] ?? '');
    $equipo   = (string) ($c['equipo'] ?? '');
    $posicion = (string) ($c['posicion'] ?? '');
    $afinidad = (string) ($c['afinidad'] ?? '');
    $rasgo    = (string) ($c['rasgo'] ?? '');
    $universo = (string) ($c['universo'] ?? '');
    $serieFila = render_serie($c['numero_serie'] ?? 0, $c['cupo_numerado'] ?? 0);

    $datos += [
        'nombre'        => $nombre,
        'rareza-nombre' => $rareza,
        'equipo'        => $equipo,
        'posicion'      => $posicion,
        'imagen'        => $imagen,
        'rasgo'         => $rasgo,
        'afinidad'      => strcasecmp($afinidad, 'No-afi') === 0 ? '' : $afinidad,
        'afinidad-img'  => (string) ($c['afinidad_imagen'] ?? ''),
        'universo'      => (string) ($c['universo'] ?? ''),
        'ataque'        => (int) ($c['ataque']  ?? 0),
        'defensa'       => (int) ($c['defensa'] ?? 0),
        'tecnica'       => (int) ($c['tecnica'] ?? 0),
    ];
    $attrs = '';
    foreach ($datos as $clave => $valor) {
        $attrs .= ' data-' . htmlspecialchars($clave) . '="' . htmlspecialchars((string) $valor) . '"';
    }

    /* Las mismas tres estadísticas, el mismo color y el mismo icono que en el
       modal de ficha. Nunca solo color: cada una lleva su abreviatura. */
    $tresStats = [
        ['mod' => 'ata', 'abrev' => 'ATA', 'largo' => 'Ataque',  'icono' => 'ph-sword',     'valor' => (int) ($c['ataque']  ?? 0)],
        ['mod' => 'def', 'abrev' => 'DEF', 'largo' => 'Defensa', 'icono' => 'ph-shield',    'valor' => (int) ($c['defensa'] ?? 0)],
        ['mod' => 'tec', 'abrev' => 'TÉC', 'largo' => 'Técnica', 'icono' => 'ph-lightning', 'valor' => (int) ($c['tecnica'] ?? 0)],
    ];
    ?>
    <?php
    $clasesFila = ['carta-fila'];
    if ($accion || $radio) { $clasesFila[] = 'carta-fila--accion'; }
    if ($elegida)          { $clasesFila[] = 'esta-elegida'; }
    ?>
    <li class="<?= implode(' ', $clasesFila) ?>" data-rareza="<?= $idRareza ?>"<?= $attrs ?>>

      <?php if ($accion): ?>
      <button type="button" class="carta-fila-interior"<?= $desactivada ? ' disabled' : '' ?>>
      <?php elseif ($radio): ?>
        <?php /* Un `checkbox` y un `radio` se marcan igual, se leen igual con
                 teclado y se envían igual: lo único que cambia es el `type`, así
                 que comparten rama en vez de duplicar la fila entera. */ ?>
      <label class="carta-fila-interior">
        <input type="<?= ($radio['tipo'] ?? 'radio') === 'checkbox' ? 'checkbox' : 'radio' ?>"
               class="sr-only"
               name="<?= htmlspecialchars($radio['name']) ?>"
               value="<?= htmlspecialchars((string) $radio['value']) ?>"
               <?= !empty($radio['marcado']) ? 'checked' : '' ?>>
      <?php endif; ?>

      <?php
      /* La miniatura lleva la MISMA plantilla que la carta grande (Común→Épico
         la traen de `assets/img/marcos/`). Sin ella, en el mercado y en el
         selector de apuesta se veía la fotografía del jugador a pelo, que no
         es como se reconoce una carta de este juego.
         El nombre no se pinta encima: a 56px sería ilegible, y aquí al lado ya
         está escrito. */
      $miniMarco = carta_usa_marco($idRareza, $imagen);
      ?>
      <span class="carta-fila-miniatura<?= $miniMarco ? ' carta-fila-miniatura--marco' : '' ?>"
            data-rareza="<?= $idRareza ?>" data-marco="<?= $miniMarco ? '1' : '0' ?>">
        <?php if ($imagen !== ''): ?>
          <img src="<?= htmlspecialchars($imagen) ?>"
               alt="Ilustración de <?= htmlspecialchars($nombre) ?>"
               loading="lazy" decoding="async">
        <?php else: ?>
          <i class="ph ph-image-square" aria-hidden="true"></i>
          <span class="sr-only">Sin ilustración</span>
        <?php endif; ?>
      </span>

      <span class="carta-fila-jugador">
        <?php if ($ficha): ?>
          <button type="button" class="carta-fila-nombre" data-ficha-carta>
            <?= htmlspecialchars($nombre) ?>
          </button>
        <?php else: ?>
          <span class="carta-fila-nombre"><?= htmlspecialchars($nombre) ?></span>
        <?php endif; ?>
        <span class="carta-fila-meta"><?= $meta !== '' ? $meta : htmlspecialchars($equipo) ?></span>
      </span>

      <span class="carta-fila-pos">
        <?php if ($posicion !== ''): ?>
          <span class="pastilla"><?= htmlspecialchars($posicion) ?></span>
        <?php endif; ?>
        <?= $serieFila ?>
        <?= render_rareza($idRareza, $rareza, 'carta-fila-rz') ?>
        <?php if ($rasgo !== ''): ?>
          <?php /* La compo, que en el deck builder es media decisión: sin ella
                   hay que abrir la ficha de cada jugador para saber si suma. */ ?>
          <span class="pastilla carta-fila-compo">
            <i class="ph ph-hexagon" aria-hidden="true"></i>
            <span class="sr-only">Compo: </span><?= htmlspecialchars($rasgo) ?>
          </span>
        <?php endif; ?>
      </span>

      <span class="carta-fila-stats">
        <?php foreach ($tresStats as $s): ?>
          <span class="carta-fila-stat carta-fila-stat--<?= $s['mod'] ?>">
            <i class="ph-fill <?= $s['icono'] ?>" aria-hidden="true"></i>
            <b class="mono"><?= $s['valor'] ?></b>
            <span class="carta-fila-stat-abrev" aria-hidden="true"><?= $s['abrev'] ?></span>
            <span class="sr-only"><?= $s['largo'] ?></span>
          </span>
        <?php endforeach; ?>
      </span>

      <?php if ($derecha !== ''): ?>
        <span class="carta-fila-derecha"><?= $derecha ?></span>
      <?php endif; ?>

      <?php if ($accion): ?>
      </button>
      <?php elseif ($radio): ?>
      </label>
      <?php endif; ?>

    </li>
    <?php
}

function render_carta(array $c, array $opts = []): void
{
    $tamano       = $opts['tamano']       ?? 'md';
    $href         = $opts['href']         ?? null;
    $poseida      = $opts['poseida']      ?? true;
    $protegida    = $opts['protegida']    ?? false;
    $precio       = $opts['precio']       ?? null;
    $seleccionada = $opts['seleccionada'] ?? false;
    $cantidad     = $opts['cantidad']     ?? null;
    $stats        = $opts['stats']        ?? null;
    $claseExtra   = $opts['clase']        ?? '';
    $datos        = $opts['datos']        ?? [];
    $lazy         = $opts['lazy']         ?? true;
    $acciones     = $opts['acciones']     ?? '';
    $pie          = $opts['pie']          ?? '';
    $modo         = $opts['modo']         ?? 'ficha';
    $ficha        = $opts['ficha']        ?? false;
    $leyenda      = $opts['leyenda']      ?? null;

    $idRareza = (int) ($c['id_rareza'] ?? 1);
    $nombre   = (string) ($c['nombre'] ?? 'Carta sin nombre');
    $rareza   = (string) ($c['rareza'] ?? 'Común');
    $imagen   = (string) ($c['imagen'] ?? '');
    $equipo   = (string) ($c['equipo'] ?? '');
    $posicion = (string) ($c['posicion'] ?? '');
    $afinidad = (string) ($c['afinidad'] ?? '');
    $afinidadImg = (string) ($c['afinidad_imagen'] ?? '');
    $rasgo = (string) ($c['rasgo'] ?? '');
    // Va en la carta (migración `037`). Sin dato no se pinta insignia.
    $universo = (string) ($c['universo'] ?? '');
    // Solo lo traen las COPIAS numeradas, no el catálogo (migración `038`).
    $numeroSerie = (int) ($c['numero_serie'] ?? 0);
    $cupoNumerado = (int) ($c['cupo_numerado'] ?? 0);

    // "No-afi" es el valor que usa la base de datos para las cartas sin
    // afinidad (escudos, presidentes): no se pinta el hexágono.
    $tieneAfinidad = $afinidad !== '' && strcasecmp($afinidad, 'No-afi') !== 0 && $afinidadImg !== '';

    $esArte = $modo === 'arte';
    // La ficha solo tiene sentido en modo arte: es adonde se ha mudado la
    // información que ese modo quita de encima del arte.
    $ficha = $ficha && $esArte;

    $clases = ['carta'];
    if ($tamano !== 'md')  { $clases[] = 'carta--' . $tamano; }
    if ($esArte)           { $clases[] = 'carta--arte'; }
    if ($href !== null || $ficha) { $clases[] = 'carta--accion'; }
    if (!$poseida)         { $clases[] = 'is-nopos'; }
    if ($seleccionada)     { $clases[] = 'is-seleccionada'; }
    if ($claseExtra !== '') { $clases[] = $claseExtra; }

    /* En modo arte la carta lleva SIEMPRE sus datos como atributos `data-*`:
       es de ahí de donde el modal de ficha se rellena, sin una segunda
       consulta ni un endpoint AJAX. Los `datos` que pase la pantalla se
       fusionan encima, así que una pantalla puede añadir los suyos (o
       sobrescribir uno) sin perder estos. */
    if ($esArte) {
        $datos += [
            /* OJO: aquí NO puede haber una clave `rareza`. El componente ya
               escribe `data-rareza` con el ID NUMÉRICO —lo lee todo el CSS de
               rarezas— y una segunda clave con ese nombre lo pisaba: el modal
               enseñaba "4" en vez de "Épico". Por eso el nombre va en
               `rareza-nombre`. */
            'nombre'        => $nombre,
            'rareza-nombre' => $rareza,
            'equipo'        => $equipo,
            'posicion'      => $posicion,
            'imagen'        => $imagen,
            /* La afinidad se enseña aunque no tenga hexágono: `$tieneAfinidad`
               exige imagen porque decide si se PINTA el icono, y aquí es solo
               texto. Lo único que sigue fuera es "No-afi", que no es una
               afinidad sino su ausencia. */
            'afinidad'      => strcasecmp($afinidad, 'No-afi') === 0 ? '' : $afinidad,
            'afinidad-img'  => $afinidadImg,
            'universo'      => $universo,
            'serie'         => $numeroSerie > 0
                ? ('#' . $numeroSerie . ($cupoNumerado > 0 ? '/' . $cupoNumerado : ''))
                : '',
            'ataque'        => (int) ($c['ataque']  ?? 0),
            'defensa'       => (int) ($c['defensa'] ?? 0),
            'tecnica'       => (int) ($c['tecnica'] ?? 0),
        ];
        if ($rasgo !== '')  { $datos += ['rasgo' => $rasgo]; }
        if ($precio !== null) { $datos += ['precio' => (int) $precio]; }
    }

    $attrs = '';
    foreach ($datos as $clave => $valor) {
        $attrs .= ' data-' . htmlspecialchars($clave) . '="' . htmlspecialchars((string) $valor) . '"';
    }

    /* La carta NUNCA es ella misma un `<button>`, aunque abra la ficha.
       Si lo fuera, el botón de proteger del mercado y de la colección —que va
       dentro de un `<form>` flotando sobre la carta— quedaría anidado dentro
       de otro botón: HTML inválido, y en la práctica el navegador reordena el
       marcado y el formulario deja de enviarse.
       En su lugar, la ficha se abre con una capa transparente por encima del
       arte (`.carta-hitbox`), hermana de las acciones. Cada control queda por
       su cuenta, accesible y en su propio orden de tabulación. */
    /* EL PIE VA FUERA DE LA CARTA (solo en modo arte).
       El borde de rareza es `padding + background` sobre `.carta`, así que
       todo lo que cuelgue de ella queda DENTRO del recuadro de color. Con el
       pie dentro, el mercado dibujaba un marco alrededor de "Vende Fulano" y
       del botón: parecía que esa información formaba parte de la carta, o
       peor, que era otra cosa seleccionable. Y no se podía arreglar moviendo
       el borde a `.carta-marco`: la SRF y la legendaria declaran el suyo
       —animado— sobre `.carta`, y habrían quedado con dos bordes.
       Así que el pie sale de la carta y los dos viven dentro de un bloque. */
    $pieFuera = $esArte && $pie !== '';
    if ($pieFuera) { echo '<div class="carta-bloque">'; }

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

      <?= render_serie($numeroSerie, $cupoNumerado) ?>

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

      <?php if ($esArte): ?>

        <?php
        /* ⚠️ LA PLANTILLA TAMBIÉN VA EN MODO ARTE.
           La primera versión de este modo pintaba la foto a pelo y se cargaba
           el marco de Común→Épico: en colección y álbum salía la fotografía
           del jugador sin el marco de la comunidad ni su placa de nombre.
           Era una regresión, no una decisión — el modo arte quita los DATOS de
           alrededor (rareza, equipo, estadísticas), no el arte de la carta, y
           la plantilla ES parte del arte. Se usa exactamente la misma
           geometría medida que en el modo ficha. */
        $usaMarco = carta_usa_marco($idRareza, $imagen);
        ?>
        <div class="carta-marco">
          <div class="carta-placa<?= $usaMarco ? ' carta-placa--marco' : '' ?>">
            <?php if ($usaMarco): ?>
              <div class="carta-foto-hueco">
                <?php if ($imagen !== ''): ?>
                  <img class="carta-arte"
                       src="<?= htmlspecialchars($imagen) ?>"
                       alt="Ilustración de <?= htmlspecialchars($nombre) ?>"
                       <?= $lazy ? 'loading="lazy" decoding="async"' : '' ?>>
                <?php else: ?>
                  <span class="carta-placa-vacia" aria-hidden="true"><i class="ph ph-image-square"></i></span>
                  <span class="sr-only">Esta carta todavía no tiene ilustración</span>
                <?php endif; ?>
              </div>
              <?php /* El nombre sobre el rectángulo blanco de la plantilla sigue
                       siendo texto real: lo lee un lector de pantalla y lo
                       encuentra el buscador del álbum. */ ?>
              <h3 class="carta-nombre-marco"><span><?= htmlspecialchars($nombre) ?></span></h3>
            <?php else: ?>
              <?php if ($imagen !== ''): ?>
                <img class="carta-arte"
                     src="<?= htmlspecialchars($imagen) ?>"
                     alt="Ilustración de <?= htmlspecialchars($nombre) ?>"
                     <?= $lazy ? 'loading="lazy" decoding="async"' : '' ?>>
              <?php else: ?>
                <span class="carta-placa-vacia" aria-hidden="true"><i class="ph ph-image-square"></i></span>
                <span class="sr-only">Esta carta todavía no tiene ilustración</span>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <?php if ($ficha): ?>
            <?php /* La capa que abre la ficha. Va DENTRO del marco a propósito:
                     así cubre exactamente el arte y ni el pie de foto ni el pie
                     de la carta (donde el mercado pone "Comprar") quedan debajo.
                     Su z-index la deja por debajo de insignias y acciones, que
                     siguen siendo pulsables.
                     Su texto no se ve —el arte ya lo dice a quien puede verlo—,
                     pero sin él un lector de pantalla anuncia "botón" a secas y
                     la rejilla entera es indistinguible. */ ?>
            <button type="button" class="carta-hitbox" data-ficha-carta>
              <span class="sr-only">
                <?= htmlspecialchars($nombre) ?>, <?= htmlspecialchars($rareza) ?>.
                Ver ficha completa.
              </span>
            </button>
          <?php endif; ?>
        </div>

        <?php if ($leyenda !== null && $leyenda !== false): ?>
          <span class="carta-leyenda"><?= $leyenda ?></span>
        <?php endif; ?>

        <?php /* el pie se pinta al cerrar, ya fuera de la carta */ ?>

      <?php else: ?>

      <div class="carta-marco">

        <div class="carta-head">
          <?= render_rareza($idRareza, $rareza) ?>
          <?php if (($cantidad !== null && $cantidad > 1) || $tieneAfinidad): ?>
            <span class="carta-head-derecha">
              <?php if ($cantidad !== null && $cantidad > 1): ?>
                <span class="carta-cantidad" title="Tienes <?= (int) $cantidad ?> copias">×<?= (int) $cantidad ?></span>
              <?php endif; ?>
              <?php if ($tieneAfinidad): ?>
                <span class="carta-afinidad" title="Afinidad: <?= htmlspecialchars($afinidad) ?>">
                  <img src="<?= htmlspecialchars($afinidadImg) ?>" alt="Afinidad <?= htmlspecialchars($afinidad) ?>">
                </span>
              <?php endif; ?>
            </span>
          <?php endif; ?>
        </div>

        <?php
        // Marco con foto de comunidad (Común/Poco común/Raro/Épico): la
        // plantilla de branding/ trae su propio hueco cuadrado para la foto y
        // su propio rectángulo blanco para el nombre — el nombre sigue siendo
        // texto real (accesible, buscable, escalable), solo cambia DÓNDE se
        // pinta. Legendaria, SRF y Numerada se pintan sin marco MIENTRAS no
        // exista su plantilla; en cuanto esté, la usan solas (marco_de_rareza).
        $usaMarco = carta_usa_marco($idRareza, $imagen);
        ?>
        <div class="carta-placa<?= $usaMarco ? ' carta-placa--marco' : '' ?>">
          <?php if ($usaMarco): ?>
            <div class="carta-foto-hueco">
              <?php if ($imagen !== ''): ?>
                <img class="carta-arte"
                     src="<?= htmlspecialchars($imagen) ?>"
                     alt="Ilustración de <?= htmlspecialchars($nombre) ?>"
                     <?= $lazy ? 'loading="lazy" decoding="async"' : '' ?>>
              <?php else: ?>
                <span class="carta-placa-vacia" aria-hidden="true"><i class="ph ph-image-square"></i></span>
                <span class="sr-only">Esta carta todavía no tiene ilustración</span>
              <?php endif; ?>
            </div>
            <?php /* El nombre va envuelto en un <span> a propósito. El <h3> es
                     un contenedor flex (centra el texto en el rectángulo blanco
                     de la plantilla) y `text-overflow: ellipsis` NO se aplica al
                     texto suelto de un contenedor flex: es una caja anónima, no
                     un elemento, así que no puede recortar. Resultado: los
                     nombres largos se desbordaban por los dos lados en vez de
                     acabar en puntos suspensivos. Con el span, el recorte tiene
                     dónde agarrarse. */ ?>
            <h3 class="carta-nombre-marco"><span><?= htmlspecialchars($nombre) ?></span></h3>
          <?php else: ?>
            <?php if ($imagen !== ''): ?>
              <img class="carta-arte"
                   src="<?= htmlspecialchars($imagen) ?>"
                   alt="Ilustración de <?= htmlspecialchars($nombre) ?>"
                   <?= $lazy ? 'loading="lazy" decoding="async"' : '' ?>>
            <?php else: ?>
              <span class="carta-placa-vacia" aria-hidden="true"><i class="ph ph-image-square"></i></span>
              <span class="sr-only">Esta carta todavía no tiene ilustración</span>
            <?php endif; ?>
          <?php endif; ?>

          <?php if ($posicion !== ''): ?>
            <span class="carta-pos"><?= htmlspecialchars($posicion) ?></span>
          <?php endif; ?>
        </div>

        <div class="carta-cuerpo">
          <?php if (!$usaMarco): ?>
            <h3 class="carta-nombre"><?= htmlspecialchars($nombre) ?></h3>
          <?php endif; ?>
          <p class="carta-meta">
            <span class="carta-equipo"><?= htmlspecialchars($equipo) ?></span>
          </p>

          <?php if ($rasgo !== ''): ?>
            <p class="carta-rasgo" title="Compo de configuración: <?= htmlspecialchars($rasgo) ?>">
              <i class="ph ph-hexagon" aria-hidden="true"></i> <?= htmlspecialchars($rasgo) ?>
            </p>
          <?php endif; ?>

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

      <?php endif; /* $esArte */ ?>
    </<?= $etiqueta ?>>
    <?php if ($pieFuera): ?>
      <div class="carta-pie carta-pie--arte"><?= $pie ?></div>
    </div>
    <?php endif; ?>
    <?php
}
