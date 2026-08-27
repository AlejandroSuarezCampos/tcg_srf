<?php
/**
 * 004 — REPARACIÓN DE CODIFICACIÓN (utilidad puntual, no una migración SQL)
 * =============================================================================
 * Repara el texto que se guardó corrompido al aplicar una migración en Windows
 * SIN el flag --default-character-set=utf8mb4.
 *
 * QUÉ PASÓ: `mysql.exe < fichero.sql` lee el .sql con la codepage de la consola
 * (CP850), no como UTF-8. Los bytes UTF-8 de "ñ" (C3 B1) se interpretan como
 * dos caracteres CP850 ("├" y "▒") y se vuelven a guardar en UTF-8. Resultado:
 * "Montaña" queda como "Monta├▒a".
 *
 * CÓMO SE REVIERTE: deshaciendo exactamente esa transformación, es decir
 * convirtiendo la cadena de UTF-8 a CP850, lo que devuelve los bytes originales.
 * Es reversible sin pérdida porque la corrupción es determinista.
 *
 * ES IDEMPOTENTE: solo toca cadenas que contienen la firma de la corrupción
 * (caracteres de dibujo de caja U+2500–U+259F), y solo escribe si el resultado
 * es UTF-8 válido. Ejecutarlo dos veces no rompe nada.
 *
 * Ejecutar con:
 *   C:\xampp\php\php.exe db/migraciones/004_reparar_codificacion.php
 *
 * Añadir --aplicar para que escriba; sin ese argumento solo informa.
 */

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

$aplicar = in_array('--aplicar', $argv, true);

$pdo = new PDO("mysql:host=localhost;port=3306;dbname=tcg;charset=utf8mb4", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/** Columnas de texto que pueden haberse sembrado desde una migración. */
$objetivos = [
    'rasgos'        => ['clave' => 'id_rasgo',  'columnas' => ['nombre', 'descripcion']],
    'misiones'      => ['clave' => 'id_mision', 'columnas' => ['nombre', 'descripcion']],
    'configuracion' => ['clave' => 'clave',     'columnas' => ['descripcion']],
];

/** ¿Tiene la firma de la corrupción? Caracteres de dibujo de caja. */
function estaCorrupto($texto) {
    return $texto !== null && preg_match('/[\x{2500}-\x{259F}]/u', $texto) === 1;
}

/** Deshace la corrupción. Devuelve null si no se puede revertir con seguridad. */
function reparar($texto) {
    $bytes = @iconv('UTF-8', 'CP850//IGNORE', $texto);
    if ($bytes === false || $bytes === '') { return null; }
    // El resultado tiene que ser UTF-8 válido; si no, no era esta corrupción.
    if (!mb_check_encoding($bytes, 'UTF-8')) { return null; }
    return $bytes;
}

$totalCorruptos = 0;
$totalReparados = 0;

foreach ($objetivos as $tabla => $info) {
    $columnas = implode(', ', array_map(fn($c) => "`$c`", $info['columnas']));
    $filas = $pdo->query("SELECT `{$info['clave']}` AS pk, $columnas FROM `$tabla`")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($filas as $fila) {
        foreach ($info['columnas'] as $col) {
            $valor = $fila[$col];
            if (!estaCorrupto($valor)) { continue; }

            $totalCorruptos++;
            $arreglado = reparar($valor);

            if ($arreglado === null) {
                echo "  [!] $tabla.$col ({$fila['pk']}): corrupto pero NO reparable con seguridad, se deja como está\n";
                continue;
            }

            echo "  $tabla.$col ({$fila['pk']}):\n";
            echo "      antes:   $valor\n";
            echo "      después: $arreglado\n";

            if ($aplicar) {
                $stmt = $pdo->prepare("UPDATE `$tabla` SET `$col` = :v WHERE `{$info['clave']}` = :pk");
                $stmt->execute([':v' => $arreglado, ':pk' => $fila['pk']]);
                $totalReparados++;
            }
        }
    }
}

echo "\n";
if ($totalCorruptos === 0) {
    echo "Nada corrupto. No hay que hacer nada.\n";
} elseif ($aplicar) {
    echo "Reparadas $totalReparados de $totalCorruptos cadenas corruptas.\n";
} else {
    echo "Encontradas $totalCorruptos cadenas corruptas. Vuelve a ejecutar con --aplicar para arreglarlas.\n";
}
