<?php
require_once __DIR__ . '/conexion.php';

function afirmar($cond, $mensaje) {
    if (!$cond) { fwrite(STDERR, "FALLO: {$mensaje}\n"); exit(1); }
    echo "OK: {$mensaje}\n";
}

afirmar($db->mapearPosicionJugador('DEL') === 'DC', 'DEL mapea a DC');
afirmar($db->mapearPosicionJugador('DEF') === 'DF', 'DEF mapea a DF');
afirmar($db->mapearPosicionJugador('MED') === 'MC', 'MED mapea a MC');
afirmar($db->mapearPosicionJugador('POR') === 'POR', 'POR mapea a POR');
afirmar($db->mapearPosicionJugador('inventado') === null, 'posición desconocida devuelve null');

afirmar($db->mapearAfinidadJugador('Fuego') === 2, 'Fuego -> 2');
afirmar($db->mapearAfinidadJugador('Aire') === 3, 'Aire -> Viento (3)');
afirmar($db->mapearAfinidadJugador('Montaña') === 1, 'Montaña -> 1');
afirmar($db->mapearAfinidadJugador('Bosque') === 4, 'Bosque -> 4');
afirmar($db->mapearAfinidadJugador(null) === 5, 'nulo -> no-afi (5)');
afirmar($db->mapearAfinidadJugador('Forest') === 5, 'valor no reconocido -> no-afi (5)');

$existentes = [
    ['id_equipo' => 13, 'nombre' => 'Instituto Zeus'],
    ['id_equipo' => 99, 'nombre' => 'Instituto Kikrwood'],
];
$exacto = $db->emparejarEquipo('Instituto Zeus', $existentes);
afirmar($exacto['estado'] === 'exacto' && $exacto['id_equipo'] === 13, 'match exacto encuentra Instituto Zeus');

$ambiguo = $db->emparejarEquipo('Instituto Kirkwood', $existentes);
afirmar($ambiguo['estado'] === 'ambiguo' && $ambiguo['candidato_db']['id_equipo'] === 99, 'Kirkwood/Kikrwood se detecta como ambiguo');

$nuevo = $db->emparejarEquipo('Equipo Totalmente Distinto FC', $existentes);
afirmar($nuevo['estado'] === 'nuevo', 'nombre sin parecido se marca como nuevo');

$fixture = [
    'config' => ['temporada' => '3'],
    'equipos' => [
        ['id' => 'eqA', 'nombre' => 'Equipo A', 'jugadores' => [
            ['nombre' => 'Goleador Top', 'goles' => 20, 'asistencias' => 1],
            ['nombre' => 'Suplente', 'goles' => 0, 'asistencias' => 0],
        ]],
        ['id' => 'eqB', 'nombre' => 'Equipo B', 'jugadores' => [
            ['nombre' => 'Jugador Medio', 'goles' => 5, 'asistencias' => 5],
        ]],
    ],
    'historial_temporadas' => [],
];
$rareza = $db->rankearRarezasImportacion($fixture);
afirmar(($rareza['eqA|Goleador Top'] ?? null) === 4, 'máximo goleador actual sube a épico');
afirmar(!isset($rareza['eqA|Suplente']), 'jugador sin goles no se promociona');

$statsEnt = $db->statsBaseImportacion('ENT', 5);
afirmar($statsEnt === ['ataque' => 0, 'defensa' => 0, 'tecnica' => 0], 'entrenador siempre 0/0/0');
$statsDC = $db->statsBaseImportacion('DC', 4);
afirmar($statsDC['ataque'] >= 50 && $statsDC['ataque'] <= 99, 'delantero épico tiene ataque alto');

echo "\nTodas las comprobaciones pasaron.\n";
