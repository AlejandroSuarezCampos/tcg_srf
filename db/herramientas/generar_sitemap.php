<?php
/**
 * GENERAR sitemap.xml.
 *
 *   C:\xampp\php\php.exe db/herramientas/generar_sitemap.php
 *
 * Se genera con un script en vez de escribirlo a mano porque el día que existan
 * las fichas de carta individuales (`/carta/{slug}`) serán ~470 URL, y eso ya no
 * se mantiene a mano. Con cuatro páginas es casi lo mismo, pero deja el sitio
 * hecho.
 *
 * SOLO ENTRAN URL PÚBLICAS Y CANÓNICAS. Nada que responda con una redirección al
 * login, nada con parámetros que no cambien el contenido, y nada que el
 * robots.txt esté bloqueando: un sitemap que anuncia páginas bloqueadas es un
 * aviso en Search Console, no una ayuda.
 *
 * Por eso `/plantilla` va SIN `?ver=todas`: son la misma página y la canónica es
 * la corta (ver seoCanonical() en partials/seo.php).
 */

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../../partials/seo.php';   // slugFicha()

const ORIGEN = 'https://tcgfrontier.es';

$raiz = dirname(__DIR__, 2);

/* `lastmod` sale de la fecha real del fichero que genera cada página: no tiene
   sentido inventarse una, y una fecha falsa que no cambia nunca es peor que no
   ponerla. */
$fecha = function (string $fichero) use ($raiz): string {
    $ruta = $raiz . '/' . $fichero;
    return date('Y-m-d', is_file($ruta) ? filemtime($ruta) : time());
};

$paginas = [
    ['/',               'weekly',  '1.0', 'landing.php'],
    ['/plantilla',      'weekly',  '0.9', 'plantilla.php'],
    ['/quienes-somos',  'monthly', '0.6', 'quienes-somos.php'],
    ['/como-se-juega',  'monthly', '0.8', 'como-se-juega.php'],
    ['/preguntas-frecuentes', 'monthly', '0.7', 'preguntas-frecuentes.php'],
    ['/legal',          'yearly',  '0.2', 'legal.php'],
    ['/privacidad',     'yearly',  '0.2', 'privacidad.php'],
];

/* ---------------------------------------------------------------------------
   LAS FICHAS DE CARTA.
   Son 460 de las 465 URL del sitemap, y ninguna está enlazada desde un menú:
   solo cuelgan del catálogo y unas de otras. Sin sitemap, encontrarlas todas
   depende de que el rastreador recorra una página de 657 KB entera.

   SE EXCLUYEN LAS QUE NO TIENEN ILUSTRACIÓN. Una ficha sin arte es la más débil
   que puede tener este sitio: le falta justo lo que la hace interesante. Siguen
   existiendo y respondiendo 200 —si alguien llega, la ve—, pero no se anuncian
   hasta que tengan imagen, momento en el que entran solas al regenerar.
   --------------------------------------------------------------------------- */
$fichas = array_values(array_filter(
    $db->fichasPublicas(),
    fn(array $f) => trim((string) ($f['imagen'] ?? '')) !== ''
));

$sinArte = count($db->fichasPublicas()) - count($fichas);

$xml = new SimpleXMLElement(
    '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>'
);

foreach ($paginas as [$ruta, $frecuencia, $prioridad, $fichero]) {
    $u = $xml->addChild('url');
    $u->addChild('loc', htmlspecialchars(ORIGEN . $ruta, ENT_XML1));
    $u->addChild('lastmod', $fecha($fichero));
    $u->addChild('changefreq', $frecuencia);
    $u->addChild('priority', $prioridad);
}

$fechaFichas = $fecha('carta.php');
foreach ($fichas as $f) {
    $slug = slugFicha($f['nombre'], $f['ambiguo'] ? $f['equipo'] : null);
    $u = $xml->addChild('url');
    $u->addChild('loc', htmlspecialchars(ORIGEN . '/carta/' . $slug, ENT_XML1));
    $u->addChild('lastmod', $fechaFichas);
    $u->addChild('changefreq', 'monthly');
    // Las raras primero: son las que la gente busca por su nombre.
    $u->addChild('priority', (int) $f['id_rareza'] >= 5 ? '0.7' : '0.5');
}

/* Sangrado, para que se pueda leer y revisar a ojo. */
$dom = new DOMDocument('1.0', 'UTF-8');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xml->asXML());

$destino = $raiz . '/sitemap.xml';
$dom->save($destino);

printf("Escrito %s\n  %d URL: %d páginas + %d fichas de carta (%.1f KB)\n",
    str_replace('\\', '/', realpath($destino)),
    count($paginas) + count($fichas), count($paginas), count($fichas),
    filesize($destino) / 1024);

if ($sinArte > 0) {
    printf("  %d fichas se quedan fuera por no tener ilustración; entran solas cuando la tengan.\n", $sinArte);
}

/* Comprobación honesta: que ninguna de las URL anunciadas esté bloqueada por el
   propio robots.txt. Es el error más fácil de cometer y el que Search Console
   marca en rojo. */
$robots = @file_get_contents($raiz . '/robots.txt');
if ($robots !== false) {
    preg_match_all('/^Disallow:\s*(\S+)/mi', $robots, $m);
    $bloqueadas = array_unique($m[1]);
    $choques = [];
    foreach ($paginas as [$ruta]) {
        foreach ($bloqueadas as $b) {
            if ($b !== '/' && $ruta !== '/' && strpos($ruta, $b) === 0) { $choques[] = "$ruta (por Disallow: $b)"; }
        }
    }
    echo $choques
        ? "  ⚠️  URL anunciadas que robots.txt bloquea:\n     - " . implode("\n     - ", $choques) . "\n"
        : "  Ninguna choca con robots.txt.\n";
}
