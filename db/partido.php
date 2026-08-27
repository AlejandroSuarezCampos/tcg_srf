<?php
/**
 * EL MOTOR DEL PARTIDO JUGABLE — todo funciones PURAS.
 *
 * Aquí no hay SQL, ni sesión, ni conexión: entra estado, sale estado. Esa es la
 * razón de que viva fuera de `consultas.php` (12.500 líneas) y de que se pueda
 * probar entero sin montar una base de datos.
 *
 * Quien persiste lo que aquí se calcula es `Tcg`, en métodos finos que solo
 * traducen entre la base de datos y estas funciones.
 */
class Partido {

	/* EL CICLO DE LAS CUATRO SENDAS. Cerrado: todos tienen a quién ganar y a
	   quién perder, no hay elemento neutral superior.

	   Ojo: NO se inventa aquí. El ciclo ya existía calibrado en el proyecto
	   (`ciclo_contra_afinidad_bonus` en `configuracion`); lo que añade este
	   motor es aplicarlo también dentro de un minijuego, con sus propios
	   factores. Si algún día se cambia el ciclo, hay que cambiarlo en los dos
	   sitios o el equipo y el minijuego dirán cosas distintas. */
	const CICLO = [
		"fuego"   => "bosque",
		"bosque"  => "viento",
		"viento"  => "montana",
		"montana" => "fuego",
	];

	const ELEMENTOS = ["fuego", "bosque", "viento", "montana"];

	/**
	 * Cuánto multiplica mi elemento al enfrentarse al suyo.
	 *
	 * Un elemento que no está en el ciclo ("no-afi", que existe en la tabla
	 * `afinidad` con id 5) devuelve 1.0: no gana ni pierde. Sin esta salida, una
	 * carta sin afinidad reventaría el cálculo de la jugada.
	 */
	public static function factorElemental($mio, $suyo) {
		if (!isset(self::CICLO[$mio]) || !isset(self::CICLO[$suyo])) return 1.0;
		if ($mio === $suyo)               return 1.15;   // resonancia
		if (self::CICLO[$mio] === $suyo)  return 1.4;    // ventaja de ciclo
		if (self::CICLO[$suyo] === $mio)  return 0.7;    // desventaja de ciclo
		return 1.0;                                      // relación no directa
	}

	/**
	 * Qué parte del rango de multiplicador te desbloquea tu estadística.
	 *
	 * Esta es la decisión de diseño central del motor: las stats NO se suman al
	 * resultado, definen a qué techo puedes aspirar. Con `stat_ref` a 80 —el
	 * listón que el propio diseño llama élite— una carta de 80 alcanza el techo
	 * completo y una de 40 se queda a medio camino AUNQUE juegue perfecto.
	 *
	 * `statRef <= 0` devuelve 1.0 en vez de dividir por cero: una configuración
	 * mal puesta debe dejar el juego jugable, no reventar la jugada.
	 */
	public static function factorStat($stat, $statRef) {
		if ($statRef <= 0) return 1.0;
		return max(0.0, min(1.0, (float) $stat / (float) $statRef));
	}

	/**
	 * Multiplicador de un minijuego DE EJECUCIÓN (trazo, memoria, ritmo, pulso).
	 *
	 *     mult = suelo + (techo − suelo) × factor_stat × rendimiento
	 *
	 * Habilidad y equipo se MULTIPLICAN, no se suman: hacen falta los dos. Con
	 * rendimiento 0 se cae al suelo, que es lo que vale no ejecutar — no un
	 * castigo extra.
	 *
	 * Los minijuegos DE LECTURA (elegir) no pasan por aquí: su multiplicador es
	 * fijo por opción y no lleva techo, porque leer la carta del rival es
	 * conocimiento y no músculo.
	 */
	public static function multiplicadorEjecucion($suelo, $techo, $factorStat, $rendimiento) {
		$rend = max(0.0, min(1.0, (float) $rendimiento));
		return (float) $suelo + ((float) $techo - (float) $suelo) * (float) $factorStat * $rend;
	}

	/**
	 * El valor final con el que una jugada se compara contra el bando contrario.
	 *
	 * `$topePct` es la RED DE SEGURIDAD (`habilidad_tope_pct`): el porcentaje
	 * máximo que la ejecución puede desviar el resultado respecto a lo que la
	 * estadística sola justifica. Nace generoso (100 = el valor puede ir del 0 %
	 * al 200 % de la stat) y solo se apreta si al medir el balance se desmadra.
	 * Existe para que apretar un tornillo NO exija tocar código.
	 */
	public static function valor($stat, $multiplicador, $factorElemental, $topePct) {
		$bruto = (float) $stat * (float) $multiplicador * (float) $factorElemental;
		$margen = max(0.0, (float) $topePct) / 100.0;
		$suelo  = (float) $stat * max(0.0, 1.0 - $margen);
		$techo  = (float) $stat * (1.0 + $margen);
		return max($suelo, min($techo, $bruto));
	}
}
