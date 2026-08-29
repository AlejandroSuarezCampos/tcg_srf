<?php
/**
 * «LO SIGUIENTE» de hoy.php — orden de prioridad.
 *
 * Lo que se protege: que la pantalla nunca mande al jugador a otro sitio
 * mientras tiene un partido corriendo, que no le prometa "estás a un paso"
 * cuando lleva 1 de 50, y que SIEMPRE devuelva una tarjeta — un hueco en blanco
 * en la portada es peor que cualquier sugerencia mediocre.
 *
 * Sin base de datos: la función es pura.
 *
 *   php db/pruebas/probar_hoy_prioridad.php
 */
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
require_once __DIR__ . '/../../partials/hoy_prioridad.php';

$fallos = 0;
function comprobar(string $titulo, callable $fn): void {
    global $fallos;
    try { $fn(); echo "  ok  $titulo\n"; }
    catch (Throwable $e) { $fallos++; echo "FALLO  $titulo\n       " . $e->getMessage() . "\n"; }
}
function igual($esperado, $real, string $que = ''): void {
    if ($esperado !== $real) {
        throw new RuntimeException("$que: esperaba " . var_export($esperado, true) . ", vino " . var_export($real, true));
    }
}

/** Jugador sin nada pendiente y con la colección a medias. */
function base(array $cambios = []): array {
    return $cambios + [
        'sobre_inicial' => false,
        'duelo_en_juego' => null,
        'listas' => [],
        'cerca' => null,
        'faltan' => 350,
    ];
}
$mision = ['nombre' => 'Diez duelos', 'descripcion' => 'Juega diez duelos', 'objetivo' => 10, 'recompensa_monedas' => 500];
$duelo  = ['id' => 7, 'rival' => 'Gonzalo'];

echo "\n«Lo siguiente» — orden de prioridad\n\n";

comprobar('el sobre de bienvenida manda por encima de todo', function () use ($mision, $duelo) {
    $r = hoy_siguiente(base([
        'sobre_inicial' => true,
        'duelo_en_juego' => $duelo,
        'listas' => [$mision],
    ]));
    igual('sobres.php', $r['url'], 'destino');
    igual('brasa', $r['tono'], 'tono');
});

comprobar('un partido en juego gana a cobrar objetivos', function () use ($mision, $duelo) {
    // el caso que importa: si se invierte, el jugador se va a misiones mientras
    // su duelo se resuelve solo con la opción segura
    $r = hoy_siguiente(base(['duelo_en_juego' => $duelo, 'listas' => [$mision]]));
    igual('duelo.php?id=7', $r['url'], 'destino');
    igual('roja', $r['tono'], 'tono');
    if (!str_contains($r['titulo'], 'Gonzalo')) throw new RuntimeException('no nombra al rival');
});

comprobar('sin rival conocido no escribe un hueco', function () {
    $r = hoy_siguiente(base(['duelo_en_juego' => ['id' => 3, 'rival' => '']]));
    if (!str_contains($r['titulo'], 'tu rival')) throw new RuntimeException("titulo: {$r['titulo']}");
});

comprobar('objetivos cobrables: uno se nombra, varios se cuentan', function () use ($mision) {
    $uno = hoy_siguiente(base(['listas' => [$mision]]));
    igual('misiones.php', $uno['url'], 'destino');
    if (!str_contains($uno['titulo'], 'Diez duelos')) throw new RuntimeException("titulo: {$uno['titulo']}");

    $dos = hoy_siguiente(base(['listas' => [$mision, $mision]]));
    if (!str_contains($dos['titulo'], '2 objetivos')) throw new RuntimeException("titulo: {$dos['titulo']}");
    // 500 + 500, formateado a la española
    if (!str_contains($dos['texto'], '1.000')) throw new RuntimeException("texto: {$dos['texto']}");
});

comprobar('«a un paso» solo a partir de la mitad', function () use ($mision) {
    $lejos = hoy_siguiente(base(['cerca' => $mision + ['progreso' => 1, 'ratio' => .1]]));
    igual('sobres.php', $lejos['url'], 'con 1 de 10 NO debe decir «a un paso»');

    $cerca = hoy_siguiente(base(['cerca' => $mision + ['progreso' => 9, 'ratio' => .9]]));
    igual('amarilla', $cerca['tono'], 'con 9 de 10 sí');
    if (!str_contains($cerca['titulo'], 'falta 1 ')) throw new RuntimeException("singular mal: {$cerca['titulo']}");

    $dos = hoy_siguiente(base(['cerca' => $mision + ['progreso' => 8, 'ratio' => .8]]));
    if (!str_contains($dos['titulo'], 'faltan 2')) throw new RuntimeException("plural mal: {$dos['titulo']}");
});

comprobar('colección completa: no dice «te faltan 0 fichas»', function () {
    $r = hoy_siguiente(base(['faltan' => 0]));
    igual('duelos.php', $r['url'], 'destino');
    if (str_contains($r['titulo'], 'faltan')) throw new RuntimeException("titulo: {$r['titulo']}");
});

comprobar('siempre devuelve una tarjeta completa', function () {
    // el peor caso posible: estado vacío del todo
    foreach ([[], base(), base(['faltan' => 0])] as $i => $estado) {
        $r = hoy_siguiente($estado);
        foreach (['tono', 'rotulo', 'titulo', 'texto', 'accion', 'url', 'icono'] as $campo) {
            if (empty($r[$campo])) throw new RuntimeException("caso $i sin «$campo»");
        }
    }
});

comprobar('el tono siempre existe como clase de la hoja', function () {
    // si se inventa un tono nuevo sin escribir su .es-… en hoy.css, la tarjeta
    // sale sin color y nadie se entera hasta verla
    $css = file_get_contents(__DIR__ . '/../../assets/css/hoy.css');
    $vistos = [];
    foreach ([
        base(['sobre_inicial' => true]),
        base(['duelo_en_juego' => ['id' => 1, 'rival' => 'X']]),
        base(['listas' => [['nombre' => 'M', 'recompensa_monedas' => 1]]]),
        base(['cerca' => ['nombre' => 'M', 'objetivo' => 2, 'progreso' => 1, 'ratio' => .5]]),
        base(),
        base(['faltan' => 0]),
    ] as $estado) {
        $vistos[hoy_siguiente($estado)['tono']] = true;
    }
    foreach (array_keys($vistos) as $tono) {
        // «brasa» es el estilo por defecto de .siguiente, no lleva modificador
        if ($tono === 'brasa') continue;
        if (!str_contains($css, ".siguiente.es-$tono")) {
            throw new RuntimeException("el tono «$tono» no tiene .siguiente.es-$tono en hoy.css");
        }
    }
});

echo $fallos === 0 ? "\nTodo correcto.\n\n" : "\n$fallos fallo(s).\n\n";
exit($fallos === 0 ? 0 : 1);
