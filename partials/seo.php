<?php
/**
 * SEO — canonical, Open Graph y datos estructurados.
 *
 * Todo lo que un buscador o una red social necesitan para entender una página
 * vive aquí, en un sitio, porque son cosas que se contradicen solas cuando se
 * copian a mano: el `og:url` que no coincide con el canonical, el título de la
 * pestaña que no es el del Open Graph, el dominio escrito con www en un fichero
 * y sin www en otro.
 *
 * ⚠️ EL HOST VA SIN WWW. Es el canónico elegido, y el `.htaccess` redirige
 *    www → sin www con un 301. Si algún día se cambia, se cambia AQUÍ y en esa
 *    regla, y en ningún otro sitio.
 *
 * En desarrollo (XAMPP, bajo /tcg_srf/) el canonical se construye con el host
 * real de la petición, no con el de producción: si no, cada página de local
 * declararía como canónica una URL de tcgfrontier.es y sería mentira. Google no
 * ve el local, pero las herramientas de auditoría sí, y una auditoría que se
 * hace contra local tiene que medir local.
 */

/** El origen del sitio: https://tcgfrontier.es en producción, http://localhost/tcg_srf en local. */
function seoOrigen(): string {
    static $origen = null;
    if ($origen !== null) { return $origen; }

    $host = $_SERVER['HTTP_HOST'] ?? 'tcgfrontier.es';

    // En producción el origen es fijo y canónico, pase lo que pase en la
    // cabecera Host: así una petición con un Host raro no puede inyectar su
    // dominio en nuestro canonical ni en el JSON-LD.
    if (!preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', $host)) {
        return $origen = 'https://tcgfrontier.es';
    }

    // Local: el proyecto cuelga de una subcarpeta, así que hay que incluirla.
    $carpeta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    // Las páginas del panel viven un nivel más abajo; la raíz del sitio es la del proyecto.
    if (substr($carpeta, -6) === '/panel') { $carpeta = substr($carpeta, 0, -6); }
    return $origen = 'http://' . $host . $carpeta;
}

/**
 * La URL canónica de la página que se está sirviendo.
 *
 * Se construye a partir de la ruta REAL y de una lista blanca de parámetros,
 * no copiando `$_SERVER['REQUEST_URI']` tal cual. El motivo es que casi toda la
 * cadena de consulta que llega a estas páginas NO cambia el contenido:
 *
 *   ?utm_source=discord   una campaña
 *   ?fbclid=...           lo que pega Facebook al enlace
 *   ?ok=publicado         un aviso de "guardado" tras un POST
 *   ?error=csrf           un aviso de error
 *
 * Con esos parámetros dentro, cada visita desde una campaña distinta sería para
 * Google una página distinta con el mismo contenido. Solo sobreviven los tres
 * que de verdad seleccionan qué se muestra.
 */
function seoCanonical(): string {
    static $permitidos = ['ver', 'id', 'u'];

    $ruta = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');

    // El .htaccess sirve /plantilla ejecutando plantilla.php. Si alguien llega
    // con el .php en la URL, el canonical tiene que apuntar a la forma limpia,
    // que es a la que redirige el servidor.
    $ruta = preg_replace('/\.php$/', '', $ruta);

    // En local la ruta ya incluye /tcg_srf; en producción no. Como seoOrigen()
    // devuelve el origen CON la subcarpeta en local, hay que quitarla de la
    // ruta para no repetirla.
    $origen = seoOrigen();
    $prefijo = parse_url($origen, PHP_URL_PATH) ?: '';
    if ($prefijo !== '' && strpos($ruta, $prefijo) === 0) {
        $ruta = substr($ruta, strlen($prefijo));
    }
    if ($ruta === '' || $ruta === '/landing') { $ruta = '/'; }

    $q = [];
    foreach ($permitidos as $clave) {
        if (isset($_GET[$clave]) && $_GET[$clave] !== '') {
            $q[$clave] = (string) $_GET[$clave];
        }
    }

    /* `/plantilla` y `/plantilla?ver=todas` sirven EXACTAMENTE el mismo HTML a
       quien no ha iniciado sesión, y son dos de las ocho URL duplicadas que
       encontró la auditoría. La canónica es la corta. Con sesión sí son dos
       páginas distintas («las mías» frente a «todas»), así que ahí el
       parámetro se respeta. */
    if ($ruta === '/plantilla' && ($q['ver'] ?? '') === 'todas' && empty($_SESSION['id_usuario'])) {
        unset($q['ver']);
    }

    return $origen . $ruta . ($q ? '?' . http_build_query($q) : '');
}

/**
 * Una URL absoluta del sitio a partir de una ruta relativa a la raíz.
 *
 * Codifica cada segmento por separado. Hace falta porque las carpetas del arte
 * llevan espacios y acentos —`assets/img/Cromos/Apuesta Segura/…`,
 * `…/InazumaWorldCup/España/…`— y un espacio crudo dentro de una etiqueta
 * `og:image` no es una URL válida: los navegadores lo perdonan, pero los
 * rastreadores de Discord, X y Facebook la descartan y el enlace vuelve a salir
 * sin imagen, que es justo lo que veníamos a arreglar.
 */
function seoUrl(string $ruta = ''): string {
    $ruta = ltrim($ruta, '/');
    if ($ruta === '') { return seoOrigen() . '/'; }

    // La cadena de consulta se deja tal cual: ahí las reglas son otras.
    [$camino, $consulta] = array_pad(explode('?', $ruta, 2), 2, null);

    $camino = implode('/', array_map(
        // Un segmento que YA venga codificado no se vuelve a codificar: si no,
        // un %20 acabaría convertido en %2520.
        fn(string $t) => rawurlencode(rawurldecode($t)),
        explode('/', $camino)
    ));

    return seoOrigen() . '/' . $camino . ($consulta !== null ? '?' . $consulta : '');
}

/**
 * La imagen que sale cuando alguien pega un enlace del juego en Discord, en X
 * o en WhatsApp.
 *
 * Antes aquí no había nada: el enlace salía en blanco, sin imagen ni título,
 * justo en el momento en que alguien estaba recomendando el juego. Para un
 * proyecto que se distribuye por Discord eso era el escaparate apagado.
 */
function seoImagenSocial(): string {
    return seoUrl('assets/img/og-portada.png');
}

/**
 * El trozo de URL de una ficha de carta.
 *
 *   Tom Skipper                      -> tom-skipper
 *   Fei Rune (en dos equipos)        -> fei-rune-instituto-zeus
 *   Perú Es Clave 67                 -> peru-es-clave-67
 *
 * El equipo SOLO se añade cuando hace falta —cuatro nombres del catálogo juegan
 * en dos equipos distintos—, porque una URL más larga sin motivo es una URL
 * peor. Quién es ambiguo lo decide `fichasPublicas()`, que ve el catálogo
 * entero; desde una carta suelta es imposible saberlo.
 */
function slugFicha(string $nombre, ?string $equipo = null): string
{
    $trozo = function (string $t): string {
        // Los nombres llevan acentos y alguna eñe. `iconv` los pasa a ASCII;
        // si la locale no lo permite, el preg de después se los come igual.
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $t) ?: $t;
        $t = strtolower($t);
        $t = preg_replace('/[^a-z0-9]+/', '-', $t);
        return trim($t, '-');
    };

    $slug = $trozo($nombre);
    if ($equipo !== null && $equipo !== '') { $slug .= '-' . $trozo($equipo); }
    return $slug;
}

/** La URL absoluta de una ficha, para canonical, Open Graph y sitemap. */
function urlFicha(string $nombre, ?string $equipo = null): string
{
    return seoUrl('carta/' . slugFicha($nombre, $equipo));
}

/**
 * Permite cachear una página pública durante unos minutos.
 *
 * `session_start()` manda por su cuenta `Cache-Control: no-store, no-cache,
 * must-revalidate`, y tiene sentido en las pantallas de cuenta: llevan tu saldo,
 * tus cartas y el estado de tus duelos, y una copia guardada sería la de otro.
 *
 * Pero la portada y el catálogo público NO llevan nada de eso cuando no hay
 * sesión, y aun así se prohibían a sí mismos la caché. Resultado: el rastreador
 * de Google —que no manda cookies y por tanto nunca tiene sesión— se bajaba
 * entera cada vez la página de 657 KB, y lo mismo cualquier visitante que
 * volviera.
 *
 * Solo se llama SIN sesión iniciada, y `Vary: Cookie` (que PHP ya envía) es la
 * red de seguridad: una caché compartida guarda esta copia bajo la clave "sin
 * cookie", así que nadie con sesión puede recibirla.
 */
function seoCachePublica(int $segundos = 300): void
{
    if (!empty($_SESSION['id_usuario']) || headers_sent()) { return; }
    header('Cache-Control: public, max-age=' . $segundos);
    header('Pragma: cache');          // pisa el `no-cache` que deja session_start()
    header_remove('Expires');
}
