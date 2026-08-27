<?php
/**
 * PROBAR_FICHAS_CARTA — que las 460 fichas públicas se puedan alcanzar.
 *
 *   C:\xampp\php\php.exe db/pruebas/probar_fichas_carta.php
 *
 * Solo lectura, contra la base de esta máquina. No monta `tcg_prueba` ni escribe
 * nada, así que no entra en `correr_todas.php`: se corre a mano al tocar
 * `carta.php`, `slugFicha()` o las consultas de fichas públicas.
 *
 * Qué vigila, y por qué justo esto:
 *
 * 1. **Que no haya dos fichas con el mismo slug.** Si dos cartas generan la
 *    misma URL, una de las dos es inalcanzable para siempre y nadie se entera:
 *    la página responde 200 y enseña la otra. El desambiguador por equipo cubre
 *    los cuatro nombres que hoy juegan en dos sitios; una carta nueva puede
 *    romperlo sin tocar una línea de código.
 *
 * 2. **Que el slug sea reversible.** `carta.php` busca comparando slugs
 *    generados: si `slugFicha()` produce algo que luego no encuentra su propia
 *    ficha, las 460 páginas devuelven 404 a la vez.
 *
 * 3. **Que ninguna quede vacía.** Una ficha sin equipo, sin posición o sin
 *    rareza sería una página sin nada que decir — el contenido delgado que estas
 *    páginas existen para evitar.
 *
 * 4. **Que el sitemap y la realidad coincidan.** Anunciar una URL que responde
 *    404 es un aviso en Search Console.
 */

require_once dirname(__DIR__) . '/conexion.php';
require_once dirname(__DIR__, 2) . '/partials/seo.php';

$fallos = 0;
function ok(string $t): void { echo "  OK    $t\n"; }
function ko(string $t): void { global $fallos; $fallos++; echo "  FALLA $t\n"; }

echo "PROBAR_FICHAS_CARTA\n", str_repeat('-', 72), "\n";

$fichas = $db->fichasPublicas();
count($fichas) > 0
    ? ok(count($fichas) . ' fichas públicas')
    : ko('no hay ninguna ficha pública: o falta contenido o el filtro está mal');

// --- 1. Slugs únicos ------------------------------------------------------
$porSlug = [];
foreach ($fichas as $f) {
    $slug = slugFicha($f['nombre'], $f['ambiguo'] ? $f['equipo'] : null);
    $porSlug[$slug][] = $f['nombre'] . ' (' . $f['equipo'] . ')';
}
$choques = array_filter($porSlug, fn($v) => count($v) > 1);
empty($choques)
    ? ok(count($porSlug) . ' slugs, todos distintos')
    : ko(count($choques) . ' slugs repetidos: ' . implode(' / ', array_map(
        fn($k, $v) => "$k → " . implode(' + ', $v),
        array_keys($choques), $choques
      )));

// --- 2. Ningún slug vacío, y todos con forma de URL -----------------------
$feos = array_filter(array_keys($porSlug), fn($s) => $s === '' || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $s));
empty($feos)
    ? ok('todos los slugs tienen forma de URL')
    : ko('slugs con forma rara: ' . implode(', ', array_map(fn($s) => "«$s»", array_slice($feos, 0, 5))));

// --- 3. El slug encuentra su propia ficha ---------------------------------
/* Es la misma búsqueda que hace carta.php. Si esto falla, todas las fichas
   devuelven 404 aunque los datos estén bien. */
$perdidas = [];
foreach ($fichas as $f) {
    $slug = slugFicha($f['nombre'], $f['ambiguo'] ? $f['equipo'] : null);
    $encontrada = null;
    foreach ($fichas as $g) {
        if (slugFicha($g['nombre'], $g['ambiguo'] ? $g['equipo'] : null) === $slug) { $encontrada = $g; break; }
    }
    if (!$encontrada || $encontrada['nombre'] !== $f['nombre']) { $perdidas[] = $f['nombre']; }
}
empty($perdidas)
    ? ok('cada slug encuentra su ficha')
    : ko(count($perdidas) . ' fichas no se encuentran por su propio slug: ' . implode(', ', array_slice($perdidas, 0, 5)));

// --- 4. Ninguna ficha vacía ----------------------------------------------
$incompletas = [];
foreach ($fichas as $f) {
    foreach (['nombre', 'equipo', 'posicion', 'rareza'] as $campo) {
        if (trim((string) ($f[$campo] ?? '')) === '') { $incompletas[] = ($f['nombre'] ?: '?') . " (sin $campo)"; break; }
    }
    if (empty($f['versiones'])) { $incompletas[] = $f['nombre'] . ' (sin versiones)'; }
}
empty($incompletas)
    ? ok('todas tienen equipo, posición y rareza')
    : ko(count($incompletas) . ' fichas incompletas: ' . implode(', ', array_slice($incompletas, 0, 5)));

// --- 5. Nada privado se ha colado ----------------------------------------
$r = new ReflectionClass($db); $p = $r->getProperty('pdo'); $p->setAccessible(true); $pdo = $p->getValue($db);

/* Se comprueba por ID, que es lo único que no miente: qué cromos concretos han
   acabado dentro de una ficha pública, y si alguno de ellos es de cadena o de
   una expansión retirada. Comparar por nombre no valdría — hay nombres que
   existen en los dos grupos, y ahí publicar el público es correcto. */
$privados = array_map('intval', $pdo->query("
    SELECT c.id_cromo FROM cromos c
    INNER JOIN expansiones e ON e.id_expansion = c.id_expansion
    WHERE c.solo_cadena = 1 OR e.activo = 0
")->fetchAll(PDO::FETCH_COLUMN));

$publicados = [];
foreach ($fichas as $f) {
    foreach ($f['versiones'] as $v) { $publicados[] = (int) $v['id_cromo']; }
}

$coladas = array_intersect($publicados, $privados);
empty($coladas)
    ? ok(count($privados) . ' cartas de cadena o de expansión retirada, ninguna con ficha pública')
    : ko(count($coladas) . ' cartas privadas se han colado en fichas públicas (id: '
        . implode(', ', array_slice($coladas, 0, 5)) . ')');

// --- 6. El sitemap coincide con las fichas -------------------------------
$sitemap = dirname(__DIR__, 2) . '/sitemap.xml';
if (!is_file($sitemap)) {
    ko('no existe sitemap.xml (córrelo con db/herramientas/generar_sitemap.php)');
} else {
    $xml = simplexml_load_file($sitemap);
    $enSitemap = [];
    foreach ($xml->url as $u) {
        if (preg_match('#/carta/([^/]+)$#', (string) $u->loc, $m)) { $enSitemap[$m[1]] = true; }
    }
    $inventadas = array_diff(array_keys($enSitemap), array_keys($porSlug));
    empty($inventadas)
        ? ok(count($enSitemap) . ' fichas anunciadas en el sitemap, todas existen')
        : ko(count($inventadas) . ' URL del sitemap no corresponden a ninguna ficha: ' . implode(', ', array_slice($inventadas, 0, 5)));

    /* Las que no tienen ilustración se dejan fuera A PROPÓSITO, así que aquí no
       se exige que estén todas: solo que lo que se anuncia sea real. */
    $conArte = count(array_filter($fichas, fn($f) => trim((string) ($f['imagen'] ?? '')) !== ''));
    count($enSitemap) === $conArte
        ? ok("coincide con las $conArte fichas con ilustración")
        : ko("el sitemap anuncia " . count($enSitemap) . " y hay $conArte con ilustración: regenéralo");
}

echo "\n";
if ($fallos) { echo "$fallos comprobación(es) en rojo.\n"; exit(1); }
echo "Todo en verde.\n";
