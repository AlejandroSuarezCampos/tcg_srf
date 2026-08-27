<?php
/**
 * CATÁLOGO DE MINIJUEGOS — solo datos.
 *
 * El motor (`db/partido.php`) no sabe jugar a ninguno en concreto: lee de aquí
 * qué ofrecer y con qué consecuencia, de modo que añadir el minijuego número 40
 * sea añadir un array y no tocar el motor.
 *
 * ---------------------------------------------------------------------------
 * DOS TIPOS, y se comportan distinto A PROPÓSITO:
 *
 *   "ejecucion"  Trazo, memoria, ritmo, pulso. Produce un `rendimiento` de 0 a 1
 *                y el multiplicador sale de `suelo + (techo − suelo) × factor_stat
 *                × rendimiento`. LLEVA TECHO POR ESTADÍSTICA: la carta decide a
 *                qué parte del rango puedes aspirar, jugando perfecto o no.
 *
 *   "lectura"    Elegir. No hay ejecución: aciertas la carta del rival o no. El
 *                multiplicador es FIJO por opción y NO lleva techo, porque leer
 *                al rival es conocimiento y no músculo.
 *
 * Y se autoequilibra: los de lectura topan sobre ×1.8 y los de ejecución llegan
 * a ×2.5, así que los premios gordos están donde las estadísticas mandan.
 *
 * ---------------------------------------------------------------------------
 * `mult_rival` es cómo se expresan los "−10 % de Defensa del portero" del
 * diseño: multiplica el valor del OTRO bando. 1.0 = no le toca nada.
 *
 * `segura` marca la opción conservadora, la que se aplica sola si se agota el
 * plazo. NUNCA puede ser la de más premio: quien no llega a tiempo no puede
 * salir beneficiado.
 *
 * `usa_elemento` activa el Ciclo de las Cuatro Sendas sobre esta entrada
 * (ventaja ×1.4, no directa ×1.0, desventaja ×0.7, resonancia ×1.15).
 */

return [

	/* =====================================================================
	   TRIPLE ESCUADRA — el minijuego de lectura de tiro.
	   Tres sectores de portería. El premio y el riesgo van juntos: la escuadra
	   derecha pega más fuerte pero es la zona que todo portero de élite vigila.
	   El centro raso no pega más, pero le quita ventaja al portero que depende
	   de técnicas aéreas.
	   ===================================================================== */
	"triple_escuadra" => [
		"nombre"       => "Triple Escuadra",
		"familia"      => "tiro",
		"tipo"         => "lectura",
		"primitiva"    => "zona",
		"stat_techo"   => "ataque",
		"usa_elemento" => false,
		"plazo_seg"    => 8,
		"titulo"       => "Eliges dónde va",
		"enunciado"    => "{jugador} encara la portería. ¿Dónde la pones?",
		"opciones" => [
			[
				"clave" => "escuadra_izq", "nombre" => "Escuadra izquierda",
				"pista" => "Roza el travesaño. Los porteros la vigilan por costumbre.",
				"mult" => 1.2, "mult_rival" => 1.05, "segura" => false,
			],
			[
				"clave" => "centro_raso", "nombre" => "Centro raso",
				"pista" => "A ras de suelo. Le obliga a agacharse y pierde su ventaja.",
				"mult" => 1.0, "mult_rival" => 0.90, "segura" => true,
			],
			[
				"clave" => "escuadra_der", "nombre" => "Escuadra derecha",
				"pista" => "La zona dorada. Máximo premio, máxima vigilancia.",
				"mult" => 1.5, "mult_rival" => 1.15, "segura" => false,
			],
		],
	],

	/* =====================================================================
	   ESPIRAL DE FUEGO — el minijuego de ejecución de tiro.
	   Se dibuja una espiral y hay que seguirla de un trazo. La figura se genera
	   cada vez (vueltas, sentido, amplitud), así que no se puede memorizar.
	   ===================================================================== */
	"espiral_fuego" => [
		"nombre"       => "Espiral de Fuego",
		"familia"      => "tiro",
		"tipo"         => "ejecucion",
		"figura"       => "espiral",
		"stat_techo"   => "tecnica",
		"usa_elemento" => true,
		"plazo_seg"    => 6,
		"suelo"        => 0.8,
		"techo"        => 1.8,
		"tolerancia"   => 42.0,
		"titulo"       => "Espiral de Fuego",
		"enunciado"    => "Sigue la espiral sin levantar el dedo.",
	],

	/* =====================================================================
	   AMAGO Y SALIDA — el minijuego de lectura de regate.
	   Recupera la mecánica de los duelos de regate clásicos: la derecha es más
	   contundente pero arriesgada, la izquierda da control pero avanza menos.
	   ===================================================================== */
	"amago_salida" => [
		"nombre"       => "Amago y Salida",
		"familia"      => "regate",
		"tipo"         => "lectura",
		"primitiva"    => "eleccion",
		"stat_techo"   => "tecnica",
		"usa_elemento" => false,
		"plazo_seg"    => 6,
		"titulo"       => "Le encaras",
		"enunciado"    => "{jugador} tiene el balón y a {rival} delante. ¿Por dónde sales?",
		"opciones" => [
			[
				"clave" => "izquierda", "nombre" => "Salida por izquierda",
				"pista" => "Mantienes el control pase lo que pase.",
				"mult" => 1.1, "mult_rival" => 1.0, "segura" => true,
			],
			[
				"clave" => "derecha", "nombre" => "Salida por derecha",
				"pista" => "El amago más directo. Si te lo lee, te la quita.",
				"mult" => 1.5, "mult_rival" => 1.2, "segura" => false,
			],
		],
	],

	/* =====================================================================
	   VUELO DEL GUARDIÁN — el minijuego de ejecución de portería.
	   La estirada se traza como un arco hacia la esquina. Misma primitiva que la
	   espiral, distinta figura y distinta estadística: aquí manda la Defensa.
	   ===================================================================== */
	"vuelo_guardian" => [
		"nombre"       => "Vuelo del Guardián",
		"familia"      => "porteria",
		"tipo"         => "ejecucion",
		"figura"       => "arco",
		"stat_techo"   => "defensa",
		"usa_elemento" => true,
		"plazo_seg"    => 5,
		"suelo"        => 0.8,
		"techo"        => 1.8,
		"tolerancia"   => 48.0,
		"titulo"       => "Vuelo del Guardián",
		"enunciado"    => "Estírate hacia donde va el balón.",
	],

	/* =====================================================================
	   CARGA O SEGADA — el minijuego de lectura del duelo físico.
	   Homenaje al doble botón de defensa clásico. Carga es segura y compara
	   fuerza; Segada puede quitar el balón limpio, pero se paga con falta.
	   ===================================================================== */
	"carga_segada" => [
		"nombre"       => "Carga o Segada",
		"familia"      => "carga",
		"tipo"         => "lectura",
		"primitiva"    => "eleccion",
		"stat_techo"   => "defensa",
		"usa_elemento" => false,
		"plazo_seg"    => 5,
		"titulo"       => "Te viene encima",
		"enunciado"    => "{rival} avanza con el balón. ¿Cómo lo paras?",
		"opciones" => [
			[
				"clave" => "carga", "nombre" => "Carga",
				"pista" => "Hombro con hombro. Si pierdes el choque, no pasa nada malo.",
				"mult" => 1.15, "mult_rival" => 1.0, "segura" => true,
			],
			[
				"clave" => "segada", "nombre" => "Segada",
				"pista" => "Al balón, a ras de suelo. Si no llegas limpio, es falta.",
				"mult" => 1.5, "mult_rival" => 1.2, "segura" => false,
			],
		],
	],
];
