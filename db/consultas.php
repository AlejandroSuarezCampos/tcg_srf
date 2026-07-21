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

	// Inserta un nuevo usuario con la contraseña hasheada y foto por defecto
	public function registrarUsuario($nombre, $pass)
	{
		$sentencia = "INSERT INTO usuarios(nombre, password_hash, foto) VALUES (:nombre, :password_hash, :foto)";
		$ejecucion = $this->pdo->prepare($sentencia);
		$ejecucion->execute(
			array(
				":nombre" => $nombre,
				":password_hash" => password_hash($pass, PASSWORD_DEFAULT),
				":foto" => "./assets/img/perfil/apple-icon-120x120.png"
			)
		);

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

	public function listarColeccionCompleta(){
		$sql = "
			SELECT
				c.id_cromo,
				c.nombre,
				c.descripcion,
				c.imagen,
				c.posicion,
				c.id_expansion,
				e.nombre AS expansion,
				e.fecha_salida,
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
				e.nombre AS expansion,
				eq.nombre AS equipo,
				r.nombre AS rareza,
				af.nombre AS afinidad
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

	public function crearCromo($nombre, $posicion, $descripcion, $imagen, $id_expansion, $id_equipo, $id_rareza, $id_afinidad) {
		$sql = "
			INSERT INTO cromos (nombre, posicion, descripcion, imagen, id_expansion, id_equipo, id_rareza, id_afinidad)
			VALUES (:nombre, :posicion, :descripcion, :imagen, :id_expansion, :id_equipo, :id_rareza, :id_afinidad)
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
		]);
		return $this->pdo->lastInsertId();
	}

	public function actualizarCromo($id_cromo, $nombre, $posicion, $descripcion, $imagen, $id_expansion, $id_equipo, $id_rareza, $id_afinidad) {
		$sql = "
			UPDATE cromos SET
				nombre = :nombre,
				posicion = :posicion,
				descripcion = :descripcion,
				imagen = :imagen,
				id_expansion = :id_expansion,
				id_equipo = :id_equipo,
				id_rareza = :id_rareza,
				id_afinidad = :id_afinidad
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
			":id_cromo" => $id_cromo,
		]);
	}

	public function eliminarCromo($id_cromo) {
		$stmt = $this->pdo->prepare("DELETE FROM cromos WHERE id_cromo = :id");
		$stmt->execute([":id" => $id_cromo]);
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

	public function eliminarExpansion($id_expansion) {
		$stmt = $this->pdo->prepare("DELETE FROM expansiones WHERE id_expansion = :id");
		$stmt->execute([":id" => $id_expansion]);
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
	// COLECCIÓN PERSONAL (coleccion.php)
	// ==========================================================

	// Devuelve los cromos que posee un usuario, con filtros opcionales
	public function listarColeccionUsuario($id_usuario, $filtros = []) {
		$sql = "
			SELECT
				col.id_coleccion, col.obtenida, col.bloqueada,
				c.id_cromo, c.nombre, c.posicion, c.imagen,
				eq.id_equipo, eq.nombre AS equipo,
				e.id_expansion, e.nombre AS expansion,
				r.id_rareza, r.nombre AS rareza,
				af.nombre AS afinidad, af.imagen AS afinidad_imagen
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

	// Nº de cromos que posee un usuario (copias incluidas)
	public function contarColeccionUsuario($id_usuario) {
		$stmt = $this->pdo->prepare("SELECT COUNT(*) AS total FROM coleccion WHERE id_usuario = :id");
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
				c.nombre, eq.nombre AS equipo, r.id_rareza, r.nombre AS rareza
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
				c.nombre, eq.nombre AS equipo, r.id_rareza, r.nombre AS rareza
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
				c.id_cromo, c.nombre AS carta, c.imagen,
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
			SELECT col.id_coleccion, c.nombre, eq.nombre AS equipo, r.id_rareza, r.nombre AS rareza
			FROM coleccion col
			INNER JOIN cromos c ON col.id_cromo = c.id_cromo
			INNER JOIN equipos eq ON c.id_equipo = eq.id_equipo
			INNER JOIN rarezas r ON c.id_rareza = r.id_rareza
			WHERE col.id_usuario = :id_usuario
				AND col.bloqueada = 0
				AND col.id_coleccion NOT IN (
					SELECT id_coleccion FROM mercado WHERE activa = 1
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
}

?>