<?php
/**
 * PLANTILLA — tus fichas y las de toda la liga, en una sola pantalla.
 *
 * Funde `coleccion.php` y `album.php`, que eran la MISMA pantalla dos veces:
 * misma rejilla, mismo componente `render_carta()`, mismo panel de filtros y
 * las dos en el mismo grupo del menú. La diferencia real era un filtro —las
 * tuyas frente a todas—, y nada en la interfaz decía cuál abrir. Ahora es un
 * conmutador de dos posiciones.
 *
 *   ?ver=mias   (por defecto con sesión)  rejilla plana, copias agrupadas ×N
 *   ?ver=todas  (por defecto sin sesión)  agrupada por expansión, con progreso
 *
 * SIN SESIÓN la pantalla sigue siendo el escaparate público que era el álbum:
 * se fuerza `ver=todas` y no se pide login.
 *
 * `descartar.php` NO se ha fusionado aquí, al revés de lo que decía el plan.
 * Ver la nota al final de este comentario en MASTER.md §6.3: es una acción
 * irreversible en lote, con datos propios (copias sobrantes, precio por unidad,
 * tope por tanda) y un contador de lo que vas a cobrar. Mezclarla con la
 * pantalla donde se navega y se filtra es cómo alguien vacía media colección
 * con un toque mal dado.
 */
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';
require_once __DIR__ . '/partials/plantilla_filtro.php';
require_once __DIR__ . '/partials/csrf.php';

$id_usuario = (int) ($_SESSION['id_usuario'] ?? 0);
$haySesion  = $id_usuario > 0;

/* ---------------------------------------------------------------------------
   PROTEGER / DESPROTEGER — se conserva tal cual venía de coleccion.php.
   Redirige después del POST para que recargar no repita la acción.
   --------------------------------------------------------------------------- */
/* Auditoría de seguridad: esta página desbloqueaba cartas por POST sin pasar
   por csrfValido() — la única (junto con duelo.php) de todo el proyecto.
   `bloqueada = 0` es justo la condición que usan publicarAnuncio(),
   copiasLibresDeCromo() y el selector de apuesta para decidir si una carta se
   puede vender, intercambiar o apostar: un CSRF que desproteja en masa la
   colección de la víctima es el paso previo a que la pierda. */
if ($haySesion && $_SERVER['REQUEST_METHOD'] === 'POST' && !csrfValido($_POST['csrf'] ?? null)) {
    header('Location: plantilla?error=csrf');
    exit;
}

if ($haySesion && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'toggle_bloqueo') {
        $db->alternarBloqueoCromo((int) $_POST['id_coleccion'], $id_usuario);
    } elseif ($_POST['accion'] === 'toggle_bloqueo_grupo') {
        $db->alternarBloqueoGrupoCromo((int) $_POST['id_cromo'], $id_usuario, (int) $_POST['estado_actual']);
    }
    $query = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: plantilla' . ($query !== '' ? '?' . $query : ''));
    exit;
}

/* Sin sesión solo existe el catálogo: quien llega desde la portada a mirar las
   cartas no tiene «las mías» que enseñar. */
$ver = ($_GET['ver'] ?? '') === 'todas' || !$haySesion ? 'todas' : 'mias';

/* ---------------------------------------------------------------------------
   FILTROS — los mismos nombres en los dos modos, para que cambiar de vista no
   pierda lo que llevas filtrado. `coleccion.php` los resolvía en el servidor y
   `album.php` en JavaScript sobre atributos `data-*`; se unifica en servidor,
   que además funciona sin JS y sobrevive a compartir la URL.
   --------------------------------------------------------------------------- */
$fNombre    = trim($_GET['q'] ?? '');
$fEquipo    = $_GET['id_equipo'] ?? '';
$fExpansion = $_GET['id_expansion'] ?? '';
$fRarezas   = array_filter(array_map('strval', (array) ($_GET['rareza'] ?? [])), 'strlen');
$fEstado    = $_GET['estado'] ?? '';   // solo en «mías»: protección
$fTengo     = $_GET['tengo'] ?? '';    // solo en «todas»: tengo / me falta

$hayFiltros = $fNombre !== '' || $fEquipo !== '' || $fExpansion !== ''
    || $fRarezas || $fEstado !== '' || $fTengo !== '';

$equipos     = $db->listarEquipos();
$expansiones = $db->listarExpansiones();
$rarezas     = [];
foreach ($db->listarRarezas() as $r) { $rarezas[$r['id_rareza']] = $r['nombre']; }

/* Progreso: la cifra que mueve a un coleccionista. Estaba enterrada al final
   de la cabecera vieja y aquí es lo primero que se lee. */
$totalCromos    = $db->contarCromosTotales($id_usuario);
$totalObtenidas = $haySesion ? $db->contarColeccionUsuario($id_usuario) : 0;
$pct            = $totalCromos > 0 ? (int) round($totalObtenidas / $totalCromos * 100) : 0;

/* Qué cartas tiene ya, para apagar las que le faltan en el modo «todas».
   Solo se usa en ese modo, así que en «mías» ni se pregunta. */
$poseidas = ($haySesion && $ver !== 'mias') ? $db->cromosPoseidos($id_usuario) : [];

/* Los cuatro nombres que juegan en dos equipos y por tanto llevan el equipo en
   su URL. Se pide UNA vez, no una por carta. */
$ambiguos = $ver === 'todas' && !$haySesion ? $db->nombresAmbiguos() : [];

$grupos = [];        // modo «mías»
$porExpansion = [];  // modo «todas»
$mostradas = 0;      // tarjetas en pantalla
$copias    = 0;      // copias que representan (solo en «mías»)

if ($ver === 'mias') {
    $cromos = $db->listarColeccionUsuario($id_usuario, [
        'nombre'       => $fNombre,
        'id_equipo'    => $fEquipo,
        'id_expansion' => $fExpansion,
        'rarezas'      => $fRarezas,
        'bloqueada'    => $fEstado === 'protegida' ? 1 : ($fEstado === 'libre' ? 0 : ''),
    ]);

    /* Copias repetidas del mismo cromo con el mismo estado de protección, en
       una sola carta con «×N»: con cientos de copias, pintar una tarjeta
       completa por copia es lo que hacía lenta esta pantalla. El orden ya viene
       por fecha de obtención, así que la primera copia de cada grupo es la
       representante. */
    $indice = [];
    foreach ($cromos as $c) {
        $clave = $c['id_cromo'] . '-' . $c['bloqueada'];
        if (!isset($indice[$clave])) { $indice[$clave] = ['fila' => $c, 'cantidad' => 0]; }
        $indice[$clave]['cantidad']++;
    }
    $grupos = array_values($indice);

    /* Se cuentan FICHAS (tarjetas en pantalla), no copias: el conteo va justo
       encima de la rejilla y decir «816» sobre 360 tarjetas se lee como un
       error de la página. Las copias se dicen aparte, que es otro dato. */
    $mostradas = count($grupos);
    $copias    = count($cromos);

} else {
    /* El filtrado vive en partials/plantilla_filtro.php: es la pieza que puede
       devolver cartas equivocadas sin fallar, así que está aparte y con prueba.
       Ver db/pruebas/probar_plantilla_filtro.php */
    foreach ($db->listarColeccionCompleta($id_usuario) as $exp) {
        $visibles = plantilla_filtrar($exp['cromos'], [
            'nombre'       => $fNombre,
            'id_equipo'    => $fEquipo,
            'id_expansion' => $fExpansion,
            'rarezas'      => $fRarezas,
            'tengo'        => $fTengo,
        ], $poseidas);
        if (!$visibles) continue;

        $conteo = 0;
        foreach ($exp['cromos'] as $cromo) {
            if (isset($poseidas[(int) $cromo['id_cromo']])) $conteo++;
        }
        $porExpansion[] = [
            'info'    => $exp['info'],
            'cromos'  => $visibles,
            'tengo'   => $conteo,
            'total'   => count($exp['cromos']),
        ];
        $mostradas += count($visibles);
    }
}

/**
 * Conserva los filtros al cambiar de vista o de página.
 *
 * SIN `.php`, y no es cosmético: esto no solo se usa como `href`, también es el
 * `action` del formulario de proteger/desproteger. Con `plantilla.php` el
 * `.htaccess` respondía 301 —quita el .php de todas las URL—, un 301 convierte
 * el POST en GET y tira el cuerpo, así que el candado NUNCA llegaba a
 * guardarse: la página se recargaba igual y parecía que no habías tocado nada.
 * Mismo fallo que tenía el botón de abrir cofre en `cadena.php`.
 */
function plantilla_url(array $cambios = []): string {
    $q = array_merge($_GET, $cambios);
    foreach ($q as $k => $v) { if ($v === '' || $v === null || $v === []) unset($q[$k]); }
    return 'plantilla' . ($q ? '?' . http_build_query($q) : '');
}

$accionForm = htmlspecialchars(plantilla_url());

$paginaTitulo = $ver === 'todas' ? 'Todas las fichas de la liga' : 'Tu plantilla';

/* La description iba dirigida a quien YA tiene cuenta —«y las que ya has
   conseguido»— y era la misma en los dos modos. Pero el catálogo es la página
   pública con más contenido del sitio: quien la ve en Google no ha entrado
   nunca. Cada modo dice ahora lo que de verdad enseña. */
$paginaDesc = $ver === 'todas'
    ? 'Las ' . $totalCromos . ' fichas del juego de cartas de la Superliga Frontier, con su equipo, su posición y su rareza. Consúltalas sin registrarte.'
    : 'Tus fichas de la Superliga Frontier: lo que llevas conseguido, agrupado por equipo y por rareza.';

$cssExtra     = ['assets/css/plantilla.css'];
require_once __DIR__ . '/partials/seo.php';
/* Sin sesión esta página no lleva ningún dato personal, así que puede
   cachearse unos minutos en vez de prohibirlo (ver seoCachePublica()). */
seoCachePublica();
include __DIR__ . '/partials/head.php';

/* Datos estructurados del catálogo. Solo en el modo «todas»: es el único que
   es un listado público. En «mías» la página depende de la sesión y no la ve
   ningún buscador.

   El ItemList lleva `numberOfItems` pero NO enumera las fichas una a una, a
   propósito: un ItemList sin URL por elemento es un listado que Google puede
   ignorar o marcar como incompleto, y hoy las fichas no tienen página propia.
   Cuando exista /carta/{slug} se amplía con `itemListElement` de verdad. */
if ($ver === 'todas'):
?>
<script type="application/ld+json">
<?= json_encode([
  '@context' => 'https://schema.org',
  '@type' => 'BreadcrumbList',
  'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio',  'item' => seoUrl()],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Fichas',  'item' => seoCanonical()],
  ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>
<script type="application/ld+json">
<?= json_encode([
  '@context'   => 'https://schema.org',
  '@type'      => 'CollectionPage',
  '@id'        => seoCanonical() . '#catalogo',
  'url'        => seoCanonical(),
  'name'       => $paginaTitulo . ' · Superliga Frontier TCG',
  'description'=> $paginaDesc,
  'inLanguage' => 'es',
  'isPartOf'   => ['@id' => seoUrl() . '#website'],
  'about'      => [
    '@type'         => 'ItemList',
    'name'          => 'Fichas de la Superliga Frontier',
    'numberOfItems' => (int) $totalCromos,
  ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>
<?php endif; ?>
<?php

$activePage = 'plantilla';
include __DIR__ . '/navbar.php';
?>

<main id="contenido" class="plantilla">

  <?php /* ===== CABECERA DE PROGRESO =====
           La cifra va primero y grande: es el motor del coleccionismo y en la
           pantalla vieja estaba al final de un bloque de metadatos. */ ?>
  <header class="pl-cabecera">
    <div class="rescoldo" aria-hidden="true"></div>
    <div class="wrap pl-cabecera-cuerpo">
      <p class="label sube">Colección</p>
      <h1 class="pl-titulo" data-revela="160"><?= $haySesion ? 'Tu plantilla' : 'Las fichas de la liga' ?></h1>

      <?php if ($haySesion): ?>
        <p class="pl-progreso-cifra">
          <span class="num" data-cifra="<?= $totalObtenidas ?>"><?= number_format($totalObtenidas, 0, ',', '.') ?></span><span
            class="pl-progreso-de">/ <?= number_format($totalCromos, 0, ',', '.') ?></span>
          <span class="pl-progreso-pct num"><?= $pct ?>&#37;</span>
        </p>
        <div class="barra-carril pl-barra"
             role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"
             aria-label="Fichas conseguidas">
          <i style="--parte:<?= $pct / 100 ?>"></i>
        </div>
      <?php else: ?>
        <p class="pl-intro">
          <span class="num"><?= number_format($totalCromos, 0, ',', '.') ?></span> fichas repartidas por
          <span class="num"><?= count($expansiones) ?></span> expansiones. Entra y empieza la tuya.
        </p>
      <?php endif; ?>
    </div>
  </header>

  <div class="wrap pl-cuerpo">

    <?php /* ===== CONMUTADOR =====
             El arreglo del problema: dos pantallas gemelas pasan a ser dos
             posiciones de un interruptor, y se ve de un vistazo en cuál estás. */ ?>
    <?php if ($haySesion): ?>
    <div class="pl-barra-superior">
      <div class="conmutador" role="group" aria-label="Qué fichas ver">
        <a class="conmutador-op<?= $ver === 'mias' ? ' es-activo' : '' ?>"
           href="<?= htmlspecialchars(plantilla_url(['ver' => 'mias'])) ?>"
           <?= $ver === 'mias' ? 'aria-current="true"' : '' ?>>
          Mías
        </a>
        <a class="conmutador-op<?= $ver === 'todas' ? ' es-activo' : '' ?>"
           href="<?= htmlspecialchars(plantilla_url(['ver' => 'todas'])) ?>"
           <?= $ver === 'todas' ? 'aria-current="true"' : '' ?>>
          Todas
        </a>
      </div>

      <button class="pl-filtrar" type="button"
              aria-expanded="false" aria-controls="hoja-filtros" data-abre-hoja="hoja-filtros">
        <i class="ph ph-funnel" aria-hidden="true"></i>
        Filtrar
        <?php if ($hayFiltros): ?><span class="pl-filtrar-punto" aria-hidden="true"></span><?php endif; ?>
        <span class="sr-only"><?= $hayFiltros ? '(hay filtros aplicados)' : '' ?></span>
      </button>

      <p class="pl-conteo" role="status" aria-live="polite">
        <span class="num"><?= number_format($mostradas, 0, ',', '.') ?></span>
        <?= $mostradas === 1 ? 'ficha' : 'fichas' ?><?php if ($copias > $mostradas): ?>
        <span class="pl-conteo-copias">·
          <span class="num"><?= number_format($copias, 0, ',', '.') ?></span> copias</span>
        <?php endif; ?>
      </p>

      <a class="pl-cortar" href="descartar">
        <i class="ph ph-recycle" aria-hidden="true"></i>
        Descartar repetidas
      </a>
    </div>
    <?php else: ?>
    <div class="pl-barra-superior">
      <button class="pl-filtrar" type="button"
              aria-expanded="false" aria-controls="hoja-filtros" data-abre-hoja="hoja-filtros">
        <i class="ph ph-funnel" aria-hidden="true"></i> Filtrar
        <?php if ($hayFiltros): ?><span class="pl-filtrar-punto" aria-hidden="true"></span><?php endif; ?>
      </button>
      <p class="pl-conteo"><span class="num"><?= number_format($mostradas, 0, ',', '.') ?></span> fichas</p>
      <a class="btn btn-primary btn-sm" href="login">Empieza la tuya</a>
    </div>
    <?php endif; ?>

    <?php /* ===== REJILLA ===== */ ?>
    <?php if ($ver === 'mias'): ?>

      <?php if (!$grupos): ?>
        <div class="vacio">
          <span class="vacio-ico"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></span>
          <?php if ($hayFiltros): ?>
            <h3>Ninguna ficha con esos filtros</h3>
            <p>Prueba a quitar alguna rareza o a buscar por otro nombre.</p>
            <a class="btn btn-ghost" href="<?= htmlspecialchars(plantilla_url(['q' => '', 'id_equipo' => '', 'id_expansion' => '', 'rareza' => '', 'estado' => '', 'tengo' => ''])) ?>">Quitar los filtros</a>
          <?php else: ?>
            <h3>Tu plantilla está vacía</h3>
            <p>Todavía no tienes ninguna ficha. El primer sobre lo pone la casa.</p>
            <a class="btn btn-primary" href="sobres">Abrir el primero</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="pl-rejilla escalona">
          <?php foreach ($grupos as $g): ?>
            <?php
            $c = $g['fila'];
            $cantidad  = $g['cantidad'];
            $protegida = (bool) $c['bloqueada'];

            $campos = $cantidad > 1
                ? '<input type="hidden" name="accion" value="toggle_bloqueo_grupo">'
                  . '<input type="hidden" name="id_cromo" value="' . (int) $c['id_cromo'] . '">'
                  . '<input type="hidden" name="estado_actual" value="' . (int) $protegida . '">'
                : '<input type="hidden" name="accion" value="toggle_bloqueo">'
                  . '<input type="hidden" name="id_coleccion" value="' . (int) $c['id_coleccion'] . '">';

            $rotulo = ($protegida ? 'Quitar protección de ' : 'Proteger ')
                . ($cantidad > 1 ? 'las ' . $cantidad . ' copias de ' : '')
                . htmlspecialchars($c['nombre']);

            $accion = '<form method="POST" action="' . $accionForm . '">' . csrfCampo() . $campos
                . '<button type="submit" class="carta-accion-flotante' . ($protegida ? ' esta-activa' : '') . '">'
                . '<i class="ph ' . ($protegida ? 'ph-lock-simple' : 'ph-lock-simple-open') . '" aria-hidden="true"></i>'
                . '<span class="sr-only">' . $rotulo . '</span>'
                . '</button></form>';

            $esJugador = in_array($c['posicion'], Tcg::POSICIONES_JUGABLES, true);
            /* Las primeras cartas están sobre el pliegue: cargarlas en diferido
               retrasaba justo la imagen con la que se mide el LCP. Ver la opción
               `prioridad` en components/carta.php. */
            $iCarta = isset($iCarta) ? $iCarta + 1 : 0;
            render_carta($c, [
                'prioridad'=> $iCarta === 0,
                'lazy'     => $iCarta >= 4,
                'modo'     => 'arte',
                'ficha'    => true,
                'acciones' => $accion,
                'cantidad' => $cantidad,
                'datos'    => ['acciones' => $esJugador ? json_encode([
                    ['texto' => 'Añadir a una alineación', 'href' => 'mazos'],
                    ['texto' => 'Ver en el mercado', 'href' => 'mercado'],
                ], JSON_UNESCAPED_UNICODE) : '[]'],
            ]);
            ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php else: ?>

      <?php if (!$porExpansion): ?>
        <div class="vacio">
          <span class="vacio-ico"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></span>
          <h3>Ninguna ficha con esos filtros</h3>
          <p>Prueba a quitar alguna rareza o a buscar por otro nombre.</p>
          <a class="btn btn-ghost" href="<?= htmlspecialchars(plantilla_url(['q' => '', 'id_equipo' => '', 'id_expansion' => '', 'rareza' => '', 'estado' => '', 'tengo' => ''])) ?>">Quitar los filtros</a>
        </div>
      <?php else: ?>
        <?php foreach ($porExpansion as $exp): ?>
          <?php $pctExp = $exp['total'] > 0 ? (int) round($exp['tengo'] / $exp['total'] * 100) : 0; ?>
          <section class="pl-expansion">
            <div class="pl-expansion-cab">
              <h2 class="pl-expansion-nombre"><?= htmlspecialchars($exp['info']['nombre']) ?></h2>
              <?php if ($haySesion): ?>
                <span class="pl-expansion-pct num"><?= $exp['tengo'] ?> / <?= $exp['total'] ?></span>
                <div class="barra-carril pl-expansion-barra"
                     role="progressbar" aria-valuenow="<?= $pctExp ?>" aria-valuemin="0" aria-valuemax="100"
                     aria-label="Fichas de <?= htmlspecialchars($exp['info']['nombre']) ?>">
                  <i style="--parte:<?= $pctExp / 100 ?>"></i>
                </div>
              <?php else: ?>
                <span class="pl-expansion-pct num"><?= $exp['total'] ?> fichas</span>
              <?php endif; ?>
            </div>

            <div class="pl-rejilla escalona">
              <?php foreach ($exp['cromos'] as $cromo): ?>
                <?php
                // Sin sesión es un catálogo: todo se enseña normal.
                $tiene = !$haySesion || isset($poseidas[(int) $cromo['id_cromo']]);
                $iCarta = isset($iCarta) ? $iCarta + 1 : 0;

                /* SIN SESIÓN cada carta es un ENLACE a su ficha; con sesión sigue
                   abriendo el modal de siempre.
                   Los dos no caben a la vez: `render_carta()` pinta un <a> cuando
                   hay `href` y el botón del modal quedaría dentro, que es HTML
                   inválido. Y es justo la visita sin sesión —la que llega de
                   Google— la que necesita el enlace: sin él las 460 fichas no las
                   encuentra nadie, porque este catálogo es el único sitio desde
                   el que cuelgan. */
                $enlaceFicha = $haySesion
                    ? null
                    : 'carta/' . slugFicha(
                        $cromo['nombre'],
                        isset($ambiguos[$cromo['nombre']]) ? ($cromo['equipo'] ?? null) : null
                      );

                render_carta($cromo, [
                    'prioridad'=> $iCarta === 0,
                    'lazy'     => $iCarta >= 4,
                    'href'     => $enlaceFicha,
                    'modo'    => 'arte',
                    /* Sólo uno de los dos: sin sesión hay `href` y debe ganar el
                       enlace (§407); con sesión no hay `href` y gana el modal.
                       `ficha' => $tiene` por sí solo dejaba el botón activo
                       también sin sesión (aquí $tiene es siempre true), así que
                       el clic abría el modal Y, al no frenar el evento, el
                       navegador seguía el enlace justo después: se veía el
                       modal un instante y acababa en la página de la carta. */
                    'ficha'   => $haySesion && $tiene,
                    'poseida' => $tiene,
                ]);
                ?>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
      <?php endif; ?>

    <?php endif; ?>
  </div>

  <?php /* ===== HOJA DE FILTROS =====
           En móvil sube desde abajo en vez de desplegarse en línea y empujar la
           rejilla media pantalla hacia abajo, que es lo que hacía el `details`
           de las pantallas viejas. En escritorio se queda como panel lateral. */ ?>
  <div class="hoja-velo" data-cierra-hoja hidden></div>
  <form class="hoja hoja--filtros" id="hoja-filtros" method="GET" action="plantilla"
        role="dialog" aria-modal="true" aria-labelledby="hoja-filtros-titulo" hidden>
    <div class="hoja-asa" aria-hidden="true"></div>
    <h2 class="hoja-titulo" id="hoja-filtros-titulo">Filtrar fichas</h2>

    <input type="hidden" name="ver" value="<?= htmlspecialchars($ver) ?>">

    <div class="campo">
      <label for="f-buscar">Buscar por nombre</label>
      <input type="search" name="q" id="f-buscar" value="<?= htmlspecialchars($fNombre) ?>"
             placeholder="Ej. Mark Evans">
    </div>

    <div class="campo">
      <label for="f-equipo">Equipo</label>
      <select name="id_equipo" id="f-equipo">
        <option value="">Todos los equipos</option>
        <?php foreach ($equipos as $eq): ?>
          <option value="<?= $eq['id_equipo'] ?>" <?= (string) $fEquipo === (string) $eq['id_equipo'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($eq['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="campo">
      <label for="f-expansion">Expansión</label>
      <select name="id_expansion" id="f-expansion">
        <option value="">Todas las expansiones</option>
        <?php foreach ($expansiones as $ex): ?>
          <option value="<?= $ex['id_expansion'] ?>" <?= (string) $fExpansion === (string) $ex['id_expansion'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($ex['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <fieldset class="campo">
      <legend class="campo-label">Rareza</legend>
      <div class="pl-rarezas">
        <?php foreach ($rarezas as $idR => $nombreR): ?>
          <label class="casilla">
            <input type="checkbox" name="rareza[]" value="<?= $idR ?>"
                   <?= in_array((string) $idR, $fRarezas, true) ? 'checked' : '' ?>>
            <?= htmlspecialchars($nombreR) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <?php if ($haySesion && $ver === 'mias'): ?>
      <div class="campo">
        <label for="f-estado">Protección</label>
        <select name="estado" id="f-estado">
          <option value="">Todas</option>
          <option value="protegida" <?= $fEstado === 'protegida' ? 'selected' : '' ?>>Solo protegidas</option>
          <option value="libre"     <?= $fEstado === 'libre' ? 'selected' : '' ?>>Solo sin proteger</option>
        </select>
      </div>
    <?php elseif ($haySesion): ?>
      <?php /* Filtro NUEVO: «las que me faltan» es lo que de verdad se quiere
               mirar en un álbum, y en la pantalla vieja no existía. */ ?>
      <div class="campo">
        <label for="f-tengo">Qué enseñar</label>
        <select name="tengo" id="f-tengo">
          <option value="">Todas</option>
          <option value="falta" <?= $fTengo === 'falta' ? 'selected' : '' ?>>Solo las que me faltan</option>
          <option value="tengo" <?= $fTengo === 'tengo' ? 'selected' : '' ?>>Solo las que ya tengo</option>
        </select>
      </div>
    <?php endif; ?>

    <div class="hoja-acciones">
      <button type="submit" class="btn btn-primary btn-bloque">Ver resultados</button>
      <?php if ($hayFiltros): ?>
        <a class="btn btn-plano btn-bloque" href="plantilla?ver=<?= htmlspecialchars($ver) ?>">Quitar los filtros</a>
      <?php endif; ?>
      <button class="hoja-cerrar" type="button" data-cierra-hoja>Cerrar</button>
    </div>
  </form>

</main>

<?php include __DIR__ . '/partials/ficha_carta.php'; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>

</body>
</html>
