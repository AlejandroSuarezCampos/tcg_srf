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

	/**
	 * Reparte una polilínea en EXACTAMENTE $n puntos equidistantes POR LONGITUD
	 * DE ARCO, no por índice ni por tiempo.
	 *
	 * ⚠️ AQUÍ VIVE LA JUSTICIA DE HARDWARE, y por eso no se puede tocar a la
	 * ligera. Si se repartiera por índice, un móvil que muestrea 20 veces y otro
	 * que muestrea 120 compararían puntos distintos del mismo dedo y sacarían
	 * notas distintas: tener peor teléfono sería tener peor balance. Repartiendo
	 * por longitud recorrida, los dos describen la misma curva y puntúan igual.
	 *
	 * Un trazo sin longitud (el dedo no se movió) no tiene curva que repartir y
	 * devuelve el array vacío; quien llama lo interpreta como no haber ejecutado.
	 */
	public static function remuestrear(array $puntos, $n) {
		$n = max(2, (int) $n);
		if (count($puntos) < 2) return [];

		$acum  = [0.0];
		$total = 0.0;
		for ($i = 1; $i < count($puntos); $i++) {
			$dx = (float) $puntos[$i]["x"] - (float) $puntos[$i - 1]["x"];
			$dy = (float) $puntos[$i]["y"] - (float) $puntos[$i - 1]["y"];
			$total += sqrt($dx * $dx + $dy * $dy);
			$acum[$i] = $total;
		}
		if ($total <= 0.0) return [];

		$salida = [];
		$j = 1;
		for ($k = 0; $k < $n; $k++) {
			$objetivo = $total * $k / ($n - 1);
			while ($j < count($acum) - 1 && $acum[$j] < $objetivo) { $j++; }
			$tramo = $acum[$j] - $acum[$j - 1];
			$t = $tramo > 0 ? ($objetivo - $acum[$j - 1]) / $tramo : 0.0;
			$salida[] = [
				"x" => (float) $puntos[$j - 1]["x"] + ((float) $puntos[$j]["x"] - (float) $puntos[$j - 1]["x"]) * $t,
				"y" => (float) $puntos[$j - 1]["y"] + ((float) $puntos[$j]["y"] - (float) $puntos[$j - 1]["y"]) * $t,
			];
		}
		return $salida;
	}

	/**
	 * Qué tan bien ha seguido el jugador la figura que se le pedía: 0.0 a 1.0.
	 *
	 * Se remuestrean las dos curvas al mismo número de puntos y se mide la
	 * distancia par a par. Cada par aporta `1 − distancia/tolerancia`, con suelo
	 * en 0, y el rendimiento es la media. Un trazo clavado da 1; uno desviado la
	 * mitad de la tolerancia da cerca de 0,5; uno fuera de tolerancia da 0.
	 *
	 * `$tolerancia` la declara el minijuego y NO puede depender del nivel visual
	 * del dispositivo: es una regla dura del diseño.
	 */
	public static function rendimientoTrazo(array $ideal, array $trazo, $tolerancia, $n = 32) {
		if ((float) $tolerancia <= 0.0) return 0.0;
		$a = self::remuestrear($ideal, $n);
		$b = self::remuestrear($trazo, $n);
		if (!$a || !$b) return 0.0;

		$suma = 0.0;
		for ($i = 0; $i < count($a); $i++) {
			$dx = $a[$i]["x"] - $b[$i]["x"];
			$dy = $a[$i]["y"] - $b[$i]["y"];
			$d  = sqrt($dx * $dx + $dy * $dy);
			$suma += max(0.0, 1.0 - $d / (float) $tolerancia);
		}
		return $suma / count($a);
	}

	/* EL CAMPO SON TRES ZONAS. El balón vive en una y avanza o retrocede. No son
	   coordenadas: son estados, y el orden del array ES el orden del campo. */
	const ZONAS = ["salida", "creacion", "area"];

	const MINUTOS = 90;

	/**
	 * QUÉ PUEDES HACER CON EL BALÓN, según dónde esté.
	 *
	 * Esta tabla es el corazón del juego: es lo que convierte "ver un partido"
	 * en "jugar un partido". El POSEEDOR elige una de estas; el rival no elige,
	 * recibe el minijuego de la familia `defensor` como respuesta. Los dos
	 * juegan en cada jugada, así que el usuario interviene en todas.
	 *
	 *   efecto  "avanza" = sube una zona   ·  "area" = salta directo al área
	 *           "gol"    = si gana la jugada, es gol
	 */
	const ACCIONES = [
		"salida" => [
			"pase_corto" => [
				"nombre" => "Pase en corto", "efecto" => "avanza",
				"atacante" => "regate", "defensor" => "carga",
			],
			"balon_largo" => [
				"nombre" => "Balón largo", "efecto" => "area",
				"atacante" => "tiro", "defensor" => "defensa",
			],
		],
		"creacion" => [
			"conducir" => [
				"nombre" => "Conducir", "efecto" => "avanza",
				"atacante" => "regate", "defensor" => "carga",
			],
			"pase_filtrado" => [
				"nombre" => "Pase filtrado", "efecto" => "avanza",
				"atacante" => "regate", "defensor" => "defensa",
			],
			"tiro_lejano" => [
				"nombre" => "Tiro lejano", "efecto" => "gol",
				"atacante" => "tiro", "defensor" => "porteria",
			],
		],
		"area" => [
			"tirar" => [
				"nombre" => "Tirar", "efecto" => "gol",
				"atacante" => "tiro", "defensor" => "porteria",
			],
			"encarar" => [
				"nombre" => "Encarar al portero", "efecto" => "gol",
				"atacante" => "regate", "defensor" => "porteria",
			],
			"centro_atras" => [
				"nombre" => "Centro atrás", "efecto" => "gol",
				"atacante" => "regate", "defensor" => "defensa",
			],
		],
	];

	public static function accionesDe($zona) {
		return self::ACCIONES[$zona] ?? [];
	}

	/**
	 * Quién gana la jugada.
	 *
	 * El EMPATE lo gana la defensa a propósito: quien tiene que romper una
	 * situación es el que ataca, y así no hace falta un desempate aleatorio que
	 * volvería a meter azar donde este motor lo acaba de quitar.
	 */
	public static function desenlace($ofensivo, $defensivo, $efecto) {
		if ((float) $ofensivo <= (float) $defensivo) return "recupera";
		return in_array($efecto, ["gol", "area"], true) ? $efecto : "avanza";
	}

	/**
	 * Dónde queda el balón después de la jugada.
	 *
	 * Tras un gol se saca de centro, y eso aquí es "salida": quien lo recibe
	 * arranca su propia jugada desde atrás. Perder el balón hace lo mismo.
	 */
	public static function zonaTras($zona, $desenlace) {
		if ($desenlace === "recupera" || $desenlace === "gol") return "salida";
		if ($desenlace === "area") return "area";
		$i = array_search($zona, self::ZONAS, true);
		if ($i === false) return "salida";
		return self::ZONAS[min($i + 1, count(self::ZONAS) - 1)];
	}

	/**
	 * En qué minuto de partido cae la jugada número $numero de $total.
	 *
	 * El reloj REAL corre entre jugadas y se congela dentro de ellas; este
	 * método solo reparte los 90 minutos más el descuento a lo largo de las
	 * jugadas, para que el marcador de minuto avance a saltos coherentes. Bajar
	 * `partido_jugadas_num` reparte solo, sin tocar nada más.
	 */
	public static function minutoDeJugada($numero, $total, $descuento) {
		if ((int) $total <= 0) return 0;
		return (int) round((self::MINUTOS + (int) $descuento) * (int) $numero / (int) $total);
	}

	const FAMILIAS = ["tiro", "regate", "defensa", "porteria", "carga"];

	/* Familias que, si se quedan sin entradas, retroceden a otra en vez de dejar
	   una acción sin minijuego. Es el andamio que permite arrancar con 5
	   semillas en lugar de con los 101: cuando el catálogo de defensa exista,
	   este retroceso deja de dispararse solo y se puede borrar la línea. */
	const FAMILIA_SUPLENTE = ["defensa" => "carga"];

	private static $cacheCatalogo = null;

	public static function catalogo() {
		if (self::$cacheCatalogo === null) {
			self::$cacheCatalogo = require __DIR__ . "/minijuegos.php";
		}
		return self::$cacheCatalogo;
	}

	public static function minijuegosDeFamilia($familia) {
		$claves = [];
		foreach (self::catalogo() as $clave => $mj) {
			if (($mj["familia"] ?? "") === $familia) { $claves[] = $clave; }
		}
		if (!$claves && isset(self::FAMILIA_SUPLENTE[$familia])) {
			return self::minijuegosDeFamilia(self::FAMILIA_SUPLENTE[$familia]);
		}
		return $claves;
	}

	public static function opcionSegura(array $mj) {
		foreach ($mj["opciones"] ?? [] as $o) {
			if (!empty($o["segura"])) return $o["clave"];
		}
		return "";
	}

	public static function opcionDe(array $mj, $clave) {
		foreach ($mj["opciones"] ?? [] as $o) {
			if ($o["clave"] === $clave) return $o;
		}
		return null;
	}

	/**
	 * Cómo de bien juega la CPU su minijuego.
	 *
	 * DETERMINISTA por (duelo, jugada, lado) a propósito. Con azar real, un
	 * reintento de la misma petición —que pasa constantemente con el sondeo— daría
	 * un rendimiento distinto y el mismo partido podría resolverse de dos formas.
	 *
	 * `$peso` viene de `pve_pesos_ia_*`, que ya existe y va de 0 en Fácil a 0.85
	 * en Extremo. Alrededor de él se abre una banda de ±0.15, así que un Fácil
	 * falla trazos como una persona nerviosa y un Extremo casi no falla, pero
	 * ninguno es una máquina perfecta ni un pelele constante.
	 */
	public static function rendimientoCpu($peso, $valorSorteo, $numero, $lado) {
		$s = abs(crc32("cpu|" . (string) $valorSorteo . "|" . (string) $numero . "|" . $lado));
		$ruido = (($s % 1000) / 1000.0 - 0.5) * 0.30;    // ±0.15
		return max(0.0, min(1.0, (float) $peso + $ruido));
	}

	/**
	 * Qué opción elige la CPU en un minijuego de lectura.
	 *
	 * Cuanto mejor es la CPU, más a menudo se aparta de la opción segura para
	 * ir a por la de más premio. Una CPU floja se queda en lo conservador, que
	 * es exactamente lo que hace un rival de dificultad baja.
	 */
	public static function opcionCpu(array $mj, $peso, $valorSorteo, $numero) {
		$opciones = $mj["opciones"] ?? [];
		if (!$opciones) return "";
		$segura = self::opcionSegura($mj);

		$s = abs(crc32("cpuop|" . (string) $valorSorteo . "|" . (string) $numero));
		if (($s % 1000) / 1000.0 > (float) $peso) return $segura;

		$arriesgadas = [];
		foreach ($opciones as $o) { if (empty($o["segura"])) { $arriesgadas[] = $o["clave"]; } }
		if (!$arriesgadas) return $segura;
		return $arriesgadas[($s >> 7) % count($arriesgadas)];
	}

	/**
	 * Comprueba que el catálogo es sano. Devuelve la lista de errores en texto;
	 * array vacío significa que está bien.
	 *
	 * Existe porque un catálogo roto tiene que DELATARSE en la suite, no fallar
	 * en silencio a mitad de un partido con cartas apostadas.
	 */
	public static function validarCatalogo(array $catalogo) {
		$errores = [];
		foreach ($catalogo as $clave => $mj) {
			$falta = [];
			foreach (["nombre", "familia", "tipo", "titulo", "enunciado", "stat_techo", "plazo_seg"] as $k) {
				if (!isset($mj[$k])) { $falta[] = $k; }
			}
			if ($falta) { $errores[] = "$clave: falta " . implode(", ", $falta); continue; }

			if (!in_array($mj["familia"], self::FAMILIAS, true)) {
				$errores[] = "$clave: familia desconocida '{$mj["familia"]}'";
			}
			if (!in_array($mj["stat_techo"], ["ataque", "defensa", "tecnica"], true)) {
				$errores[] = "$clave: stat_techo desconocida '{$mj["stat_techo"]}'";
			}

			if ($mj["tipo"] === "ejecucion") {
				foreach (["suelo", "techo", "tolerancia", "figura"] as $k) {
					if (!isset($mj[$k])) { $errores[] = "$clave: ejecución sin $k"; }
				}
				if (isset($mj["suelo"], $mj["techo"]) && $mj["techo"] <= $mj["suelo"]) {
					$errores[] = "$clave: el techo no supera al suelo";
				}
				if (isset($mj["tolerancia"]) && $mj["tolerancia"] <= 0) {
					$errores[] = "$clave: tolerancia no positiva";
				}
			} elseif ($mj["tipo"] === "lectura") {
				$opciones = $mj["opciones"] ?? [];
				if (count($opciones) < 2) { $errores[] = "$clave: lectura con menos de 2 opciones"; }
				$seguras = 0;
				$mejor = null;
				foreach ($opciones as $o) {
					foreach (["clave", "nombre", "pista", "mult", "mult_rival"] as $k) {
						if (!isset($o[$k])) { $errores[] = "$clave: opción sin $k"; }
					}
					if (!empty($o["segura"])) { $seguras++; }
					if ($mejor === null || ($o["mult"] ?? 0) > $mejor["mult"]) { $mejor = $o; }
				}
				if ($seguras !== 1) { $errores[] = "$clave: tiene $seguras opciones seguras, debe tener 1"; }
				/* REGLA DURA: la segura NUNCA puede ser la de más premio. Quien no
				   llega a tiempo no puede salir beneficiado por no decidir. */
				if ($mejor !== null && !empty($mejor["segura"]) && count($opciones) > 1) {
					$errores[] = "$clave: la opción segura es también la de más premio";
				}
			} else {
				$errores[] = "$clave: tipo desconocido '{$mj["tipo"]}'";
			}
		}
		return $errores;
	}

	/**
	 * Elige un minijuego concreto de una familia, de forma DETERMINISTA.
	 *
	 * Determinista y no aleatoria porque los dos navegadores sondean a la vez: si
	 * cada petición sorteara, dos sondeos simultáneos podrían fijar minijuegos
	 * distintos para la misma jugada.
	 */
	public static function elegirDeFamilia($familia, $semilla) {
		$claves = self::minijuegosDeFamilia($familia);
		if (!$claves) return null;
		$i = (int) (abs(crc32((string) $familia . "|" . (string) $semilla)) % count($claves));
		return $claves[$i];
	}

	/**
	 * La figura que hay que trazar, generada proceduralmente.
	 *
	 * Se genera cada vez (vueltas, sentido, amplitud) para que no se pueda
	 * memorizar un patrón fijo, pero es DETERMINISTA por (duelo, jugada) para
	 * que el servidor pueda recalcular exactamente la misma figura que vio el
	 * cliente. Sin ese determinismo, el servidor no podría puntuar el trazo.
	 *
	 * El lienzo es siempre 0..1000 en las dos dimensiones; el cliente escala.
	 */
	public static function figuraIdeal($tipo, $valorSorteo, $numero, $n = 60) {
		$s = abs(crc32($tipo . "|" . (string) $valorSorteo . "|" . (string) $numero));
		$vueltas = 2 + ($s % 2);                 // 2 o 3 vueltas
		$sentido = ($s >> 3) % 2 ? 1 : -1;
		$amplitud = 300 + (($s >> 5) % 120);

		$p = [];
		for ($i = 0; $i < $n; $i++) {
			$u = $i / ($n - 1);
			if ($tipo === "arco") {
				$a = M_PI * $u;
				$p[] = ["x" => 500 + $sentido * $amplitud * cos($a), "y" => 850 - $amplitud * sin($a)];
			} else {   // espiral
				$a = $sentido * 2 * M_PI * $vueltas * $u;
				$r = $amplitud * (1 - 0.75 * $u);
				$p[] = ["x" => 500 + $r * cos($a), "y" => 850 - 700 * $u + $r * sin($a) * 0.35];
			}
		}
		return $p;
	}

	/**
	 * Comprobaciones baratas de plausibilidad del trazo, ANTES de puntuarlo.
	 *
	 * No sustituyen al recálculo del rendimiento (eso es lo que de verdad impide
	 * hacer trampas), pero cierran dos atajos obvios: mandar un trazo que no
	 * empieza donde estaba el balón, y mandar uno fabricado antes de que la
	 * jugada existiera.
	 */
	public static function trazoPlausible(array $trazo, array $ideal, $abiertaTs, $margen = 180.0) {
		if (count($trazo) < 4 || !$ideal) return false;

		$dx = (float) $trazo[0]["x"] - (float) $ideal[0]["x"];
		$dy = (float) $trazo[0]["y"] - (float) $ideal[0]["y"];
		if (sqrt($dx * $dx + $dy * $dy) > $margen) return false;

		/* Las marcas son milisegundos DESDE que se abrió la jugada, así que ni
		   pueden ser negativas ni pueden ir hacia atrás. */
		$previo = -1;
		foreach ($trazo as $pt) {
			$t = (int) ($pt["t"] ?? -1);
			if ($t < 0 || $t < $previo) return false;
			$previo = $t;
		}
		return true;
	}

	/** La estadística que manda en esta jugada, con caída a un valor base. */
	public static function estadisticaDe(array $cartas, $cual, $familia) {
		$col = ["ataque" => "ataque", "defensa" => "defensa", "tecnica" => "tecnica"][$cual] ?? "ataque";
		$vals = [];
		foreach ($cartas as $c) { if (isset($c[$col])) { $vals[] = (float) $c[$col]; } }
		if (!$vals) return 50.0;   // partido de laboratorio: base jugable
		rsort($vals);
		return $vals[0];
	}

	/** Elemento dominante de una alineación. "no-afi" si no hay ninguno. */
	public static function elementoDe(array $cartas, $familia) {
		$cuenta = [];
		foreach ($cartas as $c) {
			$e = strtolower((string) ($c["afinidad"] ?? ""));
			$e = str_replace("ñ", "n", $e);
			if (in_array($e, self::ELEMENTOS, true)) {
				$cuenta[$e] = ($cuenta[$e] ?? 0) + 1;
			}
		}
		if (!$cuenta) return "no-afi";
		arsort($cuenta);
		return array_key_first($cuenta);
	}
}
