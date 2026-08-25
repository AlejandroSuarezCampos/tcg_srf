<?php
/**
 * «LO SIGUIENTE» — qué acción se le pone delante al jugador al entrar.
 *
 * Vive aparte de `hoy.php` por dos motivos: es la única decisión de la pantalla
 * que puede estar mal sin que se note (elige una de seis cosas y siempre
 * devuelve algo, así que un orden equivocado no rompe nada, solo empeora el
 * juego en silencio), y así se puede probar sin base de datos.
 *
 * Función pura: entra un resumen del estado, sale la tarjeta. Ni consulta ni
 * imprime nada.
 */

/**
 * @param array $e Estado del jugador:
 *   sobre_inicial   bool   tiene el sobre de bienvenida sin abrir
 *   duelo_en_juego  ?array ['id'=>int, 'rival'=>string] o null
 *   listas          array  misiones completadas sin reclamar
 *   cerca           ?array misión más avanzada sin completar (+ 'ratio')
 *   faltan          int    fichas que le faltan para la colección completa
 * @return array Tarjeta: tono, rotulo, titulo, texto, accion, url, icono
 */
function hoy_siguiente(array $e): array {
    /* EL ORDEN ES LA DECISIÓN, no el contenido de cada rama:
       1. lo que no ha empezado nadie más que él y le abre el juego
       2. lo que está corriendo AHORA sin él
       3. lo que ya se ha ganado y solo falta recoger
       4. lo que está a punto de caer
       5. lo que siempre se puede hacer
       Un "a un paso" por delante de un partido en juego mandaría al jugador a
       otro sitio mientras su duelo se resuelve solo. */

    if (!empty($e['sobre_inicial'])) {
        return [
            'tono' => 'brasa', 'rotulo' => 'Empieza por aquí',
            'titulo' => 'Tu sobre de bienvenida te espera',
            'texto'  => 'Once jugadores con las posiciones justas para montar tu primer once. Es gratis.',
            'accion' => 'Abrirlo', 'url' => 'sobres.php', 'icono' => 'ph-package',
        ];
    }

    if (!empty($e['duelo_en_juego'])) {
        $d = $e['duelo_en_juego'];
        return [
            'tono' => 'roja', 'rotulo' => 'Partido a medias',
            'titulo' => 'Tienes un duelo en juego contra ' . ($d['rival'] ?: 'tu rival'),
            'texto'  => 'El marcador sigue corriendo sin ti. Vuelve antes de que se resuelva solo.',
            'accion' => 'Volver al partido', 'url' => 'duelo.php?id=' . (int) $d['id'],
            'icono'  => 'ph-sword',
        ];
    }

    if (!empty($e['listas'])) {
        $n = count($e['listas']);
        $monedas = array_sum(array_column($e['listas'], 'recompensa_monedas'));
        return [
            'tono' => 'cesped', 'rotulo' => 'Cobra lo tuyo',
            'titulo' => $n === 1
                ? 'Has cerrado «' . $e['listas'][0]['nombre'] . '»'
                : 'Tienes ' . $n . ' objetivos cerrados sin cobrar',
            'texto'  => 'Son ' . number_format($monedas, 0, ',', '.') . ' monedas esperando a que las reclames.',
            'accion' => 'Reclamar', 'url' => 'misiones.php', 'icono' => 'ph-target',
        ];
    }

    /* Solo a partir de la mitad: decirle a alguien que "está a un paso" cuando
       lleva 1 de 50 es mentira, y una pantalla que exagera deja de leerse. */
    if (!empty($e['cerca']) && ($e['cerca']['ratio'] ?? 0) >= .5) {
        $m = $e['cerca'];
        $falta = (int) $m['objetivo'] - (int) $m['progreso'];
        return [
            'tono' => 'amarilla', 'rotulo' => 'A un paso',
            'titulo' => 'Te ' . ($falta === 1 ? 'falta 1' : 'faltan ' . $falta) . ' para «' . $m['nombre'] . '»',
            'texto'  => $m['descripcion'] ?? 'Estás muy cerca de cerrarlo.',
            'accion' => 'Ver objetivos', 'url' => 'misiones.php', 'icono' => 'ph-target',
        ];
    }

    if ((int) ($e['faltan'] ?? 0) > 0) {
        return [
            'tono' => 'brasa', 'rotulo' => 'Sigue fichando',
            'titulo' => 'Te faltan ' . number_format((int) $e['faltan'], 0, ',', '.') . ' fichas',
            'texto'  => 'Un sobre son cartas nuevas. El mercado, las que sabes que te faltan.',
            'accion' => 'Abrir un sobre', 'url' => 'sobres.php', 'icono' => 'ph-package',
        ];
    }

    return [
        'tono' => 'cesped', 'rotulo' => 'Plantilla completa',
        'titulo' => 'Las tienes todas',
        'texto'  => 'No queda ficha por conseguir. Ahora toca demostrarlo en el campo.',
        'accion' => 'Buscar duelo', 'url' => 'duelos.php', 'icono' => 'ph-sword',
    ];
}
