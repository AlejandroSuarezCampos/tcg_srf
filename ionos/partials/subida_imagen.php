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

        $nombreArchivo = $slug . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

        if (!move_uploaded_file($archivo['tmp_name'], $carpetaDisco . $nombreArchivo)) {
            return ['ok' => false, 'error' => 'No se pudo guardar la imagen en el servidor.'];
        }

        return ['ok' => true, 'ruta' => $carpetaWeb . $nombreArchivo];
    }

    /** Sanea el nombre de una expansión para usarlo como nombre de carpeta en disco. */
    function slugCarpetaExpansion(string $nombreExpansion): string {
        $limpio = preg_replace('/[<>:"\/\\\\|?*]+/', '', $nombreExpansion);
        $limpio = trim($limpio);
        return $limpio !== '' ? $limpio : 'Sin_expansion';
    }
}
