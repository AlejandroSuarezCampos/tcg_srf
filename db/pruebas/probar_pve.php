<?php
/**
 * Las CADENAS (PvE) no debían cambiar en el Paso 3: no tienen minijuegos, así
 * que no hay nada que esperar y se siguen resolviendo de una vez. Esta prueba
 * está para demostrar que la rama de PvE sigue intacta, porque el cambio pasa
 * justo por dentro de resolverDuelo() y era fácil arrastrarla sin querer.
 *
 * Sobre la copia DESECHABLE tcg_prueba (§8).
 */
require __DIR__ . "/../consultas.php";

$p = new PDO("mysql:host=127.0.0.1;dbname=tcg_prueba;charset=utf8mb4", "root", "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = new Tcg("127.0.0.1", 3306, "tcg_prueba", "root", "");

$fallos = 0;
$ok = function ($m) { echo "  OK    $m\n"; };
$ko = function ($m) use (&$fallos) { echo "  FALLO $m\n"; $fallos++; };

// Un nodo cualquiera de la primera cadena.
$nodo = (int) $p->query("SELECT id_nodo FROM cadena_nodos ORDER BY id_nodo LIMIT 1")->fetchColumn();
if (!$nodo) { echo "No hay cadenas en la base; nada que probar.\n"; exit(0); }

$r = $db->crearPartidoCadena(9, $nodo, "medio");
if (empty($r["ok"])) { echo "crearPartidoCadena: " . ($r["error"] ?? "?") . "\n"; exit(1); }
$id = (int) $r["id_duelo"];

$antes = (int) $p->query("SELECT monedas FROM usuarios WHERE id_usuario = 9")->fetchColumn();

$db->elegirAumento($id, 9, 1);
$db->cerrarFaseAumento($id);
$db->resolverDuelo($id);

$s = $p->prepare("SELECT * FROM duelos WHERE id_duelo = :d");
$s->execute([":d" => $id]);
$d = $s->fetch(PDO::FETCH_ASSOC);
$despues = (int) $p->query("SELECT monedas FROM usuarios WHERE id_usuario = 9")->fetchColumn();

echo "=== Un partido de cadena se resuelve de una vez ===\n";
$d["estado"] === "resuelto" ? $ok("queda resuelto, no en_juego") : $ko("estado " . $d["estado"]);
$d["id_ganador"] !== null ? $ok("con ganador escrito ya") : $ko("sin ganador");
$d["dificultad"] !== null ? $ok("marcado como PvE") : $ko("no es PvE");
echo "        marcador {$d['goles_creador']}-{$d['goles_rival']}, rango " . ($d["rango"] ?? "—") . "\n";

$gano = (int) $d["id_ganador"] === 9;
if ($gano) {
    $d["rango"] !== null ? $ok("rango calculado (de el sale el botin)") : $ko("victoria sin rango");
    $despues > $antes ? $ok("y el botin se entrego en el acto (+" . ($despues - $antes) . ")") : $ko("gano y no cobro nada");
} else {
    $ok("derrota (no toca botin; el reparto solo se prueba al ganar)");
}

// Y que el sondeo no lo rompa: una cadena también se mira con estadoPartido.
$e = $db->estadoPartido($id, 9);
!empty($e["ok"]) ? $ok("el sondeo sigue leyendo el partido de cadena") : $ko("el sondeo lo rechaza: " . ($e["error"] ?? "?"));
!empty($e["decidido"]) ? $ok("y lo da por decidido desde el primer sondeo") : $ko("dice que no esta decidido");

echo "\n" . ($fallos ? "$fallos FALLO(S)\n" : "Todo correcto.\n");
exit($fallos ? 1 : 0);
