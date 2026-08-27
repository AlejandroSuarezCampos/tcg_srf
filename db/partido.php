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
}
