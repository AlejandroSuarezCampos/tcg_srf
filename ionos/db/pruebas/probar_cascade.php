<?php
/**
 * Migración 023 sobre la copia DESECHABLE tcg_prueba (§8).
 *
 * Lo que hay que demostrar es que borrar un usuario deja la base LIMPIA, no
 * solo que el ALTER pasa. Se borra de verdad un usuario con duelos por los dos
 * lados y se cuenta lo que queda.
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

$p = new PDO("mysql:host=127.0.0.1;dbname=tcg_prueba;charset=utf8mb4", "root", "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$fallos = 0;
$ok = function ($m) { echo "  OK    $m\n"; };
$ko = function ($m) use (&$fallos) { echo "  FALLO $m\n"; $fallos++; };
$uno = function ($sql) use ($p) { return (int) $p->query($sql)->fetchColumn(); };

echo "=== 1) La regla quedo en CASCADE ===\n";
$regla = $p->query("
    SELECT r.DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS r
    JOIN information_schema.KEY_COLUMN_USAGE k
      ON k.CONSTRAINT_NAME = r.CONSTRAINT_NAME AND k.TABLE_SCHEMA = r.CONSTRAINT_SCHEMA
    WHERE r.CONSTRAINT_SCHEMA='tcg_prueba' AND k.TABLE_NAME='duelos'
      AND k.COLUMN_NAME='id_rival'
")->fetchColumn();
$regla === "CASCADE" ? $ok("id_rival -> CASCADE") : $ko("sigue en $regla");

echo "\n=== 2) id_rival sigue admitiendo NULL (una sala abierta no tiene rival) ===\n";
$nulo = $p->query("
    SELECT IS_NULLABLE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA='tcg_prueba' AND TABLE_NAME='duelos' AND COLUMN_NAME='id_rival'
")->fetchColumn();
$nulo === "YES" ? $ok("admite NULL") : $ko("ya no admite NULL: las salas abiertas se romperian");
// Y se comprueba de verdad, no solo por el esquema.
try {
    $p->exec("UPDATE duelos SET id_rival = NULL WHERE id_duelo = (SELECT MIN(id_duelo) FROM (SELECT id_duelo FROM duelos) x)");
    $ok("y deja dejarlo en NULL de hecho");
} catch (Throwable $e) { $ko("rechaza el NULL: " . $e->getMessage()); }

echo "\n=== 3) Borrar un usuario deja la base limpia ===\n";
$victima = 2;   // LuluLulez: tiene duelos como creador Y como rival
$comoRival   = $uno("SELECT COUNT(*) FROM duelos WHERE id_rival = $victima");
$comoCreador = $uno("SELECT COUNT(*) FROM duelos WHERE id_creador = $victima");
$colec       = $uno("SELECT COUNT(*) FROM coleccion WHERE id_usuario = $victima");
$mazos       = $uno("SELECT COUNT(*) FROM mazos WHERE id_usuario = $victima");
echo "        antes: $comoCreador duelos creados, $comoRival como rival, $colec cartas, $mazos mazos\n";

$p->exec("DELETE FROM usuarios WHERE id_usuario = $victima");
$ok("el DELETE pasa sin chocar con ninguna clave ajena");

$uno("SELECT COUNT(*) FROM duelos WHERE id_rival = $victima") === 0
    ? $ok("no queda ningun duelo apuntandole como rival") : $ko("quedan duelos como rival");
$uno("SELECT COUNT(*) FROM duelos WHERE id_creador = $victima") === 0
    ? $ok("ni como creador") : $ko("quedan duelos creados");
$uno("SELECT COUNT(*) FROM coleccion WHERE id_usuario = $victima") === 0
    ? $ok("su coleccion se fue con el") : $ko("quedan cartas huerfanas");
$uno("SELECT COUNT(*) FROM mazos WHERE id_usuario = $victima") === 0
    ? $ok("y sus mazos") : $ko("quedan mazos huerfanos");

echo "\n=== 4) No se llevo por delante nada de los demas ===\n";
$huerfanos = $uno("
    SELECT COUNT(*) FROM duelos d
    LEFT JOIN usuarios u ON u.id_usuario = d.id_creador
    WHERE u.id_usuario IS NULL
");
$huerfanos === 0 ? $ok("ningun duelo apunta a un creador que no existe") : $ko("$huerfanos duelos huerfanos");
$colHuerfana = $uno("
    SELECT COUNT(*) FROM coleccion c
    LEFT JOIN usuarios u ON u.id_usuario = c.id_usuario
    WHERE u.id_usuario IS NULL
");
$colHuerfana === 0 ? $ok("ninguna carta pertenece a un usuario que no existe") : $ko("$colHuerfana copias huerfanas");
$uno("SELECT COUNT(*) FROM usuarios") > 0 ? $ok("los demas usuarios siguen ahi") : $ko("se borraron todos");
$uno("SELECT COUNT(*) FROM cromos") > 0 ? $ok("el catalogo de cartas intacto") : $ko("se borraron cromos");
$uno("SELECT COUNT(*) FROM configuracion") > 0 ? $ok("el calibrado intacto") : $ko("se borro configuracion");

echo "\n" . ($fallos ? "$fallos FALLO(S)\n" : "Todo correcto.\n");
exit($fallos ? 1 : 0);
