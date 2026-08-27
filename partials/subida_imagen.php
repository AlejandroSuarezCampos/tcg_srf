<?php
/**
 * SUBIDA DE IMÁGENES DESDE EL PANEL — helper compartido.
 *
 * Mismo patrón de validación que ya usaba configuracion.php para la foto de
 * perfil (extensión de la whitelist + getimagesize() real + nombre de
 * archivo aleatorio, nunca el que trae el navegador): un solo sitio para no
 * repetir esa lógica en cada formulario del panel que suba una imagen.
 */

if (!function_exists('subirImagenPanel')) {

    /**
     * @param array  $archivo      Un elemento de $_FILES.
     * @param string $carpetaDisco Carpeta destino en disco, absoluta, con barra final.
     * @param string $carpetaWeb   Esa misma carpeta como ruta web relativa, con barra final.
     * @param string $nombreBase   Texto para componer el nombre de archivo (se sanea a slug).
     * @return array{ok:bool, ruta?:string, error?:string}
     */
    function subirImagenPanel(array $archivo, string $carpetaDisco, string $carpetaWeb, string $nombreBase): array {
        $extensiones = ['jpg', 'jpeg', 'png', 'webp'];
        $maxBytes    = 6 * 1024 * 1024; // 6 MB

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'No se pudo subir la imagen. Inténtalo de nuevo.'];
        }
        if ($archivo['size'] > $maxBytes) {
            return ['ok' => false, 'error' => 'La imagen pesa demasiado (máximo 6 MB).'];
        }

        $infoImagen = @getimagesize($archivo['tmp_name']);
        $extension  = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        if ($infoImagen === false || !in_array($extension, $extensiones, true)) {
            return ['ok' => false, 'error' => 'El archivo debe ser una imagen JPG, PNG o WEBP.'];
        }

        if (!is_dir($carpetaDisco) && !mkdir($carpetaDisco, 0755, true) && !is_dir($carpetaDisco)) {
            return ['ok' => false, 'error' => 'No se pudo crear la carpeta de destino.'];
        }

        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($nombreBase));
        $slug = trim($slug, '_');
        if ($slug === '') { $slug = 'imagen'; }

        /* TODO SE GUARDA EN WEBP, suba lo que suba quien suba.
           Un PNG de arte de carta ronda 1,5 MB y en WebP se queda en 150-250 KB
           sin diferencia visible. Multiplicado por las cartas que hay en una
           pantalla de colección, es la diferencia entre que un móvil de gama
           baja cargue el mercado o se quede pensando. Convertir en la subida y
           no "algún día con un script" es lo único que garantiza que no vuelvan
           a entrar PNG por la puerta de atrás. */
        $nombreArchivo = $slug . '_' . bin2hex(random_bytes(4)) . '.webp';
        $destino = $carpetaDisco . $nombreArchivo;

        if (!convertirAWebp($archivo['tmp_name'], $destino)) {
            /* Sin WebP en el servidor no se pierde la subida: se guarda tal
               cual con su extensión original. Vale más una imagen pesada que
               un formulario que no deja guardar. */
            $nombreArchivo = $slug . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            if (!move_uploaded_file($archivo['tmp_name'], $carpetaDisco . $nombreArchivo)) {
                return ['ok' => false, 'error' => 'No se pudo guardar la imagen en el servidor.'];
            }
        }

        return ['ok' => true, 'ruta' => $carpetaWeb . $nombreArchivo];
    }

    /**
     * Guarda una imagen como WebP. Devuelve false si no se pudo (sin GD, sin
     * soporte WebP, o formato que GD no sabe leer).
     *
     * La transparencia se conserva a propósito: los artes de carta son PNG con
     * alfa y aplastarlos contra un fondo blanco los dejaría con un recuadro
     * alrededor sobre el fondo oscuro de la web.
     *
     * Calidad 85: por debajo se empiezan a ver bandas en los degradados de las
     * plantillas de rareza, por encima el archivo crece sin que se note.
     */
    function convertirAWebp(string $origen, string $destino, int $calidad = 85): bool {
        if (!function_exists('imagewebp') || !function_exists('imagecreatefromstring')) {
            return false;
        }
        $datos = @file_get_contents($origen);
        if ($datos === false) { return false; }

        $imagen = @imagecreatefromstring($datos);
        if ($imagen === false) { return false; }

        imagepalettetotruecolor($imagen);
        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);

        $ok = @imagewebp($imagen, $destino, $calidad);
        imagedestroy($imagen);
        return (bool) $ok;
    }

    /**
     * Rutas que NO se convierten nunca, con su motivo.
     *
     *   · los iconos de Apple y los favicon: iOS no acepta WebP ahí y se
     *     quedaría sin icono;
     *   · `plantillas/…/original.png`: es la fuente de la que se recortan las
     *     zonas, no se le sirve a nadie, y recomprimirla la degradaría en cada
     *     recorte posterior.
     */
    function imagenIntocable(string $ruta): bool {
        $n = str_replace('\\', '/', $ruta);
        return (bool) preg_match('~/apple-icon-[^/]+$~i', $n)
            || (bool) preg_match('~/plantillas/[^/]+/original\.png$~i', $n)
            || (bool) preg_match('~favicon~i', $n);
    }

    /**
     * Convierte a WebP todas las imágenes ya subidas y reescribe las rutas que
     * la base de datos tenga apuntando a ellas.
     *
     * VIVE AQUÍ Y NO EN EL SCRIPT porque la lanzan DOS sitios: la herramienta
     * de línea de comandos (`db/herramientas/convertir_a_webp.php`) y el panel
     * de mantenimiento. Con una copia en cada uno, la del panel se habría
     * quedado atrás a la primera corrección.
     *
     * Con `$aplica` en false no toca NADA: convierte a un temporal solo para
     * poder medir cuánto se ahorraría, y lo borra. Es a propósito, porque esto
     * borra los archivos originales y reescribe columnas.
     *
     * @return array{archivos:array,convertidas:int,antes:int,despues:int,rutas:int,fallos:array}
     */
    function convertirImagenesAWebp($db, bool $aplica): array {
        $raiz   = realpath(__DIR__ . '/..');
        $imgDir = $raiz . '/assets/img';

        $mapa = [];            // ruta web vieja => ruta web nueva
        $archivos = [];        // para el informe
        $antes = 0;
        $despues = 0;
        $fallos = [];

        if (!is_dir($imgDir)) {
            return ['archivos' => [], 'convertidas' => 0, 'antes' => 0,
                    'despues' => 0, 'rutas' => 0, 'fallos' => ['No existe assets/img.']];
        }

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($imgDir));
        foreach ($it as $f) {
            if (!$f->isFile()) { continue; }
            if (!in_array(strtolower($f->getExtension()), ['png', 'jpg', 'jpeg'], true)) { continue; }

            $origen = $f->getPathname();
            if (imagenIntocable($origen)) { continue; }

            $destino   = preg_replace('/\.(png|jpe?g)$/i', '.webp', $origen);
            $pesoAntes = $f->getSize();

            if ($aplica) {
                $ok = convertirAWebp($origen, $destino);
                $pesoDespues = $ok ? filesize($destino) : $pesoAntes;
            } else {
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

            if ($aplica) { @unlink($origen); }

            $archivos[] = [
                'ruta'    => substr($webVieja, 2),
                'antes'   => $pesoAntes,
                'despues' => $pesoDespues,
            ];
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

    /** Sanea el nombre de una expansión para usarlo como nombre de carpeta en disco. */
    function slugCarpetaExpansion(string $nombreExpansion): string {
        $limpio = preg_replace('/[<>:"\/\\\\|?*]+/', '', $nombreExpansion);
        $limpio = trim($limpio);
        return $limpio !== '' ? $limpio : 'Sin_expansion';
    }
}
