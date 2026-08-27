<?php
/**
 * TAREAS DE MANTENIMIENTO — la lógica, en un solo sitio.
 *
 * Las usan DOS sitios: el panel de administración (`panel/mantenimiento.php`,
 * por AJAX) y los dos guiones de consola de esta misma carpeta. Vive aquí y no
 * dentro de cada uno porque son tareas que borran archivos y reescriben
 * columnas: tener dos copias de eso es tener dos versiones de lo que pasa al
 * pulsar el botón, y solo se descubre cuando ya han divergido.
 *
 * Las dos funciones aceptan `$aplica`:
 *   false → SIMULA. Calcula y devuelve exactamente lo que haría, sin tocar
 *           nada. Es el modo por defecto en los dos frentes, a propósito.
 *   true  → lo hace.
 */

require_once __DIR__ . '/../../partials/subida_imagen.php';   // convertirAWebp()

/**
 * Pasa a WebP las imágenes que todavía no lo estén y arregla las rutas
 * guardadas en la base.
 *
 * Qué NO se toca, y por qué:
 *   · los iconos de Apple y los favicon: iOS no acepta WebP ahí y se quedaría
 *     sin icono.
 *   · `plantillas/…/original.png`: es la fuente de la que se recortan las
 *     zonas, no se le sirve a nadie, y recomprimirla la degradaría en cada
 *     recorte posterior.
 *
 * Devuelve ["archivos" => [...], "convertidas", "antes", "despues", "rutas",
 *           "fallos"]. Los pesos van en bytes.
 */
function mantenimientoWebp(Tcg $db, bool $aplica = false): array {
    $raiz   = realpath(__DIR__ . '/../../');
    $imgDir = $raiz . '/assets/img';

    $intocable = function (string $ruta): bool {
        $n = str_replace('\\', '/', $ruta);
        return (bool) preg_match('~/apple-icon-[^/]+$~i', $n)
            || (bool) preg_match('~/plantillas/[^/]+/original\.png$~i', $n)
            || (bool) preg_match('~favicon~i', $n);
    };

    $mapa = [];
    $archivos = [];
    $antes = 0;
    $despues = 0;
    $fallos = [];

    if (!is_dir($imgDir)) {
        return ['archivos' => [], 'convertidas' => 0, 'antes' => 0, 'despues' => 0,
                'rutas' => 0, 'fallos' => ['No existe assets/img.']];
    }

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($imgDir));
    foreach ($it as $f) {
        if (!$f->isFile()) { continue; }
        if (!in_array(strtolower($f->getExtension()), ['png', 'jpg', 'jpeg'], true)) { continue; }

        $origen = $f->getPathname();
        if ($intocable($origen)) { continue; }

        $destino   = preg_replace('/\.(png|jpe?g)$/i', '.webp', $origen);
        $pesoAntes = $f->getSize();

        if ($aplica) {
            $ok = convertirAWebp($origen, $destino);
            $pesoDespues = $ok ? filesize($destino) : $pesoAntes;
        } else {
            // En simulación se convierte a un temporal para poder medir de verdad.
            $tmp = tempnam(sys_get_temp_dir(), 'webp');
            $ok  = convertirAWebp($origen, $tmp);
            $pesoDespues = $ok ? filesize($tmp) : $pesoAntes;
            @unlink($tmp);
        }

        if (!$ok) { $fallos[] = basename($origen); continue; }

        $antes   += $pesoAntes;
        $despues += $pesoDespues;

        $webVieja = './' . str_replace('\\', '/', substr($origen,  strlen($raiz) + 1));
        $webNueva = './' . str_replace('\\', '/', substr($destino, strlen($raiz) + 1));
        $mapa[$webVieja] = $webNueva;

        $archivos[] = [
            'ruta'    => substr($webVieja, 2),
            'antes'   => $pesoAntes,
            'despues' => $pesoDespues,
        ];

        if ($aplica) { @unlink($origen); }
    }

    return [
        'archivos'    => $archivos,
        'convertidas' => count($mapa),
        'antes'       => $antes,
        'despues'     => $despues,
        'rutas'       => $mapa ? $db->reescribirRutasImagen($mapa, $aplica) : 0,
        'fallos'      => $fallos,
    ];
}

/**
 * Asigna su compo a las cartas jugables que se quedaron sin ninguna.
 *
 * Pasaba con las creadas desde el editor de nodos: el alta del panel de cromos
 * siempre ha derivado el rasgo de configuración justo después de crear, pero
 * el editor de nodos no lo hacía, así que esos jugadores salían sin
 * contraataque, sin brecha, sin vínculo ni justicia, y por tanto sin aportar
 * nada a las compos de la alineación en la que juegan. El agujero ya está
 * tapado; esto arregla las que se crearon antes.
 *
 * NO pisa nada elegido a mano: la derivación respeta los `manual = 1`.
 * Es idempotente: pasarla dos veces no cambia nada la segunda.
 *
 * Devuelve ["pendientes" => [...], "tocadas", "quedan" => [...]].
 */
function mantenimientoCompos(Tcg $db, bool $aplica = false): array {
    $pendientes = $db->cartasSinCompo();

    if (!$aplica || !$pendientes) {
        return ['pendientes' => $pendientes, 'tocadas' => 0, 'quedan' => $pendientes];
    }

    /* Se derivan TODAS de una pasada y no una a una: la función ya recorre el
       catálogo entero respetando lo manual, así que una sola llamada deja el
       catálogo coherente y de paso arregla cualquier otra que estuviera mal. */
    $tocadas = $db->derivarRasgosConfiguracion();

    return [
        'pendientes' => $pendientes,
        'tocadas'    => $tocadas,
        /* Las que se queden fuera son las que no tienen afinidad real
           ("No-afi"): esas NO llevan arquetipo, es decisión de diseño y no
           una carencia — forzarles una compo sería inventársela. Van a salir
           SIEMPRE en esta lista y es correcto que salgan. */
        'quedan'     => $db->cartasSinCompo(),
    ];
}
