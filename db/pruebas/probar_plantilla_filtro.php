<?php
/**
 * FILTRADO del modo «todas» de plantilla.php.
 *
 * Lo que se protege: que un filtro no enseñe de menos ni de más. Es la clase de
 * fallo que no se nota —no lanza nada, solo faltan cartas— y que en una pantalla
 * de colección se confunde con «todavía no la tengo».
 *
 * Sin base de datos: la función es pura.
 *
 *   php db/pruebas/probar_plantilla_filtro.php
 */
require_once __DIR__ . '/../../partials/plantilla_filtro.php';

$fallos = 0;
function comprobar(string $t, callable $fn): void {
    global $fallos;
    try { $fn(); echo "  ok  $t\n"; }
    catch (Throwable $e) { $fallos++; echo "FALLO  $t\n       " . $e->getMessage() . "\n"; }
}
function nombres(array $filas): array { return array_column($filas, 'nombre'); }
function igual(array $esperado, array $real, string $que = ''): void {
    if ($esperado !== $real) {
        throw new RuntimeException("$que: esperaba [" . implode(', ', $esperado) . "], vino [" . implode(', ', $real) . "]");
    }
}

/* Cinco cartas que cubren los casos que importan: acentos, dos equipos, dos
   expansiones, tres rarezas, y una que el jugador no tiene. */
$CROMOS = [
    ['id_cromo' => 1, 'nombre' => 'Asuto Inamori',  'id_equipo' => '2', 'id_expansion' => '3', 'id_rareza' => '5'],
    ['id_cromo' => 2, 'nombre' => 'Riccardo Di Rigo','id_equipo' => '2', 'id_expansion' => '3', 'id_rareza' => '3'],
    ['id_cromo' => 3, 'nombre' => 'Gonzalo Ruiz',   'id_equipo' => '3', 'id_expansion' => '3', 'id_rareza' => '1'],
    ['id_cromo' => 4, 'nombre' => 'Martín Óscar',   'id_equipo' => '3', 'id_expansion' => '6', 'id_rareza' => '5'],
    ['id_cromo' => 5, 'nombre' => 'Payo Aguao',     'id_equipo' => '2', 'id_expansion' => '6', 'id_rareza' => '7'],
];
/* Tiene las tres primeras; le faltan la 4 y la 5. */
$TENGO = [1 => true, 2 => true, 3 => true];

echo "\nFiltrado de la plantilla — modo «todas»\n\n";

comprobar('sin filtros no quita nada', function () use ($CROMOS, $TENGO) {
    igual(nombres($CROMOS), nombres(plantilla_filtrar($CROMOS, [], $TENGO)));
});

comprobar('busca por trozo de nombre, no solo por el principio', function () use ($CROMOS, $TENGO) {
    igual(['Riccardo Di Rigo'], nombres(plantilla_filtrar($CROMOS, ['nombre' => 'rigo'], $TENGO)));
});

comprobar('la búsqueda ignora mayúsculas y acentos en los dos sentidos', function () use ($CROMOS, $TENGO) {
    // quien escribe sin acentos encuentra lo acentuado…
    igual(['Martín Óscar'], nombres(plantilla_filtrar($CROMOS, ['nombre' => 'martin'], $TENGO)));
    igual(['Martín Óscar'], nombres(plantilla_filtrar($CROMOS, ['nombre' => 'oscar'], $TENGO)));
    // …y al revés
    igual(['Martín Óscar'], nombres(plantilla_filtrar($CROMOS, ['nombre' => 'MARTÍN'], $TENGO)));
});

comprobar('espacios sueltos alrededor de la búsqueda no vacían el listado', function () use ($CROMOS, $TENGO) {
    igual(['Payo Aguao'], nombres(plantilla_filtrar($CROMOS, ['nombre' => '  payo  '], $TENGO)));
});

comprobar('equipo y expansión filtran por id, no por nombre', function () use ($CROMOS, $TENGO) {
    igual(['Asuto Inamori', 'Riccardo Di Rigo', 'Payo Aguao'],
        nombres(plantilla_filtrar($CROMOS, ['id_equipo' => '2'], $TENGO)), 'equipo 2');
    igual(['Martín Óscar', 'Payo Aguao'],
        nombres(plantilla_filtrar($CROMOS, ['id_expansion' => '6'], $TENGO)), 'expansión 6');
});

comprobar('el id 0 y la cadena vacía NO se confunden', function () use ($CROMOS, $TENGO) {
    // `''` significa «todas»; un `'0'` sería un equipo de verdad que no existe
    igual(nombres($CROMOS), nombres(plantilla_filtrar($CROMOS, ['id_equipo' => ''], $TENGO)), 'vacío = todas');
    igual([], nombres(plantilla_filtrar($CROMOS, ['id_equipo' => '0'], $TENGO)), 'cero = ninguna');
});

comprobar('varias rarezas suman, no se pisan', function () use ($CROMOS, $TENGO) {
    igual(['Asuto Inamori', 'Martín Óscar'],
        nombres(plantilla_filtrar($CROMOS, ['rarezas' => ['5']], $TENGO)), 'una');
    igual(['Asuto Inamori', 'Gonzalo Ruiz', 'Martín Óscar', 'Payo Aguao'],
        nombres(plantilla_filtrar($CROMOS, ['rarezas' => ['5', '1', '7']], $TENGO)), 'tres');
    // los ids llegan de la URL como texto; comparar con === contra enteros fallaría
    igual(['Asuto Inamori', 'Martín Óscar'],
        nombres(plantilla_filtrar($CROMOS, ['rarezas' => [5]], $TENGO)), 'ids como enteros');
});

comprobar('«tengo» y «me falta» son complementarios y suman el total', function () use ($CROMOS, $TENGO) {
    $tengo = plantilla_filtrar($CROMOS, ['tengo' => 'tengo'], $TENGO);
    $falta = plantilla_filtrar($CROMOS, ['tengo' => 'falta'], $TENGO);
    igual(['Asuto Inamori', 'Riccardo Di Rigo', 'Gonzalo Ruiz'], nombres($tengo));
    igual(['Martín Óscar', 'Payo Aguao'], nombres($falta));
    if (count($tengo) + count($falta) !== count($CROMOS)) {
        throw new RuntimeException('tengo + falta no da el total');
    }
});

comprobar('los filtros se acumulan (Y, no O)', function () use ($CROMOS, $TENGO) {
    igual(['Payo Aguao'],
        nombres(plantilla_filtrar($CROMOS, ['id_equipo' => '2', 'tengo' => 'falta'], $TENGO)));
    igual([],
        nombres(plantilla_filtrar($CROMOS, ['id_equipo' => '3', 'rarezas' => ['7']], $TENGO)),
        'combinación imposible debe dar vacío, no todo');
});

comprobar('sin sesión (nada poseído) «me falta» las devuelve todas', function () use ($CROMOS) {
    igual(nombres($CROMOS), nombres(plantilla_filtrar($CROMOS, ['tengo' => 'falta'], [])));
    igual([], nombres(plantilla_filtrar($CROMOS, ['tengo' => 'tengo'], [])));
});

comprobar('conserva el orden de entrada', function () use ($CROMOS, $TENGO) {
    $r = plantilla_filtrar(array_reverse($CROMOS), [], $TENGO);
    igual(array_reverse(nombres($CROMOS)), nombres($r));
});

echo $fallos === 0 ? "\nTodo correcto.\n\n" : "\n$fallos fallo(s).\n\n";
exit($fallos === 0 ? 0 : 1);
