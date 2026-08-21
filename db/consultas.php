<?php

class Tcg
{

	private $pdo;

	public function __construct($host, $port, $db, $user, $pass)
	{
		$this->pdo = new PDO("mysql:host=" . $host . ";port=" . $port . ";dbname=" . $db, $user, $pass);
	}

	//Función para obtener los cromos destacados. (destacados desde el panel de control de administrador)
	public function listarDestacados() {
		$sql = "
			SELECT
				c.id_cromo,
				c.nombre,
				c.descripcion,
				c.imagen,
				c.posicion,
				e.nombre AS expansion,
				eq.nombre AS equipo,
				r.id_rareza,
				r.nombre AS rareza,
				af.nombre AS afinidad,
				af.imagen AS afinidad_imagen
			FROM cromos c
			INNER JOIN expansiones e ON c.id_expansion = e.id_expansion
			INNER JOIN equipos eq ON c.id_equipo = eq.id_equipo
			INNER JOIN rarezas r ON c.id_rareza = r.id_rareza
			INNER JOIN afinidad af ON c.id_afinidad = af.id
			ORDER BY c.id_cromo LIMIT 5
		";

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function listarExpansionesActivas() {
		$sql = "
			SELECT * FROM expansiones WHERE activo=1 ORDER BY fecha_salida DESC
		";

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function listarExpansiones() {
		$sql = "
			SELECT * FROM expansiones ORDER BY fecha_salida DESC
		";

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function cartasExpansion($id) {
		$sql = "
			SELECT COUNT(*) AS total
			FROM cromos
			WHERE id_expansion = :id
		";

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([
			":id" => $id
		]);

		return $stmt->fetch(PDO::FETCH_ASSOC)["total"];
	}

	// Comprueba si ya existe un usuario registrado con ese nombre
	public function comprobarEmailExiste($nombre)
	{
		$sentencia = "SELECT COUNT(*) as total FROM usuarios WHERE nombre = :nombre";
		$ejecucion = $this->pdo->prepare($sentencia);
		$ejecucion->execute([
			":nombre" => $nombre
		]);
		$resultado = $ejecucion->fetch(PDO::FETCH_ASSOC);

		return $resultado['total'] > 0;
	}

	/**
	 * Inserta un nuevo usuario con la contraseña hasheada y foto por defecto.
	 * Devuelve null si el nombre ya existe (visto en auditoría: dos registros
	 * simultáneos con el mismo nombre pasan los dos el comprobarEmailExiste()
	 * de registro.php, porque ninguno ha escrito todavía; el UNIQUE de la
	 * columna `nombre` frena al segundo, pero como PDO por defecto lanza
	 * excepción en el fallo, sin este try/catch ese segundo request moría
	 * en un 500 en vez de con el mismo "ese nombre ya está en uso" de siempre).
	 */
	public function registrarUsuario($nombre, $pass)
	{
		$sentencia = "INSERT INTO usuarios(nombre, password_hash, foto) VALUES (:nombre, :password_hash, :foto)";
		$ejecucion = $this->pdo->prepare($sentencia);
		try {
			$ejecucion->execute(
				array(
					":nombre" => $nombre,
					":password_hash" => password_hash($pass, PASSWORD_DEFAULT),
					":foto" => "./assets/img/perfil/apple-icon-120x120.png"
				)
			);
		} catch (PDOException $e) {
			if ($e->getCode() === '23000') { // violación de UNIQUE
				return null;
			}
			throw $e;
		}

		return $this->pdo->lastInsertId();
	}

	// Busca un usuario por su nombre de invocador (para el login)
	public function obtenerUsuarioPorNombre($nombre)
	{
		$sentencia = "SELECT * FROM usuarios WHERE nombre = :nombre";
		$ejecucion = $this->pdo->prepare($sentencia);
		$ejecucion->execute([
			":nombre" => $nombre
		]);

		$resultado = $ejecucion->fetch(PDO::FETCH_ASSOC);

		return $resultado ?: null;
	}

	// Comprueba las credenciales de login y devuelve el usuario si son correctas
	public function verificarLogin($nombre, $pass)
	{
		$usuario = $this->obtenerUsuarioPorNombre($nombre);

		if (!$usuario) {
			return false;
		}

		if (!password_verify($pass, $usuario['password_hash'])) {
			return false;
		}

		return $usuario;
	}

	// ==========================================================
	// LÍMITE DE INTENTOS DE LOGIN (login.php)
	// Se cuenta por IP + nombre probado, así que ni cambiar de IP ni
	// probar contra otro nombre esquiva el bloqueo del par que sí se repite.
	// ==========================================================

	const LOGIN_MAX_INTENTOS   = 5;
	const LOGIN_BLOQUEO_MINUTOS = 15;

	/** Minutos que faltan de bloqueo para este IP+nombre, o 0 si puede intentarlo. */
	public function minutosBloqueoLogin($ip, $nombre) {
		$stmt = $this->pdo->prepare("
			SELECT bloqueado_hasta FROM login_intentos
			WHERE ip = :ip AND nombre = :nombre
		");
		$stmt->execute([":ip" => $ip, ":nombre" => $nombre]);
		$fila = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$fila || $fila["bloqueado_hasta"] === null) {
			return 0;
		}

		$restante = strtotime($fila["bloqueado_hasta"]) - time();
		return $restante > 0 ? (int) ceil($restante / 60) : 0;
	}

	/**
	 * Suma un intento fallido; si llega al máximo, bloquea el par IP+nombre.
	 *
	 * Va dentro de una transacción con `SELECT ... FOR UPDATE` (mismo patrón
	 * que canjearCodigo()): sin esto, dos peticiones concurrentes leen el
	 * mismo `intentos` antes de que ninguna escriba y se pisan entre sí —
	 * confirmado en auditoría: 30 intentos de login simultáneos solo dejaban
	 * contados 5, es decir, una fuerza bruta con hilos en paralelo se comía el
	 * bloqueo casi gratis. El `INSERT IGNORE` de antes asegura que la fila
	 * exista para poder bloquearla: `FOR UPDATE` no bloquea lo que no existe.
	 */
	public function registrarIntentoLoginFallido($ip, $nombre) {
		try {
			$this->pdo->beginTransaction();

			$this->pdo->prepare("
				INSERT IGNORE INTO login_intentos (ip, nombre, intentos, ultimo_intento, bloqueado_hasta)
				VALUES (:ip, :nombre, 0, NOW(), NULL)
			")->execute([":ip" => $ip, ":nombre" => $nombre]);

			$stmt = $this->pdo->prepare("
				SELECT intentos, bloqueado_hasta FROM login_intentos
				WHERE ip = :ip AND nombre = :nombre
				FOR UPDATE
			");
			$stmt->execute([":ip" => $ip, ":nombre" => $nombre]);
			$fila = $stmt->fetch(PDO::FETCH_ASSOC);

			// Un bloqueo ya CADUCADO cuenta como si no hubiera intentos previos:
			// si no, tras el primer bloqueo nunca se volvería a activar.
			$bloqueoCaducado = $fila["bloqueado_hasta"] !== null
				&& strtotime($fila["bloqueado_hasta"]) <= time();
			$intentos = $bloqueoCaducado ? 1 : (int) $fila["intentos"] + 1;

			$bloqueadoHasta = $intentos >= self::LOGIN_MAX_INTENTOS
				? date("Y-m-d H:i:s", time() + self::LOGIN_BLOQUEO_MINUTOS * 60)
				: null;

			$this->pdo->prepare("
				UPDATE login_intentos SET intentos = :intentos, ultimo_intento = NOW(), bloqueado_hasta = :bloqueado_hasta
				WHERE ip = :ip AND nombre = :nombre
			")->execute([
				":intentos" => $intentos, ":bloqueado_hasta" => $bloqueadoHasta,
				":ip" => $ip, ":nombre" => $nombre,
			]);

			$this->pdo->commit();
		} catch (Exception $e) {
			$this->pdo->rollBack();
		}
	}

	/** Login correcto: se olvida cualquier intento fallido previo de este par. */
	public function limpiarIntentosLogin($ip, $nombre) {
		$this->pdo->prepare("DELETE FROM login_intentos WHERE ip = :ip AND nombre = :nombre")
			->execute([":ip" => $ip, ":nombre" => $nombre]);
	}

	public function listarColeccionCompleta(){
		$sql = "
			SELECT
				c.id_cromo,
				c.nombre,
				c.descripcion,
				c.imagen,
				c.posicion,
				c.ataque, c.defensa, c.tecnica,
				c.id_expansion,
				e.nombre AS expansion,
				e.fecha_salida,
				eq.nombre AS equipo,
				r.id_rareza,
				r.nombre AS rareza,
				af.nombre AS afinidad,
				af.imagen AS afinidad_imagen,
				(SELECT rg.nombre FROM cromo_rasgos cr INNER JOIN rasgos rg ON rg.id_rasgo = cr.id_rasgo
				 WHERE cr.id_cromo = c.id_cromo AND rg.tipo = 'configuracion' LIMIT 1) AS rasgo
			FROM cromos c
			INNER JOIN expansiones e ON c.id_expansion = e.id_expansion
			INNER JOIN equipos eq ON c.id_equipo = eq.id_equipo
			INNER JOIN rarezas r ON c.id_rareza = r.id_rareza
			INNER JOIN afinidad af ON c.id_afinidad = af.id
			WHERE e.activo = 1
			ORDER BY e.fecha_salida DESC, c.id_cromo ASC
		";

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();

		$resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$coleccion = [];

		foreach ($resultado as $cromo) {

			$idExpansion = $cromo["id_expansion"];

			if (!isset($coleccion[$idExpansion])) {
				$coleccion[$idExpansion] = [
					"info" => [
						"id_expansion" => $cromo["id_expansion"],
						"nombre" => $cromo["expansion"],
						"fecha_salida" => $cromo["fecha_salida"]
					],
					"cromos" => []
				];
			}

			$coleccion[$idExpansion]["cromos"][] = $cromo;
		}

		return $coleccion;
	}

	// ==========================================================
	// PANEL DE CONTROL — CROMOS
	// ==========================================================

	// Lista todos los cromos (también los de expansiones inactivas) para el panel de admin
	public function listarCromosAdmin() {
		$sql = "
			SELECT
				c.id_cromo, c.nombre, c.posicion, c.descripcion, c.imagen,
				c.id_expansion, c.id_equipo, c.id_rareza, c.id_afinidad,
				c.ataque, c.defensa, c.tecnica,
				e.nombre AS expansion,
				eq.nombre AS equipo,
				r.nombre AS rareza,
				af.nombre AS afinidad,
				(SELECT cr.id_rasgo FROM cromo_rasgos cr INNER JOIN rasgos rg ON rg.id_rasgo = cr.id_rasgo
				 WHERE cr.id_cromo = c.id_cromo AND rg.tipo = 'configuracion' LIMIT 1) AS id_rasgo_compo,
				(SELECT cr.manual FROM cromo_rasgos cr INNER JOIN rasgos rg ON rg.id_rasgo = cr.id_rasgo
				 WHERE cr.id_cromo = c.id_cromo AND rg.tipo = 'configuracion' LIMIT 1) AS compo_manual
			FROM cromos c
			INNER JOIN expansiones e ON c.id_expansion = e.id_expansion
			INNER JOIN equipos eq ON c.id_equipo = eq.id_equipo
			INNER JOIN rarezas r ON c.id_rareza = r.id_rareza
			INNER JOIN afinidad af ON c.id_afinidad = af.id
			ORDER BY c.id_cromo DESC
		";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function crearCromo($nombre, $posicion, $descripcion, $imagen, $id_expansion, $id_equipo, $id_rareza, $id_afinidad, $ataque = 0, $defensa = 0, $tecnica = 0, $origen_importacion = 0) {
		$sql = "
			INSERT INTO cromos (nombre, posicion, descripcion, imagen, id_expansion, id_equipo, id_rareza, id_afinidad, ataque, defensa, tecnica, origen_importacion)
			VALUES (:nombre, :posicion, :descripcion, :imagen, :id_expansion, :id_equipo, :id_rareza, :id_afinidad, :ataque, :defensa, :tecnica, :origen_importacion)
		";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([
			":nombre" => $nombre,
			":posicion" => $posicion,
			":descripcion" => $descripcion,
			":imagen" => $imagen,
			":id_expansion" => $id_expansion,
			":id_equipo" => $id_equipo,
			":id_rareza" => $id_rareza,
			":id_afinidad" => $id_afinidad,
			":ataque" => $ataque,
			":defensa" => $defensa,
			":tecnica" => $tecnica,
			":origen_importacion" => $origen_importacion,
		]);
		return $this->pdo->lastInsertId();
	}

	public function actualizarCromo($id_cromo, $nombre, $posicion, $descripcion, $imagen, $id_expansion, $id_equipo, $id_rareza, $id_afinidad, $ataque = 0, $defensa = 0, $tecnica = 0) {
		$sql = "
			UPDATE cromos SET
				nombre = :nombre,
				posicion = :posicion,
				descripcion = :descripcion,
				imagen = :imagen,
				id_expansion = :id_expansion,
				id_equipo = :id_equipo,
				id_rareza = :id_rareza,
				id_afinidad = :id_afinidad,
				ataque = :ataque,
				defensa = :defensa,
				tecnica = :tecnica
			WHERE id_cromo = :id_cromo
		";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([
			":nombre" => $nombre,
			":posicion" => $posicion,
			":descripcion" => $descripcion,
			":imagen" => $imagen,
			":id_expansion" => $id_expansion,
			":id_equipo" => $id_equipo,
			":id_rareza" => $id_rareza,
			":id_afinidad" => $id_afinidad,
			":ataque" => $ataque,
			":defensa" => $defensa,
			":tecnica" => $tecnica,
			":id_cromo" => $id_cromo,
		]);
	}

	// Cascade explícito, a propósito (decisión de Alejandro: borrar desde el
	// panel nunca se bloquea). `coleccion.id_cromo` y `duelo_alineaciones.id_cromo`
	// son RESTRICT en el esquema —protegen contra un DELETE a ciegas que
	// reventaría con un error 500—, así que hay que vaciarlas a mano primero.
	// Todo lo demás (cadena_rival_cartas, cadena_loot, cadena_numeracion,
	// cromo_rasgos, y lo que cuelga de coleccion vía mazo_cartas/mercado/
	// duelo_apuestas) ya es CASCADE en el esquema y se limpia solo.
	//
	// ⚠️ Efecto real: quita la carta de la colección de CUALQUIERA que la
	// tenga, la saca de mazos y anuncios de mercado activos, y borra su rastro
	// en duelos ya jugados. Si estaba en la plantilla de un rival de Cadenas,
	// ese estilo se queda con menos de 11 huecos (mismo caso que se resolvió a
	// mano en la limpieza del catálogo — aquí no hay quien lo reconstruya solo).
	private function borrarCromoCascade($id_cromo) {
		$this->pdo->prepare("DELETE FROM coleccion WHERE id_cromo = :id")->execute([":id" => $id_cromo]);
		$this->pdo->prepare("DELETE FROM duelo_alineaciones WHERE id_cromo = :id")->execute([":id" => $id_cromo]);
		$this->pdo->prepare("DELETE FROM cromos WHERE id_cromo = :id")->execute([":id" => $id_cromo]);
	}

	public function eliminarCromo($id_cromo) {
		try {
			$this->pdo->beginTransaction();
			$this->borrarCromoCascade($id_cromo);
			$this->pdo->commit();
			return true;
		} catch (Exception $e) {
			$this->pdo->rollBack();
			return false;
		}
	}

	// ==========================================================
	// PANEL DE CONTROL — LISTADOS DE APOYO (selects de los formularios)
	// ==========================================================

	public function listarEquipos() {
		$stmt = $this->pdo->query("SELECT * FROM equipos ORDER BY nombre ASC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function listarRarezas() {
		$stmt = $this->pdo->query("SELECT * FROM rarezas ORDER BY id_rareza ASC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function listarAfinidades() {
		$stmt = $this->pdo->query("SELECT * FROM afinidad ORDER BY id ASC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	// ==========================================================
	// PANEL DE CONTROL — EXPANSIONES (crear / editar / eliminar)
	// ==========================================================

	public function crearExpansion($nombre, $fecha_salida, $activo) {
		$sql = "INSERT INTO expansiones (nombre, fecha_salida, activo) VALUES (:nombre, :fecha_salida, :activo)";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([
			":nombre" => $nombre,
			":fecha_salida" => $fecha_salida,
			":activo" => $activo,
		]);
		return $this->pdo->lastInsertId();
	}

	public function actualizarExpansion($id_expansion, $nombre, $fecha_salida, $activo) {
		$sql = "UPDATE expansiones SET nombre = :nombre, fecha_salida = :fecha_salida, activo = :activo WHERE id_expansion = :id";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([
			":nombre" => $nombre,
			":fecha_salida" => $fecha_salida,
			":activo" => $activo,
			":id" => $id_expansion,
		]);
	}

	// Cascade explícito (mismo criterio que eliminarCromo()): borra primero
	// cada cromo de la expansión con su propio cascade completo, luego los
	// sobres (`sobre.id_expansion` también es RESTRICT), y por último la
	// expansión. Una sola transacción para las tres cosas: si algo falla a
	// medio camino, no se queda la expansión mutilada.
	public function eliminarExpansion($id_expansion) {
		try {
			$this->pdo->beginTransaction();

			$stmt = $this->pdo->prepare("SELECT id_cromo FROM cromos WHERE id_expansion = :id");
			$stmt->execute([":id" => $id_expansion]);
			foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $idCromo) {
				$this->borrarCromoCascade($idCromo);
			}

			$this->pdo->prepare("DELETE FROM sobre WHERE id_expansion = :id")->execute([":id" => $id_expansion]);
			$this->pdo->prepare("DELETE FROM expansiones WHERE id_expansion = :id")->execute([":id" => $id_expansion]);

			$this->pdo->commit();
			return true;
		} catch (Exception $e) {
			$this->pdo->rollBack();
			return false;
		}
	}

	// ==========================================================
	// PANEL DE CONTROL — USUARIOS (crear / editar / eliminar)
	// ==========================================================

	public function listarUsuarios() {
		$stmt = $this->pdo->query("SELECT * FROM usuarios ORDER BY fecha_registro DESC");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function crearUsuarioAdmin($nombre, $password, $monedas, $dictador) {
		$sql = "
			INSERT INTO usuarios (nombre, password_hash, monedas, dictador, foto)
			VALUES (:nombre, :password_hash, :monedas, :dictador, :foto)
		";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([
			":nombre" => $nombre,
			":password_hash" => password_hash($password, PASSWORD_DEFAULT),
			":monedas" => $monedas,
			":dictador" => $dictador,
			":foto" => "./assets/img/perfil/apple-icon-120x120.png",
		]);
		return $this->pdo->lastInsertId();
	}

	public function actualizarUsuarioAdmin($id_usuario, $nombre, $monedas, $dictador) {
		$sql = "UPDATE usuarios SET nombre = :nombre, monedas = :monedas, dictador = :dictador WHERE id_usuario = :id";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([
			":nombre" => $nombre,
			":monedas" => $monedas,
			":dictador" => $dictador,
			":id" => $id_usuario,
		]);
	}

	// Restablece la contraseña de un usuario existente (acción aparte, no forma parte del guardado normal)
	public function restablecerPasswordUsuario($id_usuario, $password) {
		$stmt = $this->pdo->prepare("UPDATE usuarios SET password_hash = :password WHERE id_usuario = :id");
		$stmt->execute([
			":password" => password_hash($password, PASSWORD_DEFAULT),
			":id" => $id_usuario,
		]);
	}

	public function eliminarUsuario($id_usuario) {
		$stmt = $this->pdo->prepare("DELETE FROM usuarios WHERE id_usuario = :id");
		$stmt->execute([":id" => $id_usuario]);
	}

	// ==========================================================
	// PANEL DE CONTROL — CADENAS (crear el mapa, los rivales y el botín)
	//
	// Todo lo que hay en el bloque "CADENAS DE PARTIDO (PvE)" más abajo es de
	// LECTURA para el jugador (mapa, progreso, resolución de duelo). Esto es
	// lo contrario: escritura para quien diseña la cadena. Vive aquí, junto
	// al resto de CRUD del panel, no junto al motor de juego.
	// ==========================================================

	// --- Cadenas ---

	// A diferencia de listarCadenas() (la del jugador), esta trae también las
	// inactivas y caducadas: el admin tiene que poder verlas para reactivarlas.
	public function listarCadenasAdmin() {
		return $this->pdo->query("
			SELECT c.*, (SELECT COUNT(*) FROM cadena_nodos n WHERE n.id_cadena = c.id_cadena) AS total_nodos
			FROM cadenas c ORDER BY c.orden, c.id_cadena
		")->fetchAll(PDO::FETCH_ASSOC);
	}

	public function obtenerCadenaAdmin($id_cadena) {
		$stmt = $this->pdo->prepare("SELECT * FROM cadenas WHERE id_cadena = :id");
		$stmt->execute([":id" => $id_cadena]);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
	}

	public function crearCadenaAdmin($nombre, $descripcion, $anfitrion, $orden, $activa, $formacion_recompensa, $fecha_fin) {
		$stmt = $this->pdo->prepare("
			INSERT INTO cadenas (nombre, descripcion, anfitrion, orden, activa, formacion_recompensa, fecha_fin)
			VALUES (:nombre, :descripcion, :anfitrion, :orden, :activa, :formacion_recompensa, :fecha_fin)
		");
		$stmt->execute([
			":nombre" => $nombre, ":descripcion" => $descripcion ?: null, ":anfitrion" => $anfitrion ?: null,
			":orden" => $orden, ":activa" => $activa,
			":formacion_recompensa" => $formacion_recompensa ?: null, ":fecha_fin" => $fecha_fin ?: null,
		]);
		return (int) $this->pdo->lastInsertId();
	}

	public function actualizarCadenaAdmin($id_cadena, $nombre, $descripcion, $anfitrion, $orden, $activa, $formacion_recompensa, $fecha_fin) {
		$stmt = $this->pdo->prepare("
			UPDATE cadenas SET nombre = :nombre, descripcion = :descripcion, anfitrion = :anfitrion,
				orden = :orden, activa = :activa, formacion_recompensa = :formacion_recompensa, fecha_fin = :fecha_fin
			WHERE id_cadena = :id
		");
		$stmt->execute([
			":nombre" => $nombre, ":descripcion" => $descripcion ?: null, ":anfitrion" => $anfitrion ?: null,
			":orden" => $orden, ":activa" => $activa,
			":formacion_recompensa" => $formacion_recompensa ?: null, ":fecha_fin" => $fecha_fin ?: null,
			":id" => $id_cadena,
		]);
	}

	// Cascade completo ya en el esquema (cadena_nodos, cadena_requisitos, y
	// desde los nodos cadena_aristas/cadena_cofres/cadena_drops/cadena_loot/
	// cadena_progreso, todo CASCADE): borrar la cadena SÍ se lleva por delante
	// el progreso real de cualquier jugador en ella. Decisión de Alejandro:
	// borrar desde el panel nunca se bloquea.
	public function eliminarCadenaAdmin($id_cadena) {
		$this->pdo->prepare("DELETE FROM cadenas WHERE id_cadena = :id")->execute([":id" => $id_cadena]);
		return true;
	}

	// --- Requisitos de entrada ---

	public function listarRequisitosAdmin($id_cadena) {
		$stmt = $this->pdo->prepare("SELECT * FROM cadena_requisitos WHERE id_cadena = :id ORDER BY id_requisito");
		$stmt->execute([":id" => $id_cadena]);
		$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($filas as &$r) {
			if ($r["tipo"] === "cadena") {
				$n = $this->pdo->prepare("SELECT nombre FROM cadenas WHERE id_cadena = :v");
			} else {
				$n = $this->pdo->prepare("SELECT nombre FROM cromos WHERE id_cromo = :v");
			}
			$n->execute([":v" => $r["valor"]]);
			$r["nombre_valor"] = $n->fetchColumn() ?: null;
		}
		unset($r);
		return $filas;
	}

	// Un requisito tipo "cadena" es una arista id_cadena -> valor ("A exige
	// haber completado B"). Añadir una arista que ya se puede alcanzar AL
	// REVÉS desde B cierra un ciclo: ninguna de las cadenas implicadas se
	// podría empezar nunca, ni un jugador real ni el guion de pruebas
	// (probar_pve.php falló exactamente así al probarlo con datos reales).
	private function requisitoCreariaCiclo($id_cadena, $id_cadena_exigida) {
		$id_cadena = (int) $id_cadena;
		$id_cadena_exigida = (int) $id_cadena_exigida;
		if ($id_cadena === $id_cadena_exigida) {
			return true; // una cadena no puede exigirse a sí misma
		}

		// BFS desde la cadena exigida, siguiendo las aristas "exige" que YA
		// existen: si desde ahí se llega de vuelta a $id_cadena, la arista
		// nueva cerraría el ciclo.
		$visitados = [$id_cadena_exigida => true];
		$pendientes = [$id_cadena_exigida];
		while ($pendientes) {
			$actual = array_pop($pendientes);
			$stmt = $this->pdo->prepare("SELECT valor FROM cadena_requisitos WHERE id_cadena = :c AND tipo = 'cadena'");
			$stmt->execute([":c" => $actual]);
			foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $siguiente) {
				$siguiente = (int) $siguiente;
				if ($siguiente === $id_cadena) {
					return true;
				}
				if (!isset($visitados[$siguiente])) {
					$visitados[$siguiente] = true;
					$pendientes[] = $siguiente;
				}
			}
		}
		return false;
	}

	// Devuelve false sin insertar si el requisito (tipo "cadena") cerraría un
	// ciclo. Los de tipo "cromo" no pueden formar ciclos, así que pasan directos.
	public function crearRequisito($id_cadena, $tipo, $valor) {
		if ($tipo === "cadena" && $this->requisitoCreariaCiclo($id_cadena, $valor)) {
			return false;
		}
		$this->pdo->prepare("INSERT INTO cadena_requisitos (id_cadena, tipo, valor) VALUES (:c, :t, :v)")
			->execute([":c" => $id_cadena, ":t" => $tipo, ":v" => $valor]);
		return true;
	}

	public function eliminarRequisito($id_requisito) {
		$this->pdo->prepare("DELETE FROM cadena_requisitos WHERE id_requisito = :id")->execute([":id" => $id_requisito]);
	}

	// --- Nodos ---

	public function listarNodosAdmin($id_cadena) {
		$stmt = $this->pdo->prepare("
			SELECT n.*, r.nombre AS rival
			FROM cadena_nodos n
			LEFT JOIN cadena_rivales r ON r.id_rival = n.id_rival
			WHERE n.id_cadena = :id
			ORDER BY n.columna, n.fila
		");
		$stmt->execute([":id" => $id_cadena]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function crearNodo($id_cadena, $tipo, $nombre, $columna, $fila, $es_final, $id_rival = null) {
		$stmt = $this->pdo->prepare("
			INSERT INTO cadena_nodos (id_cadena, id_rival, tipo, nombre, columna, fila, es_final)
			VALUES (:c, :r, :t, :n, :col, :fil, :ef)
		");
		$stmt->execute([
			":c" => $id_cadena, ":r" => $id_rival ?: null, ":t" => $tipo, ":n" => $nombre ?: null,
			":col" => $columna, ":fil" => $fila, ":ef" => $es_final,
		]);
		return (int) $this->pdo->lastInsertId();
	}

	public function actualizarNodo($id_nodo, $tipo, $nombre, $es_final, $id_rival = null) {
		$this->pdo->prepare("
			UPDATE cadena_nodos SET tipo = :t, nombre = :n, es_final = :ef, id_rival = :r
			WHERE id_nodo = :id
		")->execute([
			":t" => $tipo, ":n" => $nombre ?: null, ":ef" => $es_final, ":r" => $id_rival ?: null, ":id" => $id_nodo,
		]);
	}

	// Solo la posición: es lo único que toca arrastrar un nodo en el editor.
	public function moverNodo($id_nodo, $columna, $fila) {
		$this->pdo->prepare("UPDATE cadena_nodos SET columna = :col, fila = :fil WHERE id_nodo = :id")
			->execute([":col" => $columna, ":fil" => $fila, ":id" => $id_nodo]);
	}

	// Igual que eliminarCadenaAdmin(): cascade completo ya en el esquema, así
	// que borra el nodo aunque algún jugador tenga progreso real en él.
	public function eliminarNodo($id_nodo) {
		$this->pdo->prepare("DELETE FROM cadena_nodos WHERE id_nodo = :id")->execute([":id" => $id_nodo]);
		return true;
	}

	// --- Aristas ---

	public function listarAristasAdmin($id_cadena) {
		$stmt = $this->pdo->prepare("
			SELECT a.* FROM cadena_aristas a
			INNER JOIN cadena_nodos n ON n.id_nodo = a.id_origen
			WHERE n.id_cadena = :id
		");
		$stmt->execute([":id" => $id_cadena]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	// INSERT IGNORE porque la PK es (id_origen, id_destino): conectar dos
	// nodos ya conectados no debe reventar, simplemente no hace nada.
	public function crearArista($id_origen, $id_destino) {
		$this->pdo->prepare("INSERT IGNORE INTO cadena_aristas (id_origen, id_destino) VALUES (:o, :d)")
			->execute([":o" => $id_origen, ":d" => $id_destino]);
	}

	public function eliminarArista($id_origen, $id_destino) {
		$this->pdo->prepare("DELETE FROM cadena_aristas WHERE id_origen = :o AND id_destino = :d")
			->execute([":o" => $id_origen, ":d" => $id_destino]);
	}

	// --- Rivales, estilos y su plantilla de 11 cartas ---

	// A diferencia de listarRivales() (la del jugador), trae también los
	// inactivos: el admin tiene que poder verlos para reutilizarlos o reactivarlos.
	public function listarRivalesAdmin() {
		return $this->pdo->query("
			SELECT r.*, (SELECT COUNT(*) FROM cadena_rival_estilos e WHERE e.id_rival = r.id_rival) AS total_estilos
			FROM cadena_rivales r ORDER BY r.nombre
		")->fetchAll(PDO::FETCH_ASSOC);
	}

	public function obtenerRival($id_rival) {
		$stmt = $this->pdo->prepare("SELECT * FROM cadena_rivales WHERE id_rival = :id");
		$stmt->execute([":id" => $id_rival]);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
	}

	public function crearRival($nombre, $escudo, $descripcion, $activo) {
		$stmt = $this->pdo->prepare("
			INSERT INTO cadena_rivales (nombre, escudo, descripcion, activo) VALUES (:n, :e, :d, :a)
		");
		$stmt->execute([":n" => $nombre, ":e" => $escudo ?: null, ":d" => $descripcion ?: null, ":a" => $activo]);
		return (int) $this->pdo->lastInsertId();
	}

	public function actualizarRival($id_rival, $nombre, $escudo, $descripcion, $activo) {
		$this->pdo->prepare("
			UPDATE cadena_rivales SET nombre = :n, escudo = :e, descripcion = :d, activo = :a WHERE id_rival = :id
		")->execute([":n" => $nombre, ":e" => $escudo ?: null, ":d" => $descripcion ?: null, ":a" => $activo, ":id" => $id_rival]);
	}

	public function listarEstilosRivalAdmin($id_rival) {
		$stmt = $this->pdo->prepare("SELECT * FROM cadena_rival_estilos WHERE id_rival = :r ORDER BY id_estilo");
		$stmt->execute([":r" => $id_rival]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function crearEstiloRival($id_rival, $nombre, $formacion) {
		$stmt = $this->pdo->prepare("
			INSERT INTO cadena_rival_estilos (id_rival, nombre, formacion) VALUES (:r, :n, :f)
		");
		$stmt->execute([":r" => $id_rival, ":n" => $nombre, ":f" => $formacion]);
		return (int) $this->pdo->lastInsertId();
	}

	public function eliminarEstiloRival($id_estilo) {
		$this->pdo->prepare("DELETE FROM cadena_rival_estilos WHERE id_estilo = :id")->execute([":id" => $id_estilo]);
	}

	// Upsert: la PK de cadena_rival_cartas es (id_estilo, hueco), así que
	// volver a asignar un hueco ya cubierto sustituye la carta en vez de fallar.
	public function asignarCartaEstilo($id_estilo, $hueco, $id_cromo) {
		$this->pdo->prepare("
			INSERT INTO cadena_rival_cartas (id_estilo, hueco, id_cromo) VALUES (:e, :h, :c)
			ON DUPLICATE KEY UPDATE id_cromo = VALUES(id_cromo)
		")->execute([":e" => $id_estilo, ":h" => $hueco, ":c" => $id_cromo]);
	}

	// --- Botín (cadena_loot) ---

	public function listarLootNodo($id_nodo) {
		$stmt = $this->pdo->prepare("
			SELECT l.*, c.nombre AS cromo_nombre
			FROM cadena_loot l
			LEFT JOIN cromos c ON c.id_cromo = l.id_cromo
			WHERE l.id_nodo = :id ORDER BY l.id_loot
		");
		$stmt->execute([":id" => $id_nodo]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function crearLoot($id_nodo, $tipo, $id_cromo, $monedas, $probabilidad, $rango_minimo) {
		$this->pdo->prepare("
			INSERT INTO cadena_loot (id_nodo, tipo, id_cromo, monedas, probabilidad, rango_minimo)
			VALUES (:n, :t, :c, :m, :p, :r)
		")->execute([
			":n" => $id_nodo, ":t" => $tipo,
			":c" => $tipo === "monedas" ? null : $id_cromo,
			":m" => $tipo === "monedas" ? $monedas : null,
			":p" => $probabilidad, ":r" => $rango_minimo ?: null,
		]);
		return (int) $this->pdo->lastInsertId();
	}

	public function eliminarLoot($id_loot) {
		$this->pdo->prepare("DELETE FROM cadena_loot WHERE id_loot = :id")->execute([":id" => $id_loot]);
	}

	// ==========================================================
	// PANEL DE CONTROL — CÓDIGOS (crear/editar códigos de canje)
	//
	// obtenerCodigoPorTexto() y canjearCodigo(), más abajo, son de LECTURA
	// para el jugador (perfil.php). Esto es la administración: quien crea el
	// código, no quien lo usa.
	// ==========================================================

	public function listarCodigosAdmin() {
		return $this->pdo->query("
			SELECT c.*, COUNT(cc.id_canje) AS veces_canjeado
			FROM codigos c
			LEFT JOIN codigos_canjeados cc ON cc.id_codigo = c.id_codigo
			GROUP BY c.id_codigo
			ORDER BY c.creado DESC
		")->fetchAll(PDO::FETCH_ASSOC);
	}

	// El texto se normaliza a MAYÚSCULAS aquí, no solo al canjear: canjearCodigo()
	// también hace strtoupper() sobre lo que escribe el jugador (assets/ajax/
	// canjear_codigo.php), así que dos filas "abc123"/"ABC123" serían el mismo
	// código a efectos del jugador pero dos filas distintas para obtenerCodigoPorTexto()
	// (que compara tal cual) — de ahí el guard de duplicado también en mayúsculas.
	private function normalizarCodigo($codigo) {
		return strtoupper(trim($codigo));
	}

	private function codigoDuplicado($codigoNormalizado, $excluirId = null) {
		$sql = "SELECT 1 FROM codigos WHERE codigo = :c";
		$params = [":c" => $codigoNormalizado];
		if ($excluirId !== null) {
			$sql .= " AND id_codigo != :id";
			$params[":id"] = $excluirId;
		}
		$stmt = $this->pdo->prepare($sql . " LIMIT 1");
		$stmt->execute($params);
		return (bool) $stmt->fetchColumn();
	}

	// Devuelve el id del código creado, o false si ya existe uno igual (sin
	// distinguir mayúsculas/minúsculas, que es como lo ve el jugador).
	public function crearCodigoAdmin($codigo, $tipo, $monedas, $activo) {
		$codigo = $this->normalizarCodigo($codigo);
		if ($codigo === '' || $this->codigoDuplicado($codigo)) {
			return false;
		}
		$stmt = $this->pdo->prepare("
			INSERT INTO codigos (codigo, tipo, monedas, activo) VALUES (:c, :t, :m, :a)
		");
		$stmt->execute([":c" => $codigo, ":t" => $tipo, ":m" => $monedas, ":a" => $activo]);
		return (int) $this->pdo->lastInsertId();
	}

	public function actualizarCodigoAdmin($id_codigo, $codigo, $tipo, $monedas, $activo) {
		$codigo = $this->normalizarCodigo($codigo);
		if ($codigo === '' || $this->codigoDuplicado($codigo, $id_codigo)) {
			return false;
		}
		$this->pdo->prepare("
			UPDATE codigos SET codigo = :c, tipo = :t, monedas = :m, activo = :a WHERE id_codigo = :id
		")->execute([":c" => $codigo, ":t" => $tipo, ":m" => $monedas, ":a" => $activo, ":id" => $id_codigo]);
		return true;
	}

	// codigos_canjeados no tiene clave ajena hacia codigos (mismo caso que
	// duelo_minijuegos, §8 del CLAUDE.md de branding): borrar un código deja
	// su historial de canjes huérfano pero inofensivo, y las monedas ya
	// entregadas no se tocan. No hace falta guard de integridad.
	public function eliminarCodigoAdmin($id_codigo) {
		$this->pdo->prepare("DELETE FROM codigos WHERE id_codigo = :id")->execute([":id" => $id_codigo]);
	}

	public function listarCanjesCodigo($id_codigo) {
		$stmt = $this->pdo->prepare("
			SELECT cc.*, u.nombre AS usuario
			FROM codigos_canjeados cc
			INNER JOIN usuarios u ON u.id_usuario = cc.id_usuario
			WHERE cc.id_codigo = :id
			ORDER BY cc.fecha_canje DESC
		");
		$stmt->execute([":id" => $id_codigo]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	// ==========================================================
	// PANEL DE CONTROL — MISIONES (crear/editar misiones)
	//
	// listarMisionesConProgreso() y reclamarMision(), en la sección MISIONES
	// más abajo, son de LECTURA para el jugador (misiones.php). Esto es la
	// administración: quien define el objetivo y la recompensa.
	// ==========================================================

	const MISIONES_TIPOS = [
		'cartas_distintas', 'copias_totales', 'duelos_jugados',
		'duelos_ganados', 'expansiones_completas', 'mazos_creados',
	];
	const MISIONES_CICLOS = ['unica', 'diaria', 'semanal'];

	public function listarMisionesAdmin() {
		return $this->pdo->query("
			SELECT m.*, COUNT(mp.id_progreso) AS veces_reclamada
			FROM misiones m
			LEFT JOIN misiones_progreso mp ON mp.id_mision = m.id_mision
			GROUP BY m.id_mision
			ORDER BY m.id_mision
		")->fetchAll(PDO::FETCH_ASSOC);
	}

	// "expansiones_completas" es un hito de estado sin fecha propia: no se
	// puede acotar a "completada esta semana" (progresoMision() lo explica).
	// Cualquier otro tipo sí es un evento con fecha y funciona en los tres ciclos.
	private function combinacionMisionValida($tipo, $ciclo) {
		if (!in_array($tipo, self::MISIONES_TIPOS, true) || !in_array($ciclo, self::MISIONES_CICLOS, true)) {
			return false;
		}
		return !($ciclo !== "unica" && $tipo === "expansiones_completas");
	}

	public function crearMisionAdmin($nombre, $descripcion, $tipo, $ciclo, $objetivo, $recompensa_monedas, $activo) {
		if (!$this->combinacionMisionValida($tipo, $ciclo)) {
			return false;
		}
		$stmt = $this->pdo->prepare("
			INSERT INTO misiones (nombre, descripcion, tipo, ciclo, objetivo, recompensa_monedas, activo)
			VALUES (:n, :d, :t, :c, :o, :r, :a)
		");
		$stmt->execute([
			":n" => $nombre, ":d" => $descripcion, ":t" => $tipo, ":c" => $ciclo,
			":o" => $objetivo, ":r" => $recompensa_monedas, ":a" => $activo,
		]);
		return (int) $this->pdo->lastInsertId();
	}

	public function actualizarMisionAdmin($id_mision, $nombre, $descripcion, $tipo, $ciclo, $objetivo, $recompensa_monedas, $activo) {
		if (!$this->combinacionMisionValida($tipo, $ciclo)) {
			return false;
		}
		$this->pdo->prepare("
			UPDATE misiones SET nombre = :n, descripcion = :d, tipo = :t, ciclo = :c,
				objetivo = :o, recompensa_monedas = :r, activo = :a
			WHERE id_mision = :id
		")->execute([
			":n" => $nombre, ":d" => $descripcion, ":t" => $tipo, ":c" => $ciclo,
			":o" => $objetivo, ":r" => $recompensa_monedas, ":a" => $activo, ":id" => $id_mision,
		]);
		return true;
	}

	// Cascade completo ya en el esquema (misiones_progreso es CASCADE): borra
	// la misión aunque algún jugador ya la haya reclamado, llevándose su
	// historial de reclamo por delante. Decisión de Alejandro: borrar desde
	// el panel nunca se bloquea.
	public function eliminarMisionAdmin($id_mision) {
		$this->pdo->prepare("DELETE FROM misiones WHERE id_mision = :id")->execute([":id" => $id_mision]);
		return true;
	}

	// ==========================================================
	// COLECCIÓN PERSONAL (coleccion.php)
	// ==========================================================

	// Devuelve los cromos que posee un usuario, con filtros opcionales
	public function listarColeccionUsuario($id_usuario, $filtros = []) {
		$sql = "
			SELECT
				col.id_coleccion, col.obtenida, col.bloqueada,
				c.id_cromo, c.nombre, c.posicion, c.imagen,
				c.ataque, c.defensa, c.tecnica,
				eq.id_equipo, eq.nombre AS equipo,
				e.id_expansion, e.nombre AS expansion,
				r.id_rareza, r.nombre AS rareza,
				af.nombre AS afinidad, af.imagen AS afinidad_imagen,
				(SELECT rg.nombre FROM cromo_rasgos cr INNER JOIN rasgos rg ON rg.id_rasgo = cr.id_rasgo
				 WHERE cr.id_cromo = c.id_cromo AND rg.tipo = 'configuracion' LIMIT 1) AS rasgo
			FROM coleccion col
			INNER JOIN cromos c ON col.id_cromo = c.id_cromo
			INNER JOIN equipos eq ON c.id_equipo = eq.id_equipo
			INNER JOIN expansiones e ON c.id_expansion = e.id_expansion
			INNER JOIN rarezas r ON c.id_rareza = r.id_rareza
			INNER JOIN afinidad af ON c.id_afinidad = af.id
			WHERE col.id_usuario = :id_usuario
		";
		$params = [":id_usuario" => $id_usuario];

		if (!empty($filtros["nombre"])) {
			$sql .= " AND c.nombre LIKE :nombre";
			$params[":nombre"] = "%" . $filtros["nombre"] . "%";
		}
		if (!empty($filtros["id_equipo"])) {
			$sql .= " AND eq.id_equipo = :id_equipo";
			$params[":id_equipo"] = $filtros["id_equipo"];
		}
		if (!empty($filtros["id_expansion"])) {
			$sql .= " AND e.id_expansion = :id_expansion";
			$params[":id_expansion"] = $filtros["id_expansion"];
		}
		if (!empty($filtros["rarezas"]) && is_array($filtros["rarezas"])) {
			$marcadores = [];
			foreach ($filtros["rarezas"] as $i => $idRareza) {
				$clave = ":rareza$i";
				$marcadores[] = $clave;
				$params[$clave] = $idRareza;
			}
			$sql .= " AND r.id_rareza IN (" . implode(",", $marcadores) . ")";
		}
		if (isset($filtros["bloqueada"]) && $filtros["bloqueada"] !== "") {
			$sql .= " AND col.bloqueada = :bloqueada";
			$params[":bloqueada"] = $filtros["bloqueada"];
		}

		$sql .= " ORDER BY col.obtenida DESC";

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	// Nº total de cromos distintos que existen (de expansiones activas), para la barra de progreso
	public function contarCromosTotales() {
		$sql = "
			SELECT COUNT(*) AS total
			FROM cromos c
			INNER JOIN expansiones e ON c.id_expansion = e.id_expansion
			WHERE e.activo = 1
		";
		$stmt = $this->pdo->query($sql);
		return (int) $stmt->fetch(PDO::FETCH_ASSOC)["total"];
	}

	// Nº de cromos DISTINTOS que posee un usuario (las copias repetidas de un
	// mismo cromo solo cuentan una vez; es lo que se usa para el X/Y de progreso)
	public function contarColeccionUsuario($id_usuario) {
		$stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT id_cromo) AS total FROM coleccion WHERE id_usuario = :id");
		$stmt->execute([":id" => $id_usuario]);
		return (int) $stmt->fetch(PDO::FETCH_ASSOC)["total"];
	}

	// Nº de cromos bloqueados de un usuario
	public function contarBloqueadasUsuario($id_usuario) {
		$stmt = $this->pdo->prepare("SELECT COUNT(*) AS total FROM coleccion WHERE id_usuario = :id AND bloqueada = 1");
		$stmt->execute([":id" => $id_usuario]);
		return (int) $stmt->fetch(PDO::FETCH_ASSOC)["total"];
	}

	// Bloquea / desbloquea un cromo de la colección (solo si pertenece al usuario)
	public function alternarBloqueoCromo($id_coleccion, $id_usuario) {
		$stmt = $this->pdo->prepare("
			UPDATE coleccion SET bloqueada = NOT bloqueada
			WHERE id_coleccion = :id_coleccion AND id_usuario = :id_usuario
		");
		$stmt->execute([
			":id_coleccion" => $id_coleccion,
			":id_usuario" => $id_usuario,
		]);
	}

	// Bloquea / desbloquea TODAS las copias de un mismo cromo que compartan el
	// mismo estado (coleccion.php agrupa copias repetidas en una sola carta
	// con insignia "×N"; esto es lo que ejecuta su botón de proteger/desproteger).
	// Solo toca las copias que estaban en $estadoActual, así que si hay copias
	// mixtas (algunas protegidas y otras no) no se pisan entre sí.
	public function alternarBloqueoGrupoCromo($id_cromo, $id_usuario, $estadoActual) {
		$stmt = $this->pdo->prepare("
			UPDATE coleccion SET bloqueada = NOT :estado_actual
			WHERE id_cromo = :id_cromo AND id_usuario = :id_usuario AND bloqueada = :estado_actual2
		");
		$stmt->execute([
			":estado_actual" => $estadoActual,
			":estado_actual2" => $estadoActual,
			":id_cromo" => $id_cromo,
			":id_usuario" => $id_usuario,
		]);
	}

	// ==========================================================
	// PERFIL (perfil.php)
	// ==========================================================

	public function obtenerUsuario($id_usuario) {
		$stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id");
		$stmt->execute([":id" => $id_usuario]);
		$resultado = $stmt->fetch(PDO::FETCH_ASSOC);
		return $resultado ?: null;
	}

	// Nº de expansiones (activas) de las que el usuario tiene TODOS los cromos
	public function contarExpansionesCompletas($id_usuario) {
		$sql = "
			SELECT COUNT(*) AS total FROM (
				SELECT c.id_expansion
				FROM cromos c
				INNER JOIN expansiones e ON c.id_expansion = e.id_expansion
				WHERE e.activo = 1
				GROUP BY c.id_expansion
				HAVING COUNT(*) = (
					SELECT COUNT(DISTINCT col.id_cromo)
					FROM coleccion col
					INNER JOIN cromos c2 ON col.id_cromo = c2.id_cromo
					WHERE c2.id_expansion = c.id_expansion AND col.id_usuario = :id_usuario
				)
			) AS completas
		";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([":id_usuario" => $id_usuario]);
		return (int) $stmt->fetch(PDO::FETCH_ASSOC)["total"];
	}

	// Últimos cromos conseguidos por el usuario
	public function listarColeccionRecienteUsuario($id_usuario, $limite = 8) {
		$limite = (int) $limite;
		$sql = "
			SELECT
				col.id_coleccion, col.obtenida,
				c.nombre, c.imagen, c.posicion, eq.nombre AS equipo, r.id_rareza, r.nombre AS rareza
			FROM coleccion col
			INNER JOIN cromos c ON col.id_cromo = c.id_cromo
			INNER JOIN equipos eq ON c.id_equipo = eq.id_equipo
			INNER JOIN rarezas r ON c.id_rareza = r.id_rareza
			WHERE col.id_usuario = :id_usuario
			ORDER BY col.obtenida DESC
			LIMIT $limite
		";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([":id_usuario" => $id_usuario]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	// Cromos bloqueados del usuario
	public function listarBloqueadasUsuario($id_usuario) {
		$sql = "
			SELECT
				col.id_coleccion,
				c.nombre, c.imagen, c.posicion, eq.nombre AS equipo, r.id_rareza, r.nombre AS rareza
			FROM coleccion col
			INNER JOIN cromos c ON col.id_cromo = c.id_cromo
			INNER JOIN equipos eq ON c.id_equipo = eq.id_equipo
			INNER JOIN rarezas r ON c.id_rareza = r.id_rareza
			WHERE col.id_usuario = :id_usuario AND col.bloqueada = 1
			ORDER BY col.obtenida DESC
		";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([":id_usuario" => $id_usuario]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Anuncios (activos o ya retirados/vendidos) que el propio usuario ha
	 * publicado en el mercado. OJO: el esquema actual no guarda quién
	 * compró cada carta (no hay tabla de "transacciones" con comprador),
	 * así que esto muestra el precio, la fecha y el estado del anuncio
	 * (activo / inactivo), pero no puede distinguir entre "se vendió" y
	 * "la retiraste tú mismo", ni mostrar un historial de compras.
	 */
	public function listarAnunciosUsuario($id_usuario) {
		$sql = "
			SELECT
				m.id_anuncio, m.precio, m.fecha_publicacion, m.activa,
				c.nombre AS carta
			FROM mercado m
			INNER JOIN coleccion col ON m.id_coleccion = col.id_coleccion
			INNER JOIN cromos c ON col.id_cromo = c.id_cromo
			WHERE col.id_usuario = :id_usuario
			ORDER BY m.fecha_publicacion DESC
		";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([":id_usuario" => $id_usuario]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	// ==========================================================
	// MERCADO (mercado.php)
	// ==========================================================

	// Anuncios activos, con datos del cromo y del vendedor
	public function listarMercadoActivo($filtros = []) {
		$sql = "
			SELECT
				m.id_anuncio, m.precio, m.fecha_publicacion,
				col.id_coleccion, col.id_usuario AS id_vendedor,
				c.id_cromo, c.nombre AS carta, c.imagen, c.posicion,
				c.ataque, c.defensa, c.tecnica,
				eq.nombre AS equipo,
				r.id_rareza, r.nombre AS rareza,
				u.nombre AS vendedor
			FROM mercado m
			INNER JOIN coleccion col ON m.id_coleccion = col.id_coleccion
			INNER JOIN cromos c ON col.id_cromo = c.id_cromo
			INNER JOIN equipos eq ON c.id_equipo = eq.id_equipo
			INNER JOIN rarezas r ON c.id_rareza = r.id_rareza
			INNER JOIN usuarios u ON col.id_usuario = u.id_usuario
			WHERE m.activa = 1
		";
		$params = [];

		if (!empty($filtros["nombre"])) {
			$sql .= " AND c.nombre LIKE :nombre";
			$params[":nombre"] = "%" . $filtros["nombre"] . "%";
		}
		if (!empty($filtros["id_rareza"])) {
			$sql .= " AND r.id_rareza = :id_rareza";
			$params[":id_rareza"] = $filtros["id_rareza"];
		}

		$orden = $filtros["orden"] ?? "";
		if ($orden === "precio_asc") {
			$sql .= " ORDER BY m.precio ASC";
		} elseif ($orden === "precio_desc") {
			$sql .= " ORDER BY m.precio DESC";
		} else {
			$sql .= " ORDER BY m.fecha_publicacion DESC";
		}

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	// Cromos que el usuario puede poner a la venta (suyos, no bloqueados y sin anuncio activo ya puesto)
	public function listarColeccionVendible($id_usuario) {
		$sql = "
			SELECT col.id_coleccion, c.id_cromo, c.nombre, c.imagen,
				c.ataque, c.defensa, c.tecnica,
				eq.nombre AS equipo, r.id_rareza, r.nombre AS rareza,
				af.nombre AS afinidad, af.imagen AS afinidad_imagen,
				(SELECT rg.nombre FROM cromo_rasgos cr INNER JOIN rasgos rg ON rg.id_rasgo = cr.id_rasgo
				 WHERE cr.id_cromo = c.id_cromo AND rg.tipo = 'configuracion' LIMIT 1) AS rasgo
			FROM coleccion col
			INNER JOIN cromos c ON col.id_cromo = c.id_cromo
			INNER JOIN equipos eq ON c.id_equipo = eq.id_equipo
			INNER JOIN rarezas r ON c.id_rareza = r.id_rareza
			INNER JOIN afinidad af ON c.id_afinidad = af.id
			WHERE col.id_usuario = :id_usuario
				AND col.bloqueada = 0
				AND col.id_coleccion NOT IN (
					SELECT id_coleccion FROM mercado WHERE activa = 1
				)
				AND col.id_coleccion NOT IN (
					SELECT id_coleccion FROM mazo_cartas
				)
				AND col.id_coleccion NOT IN (
					SELECT da.id_coleccion FROM duelo_apuestas da
					INNER JOIN duelos d ON d.id_duelo = da.id_duelo
					WHERE da.id_coleccion IS NOT NULL AND d.estado NOT IN ('resuelto','cancelado')
				)
			ORDER BY c.nombre ASC
		";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([":id_usuario" => $id_usuario]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Publica (o vuelve a publicar) un anuncio para una carta de la
	 * colección. Comprueba que la carta sea del usuario y no esté
	 * bloqueada. Como `mercado.id_coleccion` es UNIQUE, si ya existe un
	 * anuncio antiguo (inactivo) para esa carta, se reutiliza esa fila en
	 * vez de crear una duplicada.
	 */
	public function publicarAnuncio($id_coleccion, $id_usuario, $precio) {
		$stmt = $this->pdo->prepare("
			SELECT id_coleccion FROM coleccion
			WHERE id_coleccion = :id_coleccion AND id_usuario = :id_usuario AND bloqueada = 0
		");
		$stmt->execute([
			":id_coleccion" => $id_coleccion,
			":id_usuario" => $id_usuario,
		]);
		if (!$stmt->fetch()) {
			return false; // no es tuya, o está bloqueada
		}

		// Si la copia está en un mazo, venderla dejaría esa alineación coja.
		// Se comprueba aquí, en el punto por el que pasan todas las ventas, y
		// no solo en el selector de la pantalla.
		if ($this->mazosQueUsanCopia($id_coleccion)) {
			return false;
		}

		// Igual con una carta apostada en un duelo sin resolver: está
		// comprometida, todavía puede cambiar de dueño.
		$stmtDuelo = $this->pdo->prepare("
			SELECT 1 FROM duelo_apuestas da
			INNER JOIN duelos d ON d.id_duelo = da.id_duelo
			WHERE da.id_coleccion = :id_coleccion AND d.estado NOT IN ('resuelto','cancelado')
			LIMIT 1
		");
		$stmtDuelo->execute([":id_coleccion" => $id_coleccion]);
		if ($stmtDuelo->fetchColumn()) {
			return false;
		}

		$sql = "
			INSERT INTO mercado (id_coleccion, precio, activa, fecha_publicacion)
			VALUES (:id_coleccion, :precio, 1, NOW())
			ON DUPLICATE KEY UPDATE precio = VALUES(precio), activa = 1, fecha_publicacion = NOW()
		";
		$stmt = $this->pdo->prepare($sql);
		return $stmt->execute([
			":id_coleccion" => $id_coleccion,
			":precio" => $precio,
		]);
	}

	// Retira (desactiva) un anuncio propio
	public function retirarAnuncio($id_anuncio, $id_usuario) {
		$sql = "
			UPDATE mercado m
			INNER JOIN coleccion col ON m.id_coleccion = col.id_coleccion
			SET m.activa = 0
			WHERE m.id_anuncio = :id_anuncio AND col.id_usuario = :id_usuario
		";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([
			":id_anuncio" => $id_anuncio,
			":id_usuario" => $id_usuario,
		]);
	}

	/**
	 * Compra una carta del mercado: transfiere monedas entre comprador y
	 * vendedor, transfiere la propiedad del cromo, y desactiva el anuncio.
	 * Devuelve un array ["ok" => bool, "error" => string|null].
	 */
	public function comprarAnuncio($id_anuncio, $id_comprador) {
		$stmt = $this->pdo->prepare("
			SELECT m.id_anuncio, m.precio, m.activa, col.id_coleccion, col.id_usuario AS id_vendedor
			FROM mercado m
			INNER JOIN coleccion col ON m.id_coleccion = col.id_coleccion
			WHERE m.id_anuncio = :id_anuncio
			FOR UPDATE
		");

		try {
			$this->pdo->beginTransaction();

			$stmt->execute([":id_anuncio" => $id_anuncio]);
			$anuncio = $stmt->fetch(PDO::FETCH_ASSOC);

			if (!$anuncio || (int) $anuncio["activa"] !== 1) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "Ese anuncio ya no está disponible."];
			}
			if ((int) $anuncio["id_vendedor"] === (int) $id_comprador) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "No puedes comprar tu propia carta."];
			}

			$stmtComprador = $this->pdo->prepare("SELECT monedas FROM usuarios WHERE id_usuario = :id FOR UPDATE");
			$stmtComprador->execute([":id" => $id_comprador]);
			$comprador = $stmtComprador->fetch(PDO::FETCH_ASSOC);

			if (!$comprador || $comprador["monedas"] < $anuncio["precio"]) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "No tienes monedas suficientes para esta compra."];
			}

			// Cobrar al comprador y pagar al vendedor
			$this->pdo->prepare("UPDATE usuarios SET monedas = monedas - :precio WHERE id_usuario = :id")
				->execute([":precio" => $anuncio["precio"], ":id" => $id_comprador]);
			$this->pdo->prepare("UPDATE usuarios SET monedas = monedas + :precio WHERE id_usuario = :id")
				->execute([":precio" => $anuncio["precio"], ":id" => $anuncio["id_vendedor"]]);

			// Transferir la propiedad del cromo al comprador
			$this->pdo->prepare("
				UPDATE coleccion SET id_usuario = :id_comprador, bloqueada = 0, obtenida = NOW()
				WHERE id_coleccion = :id_coleccion
			")->execute([
				":id_comprador" => $id_comprador,
				":id_coleccion" => $anuncio["id_coleccion"],
			]);

			// Desactivar el anuncio
			$this->pdo->prepare("UPDATE mercado SET activa = 0 WHERE id_anuncio = :id")
				->execute([":id" => $id_anuncio]);

			$this->pdo->commit();
			return ["ok" => true, "error" => null];

		} catch (Exception $e) {
			$this->pdo->rollBack();
			return ["ok" => false, "error" => "Ha ocurrido un error al procesar la compra."];
		}
	}

	// ==========================================================
	// SOBRES (packs)
	// ==========================================================

	// Sobres a la venta ahora mismo (solo de expansiones activas)
	public function listarSobresActivos() {
		$sql = "
			SELECT
				s.id_sobre, s.nombre, s.cantidad, s.precio, s.imagen, s.id_expansion,
				e.nombre AS expansion, e.fecha_salida,
				(SELECT COUNT(*) FROM cromos c WHERE c.id_expansion = s.id_expansion) AS total_cartas
			FROM sobre s
			INNER JOIN expansiones e ON s.id_expansion = e.id_expansion
			WHERE s.activo = 1 AND e.activo = 1
			ORDER BY e.fecha_salida DESC, s.precio ASC
		";

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	// Compra y abre un sobre: cobra al usuario y le da $cantidad cartas al azar
	// de la expansión del sobre, respetando la probabilidad real de cada rareza
	// (columna `probabilidad` de la tabla `rarezas`).
	public function abrirSobre($id_sobre, $id_usuario) {
		try {
			$this->pdo->beginTransaction();

			$stmtSobre = $this->pdo->prepare("
				SELECT s.id_sobre, s.id_expansion, s.cantidad, s.precio,
				       s.activo AS sobre_activo, e.activo AS expansion_activa
				FROM sobre s
				INNER JOIN expansiones e ON s.id_expansion = e.id_expansion
				WHERE s.id_sobre = :id_sobre
				FOR UPDATE
			");
			$stmtSobre->execute([":id_sobre" => $id_sobre]);
			$sobre = $stmtSobre->fetch(PDO::FETCH_ASSOC);

			if (!$sobre || (int) $sobre["sobre_activo"] !== 1 || (int) $sobre["expansion_activa"] !== 1) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "Este sobre ya no está disponible."];
			}

			$stmtUsuario = $this->pdo->prepare("SELECT monedas FROM usuarios WHERE id_usuario = :id FOR UPDATE");
			$stmtUsuario->execute([":id" => $id_usuario]);
			$usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

			if (!$usuario) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "Usuario no encontrado."];
			}
			if ((int) $usuario["monedas"] < (int) $sobre["precio"]) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "No tienes monedas suficientes para comprar este sobre."];
			}

			// Cartas disponibles en esa expansión, con su rareza y probabilidad real
			$stmtCartas = $this->pdo->prepare("
				SELECT
					c.id_cromo, c.nombre, c.imagen, c.posicion,
					eq.nombre AS equipo,
					c.id_rareza, r.nombre AS rareza, r.probabilidad
				FROM cromos c
				INNER JOIN equipos eq ON c.id_equipo = eq.id_equipo
				INNER JOIN rarezas r ON c.id_rareza = r.id_rareza
				WHERE c.id_expansion = :id_expansion
			");
			$stmtCartas->execute([":id_expansion" => $sobre["id_expansion"]]);
			$cartasDisponibles = $stmtCartas->fetchAll(PDO::FETCH_ASSOC);

			if (empty($cartasDisponibles)) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "Esta expansión todavía no tiene cartas cargadas."];
			}

			$cartasObtenidas = $this->elegirCartasSobre($cartasDisponibles, (int) $sobre["cantidad"]);

			$stmtInsertar = $this->pdo->prepare("
				INSERT INTO coleccion (id_usuario, id_cromo, obtenida, bloqueada)
				VALUES (:id_usuario, :id_cromo, NOW(), 0)
			");
			foreach ($cartasObtenidas as $carta) {
				$stmtInsertar->execute([
					":id_usuario" => $id_usuario,
					":id_cromo"   => $carta["id_cromo"],
				]);
			}

			$this->pdo->prepare("UPDATE usuarios SET monedas = monedas - :precio WHERE id_usuario = :id")
				->execute([":precio" => $sobre["precio"], ":id" => $id_usuario]);

			$this->pdo->commit();

			return [
				"ok"      => true,
				"error"   => null,
				"cartas"  => $cartasObtenidas,
				"monedas" => (int) $usuario["monedas"] - (int) $sobre["precio"],
			];

		} catch (Exception $e) {
			$this->pdo->rollBack();
			return ["ok" => false, "error" => "Ha ocurrido un error al abrir el sobre."];
		}
	}

	// Elige $cantidad cartas al azar respetando la probabilidad real de cada
	// rareza (solo entre las rarezas que de verdad tienen cartas en esa expansión,
	// para no "desperdiciar" probabilidad en un tier vacío)
	private function elegirCartasSobre($cartasDisponibles, $cantidad) {
		$porRareza = [];
		foreach ($cartasDisponibles as $carta) {
			$idRareza = $carta["id_rareza"];
			if (!isset($porRareza[$idRareza])) {
				$porRareza[$idRareza] = [
					"probabilidad" => (float) $carta["probabilidad"],
					"cartas"       => [],
				];
			}
			$porRareza[$idRareza]["cartas"][] = $carta;
		}

		$totalProbabilidad = array_sum(array_column($porRareza, "probabilidad"));
		if ($totalProbabilidad <= 0) {
			$totalProbabilidad = count($porRareza); // fallback por si alguna probabilidad está a 0
		}

		$elegidas = [];

		for ($i = 0; $i < $cantidad; $i++) {
			$tirada    = mt_rand() / mt_getrandmax() * $totalProbabilidad;
			$acumulado = 0;
			$rarezaElegida = array_key_first($porRareza);

			foreach ($porRareza as $idRareza => $grupo) {
				$acumulado += $grupo["probabilidad"];
				if ($tirada <= $acumulado) {
					$rarezaElegida = $idRareza;
					break;
				}
			}

			$cartasDeEsaRareza = $porRareza[$rarezaElegida]["cartas"];
			$elegidas[] = $cartasDeEsaRareza[array_rand($cartasDeEsaRareza)];
		}

		return $elegidas;
	}
	// ==========================================================
	// PANEL DE CONTROL — SOBRES (crear / editar / eliminar)
	// ==========================================================

	// Todos los sobres (activos e inactivos) para la tabla del panel
	public function listarSobresAdmin() {
		$sql = "
			SELECT s.id_sobre, s.nombre, s.cantidad, s.precio, s.imagen, s.id_expansion, s.activo,
			       e.nombre AS expansion
			FROM sobre s
			INNER JOIN expansiones e ON s.id_expansion = e.id_expansion
			ORDER BY s.id_sobre DESC
		";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function crearSobre($nombre, $cantidad, $precio, $imagen, $id_expansion, $activo) {
		$sql = "
			INSERT INTO sobre (nombre, cantidad, precio, imagen, id_expansion, activo)
			VALUES (:nombre, :cantidad, :precio, :imagen, :id_expansion, :activo)
		";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([
			":nombre" => $nombre,
			":cantidad" => $cantidad,
			":precio" => $precio,
			":imagen" => $imagen,
			":id_expansion" => $id_expansion,
			":activo" => $activo,
		]);
		return $this->pdo->lastInsertId();
	}

	public function actualizarSobre($id_sobre, $nombre, $cantidad, $precio, $imagen, $id_expansion, $activo) {
		$sql = "
			UPDATE sobre SET
				nombre = :nombre, cantidad = :cantidad, precio = :precio,
				imagen = :imagen, id_expansion = :id_expansion, activo = :activo
			WHERE id_sobre = :id_sobre
		";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([
			":nombre" => $nombre,
			":cantidad" => $cantidad,
			":precio" => $precio,
			":imagen" => $imagen,
			":id_expansion" => $id_expansion,
			":activo" => $activo,
			":id_sobre" => $id_sobre,
		]);
	}

	public function eliminarSobre($id_sobre) {
		$stmt = $this->pdo->prepare("DELETE FROM sobre WHERE id_sobre = :id");
		$stmt->execute([":id" => $id_sobre]);
	}

	// ==========================================================
	// PLANTILLAS 3D (cajas y sobres) — prompt-claude-code-sobres-3d.md, Fase 5
	// Una única plantilla PNG plana por elemento, con zonas fijas predefinidas
	// aquí (nunca configurables por el admin: si las zonas se movieran, el
	// render 3D quedaría desalineado con lo ya subido). El recorte se hace con
	// GD (no hay Node/sharp en esta máquina, ver branding/CLAUDE.md §8).
	// ==========================================================

	// caja_expansion y caja_sobre comparten geometría (misma "caja", solo
	// cambia la escala CSS con la que se pinta); sobre tiene la suya.
	//
	// ⚠ LAS PROPORCIONES DE CADA ZONA COINCIDEN CON LAS DE LA CARA QUE PINTAN.
	// La caja del juego mide ancho:alto:fondo = 200:250:130 (--pack3d-w/h/d en
	// components.css). Con 400px = "ancho" en la plantilla:
	//     alto  = 400 · 250/200 = 500     fondo = 400 · 130/200 = 260
	// Antes todas las zonas eran cuadradas (512×512) o mitades (512×256), así
	// que el arte se estiraba al pintarse sobre caras con otra proporción y la
	// plantilla NO correspondía con el resultado. Si cambias --pack3d-w/h/d,
	// hay que recalcular estas zonas con la misma regla de tres.
	//   · front    ancho × alto
	//   · top/lid  ancho × fondo   (la tapa cubre la boca entera)
	//   · side     fondo × alto
	//   · interior ancho × fondo   (el suelo, se ve al abrir)
	const ZONAS_CAJA = [
		"front"    => ["x" => 0,   "y" => 0,   "w" => 400, "h" => 500],
		"side"     => ["x" => 400, "y" => 0,   "w" => 260, "h" => 500],
		"top"      => ["x" => 0,   "y" => 500, "w" => 400, "h" => 260],
		"lid"      => ["x" => 400, "y" => 500, "w" => 400, "h" => 260],
		"interior" => ["x" => 0,   "y" => 760, "w" => 400, "h" => 260],
	];
	const LIENZO_CAJA = ["w" => 1024, "h" => 1024];

	// El sobre del juego mide 165×235 (--pack3d-sobre-w/h): con 400 de ancho,
	// alto = 400 · 235/165 = 570.
	const ZONAS_SOBRE = [
		"frente"  => ["x" => 0,   "y" => 0, "w" => 400, "h" => 570],
		"reverso" => ["x" => 400, "y" => 0, "w" => 400, "h" => 570],
	];
	// 720 de alto y no 570: la franja de abajo queda libre para el aviso LEEME.
	const LIENZO_SOBRE = ["w" => 1024, "h" => 720];

	// Puramente decorativo (§14 Fase 3 del prompt): cuántos sobres "salen" de
	// la caja al abrirse. No tiene relación con `sobre.cantidad` (cartas por
	// sobre) ni con ningún stock real — cada clic compra siempre uno.
	const SOBRES_POR_CAJA = 50;

	public static function esTipoCaja($tipo) {
		return $tipo === "caja_expansion" || $tipo === "caja_sobre";
	}
	public static function zonasPlantilla($tipo) {
		return self::esTipoCaja($tipo) ? self::ZONAS_CAJA : self::ZONAS_SOBRE;
	}
	public static function lienzoPlantilla($tipo) {
		return self::esTipoCaja($tipo) ? self::LIENZO_CAJA : self::LIENZO_SOBRE;
	}

	public function obtenerPlantilla($tipo, $id_referencia) {
		$stmt = $this->pdo->prepare("SELECT * FROM plantillas_3d WHERE tipo = :tipo AND id_referencia = :id");
		$stmt->execute([":tipo" => $tipo, ":id" => $id_referencia]);
		$fila = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$fila) return null;
		$fila["rutas"] = json_decode($fila["rutas_recortadas"], true) ?: [];
		return $fila;
	}

	// Rutas listas para usar como background-image: array vacío si no hay
	// plantilla subida, y el componente cae al degradado por defecto.
	public function rutasPlantilla($tipo, $id_referencia) {
		$plantilla = $this->obtenerPlantilla($tipo, $id_referencia);
		return $plantilla["rutas"] ?? [];
	}

	// Dibuja la plantilla-guía descargable: un PNG plano del tamaño exacto del
	// lienzo, con las zonas marcadas y etiquetadas para recortar en Photoshop.
	// Devuelve el recurso GD; el llamador decide si lo envía o lo guarda.
	public function generarGuiaPlantilla($tipo) {
		$lienzo = self::lienzoPlantilla($tipo);
		$zonas  = self::zonasPlantilla($tipo);

		$img = imagecreatetruecolor($lienzo["w"], $lienzo["h"]);
		imagefill($img, 0, 0, imagecolorallocate($img, 22, 24, 29));   // --panel

		$linea = imagecolorallocate($img, 232, 117, 42);  // --amber
		$texto = imagecolorallocate($img, 237, 238, 241); // --frost

		// las fuentes internas de GD (imagestring) solo entienden ISO-8859-1:
		// sin esto, cualquier tilde o eñe llega en UTF-8 y sale corrompida
		// (la misma trampa de codificación de §5.3, aquí aplicada a imagestring)
		$latin1 = fn($s) => iconv("UTF-8", "ISO-8859-1//TRANSLIT", $s);

		foreach ($zonas as $nombre => $z) {
			imagerectangle($img, $z["x"], $z["y"], $z["x"] + $z["w"] - 1, $z["y"] + $z["h"] - 1, $linea);
			imagestring($img, 5, $z["x"] + 14, $z["y"] + 14, strtoupper($nombre), $texto);
			imagestring($img, 3, $z["x"] + 14, $z["y"] + 34,
				$z["w"] . "x" . $z["h"] . "px @ (" . $z["x"] . "," . $z["y"] . ")", $texto);
		}

		// hueco libre del lienzo (a la derecha de las zonas en ambos tipos)
		$lineasLeeme = [
			"LEEME",
			"Sustituye cada zona por tu arte real SIN mover ni redimensionarla:",
			"el recorte del servidor usa estas mismas coordenadas exactas.",
			"Cada zona ya tiene la PROPORCION exacta de su cara en el juego,",
			"asi que lo que dibujes aqui es lo que se vera, sin deformarse.",
			"Oculta o borra esta capa de guias antes de exportar.",
			"Exporta como PNG plano de " . $lienzo["w"] . "x" . $lienzo["h"] . "px.",
		];
		$xLeeme = self::esTipoCaja($tipo) ? 414 : 14;
		$yLeeme = self::esTipoCaja($tipo) ? 790 : 586;
		foreach ($lineasLeeme as $i => $linea_texto) {
			imagestring($img, $i === 0 ? 5 : 2, $xLeeme, $yLeeme + $i * 16, $latin1($linea_texto), $texto);
		}

		return $img;
	}

	// Valida, recorta con GD y guarda la plantilla subida por el admin.
	// $ruta_tmp es la ruta temporal del $_FILES subido (PNG plano).
	public function subirPlantilla($tipo, $id_referencia, $ruta_tmp) {
		$lienzo = self::lienzoPlantilla($tipo);
		$zonas  = self::zonasPlantilla($tipo);

		$info = @getimagesize($ruta_tmp);
		if (!$info || $info[2] !== IMAGETYPE_PNG) {
			return ["ok" => false, "error" => "El archivo debe ser un PNG."];
		}
		if ((int) $info[0] !== $lienzo["w"] || (int) $info[1] !== $lienzo["h"]) {
			return ["ok" => false, "error" =>
				"La plantilla debe medir exactamente {$lienzo['w']}×{$lienzo['h']}px " .
				"(el archivo subido mide {$info[0]}×{$info[1]}px)."];
		}

		$origen = @imagecreatefrompng($ruta_tmp);
		if (!$origen) {
			return ["ok" => false, "error" => "No se pudo leer el PNG."];
		}
		imagealphablending($origen, false);
		imagesavealpha($origen, true);

		$carpetaRel = "assets/img/plantillas/{$tipo}_{$id_referencia}";
		$carpetaAbs = __DIR__ . "/../{$carpetaRel}";
		if (!is_dir($carpetaAbs)) mkdir($carpetaAbs, 0775, true);

		$version  = time();
		$rutasWeb = [];

		foreach ($zonas as $nombreZona => $z) {
			$recorte = imagecreatetruecolor($z["w"], $z["h"]);
			imagealphablending($recorte, false);
			imagesavealpha($recorte, true);
			imagecopy($recorte, $origen, 0, 0, $z["x"], $z["y"], $z["w"], $z["h"]);

			$archivo = "{$nombreZona}.png";
			imagepng($recorte, "{$carpetaAbs}/{$archivo}");
			imagedestroy($recorte);

			$rutasWeb[$nombreZona] = "./{$carpetaRel}/{$archivo}?v={$version}";
		}
		imagedestroy($origen);

		copy($ruta_tmp, "{$carpetaAbs}/original.png");
		$rutaOriginalWeb = "./{$carpetaRel}/original.png?v={$version}";

		$stmt = $this->pdo->prepare("
			INSERT INTO plantillas_3d (tipo, id_referencia, ruta_original, rutas_recortadas)
			VALUES (:tipo, :id, :original, :rutas)
			ON DUPLICATE KEY UPDATE
				ruta_original = VALUES(ruta_original),
				rutas_recortadas = VALUES(rutas_recortadas),
				actualizado_en = NOW()
		");
		$stmt->execute([
			":tipo" => $tipo, ":id" => $id_referencia,
			":original" => $rutaOriginalWeb, ":rutas" => json_encode($rutasWeb),
		]);

		return ["ok" => true, "rutas" => $rutasWeb];
	}

	// ==========================================================
	// CONFIGURACIÓN DE PERFIL (configuracion.php)
	// ==========================================================

	// Cambia el nombre de invocador. Comprueba antes con comprobarEmailExiste()
	// que el nuevo nombre no esté ya en uso por OTRO usuario.
	public function actualizarNombreUsuario($id_usuario, $nombre) {
		$stmt = $this->pdo->prepare("UPDATE usuarios SET nombre = :nombre WHERE id_usuario = :id");
		$stmt->execute([
			":nombre" => $nombre,
			":id"     => $id_usuario,
		]);
	}

	// Guarda la ruta (web) de la nueva foto de perfil
	public function actualizarFotoUsuario($id_usuario, $rutaFoto) {
		$stmt = $this->pdo->prepare("UPDATE usuarios SET foto = :foto WHERE id_usuario = :id");
		$stmt->execute([
			":foto" => $rutaFoto,
			":id"   => $id_usuario,
		]);
	}

	// ==========================================================
	// CÓDIGOS CANJEABLES (perfil.php)
	// ==========================================================

	public function obtenerCodigoPorTexto($codigo) {
		$stmt = $this->pdo->prepare("SELECT * FROM codigos WHERE codigo = :codigo");
		$stmt->execute([":codigo" => $codigo]);
		$resultado = $stmt->fetch(PDO::FETCH_ASSOC);
		return $resultado ?: null;
	}

	// Intenta canjear un código para un usuario. Devuelve:
	// ["ok" => true,  "monedas_ganadas" => N, "monedas" => total_actualizado]
	// ["ok" => false, "error" => "mensaje para mostrar"]
	public function canjearCodigo($codigoTexto, $id_usuario) {
		try {
			$this->pdo->beginTransaction();

			// Bloqueamos la fila del código: si dos personas canjean el mismo
			// código 'unico' a la vez, la segunda espera a que la primera
			// termine y entonces ve que ya se ha consumido.
			$stmtCodigo = $this->pdo->prepare("SELECT * FROM codigos WHERE codigo = :codigo FOR UPDATE");
			$stmtCodigo->execute([":codigo" => $codigoTexto]);
			$codigo = $stmtCodigo->fetch(PDO::FETCH_ASSOC);

			if (!$codigo || (int) $codigo["activo"] !== 1) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "Ese código no es válido."];
			}

			if ($codigo["tipo"] === "unico") {
				$stmtUsos = $this->pdo->prepare("SELECT COUNT(*) AS total FROM codigos_canjeados WHERE id_codigo = :id_codigo");
				$stmtUsos->execute([":id_codigo" => $codigo["id_codigo"]]);
				if ((int) $stmtUsos->fetch(PDO::FETCH_ASSOC)["total"] > 0) {
					$this->pdo->rollBack();
					return ["ok" => false, "error" => "Este código ya ha sido usado por otra persona."];
				}
			} else { // 'global'
				$stmtUsos = $this->pdo->prepare("
					SELECT COUNT(*) AS total FROM codigos_canjeados
					WHERE id_codigo = :id_codigo AND id_usuario = :id_usuario
				");
				$stmtUsos->execute([
					":id_codigo"  => $codigo["id_codigo"],
					":id_usuario" => $id_usuario,
				]);
				if ((int) $stmtUsos->fetch(PDO::FETCH_ASSOC)["total"] > 0) {
					$this->pdo->rollBack();
					return ["ok" => false, "error" => "Ya has canjeado este código antes."];
				}
			}

			$this->pdo->prepare("
				INSERT INTO codigos_canjeados (id_codigo, id_usuario, fecha_canje)
				VALUES (:id_codigo, :id_usuario, NOW())
			")->execute([
				":id_codigo"  => $codigo["id_codigo"],
				":id_usuario" => $id_usuario,
			]);

			$this->pdo->prepare("UPDATE usuarios SET monedas = monedas + :monedas WHERE id_usuario = :id_usuario")
				->execute([
					":monedas"    => $codigo["monedas"],
					":id_usuario" => $id_usuario,
				]);

			$stmtUsuario = $this->pdo->prepare("SELECT monedas FROM usuarios WHERE id_usuario = :id");
			$stmtUsuario->execute([":id" => $id_usuario]);
			$monedasTotales = (int) $stmtUsuario->fetch(PDO::FETCH_ASSOC)["monedas"];

			$this->pdo->commit();

			return [
				"ok"              => true,
				"monedas_ganadas" => (int) $codigo["monedas"],
				"monedas"         => $monedasTotales,
			];

		} catch (Exception $e) {
			$this->pdo->rollBack();
			return ["ok" => false, "error" => "Ha ocurrido un error al canjear el código."];
		}
	}

	// ==========================================================
	// MAZOS (mazos.php) — Fase 2
	// ==========================================================

	/** Un mazo es una alineación: ni más ni menos que estas 11 cartas. */
	const MAZO_TAMANO = 11;

	/** Posiciones que pueden entrar en un mazo. Escudos, entrenadores y
	 *  gerentes quedan fuera por decisión de producto: no tienen combate. */
	const POSICIONES_JUGABLES = ["POR", "DF", "MC", "DC"];

	/**
	 * Cuánto pesa cada estadística de la carta según la línea donde la pongas.
	 * Decisión de Alejandro: antes cada línea puntuaba con UNA sola estadística
	 * (Portería/Defensa con defensa, Medio con técnica, Ataque con ataque); ahora
	 * las tres estadísticas de la carta cuentan siempre, pero con distinto peso
	 * según dónde la coloques — así que un portero con buen ataque ya no es un
	 * dato irrelevante, aporta un poco, pero mucho menos que su defensa.
	 *
	 * Los pesos NO están normalizados a que sumen 1: son los que pidió
	 * Alejandro literalmente, y siguen dejando clara cuál es la estadística
	 * "dueña" de cada línea (la de peso más alto) sin que las otras dos sean
	 * cero. Ver aportarCarta().
	 */
	const PESOS_LINEA = [
		"POR" => ["ataque" => 0,    "defensa" => 2,    "tecnica" => 1],
		"DF"  => ["ataque" => 0.25, "defensa" => 1,    "tecnica" => 0.5],
		"MC"  => ["ataque" => 0.5,  "defensa" => 0.5,  "tecnica" => 1],
		"DC"  => ["ataque" => 1.25, "defensa" => 0.15, "tecnica" => 0.75],
	];

	/** Cuánto aporta UNA carta a la línea en la que está colocada. */
	public static function aportarCarta(array $carta, $linea) {
		$pesos = self::PESOS_LINEA[$linea] ?? self::PESOS_LINEA["MC"];
		return (float) ($carta["ataque"]  ?? 0) * $pesos["ataque"]
			 + (float) ($carta["defensa"] ?? 0) * $pesos["defensa"]
			 + (float) ($carta["tecnica"] ?? 0) * $pesos["tecnica"];
	}

	/**
	 * Las formaciones jugables. La clave es lo que se guarda en
	 * `mazos.formacion` y en la formación congelada de un duelo.
	 *
	 * `lineas` es [nº de DF, nº de MC, nº de DC]. El portero es siempre uno,
	 * así que las tres cifras suman 10 y la alineación son siempre 11 huecos:
	 * cambiar de formación reparte los mismos once de otra manera, nunca da
	 * más jugadores ni menos.
	 *
	 * Cualquier carta puede ocupar cualquier hueco a propósito: no hay reglas
	 * de posición. Lo que decide cuánto aporta no es la posición impresa en la
	 * carta sino DÓNDE la coloques, porque cada línea pesa las tres
	 * estadísticas de otra manera (ver PESOS_LINEA). Poner un defensa de
	 * delantero está permitido y rinde según su ataque/técnica/defensa
	 * ponderados como delantero, que para él suele ser malo: ahí está el
	 * metajuego, en decidir esas colocaciones.
	 */
	const FORMACIONES = [
		"442" => ["nombre" => "1-4-4-2", "lineas" => [4, 4, 2]],
		"433" => ["nombre" => "1-4-3-3", "lineas" => [4, 3, 3]],
		"352" => ["nombre" => "1-3-5-2", "lineas" => [3, 5, 2]],
		"532" => ["nombre" => "1-5-3-2", "lineas" => [5, 3, 2]],
		"451" => ["nombre" => "1-4-5-1", "lineas" => [4, 5, 1]],
		"343" => ["nombre" => "1-3-4-3", "lineas" => [3, 4, 3]],
		"541" => ["nombre" => "1-5-4-1", "lineas" => [5, 4, 1]],
		"361" => ["nombre" => "1-3-6-1", "lineas" => [3, 6, 1]],
	];

	/** La de siempre. Es la que se asume cuando no consta ninguna. */
	const FORMACION_BASE = "442";

	/** Disponibles para todos sin desbloquear nada (ver migración 006). */
	const FORMACIONES_LIBRES = ["442", "433"];

	/**
	 * Reparto horizontal de una línea según cuántos jugadores tenga, en % del
	 * ancho del campo. Los valores de 2 y 4 son los que ya tenía el 1-4-4-2
	 * escritos a mano en el CSS, así que esa formación se sigue pintando
	 * exactamente igual que antes de existir las demás.
	 */
	const REPARTO_X = [
		1 => [50],
		2 => [35, 65],
		3 => [22, 50, 78],
		4 => [14, 38, 62, 86],
		// Las líneas de 5 y 6 no llegan hasta la banda: el retrato mide 60px de
		// ancho como mínimo y se dibuja centrado en su coordenada, así que a un
		// 8% el borde se salía del campo y quedaba recortado en móvil (el campo
		// lleva overflow:hidden). Medido a 375px y a 320px de pantalla.
		5 => [12, 31, 50, 69, 88],
		6 => [11, 26, 42, 58, 74, 89],
	];

	/**
	 * Altura de cada línea sobre el campo: [y base, coeficiente de arco,
	 * término independiente], donde la y final es `base + a·d + b` y `d` es lo
	 * lejos del centro que está el jugador (0 en el eje, 1 en la banda).
	 *
	 * El arco no es decorativo: la defensa se comba hacia adelante por las
	 * bandas (los laterales suben) y el medio se comba hacia adelante por el
	 * centro (los interiores pisan área). Es lo que hace que un 1-5-3-2 se lea
	 * como una defensa de cinco y no como una fila recta de cinco puntos.
	 */
	const LINEA_Y = [
		"POR" => [91, 0, 0],
		"DF"  => [77, -25 / 3, 4.5],
		"MC"  => [47, 25 / 3, -4.5],
		"DC"  => [16, 0, 0],
	];

	/** Línea de cada hueco. El índice del array ES `mazo_cartas.hueco`. */
	public static function huecosDe($formacion) {
		$lineas = self::FORMACIONES[$formacion]["lineas"]
			?? self::FORMACIONES[self::FORMACION_BASE]["lineas"];

		$huecos = ["POR"];
		foreach (["DF", "MC", "DC"] as $i => $linea) {
			$huecos = array_merge($huecos, array_fill(0, $lineas[$i], $linea));
		}
		return $huecos;
	}

	/**
	 * Coordenadas de cada hueco sobre el campo, en % — [hueco => [x, y]].
	 *
	 * Viven aquí y no en el CSS a propósito: con ocho formaciones serían 88
	 * reglas `:nth-child` escritas a mano, y el orden de HUECOS y el del CSS
	 * podrían desincronizarse sin que nada fallase de forma visible. Al
	 * derivarse ambas cosas de la misma definición, añadir una formación es
	 * una línea en FORMACIONES y nada más.
	 */
	public static function coordenadasDe($formacion) {
		$huecos = self::huecosDe($formacion);

		$porLinea = [];
		foreach ($huecos as $i => $linea) { $porLinea[$linea][] = $i; }

		$coords = [];
		foreach ($porLinea as $linea => $indices) {
			$xs = self::REPARTO_X[count($indices)] ?? self::REPARTO_X[4];
			[$base, $a, $b] = self::LINEA_Y[$linea];

			foreach ($indices as $n => $hueco) {
				$x = $xs[$n];
				$d = abs($x - 50) / 40;
				$coords[$hueco] = ["x" => $x, "y" => round($base + $a * $d + $b, 1)];
			}
		}
		ksort($coords);
		return $coords;
	}

	/** Formaciones que puede usar un jugador: las libres más las ganadas. */
	public function formacionesDisponibles($id_usuario) {
		$stmt = $this->pdo->prepare("SELECT formacion FROM formaciones_usuario WHERE id_usuario = :id");
		$stmt->execute([":id" => $id_usuario]);
		$ganadas = $stmt->fetchAll(PDO::FETCH_COLUMN);

		$claves = array_merge(self::FORMACIONES_LIBRES, $ganadas);

		// strval() no es cosmético: "442" es una cadena numérica, así que PHP la
		// guarda como el ENTERO 442 al usarla de clave de array. Sin convertirla
		// de vuelta, la comparación estricta contra la cadena de la base de datos
		// no casa nunca y el selector sale vacío.
		$orden = array_map("strval", array_keys(self::FORMACIONES));

		// se devuelven en el orden de FORMACIONES, no en el de desbloqueo, para
		// que el selector no baile de sitio según lo que hayas ganado
		return array_values(array_filter($orden, fn($c) => in_array($c, $claves, true)));
	}

	/** Concede una formación. Repetir la concesión no falla ni duplica. */
	public function desbloquearFormacion($id_usuario, $formacion) {
		if (!isset(self::FORMACIONES[$formacion])) { return false; }
		$this->pdo->prepare("
			INSERT IGNORE INTO formaciones_usuario (id_usuario, formacion) VALUES (:id, :f)
		")->execute([":id" => $id_usuario, ":f" => $formacion]);
		return true;
	}

	/**
	 * Fuerza de una alineación, línea a línea. Recibe lo que devuelve
	 * listarCartasMazo() y devuelve
	 *   ["POR" => n, "DF" => n, "MC" => n, "DC" => n, "total" => n]
	 *
	 * No se suma "la mejor estadística" de cada carta: se suma la de la línea
	 * en la que está puesta. Es lo que hace que colocar mal salga caro sin
	 * necesidad de prohibir nada.
	 *
	 * La formación se pasa explícitamente y no se adivina de las cartas: el
	 * mismo hueco 7 es medio en un 1-4-4-2 y delantero en un 1-3-4-3, así que
	 * dar por supuesta la formación equivocada daría un total plausible pero
	 * falso, del tipo que no revienta y por eso no se detecta.
	 */
	public static function fuerzaAlineacion(array $cartas, $formacion = self::FORMACION_BASE) {
		$lineas = ["POR" => 0, "DF" => 0, "MC" => 0, "DC" => 0];
		$huecos = self::huecosDe($formacion);

		foreach ($cartas as $carta) {
			$hueco = (int) ($carta["hueco"] ?? 0);
			$linea = $huecos[$hueco] ?? "MC";
			$lineas[$linea] += self::aportarCarta($carta, $linea);
		}

		// Ya no son enteros: con pesos como 0.15 o 0.25 la suma exacta es un
		// decimal casi siempre. Se guarda con precisión (lo usa la curva Elo,
		// que sí nota la diferencia) y quien lo enseñe en pantalla redondea al
		// pintarlo, no aquí.
		$lineas["total"] = array_sum($lineas);
		return $lineas;
	}

	// Mazos del usuario con cuántas cartas lleva cada uno, para poder decir en
	// la lista si están completos sin una consulta por mazo.
	public function listarMazosUsuario($id_usuario) {
		$stmt = $this->pdo->prepare("
			SELECT m.id_mazo, m.nombre, m.formacion, m.titular, m.creado,
			       COUNT(mc.id_mazo_carta) AS cartas
			FROM mazos m
			LEFT JOIN mazo_cartas mc ON mc.id_mazo = m.id_mazo
			WHERE m.id_usuario = :id_usuario
			GROUP BY m.id_mazo
			ORDER BY m.titular DESC, m.creado DESC
		");
		$stmt->execute([":id_usuario" => $id_usuario]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	// Devuelve el mazo solo si es de ese usuario: así ninguna pantalla puede
	// enseñar (ni editar) el mazo de otra persona pasando un id a mano.
	public function obtenerMazo($id_mazo, $id_usuario) {
		$stmt = $this->pdo->prepare("
			SELECT id_mazo, id_usuario, nombre, formacion, titular, creado
			FROM mazos WHERE id_mazo = :id_mazo AND id_usuario = :id_usuario
		");
		$stmt->execute([":id_mazo" => $id_mazo, ":id_usuario" => $id_usuario]);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
	}

	// Cartas de un mazo, con todo lo que el componente de tarjeta necesita.
	// Ordenadas por hueco, que es el orden de la alineación sobre el campo.
	public function listarCartasMazo($id_mazo) {
		$stmt = $this->pdo->prepare("
			SELECT
				mc.id_mazo_carta, mc.hueco, col.id_coleccion, col.bloqueada,
				c.id_cromo, c.nombre, c.posicion, c.imagen,
				c.ataque, c.defensa, c.tecnica,
				eq.nombre AS equipo,
				r.id_rareza, r.nombre AS rareza,
				af.nombre AS afinidad, af.imagen AS afinidad_imagen,
				(SELECT rg.nombre FROM cromo_rasgos cr INNER JOIN rasgos rg ON rg.id_rasgo = cr.id_rasgo
				 WHERE cr.id_cromo = c.id_cromo AND rg.tipo = 'configuracion' LIMIT 1) AS rasgo
			FROM mazo_cartas mc
			INNER JOIN coleccion col ON col.id_coleccion = mc.id_coleccion
			INNER JOIN cromos c ON c.id_cromo = col.id_cromo
			INNER JOIN equipos eq ON eq.id_equipo = c.id_equipo
			INNER JOIN rarezas r ON r.id_rareza = c.id_rareza
			INNER JOIN afinidad af ON af.id = c.id_afinidad
			WHERE mc.id_mazo = :id_mazo
			ORDER BY mc.hueco
		");
		$stmt->execute([":id_mazo" => $id_mazo]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	// Cartas de la colección que pueden entrar en un mazo: suyas, de posición
	// jugadora y sin un anuncio activo en el mercado (si está en venta, todavía
	// no se ha vendido, pero meterla en una alineación es pedir un conflicto).
	public function listarColeccionJugable($id_usuario) {
		$marcadores = implode(",", array_fill(0, count(self::POSICIONES_JUGABLES), "?"));
		$stmt = $this->pdo->prepare("
			SELECT
				col.id_coleccion, col.bloqueada,
				c.id_cromo, c.nombre, c.posicion, c.imagen,
				c.ataque, c.defensa, c.tecnica,
				eq.nombre AS equipo,
				r.id_rareza, r.nombre AS rareza,
				af.nombre AS afinidad, af.imagen AS afinidad_imagen,
				(SELECT rg.nombre FROM cromo_rasgos cr INNER JOIN rasgos rg ON rg.id_rasgo = cr.id_rasgo
				 WHERE cr.id_cromo = c.id_cromo AND rg.tipo = 'configuracion' LIMIT 1) AS rasgo
			FROM coleccion col
			INNER JOIN cromos c ON c.id_cromo = col.id_cromo
			INNER JOIN equipos eq ON eq.id_equipo = c.id_equipo
			INNER JOIN rarezas r ON r.id_rareza = c.id_rareza
			INNER JOIN afinidad af ON af.id = c.id_afinidad
			WHERE col.id_usuario = ?
				AND c.posicion IN ($marcadores)
				AND col.id_coleccion NOT IN (SELECT id_coleccion FROM mercado WHERE activa = 1)
			ORDER BY FIELD(c.posicion, 'POR', 'DF', 'MC', 'DC'), r.id_rareza DESC, c.nombre
		");
		$stmt->execute(array_merge([$id_usuario], self::POSICIONES_JUGABLES));
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function crearMazo($id_usuario, $nombre) {
		$stmt = $this->pdo->prepare("INSERT INTO mazos (id_usuario, nombre) VALUES (:id_usuario, :nombre)");
		$stmt->execute([":id_usuario" => $id_usuario, ":nombre" => $nombre]);
		return (int) $this->pdo->lastInsertId();
	}

	public function renombrarMazo($id_mazo, $id_usuario, $nombre) {
		$stmt = $this->pdo->prepare("
			UPDATE mazos SET nombre = :nombre
			WHERE id_mazo = :id_mazo AND id_usuario = :id_usuario
		");
		$stmt->execute([":nombre" => $nombre, ":id_mazo" => $id_mazo, ":id_usuario" => $id_usuario]);
		return $stmt->rowCount() > 0;
	}

	public function eliminarMazo($id_mazo, $id_usuario) {
		$stmt = $this->pdo->prepare("DELETE FROM mazos WHERE id_mazo = :id_mazo AND id_usuario = :id_usuario");
		$stmt->execute([":id_mazo" => $id_mazo, ":id_usuario" => $id_usuario]);
		return $stmt->rowCount() > 0;
	}

	/**
	 * El mazo con el que se duela. Es SIEMPRE el titular: no se elige en cada
	 * duelo, se elige antes en mazos.php y se asume el compromiso.
	 * Devuelve null si no hay titular o si su alineación no está completa.
	 */
	public function obtenerMazoTitular($id_usuario) {
		$stmt = $this->pdo->prepare("
			SELECT id_mazo, id_usuario, nombre, formacion, titular, creado
			FROM mazos WHERE id_usuario = :id_usuario AND titular = 1
			LIMIT 1
		");
		$stmt->execute([":id_usuario" => $id_usuario]);
		$mazo = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$mazo) {
			return null;
		}
		return count($this->listarCartasMazo($mazo["id_mazo"])) === self::MAZO_TAMANO ? $mazo : null;
	}

	// Solo puede haber un titular por usuario: es el mazo con el que se duela.
	public function marcarMazoTitular($id_mazo, $id_usuario) {
		if (!$this->obtenerMazo($id_mazo, $id_usuario)) {
			return false;
		}
		try {
			$this->pdo->beginTransaction();
			$this->pdo->prepare("UPDATE mazos SET titular = 0 WHERE id_usuario = :id_usuario")
				->execute([":id_usuario" => $id_usuario]);
			$this->pdo->prepare("UPDATE mazos SET titular = 1 WHERE id_mazo = :id_mazo AND id_usuario = :id_usuario")
				->execute([":id_mazo" => $id_mazo, ":id_usuario" => $id_usuario]);
			$this->pdo->commit();
			return true;
		} catch (Exception $e) {
			$this->pdo->rollBack();
			return false;
		}
	}

	/**
	 * Sustituye el contenido de un mazo por la alineación recibida.
	 * $porHueco es [hueco => id_coleccion], con hueco de 0 a MAZO_TAMANO-1.
	 * Devuelve ["ok" => bool, "error" => string|null].
	 *
	 * Se valida TODO en servidor aunque la pantalla ya lo impida: que estén los
	 * 11 huecos, que cada copia sea realmente del usuario, que sea de posición
	 * jugable y que no se repita. La interfaz es una comodidad, no una garantía.
	 *
	 * Lo que NO se valida a propósito es la coherencia táctica: cualquier carta
	 * en cualquier hueco es legal. Colocar mal se paga en la fuerza resultante,
	 * no con un rechazo.
	 */
	public function guardarCartasMazo($id_mazo, $id_usuario, array $porHueco, $formacion = null) {
		if (!$this->obtenerMazo($id_mazo, $id_usuario)) {
			return ["ok" => false, "error" => "Ese mazo no es tuyo."];
		}

		// La formación se valida contra las que ESTE jugador tiene: que el
		// selector solo pinte las suyas no impide mandar otra clave a mano.
		if ($formacion !== null && !in_array($formacion, $this->formacionesDisponibles($id_usuario), true)) {
			return ["ok" => false, "error" => "Todavía no has desbloqueado esa formación."];
		}

		// Normalizamos a [hueco => id_coleccion] quedándonos solo con huecos
		// válidos y descartando los vacíos.
		$alineacion = [];
		foreach ($porHueco as $hueco => $idColeccion) {
			$hueco = (int) $hueco;
			$idColeccion = (int) $idColeccion;
			if ($hueco < 0 || $hueco >= self::MAZO_TAMANO || $idColeccion <= 0) {
				continue;
			}
			$alineacion[$hueco] = $idColeccion;
		}

		if (count($alineacion) !== self::MAZO_TAMANO) {
			return ["ok" => false, "error" => "Tienes que cubrir los " . self::MAZO_TAMANO . " huecos de la alineación."];
		}

		$idsColeccion = array_values($alineacion);
		if (count(array_unique($idsColeccion)) !== count($idsColeccion)) {
			return ["ok" => false, "error" => "No puedes poner la misma carta en dos huecos."];
		}

		// Comprobamos de una sola vez que las 11 son suyas y son jugables.
		$marcadoresIds = implode(",", array_fill(0, count($idsColeccion), "?"));
		$marcadoresPos = implode(",", array_fill(0, count(self::POSICIONES_JUGABLES), "?"));
		$stmt = $this->pdo->prepare("
			SELECT col.id_coleccion, col.id_cromo FROM coleccion col
			INNER JOIN cromos c ON c.id_cromo = col.id_cromo
			WHERE col.id_coleccion IN ($marcadoresIds)
				AND col.id_usuario = ?
				AND c.posicion IN ($marcadoresPos)
		");
		$stmt->execute(array_merge($idsColeccion, [$id_usuario], self::POSICIONES_JUGABLES));
		$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if (count($filas) !== count($idsColeccion)) {
			return ["ok" => false, "error" => "Alguna carta no es tuya o no puede jugar."];
		}

		// Un jugador no puede alinearse consigo mismo aunque tengas varias
		// copias suyas. Además de ser lo lógico en un once, evita que apilar
		// duplicados del mismo cromo active sus rasgos de golpe cuando llegue
		// la capa de compos.
		$cromos = array_column($filas, "id_cromo");
		if (count(array_unique($cromos)) !== count($cromos)) {
			return ["ok" => false, "error" => "No puedes alinear dos veces al mismo jugador, aunque tengas copias repetidas."];
		}

		try {
			$this->pdo->beginTransaction();

			if ($formacion !== null) {
				$this->pdo->prepare("UPDATE mazos SET formacion = :f WHERE id_mazo = :id_mazo")
					->execute([":f" => $formacion, ":id_mazo" => $id_mazo]);
			}

			$this->pdo->prepare("DELETE FROM mazo_cartas WHERE id_mazo = :id_mazo")
				->execute([":id_mazo" => $id_mazo]);

			$insertar = $this->pdo->prepare("
				INSERT INTO mazo_cartas (id_mazo, id_coleccion, hueco)
				VALUES (:id_mazo, :id_coleccion, :hueco)
			");
			foreach ($alineacion as $hueco => $idColeccion) {
				$insertar->execute([
					":id_mazo"      => $id_mazo,
					":id_coleccion" => $idColeccion,
					":hueco"        => $hueco,
				]);
			}
			$this->pdo->commit();
			return ["ok" => true, "error" => null];
		} catch (Exception $e) {
			$this->pdo->rollBack();
			return ["ok" => false, "error" => "No se pudo guardar el mazo."];
		}
	}

	// ¿En qué mazos está metida esta copia? Lo usa el mercado para no dejar
	// vender una carta que dejaría un mazo incompleto sin avisar.
	public function mazosQueUsanCopia($id_coleccion) {
		$stmt = $this->pdo->prepare("
			SELECT m.nombre FROM mazo_cartas mc
			INNER JOIN mazos m ON m.id_mazo = mc.id_mazo
			WHERE mc.id_coleccion = :id_coleccion
		");
		$stmt->execute([":id_coleccion" => $id_coleccion]);
		return $stmt->fetchAll(PDO::FETCH_COLUMN);
	}

	// ==========================================================
	// CONFIGURACIÓN (parámetros de balance)
	// ==========================================================

	// La especificación de combate exige que K y el acotado de probabilidad se
	// puedan tocar sin desplegar código, así que viven en BD, no en constantes.
	private $configCache = null;

	/**
	 * Un parámetro de balance. Se lee la tabla ENTERA en la primera llamada y
	 * se guarda en memoria durante la petición: son una veintena de filas, y
	 * antes cada consulta de un parámetro era una consulta suelta a la base de
	 * datos —resolverDuelo() encadena decenas entre compos, curva y marcador—.
	 *
	 * La caché vive solo lo que dura la petición. Nada escribe en
	 * `configuracion` en tiempo de ejecución (se siembra por SQL), así que no
	 * puede quedarse obsoleta; si algún día el panel la edita, hay que vaciarla
	 * al guardar.
	 */
	public function config($clave, $porDefecto = null) {
		if ($this->configCache === null) {
			$this->configCache = $this->pdo
				->query("SELECT clave, valor FROM configuracion")
				->fetchAll(PDO::FETCH_KEY_PAIR);
		}
		return $this->configCache[$clave] ?? $porDefecto;
	}

	// ==========================================================
	// DUELOS (duelos.php) — Fase 2
	// ==========================================================

	/**
	 * Copias que se pueden poner en juego: suyas, sin proteger, sin anuncio
	 * activo, sin estar en un mazo y sin estar ya apostadas en un duelo vivo.
	 * $idRareza filtra por la rareza pactada en la sala.
	 */
	public function listarCopiasApostables($id_usuario, $idRareza = null) {
		$sql = "
			SELECT
				col.id_coleccion,
				c.id_cromo, c.nombre, c.posicion, c.imagen,
				c.ataque, c.defensa, c.tecnica,
				eq.nombre AS equipo,
				r.id_rareza, r.nombre AS rareza,
				af.nombre AS afinidad, af.imagen AS afinidad_imagen,
				(SELECT rg.nombre FROM cromo_rasgos cr INNER JOIN rasgos rg ON rg.id_rasgo = cr.id_rasgo
				 WHERE cr.id_cromo = c.id_cromo AND rg.tipo = 'configuracion' LIMIT 1) AS rasgo
			FROM coleccion col
			INNER JOIN cromos c ON c.id_cromo = col.id_cromo
			INNER JOIN equipos eq ON eq.id_equipo = c.id_equipo
			INNER JOIN rarezas r ON r.id_rareza = c.id_rareza
			INNER JOIN afinidad af ON af.id = c.id_afinidad
			WHERE col.id_usuario = :id_usuario
				AND col.bloqueada = 0
				AND col.id_coleccion NOT IN (SELECT id_coleccion FROM mercado WHERE activa = 1)
				AND col.id_coleccion NOT IN (SELECT id_coleccion FROM mazo_cartas)
				AND col.id_coleccion NOT IN (
					SELECT da.id_coleccion FROM duelo_apuestas da
					INNER JOIN duelos d ON d.id_duelo = da.id_duelo
					WHERE da.id_coleccion IS NOT NULL AND d.estado NOT IN ('resuelto','cancelado')
				)
		";
		$params = [":id_usuario" => $id_usuario];
		if ($idRareza !== null) {
			$sql .= " AND c.id_rareza = :id_rareza";
			$params[":id_rareza"] = $idRareza;
		}
		$sql .= " ORDER BY r.id_rareza DESC, c.nombre";

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	// Congela los 11 huecos del mazo dentro del duelo. Se copian las cifras, no
	// una referencia: a partir de aquí, editar el mazo no altera este duelo.
	private function congelarAlineacion($id_duelo, $id_usuario, $id_mazo) {
		$cartas = $this->listarCartasMazo($id_mazo);
		if (count($cartas) !== self::MAZO_TAMANO) {
			return false;
		}
		$insertar = $this->pdo->prepare("
			INSERT INTO duelo_alineaciones (id_duelo, id_usuario, hueco, id_cromo, ataque, defensa, tecnica)
			VALUES (:id_duelo, :id_usuario, :hueco, :id_cromo, :ataque, :defensa, :tecnica)
		");
		foreach ($cartas as $c) {
			$insertar->execute([
				":id_duelo"   => $id_duelo,
				":id_usuario" => $id_usuario,
				":hueco"      => (int) $c["hueco"],
				":id_cromo"   => (int) $c["id_cromo"],
				":ataque"     => (int) $c["ataque"],
				":defensa"    => (int) $c["defensa"],
				":tecnica"    => (int) $c["tecnica"],
			]);
		}
		return true;
	}

	// La alineación congelada de un jugador, en formato compatible con
	// fuerzaAlineacion() y con el componente de tarjeta.
	public function listarAlineacionDuelo($id_duelo, $id_usuario) {
		$stmt = $this->pdo->prepare("
			SELECT
				da.hueco, da.ataque, da.defensa, da.tecnica,
				c.id_cromo, c.nombre, c.posicion, c.imagen,
				eq.nombre AS equipo,
				r.id_rareza, r.nombre AS rareza,
				af.nombre AS afinidad, af.imagen AS afinidad_imagen,
				(SELECT rg.nombre FROM cromo_rasgos cr INNER JOIN rasgos rg ON rg.id_rasgo = cr.id_rasgo
				 WHERE cr.id_cromo = c.id_cromo AND rg.tipo = 'configuracion' LIMIT 1) AS rasgo
			FROM duelo_alineaciones da
			INNER JOIN cromos c ON c.id_cromo = da.id_cromo
			INNER JOIN equipos eq ON eq.id_equipo = c.id_equipo
			INNER JOIN rarezas r ON r.id_rareza = c.id_rareza
			INNER JOIN afinidad af ON af.id = c.id_afinidad
			WHERE da.id_duelo = :id_duelo AND da.id_usuario = :id_usuario
			ORDER BY da.hueco
		");
		$stmt->execute([":id_duelo" => $id_duelo, ":id_usuario" => $id_usuario]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Abre una sala. Cobra o retiene la apuesta en el acto: duelar con monedas
	 * que ya no tienes cuando entre el rival sería una promesa falsa.
	 */
	public function crearDuelo($id_usuario, $tipoApuesta, $monedas, $idRareza = null, $idColeccionApuesta = null) {
		$monedas = max(0, (int) $monedas);

		try {
			$this->pdo->beginTransaction();

			$mazo = $this->obtenerMazoTitular($id_usuario);
			if (!$mazo) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "Necesitas un mazo titular con los 11 huecos cubiertos."];
			}
			$id_mazo = (int) $mazo["id_mazo"];

			if ($tipoApuesta === "monedas") {
				if ($monedas <= 0) {
					$this->pdo->rollBack();
					return ["ok" => false, "error" => "Indica cuántas monedas quieres apostar."];
				}
				$stmt = $this->pdo->prepare("SELECT monedas FROM usuarios WHERE id_usuario = :id FOR UPDATE");
				$stmt->execute([":id" => $id_usuario]);
				if ((int) $stmt->fetchColumn() < $monedas) {
					$this->pdo->rollBack();
					return ["ok" => false, "error" => "No tienes monedas suficientes."];
				}
			} else {
				$apostables = array_column($this->listarCopiasApostables($id_usuario, $idRareza), "id_coleccion");
				if (!in_array((int) $idColeccionApuesta, array_map("intval", $apostables), true)) {
					$this->pdo->rollBack();
					return ["ok" => false, "error" => "Esa carta no está disponible para apostar."];
				}
				$monedas = 0;
			}

			// ultimo_latido arranca ya: el creador entra en la sala en el mismo
			// acto de crearla, y desde ese momento tiene que seguir latiendo.
			$this->pdo->prepare("
				INSERT INTO duelos (id_creador, id_mazo_creador, formacion_creador, tipo_apuesta, monedas, id_rareza_apuesta, estado, ultimo_latido)
				VALUES (:id_creador, :id_mazo, :formacion, :tipo, :monedas, :id_rareza, 'creado', NOW())
			")->execute([
				":id_creador" => $id_usuario,
				":id_mazo"    => $id_mazo,
				":formacion"  => $mazo["formacion"] ?? self::FORMACION_BASE,
				":tipo"       => $tipoApuesta,
				":monedas"    => $monedas,
				":id_rareza"  => $tipoApuesta === "carta" ? $idRareza : null,
			]);
			$idDuelo = (int) $this->pdo->lastInsertId();

			$this->pdo->prepare("
				INSERT INTO duelo_apuestas (id_duelo, id_usuario, monedas, id_coleccion)
				VALUES (:id_duelo, :id_usuario, :monedas, :id_coleccion)
			")->execute([
				":id_duelo"     => $idDuelo,
				":id_usuario"   => $id_usuario,
				":monedas"      => $monedas,
				":id_coleccion" => $tipoApuesta === "carta" ? (int) $idColeccionApuesta : null,
			]);

			if ($tipoApuesta === "monedas") {
				$this->pdo->prepare("UPDATE usuarios SET monedas = monedas - :m WHERE id_usuario = :id")
					->execute([":m" => $monedas, ":id" => $id_usuario]);
			}

			if (!$this->congelarAlineacion($idDuelo, $id_usuario, $id_mazo)) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "No se pudo congelar la alineación."];
			}

			$this->pdo->commit();
			return ["ok" => true, "error" => null, "id_duelo" => $idDuelo];

		} catch (Exception $e) {
			$this->pdo->rollBack();
			return ["ok" => false, "error" => "No se pudo crear el duelo."];
		}
	}

	/**
	 * Entra en una sala abierta. Retiene la apuesta del rival, congela su
	 * alineación y deja el duelo en 'aceptado'.
	 *
	 * NO resuelve aquí: la resolución es un paso aparte (resolverDuelo) porque
	 * en cuanto exista la fase de aumento habrá estados intermedios entre
	 * aceptar y resolver.
	 */
	public function aceptarDuelo($id_duelo, $id_usuario, $idColeccionApuesta = null) {
		// Una sala cuyo creador se fue no debe poder aceptarse: se limpia antes
		// de mirar, para no entrar en un duelo que ya no tiene a nadie enfrente.
		$this->cancelarSalasAbandonadas();

		try {
			$this->pdo->beginTransaction();

			$stmt = $this->pdo->prepare("SELECT * FROM duelos WHERE id_duelo = :id FOR UPDATE");
			$stmt->execute([":id" => $id_duelo]);
			$duelo = $stmt->fetch(PDO::FETCH_ASSOC);

			if (!$duelo || $duelo["estado"] !== "creado") {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "Esta sala ya no está disponible."];
			}
			if ((int) $duelo["id_creador"] === (int) $id_usuario) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "No puedes entrar en tu propia sala."];
			}

			$mazo = $this->obtenerMazoTitular($id_usuario);
			if (!$mazo) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "Necesitas un mazo titular con los 11 huecos cubiertos."];
			}
			$id_mazo = (int) $mazo["id_mazo"];

			$monedas = (int) $duelo["monedas"];

			if ($duelo["tipo_apuesta"] === "monedas") {
				$stmt = $this->pdo->prepare("SELECT monedas FROM usuarios WHERE id_usuario = :id FOR UPDATE");
				$stmt->execute([":id" => $id_usuario]);
				if ((int) $stmt->fetchColumn() < $monedas) {
					$this->pdo->rollBack();
					return ["ok" => false, "error" => "No tienes monedas suficientes para igualar la apuesta."];
				}
				$this->pdo->prepare("UPDATE usuarios SET monedas = monedas - :m WHERE id_usuario = :id")
					->execute([":m" => $monedas, ":id" => $id_usuario]);
			} else {
				$apostables = array_column(
					$this->listarCopiasApostables($id_usuario, (int) $duelo["id_rareza_apuesta"]), "id_coleccion");
				if (!in_array((int) $idColeccionApuesta, array_map("intval", $apostables), true)) {
					$this->pdo->rollBack();
					return ["ok" => false, "error" => "Necesitas una carta disponible de esa rareza."];
				}
				$monedas = 0;

				// La carta del creador tiene que seguir siendo suya y estar libre:
				// pudo venderla o meterla en un mazo mientras la sala esperaba.
				$stmtCreador = $this->pdo->prepare("
					SELECT da.id_coleccion FROM duelo_apuestas da
					INNER JOIN coleccion col ON col.id_coleccion = da.id_coleccion
					WHERE da.id_duelo = :id_duelo AND da.id_usuario = :id_creador
						AND col.id_usuario = :id_creador
				");
				$stmtCreador->execute([":id_duelo" => $id_duelo, ":id_creador" => $duelo["id_creador"]]);
				if (!$stmtCreador->fetchColumn()) {
					$this->pdo->prepare("UPDATE duelos SET estado = 'cancelado' WHERE id_duelo = :id")
						->execute([":id" => $id_duelo]);
					$this->pdo->commit();
					return ["ok" => false, "error" => "La carta apostada por el creador ya no está disponible. La sala se ha cancelado."];
				}
			}

			$this->pdo->prepare("
				INSERT INTO duelo_apuestas (id_duelo, id_usuario, monedas, id_coleccion)
				VALUES (:id_duelo, :id_usuario, :monedas, :id_coleccion)
			")->execute([
				":id_duelo"     => $id_duelo,
				":id_usuario"   => $id_usuario,
				":monedas"      => $monedas,
				":id_coleccion" => $duelo["tipo_apuesta"] === "carta" ? (int) $idColeccionApuesta : null,
			]);

			if (!$this->congelarAlineacion($id_duelo, $id_usuario, $id_mazo)) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "No se pudo congelar la alineación."];
			}

			// Al entrar el rival, el duelo pasa directamente a la fase de
			// aumento: ambos jugadores van a la pantalla de partido y lo primero
			// que ven son sus 3 opciones, con un plazo para decidir.
			$plazo = (int) $this->config("duelo_plazo_aumento", 30);

			$this->pdo->prepare("
				UPDATE duelos SET
					id_rival = :id_rival, id_mazo_rival = :id_mazo,
					formacion_rival = :formacion,
					estado = 'aumento_pendiente',
					aumento_vence = DATE_ADD(NOW(), INTERVAL :plazo SECOND)
				WHERE id_duelo = :id_duelo
			")->execute([
				":id_rival"   => $id_usuario,
				":id_mazo"    => $id_mazo,
				":formacion"  => $mazo["formacion"] ?? self::FORMACION_BASE,
				":plazo"      => $plazo,
				":id_duelo"   => $id_duelo,
			]);

			// --- Capa 2: las compos se congelan junto a la alineación ---
			// Tiene que ser aquí y no al resolver: la Tensión decide con qué
			// probabilidades se sortean los tiers del Aumento, y el Aumento se
			// genera en este mismo bloque, unas líneas más abajo.
			$composCreador = $this->calcularCompos($this->listarAlineacionDuelo($id_duelo, (int) $duelo["id_creador"]));
			$composRival   = $this->calcularCompos($this->listarAlineacionDuelo($id_duelo, $id_usuario));

			$this->congelarCompos($id_duelo, (int) $duelo["id_creador"], $composCreador);
			$this->congelarCompos($id_duelo, $id_usuario, $composRival);

			$this->pdo->prepare("
				UPDATE duelos SET
					afinidad_dom_creador = :afc, afinidad_dom_rival = :afr,
					malus_coh_creador = :mcc, malus_coh_rival = :mcr,
					tension_creador = :tc, tension_rival = :tr
				WHERE id_duelo = :id_duelo
			")->execute([
				":afc" => $composCreador["afinidad_dom"], ":afr" => $composRival["afinidad_dom"],
				":mcc" => $composCreador["malus"],        ":mcr" => $composRival["malus"],
				":tc"  => $composCreador["tension_nivel"], ":tr" => $composRival["tension_nivel"],
				":id_duelo" => $id_duelo,
			]);

			// Las opciones se generan y persisten AQUÍ, antes de que nadie las
			// vea, para que no dependan de quién cargue antes la pantalla.
			$this->generarAumentos($id_duelo, (int) $duelo["id_creador"], $composCreador["tension_nivel"]);
			$this->generarAumentos($id_duelo, $id_usuario, $composRival["tension_nivel"]);

			$this->pdo->commit();
			return ["ok" => true, "error" => null, "id_duelo" => (int) $id_duelo];

		} catch (Exception $e) {
			$this->pdo->rollBack();
			return ["ok" => false, "error" => "No se pudo entrar en el duelo."];
		}
	}

	// ==========================================================
	// AUMENTO PRE-PARTIDO (Capa 3 de la especificación de combate)
	// ==========================================================

	/**
	 * Tiers del aumento: probabilidad de salir y rango de porcentaje.
	 * Valores cerrados en la especificación (§6.1), no son parámetros de
	 * balance abiertos.
	 */
	const AUMENTO_TIERS = [
		"plata"  => ["prob" => 60, "min" => 0.00, "max" => 1.99],
		"oro"    => ["prob" => 30, "min" => 2.00, "max" => 3.49],
		"prisma" => ["prob" => 10, "min" => 3.50, "max" => 5.00],
	];

	/**
	 * A qué categorías afecta cada estadística elegida (§6.4).
	 * `defensa` toca DOS líneas con el mismo porcentaje; el aumento nunca
	 * afecta al TOTAL, solo a categorías.
	 */
	const AUMENTO_CATEGORIAS = [
		"ataque"  => ["DC"],
		"tecnica" => ["MC"],
		"defensa" => ["POR", "DF"],
	];

	/**
	 * Genera y persiste las 3 opciones de un jugador para un duelo.
	 *
	 * Se llama UNA sola vez por (duelo, jugador). Si ya existen, no hace nada:
	 * regenerarlas convertiría recargar la página en tiradas gratis hasta sacar
	 * un Prisma. La clave única (id_duelo, id_usuario, opcion) lo blinda además
	 * a nivel de base de datos.
	 *
	 * $probsOverride existe para el rival de las cadenas, cuya tabla de tiers la
	 * fija la dificultad y no su Tensión. El resto de la generación es el mismo
	 * a propósito: un aumento del bot tiene que salir del mismo bombo que el de
	 * una persona, o dejaría de ser comparable.
	 */
	public function generarAumentos($id_duelo, $id_usuario, $tensionNivel = 0, array $probsOverride = null) {
		$stmt = $this->pdo->prepare("
			SELECT COUNT(*) FROM duelo_aumentos WHERE id_duelo = :d AND id_usuario = :u
		");
		$stmt->execute([":d" => $id_duelo, ":u" => $id_usuario]);
		if ((int) $stmt->fetchColumn() > 0) {
			return false;
		}

		$stats = array_keys(self::AUMENTO_CATEGORIAS);

		// Capa 2 (§3.6): la Tensión no da fuerza, mejora estas probabilidades.
		// Nivel 0 devuelve exactamente la tabla base (60/30/10), así que un
		// jugador sin Tensión no nota ninguna diferencia respecto a antes.
		$probs = $probsOverride ?? $this->probabilidadesTier($tensionNivel);

		$insertar = $this->pdo->prepare("
			INSERT IGNORE INTO duelo_aumentos (id_duelo, id_usuario, opcion, stat, tier, porcentaje, tension_nivel)
			VALUES (:d, :u, :opcion, :stat, :tier, :pct, :tension)
		");

		for ($opcion = 1; $opcion <= 3; $opcion++) {
			// Tier por muestreo ponderado.
			$tirada = mt_rand(1, 100);
			$acumulado = 0;
			$tierElegido = "plata";
			foreach ($probs as $nombre => $prob) {
				$acumulado += $prob;
				if ($tirada <= $acumulado) { $tierElegido = $nombre; break; }
			}

			// Porcentaje uniforme dentro del rango, a 2 decimales. Un 0,00 de
			// Plata es un resultado válido, no un error: simplemente no aporta.
			$rango = self::AUMENTO_TIERS[$tierElegido];
			$pct = round(mt_rand((int) ($rango["min"] * 100), (int) ($rango["max"] * 100)) / 100, 2);

			// Estadística uniforme CON repetición: que las 3 opciones compartan
			// estadística es un resultado esperado, no algo que corregir.
			$stat = $stats[mt_rand(0, count($stats) - 1)];

			$insertar->execute([
				":d" => $id_duelo, ":u" => $id_usuario,
				":opcion" => $opcion, ":stat" => $stat,
				":tier" => $tierElegido, ":pct" => $pct,
				":tension" => (int) $tensionNivel,
			]);
		}
		return true;
	}

	/**
	 * Probabilidades de tier del Aumento para un nivel de Tensión (§3.6).
	 * Devuelve ["plata" => %, "oro" => %, "prisma" => %] en el mismo orden que
	 * AUMENTO_TIERS, porque el muestreo acumulado depende del orden.
	 */
	public function probabilidadesTier($tensionNivel = 0) {
		$nivel = max(0, min(3, (int) $tensionNivel));
		$porDefecto = ["plata" => 60, "oro" => 30, "prisma" => 10];

		$crudo = (string) $this->config("tension_tiers_" . $nivel, "");
		$partes = array_map("floatval", array_filter(explode(",", $crudo), "strlen"));
		if (count($partes) !== 3) { return $porDefecto; }

		return ["plata" => $partes[0], "oro" => $partes[1], "prisma" => $partes[2]];
	}

	// Las 3 opciones de un jugador. Nunca se piden las del rival: verlas antes
	// de resolver sería jugar con ventaja (§6.3).
	public function listarAumentos($id_duelo, $id_usuario) {
		$stmt = $this->pdo->prepare("
			SELECT * FROM duelo_aumentos
			WHERE id_duelo = :d AND id_usuario = :u ORDER BY opcion
		");
		$stmt->execute([":d" => $id_duelo, ":u" => $id_usuario]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function aumentoElegido($id_duelo, $id_usuario) {
		$stmt = $this->pdo->prepare("
			SELECT * FROM duelo_aumentos
			WHERE id_duelo = :d AND id_usuario = :u AND elegida = 1 LIMIT 1
		");
		$stmt->execute([":d" => $id_duelo, ":u" => $id_usuario]);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
	}

	/**
	 * Marca una opción como elegida. Es definitivo: si ya había una elegida no
	 * se cambia, ni recargando ni reenviando el formulario.
	 */
	public function elegirAumento($id_duelo, $id_usuario, $opcion) {
		if ($this->aumentoElegido($id_duelo, $id_usuario)) {
			return false;
		}
		$stmt = $this->pdo->prepare("
			UPDATE duelo_aumentos SET elegida = 1
			WHERE id_duelo = :d AND id_usuario = :u AND opcion = :o
		");
		$stmt->execute([":d" => $id_duelo, ":u" => $id_usuario, ":o" => (int) $opcion]);
		return $this->aumentoElegido($id_duelo, $id_usuario) !== null;
	}

	/**
	 * Vencido el plazo, elige por quien no haya elegido.
	 *
	 * NOTA: la especificación (§6.5) fijaba "la opción de porcentaje más bajo",
	 * argumentando que así el sistema es determinista y auditable. Alejandro
	 * decidió que sea ALEATORIA entre las tres, para no castigar al que no llega
	 * a tiempo. Sigue sin premiar la inacción (elegir siempre es mejor o igual)
	 * pero deja de ser determinista, así que se marca `por_defecto` para poder
	 * distinguir después una elección real de una automática.
	 */
	public function aplicarFallbackAumentos($id_duelo) {
		$stmt = $this->pdo->prepare("
			SELECT id_creador, id_rival, estado, aumento_vence,
			       (aumento_vence IS NOT NULL AND NOW() >= aumento_vence) AS vencido
			FROM duelos WHERE id_duelo = :d
		");
		$stmt->execute([":d" => $id_duelo]);
		$duelo = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$duelo || $duelo["estado"] !== "aumento_pendiente" || !$duelo["vencido"]) {
			return false;
		}

		foreach ([$duelo["id_creador"], $duelo["id_rival"]] as $idJugador) {
			if (!$idJugador || $this->aumentoElegido($id_duelo, $idJugador)) {
				continue;
			}
			$opciones = $this->listarAumentos($id_duelo, $idJugador);
			if (!$opciones) { continue; }

			$elegida = $opciones[mt_rand(0, count($opciones) - 1)];
			$this->pdo->prepare("
				UPDATE duelo_aumentos SET elegida = 1, por_defecto = 1 WHERE id_aumento = :id
			")->execute([":id" => $elegida["id_aumento"]]);
		}
		return true;
	}

	/**
	 * ¿Han elegido ya los dos? Si sí, el duelo pasa a poder resolverse.
	 * No existe un estado "medio listo": mientras falte cualquiera de los dos,
	 * el duelo sigue en aumento_pendiente (§7).
	 */
	public function cerrarFaseAumento($id_duelo) {
		$this->aplicarFallbackAumentos($id_duelo);

		$stmt = $this->pdo->prepare("SELECT id_creador, id_rival, estado FROM duelos WHERE id_duelo = :d");
		$stmt->execute([":d" => $id_duelo]);
		$duelo = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$duelo || $duelo["estado"] !== "aumento_pendiente") {
			return false;
		}

		$ambos = $this->aumentoElegido($id_duelo, $duelo["id_creador"])
			&& $this->aumentoElegido($id_duelo, $duelo["id_rival"]);

		if (!$ambos) {
			return false;
		}

		$this->pdo->prepare("
			UPDATE duelos SET estado = 'listo_para_resolver'
			WHERE id_duelo = :d AND estado = 'aumento_pendiente'
		")->execute([":d" => $id_duelo]);
		return true;
	}

	/**
	 * Bonos por categoría que aporta el aumento elegido de un jugador.
	 * Devuelve ['POR'=>%, 'DF'=>%, 'MC'=>%, 'DC'=>%].
	 */
	public function bonosAumento($id_duelo, $id_usuario) {
		$bonos = ["POR" => 0.0, "DF" => 0.0, "MC" => 0.0, "DC" => 0.0];
		$elegido = $this->aumentoElegido($id_duelo, $id_usuario);
		if (!$elegido) {
			return $bonos;
		}
		foreach (self::AUMENTO_CATEGORIAS[$elegido["stat"]] as $categoria) {
			$bonos[$categoria] += (float) $elegido["porcentaje"];
		}
		return $bonos;
	}

	/**
	 * Fórmula maestra del TOTAL final (§8 de la especificación).
	 *
	 * El orden importa y es justo lo que la especificación vino a fijar: los
	 * bonos de CATEGORÍA se aplican sobre cada línea, se suman las líneas ya
	 * ajustadas, y solo entonces se aplica el bono de TOTAL sobre esa suma.
	 * Nunca se encadenan multiplicaciones sobre un valor ya multiplicado.
	 *
	 * $bonosCategoria y $bonoTotal vendrán de rasgos (Capa 2) cuando exista;
	 * hoy los rasgos aportan 0 y solo entra el aumento.
	 */
	public static function calcularTotalFinal(array $fuerzaBruta, array $bonosCategoria, $bonoTotal = 0.0) {
		$intermedio = 0.0;
		$ajustadas = [];

		foreach (["POR", "DF", "MC", "DC"] as $categoria) {
			$pct = (float) ($bonosCategoria[$categoria] ?? 0);
			$ajustadas[$categoria] = ((float) $fuerzaBruta[$categoria]) * (1 + $pct / 100);
			$intermedio += $ajustadas[$categoria];
		}

		return [
			"categorias" => $ajustadas,
			"intermedio" => $intermedio,
			"final"      => $intermedio * (1 + ((float) $bonoTotal) / 100),
		];
	}

	// ==========================================================
	// CAPA 2 — COMPOS (rasgos y sinergias)
	// ==========================================================

	/**
	 * Ciclo de contra-afinidad. Es canon de Inazuma Eleven (Fūrinkazan) y NO
	 * se toca: lo único ajustable es la magnitud del bonus, que vive en
	 * `configuracion`. clave => a quién vence.
	 */
	const CICLO_AFINIDAD = [
		"fuego"   => "bosque",
		"bosque"  => "viento",
		"viento"  => "montana",
		"montana" => "fuego",
	];

	/** Nombre de afinidad en BD => clave de rasgo. */
	const AFINIDAD_A_RASGO = [
		"Fuego"   => "fuego",
		"Bosque"  => "bosque",
		"Viento"  => "viento",
		"Montaña" => "montana",
	];

	/**
	 * Derivación automática del rasgo de CONFIGURACIÓN de cada carta jugadora.
	 *
	 * Por qué no se deriva de las estadísticas (ataque/defensa/tecnica), que
	 * sería lo primero que uno intentaría: esas tres columnas se sembraron con
	 * una fórmula de base-por-rareza + ajuste-por-posición, así que no contienen
	 * información independiente. Derivar de ellas daría un rasgo que es un calco
	 * de la posición (todos los DC saldrían iguales) y que además CORRELACIONA
	 * CON LA RAREZA — lo cual anularía el malus de coherencia, cuyo objetivo es
	 * justamente que la rareza alta no venga con compos gratis.
	 *
	 * Se deriva del cruce (línea del puesto − línea de la afinidad) mod 4. Como
	 * ambas se mueven en la misma escala de 4 líneas, el resultado es un
	 * cuadrado latino: cada rasgo cae en las 4 posiciones Y en las 4 afinidades,
	 * de modo que es ortogonal a las dos, y no correlaciona con la rareza.
	 *
	 * Nunca pisa una asignación con `manual = 1`: el panel puede curar a mano
	 * las cartas que interese sin que una rederivación las revierta.
	 *
	 * Devuelve cuántas filas ha creado o actualizado.
	 */
	public function derivarRasgosConfiguracion() {
		$LINEA_POS = ["POR" => 0, "DF" => 1, "MC" => 2, "DC" => 3];
		$LINEA_AFI = ["Montaña" => 0, "Viento" => 1, "Bosque" => 2, "Fuego" => 3];
		$POR_RESTO = [0 => "contraataque", 1 => "justicia", 2 => "vinculo", 3 => "brecha"];

		$idsRasgo = [];
		foreach ($this->pdo->query("SELECT id_rasgo, clave FROM rasgos WHERE tipo = 'configuracion'") as $fila) {
			$idsRasgo[$fila["clave"]] = (int) $fila["id_rasgo"];
		}
		if (!$idsRasgo) { return 0; }

		$marcadores = implode(",", array_fill(0, count(self::POSICIONES_JUGABLES), "?"));
		$stmt = $this->pdo->prepare("
			SELECT c.id_cromo, c.posicion, af.nombre AS afinidad
			FROM cromos c
			INNER JOIN afinidad af ON af.id = c.id_afinidad
			WHERE c.posicion IN ($marcadores)
		");
		$stmt->execute(self::POSICIONES_JUGABLES);
		$cartas = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$idsConfig = array_values($idsRasgo);
		$enConfig  = implode(",", array_fill(0, count($idsConfig), "?"));

		// Borra solo lo derivado automáticamente de esa carta, nunca lo manual.
		$borrar = $this->pdo->prepare("
			DELETE FROM cromo_rasgos
			WHERE id_cromo = ? AND manual = 0 AND id_rasgo IN ($enConfig)
		");
		$tieneManual = $this->pdo->prepare("
			SELECT COUNT(*) FROM cromo_rasgos
			WHERE id_cromo = ? AND manual = 1 AND id_rasgo IN ($enConfig)
		");
		$insertar = $this->pdo->prepare("
			INSERT IGNORE INTO cromo_rasgos (id_cromo, id_rasgo, manual) VALUES (?, ?, 0)
		");

		$tocadas = 0;
		$this->pdo->beginTransaction();
		try {
			foreach ($cartas as $carta) {
				$p = $LINEA_POS[$carta["posicion"]] ?? null;
				$a = $LINEA_AFI[$carta["afinidad"]] ?? null;
				// Sin afinidad real (p. ej. "no-afi") no hay cruce que derivar.
				if ($p === null || $a === null) { continue; }

				$tieneManual->execute(array_merge([$carta["id_cromo"]], $idsConfig));
				if ((int) $tieneManual->fetchColumn() > 0) { continue; }

				$borrar->execute(array_merge([$carta["id_cromo"]], $idsConfig));

				$clave = $POR_RESTO[(($p - $a) % 4 + 4) % 4];
				$insertar->execute([$carta["id_cromo"], $idsRasgo[$clave]]);
				$tocadas++;
			}
			$this->pdo->commit();
		} catch (Exception $e) {
			$this->pdo->rollBack();
			return 0;
		}
		return $tocadas;
	}

	/**
	 * Fija (o quita) a mano el rasgo de CONFIGURACIÓN de un cromo, desde el panel.
	 *
	 * $id_rasgo = null vuelve al automático: borra el override manual y deja
	 * que la próxima derivarRasgosConfiguracion() (el panel la llama siempre
	 * justo después de crear/editar un cromo) le asigne el que le toque por
	 * posición × afinidad.
	 *
	 * $id_rasgo = un id concreto lo fija con manual = 1, así que
	 * derivarRasgosConfiguracion() nunca lo vuelve a tocar. Sirve tanto para
	 * elegir uno a mano como para el botón "Aleatorio" (el panel ya resuelve
	 * qué id le toca antes de llamar aquí).
	 */
	public function asignarRasgoManual($id_cromo, $id_rasgo) {
		$this->pdo->prepare("
			DELETE FROM cromo_rasgos
			WHERE id_cromo = :id_cromo
			  AND id_rasgo IN (SELECT id_rasgo FROM rasgos WHERE tipo = 'configuracion')
		")->execute([":id_cromo" => $id_cromo]);

		if ($id_rasgo !== null) {
			$this->pdo->prepare("
				INSERT INTO cromo_rasgos (id_cromo, id_rasgo, manual) VALUES (:id_cromo, :id_rasgo, 1)
			")->execute([":id_cromo" => $id_cromo, ":id_rasgo" => $id_rasgo]);
		}
	}

	/** Catálogo de rasgos indexado por clave. Se consulta varias veces por duelo. */
	public function rasgosCatalogo() {
		static $cache = null;
		if ($cache !== null) { return $cache; }

		$cache = [];
		foreach ($this->pdo->query("SELECT * FROM rasgos") as $r) {
			$cache[$r["clave"]] = $r;
		}
		return $cache;
	}

	/** Rasgos de configuración de un conjunto de cromos: [id_cromo => [clave, ...]]. */
	private function rasgosConfigDeCromos(array $idsCromo) {
		if (!$idsCromo) { return []; }
		$marcadores = implode(",", array_fill(0, count($idsCromo), "?"));
		$stmt = $this->pdo->prepare("
			SELECT cr.id_cromo, r.clave
			FROM cromo_rasgos cr
			INNER JOIN rasgos r ON r.id_rasgo = cr.id_rasgo
			WHERE cr.id_cromo IN ($marcadores)
		");
		$stmt->execute(array_values($idsCromo));

		$porCromo = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
			$porCromo[(int) $fila["id_cromo"]][] = $fila["clave"];
		}
		return $porCromo;
	}

	/** Nivel alcanzado (0-3) con N copias, según los umbrales del rasgo. */
	private static function nivelPorCopias(array $rasgo, $copias) {
		if ($copias >= (int) $rasgo["umbral_3"]) { return 3; }
		if ($copias >= (int) $rasgo["umbral_2"]) { return 2; }
		if ($copias >= (int) $rasgo["umbral_1"]) { return 1; }
		return 0;
	}

	/**
	 * Núcleo de la Capa 2. Recibe la alineación (11 cartas tal y como las
	 * devuelve listarAlineacionDuelo o listarCartasMazo) y devuelve todo lo que
	 * el motor de resolución necesita:
	 *
	 *   [
	 *     "activos"       => [clave => ["copias"=>n, "nivel"=>0-3, "pct"=>float]],
	 *     "bonos_linea"   => ["POR"=>%, "DF"=>%, "MC"=>%, "DC"=>%],  ya con
	 *                        rendimientos decrecientes y tope aplicados
	 *     "tension_nivel" => 0-3,
	 *     "afinidad_dom"  => 'fuego'|'bosque'|'viento'|'montana'|'neutro',
	 *     "compo_index"   => suma de niveles,
	 *     "rareza_index"  => rareza media del once,
	 *     "malus"         => % de malus de coherencia (positivo = penaliza),
	 *   ]
	 */
	public function calcularCompos(array $cartas) {
		$catalogo = $this->rasgosCatalogo();

		$vacio = [
			"activos" => [], "bonos_linea" => ["POR" => 0.0, "DF" => 0.0, "MC" => 0.0, "DC" => 0.0],
			"tension_nivel" => 0, "afinidad_dom" => "neutro",
			"compo_index" => 0, "rareza_index" => 0.0, "malus" => 0.0,
		];
		if (!$cartas) { return $vacio; }

		// ---- 1. contar copias de cada rasgo en el once ----
		$copias = [];
		$copiasAfinidad = [];
		$sumaRareza = 0;

		$idsCromo = array_map(fn($c) => (int) $c["id_cromo"], $cartas);
		$configPorCromo = $this->rasgosConfigDeCromos($idsCromo);

		foreach ($cartas as $carta) {
			$sumaRareza += (int) ($carta["id_rareza"] ?? 1);

			$claveAfi = self::AFINIDAD_A_RASGO[$carta["afinidad"] ?? ""] ?? null;
			if ($claveAfi !== null) {
				$copias[$claveAfi] = ($copias[$claveAfi] ?? 0) + 1;
				$copiasAfinidad[$claveAfi] = ($copiasAfinidad[$claveAfi] ?? 0) + 1;
			}

			foreach ($configPorCromo[(int) $carta["id_cromo"]] ?? [] as $claveConfig) {
				$copias[$claveConfig] = ($copias[$claveConfig] ?? 0) + 1;
			}
		}

		// ---- 2. nivel de cada rasgo ----
		$activos = [];
		foreach ($copias as $clave => $n) {
			if (!isset($catalogo[$clave])) { continue; }
			$rasgo = $catalogo[$clave];
			$nivel = self::nivelPorCopias($rasgo, $n);
			if ($nivel === 0) { continue; }

			$activos[$clave] = [
				"copias" => $n,
				"nivel"  => $nivel,
				"pct"    => (float) $rasgo["pct_" . $nivel],
			];
		}

		// ---- 3. Tensión: cuenta rasgos DISTINTOS activos, no copias ----
		// No se cuenta a sí misma (sería circular) ni aporta al compo_index:
		// la diversidad ya está contada por tener muchos rasgos activos, y
		// sumarla otra vez sería contar dos veces lo mismo.
		$tensionNivel = 0;
		if (isset($catalogo["tension"])) {
			$tensionNivel = self::nivelPorCopias($catalogo["tension"], count($activos));
		}

		// ---- 4. bonos por línea con rendimientos decrecientes y tope ----
		$pesos = array_map("floatval", explode(",", (string) $this->config("compo_pesos_dr", "1.0,0.7,0.45,0.25")));
		if (!$pesos) { $pesos = [1.0, 0.7, 0.45, 0.25]; }
		$tope = (float) $this->config("line_cap", 20);

		$porLinea = ["POR" => [], "DF" => [], "MC" => [], "DC" => []];
		foreach ($activos as $clave => $info) {
			$rasgo = $catalogo[$clave];
			foreach ([$rasgo["linea_1"], $rasgo["linea_2"]] as $linea) {
				if ($linea !== null && isset($porLinea[$linea]) && $info["pct"] > 0) {
					$porLinea[$linea][] = $info["pct"];
				}
			}
		}

		$bonosLinea = [];
		foreach ($porLinea as $linea => $lista) {
			rsort($lista);   // el bonus mayor cuenta al 100%, los siguientes menos
			$suma = 0.0;
			foreach ($lista as $i => $pct) {
				$peso = $pesos[min($i, count($pesos) - 1)];
				$suma += $pct * $peso;
			}
			$bonosLinea[$linea] = min($suma, $tope);
		}

		// ---- 5. afinidad dominante (empate => neutro) ----
		$afinidadDom = "neutro";
		if ($copiasAfinidad) {
			$maximo = max($copiasAfinidad);
			$empatadas = array_keys($copiasAfinidad, $maximo);
			$afinidadDom = count($empatadas) === 1 ? $empatadas[0] : "neutro";
		}

		// ---- 6. malus de coherencia de rareza ----
		$compoIndex  = array_sum(array_column($activos, "nivel"));
		$rarezaIndex = $sumaRareza / count($cartas);

		$umbralLibre = (float) $this->config("coherencia_umbral_libre", 2.5);
		$rate        = (float) $this->config("coherencia_malus_rate", 3.0);
		$topeMalus   = (float) $this->config("coherencia_malus_tope", 18);

		$exceso   = max(0.0, $rarezaIndex - $umbralLibre);
		$exigida  = $exceso * $rate;
		$deficit  = max(0.0, $exigida - $compoIndex);
		$malus    = min($deficit * $rate / 3.0, $topeMalus);

		return [
			"activos"       => $activos,
			"bonos_linea"   => $bonosLinea,
			"tension_nivel" => $tensionNivel,
			"afinidad_dom"  => $afinidadDom,
			"compo_index"   => $compoIndex,
			"rareza_index"  => $rarezaIndex,
			"malus"         => $malus,
		];
	}

	/**
	 * Bonus (%) al total por el ciclo de contra-afinidad. Solo lo gana quien
	 * contra directamente a la dominante del rival; Neutro ni gana ni pierde.
	 */
	public function bonoCicloAfinidad($miDominante, $suDominante) {
		if ($miDominante === "neutro" || $suDominante === "neutro") { return 0.0; }
		if ((self::CICLO_AFINIDAD[$miDominante] ?? null) !== $suDominante) { return 0.0; }
		return (float) $this->config("ciclo_contra_afinidad_bonus", 5.5);
	}

	/**
	 * Congela las compos de un jugador en el momento de comprometerse al duelo,
	 * igual que ya se congela la alineación. Reasignar un rasgo o editar el mazo
	 * después no cambia un duelo ya empezado.
	 */
	public function congelarCompos($id_duelo, $id_usuario, array $compos) {
		$idsRasgo = [];
		foreach ($this->rasgosCatalogo() as $clave => $r) { $idsRasgo[$clave] = (int) $r["id_rasgo"]; }

		$stmt = $this->pdo->prepare("
			INSERT INTO duelo_compos (id_duelo, id_usuario, id_rasgo, copias, nivel, pct_nominal)
			VALUES (:d, :u, :r, :copias, :nivel, :pct)
			ON DUPLICATE KEY UPDATE copias = VALUES(copias), nivel = VALUES(nivel), pct_nominal = VALUES(pct_nominal)
		");

		foreach ($compos["activos"] as $clave => $info) {
			if (!isset($idsRasgo[$clave])) { continue; }
			$stmt->execute([
				":d" => $id_duelo, ":u" => $id_usuario, ":r" => $idsRasgo[$clave],
				":copias" => $info["copias"], ":nivel" => $info["nivel"], ":pct" => $info["pct"],
			]);
		}

		// Tensión se guarda también, con sus "copias" = rasgos distintos, para
		// que la pantalla de resultado pueda explicar de dónde salió su nivel.
		if ($compos["tension_nivel"] > 0 && isset($idsRasgo["tension"])) {
			$stmt->execute([
				":d" => $id_duelo, ":u" => $id_usuario, ":r" => $idsRasgo["tension"],
				":copias" => count($compos["activos"]), ":nivel" => $compos["tension_nivel"], ":pct" => 0,
			]);
		}
	}

	/** Compos congeladas de un jugador en un duelo, para la pantalla de resultado. */
	public function listarComposDuelo($id_duelo, $id_usuario) {
		$stmt = $this->pdo->prepare("
			SELECT dc.copias, dc.nivel, dc.pct_nominal,
			       r.clave, r.nombre, r.tipo, r.linea_1, r.linea_2
			FROM duelo_compos dc
			INNER JOIN rasgos r ON r.id_rasgo = dc.id_rasgo
			WHERE dc.id_duelo = :d AND dc.id_usuario = :u
			ORDER BY r.tipo, dc.nivel DESC, r.nombre
		");
		$stmt->execute([":d" => $id_duelo, ":u" => $id_usuario]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/* ======================================================================
	   MOTOR DE EVENTOS NARRADO  (Biblia §1)
	   ----------------------------------------------------------------------
	   Capa que va ENCIMA del resultado, nunca dentro (§1.1): quién gana, con
	   qué probabilidad y con qué TOTAL lo siguen decidiendo las tres capas ya
	   cerradas y la curva Elo. Este motor solo cuenta lo que pasó.

	   NO GUARDA NADA. La lista de eventos se regenera cuando hace falta a
	   partir de datos que el duelo ya tiene almacenados (fuerzas por línea
	   recalculadas desde la alineación CONGELADA, marcador y valor_sorteo).
	   Es determinista: el mismo duelo se narra igual cada vez que se abre —
	   necesario para que el veredicto compartible (§6.1) siga cuadrando— y
	   dos intentos distintos del mismo nodo se narran distinto solos, porque
	   cada duelo tiene su propio valor_sorteo. Eso es exactamente la regla 6
	   de §1.5 sin pagar una columna JSON por duelo.

	   El azar va con un generador propio sembrado, NUNCA con mt_rand(): esta
	   función se llama al pintar una pantalla, y tocar el estado global del
	   generador desde ahí contaminaría el sorteo de cualquier duelo o sobre
	   que se resolviera después en la misma petición.
	   ====================================================================== */

	/** Minutos reglamentarios. El descuento se añade aparte, al final. */
	const PARTIDO_MINUTOS = 90;

	/**
	 * Generador determinista [0,1). LCG clásico: da igual que sea de calidad
	 * estadística mediocre, aquí solo reparte narración, no decide resultados.
	 */
	/**
	 * Valor de azar para UNA jugada concreta: sembrado con el sorteo del duelo y
	 * adelantado hasta el turno de ese evento.
	 *
	 * ⚠️ EXISTE PARA CORREGIR UN MAL USO QUE SE NOTABA JUGANDO. Antes cada sitio
	 * hacía `azarSembrado(fmod($semilla * K + $id * c, 1))` y cogía su PRIMER
	 * valor, usando el generador como si fuera una función hash. No lo es: el
	 * primer valor de un LCG es casi lineal en su estado inicial, así que con el
	 * sorteo del duelo fijo y el id del evento avanzando a pasos constantes el
	 * valor avanzaba TAMBIÉN a pasos constantes y el resultado caía en ciclos
	 * cortos.
	 *
	 * Medido en el duelo 1859 (sorteo 0.2025): los eventos 5 y 17 daban 0.073069
	 * y 0.074081 — semillas muy distintas, valor casi idéntico— y por tanto el
	 * mismo minijuego dos veces en el mismo partido. La firma del problema es que
	 * con 5 candidatas la colisión entre eventos CONSECUTIVOS era del 0,0 %:
	 * imposible, no improbable, que es lo que delata un patrón fijo en vez de un
	 * reparto.
	 *
	 * Un LCG usado EN SECUENCIA sí está bien distribuido, que es para lo que
	 * sirve. Medido tras el cambio: colisiones al 25,1 % / 20,1 % / 33,4 % para
	 * 4 / 5 / 3 candidatas, contra el 25 / 20 / 33,3 % teórico.
	 *
	 * `$sal` separa unos sorteos de otros (qué minijuego sale, qué remate llega,
	 * cómo sale el portero…) para que conocer uno no dé información del resto.
	 *
	 * NO se toca `generarEventosPartido()`, que saca muchos valores seguidos de
	 * una sola siembra: ese uso ya era correcto. Por eso el relato de un duelo
	 * antiguo —minutos, goles, frases— sigue saliendo idéntico; lo único que
	 * cambia es qué minijuego se ofrece y qué dato oculto toca.
	 */
	private static function azarDeJugada($semilla, $idEvento, $sal) {
		$azar = self::azarSembrado(fmod((float) $semilla * $sal + 0.5, 1));
		$turnos = max(0, (int) $idEvento);
		for ($k = 0; $k < $turnos; $k++) $azar();
		return $azar();
	}

	private static function azarSembrado($semilla) {
		$estado = (int) (fmod(abs((float) $semilla), 1.0) * 2147483647);
		if ($estado <= 0) $estado = 123456789;
		return function () use (&$estado) {
			$estado = ($estado * 1103515245 + 12345) & 0x7FFFFFFF;
			return $estado / 0x7FFFFFFF;
		};
	}

	/**
	 * Selector de frases SIN REEMPLAZO dentro de un mismo partido (§1.5 regla
	 * 6). Devuelve un callable ($tipo, $vars) => string. Mientras queden
	 * variantes sin usar de ese tipo no repite ninguna; al agotarlas vuelve a
	 * barajar. Sin esto, elegir al azar repetiría frases dentro del MISMO
	 * partido, que es donde más canta.
	 */
	private static function narrador(array $plantillas, callable $azar) {
		$bolsas = [];
		return function ($tipo, array $vars = []) use ($plantillas, $azar, &$bolsas) {
			$variantes = $plantillas[$tipo] ?? [];
			if (!$variantes) return "";

			if (empty($bolsas[$tipo])) {
				$bolsas[$tipo] = range(0, count($variantes) - 1);
				// Fisher-Yates con el azar sembrado: reproducible.
				for ($i = count($bolsas[$tipo]) - 1; $i > 0; $i--) {
					$j = (int) ($azar() * ($i + 1));
					[$bolsas[$tipo][$i], $bolsas[$tipo][$j]] = [$bolsas[$tipo][$j], $bolsas[$tipo][$i]];
				}
			}
			$texto = $variantes[array_pop($bolsas[$tipo])];

			foreach ($vars as $clave => $valor) {
				$texto = str_replace("{" . $clave . "}", $valor, $texto);
			}
			return $texto;
		};
	}

	/** Cartas de un bando agrupadas por línea, para elegir protagonista. */
	private static function plantillaPorLinea(array $cartas, $formacion) {
		$huecos = self::huecosDe($formacion);
		$porLinea = ["POR" => [], "DF" => [], "MC" => [], "DC" => []];
		foreach ($cartas as $carta) {
			$linea = $huecos[(int) ($carta["hueco"] ?? 0)] ?? "MC";
			$porLinea[$linea][] = $carta;
		}
		return $porLinea;
	}

	/**
	 * Elige una carta de las líneas pedidas, ponderando por lo que aporta:
	 * el protagonista de una jugada NO puede ser siempre el de más Ataque
	 * (§1.5 regla 6 lo prohíbe explícitamente), pero tampoco puede ser
	 * uniforme o el partido lo protagonizaría el peor de la plantilla tanto
	 * como la estrella. Se pondera, y así varía sin dejar de tener sentido.
	 */
	private static function protagonista(array $porLinea, array $lineas, callable $azar, $porDefecto = "El equipo", array $evitar = []) {
		$candidatas = [];
		foreach ($lineas as $linea) {
			foreach ($porLinea[$linea] ?? [] as $carta) {
				// En PvE el rival puede llevar LAS MISMAS cartas que el jugador,
				// así que atacante y defensor pueden salir con el mismo nombre y
				// la frase queda en "Entrada tardía de X sobre X". Se descartan
				// aquí los nombres ya usados en esta misma jugada.
				if (in_array($carta["nombre"], $evitar, true)) continue;
				$candidatas[] = [$carta, max(1.0, self::aportarCarta($carta, $linea))];
			}
		}
		if (!$candidatas) return $porDefecto;

		$suma = array_sum(array_column($candidatas, 1));
		$corte = $azar() * $suma;
		foreach ($candidatas as [$carta, $peso]) {
			$corte -= $peso;
			if ($corte <= 0) return $carta["nombre"];
		}
		return $candidatas[0][0]["nombre"];
	}

	/**
	 * Genera la lista completa y ordenada de eventos de un partido ya resuelto.
	 *
	 * $bando = [
	 *   "nombre"     => string,
	 *   "fuerza"     => ["POR"=>f,"DF"=>f,"MC"=>f,"DC"=>f],
	 *   "cartas"     => alineación congelada,
	 *   "formacion"  => clave de formación,
	 *   "goles"      => int  (ya decidido; el motor lo REPARTE, no lo inventa)
	 * ]
	 *
	 * $opciones acepta:
	 *   "dificultad" => clave de self::DIFICULTADES (null en PvP). Es la que
	 *                   gobierna cuántas ocasiones se ofrecen como minijuego,
	 *                   igual que ya gobierna Elo/Compos/Aumento del rival.
	 *
	 * Devuelve ["eventos" => [...], "stats" => [...]].
	 *
	 * CONTRATO DE UN EVENTO — es la superficie a la que se van a enganchar
	 * las ~90 entradas del catálogo (§2) y las 5 palancas de dificultad (§3),
	 * así que se rompe caro. Cada evento lleva SIEMPRE:
	 *   id          identificador estable dentro del partido; es la referencia
	 *               con la que un minijuego dirá luego "resolví ESTA jugada".
	 *   minuto,     tipo, lado, texto, marcador
	 *   momentum    -100..100, positivo = a favor del local (§1.4)
	 *   familia     solo en ocasiones: a qué familia del catálogo pertenecería
	 *               el minijuego que puede colgar de esta jugada (§2.3)
	 *   interactivo solo en ocasiones: si esta jugada es una de las elegidas
	 *               para ofrecer decisión al jugador (§1.5 regla 3: NUNCA todas)
	 *   protagonistas  cartas ya resueltas de la jugada, para que el minijuego
	 *               no tenga que volver a elegirlas y contradecir al texto.
	 *
	 * §1.3: el marcador no se recalcula aquí. Los goles ya vienen dados y el
	 * motor decide EN QUÉ MINUTO y CÓMO caen, eligiendo para ello las
	 * ocasiones más peligrosas que él mismo generó. Así el marcador nace de la
	 * simulación sin poder contradecir nunca al ganador ya sorteado, que es la
	 * única restricción dura que §1.3 mantiene del sistema anterior.
	 */
	/**
	 * Todo lo que `generarEventosPartido()` necesita leer de `configuracion`
	 * (migración `027`, penaltis desde la `018`). Está aparte porque el método
	 * es estático y no puede leer la tabla por su cuenta, así que los DOS
	 * llamantes lo inyectan por $opciones — y los dos tienen que pasar lo
	 * MISMO: uno narra el partido y el otro lo resuelve, y si difirieran el
	 * marcador guardado no cuadraría con el relato.
	 *
	 * `gol_base`/`gol_sens` estaban documentados dentro del método como "el
	 * dial del equilibrio" desde el §15.10, pero nadie los pasaba nunca: los
	 * dos llamantes solo mandaban esto mismo método con OTRO nombre
	 * (`opcionesPenalti`) y esas dos claves no estaban. El dial existía en el
	 * código y no en la práctica. La `027` lo conecta de verdad.
	 *
	 * Los valores por defecto son los que el motor tenía escritos a fuego, así
	 * que una base sin la `027` sigue jugando exactamente igual.
	 */
	private function opcionesSimulacion() {
		return [
			"penalti_prob_gol"   => (float) $this->config("partido_penalti_prob_gol", 0.12),
			"penalti_prob_fallo" => (float) $this->config("partido_penalti_prob_fallo", 0.018),
			"gol_base"           => (float) $this->config("partido_gol_base", 0.06),
			"gol_sens"           => (float) $this->config("partido_gol_sens", 0.30),
			"ocasion_base"       => (float) $this->config("partido_ocasion_base", 0.10),
			"ocasion_factor"     => (float) $this->config("partido_ocasion_factor", 0.62),
			"ocasion_min"        => (float) $this->config("partido_ocasion_min", 0.14),
			"ocasion_max"        => (float) $this->config("partido_ocasion_max", 0.52),
		];
	}

	public static function generarEventosPartido(array $local, array $visitante, $semilla, array $opciones = []) {
		$azar       = self::azarSembrado($semilla);
		$plantillas = require __DIR__ . "/plantillas_narracion.php";
		$frase      = self::narrador($plantillas, $azar);

		/* Cuántas de las ocasiones generadas se ofrecen como minijuego. NO es
		   "todas" a propósito (§1.5 regla 3: si aparecieran en cada ocasión se
		   convertirían en el nuevo botón repetitivo que este motor viene a
		   evitar). Sube con la dificultad porque un Extremo debe exigir estar
		   encima del partido; en Fácil se ofrece poco para no abrumar. */
		$ritmoPorDificultad = [
			"facil" => 0.30, "medio" => 0.40, "dificil" => 0.50,
			"muy_dificil" => 0.60, "extremo" => 0.70,
		];
		$ritmoInteractivo = $ritmoPorDificultad[$opciones["dificultad"] ?? ""] ?? 0.45;

		/* Frecuencia del penalti (migración `018`). Llegan por $opciones porque
		   este método es estático y no puede leer `configuracion` por su cuenta;
		   los dos llamantes se la pasan. Los valores por defecto son los mismos
		   que siembra la migración, así que una base sin ella funciona igual. */
		$probPenaltiGol   = (float) ($opciones["penalti_prob_gol"]   ?? 0.12);
		$probPenaltiFallo = (float) ($opciones["penalti_prob_fallo"] ?? 0.018);

		/* Conversión de ocasión en gol. Los valores por defecto son los que el
		   motor tenía escritos a fuego, así que sin pasar nada se comporta igual
		   que antes. `sens` es el dial del equilibrio cuando el partido decide el
		   resultado (ver abajo, donde se usa). */
		$golBase = (float) ($opciones["gol_base"] ?? 0.06);
		$golSens = (float) ($opciones["gol_sens"] ?? 0.30);

		/* Probabilidad de que un TRAMO acabe en ocasión (§15.8c). Es la palanca
		   que de verdad aplana o abre el duelo, más que `duelo_k` —que desde el
		   §15.10 se calcula, se guarda y no decide nada—: el suelo es lo que
		   garantiza que el mazo más flojo pise el área igual, y el techo es lo
		   que impide que el más fuerte convierta cada tramo en ocasión. Medido:
		   con los valores de hoy, el mejor mazo de rareza libre gana el 34,0 %
		   de los duelos reales contra el mejor SRF. Los valores por defecto son
		   los que el motor tenía escritos a fuego. */
		$ocasionBase   = (float) ($opciones["ocasion_base"]   ?? 0.10);
		$ocasionFactor = (float) ($opciones["ocasion_factor"] ?? 0.62);
		$ocasionMin    = (float) ($opciones["ocasion_min"]    ?? 0.14);
		$ocasionMax    = (float) ($opciones["ocasion_max"]    ?? 0.52);

		$bandos = ["local" => $local, "visitante" => $visitante];
		$porLinea = [];
		foreach ($bandos as $lado => $b) {
			$porLinea[$lado] = self::plantillaPorLinea($b["cartas"] ?? [], $b["formacion"] ?? self::FORMACION_BASE);
		}

		$mcL = max(1.0, (float) $local["fuerza"]["MC"]);
		$mcV = max(1.0, (float) $visitante["fuerza"]["MC"]);
		$pLocal = $mcL / ($mcL + $mcV);   // §1.2: la posesión la decide el MEDIO, no el total

		$descuento = 1 + (int) ($azar() * 4);
		$minutoFinal = self::PARTIDO_MINUTOS + $descuento;

		/* --- Fase 1: repartir posesión y marcar qué tramos son ocasión -----
		   Se recorre el partido en tramos cortos. Cada tramo lo domina un
		   bando y puede acabar en ocasión, ponderado por su Ataque contra el
		   muro rival (Defensa + Portería), igual que §1.2.

		   Las ocasiones viven SIEMPRE sobre un tramo real, nunca en un minuto
		   suelto inventado: la fase 3 recorre los tramos, así que una ocasión
		   colocada en un minuto que no es de ningún tramo no se narraría nunca
		   y el marcador se quedaría corto (pasó: un 7-6 se contaba como 7-4). */
		$tramos = [];
		$minuto = 1;
		while ($minuto <= $minutoFinal) {
			$lado    = $azar() < $pLocal ? "local" : "visitante";
			$contra  = $lado === "local" ? "visitante" : "local";

			$ataque = max(1.0, (float) $bandos[$lado]["fuerza"]["DC"]);
			$muro   = max(1.0, (float) $bandos[$contra]["fuerza"]["DF"] + (float) $bandos[$contra]["fuerza"]["POR"]);
			$ratio  = $ataque / ($ataque + $muro);

			// Probabilidad de que el tramo acabe en ocasión. Acotada por arriba
			// y por abajo: ni el mazo más flojo se queda sin pisar el área, ni
			// el más fuerte convierte cada tramo en una ocasión.
			$pOcasion = max($ocasionMin, min($ocasionMax, $ocasionBase + $ratio * $ocasionFactor));

			$tramos[] = [
				"minuto"  => $minuto,
				"lado"    => $lado,
				"ratio"   => $ratio,
				"ocasion" => $azar() < $pOcasion,
				// El peligro decide qué ocasiones acaban en gol. Mezcla la
				// calidad real del ataque con azar para que no sean siempre
				// las mismas jugadas las que entran.
				"peligro" => $ratio * (0.55 + $azar() * 0.9),
				"gol"     => false,
			];
			$minuto += 2 + (int) ($azar() * 3);   // tramos de 2 a 4 minutos
		}

		/* --- Fase 2: decidir qué ocasiones son gol -------------------------
		   DOS MODOS:

		   · NATURAL (goles = null) — §1.3 en su forma literal: cada ocasión se
		     resuelve por su cuenta y el marcador ES el número de ocasiones que
		     acabaron dentro. Lo usa resolverDuelo() en PvP para DECIDIR el
		     resultado, sustituyendo a la fórmula provisional marcadorDuelo().

		   · REPARTO (goles dados) — el marcador ya está decidido y aquí solo se
		     coloca: se marcan como gol las ocasiones más peligrosas de cada
		     bando. Lo usan las cadenas y, sobre todo, la narración de un duelo
		     ya guardado: así lo que se cuenta NUNCA puede contradecir al
		     marcador que está en la base de datos, aunque algún día se retoquen
		     las probabilidades del modo natural. */
		$modoNatural = !isset($local["goles"]) || !isset($visitante["goles"]);

		if ($modoNatural) {
			foreach ($tramos as $i => $t) {
				if (!$t["ocasion"]) continue;
				/* Conversión acotada: ni una ocasión clarísima es gol seguro, ni
				   una mala es imposible. Calibrado para dar marcadores con forma
				   de fútbol (la mayoría 0-3) en vez de los 7-6 de la fórmula
				   provisional.

				   `sens` (cuánto pesa el peligro de la ocasión) es EL DIAL DEL
				   EQUILIBRIO cuando el resultado lo decide el partido: cuanto más
				   alto, más manda la fuerza del mazo y menos opciones tiene el
				   débil. Medido sin tocarlo, un 240 contra 100 pasaba del 69,1 % de
				   la curva Elo al 91,0 %. Ver branding/impacto-partido-analisis.md.

				   ⚠️ Hasta la migración `027` este comentario decía "sale a
				   `configuracion`" y era falso: los dos llamantes pasaban
				   `opcionesPenalti()`, que no incluía `gol_sens` ni `gol_base`, así
				   que el valor real SIEMPRE era el de arriba (0.06/0.30) y no había
				   forma de tocarlo sin desplegar. Ahora sí sale, en
				   `opcionesSimulacion()`. El OTRO dial —el suelo/techo de
				   `pOcasion`, arriba— resultó ser el que de verdad decide (§15.8c:
				   `duelo_k` no pinta nada desde que el partido decide), y sale por
				   el mismo sitio. */
				$pGol = max(0.05, min(0.45, $golBase + $t["peligro"] * $golSens));
				$tramos[$i]["gol"] = $azar() < $pGol;
			}

			$cuenta = function ($lado) use (&$tramos) {
				return count(array_filter($tramos, fn($t) => $t["lado"] === $lado && $t["gol"]));
			};

			/* Restricción dura de §1.3 CUANDO el ganador viene ya sorteado: la
			   simulación no puede contradecirlo. Se corrige con el mínimo destrozo
			   posible —ascender la mejor ocasión fallada del ganador, y solo si no
			   queda ninguna, anular el peor gol del perdedor— en vez de rehacer el
			   marcador entero.

			   SIN `gana` NO SE FUERZA NADA: el marcador natural que acaban de dar
			   las ocasiones se queda tal cual, y con él pueden salir EMPATES, que
			   con el ganador pre-sorteado eran imposibles. Es el modo que hace
			   falta para que el resultado se resuelva a lo largo del partido.

			   Antes esta línea era `... ?? null) === "visitante" ? "visitante" :
			   "local"`, así que un llamante que olvidara pasar `gana` hacía ganar
			   al local en silencio. Ahora la ausencia significa lo que parece. */
			$gana = null;
			if (($opciones["gana"] ?? null) !== null) {
				$gana = $opciones["gana"] === "visitante" ? "visitante" : "local";
			}
			$pierde = $gana === "local" ? "visitante" : "local";

			/* POR CUÁNTO debe ganar. Antes era siempre "por uno", y eso aplanaba el
			   partido: medido, el 88,8 % de los duelos acababa con un gol de
			   margen, cuando el reparto NATURAL de la simulación es sano (margen 0
			   el 36 %, 1 el 44,8 %, 2 el 15,1 %, 3 el 3,5 %). El bucle se lo comía
			   entero, y de paso INFLABA el marcador un 60 % (1,53 → 2,47 goles por
			   partido) ascendiendo ocasiones hasta que el ganador se ponía delante.

			   Ahora se conserva la FORMA del partido que la simulación había
			   producido: si naturalmente iba a ser un 3-1, acaba 3-1 aunque haya que
			   dárselo al otro. Solo los empates naturales tienen que romperse, y
			   esos sí pasan a margen 1, que es el mínimo destrozo de verdad.

			   Sigue siendo la restricción dura de §1.3 —el ganador del sorteo acaba
			   con más goles—, solo que sin cobrarse la variedad por el camino. */
			$margenNatural = $gana === null ? 0 : abs($cuenta($gana) - $cuenta($pierde));
			$margenObjetivo = max(1, $margenNatural);

			$vueltas = 0;
			while ($gana !== null && ($cuenta($gana) - $cuenta($pierde)) < $margenObjetivo
			       && $vueltas++ < 60) {
				// 1º ascender la mejor ocasión que el ganador ya falló
				$candidatos = array_keys(array_filter(
					$tramos, fn($t) => $t["lado"] === $gana && $t["ocasion"] && !$t["gol"]
				));
				if ($candidatos) {
					usort($candidatos, fn($a, $b) => $tramos[$b]["peligro"] <=> $tramos[$a]["peligro"]);
					$tramos[$candidatos[0]]["gol"] = true;
					continue;
				}
				// 2º anular el gol más flojo del perdedor
				$suyos = array_keys(array_filter($tramos, fn($t) => $t["lado"] === $pierde && $t["gol"]));
				if ($suyos) {
					usort($suyos, fn($a, $b) => $tramos[$a]["peligro"] <=> $tramos[$b]["peligro"]);
					$tramos[$suyos[0]]["gol"] = false;
					continue;
				}
				/* 3º crear una ocasión al ganador. Hace falta de verdad: si el
				   ganador no generó NINGUNA ocasión y el perdedor tampoco marcó,
				   el partido se queda 0-0 y sin este paso se guardaba un empate
				   con un ganador ya sorteado, que es justo lo que §1.3 prohíbe
				   (pasaba en el 1% de los partidos). */
				$libres = array_keys(array_filter($tramos, fn($t) => $t["lado"] === $gana && !$t["ocasion"]));
				if (!$libres) break;
				usort($libres, fn($a, $b) => $tramos[$b]["ratio"] <=> $tramos[$a]["ratio"]);
				$tramos[$libres[0]]["ocasion"] = true;
				$tramos[$libres[0]]["gol"] = true;
			}

			$golesDe = ["local" => $cuenta("local"), "visitante" => $cuenta("visitante")];
		} else {

		$golesDe = ["local" => (int) $local["goles"], "visitante" => (int) $visitante["goles"]];

		foreach (["local", "visitante"] as $lado) {
			$suyos = array_keys(array_filter($tramos, fn($t) => $t["lado"] === $lado));

			$conOcasion = array_values(array_filter($suyos, fn($i) => $tramos[$i]["ocasion"]));
			$faltan = $golesDe[$lado] - count($conOcasion);
			if ($faltan > 0) {
				$libres = array_values(array_filter($suyos, fn($i) => !$tramos[$i]["ocasion"]));
				usort($libres, fn($a, $b) => $tramos[$b]["ratio"] <=> $tramos[$a]["ratio"]);
				foreach (array_slice($libres, 0, $faltan) as $i) {
					$tramos[$i]["ocasion"] = true;
					$conOcasion[] = $i;
				}
			}

			usort($conOcasion, fn($a, $b) => $tramos[$b]["peligro"] <=> $tramos[$a]["peligro"]);
			foreach (array_slice($conOcasion, 0, $golesDe[$lado]) as $i) {
				$tramos[$i]["gol"] = true;
			}
		}

		}   // fin del modo REPARTO

		/* TEXTURA MÍNIMA DE PARTIDO. Con marcadores altos —el marcador
		   provisional llega a dar 4-8 o 9-5— puede pasar que TODAS las
		   ocasiones generadas acaben siendo gol, y entonces el partido se narra
		   como doce goles seguidos sin una sola parada, sin un tiro fuera y sin
		   un córner. Eso no se lee como un partido, se lee como un fallo.
		   Se garantizan ocasiones falladas suficientes ascendiendo más tramos,
		   que al no entrar en el reparto de goles se resuelven como parada,
		   despeje, tiro fuera o córner. No toca el marcador: solo lo rodea de
		   lo que cualquier partido real tiene alrededor de sus goles. */
		$golesTotales = $golesDe["local"] + $golesDe["visitante"];
		$noGol = count(array_filter($tramos, fn($t) => $t["ocasion"] && !$t["gol"]));
		$minimoNoGol = max(4, (int) ceil($golesTotales * 0.6));
		if ($noGol < $minimoNoGol) {
			$libres = array_keys(array_filter($tramos, fn($t) => !$t["ocasion"]));
			usort($libres, fn($a, $b) => $tramos[$b]["ratio"] <=> $tramos[$a]["ratio"]);
			foreach (array_slice($libres, 0, $minimoNoGol - $noGol) as $i) {
				$tramos[$i]["ocasion"] = true;   // ocasión que NO es gol: gol sigue en false
			}
		}

		/* --- Fase 3: convertir todo en eventos narrados -------------------- */
		$eventos = [];
		$stats = [
			"local"     => ["posesion" => 0, "tiros" => 0, "a_puerta" => 0, "paradas" => 0, "corners" => 0, "faltas" => 0, "tarjetas" => 0],
			"visitante" => ["posesion" => 0, "tiros" => 0, "a_puerta" => 0, "paradas" => 0, "corners" => 0, "faltas" => 0, "tarjetas" => 0],
		];
		$marcador = ["local" => 0, "visitante" => 0];

		/* MOMENTUM (§1.4): media móvil de quién ha generado las ocasiones más
		   recientes. Positivo = a favor del local. No toca ninguna fuerza ni
		   ninguna probabilidad —el cálculo sigue cerrado (§1.1)—, es un
		   indicador de lectura... y el único efecto que varios minijuegos del
		   catálogo declaran tener (El Grito de Guerra, La Mirada Desafiante,
		   El Baile Provocador mueven Momentum y nada más), así que tiene que
		   existir en el motor antes que ellos. El 0.78 es el factor de olvido:
		   una ocasión pesa, pero cuatro ocasiones seguidas del rival le dan la
		   vuelta al indicador en unos pocos minutos. */
		$momentum = 0.0;
		$empujarMomentum = function ($lado, $fuerza = 26.0) use (&$momentum) {
			$momentum = $momentum * 0.78 + ($lado === "local" ? $fuerza : -$fuerza);
			$momentum = max(-100.0, min(100.0, $momentum));
		};

		$siguienteId = 0;
		$añadir = function ($minuto, $tipo, $texto, $lado = null, $extra = []) use (&$eventos, &$marcador, &$momentum, &$siguienteId) {
			if ($texto === "") return;
			$eventos[] = array_merge([
				"id"       => ++$siguienteId,
				"minuto"   => (int) $minuto,
				"tipo"     => $tipo,
				"lado"     => $lado,
				"texto"    => $texto,
				"marcador" => [$marcador["local"], $marcador["visitante"]],
				"momentum" => (int) round($momentum),
			], $extra);
		};

		$añadir(0, "inicio", $frase("inicio"));

		$ultimoNotable = 0;
		$mediaMarcada = false;

		foreach ($tramos as $tramo) {
			$m = $tramo["minuto"];

			// Hitos de cronómetro.
			if (!$mediaMarcada && $m > 45) {
				$añadir(45, "descanso", $frase("descanso"));
				$añadir(46, "reanuda", $frase("reanuda"));
				$mediaMarcada = true;
				$ultimoNotable = 46;
			}

			$stats[$tramo["lado"]]["posesion"]++;

			$lado   = $tramo["lado"];
			$contra = $lado === "local" ? "visitante" : "local";
			$nombreAtaca  = $bandos[$lado]["nombre"];
			$nombreDefien = $bandos[$contra]["nombre"];

			if ($tramo["ocasion"]) {
				$ladoOc = $lado;
				$contOc = $contra;
				$atacante = $nombreAtaca;
				$defensor = $nombreDefien;

				// Cada nombre ya elegido se pasa como "evitar" al siguiente: los
				// cuatro papeles de una misma jugada tienen que ser cuatro
				// personas distintas, aunque ambos equipos compartan cartas.
				$jugador = self::protagonista($porLinea[$ladoOc], ["DC", "MC"], $azar, $atacante);
				$portero = self::protagonista($porLinea[$contOc], ["POR"], $azar, "el portero", [$jugador]);
				$defensa = self::protagonista($porLinea[$contOc], ["DF"], $azar, "la defensa", [$jugador, $portero]);
				$asiste  = self::protagonista($porLinea[$ladoOc], ["MC", "DF"], $azar, $atacante, [$jugador, $portero, $defensa]);

				$vars = compact("jugador", "portero", "defensa", "asiste")
					+ ["equipo" => $atacante, "rival" => $defensor];

				$stats[$ladoOc]["tiros"]++;
				$empujarMomentum($ladoOc);

				// Contexto que hereda el minijuego que cuelgue de esta jugada.
				// Se resuelve UNA vez y viaja con el evento: si el minijuego
				// volviera a elegir protagonista por su cuenta, acabaría
				// contradiciendo al texto que el jugador acaba de leer.
				$gancho = [
					"protagonistas" => [
						"jugador" => $jugador, "portero" => $portero,
						"defensa" => $defensa, "asiste"  => $asiste,
					],
					"interactivo" => $azar() < $ritmoInteractivo,
				];

				/* PENALTI. Una de cada veinticinco ocasiones se convierte en pena
				   máxima: da ~0,2 por partido, que es la frecuencia real del
				   fútbol y la que la Biblia pide para lo memorable ("construido
				   para ser memorable antes que frecuente", §2.4).

				   Lo importante del diseño: NO es un tipo de evento nuevo. Se
				   emite con los tipos `gol` / `parada` / `tiro_fuera` de siempre
				   y lo único propio es la FAMILIA (`penalti`) y las frases. Así
				   el marcador sigue naciendo del sorteo, el presupuesto de §15.5
				   sigue contando igual, $tieneSentido no se toca y los minijuegos
				   de penalti se enganchan por familia como cualquier otro. Es el
				   mismo truco que ya usaba `gol_asistido`: la clave de la frase y
				   el tipo del evento no tienen por qué coincidir. */
				/* El sesgo hacia las ocasiones que YA eran gol es lo que hace que un
				   penalti se sienta como un penalti. Sin él salía marcado solo el
				   29 % de las veces —en el fútbol real es el ~78 %— y la pena
				   máxima se leía como una moneda al aire que casi siempre falla.

				   Y no toca el marcador ni el ganador: el desenlace de cada ocasión
				   ya viene decidido por el sorteo (§15.1, la narración es una capa
				   ENCIMA del resultado). Lo único que se elige aquí es a cuál de
				   esas ocasiones ya resueltas se le pone el traje de penalti.
				   Una sola tirada en las dos ramas, para no desplazar el resto del
				   sorteo según el camino.

				   Las dos probabilidades son CALIBRABLES (§5.4, migración `018`)
				   porque hay un equilibrio real que decidir: un penalti marcado da
				   la decisión al que defiende y uno fallado al que ataca, así que
				   cuanto más realista es el acierto, menos aparece El Momento de la
				   Verdad. Ese trato es de Alejandro, no del código. */
				$esPenalti = $azar() < ($tramo["gol"] ? $probPenaltiGol : $probPenaltiFallo);
				if ($esPenalti) {
					/* Un penalti ES una falta, y hay que contarla: las frases dicen
					   "falta dentro del área" y "mano en el área", así que sin esto
					   el relato hablaba de una infracción que las estadísticas del
					   partido no registraban nunca. La comete el que defiende. */
					$stats[$contOc]["faltas"]++;

					/* Se señala aparte de la ejecución para que el relato tenga el
					   latido de un penalti: primero la pena máxima, luego el tiro.

					   Va como `contexto` y NO como `falta` a propósito: el tipo
					   `falta` lleva familia `balon_parado`, así que la señalización
					   se convertiría en candidata de los minijuegos de balón parado
					   y le ofrecería al jugador "¿por dónde pasas la barrera?" sobre
					   un penalti. `contexto` no trae familia y se excluye solo. */
					$añadir($m, "contexto", $frase("penalti_senalado", $vars), $ladoOc);
				}

				if ($tramo["gol"]) {
					$marcador[$ladoOc]++;
					$stats[$ladoOc]["a_puerta"]++;
					// Con asistencia solo si hay alguien distinto a quien marca:
					// "X asiste a X" se lee como un fallo, no como una jugada.
					$tipo = ($asiste !== $jugador && $azar() < 0.55) ? "gol_asistido" : "gol";
					$empujarMomentum($ladoOc, 40.0);   // un gol pesa más que una ocasión
					$añadir($m, "gol", $frase($esPenalti ? "penalti_gol" : $tipo, $vars), $ladoOc,
						$gancho + ["destacado" => true,
						           "familia" => $esPenalti ? "penalti" : "disparo",
						           "familia_def" => $esPenalti ? "penalti" : "porteria"]);
				} elseif ($esPenalti) {
					/* Penalti fallado. Se reparte entre parada y fuera para que el
					   atacante reciba su minijuego en los dos casos (los dos son
					   `tipo !== "gol"`), y el relato no repita siempre "lo paró". */
					if ($azar() < 0.7) {
						$stats[$ladoOc]["a_puerta"]++;
						$stats[$contOc]["paradas"]++;
						/* `familia_def` en NULL a propósito: en un penalti ya parado
						   no queda nada que defender. Con la familia puesta, quien
						   defendía recibía "elige dónde esperarle" sobre una pena
						   máxima que su portero acababa de sacar, y encima gastaba
						   una de sus dos decisiones sin poder mover nada, porque
						   descontarGolRival() solo actúa sobre `tipo === "gol"`.
						   El verificador no lo caza: comprueba que una entrada
						   LLEGUE, no que sirva de algo cuando llega. */
						$añadir($m, "parada", $frase("penalti_parado", $vars), $ladoOc,
							$gancho + ["destacado" => true,
							           "familia" => "penalti", "familia_def" => null]);
					} else {
						$añadir($m, "tiro_fuera", $frase("penalti_fuera", $vars), $ladoOc,
							$gancho + ["destacado" => true,
							           "familia" => "penalti", "familia_def" => null]);
					}
				} else {
					$dado = $azar();
					if ($dado < 0.34) {
						$stats[$ladoOc]["a_puerta"]++;
						$stats[$contOc]["paradas"]++;
						$añadir($m, "parada", $frase("parada", $vars), $ladoOc,
							$gancho + ["familia" => "disparo", "familia_def" => "porteria"]);
					} elseif ($dado < 0.62) {
						$añadir($m, "tiro_fuera", $frase("tiro_fuera", $vars), $ladoOc,
							$gancho + ["familia" => "disparo", "familia_def" => null]);
					} elseif ($dado < 0.84) {
						$añadir($m, "despeje", $frase("despeje", $vars), $ladoOc,
							$gancho + ["familia" => "disparo", "familia_def" => "defensa"]);
					} else {
						$stats[$ladoOc]["corners"]++;
						$añadir($m, "corner", $frase("corner", $vars), $ladoOc,
							$gancho + ["familia" => "balon_parado", "familia_def" => "balon_parado"]);
					}
				}
				$ultimoNotable = $m;
			}

			// Faltas y tarjetas: puro color, sin efecto mecánico (§1.4).
			if (!$tramo["ocasion"] && $azar() < 0.10) {
				$jugador = self::protagonista($porLinea[$lado], ["DC", "MC"], $azar, $nombreAtaca);
				$defensa = self::protagonista($porLinea[$contra], ["DF", "MC"], $azar, $nombreDefien, [$jugador]);
				$vars = compact("jugador", "defensa") + ["equipo" => $nombreAtaca, "rival" => $nombreDefien];

				$stats[$contra]["faltas"]++;
				if ($azar() < 0.22) {
					$stats[$contra]["tarjetas"]++;
					/* La tarjeta es del que la ve, pero la falta la sufre el que
					   atacaba: el balón parado resultante es SUYO.

					   Los protagonistas viajan también aquí, y eso es lo que abre
					   la familia `arbitro` (§15.4). Antes se calculaban dos líneas
					   más arriba y se tiraban solo en esta rama, así que el hueco
					   llegaba —304 de cada 600 partidos— pero era inservible: sin
					   protagonistas el dato oculto caía siempre en su valor por
					   defecto y una opción ganaba el 100 % de las tarjetas.

					   OJO al reparto, que aquí está del revés respecto al resto
					   del motor: `jugador` es del equipo que SUFRE la falta y
					   `defensa` el que la comete y ve la tarjeta, mientras que el
					   `lado` del evento es el SANCIONADO. Es la razón de que
					   `reaccion_rival` exista como cuarto dato oculto. */
					$añadir($m, "tarjeta", $frase("tarjeta", $vars), $contra,
						["familia" => "arbitro", "interactivo" => false,
						 "protagonistas" => ["jugador" => $jugador, "defensa" => $defensa]]);
				} else {
					$añadir($m, "falta", $frase("falta", $vars), $lado,
						["familia" => "balon_parado", "interactivo" => $azar() < $ritmoInteractivo,
						 "protagonistas" => ["jugador" => $jugador, "defensa" => $defensa]]);
				}
				$ultimoNotable = $m;
			}

			/* §1.5 regla 8 — mínimo garantizado de interacción. Si el azar deja
			   un hueco largo sin nada que leer, se fuerza un evento de contexto.
			   Sin esto, un partido puede tener tramos enteros en blanco y se
			   siente como "botón, esperar, continuar", que es justo lo que todo
			   este motor viene a corregir. */
			if ($m - $ultimoNotable >= 12) {
				$jugador = self::protagonista($porLinea[$lado], ["MC", "DF"], $azar, $nombreAtaca);
				$vars = ["jugador" => $jugador, "equipo" => $nombreAtaca, "rival" => $nombreDefien];
				$añadir($m, "contexto", $frase($azar() < 0.6 ? "posesion" : "presion", $vars), $lado);
				$ultimoNotable = $m;
			}
		}

		if (!$mediaMarcada) {
			$añadir(45, "descanso", $frase("descanso"));
			$añadir(46, "reanuda", $frase("reanuda"));
		}

		$añadir(self::PARTIDO_MINUTOS, "descuento", $frase("descuento"), null, ["descuento" => $descuento]);
		$añadir($minutoFinal, "final", $frase("final"));

		usort($eventos, fn($a, $b) => $a["minuto"] <=> $b["minuto"]);

		/* Los id se renumeran DESPUÉS de ordenar, no al crear. El descuento y
		   el descanso se añaden fuera del recorrido de tramos, así que el orden
		   de creación no es el cronológico y los id salían saltados (un id 16
		   en el minuto 90 antes que un id 15 en el 92). Un id que no avanza con
		   el reloj es una trampa para cualquier reproducción que lo use como
		   cursor de "por dónde voy". */
		foreach ($eventos as $i => &$evento) {
			$evento["id"] = $i + 1;
		}
		unset($evento);

		// La posesión se contó en tramos; se pasa a porcentaje ya redondeado
		// para que el cliente solo pinte, sin recalcular nada.
		$totalTramos = max(1, $stats["local"]["posesion"] + $stats["visitante"]["posesion"]);
		$pctLocal = (int) round($stats["local"]["posesion"] * 100 / $totalTramos);
		$stats["local"]["posesion"]     = $pctLocal;
		$stats["visitante"]["posesion"] = 100 - $pctLocal;

		return [
			"eventos"   => $eventos,
			"stats"     => $stats,
			"descuento" => $descuento,
			"minutos"   => $minutoFinal,
			// En modo natural este es el marcador que el partido ha producido y
			// que hay que guardar; en modo reparto coincide con el que entró.
			"goles"     => [$marcador["local"], $marcador["visitante"]],
		];
	}

	/* ======================================================================
	   MINIJUEGOS  (Biblia §2)
	   ----------------------------------------------------------------------
	   El motor no sabe jugar a ninguno: las reglas de cada uno viven en
	   db/minijuegos.php y aquí solo está lo común — qué remate llega y qué
	   pasa con la opción que eligió el jugador.

	   Todo es DETERMINISTA a partir del duelo y del id del evento, nunca de
	   mt_rand(). El motivo es de seguridad, no de estilo: el navegador manda
	   qué OPCIÓN eligió, y el servidor tiene que poder recalcular por su
	   cuenta qué remate llegaba en esa jugada. Si el remate se sorteara al
	   vuelo, un cliente podría pedir el mismo evento hasta que le saliera
	   favorable.
	   ====================================================================== */

	public static function catalogoMinijuegos() {
		static $catalogo = null;
		if ($catalogo === null) $catalogo = require __DIR__ . "/minijuegos.php";
		return $catalogo;
	}

	/**
	 * Qué minijuego cuelga de un evento concreto, o null si ese evento no
	 * ofrece decisión. Solo las ocasiones marcadas como interactivas por el
	 * motor son candidatas (§1.5 regla 3: nunca todas).
	 */
	/**
	 * Vocabulario de la primitiva "zona" (Biblia §2.1, clic-en-zona): qué huecos
	 * tiene cada lienzo. Es fuente única de verdad para tres sitios que TIENEN
	 * que coincidir o la zona se pinta fuera del mapa:
	 *   · el catálogo, que declara `lienzo` y la `zona` de cada opción,
	 *   · el verificador, que comprueba que la zona existe en ese lienzo,
	 *   · el CSS, cuyas `grid-template-areas` usan literalmente estos nombres.
	 *
	 * Si añades un hueco aquí, añádelo también a la plantilla del lienzo en
	 * layout.css: el cliente pone `grid-area` con el nombre tal cual, así que un
	 * nombre que el CSS no conozca deja la zona colocada automáticamente y el
	 * mapa se descuadra sin avisar.
	 */
	const LIENZOS_ZONA = [
		// El marco de la portería visto de frente: dos alturas por tres palos.
		"porteria" => ["escuadra_izq", "centro_alto", "escuadra_der",
		               "raso_izq", "centro_bajo", "raso_der"],
		// El área vista desde arriba, con la frontal por detrás del punto.
		"area"     => ["primer_palo", "punto_penalti", "segundo_palo", "frontal"],
		// El último tercio visto desde arriba: bandas y centro.
		"campo"    => ["banda_izq", "centro", "banda_der"],
	];

	public static function minijuegoDeEvento(array $evento, $defiendo = false, $semilla = 0.0) {
		if (empty($evento["interactivo"])) return null;
		/* La familia depende del LADO desde el que se mire la misma jugada: un
		   remate del rival es "disparo" para quien lo tira y "porteria" para
		   quien lo tiene que parar. Sin esta distinción, Muralla Humana no
		   aparecía nunca — el motor solo marcaba "porteria" en las paradas, y
		   un gol (que es justo lo que hay que parar) quedaba fuera. */
		$familia = $defiendo ? ($evento["familia_def"] ?? null) : ($evento["familia"] ?? null);
		if (!$familia) return null;

		$quiero = $defiendo ? "defiendo" : "ataco";

		/* TODOS los candidatos, no el primero que case. Con "return" al primer
		   match el catálogo tenía un techo de UNA entrada por (familia, lado):
		   daba igual cuántas escribieras, el resto eran código muerto. Ese era
		   el límite real para crecer, no el motor de eventos. */
		$candidatos = [];
		foreach (self::catalogoMinijuegos() as $clave => $mj) {
			// El lado también tiene que casar: "disparo" existe en las dos
			// lecturas de la misma jugada, y sin esta comprobación una ocasión
			// del rival ofrecería el minijuego de rematar.
			if ($mj["familia"] !== $familia) continue;
			if (($mj["lado"] ?? "defiendo") !== $quiero) continue;
			/* "tipos" acota una entrada a ciertos tipos de evento. Existe porque
			   una misma familia mezcla jugadas que no se narran igual: en
			   balon_parado caen córners Y faltas, y "¿dónde pones el córner?"
			   sobre una falta se lee como un fallo. Sin la lista, la entrada
			   vale para todos los tipos de su familia. */
			if (!empty($mj["tipos"]) && !in_array($evento["tipo"] ?? "", $mj["tipos"], true)) continue;
			$candidatos[] = ["clave" => $clave] + $mj;
		}
		if (!$candidatos) return null;
		if (count($candidatos) === 1) return $candidatos[0];

		/* Elección DETERMINISTA, nunca mt_rand(): el navegador manda solo qué
		   opción eligió, y resolverMinijuegoDuelo() vuelve a preguntar por este
		   camino qué minijuego era. Si aquí hubiera azar real, el servidor
		   podría recalcular una entrada distinta a la que se jugó.
		   Sal propia (3313) para no correlacionar QUÉ minijuego sale con el dato
		   oculto que se sortea después con 7919 / 4409 / 6271 / 5147. */
		$v = self::azarDeJugada($semilla, $evento["id"] ?? 0, 3313);
		return $candidatos[(int) floor($v * count($candidatos)) % count($candidatos)];
	}

	/**
	 * Cómo sale el portero rival en esta jugada. Espejo exacto de
	 * remateDeJugada(): se deduce de SU carta, con el desvío medido contra la
	 * media de los once que tiene en el campo, para que el reparto salga
	 * parejo aunque el catálogo esté sesgado.
	 */
	public static function estiloPorteroDeJugada(array $evento, array $cartasDefensor, $semilla) {
		$nombre = $evento["protagonistas"]["portero"] ?? null;
		$carta = null;
		foreach ($cartasDefensor as $c) {
			if ($c["nombre"] === $nombre) { $carta = $c; break; }
		}
		if (!$carta) return "espera";

		// Un portero de mucha Defensa se lanza; uno de mucha Técnica lee y sale.
		$perfil = fn($c) => ((float) ($c["defensa"] ?? 0) + (float) ($c["tecnica"] ?? 0)) > 0
			? ((float) $c["defensa"] - (float) $c["tecnica"]) / ((float) $c["defensa"] + (float) $c["tecnica"])
			: 0.0;
		$medias = array_map($perfil, $cartasDefensor);
		$media  = $medias ? array_sum($medias) / count($medias) : 0.0;
		$desvio = $perfil($carta) - $media;

		$k = 3.0;
		$pesos = [
			"tierra" => max(0.05, 1 + $k * $desvio),
			"achica" => max(0.05, 1 - $k * $desvio),
			"espera" => 1.0,
		];

		// Un solo valor, ya bien mezclado (ver azarDeJugada).
		$corteAzar = self::azarDeJugada($semilla, $evento["id"], 4409);
		$corte = $corteAzar * array_sum($pesos);
		foreach ($pesos as $tipo => $peso) {
			$corte -= $peso;
			if ($corte <= 0) return $tipo;
		}
		return "espera";
	}

	/**
	 * Cómo se planta la defensa rival en un balón parado. Tercer dato oculto,
	 * y el que abre la familia `balon_parado` entera (§2 de la Biblia).
	 *
	 * Se deduce del DEFENSOR (`protagonistas.defensa`) y no del portero a
	 * propósito: en una FALTA el motor no reparte portero — sus protagonistas
	 * son solo jugador y defensa—, así que un minijuego de balón parado que
	 * leyera al portero devolvería siempre el valor por defecto y dejaría una
	 * opción ganando el 100 % de las faltas. Justo la opción dominante que
	 * prohíbe §1.5 regla 2. El defensor sí está en las dos jugadas de la
	 * familia (córner y falta), así que es el único dato honesto disponible.
	 */
	public static function estiloDefensaDeJugada(array $evento, array $cartasDefensor, $semilla) {
		$nombre = $evento["protagonistas"]["defensa"] ?? null;
		$carta = null;
		foreach ($cartasDefensor as $c) {
			if ($c["nombre"] === $nombre) { $carta = $c; break; }
		}
		if (!$carta) return "salta";

		// Un defensa de mucha Defensa aguanta la posición; uno de mucho Ataque
		// rompe hacia el balón. Mismo esquema relativo que las otras dos.
		$perfil = fn($c) => ((float) ($c["defensa"] ?? 0) + (float) ($c["ataque"] ?? 0)) > 0
			? ((float) $c["defensa"] - (float) $c["ataque"]) / ((float) $c["defensa"] + (float) $c["ataque"])
			: 0.0;
		$medias = array_map($perfil, $cartasDefensor);
		$media  = $medias ? array_sum($medias) / count($medias) : 0.0;
		$desvio = $perfil($carta) - $media;

		$k = 3.0;
		$pesos = [
			"aguanta" => max(0.05, 1 + $k * $desvio),
			"sale"    => max(0.05, 1 - $k * $desvio),
			"salta"   => 1.0,
		];

		// Un solo valor, ya bien mezclado (ver azarDeJugada).
		$corteAzar = self::azarDeJugada($semilla, $evento["id"], 6271);
		$corte = $corteAzar * array_sum($pesos);
		foreach ($pesos as $tipo => $peso) {
			$corte -= $peso;
			if ($corte <= 0) return $tipo;
		}
		return "salta";
	}

	/** Pista sobre la defensa rival: espejo de pistaRemate(). */
	public static function pistaDefensa(array $evento, array $cartasDefensor) {
		$nombre = $evento["protagonistas"]["defensa"] ?? null;
		$carta = null;
		foreach ($cartasDefensor as $c) {
			if ($c["nombre"] === $nombre) { $carta = $c; break; }
		}
		if (!$carta) return "No sabes cómo van a defenderla.";

		$perfil = fn($c) => ((float) ($c["defensa"] ?? 0) + (float) ($c["ataque"] ?? 0)) > 0
			? ((float) $c["defensa"] - (float) $c["ataque"]) / ((float) $c["defensa"] + (float) $c["ataque"])
			: 0.0;
		$medias = array_map($perfil, $cartasDefensor);
		$media  = $medias ? array_sum($medias) / count($medias) : 0.0;
		$desvio = $perfil($carta) - $media;

		if ($desvio > 0.02)  return $carta["nombre"] . " aguanta la posición mejor que sus compañeros.";
		if ($desvio < -0.02) return $carta["nombre"] . " es de los que rompen hacia el balón.";
		return $carta["nombre"] . " no se casa con ninguna marca.";
	}

	/**
	 * Cómo se toma el rival la falta que le acabas de hacer. CUARTO dato oculto,
	 * y el que abre la familia `arbitro` entera (Biblia §2.4).
	 *
	 * Se lee del JUGADOR QUE SUFRE LA FALTA, que está en la alineación CONTRARIA
	 * a la del evento: en un evento de tarjeta el `lado` es el equipo SANCIONADO
	 * —el que recibe la decisión—, así que el que está en el suelo es su rival.
	 *
	 * Esa inversión es exactamente la razón por la que hacía falta un dato nuevo
	 * en vez de reutilizar uno de los tres anteriores: los tres leen al equipo
	 * que defiende una jugada, y en una tarjeta no hay jugada que defender. Con
	 * `colocacion_defensa` el lector habría buscado a `protagonistas.defensa`,
	 * que aquí es TU propio amonestado, en la alineación equivocada — no lo
	 * habría encontrado nunca y habría devuelto siempre su valor por defecto,
	 * dejando una opción ganando el 100 % de las tarjetas.
	 *
	 * Tampoco se lee tu propio amonestado a propósito: tu alineación la ves
	 * entera, así que "adivinar" algo de tus cartas no sería adivinar nada.
	 */
	public static function reaccionRivalDeJugada(array $evento, array $cartasRival, $semilla) {
		$nombre = $evento["protagonistas"]["jugador"] ?? null;
		$carta = null;
		foreach ($cartasRival as $c) {
			if ($c["nombre"] === $nombre) { $carta = $c; break; }
		}
		if (!$carta) return "sigue";

		// Mucha Técnica lo alarga en el suelo; mucho Ataque se levanta a por el
		// árbitro. Mismo esquema relativo y centrado que los otros tres datos.
		$perfil = fn($c) => ((float) ($c["tecnica"] ?? 0) + (float) ($c["ataque"] ?? 0)) > 0
			? ((float) $c["tecnica"] - (float) $c["ataque"]) / ((float) $c["tecnica"] + (float) $c["ataque"])
			: 0.0;
		$medias = array_map($perfil, $cartasRival);
		$media  = $medias ? array_sum($medias) / count($medias) : 0.0;
		$desvio = $perfil($carta) - $media;

		$k = 3.0;
		$pesos = [
			"teatro"   => max(0.05, 1 + $k * $desvio),
			"protesta" => max(0.05, 1 - $k * $desvio),
			"sigue"    => 1.0,
		];

		// Multiplicador propio (5147), sin repetir los de los otros datos ni el
		// de la elección de minijuego: si compartieran semilla, saber uno daría
		// información del otro.
		// Un solo valor, ya bien mezclado (ver azarDeJugada).
		$corteAzar = self::azarDeJugada($semilla, $evento["id"], 5147);
		$corte = $corteAzar * array_sum($pesos);
		foreach ($pesos as $tipo => $peso) {
			$corte -= $peso;
			if ($corte <= 0) return $tipo;
		}
		return "sigue";
	}

	/** Pista sobre el rival que está en el suelo: espejo de pistaRemate(). */
	public static function pistaReaccionRival(array $evento, array $cartasRival) {
		$nombre = $evento["protagonistas"]["jugador"] ?? null;
		$carta = null;
		foreach ($cartasRival as $c) {
			if ($c["nombre"] === $nombre) { $carta = $c; break; }
		}
		if (!$carta) return "No sabes de qué pie calza.";

		$perfil = fn($c) => ((float) ($c["tecnica"] ?? 0) + (float) ($c["ataque"] ?? 0)) > 0
			? ((float) $c["tecnica"] - (float) $c["ataque"]) / ((float) $c["tecnica"] + (float) $c["ataque"])
			: 0.0;
		$medias = array_map($perfil, $cartasRival);
		$media  = $medias ? array_sum($medias) / count($medias) : 0.0;
		$desvio = $perfil($carta) - $media;

		if ($desvio > 0.02)  return $carta["nombre"] . " es de los que lo alargan en el suelo.";
		if ($desvio < -0.02) return $carta["nombre"] . " se va a ir a por el árbitro.";
		return $carta["nombre"] . " no suele hacer aspavientos.";
	}

	/**
	 * ¿El dato oculto lo pone el equipo CONTRARIO al del evento?
	 *
	 * Fuente única de verdad para saber de qué alineación hay que sacar las
	 * cartas. La usan resolverMinijuegoDuelo() (para calcular el dato) y
	 * narracionDuelo() (para calcular la pista), y tienen que coincidir: si una
	 * mirase la alineación equivocada, la pista hablaría de una carta y el
	 * sorteo saldría de otra.
	 *
	 * El nombre habla de "el defensor" porque los tres primeros datos ocultos
	 * leían siempre a quien defendía la jugada. Con `reaccion_rival` eso dejó de
	 * ser literal —ahí se lee al rival que sufre la falta, que es quien ATACABA—,
	 * pero la pregunta que responde la función es la misma y no ha cambiado: ¿hay
	 * que mirar la alineación de enfrente del `lado` del evento?
	 */
	public static function datoOcultoLoPoneElDefensor(array $minijuego) {
		return in_array($minijuego["oculto"] ?? "remate",
			["estilo_portero", "colocacion_defensa", "reaccion_rival"], true);
	}

	/** El dato oculto que hay que adivinar, según lo que declare el catálogo. */
	public static function ocultoDeJugada(array $minijuego, array $evento, array $cartas, $semilla) {
		switch ($minijuego["oculto"] ?? "remate") {
			case "estilo_portero":     return self::estiloPorteroDeJugada($evento, $cartas, $semilla);
			case "colocacion_defensa": return self::estiloDefensaDeJugada($evento, $cartas, $semilla);
			case "reaccion_rival":     return self::reaccionRivalDeJugada($evento, $cartas, $semilla);
			default:                   return self::remateDeJugada($evento, $cartas, $semilla);
		}
	}

	/** La pista que acompaña a ese dato oculto. Espejo de ocultoDeJugada(). */
	public static function pistaDeJugada(array $minijuego, array $evento, array $cartas) {
		switch ($minijuego["oculto"] ?? "remate") {
			case "estilo_portero":     return self::pistaPortero($evento, $cartas);
			case "colocacion_defensa": return self::pistaDefensa($evento, $cartas);
			case "reaccion_rival":     return self::pistaReaccionRival($evento, $cartas);
			default:                   return self::pistaRemate($evento, $cartas);
		}
	}

	/** Pista sobre el portero rival: espejo de pistaRemate(). */
	public static function pistaPortero(array $evento, array $cartasDefensor) {
		$nombre = $evento["protagonistas"]["portero"] ?? null;
		$carta = null;
		foreach ($cartasDefensor as $c) {
			if ($c["nombre"] === $nombre) { $carta = $c; break; }
		}
		if (!$carta) return "No sabes cómo suele salir.";

		$perfil = fn($c) => ((float) ($c["defensa"] ?? 0) + (float) ($c["tecnica"] ?? 0)) > 0
			? ((float) $c["defensa"] - (float) $c["tecnica"]) / ((float) $c["defensa"] + (float) $c["tecnica"])
			: 0.0;
		$medias = array_map($perfil, $cartasDefensor);
		$media  = $medias ? array_sum($medias) / count($medias) : 0.0;
		$desvio = $perfil($carta) - $media;

		if ($desvio > 0.02)  return $carta["nombre"] . " se lanza pronto al suelo.";
		if ($desvio < -0.02) return $carta["nombre"] . " sale a comerte el ángulo.";
		return $carta["nombre"] . " no se define hasta el final.";
	}

	/**
	 * Qué remate llega en esa jugada. Se deduce de las estadísticas de quien
	 * remata, no de un sorteo ciego: un delantero de mucho Ataque tiende al
	 * disparo potente y uno de mucha Técnica al colocado. Así la pista que se
	 * le enseña al jugador (§3.2 palanca 3) puede ser HONESTA — se basa en
	 * algo real de la carta que tiene enfrente, que es justo lo que §3.3 exige
	 * para que la dificultad alta siga siendo un reto legítimo y no una lotería.
	 */
	public static function remateDeJugada(array $evento, array $cartasAtacante, $semilla) {
		$nombre = $evento["protagonistas"]["jugador"] ?? null;
		$carta = null;
		foreach ($cartasAtacante as $c) {
			if ($c["nombre"] === $nombre) { $carta = $c; break; }
		}
		if (!$carta) return "colocado";

		$ataque  = (float) ($carta["ataque"] ?? 0);
		$tecnica = (float) ($carta["tecnica"] ?? 0);

		/* Pesos RELATIVOS, no umbrales absolutos. La diferencia importa:
		   comparar `ataque > tecnica` directamente ataba el reparto a cómo esté
		   el catálogo hoy, y hoy está sesgado a Técnica (solo 5 cartas de 44
		   tienen Ataque dominante). Con umbrales absolutos salía "colocado" el
		   54 % de las veces, y entonces la opción que gana a colocado era
		   dominante — exactamente lo que prohíbe §1.5 regla 2.

		   Así el reparto se mantiene repartido aunque el catálogo crezca o se
		   reequilibre: si todas las cartas fueran idénticas saldría un tercio
		   de cada tipo, y la desviación de cada carta solo lo inclina un poco.
		   La pista sigue siendo honesta (§3.3): un rematador de más Ataque
		   dispara potente más a menudo, solo que no siempre. */
		$perfil = fn($c) => ((float) ($c["ataque"] ?? 0) + (float) ($c["tecnica"] ?? 0)) > 0
			? ((float) $c["ataque"] - (float) $c["tecnica"]) / ((float) $c["ataque"] + (float) $c["tecnica"])
			: 0.0;

		/* El desvío se mide contra la MEDIA DE LOS ONCE QUE ESTÁN EN EL CAMPO,
		   no contra cero. Contra cero, el sesgo del catálogo (más Técnica que
		   Ataque de media) empujaba siempre hacia "colocado" y dejaba dominante
		   a la opción que le gana. Centrado en la alineación se normaliza solo:
		   la pregunta pasa a ser "¿este rematador es más potente QUE SUS
		   COMPAÑEROS?", que además es la lectura que un jugador puede hacer de
		   verdad mirando la alineación rival. */
		$medias = array_map($perfil, $cartasAtacante);
		$media  = $medias ? array_sum($medias) / count($medias) : 0.0;

		$desvio = $perfil($carta) - $media;
		$k = 3.0;   // el desvío ya viene centrado, así que es mucho más pequeño
		$pesos = [
			"potente"  => max(0.05, 1 + $k * $desvio),
			"colocado" => max(0.05, 1 - $k * $desvio),
			"raso"     => 1.0,
		];

		// El id del evento entra en la semilla: dos ocasiones del mismo
		// rematador en el mismo partido no tienen por qué salir iguales.
		// Un solo valor, ya bien mezclado (ver azarDeJugada).
		$corteAzar = self::azarDeJugada($semilla, $evento["id"], 7919);
		$corte = $corteAzar * array_sum($pesos);
		foreach ($pesos as $tipo => $peso) {
			$corte -= $peso;
			if ($corte <= 0) return $tipo;
		}
		return "colocado";
	}

	/**
	 * Resuelve la elección del jugador contra el remate que llegaba.
	 * Devuelve "acierto" (la para) o "fallo" (no llega).
	 *
	 * No hay castigo por elegir mal: o paras el gol o el gol entra igual que
	 * habría entrado. Es deliberado — el minijuego solo puede mejorar tu
	 * partido, nunca empeorarlo, así que ofrecerlo jamás es una trampa.
	 */
	public static function resolverMinijuego(array $minijuego, $claveOpcion, $remate) {
		$opcion = null;
		foreach ($minijuego["opciones"] as $o) {
			if ($o["clave"] === $claveOpcion) { $opcion = $o; break; }
		}
		if (!$opcion) {   // opción inventada por el cliente: se trata como no decidir
			foreach ($minijuego["opciones"] as $o) {
				if (!empty($o["segura"])) { $opcion = $o; break; }
			}
		}
		if (!$opcion) return "fallo";

		return (($opcion["gana"] ?? null) === $remate) ? "acierto" : "fallo";
	}

	/**
	 * La pista que se le enseña al jugador antes de decidir (§3.2 palanca 3).
	 *
	 * Es HONESTA en el sentido de §3.3: no revela el remate, revela la
	 * TENDENCIA real del que remata, que es la misma que sesga el sorteo del
	 * remate. Un delantero marcadamente potente disparará potente más a menudo,
	 * pero puede sorprenderte — igual que en el fútbol real. Quien lee la pista
	 * acierta más que 1 de cada 3; quien no la lee, no.
	 */
	public static function pistaRemate(array $evento, array $cartasAtacante) {
		$nombre = $evento["protagonistas"]["jugador"] ?? null;
		$carta = null;
		foreach ($cartasAtacante as $c) {
			if ($c["nombre"] === $nombre) { $carta = $c; break; }
		}
		if (!$carta) return "No le has visto rematar todavía.";

		$perfil = fn($c) => ((float) ($c["ataque"] ?? 0) + (float) ($c["tecnica"] ?? 0)) > 0
			? ((float) $c["ataque"] - (float) $c["tecnica"]) / ((float) $c["ataque"] + (float) $c["tecnica"])
			: 0.0;
		$medias = array_map($perfil, $cartasAtacante);
		$media  = $medias ? array_sum($medias) / count($medias) : 0.0;
		$desvio = $perfil($carta) - $media;

		if ($desvio > 0.02)  return $carta["nombre"] . " pega más fuerte que sus compañeros.";
		if ($desvio < -0.02) return $carta["nombre"] . " es de los que la colocan.";
		return $carta["nombre"] . " no tiene un remate marcado.";
	}

	/**
	 * Elige QUÉ jugadas de un partido llevan decisión, repartidas a lo largo del
	 * encuentro en vez de dárselas a las primeras que valgan.
	 *
	 * El problema que resuelve, medido y no supuesto: al abrir los huecos
	 * defensivos (v7.3) las candidatas tempranas se multiplicaron, y coger las
	 * primeras gastaba el techo en los primeros minutos —minuto mediano 10',
	 * última decisión en el 17', 88 % antes del 30'—. El partido se quedaba
	 * plano el resto del tiempo y repetía siempre el mismo tipo de jugada,
	 * porque las frecuentes al principio son siempre las mismas.
	 *
	 * Cómo reparte: divide el partido en tantas ventanas iguales como decisiones
	 * quepan y coge una de cada ventana. Dentro de una ventana prefiere las que
	 * PUEDEN mover el marcador; las de impacto "ninguno" solo entran si queda
	 * cupo (§15.5). Las ventanas que se quedan vacías se rellenan al final con
	 * las candidatas que sobren, para no perder una decisión por un reparto
	 * desafortunado de los eventos.
	 *
	 * DETERMINISTA de principio a fin, y no es un detalle: resolverMinijuegoDuelo()
	 * vuelve a llamar a narracionDuelo() para recalcular qué se jugó, así que con
	 * azar real aquí el servidor podría elegir una jugada distinta a la que el
	 * jugador tenía delante.
	 *
	 * @param array $candidatas  [["i"=>indice, "minuto"=>int, "mueve"=>bool], ...]
	 * @return array             [indice_de_evento => true]
	 */
	private static function repartirDecisiones(array $candidatas, $max, $maxSinImpacto, $minutos, $semilla) {
		if (!$candidatas || $max <= 0) return [];

		$minutos = max(1, (int) $minutos);
		// Multiplicador propio, sin repetir los de los datos ocultos ni el de la
		// elección de minijuego: el reparto no debe correlacionar con ellos.
		$azar = self::azarSembrado(fmod($semilla * 7717 + 0.137, 1));

		$elegidas = [];
		$usadas = [];
		$yaVistas = [];      // claves de minijuego ya repartidas en este partido
		$sinImpacto = 0;

		// Coge una candidata de $pool, sin azar global y de forma reproducible.
		$coger = function (array $pool) use (&$usadas, &$yaVistas, &$sinImpacto, &$elegidas, $candidatas, $azar) {
			// Dentro de la ventana manda la que puede mover el marcador.
			$conImpacto = array_filter($pool, fn($c) => $c["mueve"]);
			if ($conImpacto) $pool = $conImpacto;

			/* Y entre esas, las que NO repiten un minijuego ya visto en este
			   partido. Con el reparto ya arreglado repetir es una colisión
			   legítima (1/n), pero se sigue notando: con cinco variantes
			   disponibles, jugar dos veces la misma en el mismo encuentro es
			   justo lo que §1.5 regla 6 quiere evitar. Si TODAS repiten se coge
			   igual — antes repetir que quedarse sin decisión. */
			$frescas = array_filter($pool, fn($c) => !isset($yaVistas[$c["clave"]]));
			if ($frescas) $pool = $frescas;

			$claves = array_keys($pool);
			$k = $claves[(int) floor($azar() * count($claves)) % count($claves)];
			$usadas[$k] = true;
			$yaVistas[$candidatas[$k]["clave"]] = true;
			if (!$candidatas[$k]["mueve"]) $sinImpacto++;
			$elegidas[$candidatas[$k]["i"]] = true;
		};

		$disponible = function ($k, $c) use (&$usadas, &$sinImpacto, $maxSinImpacto) {
			if (isset($usadas[$k])) return false;
			return $c["mueve"] || $sinImpacto < $maxSinImpacto;
		};

		// Una por ventana.
		for ($v = 0; $v < $max; $v++) {
			$desde = ($v * $minutos) / $max;
			$hasta = (($v + 1) * $minutos) / $max;

			$pool = [];
			foreach ($candidatas as $k => $c) {
				if (!$disponible($k, $c)) continue;
				if ($c["minuto"] < $desde || $c["minuto"] >= $hasta) continue;
				$pool[$k] = $c;
			}
			if ($pool) $coger($pool);
		}

		// Ventanas vacías: se rellena con lo que sobre, en orden cronológico, para
		// no dejar sin decisión a quien tuvo los eventos mal repartidos.
		foreach ($candidatas as $k => $c) {
			if (count($elegidas) >= $max) break;
			if (!$disponible($k, $c)) continue;
			$coger([$k => $c]);
		}

		return $elegidas;
	}

	/** La opción que se aplica sola si no se decide a tiempo (§1.5 regla 4). */
	public static function opcionSegura(array $minijuego) {
		foreach ($minijuego["opciones"] as $o) {
			if (!empty($o["segura"])) return $o["clave"];
		}
		return $minijuego["opciones"][0]["clave"] ?? null;
	}

	/* cabeCambioMarcador() se retiró aquí.
	   Era la restricción dura de §1.3 aplicada a los minijuegos: "¿cabe mover
	   el marcador sin contradecir al ganador ya sorteado?". Con el partido
	   decidiendo el resultado, no hay ningún ganador previo que respetar, así
	   que la pregunta ya no existe. Lo que ocupa su sitio es
	   `partido_presupuesto_marcador` en narracionDuelo(): un tope de diseño
	   sobre cuánto puede mover cada jugador, no una condición de coherencia.

	   No se deja como función muerta a propósito: dejarla invitaría a volver a
	   llamarla, y volver a llamarla reimplantaría el §1.3 a medias. */

	/* ======================================================================
	   PARTIDO EN VIVO  (migración 014)
	   ----------------------------------------------------------------------
	   El partido narrado dejó de ser cosa del navegador. Antes cada jugador lo
	   reproducía en SU reloj, al cargar la página con ?nuevo=1, y un minijuego
	   solo detenía su tiempo: con dos personas en el mismo duelo eso significa
	   que veían minutos distintos y que la pausa de uno no existía para el otro.

	   Ahora el minuto lo manda el servidor y sale del reloj de pared. No hay
	   ningún proceso de fondo llevándolo —NO HAY CRON (§8)—: se deriva en cada
	   sondeo, igual que ya se hace con el plazo del Aumento.

	   Regla acordada con Alejandro: si no estás atento, te lo pierdes. El
	   partido no espera indefinidamente a nadie.
	   ====================================================================== */

	/**
	 * Los dos estados en los que un duelo TIENE partido que mirar.
	 *
	 * `en_juego` es el partido de un PvP mientras se juega: montado y sembrado,
	 * pero sin ganador todavía porque lo decide el propio encuentro.
	 * `resuelto` es un partido ya terminado —y también un PvE, que se resuelve
	 * de una vez porque no tiene minijuegos que puedan mover nada.
	 *
	 * Todo lo que lee el partido (narración, sondeo, reloj, marcador) acepta los
	 * dos. Lo que necesita un GANADOR ya escrito exige `resuelto` a secas.
	 */
	const ESTADOS_CON_PARTIDO = ["en_juego", "resuelto"];

	/** Deja constancia de que este jugador sigue delante del partido. */
	public function latirPartido($id_duelo, $id_usuario) {
		$this->pdo->prepare("
			UPDATE duelos
			SET latido_creador = IF(id_creador = :u1, NOW(), latido_creador),
			    latido_rival   = IF(id_rival   = :u2, NOW(), latido_rival)
			WHERE id_duelo = :d
		")->execute([":u1" => $id_usuario, ":u2" => $id_usuario, ":d" => $id_duelo]);
	}

	/** Segundos que el partido lleva corriendo, descontando las pausas. */
	private static function segundosDePartido(array $duelo) {
		if (!$duelo["partido_inicio"]) return 0.0;
		$corrido = time() - strtotime($duelo["partido_inicio"]) - (int) $duelo["partido_pausa_seg"];
		// Si está parado ahora mismo, lo que lleve parado tampoco cuenta.
		if ($duelo["partido_pausado_en"]) {
			$corrido -= time() - strtotime($duelo["partido_pausado_en"]);
		}
		return max(0.0, (float) $corrido);
	}

	/** Arranca el reloj si ya toca: están los dos, o se acabó la espera. */
	private function arrancarPartidoSiToca(array $duelo) {
		if ($duelo["partido_inicio"]) return $duelo;

		$espera = (int) $this->config("partido_espera_seg", 15);
		$maxLatido = (int) $this->config("partido_latido_max", 12);

		$presente = fn($latido) => $latido && (time() - strtotime($latido)) <= $maxLatido;

		/* ⚠️ EN UNA CADENA SOLO HAY UN JUGADOR PRESENTE. El CPU no tiene pantalla
		   y por tanto NO LATE NUNCA, así que exigir los dos latidos dejaría todos
		   los partidos de cadena arrancando por el otro camino: el de la espera
		   máxima, o sea 15 segundos de reloj parado antes de cada partido. Aquí
		   "están los dos" significa "está el que juega". */
		$esPve = $duelo["dificultad"] !== null;
		$ambos = $esPve
			? ($presente($duelo["latido_creador"]) || $presente($duelo["latido_rival"]))
			: ($presente($duelo["latido_creador"]) && $presente($duelo["latido_rival"]));
		$seAcaboLaEspera = $duelo["resuelto"] && (time() - strtotime($duelo["resuelto"])) >= $espera;

		if (!$ambos && !$seAcaboLaEspera) return $duelo;

		// El WHERE con partido_inicio IS NULL evita que dos sondeos simultáneos
		// arranquen el reloj dos veces y le roben segundos al partido.
		$this->pdo->prepare("
			UPDATE duelos SET partido_inicio = NOW()
			WHERE id_duelo = :d AND partido_inicio IS NULL
			  AND estado IN ('en_juego', 'resuelto')
		")->execute([":d" => $duelo["id_duelo"]]);

		$stmt = $this->pdo->prepare("SELECT * FROM duelos WHERE id_duelo = :d");
		$stmt->execute([":d" => $duelo["id_duelo"]]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
	}

	/** Detiene el reloj para los DOS jugadores. */
	private function pausarPartido($id_duelo) {
		$this->pdo->prepare("
			UPDATE duelos SET partido_pausado_en = NOW()
			WHERE id_duelo = :d AND partido_pausado_en IS NULL
		")->execute([":d" => $id_duelo]);
	}

	/** Reanuda, sumando lo que estuvo parado para que no cuente como partido. */
	private function reanudarPartido($id_duelo) {
		$this->pdo->prepare("
			UPDATE duelos
			SET partido_pausa_seg = partido_pausa_seg + TIMESTAMPDIFF(SECOND, partido_pausado_en, NOW()),
			    partido_pausado_en = NULL
			WHERE id_duelo = :d AND partido_pausado_en IS NOT NULL
		")->execute([":d" => $id_duelo]);
	}

	/**
	 * ¿Ha terminado ya el partido? Entonces se cierra.
	 *
	 * Es el enganche perezoso de la liquidación (§8, no hay cron): lo llaman el
	 * sondeo del partido, la propia página del duelo y el listado de duelos, así
	 * que basta con que UNO de los dos jugadores vuelva a mirar para que el bote
	 * se entregue. No hay nada corriendo de fondo esperando el minuto 90.
	 *
	 * ⚠️ LO QUE OBLIGA A LAS DOS RAMAS DE ABANDONO
	 * Desde que el duelo se decide en el campo, el dinero de los dos está
	 * RETENIDO hasta que alguien liquide. Un partido que se queda a medias ya no
	 * es un partido perdido: es un bote que no vuelve a nadie. Y hay dos formas
	 * de quedarse a medias sin que el reloj llegue nunca al final:
	 *
	 *   · el partido no ARRANCA nunca, porque arrancarlo es cosa del sondeo y
	 *     nadie volvió a abrir la pantalla (o volvió sin JavaScript);
	 *   · el partido se queda PARADO en una decisión, porque quien la tenía que
	 *     tomar se fue y el plazo solo lo aplica el sondeo de alguien presente.
	 *
	 * Las dos se resuelven con el mismo umbral, `partido_abandono_seg`, holgado a
	 * propósito: quien llega tarde a su partido todavía puede jugarlo entero, y
	 * solo cuando ya no va a aparecer nadie se cierra con el marcador tal cual
	 * está. Es la regla de §15.3 ("si no estás atento, te lo pierdes") llevada a
	 * su conclusión: te pierdes el partido, no la apuesta.
	 */
	public function cerrarPartidoSiToca($id_duelo) {
		$leer = function () use ($id_duelo) {
			$stmt = $this->pdo->prepare("SELECT * FROM duelos WHERE id_duelo = :d");
			$stmt->execute([":d" => $id_duelo]);
			return $stmt->fetch(PDO::FETCH_ASSOC);
		};
		$duelo = $leer();
		if (!$duelo || $duelo["estado"] !== "en_juego") return false;

		$duracion = max(10, (int) $this->config("partido_duracion_seg", 75));
		$abandono = max($duracion, (int) $this->config("partido_abandono_seg", 3600));

		/* Nadie ha llegado a ver este partido, y ya no va a venir.
		   ⚠️ También pasa por la tanda: si el marcador quedó empatado, liquidar a
		   secas no escribe ganador y el duelo se queda colgado con el bote dentro
		   PARA SIEMPRE. Esta rama se me quedó sin la tanda al hacerla jugable y lo
		   cazó la prueba del Paso 3, no el razonamiento. */
		if (!$duelo["partido_inicio"]) {
			$montado = $duelo["resuelto"] ? time() - strtotime($duelo["resuelto"]) : 0;
			if ($montado < $abandono) return false;
			return $this->cerrarConTandaSiHace($id_duelo, $duelo, true);
		}

		/* Parado en una decisión que nadie va a tomar: este partido está
		   abandonado y se cierra tal cual, sin mirar el reloj.

		   Lo primero que probé aquí fue reanudar y dejar que el reloj siguiera su
		   curso, y la prueba lo tumbó: reanudar suma el tiempo parado a
		   `partido_pausa_seg` —que es lo correcto, ese rato no era partido—, así
		   que el encuentro vuelve al minuto en el que se detuvo y todavía le
		   faltan segundos. Hacía falta una segunda visita para cerrarlo. Un
		   partido que lleva una hora congelado no necesita que le contemos los
		   minutos que le quedaban: necesita cerrarse.

		   La decisión pendiente simplemente no se juega, y eso es lo correcto: no
		   hay nadie a quien aplicarle la opción segura, y sin jugarla no mueve el
		   marcador. El resultado es el que la simulación dejó escrito. */
		if ($duelo["partido_pausado_en"]) {
			if (time() - strtotime($duelo["partido_pausado_en"]) < $abandono) return false;
			// Abandonado: se fuerza también la tanda, si la hubiera.
			return $this->cerrarConTandaSiHace($id_duelo, $duelo, true);
		}

		if (self::segundosDePartido($duelo) < $duracion) return false;

		/* ¿Cuánto lleva terminado el partido? Pasado el plazo de abandono ya no
		   queda nadie a quien esperar y la tanda se decide sola. */
		$abandonada = self::segundosDePartido($duelo) >= $duracion + $abandono;
		return $this->cerrarConTandaSiHace($id_duelo, $duelo, $abandonada);
	}

	/**
	 * Si el partido acabó EMPATADO, empuja la tanda antes de liquidar; luego
	 * liquida. Si la tanda todavía no tiene ganador, liquidarPartido() no hace
	 * nada y el duelo espera al siguiente sondeo.
	 *
	 * ⚠️ Existe porque, al hacer jugable la tanda, apareció una forma nueva de
	 * dejar un duelo colgado con el dinero de los dos dentro: partido empatado +
	 * tanda a medias + nadie que vuelva. Con `$forzar` la tanda se decide entera
	 * sin esperar plazos, que es lo que hace que esa situación no exista.
	 */
	private function cerrarConTandaSiHace($id_duelo, array $duelo, $forzar) {
		if ((int) $duelo["goles_creador"] === (int) $duelo["goles_rival"]) {
			$this->tandaAvanzar($id_duelo, $forzar);
		}
		return (bool) $this->liquidarPartido($id_duelo)["ok"];
	}

	/**
	 * Barre los partidos en juego de un jugador y cierra los que ya han
	 * terminado. Lo llama el listado de duelos.
	 *
	 * Sin esto quedaba un caso sin cubrir: si los DOS cierran la pestaña a mitad
	 * de partido y ninguno vuelve a abrir ese duelo en concreto, nadie liquida y
	 * el bote se queda retenido. Abrir la lista de duelos es lo primero que hace
	 * cualquiera al volver, así que es donde tiene que estar la red.
	 *
	 * Acotado a los duelos DEL JUGADOR: es una limpieza perezosa, no una tarea
	 * de mantenimiento, y no debe crecer con el tamaño de la base.
	 */
	public function cerrarPartidosPendientes($id_usuario) {
		$stmt = $this->pdo->prepare("
			SELECT id_duelo FROM duelos
			WHERE estado = 'en_juego' AND (id_creador = :u1 OR id_rival = :u2)
		");
		$stmt->execute([":u1" => (int) $id_usuario, ":u2" => (int) $id_usuario]);

		$cerrados = 0;
		foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
			if ($this->cerrarPartidoSiToca((int) $id)) $cerrados++;
		}
		return $cerrados;
	}

	/**
	 * Cierra un partido que ha llegado al final: decide el ganador con el
	 * marcador que ha quedado, rompe el empate en la tanda, entrega el bote y
	 * pasa el duelo a `resuelto`.
	 *
	 * ⚠️ SE LLAMA DESDE UN SONDEO, así que la llaman LOS DOS JUGADORES a la vez y
	 * muchas veces. Que el bote se entregue una sola vez no lo garantiza este
	 * PHP: lo garantiza la condición `estado = 'en_juego'` dentro del UPDATE, que
	 * es la misma técnica que usa descontarGolRival() y por el mismo motivo
	 * (comprobar y luego actualizar deja una ventana por la que dos peticiones
	 * simultáneas pagarían dos veces). Si el UPDATE no toca ninguna fila, otro ya
	 * liquidó y aquí no se paga nada.
	 *
	 * El dinero YA está retenido desde que cada uno entró (crearDuelo y
	 * aceptarDuelo), así que esto no cobra: solo entrega.
	 *
	 * EN CADENAS (§15.12) cierra además el nodo: calcula el rango con el marcador
	 * final, lo guarda, registra el progreso y reparte monedas y botín. Todo eso
	 * estaba en resolverDuelo() hasta que el partido pasó a decidir también en
	 * PvE. El "bote" de una cadena es de 0 monedas —no se apuesta nada—, así que
	 * la entrega de arriba no hace nada ahí: lo que importa es el botín.
	 *
	 * @return array{ok:bool, id_ganador:?int, por_tanda:bool, rango:?string}
	 */
	public function liquidarPartido($id_duelo) {
		try {
			$this->pdo->beginTransaction();

			$stmt = $this->pdo->prepare("SELECT * FROM duelos WHERE id_duelo = :d FOR UPDATE");
			$stmt->execute([":d" => $id_duelo]);
			$duelo = $stmt->fetch(PDO::FETCH_ASSOC);

			if (!$duelo || $duelo["estado"] !== "en_juego") {
				$this->pdo->rollBack();
				return ["ok" => false, "id_ganador" => null, "por_tanda" => false, "rango" => null];
			}

			$idCreador = (int) $duelo["id_creador"];
			$idRival   = (int) $duelo["id_rival"];
			$golesC    = (int) $duelo["goles_creador"];
			$golesR    = (int) $duelo["goles_rival"];
			$esPve     = $duelo["dificultad"] !== null;

			$porTanda = false;
			if ($golesC > $golesR)      { $ganaLado = "local"; }
			elseif ($golesR > $golesC)  { $ganaLado = "visitante"; }
			else {
				/* Empate: lo decide la TANDA, que se juega (§15.11). Aquí solo se
				   LEE su resultado; jugarla es cosa del sondeo. Si todavía no hay
				   ganador, no se liquida nada: el duelo se queda en `en_juego` con
				   la tanda abierta y volverá por aquí cuando termine. */
				$porTanda = true;
				$tanda = $this->tandaEstado($id_duelo);

				if ($tanda["gana"] === null) {
					$this->pdo->rollBack();
					return ["ok" => false, "id_ganador" => null, "por_tanda" => false, "rango" => null];
				}
				$ganaLado = $tanda["gana"];
			}

			$idGanador  = $ganaLado === "local" ? $idCreador : $idRival;
			$idPerdedor = $ganaLado === "local" ? $idRival   : $idCreador;

			/* EL RANGO DE CADENA SE CALCULA AQUÍ (§15.12), porque hasta ahora no
			   había marcador final. En una cadena el jugador es siempre el creador.

			   ⚠️ El `?? "B"` no es un adorno. Cuando el partido acaba empatado y lo
			   decide la tanda, rangoPartido() devuelve null —nadie ganó EN EL
			   CAMPO—, y dejarlo en null tendría un efecto que desde aquí no se ve:
			   mapaCadena() da un nodo por superado si tiene `mejor_rango`, así que
			   una victoria en los penaltis no abriría el nodo siguiente y la cadena
			   se quedaría cortada. Ganar es ganar; el suelo es B, que es justo lo
			   que significa la B ("B = ganar"). */
			$rango = null;
			if ($esPve) {
				$rango = $idGanador === $idCreador
					? ($this->rangoPartido($golesC, $golesR) ?? "B")
					: null;
			}

			// El cierre y la condición de carrera, en la misma sentencia.
			$cerrar = $this->pdo->prepare("
				UPDATE duelos
				SET estado = 'resuelto', id_ganador = :g, resuelto_por_tanda = :t, rango = :r
				WHERE id_duelo = :d AND estado = 'en_juego'
			");
			$cerrar->execute([":g" => $idGanador, ":t" => $porTanda ? 1 : 0,
			                  ":r" => $rango, ":d" => $id_duelo]);
			if ($cerrar->rowCount() === 0) {
				// Otro sondeo se nos adelantó: no se paga dos veces.
				$this->pdo->rollBack();
				return ["ok" => false, "id_ganador" => null, "por_tanda" => false, "rango" => null];
			}

			// --- entregar el bote (ya estaba retenido) ---
			if ($duelo["tipo_apuesta"] === "monedas") {
				$bote = ((int) $duelo["monedas"]) * 2;
				$this->pdo->prepare("UPDATE usuarios SET monedas = monedas + :bote WHERE id_usuario = :id")
					->execute([":bote" => $bote, ":id" => $idGanador]);
			} else {
				$stmtCarta = $this->pdo->prepare("
					SELECT id_coleccion FROM duelo_apuestas
					WHERE id_duelo = :id_duelo AND id_usuario = :id_usuario
				");
				$stmtCarta->execute([":id_duelo" => $id_duelo, ":id_usuario" => $idPerdedor]);
				$copiaPerdida = $stmtCarta->fetchColumn();
				if ($copiaPerdida) {
					$this->pdo->prepare("
						UPDATE coleccion SET id_usuario = :ganador, bloqueada = 0
						WHERE id_coleccion = :id_coleccion
					")->execute([":ganador" => $idGanador, ":id_coleccion" => $copiaPerdida]);
				}
			}

			/* --- CADENAS: progreso del nodo y botín DEL PARTIDO (§15.12) ---
			   Estaba en resolverDuelo(), y se mudó aquí cuando el partido pasó a
			   decidir también en PvE: allí ya no hay marcador final, y del marcador
			   final salen el rango y la recompensa. Esto es, literalmente, lo que
			   hace que los minijuegos cuenten en una cadena.

			   ⚠️ VA DESPUÉS DEL UPDATE DE ARRIBA, Y NO ES CASUAL. Ese UPDATE con
			   `estado = 'en_juego'` en el WHERE es lo único que garantiza que una
			   sola llamada liquide, y las dos pantallas sondean a la vez. Poniendo
			   el reparto antes, dos sondeos simultáneos entregarían el botín dos
			   veces; poniéndolo aquí hereda esa protección sin añadir nada. */
			if ($esPve && $duelo["id_nodo"]) {
				$gano = $idGanador === $idCreador;   // en una cadena el jugador es siempre el creador
				$vecesPrevias = $this->registrarProgresoNodo(
					$idCreador,
					(int) $duelo["id_nodo"],
					$duelo["dificultad"],
					$gano,
					$rango
				);

				if ($gano) {
					$monedas = $this->calcularRecompensaMonedas($duelo["dificultad"], $rango, $vecesPrevias);
					$this->pdo->prepare("UPDATE usuarios SET monedas = monedas + :m WHERE id_usuario = :u")
						->execute([":m" => $monedas, ":u" => $idCreador]);
					$this->registrarDrop($idCreador, $id_duelo, (int) $duelo["id_nodo"], "monedas", null, null, $monedas, null);

					$this->otorgarLootNodo((int) $duelo["id_nodo"], $idCreador, $rango, $id_duelo);
				}
			}

			$this->pdo->commit();
			return ["ok" => true, "id_ganador" => $idGanador, "por_tanda" => $porTanda, "rango" => $rango];
		} catch (Throwable $e) {
			if ($this->pdo->inTransaction()) $this->pdo->rollBack();
			return ["ok" => false, "id_ganador" => null, "por_tanda" => false, "rango" => null];
		}
	}

	/* ======================================================================
	   LA TANDA DE PENALTIS, JUGADA  (migración 024, §15.11)
	   ----------------------------------------------------------------------
	   La regla entera cabe en una línea: la portería tiene CUATRO huecos,
	   tirador y portero eligen uno cada uno, y si coinciden es parada; si no,
	   gol. Ni Ataque ni Portería entran en la cuenta — es un pulso de
	   intenciones, no de estadísticas.

	   ⚠️ ES LA PRIMERA INTERACCIÓN SIMULTÁNEA DEL JUEGO, y eso rompe el
	   supuesto sobre el que está construido todo lo demás. En un minijuego el
	   dato oculto sale de las cartas: el servidor lo puede recalcular cuando
	   quiera y por eso la narración entera es función de `valor_sorteo`. Aquí
	   el dato oculto es **lo que el otro jugador está eligiendo ahora mismo**,
	   que no se deriva de nada. De ahí la tabla `duelo_penaltis`: hay que
	   guardarlo porque no se puede reconstruir.

	   Tres consecuencias que hay que respetar al tocar esto:

	   · La elección del rival NO puede viajar al cliente antes de resolverse
	     el tiro (§6.3). Verla sería ganar siempre.
	   · La idempotencia sale de la clave primaria (duelo, ronda, turno) y de
	     `zona_X IS NULL` dentro del UPDATE, no de comprobar antes en PHP:
	     los dos jugadores sondean a la vez.
	   · El plazo tiene que resolver solo, porque si alguien se va a mitad de
	     tanda el bote se queda retenido para siempre. Ese es el mismo motivo
	     que obliga a las dos ramas de abandono del partido.

	   La sustituye a la tanda automática anterior, que se ha RETIRADO en vez
	   de dejarla muerta: si siguiera ahí, alguien volvería a llamarla y el
	   duelo se decidiría sin que el jugador tocase nada, que es exactamente
	   lo que este trabajo vino a quitar.
	   ====================================================================== */

	/** Los cuatro huecos. El orden es el de lectura: arriba-izq → abajo-der. */
	const ZONAS_PENALTI = ["arriba_izq", "arriba_der", "abajo_izq", "abajo_der"];

	/** Tiros reglamentarios por bando antes de la muerte súbita. */
	const TANDA_TIROS_BASE = 5;

	/** Tope de rondas. No es decoración: sin él una muerte súbita podría no
	 *  acabar nunca y el duelo se quedaría sin liquidar, con el bote retenido. */
	const TANDA_MAX_RONDAS = 25;

	/**
	 * Quién tira en el turno 0 de cada ronda.
	 *
	 * Sale del sorteo del duelo y no de "siempre el creador", porque tirar
	 * primero es una ventaja real en una tanda: el que va detrás lanza siempre
	 * bajo la presión de tener que igualar. Determinista, como todo aquí.
	 */
	private static function tandaTiraPrimero(array $duelo) {
		return ((float) $duelo["valor_sorteo"]) < 0.5
			? (int) $duelo["id_creador"] : (int) $duelo["id_rival"];
	}

	/**
	 * El estado completo de la tanda, DERIVADO de los tiros guardados.
	 *
	 * No guarda marcador propio ni "de quién es el turno": se recalcula todo de
	 * las filas, así que dos sondeos simultáneos no pueden discrepar y recargar
	 * la página no pierde nada.
	 *
	 * @return array{goles:array,tiros:array,ronda:int,turno:int,gana:?string,abierto:?array}
	 */
	public function tandaEstado($id_duelo) {
		$stmt = $this->pdo->prepare("SELECT * FROM duelos WHERE id_duelo = :d");
		$stmt->execute([":d" => $id_duelo]);
		$duelo = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$duelo) return ["goles" => [0, 0], "tiros" => [], "ronda" => 1,
		                     "turno" => 0, "gana" => null, "abierto" => null];

		$stmt = $this->pdo->prepare("
			SELECT * FROM duelo_penaltis WHERE id_duelo = :d ORDER BY ronda, turno
		");
		$stmt->execute([":d" => $id_duelo]);
		$tiros = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$goles = [0, 0];      // [turno 0, turno 1]
		$hechos = [0, 0];
		$abierto = null;
		foreach ($tiros as $t) {
			$i = (int) $t["turno"];
			if ($t["gol"] === null) { $abierto = $t; continue; }
			$hechos[$i]++;
			if ((int) $t["gol"] === 1) $goles[$i]++;
		}

		$base = self::TANDA_TIROS_BASE;
		$gana = null;

		/* Corte anticipado, que es lo que hace que una tanda se sienta como una
		   tanda: en cuanto uno no puede alcanzar al otro ni marcando todo lo que
		   le queda, se acabó. Sin esto un 3-0 seguiría lanzando hasta el quinto. */
		if ($hechos[0] <= $base && $hechos[1] <= $base) {
			$quedan = [$base - $hechos[0], $base - $hechos[1]];
			if ($goles[0] > $goles[1] + $quedan[1])      $gana = 0;
			elseif ($goles[1] > $goles[0] + $quedan[0])  $gana = 1;
			elseif ($hechos[0] === $base && $hechos[1] === $base && $goles[0] !== $goles[1])
				$gana = $goles[0] > $goles[1] ? 0 : 1;
		}

		// Muerte súbita: solo decide con la ronda COMPLETA, nunca a medias.
		if ($gana === null && $hechos[0] >= $base && $hechos[1] >= $base
		    && $hechos[0] === $hechos[1] && $goles[0] !== $goles[1]) {
			$gana = $goles[0] > $goles[1] ? 0 : 1;
		}

		// Y el tope, para que esto no pueda quedarse colgado nunca.
		if ($gana === null && $hechos[0] >= self::TANDA_MAX_RONDAS) {
			$gana = ((float) $duelo["valor_sorteo"]) < 0.5 ? 0 : 1;
		}

		// Cuál es el tiro que toca ahora (si la tanda sigue viva).
		$ronda = (int) floor(max($hechos[0], $hechos[1]));
		$turno = $hechos[0] > $hechos[1] ? 1 : 0;
		if ($turno === 0) $ronda++;
		if ($abierto) { $ronda = (int) $abierto["ronda"]; $turno = (int) $abierto["turno"]; }

		$primero  = self::tandaTiraPrimero($duelo);
		$esCreador = $primero === (int) $duelo["id_creador"];
		$ladoDe = function ($turnoIdx) use ($esCreador) {
			$creador = ($turnoIdx === 0) === $esCreador;
			return $creador ? "local" : "visitante";
		};

		return [
			"goles"   => $goles,
			"hechos"  => $hechos,
			"tiros"   => $tiros,
			"ronda"   => $ronda,
			"turno"   => $turno,
			"gana"    => $gana === null ? null : $ladoDe($gana),
			"abierto" => $abierto,
			"primero" => $primero,
		];
	}

	/** Quién tira y quién para en un turno dado. */
	private static function tandaProtagonistas(array $duelo, $turno) {
		$primero = self::tandaTiraPrimero($duelo);
		$otro = $primero === (int) $duelo["id_creador"]
			? (int) $duelo["id_rival"] : (int) $duelo["id_creador"];
		return $turno === 0 ? ["tira" => $primero, "para" => $otro]
		                    : ["tira" => $otro,    "para" => $primero];
	}

	/**
	 * Empuja la tanda tan lejos como pueda: abre el tiro que toque, aplica el
	 * plazo a quien no haya elegido y resuelve los tiros que ya tengan las dos
	 * elecciones. Perezoso, como todo (§8): lo llama el sondeo.
	 *
	 * Con $forzar = true no espera al plazo y decide por los dos. Es lo que usa
	 * el cierre por abandono: si nadie va a volver, la tanda tiene que terminar
	 * igualmente o el bote se queda retenido para siempre.
	 */
	public function tandaAvanzar($id_duelo, $forzar = false) {
		$stmt = $this->pdo->prepare("SELECT * FROM duelos WHERE id_duelo = :d");
		$stmt->execute([":d" => $id_duelo]);
		$duelo = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$duelo || $duelo["estado"] !== "en_juego") return false;
		if ((int) $duelo["goles_creador"] !== (int) $duelo["goles_rival"]) return false;

		$plazo = max(3, (int) $this->config("tanda_plazo_seg", 12));
		$esPve = $duelo["dificultad"] !== null;
		$idBot = $esPve ? $this->idBot() : 0;

		/* ⚠️ EL TOPE DE VUELTAS TIENE QUE CUBRIR EL PEOR CASO ENTERO, no solo
		   "muchas". Este bucle no resuelve un tiro por vuelta: ABRIRLO (INSERT +
		   `continue`) y RESOLVERLO son dos vueltas separadas, así que cada tiro
		   cuesta 2. Y `tandaEstado()` solo corta por el tope mirando
		   `hechos[0]` (el turno que tira primero en cada ronda), así que en el
		   peor caso la tanda no se decide hasta el tiro número
		   `2·TANDA_MAX_RONDAS − 1` (el turno 0 llega a su ronda 25 en el tiro
		   49, antes de que el turno 1 dispare el suyo).
		   Con el tope viejo (`TANDA_MAX_RONDAS*2+4` = 54) una muerte súbita que
		   de verdad agotara las 25 rondas se quedaba a media resolución: la
		   función devolvía `true` sin haber decidido nada, `liquidarPartido()`
		   no tenía ganador que escribir, y el duelo se quedaba `en_juego` con
		   el bote retenido — lo cazó `probar_tanda.php`, intermitente porque
		   depende del `valor_sorteo` de cada duelo. `4 * TANDA_MAX_RONDAS`
		   cubre con margen los ~99 pasos que hacen falta en el peor caso. */
		for ($vuelta = 0; $vuelta < self::TANDA_MAX_RONDAS * 4; $vuelta++) {
			$estado = $this->tandaEstado($id_duelo);
			if ($estado["gana"] !== null) return true;

			// ¿Hay tiro abierto? Si no, se abre el que toca.
			if (!$estado["abierto"]) {
				$this->pdo->prepare("
					INSERT IGNORE INTO duelo_penaltis (id_duelo, ronda, turno, abierto)
					VALUES (:d, :r, :t, NOW())
				")->execute([":d" => $id_duelo, ":r" => $estado["ronda"], ":t" => $estado["turno"]]);
				continue;
			}

			$tiro = $estado["abierto"];

			/* ⚠️ EL CPU ELIGE AL INSTANTE, NO AL VENCER EL PLAZO (§15.12, pieza 5).
			   Sin esto una tanda de cadena se juega igual —el plazo acaba eligiendo
			   por él— pero con DOCE SEGUNDOS MUERTOS en cada tiro en los que el
			   jugador mira una portería que no espera nada de él. Una tanda son diez
			   tiros: dos minutos de reloj parado.

			   Elegir antes no filtra nada: la elección del rival no viaja al cliente
			   hasta que el tiro se resuelve (§15.11 y §6.3), y esa es la garantía que
			   protege también el caso humano. Y sigue sin ser adivinable, porque sale
			   de `valor_sorteo`, que no sale del servidor. */
			if ($esPve) {
				$quien = self::tandaProtagonistas($duelo, (int) $tiro["turno"]);
				if ((int) $quien["tira"] === $idBot && $tiro["zona_tirador"] === null) {
					$tiro["zona_tirador"] = $this->elegirZonaAutomatica($id_duelo, $duelo, $tiro, "tirador");
				}
				if ((int) $quien["para"] === $idBot && $tiro["zona_portero"] === null) {
					$tiro["zona_portero"] = $this->elegirZonaAutomatica($id_duelo, $duelo, $tiro, "portero");
				}
			}

			$faltaAlguna = $tiro["zona_tirador"] === null || $tiro["zona_portero"] === null;
			$vencido = $forzar
				|| (time() - strtotime($tiro["abierto"])) >= $plazo;

			if ($faltaAlguna && !$vencido) return true;   // se está jugando; nada que hacer

			// Vencido el plazo, elige el sistema por quien no eligió.
			if ($tiro["zona_tirador"] === null) {
				$this->elegirZonaAutomatica($id_duelo, $duelo, $tiro, "tirador");
			}
			if ($tiro["zona_portero"] === null) {
				$this->elegirZonaAutomatica($id_duelo, $duelo, $tiro, "portero");
			}

			if (!$this->resolverTiro($id_duelo, (int) $tiro["ronda"], (int) $tiro["turno"])) {
				return true;   // no se pudo cerrar; se reintentará en el siguiente sondeo
			}
		}
		return true;
	}

	/**
	 * Elige por quien no ha elegido —el CPU al instante, o quien dejó vencer su
	 * plazo— y devuelve la zona escrita.
	 *
	 * La zona sale del SORTEO DEL DUELO, no de `mt_rand()`: los dos jugadores
	 * sondean a la vez y con azar real cada uno calcularía una zona distinta para
	 * el mismo tiro. Cada lado lleva su propia sal para que la del tirador y la
	 * del portero no queden correlacionadas — si lo estuvieran, el porcentaje de
	 * paradas dejaría de ser el 25 % que sale de la regla.
	 *
	 * `zona_X IS NULL` en el WHERE es lo que hace que esto no pueda pisar una
	 * elección real que haya entrado un instante antes.
	 */
	private function elegirZonaAutomatica($id_duelo, array $duelo, array $tiro, $cual) {
		$cual = $cual === "tirador" ? "tirador" : "portero";
		$sal  = $cual === "tirador" ? 7717 : 3331;

		$clave = ((int) $tiro["ronda"]) * 4 + ((int) $tiro["turno"]);
		$zona  = self::ZONAS_PENALTI[(int) floor(self::azarDeJugada(
			(float) $duelo["valor_sorteo"], $clave, $sal) * 4) % 4];

		$this->pdo->prepare("
			UPDATE duelo_penaltis SET zona_$cual = :z, auto_$cual = 1
			WHERE id_duelo = :d AND ronda = :r AND turno = :t AND zona_$cual IS NULL
		")->execute([":z" => $zona, ":d" => $id_duelo,
		             ":r" => $tiro["ronda"], ":t" => $tiro["turno"]]);

		return $zona;
	}

	/**
	 * Cierra un tiro que ya tiene las dos elecciones. LA REGLA ENTERA ESTÁ AQUÍ:
	 * misma zona, parada; distinta, gol.
	 *
	 * La condición va DENTRO del UPDATE (`gol IS NULL` y las dos zonas puestas),
	 * así que dos sondeos simultáneos no pueden resolverlo dos veces ni con
	 * resultados distintos.
	 */
	private function resolverTiro($id_duelo, $ronda, $turno) {
		$stmt = $this->pdo->prepare("
			UPDATE duelo_penaltis
			SET gol = IF(zona_tirador = zona_portero, 0, 1), resuelto = NOW()
			WHERE id_duelo = :d AND ronda = :r AND turno = :t
			  AND gol IS NULL AND zona_tirador IS NOT NULL AND zona_portero IS NOT NULL
		");
		$stmt->execute([":d" => $id_duelo, ":r" => $ronda, ":t" => $turno]);
		if ($stmt->rowCount() > 0) return true;

		// O ya estaba resuelto (otro sondeo se adelantó), o falta una elección.
		$comprobar = $this->pdo->prepare("
			SELECT gol FROM duelo_penaltis WHERE id_duelo = :d AND ronda = :r AND turno = :t
		");
		$comprobar->execute([":d" => $id_duelo, ":r" => $ronda, ":t" => $turno]);
		return $comprobar->fetchColumn() !== null;
	}

	/**
	 * Registra la zona que ha elegido un jugador en el tiro abierto.
	 *
	 * `zona_X IS NULL` dentro del UPDATE es lo que impide cambiar de opinión y,
	 * sobre todo, lo que impide elegir DESPUÉS de que el tiro se resolviera.
	 */
	public function tirarPenalti($id_duelo, $id_usuario, $zona) {
		if (!in_array($zona, self::ZONAS_PENALTI, true)) {
			return ["ok" => false, "error" => "Ese hueco no existe."];
		}

		$duelo = $this->obtenerDuelo($id_duelo, $id_usuario);
		if (!$duelo || $duelo["estado"] !== "en_juego") {
			return ["ok" => false, "error" => "Este duelo ya no admite penaltis."];
		}

		$this->tandaAvanzar($id_duelo);           // abre el tiro si hacía falta
		$estado = $this->tandaEstado($id_duelo);
		if (!$estado["abierto"] || $estado["gana"] !== null) {
			return ["ok" => false, "error" => "Ahora mismo no hay ningún penalti que lanzar."];
		}

		$tiro  = $estado["abierto"];
		$quien = self::tandaProtagonistas($duelo, (int) $tiro["turno"]);
		$campo = (int) $id_usuario === $quien["tira"] ? "zona_tirador"
		       : ((int) $id_usuario === $quien["para"] ? "zona_portero" : null);
		if ($campo === null) return ["ok" => false, "error" => "Este penalti no es tuyo."];

		$stmt = $this->pdo->prepare("
			UPDATE duelo_penaltis SET $campo = :z
			WHERE id_duelo = :d AND ronda = :r AND turno = :t AND $campo IS NULL AND gol IS NULL
		");
		$stmt->execute([":z" => $zona, ":d" => $id_duelo,
		                ":r" => $tiro["ronda"], ":t" => $tiro["turno"]]);
		if ($stmt->rowCount() === 0) {
			return ["ok" => false, "error" => "Ya habías elegido en este penalti."];
		}

		$this->resolverTiro($id_duelo, (int) $tiro["ronda"], (int) $tiro["turno"]);
		return ["ok" => true, "tiraba" => $campo === "zona_tirador"];
	}

	/**
	 * La tanda vista por UN jugador, lista para pintar.
	 *
	 * ⚠️ EL FILTRO DE §6.3 ESTÁ AQUÍ. Del tiro en curso solo se dice si YO ya
	 * elegí; la zona del rival no viaja jamás, ni siquiera la mía de vuelta hace
	 * falta. Del historial sí van las dos zonas, porque esos tiros ya están
	 * resueltos y enseñarlos es justo la gracia: aprender a leer al otro.
	 */
	public function tandaParaCliente($id_duelo, $id_usuario) {
		$duelo = $this->obtenerDuelo($id_duelo, $id_usuario);
		if (!$duelo) return null;

		$estado = $this->tandaEstado($id_duelo);
		$soyCreador = (int) $duelo["id_creador"] === (int) $id_usuario;

		// El marcador de la tanda, desde mi lado.
		$primero = $estado["primero"];
		$mioEsTurno0 = $primero === (int) $id_usuario;
		$mios  = $mioEsTurno0 ? $estado["goles"][0] : $estado["goles"][1];
		$suyos = $mioEsTurno0 ? $estado["goles"][1] : $estado["goles"][0];

		$historial = [];
		foreach ($estado["tiros"] as $t) {
			if ($t["gol"] === null) continue;
			$quien = self::tandaProtagonistas($duelo, (int) $t["turno"]);
			$historial[] = [
				"ronda"  => (int) $t["ronda"],
				"mio"    => $quien["tira"] === (int) $id_usuario,   // ¿tiraba yo?
				"gol"    => (int) $t["gol"] === 1,
				"tirador" => $t["zona_tirador"],
				"portero" => $t["zona_portero"],
				"auto"   => (bool) ($quien["tira"] === (int) $id_usuario
					? $t["auto_tirador"] : $t["auto_portero"]),
			];
		}

		$abierto = $estado["abierto"];
		$tiro = null;
		if ($abierto && $estado["gana"] === null) {
			$quien = self::tandaProtagonistas($duelo, (int) $abierto["turno"]);
			$tiroYo = $quien["tira"] === (int) $id_usuario;
			$campoMio = $tiroYo ? "zona_tirador" : "zona_portero";
			$plazo = max(3, (int) $this->config("tanda_plazo_seg", 12));
			$tiro = [
				"ronda"     => (int) $abierto["ronda"],
				"tiro_yo"   => $tiroYo,
				// Solo si YO ya elegí. La del rival no sale de aquí.
				"ya_elegi"  => $abierto[$campoMio] !== null,
				"restante"  => max(0, $plazo - (time() - strtotime($abierto["abierto"]))),
			];
		}

		return [
			"zonas"     => self::ZONAS_PENALTI,
			"marcador"  => [$mios, $suyos],
			"historial" => $historial,
			"tiro"      => $tiro,
			"muerte_subita" => $estado["hechos"][0] >= self::TANDA_TIROS_BASE
			                && $estado["hechos"][1] >= self::TANDA_TIROS_BASE,
			"acabada"   => $estado["gana"] !== null,
		];
	}

	/** Los minijuegos ya resueltos de un duelo, indexados por evento+usuario. */
	public function minijuegosResueltos($id_duelo) {
		$stmt = $this->pdo->prepare("SELECT * FROM duelo_minijuegos WHERE id_duelo = :d");
		$stmt->execute([":d" => $id_duelo]);
		$fuera = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
			$fuera[$f["id_evento"] . ":" . $f["id_usuario"]] = $f;
		}
		return $fuera;
	}

	/**
	 * Quién juega el minijuego de cada evento, visto desde fuera de los dos
	 * jugadores. Devuelve [id_evento => id_usuario].
	 *
	 * Hace falta mirar el duelo desde los DOS lados porque narracionDuelo()
	 * razona en primera persona: una ocasión es "ataque mío" para uno y
	 * "ocasión del rival" para el otro, y el minijuego solo se le ofrece a
	 * quien defiende. Las dos lecturas nunca se contradicen —cada evento tiene
	 * un único defensor— así que juntarlas es seguro.
	 */
	private function duenosDeMinijuego($id_duelo, $idCreador, $idRival) {
		$duenos = [];
		$idBot = $this->idBot();
		foreach ([$idCreador, $idRival] as $quien) {
			/* ⚠️ EL CPU NO JUEGA MINIJUEGOS, y esto no es un detalle de comodidad.
			   Un minijuego con dueño detiene el partido hasta que lo resuelven o
			   vence el plazo, así que darle decisiones al bot —que no tiene
			   pantalla— pausaría TU partido de cadena nueve segundos por cada una,
			   varias veces, y el jugador solo vería el reloj parado sin nada que
			   hacer. Sus jugadas se narran igual; lo que no pasa es que esperen. */
			if ((int) $quien === $idBot) continue;

			$n = $this->narracionDuelo($id_duelo, $quien);
			if (empty($n["ok"])) continue;
			foreach ($n["eventos"] as $e) {
				if (!empty($e["interactivo"]) && !empty($e["minijuego"])) {
					$duenos[$e["id"]] = $quien;
				}
			}
		}
		return $duenos;
	}

	/**
	 * EL SONDEO. Devuelve en qué minuto va el partido, qué se ha narrado hasta
	 * ahí y si hay un minijuego esperando decisión.
	 *
	 * Todo se evalúa aquí, en diferido: arrancar el reloj, pausarlo al llegar a
	 * un minijuego, resolver por fallback al que no contesta y reanudar. No hay
	 * nada corriendo de fondo entre sondeo y sondeo.
	 */
	public function estadoPartido($id_duelo, $id_usuario) {
		$duelo = $this->obtenerDuelo($id_duelo, $id_usuario);
		if (!$duelo) return ["ok" => false, "error" => "Ese duelo no existe o no es tuyo."];
		if (!in_array($duelo["estado"], self::ESTADOS_CON_PARTIDO, true)) {
			return ["ok" => false, "error" => "Ese partido todavía no se ha jugado."];
		}

		$this->latirPartido($id_duelo, $id_usuario);
		$duelo = $this->arrancarPartidoSiToca($this->obtenerDuelo($id_duelo, $id_usuario));

		$narracion = $this->narracionDuelo($id_duelo, $id_usuario);
		if (empty($narracion["ok"])) return $narracion;

		if (!$duelo["partido_inicio"]) {
			// `decidido` va también aquí, aunque en esta fase el cliente no lo
			// mire: una respuesta que cambia de forma según la fase es una trampa
			// esperando a que alguien lea la clave en el sitio equivocado.
			return ["ok" => true, "fase" => "esperando", "minuto" => 0, "eventos" => [],
			        "nombres" => $narracion["nombres"], "marcador" => [0, 0],
			        "decidido" => $duelo["estado"] === "resuelto",
			        "por_tanda" => (bool) ($duelo["resuelto_por_tanda"] ?? 0)];
		}

		$duracion = max(10, (int) $this->config("partido_duracion_seg", 75));
		$minutos  = (int) $narracion["minutos"];
		$avance   = min(1.0, self::segundosDePartido($duelo) / $duracion);
		$minutoActual = $avance * $minutos;

		$resueltos = $this->minijuegosResueltos($id_duelo);
		$duenos    = $this->duenosDeMinijuego($id_duelo, (int) $duelo["id_creador"], (int) $duelo["id_rival"]);

		/* ¿Hay un minijuego que ya toca y que nadie ha resuelto? El partido se
		   detiene AHÍ, aunque el reloj de pared siga corriendo: el minuto no
		   avanza mientras partido_pausado_en esté puesto. */
		$pendiente = null;
		foreach ($narracion["eventos"] as $e) {
			if (empty($duenos[$e["id"]])) continue;
			if ($e["minuto"] > $minutoActual) break;
			if (isset($resueltos[$e["id"] . ":" . $duenos[$e["id"]]])) continue;
			$pendiente = $e;
			break;
		}

		if ($pendiente) {
			$this->pausarPartido($id_duelo);
			$duelo = $this->obtenerDuelo($id_duelo, $id_usuario);

			$plazo = (int) ($pendiente["minijuego"]["plazo"] ?? 9);
			$parado = $duelo["partido_pausado_en"] ? time() - strtotime($duelo["partido_pausado_en"]) : 0;

			// Se acabó el plazo: decide el sistema por quien no contestó, con la
			// opción SEGURA (§1.5 regla 4), y el partido sigue para los dos.
			if ($parado >= $plazo) {
				$this->resolverMinijuegoDuelo($id_duelo, $duenos[$pendiente["id"]], (int) $pendiente["id"], "");
				$this->reanudarPartido($id_duelo);
				$duelo = $this->obtenerDuelo($id_duelo, $id_usuario);
				$pendiente = null;
			}
		}

		// El minuto se recalcula tras haber podido pausar/reanudar arriba.
		$avance = min(1.0, self::segundosDePartido($duelo) / $duracion);
		$minutoActual = $avance * $minutos;

		/* FINAL DEL PARTIDO — aquí se decide el duelo.
		   El marcador que hay guardado en este momento ES el resultado: lo puso
		   la simulación y lo movieron las decisiones de los dos jugadores. Se
		   determina el ganador y se entrega el bote. Lo piden los DOS jugadores
		   en cada sondeo; que se pague una sola vez lo garantiza el WHERE de
		   liquidarPartido().

		   Si el partido acabó EMPATADO no se liquida todavía: la tanda se juega
		   (§15.11), así que el duelo se queda en `en_juego` con la tanda abierta y
		   liquidarPartido() no encuentra ganador hasta que termine. */
		/* ⚠️ `!$pendiente` NO ES OPCIONAL: las fases tienen que ser excluyentes.
		   Sin esa condición el sondeo podía anunciar la tanda Y una decisión
		   pendiente a la vez, y eso es un estado del que no se sale: la decisión
		   pausa el partido, `cerrarPartidoSiToca()` se niega a cerrar mientras
		   está pausado, y el duelo se queda con el bote dentro mientras el cliente
		   pinta penaltis. Lo cazó la prueba de 300 duelos, con 74 colgados.

		   Y el orden correcto es este: una decisión pendiente todavía puede mover
		   el marcador y deshacer el empate, así que hasta que no se resuelva no se
		   sabe siquiera si hay tanda que jugar. */
		$tanda = null;
		if ($avance >= 1 && !$pendiente) {
			$empatado = (int) $duelo["goles_creador"] === (int) $duelo["goles_rival"];
			// cerrarPartidoSiToca() ya empuja la tanda cuando hay empate, así que
			// no hace falta llamarla aquí aparte: solo leer cómo quedó.
			$this->cerrarPartidoSiToca($id_duelo);
			$duelo = $this->obtenerDuelo($id_duelo, $id_usuario);
			if ($empatado) $tanda = $this->tandaParaCliente($id_duelo, $id_usuario);
		}

		$hasta = [];
		foreach ($narracion["eventos"] as $e) {
			if ($e["minuto"] > $minutoActual) break;
			$hasta[] = $e;
		}
		$ultimo = end($hasta) ?: null;

		$mio = $pendiente && (int) $duenos[$pendiente["id"]] === (int) $id_usuario;

		/* La TANDA es una fase propia entre el final del partido y el resultado.
		   No es "final": el duelo todavía no está decidido y el cliente no debe
		   irse a buscar la pantalla de resultado, que aún no existe. */
		$enTanda = $tanda !== null && empty($tanda["acabada"]);

		return [
			"ok"        => true,
			"fase"      => $pendiente ? "minijuego"
			             : ($enTanda ? "tanda"
			             : ($avance >= 1 ? "final" : "jugando")),
			"tanda"     => $tanda,
			"minuto"    => (int) floor($minutoActual),
			"minutos"   => $minutos,
			"avance"    => round($avance, 4),
			"eventos"   => $hasta,
			"marcador"  => $ultimo ? $ultimo["marcador"] : [0, 0],
			"stats"     => $narracion["stats"],
			"nombres"   => $narracion["nombres"],
			// Solo se manda el minijuego a quien le toca defender; al otro se le
			// dice que espere, para que vea por qué se ha parado el partido.
			"minijuego" => ($pendiente && $mio) ? $pendiente["minijuego"] + ["id_evento" => $pendiente["id"]] : null,
			"esperando_rival" => (bool) ($pendiente && !$mio),
			/* Si el duelo ya está decidido. La pantalla de resultado se renderiza
			   en el servidor y se pintó ANTES de que el partido acabara, cuando
			   todavía no había ganador: con esto el cliente sabe que al terminar
			   tiene que ir a por ella en vez de destapar una pantalla en blanco.
			   Ver el final de assets/js/duelo.js. */
			"decidido"  => $duelo["estado"] === "resuelto",
			"por_tanda" => (bool) ($duelo["resuelto_por_tanda"] ?? 0),
			/* Puntuación de actuación (§4.6 y §6.4): independiente de ganar o
			   perder. Es lo que hace que una jugada siga importando cuando el
			   marcador ya no puede moverse, y lo que da algo que optimizar a
			   quien pierde. Se deriva de lo guardado, no se acumula aparte. */
			"actuacion" => $this->actuacionDuelo($id_duelo, $id_usuario),
		];
	}

	/**
	 * EL VEREDICTO DEL PARTIDO  (§1.5 regla 7 y §6.1)
	 *
	 * "Incluso una derrota debe generar su propio resumen narrado con algún
	 * dato memorable y específico, nunca limitarse a mostrar 'has perdido'."
	 * Hasta ahora la pantalla de resultado enseñaba exactamente eso: la palabra
	 * Victoria o Derrota y el marcador.
	 *
	 * La gracia está en el DATO, no en la frase: se buscan hechos concretos de
	 * ESE partido y se elige el más memorable. Por eso van ordenados por peso —
	 * haber sacado un gol bajo palos se cuenta antes que haber tenido la
	 * posesión, aunque las dos cosas sean verdad.
	 *
	 * Del mecanismo 4 de Copero (§6.1): lo que sale de aquí tiene que poder
	 * pegarse en Discord tal cual. De ahí "compartible", en una sola línea.
	 */
	public function veredictoDuelo($id_duelo, $id_usuario) {
		$n = $this->narracionDuelo($id_duelo, $id_usuario);
		if (empty($n["ok"])) return null;

		$duelo  = $this->obtenerDuelo($id_duelo, $id_usuario);
		$mios   = (int) $n["marcador"][0];
		$suyos  = (int) $n["marcador"][1];
		$ganoYo = (bool) $n["gano_yo"];
		$act    = $this->actuacionDuelo($id_duelo, $id_usuario);

		// Goles narrados, en orden, para poder hablar de minutos concretos.
		$goles = [];
		foreach ($n["eventos"] as $e) {
			if ($e["tipo"] === "gol") $goles[] = $e;
		}

		/* El gol decisivo: el último que dejó al ganador por delante para no
		   volver a igualarse. Es el minuto que de verdad se recuerda, mucho más
		   que el del último gol del partido. */
		$decisivo = null;
		foreach ($goles as $g) {
			$vaGanandoElGanador = $ganoYo
				? $g["marcador"][0] > $g["marcador"][1]
				: $g["marcador"][1] > $g["marcador"][0];
			if ($vaGanandoElGanador && $decisivo === null) $decisivo = $g;
			if (!$vaGanandoElGanador) $decisivo = null;   // se volvió a igualar
		}

		// ¿El que acabó ganando llegó a ir por detrás? Eso es una remontada.
		$huboRemontada = false;
		foreach ($goles as $g) {
			$ganadorDetras = $ganoYo
				? $g["marcador"][0] < $g["marcador"][1]
				: $g["marcador"][1] < $g["marcador"][0];
			if ($ganadorDetras) $huboRemontada = true;
		}

		// Jugadas de minijuego que llegaron a cambiar el marcador.
		$miParada = null; $miGol = null;
		$catalogo = self::catalogoMinijuegos();
		foreach ($this->minijuegosResueltos($id_duelo) as $r) {
			if (!$r["aplicado"] || (int) $r["id_usuario"] !== (int) $id_usuario) continue;
			foreach ($n["eventos"] as $e) {
				if ((int) $e["id"] !== (int) $r["id_evento"]) continue;
				if (($catalogo[$r["minijuego"]]["lado"] ?? "defiendo") === "defiendo") $miParada = $e;
				else $miGol = $e;
			}
		}

		$rival = $n["nombres"]["suyo"];

		/* La TANDA va antes que cualquier otro hecho, y no por dramatismo: si el
		   partido acabó empatado, el marcador que se enseña arriba no explica por
		   sí solo por qué hay un ganador. Callarlo dejaría un 1-1 con la palabra
		   "Victoria" al lado, que se lee como un error del juego. */
		$porTanda = !empty($duelo["resuelto_por_tanda"]);

		$titular = $porTanda
			? ($ganoYo ? "Victoria en los penaltis" : "Derrota en los penaltis")
			: ($ganoYo ? "Victoria" : "Derrota");

		/* Los hechos, del más memorable al más genérico. El primero que aplique
		   es el que se cuenta. */
		if ($porTanda) {
			$detalle = $ganoYo
				? "Se fue a los penaltis y los ganaste."
				: "Se fue a los penaltis y se perdió ahí.";
		} elseif ($miParada) {
			$detalle = "Sacaste bajo palos el gol del " . $miParada["minuto"] . "'. Sin esa mano, otro partido.";
		} elseif ($miGol) {
			$detalle = "La del " . $miGol["minuto"] . "' la metiste tú eligiendo dónde poner el remate.";
		} elseif ($decisivo && $decisivo["minuto"] > self::PARTIDO_MINUTOS) {
			$detalle = $ganoYo
				? "La ganaste en el descuento, en el " . $decisivo["minuto"] . "'."
				: "Se te escapó en el descuento, en el " . $decisivo["minuto"] . "'.";
		} elseif ($huboRemontada) {
			$detalle = $ganoYo
				? "Ibas por detrás y le diste la vuelta."
				: "La tenías y se te dio la vuelta.";
		} elseif ($ganoYo && $suyos === 0) {
			$detalle = "Sin encajar un gol en todo el partido.";
		} elseif (!$ganoYo && $decisivo && $decisivo["minuto"] >= 70) {
			/* "Aguantaste hasta el X" SOLO si X es tarde. Sin este corte salía
			   "Aguantaste hasta el 15'", que promete épica y la desmiente con el
			   número: aguantar quince minutos no es resistir, es encajar pronto.
			   Se leía como una burla involuntaria. */
			$detalle = "Aguantaste hasta el " . $decisivo["minuto"] . "'.";
		} elseif (!$ganoYo && ($n["stats"]["mias"]["posesion"] ?? 0) >= 58) {
			$detalle = "Mandaste el partido (" . $n["stats"]["mias"]["posesion"] . "% de posesión) y aun así se perdió.";
		} elseif (!$ganoYo && $decisivo && $decisivo["minuto"] <= 20) {
			$detalle = "Te lo pusieron cuesta arriba en el " . $decisivo["minuto"] . "' y ya no hubo vuelta.";
		} elseif (abs($mios - $suyos) >= 3) {
			$detalle = $ganoYo ? "Sin discusión en ningún momento." : "No hubo partido.";
		} elseif (($n["stats"]["mias"]["paradas"] ?? 0) >= 3) {
			$detalle = "Tu portero firmó " . $n["stats"]["mias"]["paradas"] . " paradas.";
		} else {
			$detalle = $ganoYo ? "Ajustada hasta el final." : "Se decidió por poco.";
		}

		// La actuación va aparte: no compite con el hecho, lo acompaña.
		$actuacion = $act["jugados"]
			? "Acertaste " . $act["aciertos"] . " de " . $act["jugados"] . " decisiones."
			: null;

		$compartible = sprintf("%s %d-%d%s %s. %s%s",
			$n["nombres"]["mio"], $mios, $suyos, $porTanda ? " (pen.)" : "",
			$rival, $detalle,
			$actuacion ? " " . $actuacion : "");

		return [
			"titular"     => $titular,
			"detalle"     => $detalle,
			"actuacion"   => $actuacion,
			"compartible" => $compartible,
			"stats"       => $n["stats"],
			"por_tanda"   => $porTanda,
		];
	}

	/** Aciertos del jugador en los minijuegos de este duelo. */
	public function actuacionDuelo($id_duelo, $id_usuario) {
		$stmt = $this->pdo->prepare("
			SELECT COUNT(*) AS jugados,
			       SUM(resultado = 'acierto') AS aciertos
			FROM duelo_minijuegos WHERE id_duelo = :d AND id_usuario = :u
		");
		$stmt->execute([":d" => $id_duelo, ":u" => $id_usuario]);
		$f = $stmt->fetch(PDO::FETCH_ASSOC);
		return ["jugados" => (int) $f["jugados"], "aciertos" => (int) $f["aciertos"]];
	}

	/**
	 * Resuelve un minijuego y lo deja escrito. Es la única vía: el resultado
	 * tiene que ser el mismo para los dos jugadores y sobrevivir al sondeo, así
	 * que no puede vivir en la sesión de nadie.
	 */
	public function resolverMinijuegoDuelo($id_duelo, $id_usuario, $id_evento, $opcion) {
		$narracion = $this->narracionDuelo($id_duelo, $id_usuario);
		if (empty($narracion["ok"])) return $narracion;

		$evento = null;
		foreach ($narracion["eventos"] as $e) {
			if ((int) $e["id"] === (int) $id_evento) { $evento = $e; break; }
		}
		if (!$evento || empty($evento["interactivo"]) || empty($evento["minijuego"])) {
			return ["ok" => false, "error" => "Esa jugada no admite decisión."];
		}

		$catalogo  = self::catalogoMinijuegos();
		$minijuego = $catalogo[$evento["minijuego"]["clave"]] ?? null;
		if (!$minijuego) return ["ok" => false, "error" => "Minijuego desconocido."];

		if ($opcion === "" || $opcion === null) $opcion = self::opcionSegura($minijuego);

		$duelo = $this->obtenerDuelo($id_duelo, $id_usuario);

		/* De qué alineación sale el dato oculto depende de qué se adivina: el
		   REMATE lo elige quien ataca; el ESTILO DEL PORTERO y la COLOCACIÓN DE
		   LA DEFENSA, quien defiende. */
		$idAtacante = $evento["lado"] === "local" ? (int) $duelo["id_creador"] : (int) $duelo["id_rival"];
		$idDefensor = $evento["lado"] === "local" ? (int) $duelo["id_rival"]   : (int) $duelo["id_creador"];
		$cartas = $this->listarAlineacionDuelo(
			$id_duelo,
			self::datoOcultoLoPoneElDefensor($minijuego) ? $idDefensor : $idAtacante
		);

		$remate    = self::ocultoDeJugada($minijuego, $evento, $cartas, (float) $duelo["valor_sorteo"]);
		$resultado = self::resolverMinijuego($minijuego, $opcion, $remate);

		/* INSERT IGNORE es la defensa contra resolver dos veces la misma jugada:
		   la clave primaria (duelo, evento, usuario) lo impide en la propia base
		   de datos. Antes esto se guardaba en $_SESSION, que ni servía para dos
		   jugadores ni sobrevivía a un cambio de pestaña. */
		$stmt = $this->pdo->prepare("
			INSERT IGNORE INTO duelo_minijuegos
				(id_duelo, id_evento, id_usuario, minijuego, opcion, resultado)
			VALUES (:d, :e, :u, :m, :o, :r)
		");
		$stmt->execute([
			":d" => $id_duelo, ":e" => $id_evento, ":u" => $id_usuario,
			":m" => $evento["minijuego"]["clave"], ":o" => $opcion, ":r" => $resultado,
		]);
		if ($stmt->rowCount() === 0) {
			return ["ok" => false, "error" => "Esa jugada ya estaba resuelta."];
		}

		$parado = false;
		/* El marcador solo se mueve si la ENTRADA lo declara (impacto "jugada").
		   Una decisión disciplinaria o una defensa sobre una jugada que no era
		   gol no tiene ningún gol que quitar ni que sumar: cuenta para la
		   puntuación de actuación (§4.6) y ahí se queda. Hasta ahora esta clave
		   no la leía nadie y el marcador se movía por `lado` a secas. */
		/* ¿Podía esta jugada mover el marcador? Viaja al cliente porque sin ese
		   dato no puede explicar un acierto que no acabó en gol: distinguir "lo
		   hiciste bien y no entró" de "esto solo contaba para la actuación". */
		$podiaMover = ($minijuego["impacto"] ?? "jugada") === "jugada"
			&& (($minijuego["lado"] ?? "defiendo") === "defiendo"
				? $evento["tipo"] === "gol"
				: $evento["tipo"] !== "gol");

		if ($resultado === "acierto" && ($minijuego["impacto"] ?? "jugada") === "jugada") {
			/* EL ACIERTO SUBE LA PROBABILIDAD, NO REGALA EL GOL. Decisión de
			   Alejandro: "si ganas un minijuego en un punto decisivo que sea
			   ocasión de gol". Así puedes leerle la intención y que se te vaya al
			   palo, como en el fútbol — y el minijuego sigue sin poder empeorarte
			   la jugada, porque fallar deja las cosas como estaban.

			   El sorteo es DETERMINISTA por (duelo, evento): resolverMinijuegoDuelo
			   se puede reintentar y el sondeo repite, así que con azar real el
			   mismo acierto podría entrar una vez y no la siguiente. Sal propia
			   (8663) para no correlacionar con qué minijuego salió ni con el dato
			   oculto. */
			$probGol = (float) $this->config("partido_minijuego_prob_gol", 0.70);
			$entra = self::azarDeJugada((float) $duelo["valor_sorteo"], (int) $id_evento, 8663) < $probGol;

			/* Defendiendo se le quita un gol al rival; atacando me sumo uno. El
			   TOPE va como parámetro y lo aplica el SQL: sin él, el presupuesto no
			   lo hacía cumplir nadie desde que se retiró la §1.3. */
			$tope = (int) ($narracion["tope_marcador"] ?? 0);
			$parado = $entra && (($minijuego["lado"] ?? "defiendo") === "defiendo"
				? ($evento["tipo"] === "gol" && $this->descontarGolRival($id_duelo, $id_usuario, $tope))
				: ($evento["tipo"] !== "gol" && $this->sumarGolPropio($id_duelo, $id_usuario, $tope)));
			if ($parado) {
				$this->pdo->prepare("
					UPDATE duelo_minijuegos SET aplicado = 1
					WHERE id_duelo = :d AND id_evento = :e AND id_usuario = :u
				")->execute([":d" => $id_duelo, ":e" => $id_evento, ":u" => $id_usuario]);
			}
		}

		// Resuelta la jugada, el partido sigue para los dos.
		$this->reanudarPartido($id_duelo);

		$refrescado = $this->obtenerDuelo($id_duelo, $id_usuario);
		$soyCreador = (int) $refrescado["id_creador"] === (int) $id_usuario;

		return [
			"ok" => true, "resultado" => $resultado, "remate" => $remate, "parado" => $parado,
			// Para que el cliente pueda contar un acierto que no acabó en gol sin
			// confundirlo con una decisión que solo sumaba a la actuación.
			"podia_mover" => $podiaMover,
			"marcador" => $soyCreador
				? [(int) $refrescado["goles_creador"], (int) $refrescado["goles_rival"]]
				: [(int) $refrescado["goles_rival"], (int) $refrescado["goles_creador"]],
		];
	}

	/**
	 * Le quita un gol al rival de $id_usuario, porque el jugador paró esa
	 * ocasión en un minijuego (impacto "jugada").
	 *
	 * ESTE UPDATE ES DONDE SE DECIDE EL DUELO. Ya no hay un ganador escrito al
	 * que este marcador tenga que dar la razón: el marcador ES el resultado, y
	 * liquidarPartido() lo lee tal cual al final. Por eso desapareció de aquí la
	 * condición de §1.3 —"el ganador sorteado sigue ganando después de restar"—
	 * que antes ocupaba la mitad del WHERE: si siguiera puesta, con id_ganador
	 * en NULL durante `en_juego` daría siempre falso y ninguna parada contaría.
	 *
	 * Lo que sí sigue dentro del WHERE, y no comprobado antes en PHP, es que
	 * nada baje de cero. Comprobar y luego actualizar deja una ventana entre las
	 * dos consultas por la que dos peticiones simultáneas restarían dos goles;
	 * aquí, o la fila cumple la condición en el momento exacto del UPDATE o no
	 * se toca nada.
	 *
	 * Devuelve true solo si de verdad se descontó.
	 */
	public function descontarGolRival($id_duelo, $id_usuario, $tope = null) {
		$stmt = $this->pdo->prepare("
			UPDATE duelos SET
				goles_creador = goles_creador - IF(id_creador = :u1, 0, 1),
				goles_rival   = goles_rival   - IF(id_creador = :u2, 1, 0)
			WHERE id_duelo = :d
			  AND estado = 'en_juego'
			  AND (id_creador = :u3 OR id_rival = :u4)
			  /* nada baja de cero */
			  AND IF(id_creador = :u5, goles_rival, goles_creador) > 0
			  " . self::sqlTopeMarcador($tope) . "
		");
		$parametros = array_fill_keys(
			[":u1", ":u2", ":u3", ":u4", ":u5"],
			(int) $id_usuario
		) + [":d" => (int) $id_duelo];
		if ($tope !== null) {
			$parametros[":tu"] = (int) $id_usuario;
			$parametros[":td"] = (int) $id_duelo;
			$parametros[":tope"] = (int) $tope;
		}
		$stmt->execute($parametros);

		return $stmt->rowCount() > 0;
	}

	/**
	 * El trozo de WHERE que hace cumplir `partido_presupuesto_marcador`.
	 *
	 * ⚠️ ESTE ES EL JUEZ DEL PRESUPUESTO, y no está en PHP a propósito. Ocupa el
	 * sitio que dejó libre la condición de §1.3 al retirarse en el Paso 3, y su
	 * ausencia durante ese rato dejó el presupuesto DECORATIVO: el PHP contaba
	 * pero nadie impedía el gol de más. Lo destapó una prueba a mano de la tanda,
	 * en la que un acierto rompió un 1-1 con el presupuesto puesto a 0.
	 *
	 * Cuenta los minijuegos de ESE jugador en ESE duelo que ya movieron el
	 * marcador (`aplicado = 1`). Va dentro del UPDATE porque contar antes y
	 * actualizar después deja una ventana por la que dos peticiones simultáneas
	 * moverían dos goles con presupuesto para uno.
	 *
	 * Con $tope null no añade nada: hay llamadas legítimas sin tope (pruebas y
	 * herramientas), y quien decide el número es narracionDuelo().
	 */
	private static function sqlTopeMarcador($tope) {
		if ($tope === null) return "";
		return "AND (
			  SELECT COUNT(*) FROM duelo_minijuegos dm
			  WHERE dm.id_duelo = :td AND dm.id_usuario = :tu AND dm.aplicado = 1
			) < :tope";
	}

	/**
	 * Le suma un gol a $id_usuario porque metió una ocasión propia en un
	 * minijuego de ataque. Espejo de descontarGolRival() y, como él, uno de los
	 * dos únicos sitios por los que el marcador de un partido en juego se mueve.
	 *
	 * `estado = 'en_juego'` dentro del WHERE hace además de cierre: en cuanto
	 * liquidarPartido() pasa el duelo a `resuelto`, un minijuego que llegue
	 * tarde ya no puede cambiar un resultado que se acaba de pagar.
	 */
	public function sumarGolPropio($id_duelo, $id_usuario, $tope = null) {
		$stmt = $this->pdo->prepare("
			UPDATE duelos SET
				goles_creador = goles_creador + IF(id_creador = :u1, 1, 0),
				goles_rival   = goles_rival   + IF(id_creador = :u2, 0, 1)
			WHERE id_duelo = :d
			  AND estado = 'en_juego'
			  AND (id_creador = :u3 OR id_rival = :u4)
			  " . self::sqlTopeMarcador($tope) . "
		");
		$parametros = array_fill_keys(
			[":u1", ":u2", ":u3", ":u4"], (int) $id_usuario
		) + [":d" => (int) $id_duelo];
		if ($tope !== null) {
			$parametros[":tu"] = (int) $id_usuario;
			$parametros[":td"] = (int) $id_duelo;
			$parametros[":tope"] = (int) $tope;
		}
		$stmt->execute($parametros);

		return $stmt->rowCount() > 0;
	}

	/**
	 * La narración completa de un duelo resuelto, ya desde el punto de vista de
	 * quien la pide y con los minijuegos que puede jugar.
	 *
	 * Vive aquí y no en el endpoint porque la usan DOS sitios (pintar el
	 * partido y resolver un minijuego) y tienen que ver exactamente lo mismo:
	 * si el endpoint que valida una jugada generase la narración con reglas
	 * distintas a las que la pintaron, aceptaría o rechazaría jugadas que el
	 * jugador nunca vio, o al revés.
	 */
	public function narracionDuelo($id_duelo, $id_usuario) {
		$duelo = $this->obtenerDuelo($id_duelo, $id_usuario);
		if (!$duelo) return ["ok" => false, "error" => "Ese duelo no existe o no es tuyo."];
		if (!in_array($duelo["estado"], self::ESTADOS_CON_PARTIDO, true)) {
			return ["ok" => false, "error" => "Ese partido todavía no se ha jugado."];
		}

		$idCreador = (int) $duelo["id_creador"];
		$idRival   = (int) $duelo["id_rival"];
		$formC = $duelo["formacion_creador"] ?: self::FORMACION_BASE;
		$formR = $duelo["formacion_rival"]   ?: self::FORMACION_BASE;

		$cartasC = $this->listarAlineacionDuelo($id_duelo, $idCreador);
		$cartasR = $this->listarAlineacionDuelo($id_duelo, $idRival);
		if (!$cartasC || !$cartasR) {
			return ["ok" => false, "error" => "Este partido es anterior al motor de narración."];
		}

		/* La narración se genera SIEMPRE desde el marcador ORIGINAL del partido,
		   no desde el que hay guardado ahora. Cada gol parado en un minijuego
		   baja el marcador guardado, y si se regenerase desde ahí, el reparto de
		   goles cambiaría a mitad de encuentro: jugadas ya narradas se moverían
		   de minuto y el relato daría un salto. Se reconstruye el original
		   sumando lo parado, y las paradas se aplican después como una capa. */
		/* El marcador REAL, antes de reconstruir el original. Se guarda aparte
		   porque la reconstrucción de abajo modifica $duelo para poder generar
		   el relato, y a partir de ahí sus goles ya no son los que están en la
		   base de datos: son los que HABÍA antes de las paradas. Confundirlos
		   hacía que esta función informara del marcador antiguo. */
		$golesRealesC = (int) $duelo["goles_creador"];
		$golesRealesR = (int) $duelo["goles_rival"];

		$catalogo = self::catalogoMinijuegos();
		$parados = [];    // goles que el portero acabó sacando
		$marcados = [];   // ocasiones falladas que acabaron dentro
		foreach ($this->minijuegosResueltos($id_duelo) as $r) {
			if (!$r["aplicado"]) continue;
			$idEv = (int) $r["id_evento"];
			$defendia = (($catalogo[$r["minijuego"]]["lado"] ?? "defiendo") === "defiendo");

			if ($defendia) {
				$parados[$idEv] = true;
				// el gol lo encajaba quien NO paró
				if ((int) $r["id_usuario"] === $idCreador) $duelo["goles_rival"]++;
				else                                       $duelo["goles_creador"]++;
			} else {
				$marcados[$idEv] = true;
				// el gol lo metió quien atacaba, así que el original tenía uno menos
				if ((int) $r["id_usuario"] === $idCreador) $duelo["goles_creador"]--;
				else                                       $duelo["goles_rival"]--;
			}
		}

		$sim = self::generarEventosPartido(
			["nombre" => $duelo["creador"], "fuerza" => self::fuerzaAlineacion($cartasC, $formC),
			 "cartas" => $cartasC, "formacion" => $formC, "goles" => (int) $duelo["goles_creador"]],
			["nombre" => $duelo["rival"], "fuerza" => self::fuerzaAlineacion($cartasR, $formR),
			 "cartas" => $cartasR, "formacion" => $formR, "goles" => (int) $duelo["goles_rival"]],
			(float) $duelo["valor_sorteo"],
			["dificultad" => $duelo["dificultad"]] + $this->opcionesSimulacion()
		);

		/* Las jugadas paradas dejan de ser gol y el marcador que arrastra cada
		   evento se recalcula. Sin esto el relato seguiría cantando un gol que
		   el jugador acaba de sacar. */
		if ($parados || $marcados) {
			$acum = ["local" => 0, "visitante" => 0];
			foreach ($sim["eventos"] as &$ev) {
				if ($ev["tipo"] === "gol" && isset($parados[$ev["id"]])) {
					$ev["tipo"]  = "parada";
					$ev["texto"] = "Paradón. El remate se queda fuera.";
					$ev["destacado"] = false;
					$sim["stats"][$ev["lado"] === "local" ? "visitante" : "local"]["paradas"]++;
				} elseif ($ev["tipo"] !== "gol" && isset($marcados[$ev["id"]])) {
					$ev["tipo"]  = "gol";
					$ev["texto"] = "¡Dentro! La coloca donde no llegaba.";
					$ev["destacado"] = true;
					$acum[$ev["lado"]]++;
				} elseif ($ev["tipo"] === "gol") {
					$acum[$ev["lado"]]++;
				}
				$ev["marcador"] = [$acum["local"], $acum["visitante"]];
			}
			unset($ev);
		}

		$soyCreador = $idCreador === (int) $id_usuario;
		$miLado = $soyCreador ? "local" : "visitante";
		// Con los goles REALES, no con los reconstruidos: el presupuesto de lo
		// que todavía cabe mover tiene que salir de lo que hay guardado ahora.
		$mios  = $soyCreador ? $golesRealesC : $golesRealesR;
		$suyos = $soyCreador ? $golesRealesR : $golesRealesC;
		$ganoYo = (int) $duelo["id_ganador"] === (int) $id_usuario;

		/* ---------------------------------------------------------------
		   PRESUPUESTO DE CAMBIOS DE MARCADOR — ya no es la §1.3

		   Antes esto medía el MARGEN que dejaba libre un ganador ya sorteado:
		   con un 2-1 a favor cabía parar el gol del rival (2-0) pero no encajar
		   otro (2-2), porque eso contradecía el resultado que el sorteo había
		   fijado antes del primer minuto. De ahí venía todo:
		   cabeCambioMarcador(), el $ganoYo de este cálculo y la asimetría entre
		   el que iba ganando y el que iba perdiendo.

		   Con el partido decidiendo, ESE LÍMITE DESAPARECE: no hay ningún
		   resultado previo al que no se pueda contradecir, y poder empatar un
		   partido o darle la vuelta es exactamente lo que se ha ido a buscar.

		   Lo que queda por acotar es otra cosa, y es de diseño, no de
		   coherencia: CUÁNTO puede mover el marcador un jugador con sus
		   minijuegos en un partido. Se deja en un gol —el mismo margen que en
		   la práctica autorizaba la §1.3, para no meter en el mismo cambio
		   "los minijuegos deciden" y "los minijuegos deciden el doble"— y va en
		   configuracion (§5.4) porque es puro calibrado.

		   Se consume en orden cronológico: si la primera decisión ya lo agotó,
		   las siguientes se ofrecen igual pero sin poder tocar el resultado.
		   Nunca al revés: prometer un efecto y luego no aplicarlo sería peor
		   que no ofrecerlo. */
		$presupuesto = max(0, (int) $this->config("partido_presupuesto_marcador", 1));

		/* Con el interruptor de pruebas puesto, el presupuesto es 0: si no, un
		   minijuego acertado rompería el 1-1 forzado y el partido no llegaría a la
		   tanda, que es justo lo que se está intentando probar. Las decisiones se
		   siguen ofreciendo y siguen contando para la actuación. */
		if ((int) $this->config("depuracion_forzar_empate", 0) === 1) $presupuesto = 0;

		$paradasLibres = $presupuesto;   // ocasiones del rival que puedo sacar
		$golesLibres   = $presupuesto;   // ocasiones mías falladas que puedo meter

		/* ⚠️ EL TOPE QUE DE VERDAD SE APLICA, y por qué hace falta devolverlo.
		   $paradasLibres y $golesLibres solo deciden cómo se ETIQUETA cada
		   decisión al ofrecerla. Quien tiene que impedir el gol de más es la base
		   de datos, dentro del UPDATE de descontarGolRival() / sumarGolPropio().

		   Hasta el Paso 3 ese papel lo hacía la condición de §1.3, que estaba en
		   el SQL y coincidía con el presupuesto por construcción (los dos salían
		   de cabeCambioMarcador). Al quitar la §1.3 se quedó sin juez, y el
		   presupuesto pasó a ser decorativo: lo destapó una prueba a mano de la
		   tanda, donde un acierto rompió un 1-1 con el presupuesto a 0.

		   Se devuelve el tope INICIAL —con los efectos de `impacto: partido` ya
		   sumados, ver abajo— para que resolverMinijuegoDuelo() se lo pase al SQL
		   y haya UN SOLO sitio que lo calcula. */
		$topeMarcador = 0;   // se fija más abajo, tras los efectos de partido

		/* Techo de decisiones por partido, sumando ataque y defensa. §1.5 regla 3
		   lo pide explícitamente, pero el motivo de fondo es de ritmo: el reloj
		   se detiene para LOS DOS en cada decisión, así que este número no vale
		   por jugador sino por partido — dos jugadores a tres decisiones son
		   seis paradas, y la mitad las pasas mirando cómo decide el otro.
		   Va en configuracion, no como constante, porque es puro calibrado. */
		$ofrecidos = 0;
		$MAX_OFRECIDOS = max(1, (int) $this->config("partido_minijuegos_max", 2));

		/* Cuántas de esas decisiones pueden ser de impacto "ninguno" — las que
		   no pueden mover el marcador (familia árbitro y las defensivas sobre
		   jugadas que ya acabaron sin gol).

		   Hace falta un tope PROPIO porque al abrir los tres huecos defensivos
		   nuevos esas decisiones pasaron a ser mayoría de las candidatas, y
		   quien cobrara las dos se quedaba sin poder tocar el resultado: el
		   mismo problema que §15.5 arregló en su día por el otro lado ("quien
		   perdía por un gol se quedaba con cero decisiones").

		   No sube el número de PAUSAS, que es el coste de ritmo real: el total
		   sigue siendo partido_minijuegos_max. Solo acota cuántas de ellas
		   pueden ser irrelevantes para el marcador. */
		$MAX_SIN_IMPACTO = max(0, (int) $this->config("partido_minijuegos_sin_impacto_max", 1));

		/* ---------------------------------------------------------------
		   IMPACTO "partido": lo que arrastra de las decisiones YA JUGADAS

		   Es la tercera clase de impacto (§15.4b) y la que la Biblia pide para
		   sus entradas de ritmo y moral. El efecto NO toca el desenlace de
		   ninguna jugada: **amplía el presupuesto** con el que las jugadas
		   siguientes pueden mover el marcador, o da una decisión más.

		   Sigue teniendo sentido ahora que el partido decide, y de hecho más:
		   el presupuesto pasó de ser el margen que dejaba el §1.3 a ser un tope
		   de diseño (`partido_presupuesto_marcador`), así que ampliarlo es una
		   recompensa clara y acotada — un gol más de los que puedes mover, no
		   una excepción a ninguna regla.

		   Y por qué solo puede CONCEDER: resolverMinijuego() no castiga elegir
		   mal —"el minijuego solo puede mejorar tu partido, nunca empeorarlo,
		   así que ofrecerlo jamás es una trampa"—. Esa regla deja fuera del
		   motor a la familia de Decisiones Negativas de la Biblia, donde una
		   rama "solo puede salir peor". No es un olvido: es esa regla.

		   Se reconstruye de las filas guardadas en cada sondeo, así que los dos
		   jugadores ven lo mismo y sobrevive a recargar la página. */
		$resueltos = $this->minijuegosResueltos($id_duelo);
		$decisionesExtra = 0;
		foreach ($resueltos as $fila) {
			if ((int) $fila["id_usuario"] !== (int) $id_usuario) continue;
			if ($fila["resultado"] !== "acierto") continue;

			$entrada = self::catalogoMinijuegos()[$fila["minijuego"]] ?? null;
			if (!$entrada || ($entrada["impacto"] ?? "jugada") !== "partido") continue;

			switch ($entrada["efecto"] ?? null) {
				case "presupuesto_gol":    $golesLibres++;    break;
				case "presupuesto_parada": $paradasLibres++;  break;
				case "decision":           $decisionesExtra++; break;
			}
		}
		// El techo de decisiones puede crecer, pero con tope: cada decisión para
		// el reloj de los DOS, y §15.5 midió que seis pausas hacen el partido
		// eterno. Una más como mucho.
		$MAX_OFRECIDOS += min(1, $decisionesExtra);

		/* Ya están sumados los efectos de partido, así que este es el tope real de
		   goles que este jugador puede mover en este encuentro. Se cuenta sobre el
		   MAYOR de los dos presupuestos y no sobre la suma: el parámetro se llama
		   "goles que puede mover cada jugador", en singular, y separar parada de
		   gol dentro del SQL exigiría consultar el catálogo desde la consulta. */
		$topeMarcador = max($paradasLibres, $golesLibres);

		/* ---------------------------------------------------------------
		   QUÉ JUGADAS LLEVAN DECISIÓN — se reparten por el partido

		   Antes se ofrecían las PRIMERAS jugadas que valían. Con los huecos
		   defensivos abiertos (v7.3) eso se volvió un defecto medible: las
		   candidatas tempranas se multiplicaron y el techo se gastaba enseguida
		   —minuto mediano 10', última decisión del partido en el 17', 88 % de
		   ellas antes del 30'—, así que el encuentro se quedaba plano durante
		   más de una hora de juego y encima repetía el mismo tipo de jugada,
		   porque las frecuentes al principio son siempre las mismas. Se notaba
		   como "me sale siempre el mismo minijuego".

		   La lista de eventos ya está completa aquí, así que en vez de ir
		   cogiendo las primeras se ELIGE repartiendo por ventanas iguales del
		   encuentro. Sigue siendo función pura de la lista y del sorteo del
		   duelo, que es obligatorio: resolverMinijuegoDuelo() vuelve a pasar por
		   aquí para recalcular qué minijuego se jugó, y si esto tuviera azar real
		   podría recalcular otro distinto.
		   --------------------------------------------------------------- */
		$candidatas = [];
		foreach ($sim["eventos"] as $i => $ev) {
			if ($ev["lado"] === null) continue;
			$defiende = (($ev["lado"] === $miLado) === false);
			$vale = $defiende ? !empty($ev["familia_def"]) : ($ev["tipo"] !== "gol");
			if (!$vale) continue;

			// Copia local: minijuegoDeEvento() exige el evento ya interactivo.
			$ev["interactivo"] = true;
			$mjCand = self::minijuegoDeEvento($ev, $defiende, (float) $duelo["valor_sorteo"]);
			if (!$mjCand) continue;

			$candidatas[] = [
				"i"      => $i,
				"minuto" => (int) $ev["minuto"],
				"mueve"  => (($mjCand["impacto"] ?? "jugada") === "jugada"),
				"clave"  => $mjCand["clave"],
			];
		}

		$elegidas = self::repartirDecisiones(
			$candidatas, $MAX_OFRECIDOS, $MAX_SIN_IMPACTO,
			(int) ($sim["minutos"] ?? 90), (float) $duelo["valor_sorteo"]
		);

		foreach ($sim["eventos"] as $i => &$e) {
			$e["mio"] = $e["lado"] === null ? null : ($e["lado"] === $miLado);
			$e["marcador"] = $soyCreador ? $e["marcador"] : [$e["marcador"][1], $e["marcador"][0]];
			$e["momentum"] = $soyCreador ? $e["momentum"] : -$e["momentum"];

			if ($e["mio"] === null) { $e["interactivo"] = false; continue; }
			$defiendo = ($e["mio"] === false);

			/* Las dos caras de la misma moneda:
			   · DEFENDIENDO — un gol del rival que todavía cabe parar.
			   · ATACANDO    — una ocasión propia FALLADA que todavía cabe meter.
			   Las dos son el momento más dramático que puede dar el partido y el
			   presupuesto ya las limita a una o dos por encuentro, así que se
			   ofrecen SIEMPRE en vez de dejarlas además al dado del ritmo
			   interactivo: al azar salían en uno de cada tres partidos. */
			/* Se ofrece si la jugada tiene sentido para ese lado, hasta el techo
			   por partido. Puede cambiar el marcador solo si además queda
			   presupuesto — pero eso NO decide si se ofrece.

			   Antes sí lo decidía, y el resultado medido era que quien perdía
			   por un gol se quedaba con CERO decisiones: con un margen mínimo no
			   cabe mover nada sin contradecir el sorteo. Justo el partido en el
			   que más quieres pelear era el que no te dejaba tocar nada.

			   Cuando no cabe cambiar el marcador, la jugada sigue contando para
			   la puntuación de actuación (§4.6 y §6.4: "un puntaje independiente
			   del resultado, algo real que optimizar"), así que la decisión
			   nunca es un "continuar" disfrazado y §1.5 regla 2 se respeta.

			   Quien decide de verdad si el marcador se mueve es la base de datos
			   —la condición de §1.3 va dentro del UPDATE—, no este flag. */
			/* DEFENDER YA NO EXIGE UN GOL. Antes la condición era `tipo === "gol"`,
			   y eso dejaba las familias `defensa` y `balon_parado` defensivas en
			   CERO huecos: un gol siempre es familia_def "porteria", así que no
			   había forma de que llegaran. Se daban por "baratas de empezar" y en
			   realidad eran inalcanzables.

			   Ahora defender tiene sentido en cualquier jugada del rival que
			   traiga `familia_def`, que son exactamente cuatro: el gol (porteria),
			   la parada de tu portero (porteria), el despeje de tu defensa
			   (defensa) y el córner que te han sacado (balon_parado). Las demás se
			   excluyen solas porque minijuegoDeEvento() no encuentra familia:
			   `tiro_fuera` la trae en null y `falta` no la trae.

			   Las tres nuevas NO pueden mover el marcador —no hay gol que quitar
			   en una jugada que ya acabó sin gol—, así que van con impacto
			   "ninguno" y suman solo a la actuación. Para que eso no le robe el
			   sitio a las que sí cuentan, hay un tope aparte más abajo. */
			/* Quién lleva decisión ya lo decidió repartirDecisiones() más arriba,
			   con los dos techos aplicados. Aquí solo se consulta: así el filtro
			   de "qué jugada vale" vive en UN sitio y no en dos que se pueden
			   desincronizar. */
			if (!isset($elegidas[$i])) { $e["interactivo"] = false; continue; }

			// Se marca ANTES de preguntar por el minijuego: minijuegoDeEvento()
			// exige que el evento ya sea interactivo.
			$e["interactivo"] = true;
			$mj = self::minijuegoDeEvento($e, $defiendo, (float) $duelo["valor_sorteo"]);
			if (!$mj) { $e["interactivo"] = false; continue; }

			/* Solo las entradas de impacto "jugada" pueden tocar el marcador, y
			   por tanto solo ellas gastan presupuesto. Antes esta clave estaba
			   declarada en el catálogo pero NO la leía nadie —las tres menciones
			   en este fichero eran comentarios—, así que una entrada disciplinaria
			   o defensiva sin gol que quitar habría movido el marcador igual y
			   habría consumido margen que le hacía falta a la siguiente jugada. */
			$mueveMarcador = (($mj["impacto"] ?? "jugada") === "jugada");

			$cambiaMarcador = $mueveMarcador
				&& ($defiendo ? ($paradasLibres > 0) : ($golesLibres > 0));
			if ($cambiaMarcador) { $defiendo ? $paradasLibres-- : $golesLibres--; }

			$dificultad = $duelo["dificultad"] ?? "pvp";
			$plazo = $mj["plazo"][$dificultad] ?? $mj["plazo"]["pvp"] ?? 9;

			/* Los nombres se sustituyen en el TÍTULO además del enunciado. Antes
			   solo en el enunciado, y una entrada con un nombre en el título
			   habría enseñado el marcador crudo "{defensa}" en pantalla. */
			$nombres = [
				"{jugador}" => $e["protagonistas"]["jugador"] ?? "El rival",
				"{portero}" => $e["protagonistas"]["portero"] ?? "tu portero",
				"{defensa}" => $e["protagonistas"]["defensa"] ?? "la defensa",
				"{asiste}"  => $e["protagonistas"]["asiste"]  ?? "un compañero",
			];

			$e["minijuego"] = [
				"clave"     => $mj["clave"],
				"nombre"    => $mj["nombre"],
				"titulo"    => strtr($mj["titulo"], $nombres),
				"enunciado" => strtr($mj["enunciado"], $nombres),
				/* Cómo se elige, no qué se decide (Biblia §2.1). El cliente pinta
				   botones o el medidor según esto; el servidor resuelve igual en
				   los dos casos, así que una entrada puede cambiar de primitiva
				   sin tocar nada más. `velocidad` son los ms de ida y vuelta de
				   la aguja y es la palanca de dificultad del medidor (§3.2),
				   igual que el plazo lo es de la decisión. */
				"primitiva" => $mj["primitiva"] ?? "eleccion",
				"velocidad" => $mj["velocidad"][$dificultad] ?? $mj["velocidad"]["pvp"] ?? 2200,
				// Qué mapa dibujar en la primitiva "zona". Vacío en las demás.
				"lienzo"    => $mj["lienzo"] ?? null,
				"plazo"     => $plazo,
				/* La pista habla de la TENDENCIA de la carta rival implicada,
				   nunca del dato concreto: ese no viaja al cliente ni aquí ni en
				   ningún sitio, o bastaría con mirar la respuesta de red.

				   De qué alineación se lee lo decide el DATO OCULTO, no el lado
				   desde el que juegas: antes era un ternario sobre $defiendo, y
				   con un tercer dato oculto (la defensa en los balones parados,
				   que se lee del rival aunque estés atacando) ese atajo pasaba a
				   ser falso y la pista habría hablado de la carta equivocada. */
				"pista"     => self::pistaDeJugada($mj, $e,
					self::datoOcultoLoPoneElDefensor($mj)
						? ($e["lado"] === "local" ? $cartasR : $cartasC)
						: ($e["lado"] === "local" ? $cartasC : $cartasR)),
				/* `segura` sí viaja, y no filtra nada: dice cuál es la opción
				   CONSERVADORA, no contra qué valor gana. El cliente la necesita
				   para colocar la aguja del medidor al arrancar, de forma que si
				   el navegador no llega a pintar ni un fotograma (pestaña en
				   segundo plano) pulsar "Parar" caiga en la conservadora y no en
				   la primera de la lista. Además el jugador la acaba conociendo
				   igual, porque es la que el servidor aplica al agotarse el
				   plazo. */
				"opciones"  => array_map(fn($o) => [
					"clave" => $o["clave"], "nombre" => $o["nombre"], "pista" => $o["pista"],
					"segura" => !empty($o["segura"]),
					// Dónde va cada opción en el mapa. Nula fuera de la primitiva
					// "zona"; el cliente la usa como `grid-area` tal cual.
					"zona"   => $o["zona"] ?? null,
					// Hacia qué lado hay que arrastrar. Nulo fuera de "arrastre".
					"sector" => $o["sector"] ?? null,
				], $mj["opciones"]),
			];
		}
		unset($e);

		return [
			"ok"        => true,
			"eventos"   => $sim["eventos"],
			"stats"     => [
				"mias"  => $soyCreador ? $sim["stats"]["local"] : $sim["stats"]["visitante"],
				"suyas" => $soyCreador ? $sim["stats"]["visitante"] : $sim["stats"]["local"],
			],
			"minutos"   => $sim["minutos"],
			"descuento" => $sim["descuento"],
			"marcador"  => [$mios, $suyos],
			"gano_yo"   => $ganoYo,
			// Cuántos goles puede mover ESTE jugador en este partido. Lo aplica el
			// SQL, no el PHP: ver el aviso donde se calcula.
			"tope_marcador" => $topeMarcador,
			"nombres"   => [
				"mio"  => $soyCreador ? $duelo["creador"] : $duelo["rival"],
				"suyo" => $soyCreador ? $duelo["rival"]   : $duelo["creador"],
			],
		];
	}

	/* marcadorDuelo() se retiró aquí: era la fórmula PROVISIONAL que inventaba
	   cuántos goles caían en un PvP a partir del valor de sorteo. La sustituye
	   el modo natural de generarEventosPartido() (§1.3): el marcador ya no se
	   calcula aparte, es el número de ocasiones de la simulación que acabaron
	   dentro. Y desde el §15.12 tampoco queda la de las CADENAS: marcadorCadena()
	   se retiró por el mismo motivo y con el mismo sustituto. */

	/**
	 * MONTA el partido de un duelo aceptado: congela fuerzas, compos y aumentos,
	 * aplica la curva Elo, siembra el sorteo, simula el encuentro para saber
	 * cuántos goles caen y guarda toda la trazabilidad.
	 *
	 * OJO CON EL NOMBRE: esto ya NO resuelve nada, ni en PvP ni en PvE. Deja el
	 * duelo en `en_juego`, sin ganador, sin bote entregado y —en cadenas— sin
	 * rango ni botín. Lo que decide es el partido, y quien lo cierra es
	 * liquidarPartido() al llegar al minuto final.
	 *
	 * Se conserva el nombre a propósito: es el paso del ciclo de vida que los
	 * demás sitios conocen (duelo.php y duelo_estado.php lo llaman al cerrarse
	 * la fase de aumento) y renombrarlo solo movería la confusión de sitio.
	 */
	public function resolverDuelo($id_duelo) {
		try {
			$this->pdo->beginTransaction();

			$stmt = $this->pdo->prepare("SELECT * FROM duelos WHERE id_duelo = :id FOR UPDATE");
			$stmt->execute([":id" => $id_duelo]);
			$duelo = $stmt->fetch(PDO::FETCH_ASSOC);

			// 'aceptado' sigue aceptándose por compatibilidad con duelos creados
			// antes de que existiera la fase de aumento; el camino normal ahora
			// es aumento_pendiente -> listo_para_resolver -> aquí.
			if (!$duelo || !in_array($duelo["estado"], ["aceptado", "listo_para_resolver"], true)) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "Este duelo no se puede resolver ahora."];
			}

			$idCreador = (int) $duelo["id_creador"];
			$idRival   = (int) $duelo["id_rival"];

			// Las formaciones también están congeladas: el mismo hueco puntúa con
			// otra estadística según la formación, así que releerla del mazo
			// dejaría que editarlo después cambiase un duelo ya comprometido.
			$formCreador = $duelo["formacion_creador"] ?: self::FORMACION_BASE;
			$formRival   = $duelo["formacion_rival"]   ?: self::FORMACION_BASE;

			$fuerzaCreador = self::fuerzaAlineacion($this->listarAlineacionDuelo($id_duelo, $idCreador), $formCreador);
			$fuerzaRival   = self::fuerzaAlineacion($this->listarAlineacionDuelo($id_duelo, $idRival), $formRival);

			// --- PvE: la dificultad escala al rival ---
			// El multiplicador se aplica LÍNEA A LÍNEA y no al total, para que la
			// dificultad se note igual en quién gana y en el marcador. Aplicado
			// solo al total, un Extremo daría muchas derrotas pero con resultados
			// de partido igualado, que es justo la sensación contraria.
			$esPve = $duelo["dificultad"] !== null;
			if ($esPve) {
				$mult = (float) $this->config("pve_mult_" . $duelo["dificultad"], 1.0);
				foreach (["POR", "DF", "MC", "DC"] as $linea) {
					$fuerzaRival[$linea] = $fuerzaRival[$linea] * $mult;
				}
				$fuerzaRival["total"] = $fuerzaRival["POR"] + $fuerzaRival["DF"]
					+ $fuerzaRival["MC"] + $fuerzaRival["DC"];
			}

			$totalBrutoCreador = (float) $fuerzaCreador["total"];
			$totalBrutoRival   = (float) $fuerzaRival["total"];

			// --- Capa 2: se recalcula desde la alineación CONGELADA ---
			// No se leen los rasgos actuales de las cartas: si alguien reasignó
			// un rasgo desde el panel mientras el duelo estaba en curso, este
			// duelo tiene que resolverse con lo que había al comprometerse.
			$composCreador = $this->calcularCompos($this->listarAlineacionDuelo($id_duelo, $idCreador));
			$composRival   = $this->calcularCompos($this->listarAlineacionDuelo($id_duelo, $idRival));

			// Fórmula maestra (§8). Los bonos de CATEGORÍA son la suma de las
			// compos (ya con rendimientos decrecientes y tope de línea) más el
			// Aumento. El tope de línea acota solo las compos: el Aumento se
			// suma por encima, porque es un recurso de un solo uso y no forma
			// parte del apilamiento que el tope viene a contener.
			$bonosCreador = $this->bonosAumento($id_duelo, $idCreador);
			$bonosRival   = $this->bonosAumento($id_duelo, $idRival);

			// En dificultad alta el rival exprime más sus compos (§3.2 del
			// briefing: "puede usar niveles de Compo más altos"). Se multiplica
			// el bonus ya calculado en vez de inventarle rasgos que no tiene, así
			// que el desglose que ve el jugador sigue cuadrando con su alineación.
			$multCompos = $esPve
				? (float) $this->config("pve_compos_mult_" . $duelo["dificultad"], 1.0)
				: 1.0;

			foreach (["POR", "DF", "MC", "DC"] as $linea) {
				$bonosCreador[$linea] = ($bonosCreador[$linea] ?? 0) + ($composCreador["bonos_linea"][$linea] ?? 0);
				$bonosRival[$linea]   = ($bonosRival[$linea] ?? 0)
					+ ($composRival["bonos_linea"][$linea] ?? 0) * $multCompos;
			}

			// Bonos de TOTAL: ciclo de contra-afinidad (suma) y malus de
			// coherencia de rareza (resta). Se aplican sobre la suma de las
			// líneas ya ajustadas, nunca encadenados sobre un valor ya
			// multiplicado.
			$cicloCreador = $this->bonoCicloAfinidad($composCreador["afinidad_dom"], $composRival["afinidad_dom"]);
			$cicloRival   = $this->bonoCicloAfinidad($composRival["afinidad_dom"], $composCreador["afinidad_dom"]);

			$totalPctCreador = $cicloCreador - $composCreador["malus"];
			$totalPctRival   = $cicloRival   - $composRival["malus"];

			$calcCreador = self::calcularTotalFinal($fuerzaCreador, $bonosCreador, $totalPctCreador);
			$calcRival   = self::calcularTotalFinal($fuerzaRival, $bonosRival, $totalPctRival);

			$totalFinalCreador = $calcCreador["final"];
			$totalFinalRival   = $calcRival["final"];

			$k = (float) $this->config("duelo_k", 400);
			$pMin = (float) $this->config("duelo_p_min", 0.05);
			$pMax = (float) $this->config("duelo_p_max", 0.95);

			$p = 1 / (1 + pow(10, ($totalFinalRival - $totalFinalCreador) / $k));
			// Acotado obligatorio: ninguna diferencia, por grande que sea, deja
			// al mazo débil sin ninguna opción.
			$p = max($pMin, min($pMax, $p));

			$sorteo = mt_rand() / (mt_getrandmax() + 1);   // [0,1)

			/* EL SORTEO YA NO DECIDE QUIÉN GANA. NI EN PvP NI EN PvE.
			   Decide el partido: el marcador se acumula durante el encuentro y el
			   ganador se escribe al final, en liquidarPartido(). $sorteo conserva
			   sus otros dos trabajos —sembrar toda la narración (valor_sorteo,
			   §15.1) y dejar constancia del "partías con un X %"—, así que sigue
			   guardándose igual.

			   Las CADENAS entraron aquí en el §15.12, y por el mismo motivo que el
			   PvP: su rango y su botín cuelgan del marcador, así que con el ganador
			   sorteado antes del minuto 1 la recompensa venía dada y los minijuegos
			   no podían tocarla. Ahora el partido de cadena también se juega. */
			$idGanador  = null;
			$idPerdedor = null;

			/* El marcador NACE de la simulación, y manda. Se juega el partido
			   minuto a minuto y el marcador es, literalmente, el número de
			   ocasiones que acabaron dentro.

			   MODO NATURAL: no se pasa `gana`. Esa clave existía para que la
			   simulación no pudiera contradecir al ganador pre-sorteado (§1.3), y
			   hacía dos cosas de las que hay que desprenderse: forzaba un margen a
			   favor de ese ganador e IMPEDÍA EL EMPATE. Sin ella el marcador sale
			   como salga — empates incluidos, que es justo lo que hace falta para
			   que la tanda de penaltis tenga sentido.

			   En PvE la fuerza del rival YA viene multiplicada por la dificultad
			   (arriba), así que el escalado de las cadenas entra en la simulación
			   solo: un Extremo no marca menos goles, marca contra un muro mejor. */
			$sim = self::generarEventosPartido(
				[
					"nombre" => "local", "fuerza" => $fuerzaCreador, "goles" => null,
					"cartas" => $this->listarAlineacionDuelo($id_duelo, $idCreador),
					"formacion" => $formCreador,
				],
				[
					"nombre" => "visitante", "fuerza" => $fuerzaRival, "goles" => null,
					"cartas" => $this->listarAlineacionDuelo($id_duelo, $idRival),
					"formacion" => $formRival,
				],
				$sorteo,
				$this->opcionesSimulacion()
			);
			[$golesCreador, $golesRival] = $sim["goles"];

			/* ⚠️ INTERRUPTOR DE PRUEBAS — `depuracion_forzar_empate`
			   Con esto a 1, TODO partido acaba 1-1 y por tanto se va a la tanda.
			   Existe porque probar los penaltis a mano es imposible de otro modo:
			   solo empata el 27,7 % de los duelos, así que habría que jugar cuatro
			   partidos enteros para ver una tanda.

			   Vale 0 por defecto y por defecto la fila ni existe. Si te encuentras
			   todos los duelos empatados, esto es lo primero que hay que mirar:
			     UPDATE configuracion SET valor='0'
			      WHERE clave='depuracion_forzar_empate';

			   Va en configuracion y no en el código a propósito: un interruptor de
			   pruebas escondido en un `if` es un interruptor que se queda puesto.
			   Así se ve en el panel y se apaga sin desplegar nada. */
			if ((int) $this->config("depuracion_forzar_empate", 0) === 1) {
				$golesCreador = 1;
				$golesRival   = 1;
			}

			/* --- las apuestas ya no se mueven aquí, ni en PvP ni en PvE ---
			   El bote se entrega al terminar el partido (liquidarPartido), porque
			   hasta entonces no hay ganador a quien entregárselo. Aplazarlo no deja
			   nada a medias: lo apostado está RETENIDO de los dos desde que
			   entraron —las monedas se descuentan en crearDuelo() y aceptarDuelo()—
			   así que el modelo de pago no cambia. Lo único que se mueve es el
			   momento de la entrega. En cadenas la apuesta es de 0 monedas, así que
			   ahí no hay bote que entregar: lo que se aplaza es el BOTÍN, y eso se
			   hace en el mismo sitio.

			   OJO CON LA CARTA, que no funciona como parece: NO se marca
			   `bloqueada` (esa columna es el candado manual del jugador contra la
			   venta, y nadie la toca aquí). Lo que la retiene es que las consultas
			   de "¿puedo apostar/vender esta copia?" excluyen las que tienen fila
			   en `duelo_apuestas` con el duelo en `estado NOT IN
			   ('resuelto','cancelado')`. Que sea un NOT IN es lo que hace que
			   `en_juego` quede retenido sin tocar nada — si fuera una lista
			   positiva, la carta se habría liberado a mitad de partido. */

			$estadoNuevo = "en_juego";

			/* `resuelto = NOW()` se sigue escribiendo aquí aunque el duelo no quede
			   resuelto todavía: es la hora de referencia con la que
			   arrancarPartidoSiToca() cuenta partido_espera_seg. Lo que marca es
			   "el partido ya está montado", que es exactamente cuando toca.

			   `rango` NO se escribe aquí: sale del marcador final, así que lo pone
			   liquidarPartido() en el mismo UPDATE que el ganador. Hasta entonces
			   se queda en NULL, que es lo que las pantallas ya leen como "todavía
			   no hay rango". */
			$this->pdo->prepare("
				UPDATE duelos SET
					estado = :estado,
					id_ganador = :id_ganador,
					goles_creador = :goles_creador,
					goles_rival = :goles_rival,
					total_bruto_creador = :tbc, total_bruto_rival = :tbr,
					total_final_creador = :tfc, total_final_rival = :tfr,
					probabilidad_victoria_creador = :p,
					valor_sorteo = :sorteo,
					k_utilizado = :k,
					afinidad_dom_creador = :afc, afinidad_dom_rival = :afr,
					ciclo_bonus_creador = :cbc, ciclo_bonus_rival = :cbr,
					malus_coh_creador = :mcc, malus_coh_rival = :mcr,
					tension_creador = :tc, tension_rival = :tr,
					resuelto = NOW()
				WHERE id_duelo = :id_duelo
			")->execute([
				":afc" => $composCreador["afinidad_dom"], ":afr" => $composRival["afinidad_dom"],
				":cbc" => $cicloCreador,                  ":cbr" => $cicloRival,
				":mcc" => $composCreador["malus"],        ":mcr" => $composRival["malus"],
				":tc"  => $composCreador["tension_nivel"], ":tr" => $composRival["tension_nivel"],
				":estado"        => $estadoNuevo,
				":id_ganador"    => $idGanador,
				":goles_creador" => $golesCreador,
				":goles_rival"   => $golesRival,
				":tbc" => $totalBrutoCreador, ":tbr" => $totalBrutoRival,
				":tfc" => $totalFinalCreador, ":tfr" => $totalFinalRival,
				":p"      => $p,
				":sorteo" => $sorteo,
				":k"      => $k,
				":id_duelo" => $id_duelo,
			]);

			/* ⚠️ COMPROBAR QUE EL ESTADO SE GUARDÓ DE VERDAD. No es paranoia:
			   esta base NO corre con STRICT_TRANS_TABLES, así que si a la columna
			   `estado` le falta el valor 'en_juego' —la migración `019` sin
			   aplicar— MariaDB **no da error**: TRUNCA a cadena vacía y sigue con
			   un simple warning, que PDO no convierte en excepción. Medido:
			   guarda '' y avisa "Data truncated for column 'estado'".

			   Lo que dejaría es lo peor que puede pasar aquí: un duelo con lo
			   apostado ya retenido a los DOS, en un estado que no existe, que
			   liquidarPartido() nunca podrá cerrar porque exige `en_juego`. El
			   bote no volvería a nadie y nada avisaría.

			   Así que se relee y, si no cuadra, se deshace todo. Un duelo que no
			   arranca y lo dice es infinitamente mejor que uno que se queda con el
			   dinero. La `021` no necesita esto: una columna que no existe sí da
			   error y la transacción se deshace sola. */
			$comprobar = $this->pdo->prepare("SELECT estado FROM duelos WHERE id_duelo = :d");
			$comprobar->execute([":d" => $id_duelo]);
			if ($comprobar->fetchColumn() !== $estadoNuevo) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" =>
					"La base de datos no admite el estado '$estadoNuevo'. "
					. "Falta aplicar db/migraciones/019_partido_decide.sql."];
			}

			/* El progreso de nodo y el botín se REPARTEN EN liquidarPartido(), no
			   aquí. Aquí todavía no hay marcador final —los minijuegos están por
			   jugarse— así que no hay rango, y sin rango no hay recompensa que
			   calcular. Es justo el cambio que pedía el §15.12: que los minijuegos
			   influyan en las recompensas de ese partido. */

			$this->pdo->commit();
			return ["ok" => true, "error" => null, "id_ganador" => $idGanador];

		} catch (Exception $e) {
			$this->pdo->rollBack();
			return ["ok" => false, "error" => "No se pudo resolver el duelo."];
		}
	}

	// Retira una sala propia que nadie ha aceptado y devuelve lo apostado.
	public function cancelarDuelo($id_duelo, $id_usuario) {
		try {
			$this->pdo->beginTransaction();

			$stmt = $this->pdo->prepare("SELECT * FROM duelos WHERE id_duelo = :id FOR UPDATE");
			$stmt->execute([":id" => $id_duelo]);
			$duelo = $stmt->fetch(PDO::FETCH_ASSOC);

			if (!$duelo || (int) $duelo["id_creador"] !== (int) $id_usuario || $duelo["estado"] !== "creado") {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "Esta sala ya no se puede cancelar."];
			}

			// Solo hay monedas que devolver; la carta apostada nunca cambió de
			// dueño, se limita a dejar de estar retenida al morir la sala.
			if ($duelo["tipo_apuesta"] === "monedas") {
				$this->pdo->prepare("UPDATE usuarios SET monedas = monedas + :m WHERE id_usuario = :id")
					->execute([":m" => (int) $duelo["monedas"], ":id" => $id_usuario]);
			}

			$this->pdo->prepare("UPDATE duelos SET estado = 'cancelado' WHERE id_duelo = :id")
				->execute([":id" => $id_duelo]);

			$this->pdo->commit();
			return ["ok" => true, "error" => null];

		} catch (Exception $e) {
			$this->pdo->rollBack();
			return ["ok" => false, "error" => "No se pudo cancelar el duelo."];
		}
	}

	/**
	 * Latido del creador mientras espera en su sala. Su pantalla lo manda cada
	 * pocos segundos; es lo que demuestra que sigue dentro.
	 * Devuelve false si la sala ya no está esperando (la aceptaron o murió).
	 */
	public function latirDuelo($id_duelo, $id_usuario) {
		$parametros = [":id_duelo" => $id_duelo, ":id_usuario" => $id_usuario];

		$this->pdo->prepare("
			UPDATE duelos SET ultimo_latido = NOW()
			WHERE id_duelo = :id_duelo AND id_creador = :id_usuario AND estado = 'creado'
		")->execute($parametros);

		// No se puede usar rowCount() aquí: MySQL cuenta filas MODIFICADAS, no
		// coincidentes, y dos latidos dentro del mismo segundo escriben el mismo
		// NOW(), así que daría 0 con la sala perfectamente viva. Se confirma
		// leyendo el estado, que además es lo que de verdad quiere saber quien
		// llama: si la sala sigue esperando.
		$stmt = $this->pdo->prepare("
			SELECT 1 FROM duelos
			WHERE id_duelo = :id_duelo AND id_creador = :id_usuario AND estado = 'creado'
		");
		$stmt->execute($parametros);
		return (bool) $stmt->fetchColumn();
	}

	/**
	 * Cancela las salas cuyo creador dejó de latir y devuelve lo apostado.
	 *
	 * No hay cron en este proyecto, así que la limpieza es perezosa: se ejecuta
	 * cuando alguien mira el listado o intenta entrar en una sala. Es suficiente
	 * porque una sala fantasma solo molesta si alguien la ve.
	 */
	public function cancelarSalasAbandonadas() {
		$margen = (int) $this->config("duelo_latido_max", 15);

		$stmt = $this->pdo->prepare("
			SELECT id_duelo, id_creador, tipo_apuesta, monedas FROM duelos
			WHERE estado = 'creado'
				AND ultimo_latido IS NOT NULL
				AND ultimo_latido < (NOW() - INTERVAL :margen SECOND)
		");
		$stmt->bindValue(":margen", $margen, PDO::PARAM_INT);
		$stmt->execute();
		$abandonadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach ($abandonadas as $sala) {
			try {
				$this->pdo->beginTransaction();
				if ($sala["tipo_apuesta"] === "monedas") {
					$this->pdo->prepare("UPDATE usuarios SET monedas = monedas + :m WHERE id_usuario = :id")
						->execute([":m" => (int) $sala["monedas"], ":id" => $sala["id_creador"]]);
				}
				$this->pdo->prepare("UPDATE duelos SET estado = 'cancelado' WHERE id_duelo = :id AND estado = 'creado'")
					->execute([":id" => $sala["id_duelo"]]);
				$this->pdo->commit();
			} catch (Exception $e) {
				$this->pdo->rollBack();
			}
		}

		return count($abandonadas);
	}

	// Salas abiertas de OTROS. La propia no se lista: no puedes entrar en ella.
	// Solo se listan las que siguen latiendo: si el creador se fue, la sala no
	// existe a efectos prácticos.
	public function listarDuelosAbiertos($id_usuario) {
		$this->cancelarSalasAbandonadas();

		$stmt = $this->pdo->prepare("
			SELECT d.*, u.nombre AS creador, r.nombre AS rareza_apuesta
			FROM duelos d
			INNER JOIN usuarios u ON u.id_usuario = d.id_creador
			LEFT JOIN rarezas r ON r.id_rareza = d.id_rareza_apuesta
			WHERE d.estado = 'creado' AND d.id_creador <> :id_usuario
			ORDER BY d.creado DESC
		");
		$stmt->execute([":id_usuario" => $id_usuario]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	// Duelos en los que participa el usuario, para su propia lista.
	public function listarMisDuelos($id_usuario, $limite = 20) {
		$stmt = $this->pdo->prepare("
			SELECT d.*,
				uc.nombre AS creador, ur.nombre AS rival,
				r.nombre AS rareza_apuesta
			FROM duelos d
			INNER JOIN usuarios uc ON uc.id_usuario = d.id_creador
			LEFT JOIN usuarios ur ON ur.id_usuario = d.id_rival
			LEFT JOIN rarezas r ON r.id_rareza = d.id_rareza_apuesta
			WHERE (d.id_creador = :id_usuario OR d.id_rival = :id_usuario)
				-- los partidos de cadena tienen su propio historial: mezclarlos
				-- aquí llenaría la lista de duelos con partidas contra el sistema
				-- en las que no se apostó nada
				AND d.dificultad IS NULL
			ORDER BY d.creado DESC
			LIMIT " . (int) $limite . "
		");
		$stmt->execute([":id_usuario" => $id_usuario]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	// Un duelo concreto, solo si el usuario participa en él.
	public function obtenerDuelo($id_duelo, $id_usuario) {
		// En un duelo de cadena el rival es la cuenta de sistema 'CPU', que no se
		// enseña nunca: el nombre que ve el jugador es el del equipo rival. Se
		// resuelve con COALESCE aquí y no en la pantalla para que cualquier sitio
		// que lea un duelo obtenga ya el nombre correcto.
		$stmt = $this->pdo->prepare("
			SELECT d.*,
				uc.nombre AS creador,
				COALESCE(cr.nombre, ur.nombre) AS rival,
				cr.nombre AS equipo_rival, cr.escudo AS escudo_rival,
				r.nombre AS rareza_apuesta
			FROM duelos d
			INNER JOIN usuarios uc ON uc.id_usuario = d.id_creador
			LEFT JOIN usuarios ur ON ur.id_usuario = d.id_rival
			LEFT JOIN cadena_rival_estilos ce ON ce.id_estilo = d.id_estilo_rival
			LEFT JOIN cadena_rivales cr ON cr.id_rival = ce.id_rival
			LEFT JOIN rarezas r ON r.id_rareza = d.id_rareza_apuesta
			WHERE d.id_duelo = :id_duelo
				AND (d.id_creador = :id_usuario OR d.id_rival = :id_usuario)
		");
		$stmt->execute([":id_duelo" => $id_duelo, ":id_usuario" => $id_usuario]);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
	}

	// ==========================================================
	// CADENAS DE PARTIDO (PvE) — bloque B
	//
	// No hay un motor de combate paralelo. El rival es un "jugador virtual":
	// se congela en duelo_alineaciones igual que una persona, así que
	// fuerzaAlineacion(), las compos, el aumento y la curva Elo son
	// literalmente los mismos que en PvP. Lo único propio del PvE es CÓMO se
	// construye ese rival (dificultad) y CÓMO se cuenta el marcador.
	// ==========================================================

	const DIFICULTADES = ["facil", "medio", "dificil", "muy_dificil", "extremo"];

	/**
	 * Orden real de los rangos, de mejor a peor. Existe porque comparar las
	 * letras directamente ENGAÑA: alfabéticamente es 'A' < 'B' < 'S', así que
	 * un min() sobre las letras daría la A como mejor que la S.
	 */
	const ORDEN_RANGO = ["S" => 1, "A" => 2, "B" => 3];

	/** Cuenta de sistema que hace de rival. No puede iniciar sesión (007). */
	const USUARIO_BOT = "CPU";

	private $idBotCache = null;

	public function idBot() {
		if ($this->idBotCache === null) {
			$stmt = $this->pdo->prepare("SELECT id_usuario FROM usuarios WHERE nombre = :n");
			$stmt->execute([":n" => self::USUARIO_BOT]);
			$this->idBotCache = (int) $stmt->fetchColumn();
		}
		return $this->idBotCache;
	}

	public function listarRivales() {
		return $this->pdo->query("
			SELECT * FROM cadena_rivales WHERE activo = 1 ORDER BY id_rival
		")->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Un estilo al azar del rival. Es el corazón de que el PvE no se memorice:
	 * el mismo equipo sale con alineaciones distintas y al jugador no se le
	 * dice cuál le ha tocado hasta que termina el partido.
	 */
	public function estiloAleatorioDeRival($id_rival) {
		$stmt = $this->pdo->prepare("
			SELECT * FROM cadena_rival_estilos WHERE id_rival = :r ORDER BY RAND() LIMIT 1
		");
		$stmt->execute([":r" => $id_rival]);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
	}

	/**
	 * Las 11 cartas de un estilo, en el mismo formato que listarCartasMazo():
	 * así valen tal cual para fuerzaAlineacion(), calcularCompos() y el
	 * componente de tarjeta, sin adaptadores por el medio.
	 */
	public function listarCartasEstilo($id_estilo) {
		$stmt = $this->pdo->prepare("
			SELECT
				rc.hueco,
				c.id_cromo, c.nombre, c.posicion, c.imagen,
				c.ataque, c.defensa, c.tecnica,
				eq.nombre AS equipo,
				r.id_rareza, r.nombre AS rareza,
				af.nombre AS afinidad, af.imagen AS afinidad_imagen,
				(SELECT rg.nombre FROM cromo_rasgos cr INNER JOIN rasgos rg ON rg.id_rasgo = cr.id_rasgo
				 WHERE cr.id_cromo = c.id_cromo AND rg.tipo = 'configuracion' LIMIT 1) AS rasgo
			FROM cadena_rival_cartas rc
			INNER JOIN cromos c ON c.id_cromo = rc.id_cromo
			INNER JOIN equipos eq ON eq.id_equipo = c.id_equipo
			INNER JOIN rarezas r ON r.id_rareza = c.id_rareza
			INNER JOIN afinidad af ON af.id = c.id_afinidad
			WHERE rc.id_estilo = :e
			ORDER BY rc.hueco
		");
		$stmt->execute([":e" => $id_estilo]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/** Congela la alineación del rival copiando las cifras, igual que la humana. */
	private function congelarAlineacionRival($id_duelo, $id_estilo) {
		$cartas = $this->listarCartasEstilo($id_estilo);
		if (count($cartas) !== self::MAZO_TAMANO) {
			return false;
		}
		$insertar = $this->pdo->prepare("
			INSERT INTO duelo_alineaciones (id_duelo, id_usuario, hueco, id_cromo, ataque, defensa, tecnica)
			VALUES (:id_duelo, :id_usuario, :hueco, :id_cromo, :ataque, :defensa, :tecnica)
		");
		foreach ($cartas as $c) {
			$insertar->execute([
				":id_duelo"   => $id_duelo,
				":id_usuario" => $this->idBot(),
				":hueco"      => (int) $c["hueco"],
				":id_cromo"   => (int) $c["id_cromo"],
				":ataque"     => (int) $c["ataque"],
				":defensa"    => (int) $c["defensa"],
				":tecnica"    => (int) $c["tecnica"],
			]);
		}
		return true;
	}

	/**
	 * Cartas del mazo titular que superan la rareza admitida en esa dificultad.
	 * Devuelve [] si no hay límite o si el mazo cumple.
	 *
	 * No se corrige la alineación por su cuenta: como se duela SIEMPRE con el
	 * titular, tocarlo aquí cambiaría en silencio el mazo con el que la persona
	 * también juega en PvP. Se bloquea la entrada y se dice por qué.
	 */
	public function cartasQueExcedenRareza(array $cartas, $dificultad) {
		$max = (int) $this->config("pve_rareza_max_" . $dificultad, 0);
		if ($max <= 0) { return []; }

		return array_values(array_filter($cartas, fn($c) => (int) $c["id_rareza"] > $max));
	}

	/**
	 * Arranca un partido de cadena contra un rival.
	 *
	 * A diferencia del PvP no hay sala ni espera —no hay a quién esperar— así
	 * que el duelo nace ya en fase de aumento. Tampoco hay plazo: el briefing
	 * (§5) dice explícitamente que el PvE no tiene límite de tiempo, así que
	 * `aumento_vence` se queda a NULL y el fallback por vencimiento nunca entra.
	 */
	public function crearPartidoCadena($id_usuario, $id_nodo, $dificultad) {
		if (!in_array($dificultad, self::DIFICULTADES, true)) {
			return ["ok" => false, "error" => "Dificultad no válida."];
		}

		// --- validación del nodo, fuera de la transacción porque solo lee ---
		$nodo = $this->obtenerNodo($id_nodo);
		if (!$nodo || $nodo["tipo"] !== "partido" || !$nodo["id_rival"]) {
			return ["ok" => false, "error" => "Ese nodo no es un partido jugable."];
		}
		if ((int) $nodo["activa"] !== 1 || ($nodo["fecha_fin"] !== null && strtotime($nodo["fecha_fin"]) <= time())) {
			return ["ok" => false, "error" => "Esta cadena ya no está disponible."];
		}

		$pendientes = $this->requisitosPendientes((int) $nodo["id_cadena"], $id_usuario);
		if ($pendientes) {
			return ["ok" => false, "error" => "Esta cadena está bloqueada.", "requisitos" => $pendientes];
		}

		// Que el nodo esté abierto se revalida aquí y no solo en el mapa: el
		// mapa es comodidad, esto es la garantía.
		$mapa = $this->mapaCadena((int) $nodo["id_cadena"], $id_usuario);
		if (empty($mapa["nodos"][(int) $id_nodo]["disponible"])) {
			return ["ok" => false, "error" => "Todavía no has llegado a este partido."];
		}

		$id_rival = (int) $nodo["id_rival"];

		try {
			$this->pdo->beginTransaction();

			$mazo = $this->obtenerMazoTitular($id_usuario);
			if (!$mazo) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "Necesitas un mazo titular con los 11 huecos cubiertos."];
			}

			$cartasMazo = $this->listarCartasMazo($mazo["id_mazo"]);
			$excedidas  = $this->cartasQueExcedenRareza($cartasMazo, $dificultad);
			if ($excedidas) {
				$this->pdo->rollBack();
				return [
					"ok" => false,
					"error" => "Tu mazo titular lleva cartas por encima de la rareza permitida en esta dificultad.",
					"cartas_excedidas" => $excedidas,
				];
			}

			$estilo = $this->estiloAleatorioDeRival($id_rival);
			if (!$estilo) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "Ese rival no tiene ninguna alineación preparada."];
			}

			$idBot = $this->idBot();

			// Apuesta a cero: en las cadenas no se juega nada del bolsillo, las
			// recompensas van por la tabla de botín (bloque D). Se reutiliza el
			// tipo 'monedas' con 0 para no meter un tipo de apuesta que solo
			// existiría para decir "ninguna".
			$this->pdo->prepare("
				INSERT INTO duelos (
					id_creador, id_mazo_creador, formacion_creador,
					id_rival, formacion_rival,
					tipo_apuesta, monedas, estado, dificultad, id_estilo_rival, id_nodo
				) VALUES (
					:id_creador, :id_mazo, :form_creador,
					:id_rival, :form_rival,
					'monedas', 0, 'aumento_pendiente', :dificultad, :id_estilo, :id_nodo
				)
			")->execute([
				":id_creador"   => $id_usuario,
				":id_mazo"      => (int) $mazo["id_mazo"],
				":form_creador" => $mazo["formacion"] ?: self::FORMACION_BASE,
				":id_rival"     => $idBot,
				":form_rival"   => $estilo["formacion"],
				":dificultad"   => $dificultad,
				":id_estilo"    => (int) $estilo["id_estilo"],
				":id_nodo"      => (int) $id_nodo,
			]);
			$idDuelo = (int) $this->pdo->lastInsertId();

			if (!$this->congelarAlineacion($idDuelo, $id_usuario, (int) $mazo["id_mazo"])
				|| !$this->congelarAlineacionRival($idDuelo, (int) $estilo["id_estilo"])) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "No se pudo preparar el partido."];
			}

			// Compos congeladas de ambos, igual que en PvP y por el mismo motivo:
			// la Tensión decide con qué probabilidades se sortea el aumento.
			$composJugador = $this->calcularCompos($this->listarAlineacionDuelo($idDuelo, $id_usuario));
			$composRival   = $this->calcularCompos($this->listarAlineacionDuelo($idDuelo, $idBot));
			$this->congelarCompos($idDuelo, $id_usuario, $composJugador);
			$this->congelarCompos($idDuelo, $idBot, $composRival);

			// El aumento del JUGADOR sale de su Tensión, exactamente como en PvP.
			$this->generarAumentos($idDuelo, $id_usuario, (int) $composJugador["tension_nivel"]);

			// El del RIVAL sale de la dificultad. Se elige en el acto porque el
			// bot no tiene pantalla donde decidir; sigue sin poder verlo el
			// jugador hasta la resolución, que es la regla anti-abuso de §6.3.
			$this->generarAumentos($idDuelo, $idBot, 0, $this->tiersDificultad($dificultad));
			$opciones = $this->listarAumentos($idDuelo, $idBot);
			if ($opciones) {
				$this->elegirAumento($idDuelo, $idBot, (int) $opciones[mt_rand(0, count($opciones) - 1)]["opcion"]);
			}

			$this->pdo->commit();
			return ["ok" => true, "error" => null, "id_duelo" => $idDuelo];

		} catch (Exception $e) {
			$this->pdo->rollBack();
			return ["ok" => false, "error" => "No se pudo crear el partido."];
		}
	}

	// ----------------------------------------------------------
	// Cadenas: recompensas (bloque D)
	// ----------------------------------------------------------

	/** Solo estas dos decrecen por repetición (§12 del briefing). */
	const DIFICULTADES_CON_DECRECIMIENTO = ["facil", "medio"];

	/**
	 * Monedas por ganar un partido de cadena.
	 *
	 * `$vecesPrevias` es cuántas veces se había jugado YA ese nodo a esa
	 * dificultad (0 = primera vez, que es la que cobra completo). Solo decrece
	 * en Fácil/Medio: en Difícil/Muy difícil/Extremo la recompensa se mantiene
	 * alta siempre, para no castigar rejugar lo difícil de verdad.
	 */
	public function calcularRecompensaMonedas($dificultad, $rango, $vecesPrevias) {
		$base = (float) $this->config("pve_recompensa_" . $dificultad, 100);
		$multRango = (float) $this->config("pve_recompensa_mult_rango_" . strtolower($rango ?: "b"), 1.0);

		$factor = 1.0;
		if ($vecesPrevias > 0 && in_array($dificultad, self::DIFICULTADES_CON_DECRECIMIENTO, true)) {
			$tasa = (float) $this->config("pve_recompensa_decrecimiento_tasa", 0.55);
			$piso = (float) $this->config("pve_recompensa_decrecimiento_piso", 0.15);
			$factor = max($piso, pow($tasa, $vecesPrevias));
		}

		return (int) round($base * $multRango * $factor);
	}

	/** Da de alta una copia normal. Es lo mismo que hace un sobre, reutilizado. */
	private function otorgarCromo($id_usuario, $id_cromo) {
		$this->pdo->prepare("
			INSERT INTO coleccion (id_usuario, id_cromo, obtenida, bloqueada) VALUES (:u, :c, NOW(), 0)
		")->execute([":u" => $id_usuario, ":c" => $id_cromo]);
		return (int) $this->pdo->lastInsertId();
	}

	/**
	 * Da de alta una copia NUMERADA, si queda cupo. Devuelve
	 * ["numero_serie" => n, "cupo_total" => n] o null si el cupo ya está
	 * agotado (no es un error: simplemente esa tirada no entrega nada).
	 *
	 * `FOR UPDATE` sobre la fila del cromo es lo que evita la doble emisión:
	 * si dos jugadores ganan la última copia a la vez, el segundo espera a que
	 * el primero termine y entonces ve el cupo ya a cero.
	 */
	private function otorgarCromoLimitado($id_usuario, $id_cromo) {
		$cromo = $this->pdo->prepare("SELECT cupo_numerado FROM cromos WHERE id_cromo = :c FOR UPDATE");
		$cromo->execute([":c" => $id_cromo]);
		$cupo = (int) $cromo->fetchColumn();
		if ($cupo <= 0) { return null; }   // no está configurada como limitada

		$emitidas = $this->pdo->prepare("SELECT COUNT(*) FROM cadena_numeracion WHERE id_cromo = :c");
		$emitidas->execute([":c" => $id_cromo]);
		$n = (int) $emitidas->fetchColumn();
		if ($n >= $cupo) { return null; }   // cupo agotado

		$idColeccion = $this->otorgarCromo($id_usuario, $id_cromo);
		$numeroSerie = $n + 1;
		$this->pdo->prepare("
			INSERT INTO cadena_numeracion (id_cromo, numero_serie, id_coleccion, otorgado)
			VALUES (:c, :n, :col, NOW())
		")->execute([":c" => $id_cromo, ":n" => $numeroSerie, ":col" => $idColeccion]);

		return ["numero_serie" => $numeroSerie, "cupo_total" => $cupo];
	}

	private function registrarDrop($id_usuario, $id_duelo, $id_nodo, $tipo, $idCromo, $numeroSerie, $monedas, $formacion) {
		$this->pdo->prepare("
			INSERT INTO cadena_drops (id_usuario, id_duelo, id_nodo, tipo, id_cromo, numero_serie, monedas, formacion, creado)
			VALUES (:u, :d, :n, :t, :c, :s, :m, :f, NOW())
		")->execute([
			":u" => $id_usuario, ":d" => $id_duelo, ":n" => $id_nodo, ":t" => $tipo,
			":c" => $idCromo, ":s" => $numeroSerie, ":m" => $monedas, ":f" => $formacion,
		]);
	}

	/**
	 * Sortea y entrega la loot table de un nodo (partido o cofre). Cada fila
	 * de `cadena_loot` es una tirada independiente: pueden caer varias, o
	 * ninguna, según su probabilidad. `$rango` es null en los cofres (no
	 * puntúan) y en ese caso solo se consideran las filas SIN rango mínimo.
	 *
	 * Devuelve la lista de lo entregado, en el mismo formato que espera la
	 * pantalla de resultado.
	 */
	public function otorgarLootNodo($id_nodo, $id_usuario, $rango, $id_duelo = null) {
		$stmt = $this->pdo->prepare("SELECT * FROM cadena_loot WHERE id_nodo = :n");
		$stmt->execute([":n" => $id_nodo]);

		$otorgado = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
			if ($item["rango_minimo"] !== null) {
				if ($rango === null) { continue; }
				if (self::ORDEN_RANGO[$rango] > self::ORDEN_RANGO[$item["rango_minimo"]]) { continue; }
			}
			if ((mt_rand(0, 9999) / 100) >= (float) $item["probabilidad"]) { continue; }

			if ($item["tipo"] === "monedas") {
				$cantidad = (int) $item["monedas"];
				$this->pdo->prepare("UPDATE usuarios SET monedas = monedas + :m WHERE id_usuario = :u")
					->execute([":m" => $cantidad, ":u" => $id_usuario]);
				$this->registrarDrop($id_usuario, $id_duelo, $id_nodo, "monedas", null, null, $cantidad, null);
				$otorgado[] = ["tipo" => "monedas", "monedas" => $cantidad];

			} elseif ($item["tipo"] === "cromo") {
				$this->otorgarCromo($id_usuario, (int) $item["id_cromo"]);
				$this->registrarDrop($id_usuario, $id_duelo, $id_nodo, "cromo", (int) $item["id_cromo"], null, null, null);
				$otorgado[] = ["tipo" => "cromo", "id_cromo" => (int) $item["id_cromo"]];

			} elseif ($item["tipo"] === "cromo_limitado") {
				$r = $this->otorgarCromoLimitado($id_usuario, (int) $item["id_cromo"]);
				if ($r === null) { continue; }   // cupo agotado: tirada sin premio, no es un fallo
				$this->registrarDrop(
					$id_usuario, $id_duelo, $id_nodo, "cromo_limitado",
					(int) $item["id_cromo"], $r["numero_serie"], null, null
				);
				$otorgado[] = [
					"tipo" => "cromo_limitado", "id_cromo" => (int) $item["id_cromo"],
					"numero_serie" => $r["numero_serie"], "cupo_total" => $r["cupo_total"],
				];
			}
		}
		return $otorgado;
	}

	/** Lo que se entregó en un duelo de cadena concreto, para la pantalla de resultado. */
	public function listarDropsDuelo($id_duelo) {
		$stmt = $this->pdo->prepare("
			SELECT d.*, c.nombre AS cromo_nombre, c.cupo_numerado
			FROM cadena_drops d
			LEFT JOIN cromos c ON c.id_cromo = d.id_cromo
			WHERE d.id_duelo = :id
			ORDER BY d.id_drop
		");
		$stmt->execute([":id" => $id_duelo]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Lo que se entregó al reclamar un cofre concreto. Trae también las
	 * columnas que espera `carta_html()` (equipo, rareza, afinidad...) para
	 * que la ceremonia de apertura pueda pintar la carta real sin una
	 * segunda consulta.
	 */
	public function listarDropsCofre($id_nodo, $id_usuario) {
		$stmt = $this->pdo->prepare("
			SELECT d.*,
			       c.nombre AS cromo_nombre, c.cupo_numerado,
			       c.imagen, c.posicion, c.id_rareza,
			       eq.nombre AS equipo, r.nombre AS rareza,
			       af.nombre AS afinidad, af.imagen AS afinidad_imagen
			FROM cadena_drops d
			LEFT JOIN cromos c ON c.id_cromo = d.id_cromo
			LEFT JOIN equipos eq ON eq.id_equipo = c.id_equipo
			LEFT JOIN rarezas r ON r.id_rareza = c.id_rareza
			LEFT JOIN afinidad af ON af.id = c.id_afinidad
			WHERE d.id_nodo = :n AND d.id_usuario = :u AND d.id_duelo IS NULL
			ORDER BY d.id_drop
		");
		$stmt->execute([":n" => $id_nodo, ":u" => $id_usuario]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	// ----------------------------------------------------------
	// Cadenas: mapa, progreso y desbloqueo (bloque C)
	// ----------------------------------------------------------

	/** Cadenas publicadas y no caducadas, en orden de presentación. */
	public function listarCadenas() {
		return $this->pdo->query("
			SELECT * FROM cadenas
			WHERE activa = 1 AND (fecha_fin IS NULL OR fecha_fin > NOW())
			ORDER BY orden, id_cadena
		")->fetchAll(PDO::FETCH_ASSOC);
	}

	public function obtenerCadena($id_cadena) {
		$stmt = $this->pdo->prepare("
			SELECT * FROM cadenas
			WHERE id_cadena = :id AND activa = 1 AND (fecha_fin IS NULL OR fecha_fin > NOW())
		");
		$stmt->execute([":id" => $id_cadena]);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
	}

	/**
	 * Requisitos que este jugador NO cumple todavía, ya redactados para poder
	 * enseñarlos tal cual en el modal de bloqueo. Devuelve [] si puede entrar.
	 *
	 * Se comprueba al intentar entrar, no al listar (§5 del briefing): así la
	 * cadena se ve y se sabe que existe, pero no se puede jugar.
	 */
	public function requisitosPendientes($id_cadena, $id_usuario) {
		$stmt = $this->pdo->prepare("SELECT * FROM cadena_requisitos WHERE id_cadena = :c");
		$stmt->execute([":c" => $id_cadena]);

		$pendientes = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $req) {
			if ($req["tipo"] === "cadena") {
				if (!$this->cadenaCompletada((int) $req["valor"], $id_usuario)) {
					$nombre = $this->pdo->prepare("SELECT nombre FROM cadenas WHERE id_cadena = :c");
					$nombre->execute([":c" => $req["valor"]]);
					$pendientes[] = "Completar la cadena «" . ($nombre->fetchColumn() ?: "anterior") . "»";
				}
			} elseif ($req["tipo"] === "cromo") {
				$tiene = $this->pdo->prepare("
					SELECT 1 FROM coleccion WHERE id_usuario = :u AND id_cromo = :c LIMIT 1
				");
				$tiene->execute([":u" => $id_usuario, ":c" => $req["valor"]]);
				if (!$tiene->fetchColumn()) {
					$nombre = $this->pdo->prepare("SELECT nombre FROM cromos WHERE id_cromo = :c");
					$nombre->execute([":c" => $req["valor"]]);
					$pendientes[] = "Tener la carta «" . ($nombre->fetchColumn() ?: "requerida") . "»";
				}
			}
		}
		return $pendientes;
	}

	/** Una cadena está completa cuando su cofre final está reclamado. */
	public function cadenaCompletada($id_cadena, $id_usuario) {
		$stmt = $this->pdo->prepare("
			SELECT 1 FROM cadena_nodos n
			INNER JOIN cadena_cofres c ON c.id_nodo = n.id_nodo AND c.id_usuario = :u
			WHERE n.id_cadena = :c AND n.es_final = 1
			LIMIT 1
		");
		$stmt->execute([":u" => $id_usuario, ":c" => $id_cadena]);
		return (bool) $stmt->fetchColumn();
	}

	/**
	 * Los nodos de una cadena con su estado para este jugador.
	 *
	 * Un nodo está DISPONIBLE si no tiene aristas de entrada (es un inicio) o si
	 * alguno de sus predecesores está superado. "Superado" es ganar el partido a
	 * cualquier dificultad, o haber abierto el cofre si es un nodo de cofre: por
	 * eso hay que recoger un cofre para seguir avanzando, aunque recogerlo sea
	 * gratis.
	 *
	 * Devuelve además `aristas` para que el mapa dibuje las líneas sin volver a
	 * consultar, y `rangos` con el mejor rango por dificultad de cada nodo.
	 */
	public function mapaCadena($id_cadena, $id_usuario) {
		$stmt = $this->pdo->prepare("
			SELECT n.*, r.nombre AS rival, r.escudo AS escudo_rival
			FROM cadena_nodos n
			LEFT JOIN cadena_rivales r ON r.id_rival = n.id_rival
			WHERE n.id_cadena = :c
			ORDER BY n.columna, n.fila
		");
		$stmt->execute([":c" => $id_cadena]);
		$nodos = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $n) { $nodos[(int) $n["id_nodo"]] = $n; }
		if (!$nodos) { return ["nodos" => [], "aristas" => []]; }

		$ids = array_keys($nodos);
		$marcadores = implode(",", array_fill(0, count($ids), "?"));

		$aristas = $this->pdo->prepare("
			SELECT id_origen, id_destino FROM cadena_aristas WHERE id_destino IN ($marcadores)
		");
		$aristas->execute($ids);
		$aristas = $aristas->fetchAll(PDO::FETCH_ASSOC);

		// progreso del jugador en estos nodos
		$prog = $this->pdo->prepare("
			SELECT id_nodo, dificultad, veces, victorias, mejor_rango
			FROM cadena_progreso WHERE id_usuario = ? AND id_nodo IN ($marcadores)
		");
		$prog->execute(array_merge([$id_usuario], $ids));

		$porNodo = [];
		foreach ($prog->fetchAll(PDO::FETCH_ASSOC) as $p) {
			$porNodo[(int) $p["id_nodo"]][$p["dificultad"]] = $p;
		}

		$cofres = $this->pdo->prepare("
			SELECT id_nodo FROM cadena_cofres WHERE id_usuario = ? AND id_nodo IN ($marcadores)
		");
		$cofres->execute(array_merge([$id_usuario], $ids));
		$reclamados = array_flip(array_map("intval", $cofres->fetchAll(PDO::FETCH_COLUMN)));

		// superado = ganado alguna vez (partido) o cofre ya abierto
		$superado = [];
		foreach ($nodos as $id => $n) {
			$superado[$id] = $n["tipo"] === "cofre"
				? isset($reclamados[$id])
				: (bool) array_filter($porNodo[$id] ?? [], fn($p) => $p["mejor_rango"] !== null);
		}

		$entrantes = [];
		foreach ($aristas as $a) { $entrantes[(int) $a["id_destino"]][] = (int) $a["id_origen"]; }

		foreach ($nodos as $id => &$n) {
			$previos = $entrantes[$id] ?? [];
			$disponible = !$previos;
			foreach ($previos as $p) {
				if (!empty($superado[$p])) { $disponible = true; break; }
			}

			$n["superado"]   = $superado[$id];
			$n["disponible"] = $disponible;
			$n["reclamado"]  = isset($reclamados[$id]);
			$n["progreso"]   = $porNodo[$id] ?? [];

			// mejor rango conseguido en cualquier dificultad, para la insignia
			$n["mejor_rango"] = null;
			foreach ($n["progreso"] as $p) {
				if ($p["mejor_rango"] === null) { continue; }
				if ($n["mejor_rango"] === null
					|| self::ORDEN_RANGO[$p["mejor_rango"]] < self::ORDEN_RANGO[$n["mejor_rango"]]) {
					$n["mejor_rango"] = $p["mejor_rango"];
				}
			}
		}
		unset($n);

		return ["nodos" => $nodos, "aristas" => $aristas];
	}

	/** Un nodo suelto, con su cadena, para validar antes de jugarlo. */
	public function obtenerNodo($id_nodo) {
		$stmt = $this->pdo->prepare("
			SELECT n.*, c.nombre AS cadena, c.formacion_recompensa, c.activa, c.fecha_fin
			FROM cadena_nodos n
			INNER JOIN cadenas c ON c.id_cadena = n.id_cadena
			WHERE n.id_nodo = :n
		");
		$stmt->execute([":n" => $id_nodo]);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
	}

	/**
	 * Guarda el resultado de un partido de cadena. Devuelve cuántas veces se
	 * había jugado YA este nodo a esta dificultad ANTES de esta partida (0 la
	 * primera vez), que es lo que necesita calcularRecompensaMonedas() para
	 * decidir si esta victoria decrece o es la primera.
	 *
	 * `veces` cuenta también las derrotas (lo necesita la recompensa decreciente
	 * del bloque D) y `mejor_rango` NUNCA empeora: rejugar para probar otra
	 * dificultad no puede costarte la S que ya tenías. Decisión de Alejandro.
	 */
	public function registrarProgresoNodo($id_usuario, $id_nodo, $dificultad, $gano, $rango) {
		// FOR UPDATE: la misma fila que se va a tocar a continuación, para que
		// leer "veces previas" y escribir el incremento sea una operación
		// atómica y no una carrera entre dos peticiones del mismo jugador.
		$actual = $this->pdo->prepare("
			SELECT veces FROM cadena_progreso WHERE id_usuario = :u AND id_nodo = :n AND dificultad = :d FOR UPDATE
		");
		$actual->execute([":u" => $id_usuario, ":n" => $id_nodo, ":d" => $dificultad]);
		$vecesPrevias = (int) ($actual->fetchColumn() ?: 0);

		$this->pdo->prepare("
			INSERT INTO cadena_progreso (id_usuario, id_nodo, dificultad, veces, victorias, mejor_rango, primera_victoria)
			VALUES (:u, :n, :d, 1, :v, :r, :pv)
			ON DUPLICATE KEY UPDATE
				veces = veces + 1,
				victorias = victorias + VALUES(victorias),
				-- OJO: no vale LEAST() sobre el CHAR. Alfabéticamente es
				-- 'A' < 'B' < 'S', así que LEAST() elegiría una A por encima de
				-- una S y el rango EMPEORARÍA al rejugar. FIELD() da el orden
				-- real de mejor a peor (S=1, A=2, B=3).
				mejor_rango = IF(VALUES(mejor_rango) IS NULL, mejor_rango,
					IF(mejor_rango IS NULL, VALUES(mejor_rango),
						IF(FIELD(VALUES(mejor_rango), 'S','A','B') < FIELD(mejor_rango, 'S','A','B'),
							VALUES(mejor_rango), mejor_rango))),
				primera_victoria = COALESCE(primera_victoria, VALUES(primera_victoria))
		")->execute([
			":u" => $id_usuario,
			":n" => $id_nodo,
			":d" => $dificultad,
			":v" => $gano ? 1 : 0,
			":r" => $rango,
			":pv" => $gano ? date("Y-m-d H:i:s") : null,
		]);

		return $vecesPrevias;
	}

	/** Qué hace falta para el premio extra del cofre. Cambiarlo es cambiar estas
	 *  dos líneas, que es justo lo que pidió Alejandro (§15.12, decisión 4). */
	const CAMINO_PERFECTO_DIFICULTAD = "extremo";
	const CAMINO_PERFECTO_RANGO      = "S";

	/**
	 * ¿EXISTE UN CAMINO de raíz a este cofre en el que todos los partidos estén
	 * en rango S y en Extremo? (§15.12, decisión 4.)
	 *
	 * ⚠️ "EXISTE UN CAMINO" Y NO "TODOS LOS ANTERIORES", y la diferencia importa:
	 * `mapaCadena()` da un nodo por disponible si CUALQUIERA de sus predecesores
	 * está superado, o sea que una cadena se recorre ELIGIENDO camino. Exigir
	 * todos los ancestros obligaría a jugarse las dos ramas de cada bifurcación,
	 * que no es como se llega al cofre.
	 *
	 * ⚠️ Y TIENE QUE SER EN EXTREMO. `mejor_rango` se guarda por dificultad y las
	 * cinco están siempre disponibles, así que con "cualquier dificultad" se podría
	 * granjear la S entera en Fácil y reclamar el cofre con el premio bueno.
	 *
	 * Un cofre sin predecesores da `true` por vacuidad: no hay ningún partido
	 * previo que pueda no estar en S.
	 */
	public function caminoPerfectoHastaCofre($id_nodo, $id_usuario) {
		$nodo = $this->obtenerNodo($id_nodo);
		if (!$nodo) return false;

		$mapa = $this->mapaCadena((int) $nodo["id_cadena"], $id_usuario);
		$nodos = $mapa["nodos"];

		$entrantes = [];
		foreach ($mapa["aristas"] as $a) {
			$entrantes[(int) $a["id_destino"]][] = (int) $a["id_origen"];
		}

		// Memoria y marca de visita: la memoria evita recorrer dos veces las
		// confluencias (las rutas alternativas vuelven a juntarse), y la marca
		// evita quedarse dando vueltas si alguien llega a sembrar un ciclo.
		$resuelto = [];
		$visitando = [];

		$perfecto = function ($id) use (&$perfecto, &$resuelto, &$visitando, $nodos, $entrantes) {
			$id = (int) $id;
			if (isset($resuelto[$id]))  return $resuelto[$id];
			if (isset($visitando[$id])) return false;
			$visitando[$id] = true;

			$n = $nodos[$id] ?? null;
			$vale = true;

			if (!$n) {
				$vale = false;
			} elseif ($n["tipo"] === "partido") {
				$prog = $n["progreso"][self::CAMINO_PERFECTO_DIFICULTAD] ?? null;
				$vale = $prog && $prog["mejor_rango"] === self::CAMINO_PERFECTO_RANGO;
			}

			if ($vale) {
				$previos = $entrantes[$id] ?? [];
				if ($previos) {
					$vale = false;
					foreach ($previos as $p) {
						if ($perfecto($p)) { $vale = true; break; }
					}
				}
			}

			unset($visitando[$id]);
			return $resuelto[$id] = $vale;
		};

		return $perfecto($id_nodo);
	}

	/**
	 * Abre un cofre alcanzado. Entrega la formación de la cadena si es el cofre
	 * final, y la loot table del nodo (monedas, cartas, numeradas). Idempotente:
	 * reclamar dos veces no entrega dos veces (lo impide `$estado['reclamado']`
	 * arriba, antes de llegar aquí).
	 */
	public function reclamarCofre($id_nodo, $id_usuario) {
		$nodo = $this->obtenerNodo($id_nodo);
		if (!$nodo || $nodo["tipo"] !== "cofre") {
			return ["ok" => false, "error" => "Ese nodo no es un cofre."];
		}

		$mapa = $this->mapaCadena((int) $nodo["id_cadena"], $id_usuario);
		$estado = $mapa["nodos"][(int) $id_nodo] ?? null;
		if (!$estado || !$estado["disponible"]) {
			return ["ok" => false, "error" => "Todavía no has llegado a este cofre."];
		}
		if ($estado["reclamado"]) {
			return ["ok" => false, "error" => "Ya habías abierto este cofre."];
		}

		try {
			$this->pdo->beginTransaction();

			$this->pdo->prepare("
				INSERT IGNORE INTO cadena_cofres (id_usuario, id_nodo) VALUES (:u, :n)
			")->execute([":u" => $id_usuario, ":n" => $id_nodo]);

			$formacion = null;
			if ((int) $nodo["es_final"] === 1 && $nodo["formacion_recompensa"]) {
				$formacion = $nodo["formacion_recompensa"];
				$this->desbloquearFormacion($id_usuario, $formacion);
				$this->registrarDrop($id_usuario, null, $id_nodo, "formacion", null, null, null, $formacion);
			}

			/* Los cofres no puntúan: se reclaman, no se juegan, así que su contenido
			   es FIJO y sale de las filas SIN rango mínimo — decisión 3 de Alejandro,
			   y es lo que hacía este `null` desde siempre.

			   La única excepción es el PREMIO EXTRA POR CAMINO PERFECTO (decisión 4):
			   si todos los partidos que llevan hasta aquí están en S y en Extremo, se
			   pasa rango 'S' y entran además las filas con `rango_minimo = 'S'`. El
			   premio se declara en `cadena_loot` como cualquier otro botín: aquí no
			   hay ningún mecanismo nuevo, solo un parámetro distinto. */
			$perfecto = $this->caminoPerfectoHastaCofre($id_nodo, $id_usuario);
			$loot = $this->otorgarLootNodo(
				$id_nodo, $id_usuario, $perfecto ? self::CAMINO_PERFECTO_RANGO : null
			);

			$this->pdo->commit();
			return ["ok" => true, "error" => null, "formacion" => $formacion,
			        "loot" => $loot, "camino_perfecto" => $perfecto];
		} catch (Exception $e) {
			$this->pdo->rollBack();
			return ["ok" => false, "error" => "No se pudo abrir el cofre."];
		}
	}

	/** Tabla Plata/Oro/Prisma del aumento del rival según dificultad. */
	public function tiersDificultad($dificultad) {
		$crudo  = (string) $this->config("pve_tiers_" . $dificultad, "");
		$partes = array_map("floatval", array_filter(explode(",", $crudo), "strlen"));
		if (count($partes) !== 3) {
			return ["plata" => 60, "oro" => 30, "prisma" => 10];
		}
		return ["plata" => $partes[0], "oro" => $partes[1], "prisma" => $partes[2]];
	}

	/**
	 * Marcador de un partido de cadena.
	 *
	 * SOLO PvE. El PvP ya no usa fórmula: su marcador nace de la simulación
	 * minuto a minuto (§1.3, modo natural de generarEventosPartido). Esta se
	 * mantiene intacta mientras las cadenas no se trabajen aparte. El motivo de
	 * existir sigue siendo concreto: con la fórmula que tenía antes el PvP, la
	 * portería a cero era imposible (0 de 200.000 simulaciones), y sin portería
	 * a cero el rango S no se puede conseguir nunca.
	 *
	 * Cada lado marca según un Poisson cuya media es su ataque contra el muro
	 * rival. Poisson es el modelo estándar de goles en fútbol y, a diferencia
	 * de la fórmula anterior, da al 0 una probabilidad real: una defensa que
	 * domina al ataque contrario deja la portería a cero de vez en cuando.
	 *
	 * El ganador ya viene decidido por la curva Elo y NO se toca: si el sorteo
	 * de goles contradijera el resultado, se ajusta el marcador, nunca el
	 * ganador.
	 */
	private function marcadorCadena(array $fuerzaGanador, array $fuerzaPerdedor, $formGanador, $formPerdedor) {
		$escala   = (float) $this->config("pve_goles_escala", 2.6);
		$exponente = (float) $this->config("pve_goles_exponente", 1.6);
		$tope     = (int) $this->config("pve_goles_max", 9);

		// Se compara la media POR JUGADOR, no el total de la línea. Sumar sin
		// normalizar comparaba dos delanteros contra cinco defensas, así que el
		// cociente salía bajo siempre y casi todos los partidos acababan 1-0.
		// Además hace la cifra independiente de la formación: un 1-3-6-1 no
		// marca menos por tener un delantero, marca según lo bueno que sea.
		$ataqueGanador  = $this->mediaLinea($fuerzaGanador,  $formGanador,  ["DC"]);
		$ataquePerdedor = $this->mediaLinea($fuerzaPerdedor, $formPerdedor, ["DC"]);
		$muroGanador    = $this->mediaLinea($fuerzaGanador,  $formGanador,  ["POR", "DF"]);
		$muroPerdedor   = $this->mediaLinea($fuerzaPerdedor, $formPerdedor, ["POR", "DF"]);

		// El exponente hace que las diferencias de calidad se noten más de lo
		// proporcional: un ataque muy superior no marca un poco más, golea. Es
		// lo que separa una victoria de rango S de una de rango B.
		$lambdaGanador  = $escala * pow($ataqueGanador  / max(0.01, $muroPerdedor), $exponente);
		$lambdaPerdedor = $escala * pow($ataquePerdedor / max(0.01, $muroGanador),  $exponente);

		// El ganador ya viene decidido por la curva Elo, así que el marcador se
		// construye alrededor de él: primero cuánto marca el ganador según su
		// propio ritmo, y después el perdedor con su Poisson TRUNCADO por debajo
		// de esa cifra.
		//
		// Se hizo así tras descartar dos alternativas peores. Recortar al
		// perdedor a "ganador − 1" amontonaba los resultados en un gol de
		// diferencia (3-2, 4-3, 5-4). Y volver a sortear a los dos hasta que el
		// ganador superase al otro reventaba en los desequilibrios: cuando el
		// perdedor es mucho más fuerte —una victoria por sorpresa en Extremo—
		// solo pasaban los sorteos altísimos y salían 9-8 en el 18 % de los
		// partidos. Truncando, una victoria por sorpresa sale 1-0, que es
		// exactamente como se gana un partido así.
		$golesGanador = max(1, min($tope, self::poisson($lambdaGanador)));

		$golesPerdedor = $golesGanador - 1;
		for ($intento = 0; $intento < 32; $intento++) {
			$tirada = self::poisson($lambdaPerdedor);
			if ($tirada < $golesGanador) { $golesPerdedor = $tirada; break; }
		}

		return [$golesGanador, $golesPerdedor];
	}

	/** Fuerza media POR JUGADOR de unas líneas dentro de una formación. */
	private function mediaLinea(array $fuerza, $formacion, array $lineas) {
		$huecos = self::huecosDe($formacion);
		$suma = 0.0;
		$n = 0;
		foreach ($lineas as $linea) {
			$suma += (float) ($fuerza[$linea] ?? 0);
			$n += count(array_keys($huecos, $linea, true));
		}
		return $n > 0 ? $suma / $n : 0.0;
	}

	/** Poisson por el método de Knuth. Suficiente para lambdas pequeñas. */
	private static function poisson($lambda) {
		$lambda = max(0.0, min(12.0, (float) $lambda));
		$limite = exp(-$lambda);
		$k = 0;
		$p = 1.0;
		do {
			$k++;
			$p *= mt_rand() / mt_getrandmax();
		} while ($p > $limite);
		return $k - 1;
	}

	/**
	 * Rango del partido, con los umbrales que fijó Alejandro:
	 *   S = ganar 5-0 (o más) sin encajar · A = ganar por 3+ · B = ganar.
	 * Devuelve null si no se ganó: perder no tiene rango, tiene reintento.
	 */
	public function rangoPartido($golesJugador, $golesRival) {
		if ($golesJugador <= $golesRival) {
			return null;
		}
		$sGoles  = (int) $this->config("pve_rango_s_goles", 5);
		$aMargen = (int) $this->config("pve_rango_a_margen", 3);

		if ($golesJugador >= $sGoles && $golesRival === 0) { return "S"; }
		if ($golesJugador - $golesRival >= $aMargen)       { return "A"; }
		return "B";
	}

	// ==========================================================
	// MISIONES
	// ==========================================================

	// Límites del "hoy" y del "esta semana" ACTUALES, calculados por MySQL y no
	// por PHP: no hay ningún date_default_timezone_set() en el proyecto, así
	// que la zona horaria de PHP depende de php.ini y podría no coincidir con
	// la del servidor de MySQL. Todo lo que escribe una fecha en esta base de
	// datos lo hace con NOW() (nunca con la hora de PHP), así que comparar
	// contra límites calculados también en MySQL es la única forma de que
	// "medianoche hora del servidor" signifique lo mismo en la escritura y en
	// la lectura. El lunes de la semana sale de WEEKDAY() (0 = lunes), no de
	// restar días a mano, que se lía con los cambios de mes/año.
	private function limitesDePeriodo() {
		$fila = $this->pdo->query("
			SELECT
				DATE_FORMAT(NOW(), '%Y-%m-%d') AS periodo_diaria,
				CURDATE() AS desde_diaria,
				DATE_FORMAT(NOW(), '%x-W%v') AS periodo_semanal,
				DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AS desde_semanal
		")->fetch(PDO::FETCH_ASSOC);

		return [
			"diaria"  => ["periodo" => $fila["periodo_diaria"],  "desde" => $fila["desde_diaria"]],
			"semanal" => ["periodo" => $fila["periodo_semanal"], "desde" => $fila["desde_semanal"]],
		];
	}

	// `periodo`/`desde` de UNA misión según su ciclo. "unica" no tiene ventana:
	// es la cadena vacía reservada de la 005 y el progreso es de toda la vida.
	private function periodoDeCiclo($ciclo) {
		if ($ciclo === "unica") {
			return ["periodo" => "", "desde" => null];
		}
		return $this->limitesDePeriodo()[$ciclo];
	}

	// Progreso actual de un usuario para un TIPO de misión. Nunca se guarda en
	// un contador propio (misiones_progreso solo registra el reclamo): se
	// deriva siempre de las consultas que ya existen, para no tener una
	// segunda fuente de verdad que se pueda desincronizar.
	//
	// `$desde` acota el conteo a "ocurrido desde tal instante" (medianoche de
	// hoy, o el lunes de esta semana) para las misiones diaria/semanal. `null`
	// (el caso de "unica") es el comportamiento de siempre: acumulado de toda
	// la vida, sin tocar.
	private function progresoMision($tipo, $id_usuario, $desde = null) {
		$params = [":id" => $id_usuario];
		if ($desde !== null) { $params[":desde"] = $desde; }

		switch ($tipo) {
			case "cartas_distintas":
				$sql = "SELECT COUNT(DISTINCT id_cromo) AS total FROM coleccion WHERE id_usuario = :id"
					. ($desde !== null ? " AND obtenida >= :desde" : "");
				break;
			case "copias_totales":
				$sql = "SELECT COUNT(*) AS total FROM coleccion WHERE id_usuario = :id"
					. ($desde !== null ? " AND obtenida >= :desde" : "");
				break;
			case "expansiones_completas":
				// Es un hito de ESTADO ("ya tienes toda la expansión"), no un evento
				// con fecha propia: no hay columna que diga cuándo se cruzó el
				// umbral, así que no se puede acotar a "completada esta semana". El
				// panel no deja crear esta combinación (tipo=expansiones_completas +
				// ciclo != única, ver crearMisionAdmin()); si de todos modos llegara
				// aquí con $desde, se ignora y se responde el estado actual, que es
				// lo menos sorprendente que se puede hacer.
				return $this->contarExpansionesCompletas($id_usuario);
			case "mazos_creados":
				$sql = "SELECT COUNT(*) AS total FROM mazos WHERE id_usuario = :id"
					. ($desde !== null ? " AND creado >= :desde" : "");
				break;
			case "duelos_jugados":
				// Se acota por `duelos.creado`, no por cuándo se liquidó de verdad:
				// esa fecha (`resuelto`) ya está tomada para otra cosa —la hora en la
				// que arrancarPartidoSiToca() cuenta partido_espera_seg, ver §15.10—
				// y reutilizarla rompería esa cuenta. Un duelo se cierra dentro de la
				// misma sesión salvo abandono (hasta partido_abandono_seg = 1h), así
				// que `creado` es una aproximación de sobra para una misión.
				$sql = "SELECT COUNT(*) AS total FROM duelos
					WHERE estado = 'resuelto' AND (id_creador = :id OR id_rival = :id)"
					. ($desde !== null ? " AND creado >= :desde" : "");
				break;
			case "duelos_ganados":
				$sql = "SELECT COUNT(*) AS total FROM duelos WHERE id_ganador = :id"
					. ($desde !== null ? " AND creado >= :desde" : "");
				break;
			default:
				return 0;
		}

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return (int) $stmt->fetch(PDO::FETCH_ASSOC)["total"];
	}

	// Misiones activas con el progreso YA calculado y si están reclamadas.
	// El `periodo` contra el que se busca el reclamo depende del CICLO de
	// CADA misión (única -> '', diaria -> el día de hoy, semanal -> la semana
	// de hoy), de ahí el CASE: no es el mismo valor para todas las filas.
	// Una misión ya reclamada en el periodo actual no vuelve a recalcular
	// progreso, se queda en el objetivo para no hacer trabajo de más.
	public function listarMisionesConProgreso($id_usuario) {
		$limites = $this->limitesDePeriodo();

		$stmt = $this->pdo->prepare("
			SELECT m.*, mp.fecha_reclamada
			FROM misiones m
			LEFT JOIN misiones_progreso mp
				ON mp.id_mision = m.id_mision AND mp.id_usuario = :id_usuario
				AND mp.periodo = CASE m.ciclo
					WHEN 'diaria'  THEN :periodo_diaria
					WHEN 'semanal' THEN :periodo_semanal
					ELSE ''
				END
			WHERE m.activo = 1
			ORDER BY m.id_mision
		");
		$stmt->execute([
			":id_usuario" => $id_usuario,
			":periodo_diaria" => $limites["diaria"]["periodo"],
			":periodo_semanal" => $limites["semanal"]["periodo"],
		]);
		$misiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach ($misiones as &$m) {
			$m["reclamada"] = $m["fecha_reclamada"] !== null;
			if ($m["reclamada"]) {
				$m["progreso"] = (int) $m["objetivo"];
			} else {
				$desde = $m["ciclo"] === "unica" ? null : $limites[$m["ciclo"]]["desde"];
				$m["progreso"] = $this->progresoMision($m["tipo"], $id_usuario, $desde);
			}
			$m["completada"] = $m["progreso"] >= (int) $m["objetivo"];
		}
		return $misiones;
	}

	// Reclamo manual: comprueba objetivo y que no se haya reclamado ya EN ESTE
	// PERIODO antes de pagar, bloqueando las filas implicadas (mismo patrón
	// que comprarAnuncio() y canjearCodigo()) para que dos clics simultáneos
	// no paguen dos veces. Al llegar un periodo nuevo (mañana, o el lunes que
	// viene) el `periodo` cambia solo y la fila de ayer/la semana pasada deja
	// de bloquear nada: el "reinicio" no borra ni actualiza nada, sale gratis
	// de que la clave única es (usuario, misión, periodo).
	// Segundos hasta el próximo reinicio de cada ciclo, para pintar la cuenta
	// atrás en misiones.php. Calculado en MySQL por el mismo motivo que
	// limitesDePeriodo(): el servidor no fija zona horaria en PHP, así que
	// "cuánto falta" tiene que salir de la misma hora con la que se decide
	// cuándo cambia el periodo, o el reloj de la pantalla mentiría.
	//
	// La semana que viene no es siempre "+7 días": si hoy es lunes, el
	// PRÓXIMO lunes cae dentro de 7 días (el de hoy ya pasó su medianoche);
	// cualquier otro día, cae dentro de `7 - WEEKDAY(hoy)` días. WEEKDAY()
	// da 0 para lunes, por eso el caso lunes necesita su propio IF.
	public function proximosReinicios() {
		$fila = $this->pdo->query("
			SELECT
				TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(CURDATE(), INTERVAL 1 DAY)) AS seg_diaria,
				TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(CURDATE(), INTERVAL
					IF(WEEKDAY(CURDATE()) = 0, 7, 7 - WEEKDAY(CURDATE())) DAY
				)) AS seg_semanal
		")->fetch(PDO::FETCH_ASSOC);

		return [
			"diaria"  => (int) $fila["seg_diaria"],
			"semanal" => (int) $fila["seg_semanal"],
		];
	}

	public function reclamarMision($id_mision, $id_usuario) {
		try {
			$this->pdo->beginTransaction();

			$stmt = $this->pdo->prepare("SELECT * FROM misiones WHERE id_mision = :id AND activo = 1 FOR UPDATE");
			$stmt->execute([":id" => $id_mision]);
			$mision = $stmt->fetch(PDO::FETCH_ASSOC);
			if (!$mision) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "Esa misión no existe."];
			}

			$limite = $this->periodoDeCiclo($mision["ciclo"]);

			$stmtYa = $this->pdo->prepare("
				SELECT 1 FROM misiones_progreso
				WHERE id_mision = :id_mision AND id_usuario = :id_usuario AND periodo = :periodo
				FOR UPDATE
			");
			$stmtYa->execute([":id_mision" => $id_mision, ":id_usuario" => $id_usuario, ":periodo" => $limite["periodo"]]);
			if ($stmtYa->fetch()) {
				$this->pdo->rollBack();
				$mensajes = [
					"unica"   => "Ya has reclamado esta misión.",
					"diaria"  => "Ya has reclamado esta misión hoy. Vuelve mañana.",
					"semanal" => "Ya has reclamado esta misión esta semana. Vuelve el lunes.",
				];
				return ["ok" => false, "error" => $mensajes[$mision["ciclo"]] ?? $mensajes["unica"]];
			}

			if ($this->progresoMision($mision["tipo"], $id_usuario, $limite["desde"]) < (int) $mision["objetivo"]) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "Todavía no cumples el objetivo."];
			}

			$this->pdo->prepare("
				INSERT INTO misiones_progreso (id_usuario, id_mision, periodo, fecha_reclamada)
				VALUES (:id_usuario, :id_mision, :periodo, NOW())
			")->execute([":id_usuario" => $id_usuario, ":id_mision" => $id_mision, ":periodo" => $limite["periodo"]]);

			$this->pdo->prepare("UPDATE usuarios SET monedas = monedas + :m WHERE id_usuario = :id")
				->execute([":m" => (int) $mision["recompensa_monedas"], ":id" => $id_usuario]);

			$this->pdo->commit();
			return ["ok" => true, "recompensa" => (int) $mision["recompensa_monedas"]];
		} catch (Exception $e) {
			$this->pdo->rollBack();
			return ["ok" => false, "error" => "Error al reclamar la misión."];
		}
	}

	// ==========================================================
	// IMPORTACIÓN DATOS OFICIALES
	// ==========================================================

	private const IMPORT_POSICIONES = ['POR' => 'POR', 'DEF' => 'DF', 'MED' => 'MC', 'DEL' => 'DC'];
	private const IMPORT_AFINIDADES = ['fuego' => 2, 'bosque' => 4, 'aire' => 3, 'viento' => 3, 'montana' => 1];

	// Minúsculas, sin tildes, sin espacios repetidos — para comparar nombres
	// de equipo y claves de afinidad sin que un acento o una mayúscula rompa
	// el match (el JSON oficial mezcla "Aire"/"aire", "Montaña"/"montaña"...).
	public function normalizarTexto(string $s): string {
		$s = trim(mb_strtolower($s, 'UTF-8'));
		// Reemplaza caracteres españoles comunes manualmente para evitar problemas de translit en iconv en algunos sistemas
		$s = str_replace(['ñ', 'á', 'é', 'í', 'ó', 'ú', 'à', 'è', 'ì', 'ò', 'ù'],
		                   ['n', 'a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u'], $s);
		$translit = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
		if ($translit !== false) { $s = $translit; }
		// Elimina caracteres especiales restantes que iconv pudiera haber dejado
		$s = preg_replace('/[^a-z0-9\s]/i', '', $s);
		return preg_replace('/\s+/', ' ', $s);
	}

	public function mapearPosicionJugador(string $pos): ?string {
		$pos = strtoupper(trim($pos));
		return self::IMPORT_POSICIONES[$pos] ?? null;
	}

	public function mapearAfinidadJugador(?string $nombre): int {
		if ($nombre === null || trim($nombre) === '') { return 5; } // no-afi
		return self::IMPORT_AFINIDADES[$this->normalizarTexto($nombre)] ?? 5;
	}

	public function slugImportado(string $texto): string {
		$slug = preg_replace('/[^a-z0-9]+/', '-', $this->normalizarTexto($texto));
		$slug = trim($slug, '-');
		return $slug !== '' ? $slug : 'x';
	}

	public function emparejarEquipo(string $nombreJson, array $equiposExistentes): array {
		$normJson = $this->normalizarTexto($nombreJson);
		$mejor = null;
		$mejorPct = 0.0;
		foreach ($equiposExistentes as $eq) {
			if ($this->normalizarTexto($eq['nombre']) === $normJson) {
				return ['estado' => 'exacto', 'id_equipo' => (int) $eq['id_equipo'], 'nombre' => $eq['nombre']];
			}
			similar_text($normJson, $this->normalizarTexto($eq['nombre']), $pct);
			if ($pct > $mejorPct) { $mejorPct = $pct; $mejor = $eq; }
		}
		// Umbral verificado contra datos reales: los typos genuinos dan 93-94%
		// ("Instituto Kirkwood"/"Instituto Kikrwood", "Inazuma Kids FC"/"CF"),
		// mientras que equipos distintos con un prefijo común como "Instituto "
		// pueden llegar a 75-77% sin ser el mismo equipo ("Instituto Occult"
		// contra "Instituto Otaku" da 77,4%). 90% deja margen a ambos lados.
		if ($mejor !== null && $mejorPct >= 90.0) {
			return [
				'estado' => 'ambiguo',
				'nombre_json' => $nombreJson,
				'candidato_db' => ['id_equipo' => (int) $mejor['id_equipo'], 'nombre' => $mejor['nombre']],
				'porcentaje' => round($mejorPct, 1),
			];
		}
		return ['estado' => 'nuevo', 'nombre' => $nombreJson];
	}

	// $decisiones: [id_json_equipo => ['eleccion' => 'json'|'db'|'otro', 'texto' => string|null]],
	// solo hace falta para los equipos que salieron 'ambiguo' en emparejarEquipo().
	public function resolverEquipos(array $equiposJson, array $equiposExistentes, array $decisiones): array {
		$mapa = [];
		$cacheNombreAId = [];
		foreach ($equiposExistentes as $eq) {
			$cacheNombreAId[$this->normalizarTexto($eq['nombre'])] = (int) $eq['id_equipo'];
		}

		foreach ($equiposJson as $equipo) {
			$match = $this->emparejarEquipo($equipo['nombre'], $equiposExistentes);
			$idExistente = null;
			$nombreFinal = null;

			if ($match['estado'] === 'exacto') {
				$idExistente = $match['id_equipo'];
			} elseif ($match['estado'] === 'ambiguo') {
				$decision = $decisiones[$equipo['id']] ?? ['eleccion' => 'db', 'texto' => null];
				if ($decision['eleccion'] === 'db') {
					$idExistente = $match['candidato_db']['id_equipo'];
				} elseif ($decision['eleccion'] === 'json') {
					$nombreFinal = $match['nombre_json'];
				} else {
					$nombreFinal = trim((string) $decision['texto']) !== '' ? $decision['texto'] : $match['nombre_json'];
				}
			} else {
				$nombreFinal = $match['nombre'];
			}

			if ($idExistente !== null) {
				$mapa[$equipo['id']] = $idExistente;
				continue;
			}

			$clave = $this->normalizarTexto($nombreFinal);
			if (isset($cacheNombreAId[$clave])) {
				$mapa[$equipo['id']] = $cacheNombreAId[$clave];
				continue;
			}

			$stmt = $this->pdo->prepare("INSERT INTO equipos (nombre) VALUES (:nombre)");
			$stmt->execute([':nombre' => $nombreFinal]);
			$nuevoId = (int) $this->pdo->lastInsertId();
			$cacheNombreAId[$clave] = $nuevoId;
			$mapa[$equipo['id']] = $nuevoId;
		}

		return $mapa;
	}

	// Tabla real de Rangos_estadisticas_SRF.csv (24 filas: rarezas 1-6 x POR/DF/MC/DC). Ver §15.10.
	private const IMPORT_RANGOS_STATS = [
		1 => [
			'POR' => ['ataque' => [23, 37], 'defensa' => [62, 76], 'tecnica' => [52, 66]],
			'DF'  => ['ataque' => [37, 51], 'defensa' => [57, 71], 'tecnica' => [47, 61]],
			'MC'  => ['ataque' => [48, 62], 'defensa' => [49, 63], 'tecnica' => [56, 70]],
			'DC'  => ['ataque' => [63, 77], 'defensa' => [37, 51], 'tecnica' => [50, 64]],
		],
		2 => [
			'POR' => ['ataque' => [31, 45], 'defensa' => [68, 82], 'tecnica' => [59, 73]],
			'DF'  => ['ataque' => [43, 57], 'defensa' => [65, 79], 'tecnica' => [53, 67]],
			'MC'  => ['ataque' => [56, 70], 'defensa' => [57, 71], 'tecnica' => [65, 79]],
			'DC'  => ['ataque' => [69, 83], 'defensa' => [45, 59], 'tecnica' => [58, 72]],
		],
		3 => [
			'POR' => ['ataque' => [39, 53], 'defensa' => [74, 88], 'tecnica' => [65, 79]],
			'DF'  => ['ataque' => [50, 64], 'defensa' => [72, 86], 'tecnica' => [60, 74]],
			'MC'  => ['ataque' => [64, 78], 'defensa' => [65, 79], 'tecnica' => [73, 87]],
			'DC'  => ['ataque' => [76, 90], 'defensa' => [53, 67], 'tecnica' => [66, 80]],
		],
		4 => [
			'POR' => ['ataque' => [47, 61], 'defensa' => [80, 94], 'tecnica' => [72, 86]],
			'DF'  => ['ataque' => [56, 70], 'defensa' => [79, 93], 'tecnica' => [66, 80]],
			'MC'  => ['ataque' => [72, 86], 'defensa' => [73, 87], 'tecnica' => [81, 95]],
			'DC'  => ['ataque' => [82, 96], 'defensa' => [60, 74], 'tecnica' => [74, 88]],
		],
		5 => [
			'POR' => ['ataque' => [55, 69], 'defensa' => [86, 99], 'tecnica' => [79, 93]],
			'DF'  => ['ataque' => [63, 77], 'defensa' => [86, 99], 'tecnica' => [73, 87]],
			'MC'  => ['ataque' => [80, 94], 'defensa' => [81, 95], 'tecnica' => [90, 99]],
			'DC'  => ['ataque' => [89, 99], 'defensa' => [68, 82], 'tecnica' => [83, 97]],
		],
		6 => [
			'POR' => ['ataque' => [63, 77], 'defensa' => [92, 99], 'tecnica' => [86, 99]],
			'DF'  => ['ataque' => [69, 83], 'defensa' => [92, 99], 'tecnica' => [79, 93]],
			'MC'  => ['ataque' => [88, 99], 'defensa' => [89, 99], 'tecnica' => [92, 99]],
			'DC'  => ['ataque' => [92, 99], 'defensa' => [76, 90], 'tecnica' => [91, 99]],
		],
	];

	// Tres rankings independientes (goleadores temporada anterior, goleadores
	// actuales, mejor jugador por equipo); top 1-3 -> Épico, top 4-10 -> Raro.
	// Si un jugador cae en varias listas, se queda con la rareza más alta.
	public function rankearRarezasImportacion(array $datosJson): array {
		$resultado = [];

		$ubicacionActual = [];
		foreach ($datosJson['equipos'] as $equipo) {
			foreach ($equipo['jugadores'] ?? [] as $jugador) {
				$ubicacionActual[$jugador['nombre']] = $equipo['id'];
			}
		}

		$aplicarRanking = function (array $lista) use (&$resultado, $ubicacionActual) {
			usort($lista, fn($a, $b) => $b['puntos'] <=> $a['puntos']);
			foreach ($lista as $i => $item) {
				if (!isset($ubicacionActual[$item['nombre']])) { continue; } // ya no juega
				if ($item['puntos'] <= 0) { continue; } // sin puntos no se rankea
				$idRareza = $i < 3 ? 4 : ($i < 10 ? 3 : null);
				if ($idRareza === null) { continue; }
				$clave = $ubicacionActual[$item['nombre']] . '|' . $item['nombre'];
				if ($idRareza > ($resultado[$clave] ?? 0)) { $resultado[$clave] = $idRareza; }
			}
		};

		$actuales = [];
		foreach ($datosJson['equipos'] as $equipo) {
			foreach ($equipo['jugadores'] ?? [] as $jugador) {
				$actuales[] = ['nombre' => $jugador['nombre'], 'puntos' => (int) ($jugador['goles'] ?? 0)];
			}
		}
		$aplicarRanking($actuales);

		$numeroActual = (int) ($datosJson['config']['temporada'] ?? 0);
		$etiquetaAnterior = 'Temporada ' . ($numeroActual - 1);
		foreach ($datosJson['historial_temporadas'] ?? [] as $temporada) {
			if (($temporada['nombre'] ?? '') !== $etiquetaAnterior) { continue; }
			$anteriores = [];
			foreach ($temporada['equipos'] ?? [] as $equipo) {
				foreach ($equipo['jugadores'] ?? [] as $jugador) {
					$anteriores[] = ['nombre' => $jugador['nombre'], 'puntos' => (int) ($jugador['goles'] ?? 0)];
				}
			}
			$aplicarRanking($anteriores);
			break;
		}

		$mejoresPorEquipo = [];
		foreach ($datosJson['equipos'] as $equipo) {
			$mejor = null;
			foreach ($equipo['jugadores'] ?? [] as $jugador) {
				$puntos = (int) ($jugador['goles'] ?? 0) + (int) ($jugador['asistencias'] ?? 0);
				if ($mejor === null || $puntos > $mejor['puntos']) {
					$mejor = ['nombre' => $jugador['nombre'], 'puntos' => $puntos];
				}
			}
			if ($mejor !== null) { $mejoresPorEquipo[] = $mejor; }
		}
		$aplicarRanking($mejoresPorEquipo);

		return $resultado;
	}

	public function statsBaseImportacion(string $posicion, int $idRareza): array {
		$rango = self::IMPORT_RANGOS_STATS[$idRareza][$posicion] ?? null;
		if ($rango === null) {
			return ['ataque' => 0, 'defensa' => 0, 'tecnica' => 0]; // ENT/GER/ESCUDO, o rareza sin tabla
		}
		return [
			'ataque'  => mt_rand($rango['ataque'][0], $rango['ataque'][1]),
			'defensa' => mt_rand($rango['defensa'][0], $rango['defensa'][1]),
			'tecnica' => mt_rand($rango['tecnica'][0], $rango['tecnica'][1]),
		];
	}

	public function guardarFotoImportada(string $url, string $equipoSlug, string $jugadorSlug): string {
		if (!preg_match('~^https?://~i', $url)) { return ''; }
		$contenido = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 8]]));
		if ($contenido === false) { return ''; }

		$imagen = @imagecreatefromstring($contenido);
		if ($imagen === false) { return ''; }

		$carpeta = __DIR__ . "/../assets/img/Cromos/Importados/{$equipoSlug}";
		if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true) && !is_dir($carpeta)) {
			imagedestroy($imagen);
			return '';
		}

		$rutaDisco = "{$carpeta}/{$jugadorSlug}.webp";
		$ok = @imagewebp($imagen, $rutaDisco, 85);
		imagedestroy($imagen);

		return $ok ? "./assets/img/Cromos/Importados/{$equipoSlug}/{$jugadorSlug}.webp" : '';
	}

	public function existeCromoImportado(string $nombre, int $id_equipo, int $id_expansion): bool {
		$stmt = $this->pdo->prepare("
			SELECT 1 FROM cromos
			WHERE nombre = :nombre AND id_equipo = :id_equipo AND id_expansion = :id_expansion
			LIMIT 1
		");
		$stmt->execute([':nombre' => $nombre, ':id_equipo' => $id_equipo, ':id_expansion' => $id_expansion]);
		return (bool) $stmt->fetchColumn();
	}

	public function previsualizarImportacion(array $datosJson, int $id_expansion): array {
		$equiposExistentes = $this->listarEquipos();
		$nombresEquipos = array_column($equiposExistentes, 'nombre', 'id_equipo');
		$equiposConJugadores = array_values(array_filter($datosJson['equipos'] ?? [], fn($eq) => !empty($eq['jugadores']) && empty($eq['archivado'])));

		$exactos = 0; $nuevos = []; $ambiguos = [];
		$jugadoresACrear = 0; $jugadoresOmitidos = 0; $afinidadesDesconocidas = 0; $cartasEquipo = 0;
		$posicionesDesconocidas = [];

		foreach ($equiposConJugadores as $equipo) {
			$match = $this->emparejarEquipo($equipo['nombre'], $equiposExistentes);
			$idEquipo = null;
			if ($match['estado'] === 'exacto') { $exactos++; $idEquipo = $match['id_equipo']; }
			elseif ($match['estado'] === 'ambiguo') {
				$ambiguos[] = ['id' => $equipo['id']] + $match;
				// El default de resolverEquipos() para un ambiguo sin decisión es "db"
				// (usa candidato_db); aquí mismo, para que el conteo de omitidos coincida.
				$idEquipo = $match['candidato_db']['id_equipo'];
			}
			else { $nuevos[] = $equipo['nombre']; }

			foreach ($equipo['jugadores'] as $jugador) {
				if ($this->mapearPosicionJugador($jugador['posicion'] ?? '') === null) {
					$posicionesDesconocidas[] = $jugador['nombre'];
					continue;
				}
				if ($idEquipo !== null && $this->existeCromoImportado($jugador['nombre'], $idEquipo, $id_expansion)) {
					$jugadoresOmitidos++;
					continue;
				}
				$jugadoresACrear++;
				if ($this->mapearAfinidadJugador($jugador['afinidad'] ?? null) === 5) { $afinidadesDesconocidas++; }
			}

			$nombreEquipoFinal = $idEquipo !== null ? ($nombresEquipos[$idEquipo] ?? $equipo['nombre']) : $equipo['nombre'];

			if (trim((string) ($equipo['escudo'] ?? '')) !== '') {
				$nombreEscudo = 'Escudo ' . $nombreEquipoFinal;
				if ($idEquipo === null || !$this->existeCromoImportado($nombreEscudo, $idEquipo, $id_expansion)) { $cartasEquipo++; }
			}
			if (trim((string) ($equipo['entrenador'] ?? '')) !== '') {
				if ($idEquipo === null || !$this->existeCromoImportado($equipo['entrenador'], $idEquipo, $id_expansion)) { $cartasEquipo++; }
			}
			if (trim((string) ($equipo['gerente'] ?? '')) !== '') {
				if ($idEquipo === null || !$this->existeCromoImportado($equipo['gerente'], $idEquipo, $id_expansion)) { $cartasEquipo++; }
			}
		}

		return [
			'equipos_exactos' => $exactos,
			'equipos_nuevos' => $nuevos,
			'equipos_ambiguos' => $ambiguos,
			'jugadores_a_crear' => $jugadoresACrear,
			'jugadores_omitidos' => $jugadoresOmitidos,
			'afinidades_desconocidas' => $afinidadesDesconocidas,
			'cartas_equipo_a_crear' => $cartasEquipo,
			'posiciones_desconocidas' => $posicionesDesconocidas,
		];
	}

	public function ejecutarImportacion(array $datosJson, int $id_expansion, array $decisiones, ?string $idSesionProgreso = null): array {
		set_time_limit(0);

		$equiposExistentes = $this->listarEquipos();
		$idsEquiposPrevios = array_column($equiposExistentes, 'id_equipo');
		$equiposConJugadores = array_values(array_filter($datosJson['equipos'] ?? [], fn($eq) => !empty($eq['jugadores']) && empty($eq['archivado'])));
		$mapaEquipos = $this->resolverEquipos($equiposConJugadores, $equiposExistentes, $decisiones);
		$nombresEquipos = array_column($this->listarEquipos(), 'nombre', 'id_equipo'); // incluye los equipos recién creados por resolverEquipos()
		$rareza = $this->rankearRarezasImportacion($datosJson);

		$creados = 0; $omitidos = 0; $fotosFallidas = []; $equiposCreados = 0;
		$posicionesDesconocidas = [];

		// Progreso opcional, sondeado por assets/ajax/importacion_progreso.php mientras
		// esta función (que puede tardar varios minutos) sigue ejecutándose. Se guarda en
		// un fichero temporal, no en BD ni sesión — ver §15.11 del CLAUDE.md de branding.
		$ficheroProgreso = null;
		$actualProgreso = 0;
		$totalProgreso = 0;
		if ($idSesionProgreso !== null) {
			$ficheroProgreso = sys_get_temp_dir() . '/tcg_importacion_progreso_' . $idSesionProgreso . '.json';
			foreach ($equiposConJugadores as $equipo) {
				$totalProgreso += count($equipo['jugadores']);
				if (trim((string) ($equipo['escudo'] ?? '')) !== '') { $totalProgreso++; }
				if (trim((string) ($equipo['entrenador'] ?? '')) !== '') { $totalProgreso++; }
				if (trim((string) ($equipo['gerente'] ?? '')) !== '') { $totalProgreso++; }
			}
			file_put_contents($ficheroProgreso, json_encode(['actual' => $actualProgreso, 'total' => $totalProgreso]));
		}

		$avanzarProgreso = function () use (&$actualProgreso, $totalProgreso, $ficheroProgreso) {
			if ($ficheroProgreso === null) { return; }
			$actualProgreso++;
			file_put_contents($ficheroProgreso, json_encode(['actual' => $actualProgreso, 'total' => $totalProgreso]));
		};

		foreach ($equiposConJugadores as $equipo) {
			$idEquipo = $mapaEquipos[$equipo['id']];
			$nombreEquipoFinal = $nombresEquipos[$idEquipo] ?? $equipo['nombre'];
			if (!in_array($idEquipo, $idsEquiposPrevios, true)) { $equiposCreados++; }
			$equipoSlug = $this->slugImportado($equipo['nombre']);

			foreach ($equipo['jugadores'] as $jugador) {
				$avanzarProgreso();
				if ($this->existeCromoImportado($jugador['nombre'], $idEquipo, $id_expansion)) { $omitidos++; continue; }

				$posicion = $this->mapearPosicionJugador($jugador['posicion'] ?? '');
				if ($posicion === null) { $posicionesDesconocidas[] = $jugador['nombre']; continue; }

				$idRareza = $rareza["{$equipo['id']}|{$jugador['nombre']}"] ?? (($jugador['titular'] ?? false) ? 2 : 1);
				$idAfinidad = $this->mapearAfinidadJugador($jugador['afinidad'] ?? null);
				$stats = $this->statsBaseImportacion($posicion, $idRareza);

				$imagen = '';
				if (!empty($jugador['foto'])) {
					$imagen = $this->guardarFotoImportada($jugador['foto'], $equipoSlug, $this->slugImportado($jugador['nombre']));
					if ($imagen === '') { $fotosFallidas[] = $jugador['nombre']; }
				}

				$this->crearCromo($jugador['nombre'], $posicion, '', $imagen, $id_expansion, $idEquipo, $idRareza, $idAfinidad, $stats['ataque'], $stats['defensa'], $stats['tecnica'], 1);
				$creados++;
			}

			if (trim((string) ($equipo['escudo'] ?? '')) !== '') {
				$avanzarProgreso();
				if (!$this->existeCromoImportado('Escudo ' . $nombreEquipoFinal, $idEquipo, $id_expansion)) {
					$this->crearCromo('Escudo ' . $nombreEquipoFinal, 'ESCUDO', '', '', $id_expansion, $idEquipo, 5, 5, 0, 0, 0, 1);
					$creados++;
				}
			}
			if (trim((string) ($equipo['entrenador'] ?? '')) !== '') {
				$avanzarProgreso();
				if (!$this->existeCromoImportado($equipo['entrenador'], $idEquipo, $id_expansion)) {
					$this->crearCromo($equipo['entrenador'], 'ENT', '', '', $id_expansion, $idEquipo, 5, 5, 0, 0, 0, 1);
					$creados++;
				}
			}
			if (trim((string) ($equipo['gerente'] ?? '')) !== '') {
				$avanzarProgreso();
				if (!$this->existeCromoImportado($equipo['gerente'], $idEquipo, $id_expansion)) {
					$this->crearCromo($equipo['gerente'], 'GER', '', '', $id_expansion, $idEquipo, 5, 5, 0, 0, 0, 1);
					$creados++;
				}
			}
		}

		$this->derivarRasgosConfiguracion();

		if ($ficheroProgreso !== null && file_exists($ficheroProgreso)) {
			unlink($ficheroProgreso);
		}

		return ['creados' => $creados, 'omitidos' => $omitidos, 'equipos_creados' => $equiposCreados, 'fotos_fallidas' => $fotosFallidas, 'posiciones_desconocidas' => $posicionesDesconocidas];
	}

	public function contarCartasImportadas(): int {
		$stmt = $this->pdo->query("SELECT COUNT(*) FROM cromos WHERE origen_importacion = 1");
		return (int) $stmt->fetchColumn();
	}

	public function listarExpansionesConCartasImportadas(): array {
		$stmt = $this->pdo->query("SELECT c.id_expansion, e.nombre, COUNT(*) AS total FROM cromos c JOIN expansiones e ON e.id_expansion = c.id_expansion WHERE c.origen_importacion = 1 GROUP BY c.id_expansion, e.nombre ORDER BY e.nombre");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function borrarCartasImportadas(?int $id_expansion = null): array {
		if ($id_expansion !== null) {
			$stmt = $this->pdo->prepare("SELECT id_cromo FROM cromos WHERE origen_importacion = 1 AND id_expansion = :id_expansion");
			$stmt->execute(['id_expansion' => $id_expansion]);
		} else {
			$stmt = $this->pdo->query("SELECT id_cromo FROM cromos WHERE origen_importacion = 1");
		}
		$ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
		if (empty($ids)) {
			return ['borrados' => 0, 'en_uso' => 0];
		}

		$placeholders = implode(',', array_fill(0, count($ids), '?'));

		$stmtC = $this->pdo->prepare("SELECT DISTINCT id_cromo FROM coleccion WHERE id_cromo IN ($placeholders)");
		$stmtC->execute($ids);
		$enColeccion = array_map('intval', $stmtC->fetchAll(PDO::FETCH_COLUMN));

		$stmtA = $this->pdo->prepare("SELECT DISTINCT id_cromo FROM duelo_alineaciones WHERE id_cromo IN ($placeholders)");
		$stmtA->execute($ids);
		$enAlineacion = array_map('intval', $stmtA->fetchAll(PDO::FETCH_COLUMN));

		$enUso = array_unique(array_merge($enColeccion, $enAlineacion));
		$aBorrar = array_values(array_diff($ids, $enUso));

		$borrados = 0;
		if (!empty($aBorrar)) {
			$placeholdersBorrar = implode(',', array_fill(0, count($aBorrar), '?'));
			$stmtDel = $this->pdo->prepare("DELETE FROM cromos WHERE id_cromo IN ($placeholdersBorrar)");
			$stmtDel->execute($aBorrar);
			$borrados = $stmtDel->rowCount();
		}

		return ['borrados' => $borrados, 'en_uso' => count($enUso)];
	}
}

?>