<?php
/**
 * DISPOSICIÓN de jugar.php — ¿puede el jugador salir al campo?
 *
 * Lo que se protege: que cada peldaño nombre el paso que DE VERDAD falta.
 * Decirle «monta una alineación» a quien ya tiene tres pero ninguna marcada
 * como titular le manda a crear una cuarta, que no arregla nada — y el fallo
 * no lanza ningún error, solo deja a alguien dando vueltas.
 *
 * Sin base de datos: la función es pura. Solo necesita Tcg::FORMACIONES.
 *
 *   php db/pruebas/probar_jugar_disposicion.php
 */
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
require_once __DIR__ . '/../consultas.php';
require_once __DIR__ . '/../../partials/jugar_disposicion.php';

$fallos = 0;
function comprobar(string $t, callable $fn): void {
    global $fallos;
    try { $fn(); echo "  ok  $t\n"; }
    catch (Throwable $e) { $fallos++; echo "FALLO  $t\n       " . $e->getMessage() . "\n"; }
}
function igual($esp, $real, string $que = ''): void {
    if ($esp !== $real) throw new RuntimeException("$que: esperaba " . var_export($esp, true) . ", vino " . var_export($real, true));
}
function contiene(string $pajar, string $aguja, string $que = ''): void {
    if (!str_contains($pajar, $aguja)) throw new RuntimeException("$que: «$pajar» no contiene «$aguja»");
}

const TAM = 11;
function mazo(string $nombre, int $cartas, bool $titular = false, string $formacion = '442'): array {
    return ['id_mazo' => 1, 'nombre' => $nombre, 'formacion' => $formacion,
            'titular' => $titular ? 1 : 0, 'cartas' => $cartas];
}

echo "\nDisposición para jugar — cuatro estados\n\n";

comprobar('sin ninguna alineación: está parado, y se dice', function () {
    $r = jugar_disposicion(null, [], TAM);
    igual('no', $r['estado'], 'estado');
    contiene($r['titulo'], 'No tienes ninguna', 'titulo');
    contiene($r['accion'], 'Montar', 'accion');
});

comprobar('con mazos pero sin titular: NO le manda a montar otro', function () {
    // el caso que motiva la prueba: si esto cae en la rama «no», el jugador
    // crea una cuarta alineación y sigue sin poder jugar
    $mazos = [mazo('A', 11), mazo('B', 11), mazo('C', 11)];
    $r = jugar_disposicion(null, $mazos, TAM);
    igual('casi', $r['estado'], 'estado');
    contiene($r['titulo'], 'titular', 'titulo');
    contiene($r['texto'], '3 alineaciones', 'debe decir cuántas tiene');
    contiene($r['accion'], 'Elegir', 'accion');
    if (str_contains($r['accion'], 'Montar')) throw new RuntimeException('le manda a montar otra');
});

comprobar('una sola alineación sin titular: singular correcto', function () {
    $r = jugar_disposicion(null, [mazo('A', 11)], TAM);
    contiene($r['texto'], '1 alineación', 'texto');
    if (str_contains($r['texto'], 'alineaciones')) throw new RuntimeException('plural donde va singular');
});

comprobar('titular marcada pero incompleta: NO dice «elige titular»', function () {
    /* EL CASO QUE SE ESCAPÓ LA PRIMERA VEZ. `obtenerMazoTitular()` devuelve
       null en DOS situaciones: sin titular marcado, y con titular marcado pero
       con menos de once cartas. Así que aquí llega $titular = null aunque el
       jugador SÍ haya elegido una. Si no se distingue, la pantalla le manda a
       «elegir titular» algo que ya hizo, y se queda dando vueltas. */
    $r = jugar_disposicion(null, [mazo('Principal', 7, true)], TAM);
    igual('casi', $r['estado'], 'estado');
    contiene($r['titulo'], 'Principal', 'debe nombrar la alineación');
    contiene($r['titulo'], 'incompleta', 'titulo');
    contiene($r['texto'], '7 de 11', 'debe decir el recuento');
    contiene($r['texto'], 'faltan 4', 'debe decir cuántas faltan');
    contiene($r['accion'], 'Completar', 'accion');
    if (str_contains($r['accion'], 'Elegir')) throw new RuntimeException('le manda a elegir una que ya eligió');
});

comprobar('a la que le falta 1 se le habla en singular', function () {
    $r = jugar_disposicion(null, [mazo('Principal', 10, true)], TAM);
    contiene($r['texto'], 'falta 1', 'texto');
    if (str_contains($r['texto'], 'faltan 1')) throw new RuntimeException('plural donde va singular');
});

comprobar('el recuento sale de la marcada, no del primer mazo de la lista', function () {
    $r = jugar_disposicion(null, [mazo('Otra', 11), mazo('Principal', 4, true)], TAM);
    contiene($r['texto'], '4 de 11', 'debe leer las de la marcada');
    contiene($r['titulo'], 'Principal', 'debe nombrar la marcada');
});

comprobar('once completo: listo, con su formación en claro', function () {
    $titular = ['nombre' => 'Principal', 'formacion' => '442'];
    $r = jugar_disposicion($titular, [mazo('Principal', 11, true)], TAM);
    igual('si', $r['estado'], 'estado');
    contiene($r['titulo'], '4-4-2', 'debe traducir la formación');
    if (str_contains($r['titulo'], '442')) throw new RuntimeException('enseña el código crudo');
});

comprobar('una formación desconocida no rompe la pantalla', function () {
    $titular = ['nombre' => 'Rara', 'formacion' => '999'];
    $r = jugar_disposicion($titular, [mazo('Rara', 11, true)], TAM);
    igual('si', $r['estado']);
    contiene($r['titulo'], '999', 'cae al código en crudo, pero no revienta');
});

comprobar('más cartas de las que pide tampoco bloquea', function () {
    $titular = ['nombre' => 'Principal', 'formacion' => '433'];
    igual('si', jugar_disposicion($titular, [mazo('Principal', 14, true)], TAM)['estado']);
});

comprobar('siempre devuelve los cinco campos', function () {
    $titular = ['nombre' => 'P', 'formacion' => '442'];
    $casos = [
        [null, []],
        [null, [mazo('A', 11)]],
        [null, [mazo('P', 3, true)]],
        [$titular, [mazo('P', 11, true)]],
    ];
    foreach ($casos as $i => [$t, $m]) {
        $r = jugar_disposicion($t, $m, TAM);
        foreach (['estado', 'rotulo', 'titulo', 'texto', 'accion'] as $c) {
            if (empty($r[$c])) throw new RuntimeException("caso $i sin «$c»");
        }
        if (!in_array($r['estado'], ['no', 'casi', 'si'], true)) {
            throw new RuntimeException("caso $i con estado inventado: {$r['estado']}");
        }
    }
});

comprobar('cada estado tiene su clase escrita en la hoja', function () {
    // si se inventa un estado sin su .es-… en jugar.css, la tarjeta sale sin
    // color y nadie se entera hasta verla
    $css = file_get_contents(__DIR__ . '/../../assets/css/jugar.css');
    foreach (['no', 'casi', 'si'] as $estado) {
        if (!str_contains($css, ".jg-listo.es-$estado")) {
            throw new RuntimeException("falta .jg-listo.es-$estado en jugar.css");
        }
    }
});

echo $fallos === 0 ? "\nTodo correcto.\n\n" : "\n$fallos fallo(s).\n\n";
exit($fallos === 0 ? 0 : 1);
