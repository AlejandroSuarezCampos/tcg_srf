<?php
/**
 * FICHA PÚBLICA DE UNA CARTA — /carta/{slug}
 *
 * Las 470 cartas del juego vivían dentro de una sola URL de 657 KB. Eran 460
 * páginas potenciales convertidas en cero, y con ellas la única cola larga que
 * este sitio puede ganar de verdad: el nombre de cada creador de la liga.
 * La auditoría lo midió como el punto de mayor retorno de todo el proyecto, y
 * también el más desaprovechado — quien busca su propia carta es justo quien
 * más ganas tiene de compartir el enlace, y no tenía ninguno.
 *
 * UNA PÁGINA POR JUGADOR, NO POR CARTA. `Tom Skipper` existe en Legendario y en
 * Épico: es la misma persona con dos cartas, y dos páginas casi idénticas serían
 * contenido duplicado. Las versiones se enseñan dentro de la misma ficha.
 *
 * ⚠️ LO QUE HACE QUE ESTO NO SEA CONTENIDO DELGADO.
 *    Solo 10 de las 470 cartas tienen descripción escrita. Publicar 460 páginas
 *    con «nombre, equipo, posición y tres números» es exactamente el patrón que
 *    Google penaliza como contenido generado a escala.
 *    Por eso cada ficha COMPARA: sus estadísticas contra la media real de su
 *    posición, su rareza contra la probabilidad real de que salga, y enlaza a
 *    sus compañeros de equipo. Todo sale de la base de datos, nada es relleno, y
 *    dos fichas distintas dicen cosas distintas.
 *    Si algún día se quita esa comparación, esto vuelve a ser 460 páginas
 *    iguales y más vale no tenerlas.
 *
 * No mira la sesión en ningún momento: la ficha es igual para todo el mundo, que
 * es lo que permite cachearla.
 */
session_start();

require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';
require_once __DIR__ . '/partials/seo.php';

/* ⚠️ ESTA PÁGINA CUELGA DE /carta/, UN NIVEL POR DEBAJO DE LA RAÍZ.
   Todo el sitio emite rutas relativas ('assets/css/…'), que desde
   /carta/tom-skipper resuelven a /carta/assets/css/… y no existen: la página
   se servía sin una sola hoja de estilo, sin JavaScript y sin imágenes.
   `$base` es el mecanismo que el proyecto ya tiene para esto —lo usa /panel/—
   y lo leen head.php, navbar.php y footer.php. */
$base = '../';

/* El `.htaccess` deja pasar el segmento entero y aquí se normaliza: así
   /carta/Tom-Skipper y /carta/tom-skipper no son dos páginas distintas. */
$pedido = trim((string) ($_GET['slug'] ?? ''));
$slug   = strtolower($pedido);

/* Se busca comparando slugs generados en vez de guardar una columna `slug`: son
   460 filas y 19 ms, y así no hay un segundo sitio donde el nombre y su URL
   puedan desincronizarse cuando alguien renombre una carta desde el panel. */
$ficha = null;
$todas = $db->fichasPublicas();
foreach ($todas as $f) {
    if (slugFicha($f['nombre'], $f['ambiguo'] ? $f['equipo'] : null) === $slug) { $ficha = $f; break; }
}

/* Si la ficha existe pero la URL no venía en su forma canónica —mayúsculas, una
   barra final—, se redirige en vez de servir la misma página en dos direcciones.
   Es 301 porque la forma buena no va a cambiar. */
if ($ficha) {
    $canonico = slugFicha($ficha['nombre'], $ficha['ambiguo'] ? $ficha['equipo'] : null);
    if ($pedido !== $canonico) {
        header('Location: ' . seoUrl('carta/' . $canonico), true, 301);
        exit;
    }
}

if (!$ficha) {
    http_response_code(404);
    $paginaTitulo = 'Esa ficha no existe';
    $paginaDesc   = 'No hay ninguna carta con esa dirección en el catálogo de la Superliga Frontier.';
    include __DIR__ . '/partials/head.php';
    $activePage = '';
    include __DIR__ . '/navbar.php';
    ?>
    <main id="contenido" class="seccion wrap">
      <div class="vacio">
        <span class="vacio-ico"><i class="ph ph-identification-card" aria-hidden="true"></i></span>
        <h1 class="t-h3">Esa ficha no existe</h1>
        <p>Puede que la carta se haya retirado, o que el enlace esté mal escrito.</p>
        <a class="btn btn-primary" href="<?= $base ?>plantilla">Ver todas las fichas</a>
      </div>
    </main>
    <?php include __DIR__ . '/partials/footer.php'; ?>
    </body></html>
    <?php
    exit;
}

/**
 * Reapunta las imágenes de una carta al nivel de esta página.
 *
 * La base de datos guarda las rutas como `./assets/img/…`, que es correcto para
 * las pantallas que cuelgan de la raíz. Desde `/carta/tom-skipper` ese `./`
 * significa `/carta/`, y la ilustración no existe ahí: las fichas salían con el
 * hueco de la carta vacío.
 *
 * Se corrige aquí, al entregar la fila, y no dentro de `render_carta()`: ese
 * componente lo usan diez pantallas que están todas en la raíz, y meterle una
 * noción de profundidad por una sola sería cambiar lo que funciona.
 */
function fichaRutas(array $fila, string $base): array
{
    foreach (['imagen', 'afinidad_imagen'] as $campo) {
        if (!empty($fila[$campo])) { $fila[$campo] = $base . ltrim($fila[$campo], './'); }
    }
    return $fila;
}

$medias      = $db->mediasPorPosicion();
$companeros  = $db->companerosDeEquipo((int) $ficha['id_equipo'], $ficha['nombre']);
$totalEquipo = $db->contarCartasEquipo((int) $ficha['id_equipo']);

$nombre   = $ficha['nombre'];
$equipo   = $ficha['equipo'];
$versiones = $ficha['versiones'];
$mejor    = $versiones[0];                      // la de más rareza: manda en el título y el arte

$ETIQUETA_POS = [
    'POR' => 'portero',       'DF' => 'defensa',    'MC' => 'centrocampista',
    'DC'  => 'delantero',     'ENT' => 'entrenador', 'GER' => 'gerente',
    'ESCUDO' => 'escudo',     'PRESIDENTE' => 'presidente',
];
$posNombre = $ETIQUETA_POS[$ficha['posicion']] ?? strtolower($ficha['posicion']);
$juega     = in_array($ficha['posicion'], ['POR', 'DF', 'MC', 'DC'], true);

/* --------------------------------------------------------------------------
   EL PÁRRAFO QUE HACE ÚTIL LA PÁGINA.
   Se arma con los datos que de verdad distinguen a esta carta de las otras 459,
   y solo se dice lo que es cierto: si no hay estadísticas, no se habla de
   estadísticas.
   -------------------------------------------------------------------------- */
$media = $medias[$ficha['posicion']] ?? null;

/** En qué destaca de verdad, comparado con la media de su posición. */
$fuertes = [];
if ($juega && $media) {
    foreach (['ataque' => 'ataque', 'defensa' => 'defensa', 'tecnica' => 'técnica'] as $col => $etq) {
        $dif = (int) $mejor[$col] - (int) $media[$col];
        if ($dif >= 8) { $fuertes[] = ['etq' => $etq, 'dif' => $dif, 'valor' => (int) $mejor[$col]]; }
    }
    usort($fuertes, fn($a, $b) => $b['dif'] <=> $a['dif']);
}

$prob = (float) $mejor['probabilidad'];
$unaDeCada = $prob > 0 ? (int) round(100 / $prob) : 0;

$paginaTitulo = $nombre . ' · ' . $mejor['rareza'];
/* «Carta de rareza legendario» no concuerda en género, y la rareza es una de
   las tres palabras que de verdad busca la gente. Se pone como etiqueta, que
   además es como se llama en el juego. */
$paginaDesc = $nombre . ', ' . $posNombre . ' del ' . $equipo . ' en el juego de cartas de la Superliga Frontier'
    . ($juega ? '. Ataque ' . (int) $mejor['ataque'] . ', defensa ' . (int) $mejor['defensa'] . ', técnica ' . (int) $mejor['tecnica'] : '')
    . '. Rareza: ' . $mejor['rareza'] . '.';

/* La imagen social de una ficha es SU PROPIA carta, no la genérica del sitio:
   quien comparte el enlace de un jugador quiere que salga ese jugador. */
if (($mejor['imagen'] ?? '') !== '') {
    $seoImagen = seoUrl(ltrim($mejor['imagen'], './'));
}

$cssExtra = ['assets/css/plantilla.css', 'assets/css/ficha.css'];
seoCachePublica(1800);
include __DIR__ . '/partials/head.php';
?>

<script type="application/ld+json">
<?= json_encode([
  '@context' => 'https://schema.org',
  '@type' => 'BreadcrumbList',
  'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => seoUrl()],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Fichas', 'item' => seoUrl('plantilla')],
    ['@type' => 'ListItem', 'position' => 3, 'name' => $nombre,  'item' => seoCanonical()],
  ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>
<?php
/* `CreativeWork` y no `Product`: una ficha coleccionable sin precio real ni
   transacción. Marcarla como producto con oferta sería marcado engañoso, que es
   la misma razón por la que la portada no lleva `Offer`. */
$propiedades = [
    ['@type' => 'PropertyValue', 'name' => 'Equipo',   'value' => $equipo],
    ['@type' => 'PropertyValue', 'name' => 'Posición', 'value' => $posNombre],
    ['@type' => 'PropertyValue', 'name' => 'Rareza',   'value' => $mejor['rareza']],
];
if ($juega) {
    $propiedades[] = ['@type' => 'PropertyValue', 'name' => 'Ataque',  'value' => (int) $mejor['ataque']];
    $propiedades[] = ['@type' => 'PropertyValue', 'name' => 'Defensa', 'value' => (int) $mejor['defensa']];
    $propiedades[] = ['@type' => 'PropertyValue', 'name' => 'Técnica', 'value' => (int) $mejor['tecnica']];
}
$ld = [
    '@context'    => 'https://schema.org',
    '@type'       => 'CreativeWork',
    '@id'         => seoCanonical() . '#ficha',
    'name'        => $nombre,
    'url'         => seoCanonical(),
    'description' => $paginaDesc,
    'inLanguage'  => 'es',
    'isPartOf'    => ['@id' => seoUrl() . '#website'],
    'about'       => ['@id' => seoUrl() . '#juego'],
    'additionalProperty' => $propiedades,
];
if (($mejor['imagen'] ?? '') !== '') { $ld['image'] = seoUrl(ltrim($mejor['imagen'], './')); }
?>
<script type="application/ld+json">
<?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>
<?php
$activePage = 'plantilla';
include __DIR__ . '/navbar.php';
?>

<main id="contenido" class="seccion wrap fi-pagina">

  <nav class="fi-migas" aria-label="Dónde estás">
    <a href="<?= $base ?>landing">Inicio</a>
    <span aria-hidden="true">·</span>
    <a href="<?= $base ?>plantilla">Fichas</a>
    <span aria-hidden="true">·</span>
    <span aria-current="page"><?= htmlspecialchars($nombre) ?></span>
  </nav>

  <div class="fi-cabecera">

    <div class="fi-arte">
      <?php render_carta(fichaRutas($mejor, $base), ['modo' => 'arte', 'prioridad' => true]); ?>
    </div>

    <div class="fi-datos">
      <p class="seccion-tag"><?= htmlspecialchars($mejor['rareza']) ?> · <?= htmlspecialchars($ficha['expansion']) ?></p>
      <h1><?= htmlspecialchars($nombre) ?></h1>

      <p class="fi-lede">
        <?= htmlspecialchars(ucfirst($posNombre)) ?> del
        <b><?= htmlspecialchars($equipo) ?></b><?php
          if (!empty($ficha['afinidad']) && strcasecmp($ficha['afinidad'], 'No-afi') !== 0):
        ?>, con afinidad <b><?= htmlspecialchars(strtolower($ficha['afinidad'])) ?></b><?php endif; ?>.
        <?php if (!empty($ficha['rasgo'])): ?>
          Activa la compo <b><?= htmlspecialchars($ficha['rasgo']) ?></b>.
        <?php endif; ?>
      </p>

      <?php /* Este párrafo es la razón de ser de la página: dice algo que no se
               puede leer en el catálogo y que es distinto en cada ficha. */ ?>
      <p class="fi-contexto">
        <?php /* «una carta legendario» no concuerda en género y la rareza cambia de
                 género según cuál sea (común, épica, legendario, SRF…). Se trata como
                 etiqueta, que además es como la llama el juego. */ ?>
        <?php if ($unaDeCada > 0): ?>
          Es de rareza <b><?= htmlspecialchars($mejor['rareza']) ?></b>:
          sale aproximadamente <b>una de cada <?= number_format($unaDeCada, 0, ',', '.') ?></b>
          cartas que se abren<?= $prob < 1 ? ', así que es de las difíciles de ver' : '' ?>.
        <?php endif; ?>
        <?php if ((int) $mejor['cupo_numerado'] > 0): ?>
          Está <b>numerada</b>: solo existen <?= (int) $mejor['cupo_numerado'] ?> copias en todo el juego.
        <?php endif; ?>
        <?php if ($fuertes): ?>
          <?php /* Se elige la mayor DIFERENCIA contra la media, no el número más
                   alto: que un delantero tenga 88 de ataque no dice nada —todos lo
                   tienen— y que tenga 15 puntos más de defensa que el resto de
                   delanteros sí. Por eso la frase habla de separarse de la media y
                   no de «destacar», que se leería como «su mejor estadística». */ ?>
          <?php $f0 = $fuertes[0]; ?>
          Lo que más lo separa del resto de <?= htmlspecialchars($posNombre) ?>s es su
          <b><?= htmlspecialchars($f0['etq']) ?></b>:
          <?= $f0['valor'] ?> puntos, <b><?= $f0['dif'] ?> por encima</b>
          de la media de su posición
          (<?= (int) $media[str_replace('é', 'e', $f0['etq'])] ?>).
        <?php elseif ($juega && $media): ?>
          Sus tres estadísticas se mueven cerca de la media de los
          <?= htmlspecialchars($posNombre) ?>s del juego.
        <?php endif; ?>
      </p>

      <?php if (trim((string) ($ficha['descripcion'] ?? '')) !== ''): ?>
        <p class="fi-descripcion"><?= nl2br(htmlspecialchars(trim($ficha['descripcion']))) ?></p>
      <?php endif; ?>

      <?php if ($juega && $media): ?>
        <?php /* Las barras comparan contra la media de la POSICIÓN, no contra
                 100: un portero con 61 de ataque no es «malo», es un portero. */ ?>
        <dl class="fi-stats">
          <?php foreach ([['ataque','Ataque','ata'], ['defensa','Defensa','def'], ['tecnica','Técnica','tec']] as [$col, $etq, $mod]):
            $valor = (int) $mejor[$col];
            $ref   = (int) $media[$col];
            $dif   = $valor - $ref;
          ?>
            <div class="fi-stat fi-stat--<?= $mod ?>">
              <dt><?= $etq ?></dt>
              <dd>
                <b class="mono"><?= $valor ?></b>
                <span class="fi-barra" aria-hidden="true">
                  <span style="width:<?= max(2, min(100, $valor)) ?>%"></span>
                  <i style="left:<?= max(2, min(100, $ref)) ?>%" title="Media de la posición: <?= $ref ?>"></i>
                </span>
                <span class="fi-dif <?= $dif >= 0 ? 'es-mas' : 'es-menos' ?>">
                  <?= $dif >= 0 ? '+' : '' ?><?= $dif ?> vs media
                </span>
              </dd>
            </div>
          <?php endforeach; ?>
        </dl>
        <p class="t-caption-sm t-dim">
          La media se calcula sobre los <?= (int) $media['cartas'] ?>
          <?= htmlspecialchars($posNombre) ?>s del catálogo público.
        </p>
      <?php endif; ?>

      <p class="fi-acciones">
        <a class="btn btn-primary" href="<?= $base ?>acceso?modo=crear">Consíguela en un sobre</a>
        <a class="btn btn-plano" href="<?= $base ?>plantilla">Ver todas las fichas</a>
      </p>
    </div>
  </div>

  <?php if (count($versiones) > 1): ?>
    <section class="panel">
      <div class="panel-head">
        <h2 class="panel-titulo">Sus <?= count($versiones) ?> versiones</h2>
      </div>
      <p class="t-body-sm t-dim">
        El mismo jugador existe en varias rarezas. Cuanto más rara, mejores estadísticas y más difícil de sacar.
      </p>
      <div class="carta-grid">
        <?php foreach ($versiones as $v): ?>
          <?php render_carta(fichaRutas($v, $base), ['modo' => 'arte']); ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($companeros): ?>
    <section class="panel">
      <div class="panel-head">
        <h2 class="panel-titulo">Más fichas del <?= htmlspecialchars($equipo) ?></h2>
      </div>
      <?php /* Estos enlaces son lo que convierte 460 páginas sueltas en un sitio:
               sin ellos cada ficha es un callejón sin salida y un rastreador que
               entre por una no encuentra ninguna otra. */ ?>
      <ul class="fi-companeros">
        <?php foreach ($companeros as $c): ?>
          <li>
            <a href="<?= $base ?>carta/<?= htmlspecialchars(slugFicha($c['nombre'])) ?>">
              <span class="fi-comp-nombre"><?= htmlspecialchars($c['nombre']) ?></span>
              <span class="fi-comp-meta"><?= htmlspecialchars($ETIQUETA_POS[$c['posicion']] ?? $c['posicion']) ?> · <?= htmlspecialchars($c['rareza']) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($totalEquipo > count($companeros) + 1): ?>
        <p>
          <a class="btn btn-plano btn-sm" href="<?= $base ?>plantilla?ver=todas&amp;id_equipo=<?= (int) $ficha['id_equipo'] ?>">
            Ver las <?= $totalEquipo ?> fichas del <?= htmlspecialchars($equipo) ?>
          </a>
        </p>
      <?php endif; ?>
    </section>
  <?php endif; ?>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
