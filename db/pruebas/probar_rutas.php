<?php
/**
 * PROBAR_RUTAS — que ningún fichero pida algo que no está donde dice.
 *
 * No toca la base de datos ni levanta Apache: es lectura de ficheros, así que
 * corre sola y en un segundo.
 *
 *   C:\xampp\php\php.exe db/pruebas/probar_rutas.php
 *
 * Comprueba las dos cosas que se rompieron a la vez y que ni `php -l` ni la
 * suite de partido ven, porque las dos son rutas y no sintaxis:
 *
 * 1. **`require_once __DIR__ . '/...'` que no resuelve.** Es como estaban
 *    `assets/ajax/monedas.php` (subía un nivel de menos: `assets/db/` en vez
 *    de `db/`) y las seis copias de `panel/*.php` que se habían quedado en la
 *    raíz. Las siete daban `Fatal error` en cuanto se pedían, y en un
 *    proyecto sin autoload no hay nada más que lo avise.
 *
 * 2. **`fetch('...')` a un endpoint que no existe.** `scriptsAsync.js` pedía
 *    `ajax/monedas`, que no cuelga de la raíz del proyecto sino de `assets/`,
 *    así que el saldo del navbar no se refrescaba nunca y el fallo se quedaba
 *    en la consola del navegador.
 *
 *    Las URL van SIN `.php` a propósito (lo quita el mod_rewrite del
 *    `.htaccess`), así que aquí se prueban las dos formas.
 */

$raiz = dirname(__DIR__, 2);
$fallos = [];
$comprobadas = 0;

/** Ficheros del proyecto, saltándose lo que no es código nuestro. */
function ficheros(string $raiz, string $ext): array {
    $salida = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        $p = str_replace('\\', '/', $f->getPathname());
        // node_modules es de terceros; _archivo son copias apartadas a
        // propósito, y varias están rotas justamente por eso.
        if (preg_match('#/(node_modules|_archivo)/#', $p)) { continue; }
        if (!preg_match('/\.' . $ext . '$/i', $p)) { continue; }
        $salida[] = $p;
    }
    return $salida;
}

// ---------------------------------------------------------------------------
// 1. require/include con __DIR__
// ---------------------------------------------------------------------------
$raizN = str_replace('\\', '/', $raiz);

/**
 * El código de un fichero, sin comentarios.
 *
 * Hace falta: varios docblocks del proyecto CITAN un require de ejemplo
 * ("se incluye con require __DIR__ . '/components/carta.php'") y buscarlo con
 * una expresión regular sobre el texto crudo los da por rutas de verdad.
 */
function soloCodigo(string $ruta): string {
    $salida = '';
    foreach (token_get_all(file_get_contents($ruta)) as $t) {
        if (is_array($t)) {
            if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) { continue; }
            $salida .= $t[1];
        } else {
            $salida .= $t;
        }
    }
    return $salida;
}

foreach (ficheros($raizN, 'php') as $php) {
    $texto = soloCodigo($php);
    preg_match_all(
        '#(?:require|include)(?:_once)?\s*(?:\(\s*)?__DIR__\s*\.\s*[\'"]([^\'"]+)[\'"]#',
        $texto,
        $m
    );
    foreach ($m[1] as $trozo) {
        $comprobadas++;
        $destino = dirname($php) . $trozo;
        if (!is_file($destino)) {
            $rel = ltrim(substr($php, strlen($raizN)), '/');
            $fallos[] = "$rel  ->  incluye '$trozo'  (no existe)";
        }
    }
}

// ---------------------------------------------------------------------------
// 2. fetch() a endpoints del propio sitio
// ---------------------------------------------------------------------------
foreach (ficheros($raizN, 'js') as $js) {
    // GSAP y cualquier otra librería vendorizada no son nuestras.
    if (strpos($js, '/vendor/') !== false) { continue; }

    $texto = file_get_contents($js);
    preg_match_all('#fetch\(\s*[\'"`]([^\'"`]+)[\'"`]#', $texto, $m);

    foreach ($m[1] as $url) {
        // Absolutas, plantillas con variables dentro y anclas: fuera.
        if (preg_match('#^(https?:|//|data:|\$\{)#i', $url)) { continue; }
        if (strpos($url, '${') !== false) { continue; }

        $ruta = preg_replace('/[?#].*$/', '', $url);
        if ($ruta === '') { continue; }

        $comprobadas++;
        // El JS vive en assets/js/ o panel/assets/js/, pero la URL es relativa
        // a la PÁGINA que lo carga, no al script. Se acepta si el endpoint
        // existe colgando de la raíz o de la carpeta del panel, que son las
        // dos únicas bases que usa el sitio.
        $bases = [$raizN, $raizN . '/panel'];
        $vale = false;
        foreach ($bases as $base) {
            foreach ([$ruta, $ruta . '.php'] as $candidato) {
                if (is_file($base . '/' . ltrim($candidato, './'))) { $vale = true; break 2; }
            }
        }
        if (!$vale) {
            $rel = ltrim(substr($js, strlen($raizN)), '/');
            $fallos[] = "$rel  ->  fetch('$url')  (ningún fichero responde a esa URL)";
        }
    }
}

// ---------------------------------------------------------------------------
echo "PROBAR_RUTAS — $comprobadas rutas comprobadas\n";
echo str_repeat('-', 72), "\n";

if ($fallos) {
    foreach ($fallos as $f) { echo "  FALLA  $f\n"; }
    echo "\n", count($fallos), " ruta(s) rota(s).\n";
    exit(1);
}

echo "  Todas resuelven.\n";
