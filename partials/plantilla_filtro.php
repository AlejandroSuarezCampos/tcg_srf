<?php
/**
 * FILTRADO DEL MODO «TODAS» de plantilla.php.
 *
 * El modo «mías» filtra en SQL (`listarColeccionUsuario` ya acepta filtros).
 * El modo «todas» tira de `listarColeccionCompleta()`, que no los acepta y la
 * usan otras pantallas, así que se filtra aquí: son ~500 filas y una sola
 * pasada, mucho menos riesgo que tocarle el SQL a una consulta compartida.
 *
 * Vive aparte porque es la pieza que puede devolver cartas equivocadas SIN
 * FALLAR —una condición mal escrita no lanza nada, solo enseña de menos o de
 * más— y así se puede probar sin base de datos.
 */

/**
 * @param array $cromos   Filas de una expansión, tal cual las da listarColeccionCompleta().
 * @param array $f        Filtros: nombre, id_equipo, id_expansion, rarezas[], tengo.
 * @param array $poseidas Mapa id_cromo => true de lo que ya tiene el jugador.
 * @return array Las filas que pasan, en el mismo orden.
 */
function plantilla_filtrar(array $cromos, array $f, array $poseidas): array {
    $nombre    = trim((string) ($f['nombre'] ?? ''));
    $equipo    = (string) ($f['id_equipo'] ?? '');
    $expansion = (string) ($f['id_expansion'] ?? '');
    $rarezas   = array_map('strval', (array) ($f['rarezas'] ?? []));
    $tengo     = (string) ($f['tengo'] ?? '');

    $pasan = [];
    foreach ($cromos as $c) {
        $laTengo = isset($poseidas[(int) $c['id_cromo']]);

        /* Búsqueda por nombre sin distinguir mayúsculas ni acentos: quien
           escribe «asuto» espera encontrar «Asuto», y quien escribe «rigo»
           espera «Di Rigo». `stripos` sola no cubre los acentos. */
        if ($nombre !== '' && !plantilla_contiene($c['nombre'] ?? '', $nombre)) continue;

        if ($equipo !== '' && (string) ($c['id_equipo'] ?? '') !== $equipo) continue;
        if ($expansion !== '' && (string) ($c['id_expansion'] ?? '') !== $expansion) continue;
        if ($rarezas && !in_array((string) ($c['id_rareza'] ?? ''), $rarezas, true)) continue;

        if ($tengo === 'tengo' && !$laTengo) continue;
        if ($tengo === 'falta' && $laTengo) continue;

        $pasan[] = $c;
    }
    return $pasan;
}

/** ¿`$aguja` aparece en `$pajar`, ignorando mayúsculas y acentos? */
function plantilla_contiene(string $pajar, string $aguja): bool {
    return mb_stripos(plantilla_plano($pajar), plantilla_plano($aguja)) !== false;
}

/** Quita acentos y pasa a minúsculas. Sin depender de la extensión `intl`. */
function plantilla_plano(string $t): string {
    $de = ['á','à','ä','â','é','è','ë','ê','í','ì','ï','î','ó','ò','ö','ô','ú','ù','ü','û','ñ','ç'];
    $a  = ['a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','u','u','u','u','n','c'];
    return str_replace($de, $a, mb_strtolower($t, 'UTF-8'));
}
