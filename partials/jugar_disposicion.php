<?php
/**
 * DISPOSICIÓN — ¿puede este jugador salir al campo?
 *
 * Es lo primero de `jugar.php` porque es la puerta: sin un once completo
 * marcado como titular, los duelos y las cadenas están cerrados. Antes eso no
 * se decía en ninguna parte — se descubría al llegar a `duelos.php` y
 * encontrarse el botón apagado sin explicación.
 *
 * ⚠️ `obtenerMazoTitular()` DEVUELVE NULL EN DOS CASOS DISTINTOS: cuando no hay
 * ningún mazo marcado como titular, y cuando lo hay pero no llega a las once
 * cartas (mira la última línea de esa función). Son situaciones opuestas para
 * quien está jugando: en la primera hay que elegir una, en la segunda ya está
 * elegida y lo que falta es rellenarla. Distinguirlas es precisamente el
 * trabajo de esta función, y por eso recibe también la lista de mazos.
 *
 * Se usa `obtenerMazoTitular()` como señal de «listo» y no un recuento propio
 * porque ESA es la puerta que abren de verdad `duelos.php` y `cadenas.php`: si
 * esta pantalla dijera «listo» con otro criterio, mandaría a la gente a un
 * botón apagado.
 *
 * Vive aparte porque es una cadena de condiciones que puede estar mal SIN
 * FALLAR: siempre devuelve un estado, así que un orden equivocado no rompe
 * nada, solo le dice al jugador algo que no es.
 */

/**
 * @param array|null $titular Fila de `obtenerMazoTitular()`, o null. Non-null
 *                            significa «los duelos te dejan entrar».
 * @param array      $mazos   Filas de `listarMazosUsuario()` (traen `cartas` y `titular`).
 * @param int        $tamano  Huecos que pide una alineación (Tcg::MAZO_TAMANO).
 * @return array estado ('no'|'casi'|'si'), rotulo, titulo, texto, accion
 */
function jugar_disposicion(?array $titular, array $mazos, int $tamano): array {

    if (!$mazos) {
        return [
            'estado' => 'no', 'rotulo' => 'Antes de jugar',
            'titulo' => 'No tienes ninguna alineación',
            'texto'  => 'Sin un once montado no puedes entrar en duelos ni en cadenas.',
            'accion' => 'Montar la primera',
        ];
    }

    // La puerta ha dicho que sí: no hay nada más que comprobar.
    if ($titular) {
        $formacion = Tcg::FORMACIONES[$titular['formacion']]['nombre'] ?? $titular['formacion'];
        return [
            'estado' => 'si', 'rotulo' => 'Listo para salir',
            'titulo' => '«' . $titular['nombre'] . '» en ' . $formacion,
            'texto'  => 'Once completo. Puedes entrar en cualquier duelo o cadena.',
            'accion' => 'Cambiar la alineación',
        ];
    }

    // La puerta ha dicho que no. ¿Por cuál de los dos motivos?
    $marcado = null;
    foreach ($mazos as $m) {
        if ((int) ($m['titular'] ?? 0) === 1) { $marcado = $m; break; }
    }

    if ($marcado) {
        $cartas = (int) ($marcado['cartas'] ?? 0);
        $faltan = max(0, $tamano - $cartas);
        return [
            'estado' => 'casi', 'rotulo' => 'Te falta un paso',
            'titulo' => '«' . $marcado['nombre'] . '» está incompleta',
            'texto'  => $cartas . ' de ' . $tamano . ' huecos cubiertos'
                        . ($faltan > 0 ? ', te ' . ($faltan === 1 ? 'falta 1' : 'faltan ' . $faltan) : '')
                        . '. Un once a medias no puede salir al campo.',
            'accion' => 'Completarla',
        ];
    }

    $n = count($mazos);
    return [
        'estado' => 'casi', 'rotulo' => 'Te falta un paso',
        'titulo' => 'Ninguna alineación está marcada como titular',
        'texto'  => 'Tienes ' . $n . ($n === 1 ? ' alineación' : ' alineaciones')
                    . ', pero hay que marcar cuál sale al campo.',
        'accion' => 'Elegir titular',
    ];
}
