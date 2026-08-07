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

	public function crearCromo($nombre, $posicion, $descripcion, $imagen, $id_expansion, $id_equipo, $id_rareza, $id_afinidad, $ataque = 0, $defensa = 0, $tecnica = 0) {
		$sql = "
			INSERT INTO cromos (nombre, posicion, descripcion, imagen, id_expansion, id_equipo, id_rareza, id_afinidad, ataque, defensa, tecnica)
			VALUES (:nombre, :posicion, :descripcion, :imagen, :id_expansion, :id_equipo, :id_rareza, :id_afinidad, :ataque, :defensa, :tecnica)
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
				c.nombre, c.imagen, eq.nombre AS equipo, r.id_rareza, r.nombre AS rareza
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
				c.nombre, c.imagen, eq.nombre AS equipo, r.id_rareza, r.nombre AS rareza
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
					c.id_cromo, c.nombre, c.imagen,
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
	// "interior" es el fondo/base que se ve al levantar la tapa (§Fase 3 del
	// prompt de sobres 3D): la fila y1024-1024 del lienzo queda libre a
	// propósito para el aviso LEEME (ver generarGuiaPlantilla()).
	const ZONAS_CAJA = [
		"front"    => ["x" => 0,   "y" => 0,   "w" => 512, "h" => 512],
		"lid"      => ["x" => 512, "y" => 0,   "w" => 512, "h" => 256],
		"top"      => ["x" => 512, "y" => 256, "w" => 512, "h" => 256],
		"side"     => ["x" => 0,   "y" => 512, "w" => 512, "h" => 256],
		"interior" => ["x" => 512, "y" => 512, "w" => 512, "h" => 256],
	];
	const LIENZO_CAJA = ["w" => 1024, "h" => 1024];

	const ZONAS_SOBRE = [
		"frente"  => ["x" => 0,   "y" => 0, "w" => 512, "h" => 512],
		"reverso" => ["x" => 512, "y" => 0, "w" => 512, "h" => 512],
	];
	const LIENZO_SOBRE = ["w" => 1024, "h" => 512];

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

		// hueco libre del lienzo (para caja: la fila y1024 entera; para sobre:
		// la franja baja de su propio lienzo): aviso LEEME
		$lineasLeeme = [
			"LEEME",
			"Sustituye cada zona por tu arte real SIN mover ni redimensionarla:",
			"el recorte del servidor usa estas mismas coordenadas exactas.",
			"Oculta o borra esta capa de guias antes de exportar.",
			"Exporta como PNG plano de " . $lienzo["w"] . "x" . $lienzo["h"] . "px,",
			"igual que este archivo.",
		];
		$xLeeme = 14;
		$yLeeme = self::esTipoCaja($tipo) ? 768 + 14 : $lienzo["h"] - 110;
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

	/**
	 * Marcador de goles. PROVISIONAL: la especificación (§9.3) lo deja para un
	 * documento propio. Cumple el contrato que sí fija:
	 *   · nunca contradice al ganador ya sorteado,
	 *   · se deriva del mismo valor de sorteo, no de otra tirada aparte,
	 *   · tiene en cuenta las categorías, no solo quién ganó.
	 */
	private function marcadorDuelo($fuerzaGanador, $fuerzaPerdedor, $sorteo) {
		// Cuánto pesó el ataque del ganador frente a lo que tenía enfrente.
		$muro = max(1, $fuerzaPerdedor["DF"] + $fuerzaPerdedor["POR"]);
		$dominio = $fuerzaGanador["DC"] / $muro;

		// Dos derivados deterministas del mismo sorteo, para no tirar otra vez.
		$r1 = fmod($sorteo * 1000, 1);
		$r2 = fmod($sorteo * 7919, 1);

		$golesGanador  = (int) max(1, min(6, round(1 + $dominio * 3 + $r1 * 1.5)));
		$muroGanador   = max(1, $fuerzaGanador["DF"] + $fuerzaGanador["POR"]);
		$dominioRival  = $fuerzaPerdedor["DC"] / $muroGanador;
		$golesPerdedor = (int) max(0, min($golesGanador - 1, round($dominioRival * 3 + $r2)));

		return [$golesGanador, $golesPerdedor];
	}

	/**
	 * Resuelve un duelo aceptado: calcula fuerzas, aplica la curva Elo, sortea,
	 * mueve las apuestas y guarda toda la trazabilidad.
	 *
	 * Las capas 2 (rasgos) y 3 (aumento) todavía no existen, así que el TOTAL
	 * final coincide hoy con el bruto. El punto donde se enganchan está marcado
	 * abajo; la curva de resolución no cambiará cuando lleguen.
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
			$ganaCreador = $sorteo < $p;

			$idGanador  = $ganaCreador ? $idCreador : $idRival;
			$idPerdedor = $ganaCreador ? $idRival : $idCreador;

			// El PvP conserva su marcador; las cadenas usan el suyo, que sí puede
			// dejar la portería a cero y por tanto permite el rango S.
			[$golesGanador, $golesPerdedor] = $esPve
				? $this->marcadorCadena(
					$ganaCreador ? $fuerzaCreador : $fuerzaRival,
					$ganaCreador ? $fuerzaRival : $fuerzaCreador,
					$ganaCreador ? $formCreador : $formRival,
					$ganaCreador ? $formRival : $formCreador
				)
				: $this->marcadorDuelo(
					$ganaCreador ? $fuerzaCreador : $fuerzaRival,
					$ganaCreador ? $fuerzaRival : $fuerzaCreador,
					$sorteo
				);

			// En una cadena el jugador es siempre el creador, así que su rango se
			// lee de los goles del creador.
			$golesCreador = $ganaCreador ? $golesGanador : $golesPerdedor;
			$golesRival   = $ganaCreador ? $golesPerdedor : $golesGanador;
			$rango = $esPve ? $this->rangoPartido($golesCreador, $golesRival) : null;

			// --- mover las apuestas ---
			if ($duelo["tipo_apuesta"] === "monedas") {
				// El bote son las dos apuestas, ya retenidas al entrar.
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

			$this->pdo->prepare("
				UPDATE duelos SET
					estado = 'resuelto',
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
					rango = :rango,
					resuelto = NOW()
				WHERE id_duelo = :id_duelo
			")->execute([
				":rango" => $rango,
				":afc" => $composCreador["afinidad_dom"], ":afr" => $composRival["afinidad_dom"],
				":cbc" => $cicloCreador,                  ":cbr" => $cicloRival,
				":mcc" => $composCreador["malus"],        ":mcr" => $composRival["malus"],
				":tc"  => $composCreador["tension_nivel"], ":tr" => $composRival["tension_nivel"],
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

			// Progreso y recompensas de cadena: dentro de la misma transacción
			// que el resultado, para que no pueda quedar un duelo resuelto
			// cuyo nodo no conste como jugado o cuyas monedas no se cobraran.
			if ($esPve && $duelo["id_nodo"]) {
				$gano = $idGanador === $idCreador;
				$vecesPrevias = $this->registrarProgresoNodo(
					$idCreador,                    // en una cadena el jugador es siempre el creador
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

	/** Lo que se entregó al reclamar un cofre concreto. */
	public function listarDropsCofre($id_nodo, $id_usuario) {
		$stmt = $this->pdo->prepare("
			SELECT d.*, c.nombre AS cromo_nombre, c.cupo_numerado
			FROM cadena_drops d
			LEFT JOIN cromos c ON c.id_cromo = d.id_cromo
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

			// Los cofres no puntúan: se reclaman, no se juegan. $rango = null
			// hace que otorgarLootNodo() solo considere las filas sin rango
			// mínimo, que es la única clase de loot que tiene sentido aquí.
			$loot = $this->otorgarLootNodo($id_nodo, $id_usuario, null);

			$this->pdo->commit();
			return ["ok" => true, "error" => null, "formacion" => $formacion, "loot" => $loot];
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
	 * Sustituye a marcadorDuelo() SOLO en PvE; el PvP se queda con el suyo por
	 * decisión de Alejandro. El motivo de existir es concreto: con la fórmula
	 * del PvP la portería a cero era imposible (0 de 200.000 simulaciones), y
	 * sin portería a cero el rango S no se puede conseguir nunca.
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

	// Progreso actual de un usuario para un TIPO de misión. Nunca se guarda en
	// un contador propio (misiones_progreso solo registra el reclamo): se
	// deriva siempre de las consultas que ya existen, para no tener una
	// segunda fuente de verdad que se pueda desincronizar.
	private function progresoMision($tipo, $id_usuario) {
		switch ($tipo) {
			case "cartas_distintas":
				return $this->contarColeccionUsuario($id_usuario);
			case "copias_totales":
				$stmt = $this->pdo->prepare("SELECT COUNT(*) AS total FROM coleccion WHERE id_usuario = :id");
				$stmt->execute([":id" => $id_usuario]);
				return (int) $stmt->fetch(PDO::FETCH_ASSOC)["total"];
			case "expansiones_completas":
				return $this->contarExpansionesCompletas($id_usuario);
			case "mazos_creados":
				$stmt = $this->pdo->prepare("SELECT COUNT(*) AS total FROM mazos WHERE id_usuario = :id");
				$stmt->execute([":id" => $id_usuario]);
				return (int) $stmt->fetch(PDO::FETCH_ASSOC)["total"];
			case "duelos_jugados":
				$stmt = $this->pdo->prepare("
					SELECT COUNT(*) AS total FROM duelos
					WHERE estado = 'resuelto' AND (id_creador = :id OR id_rival = :id)
				");
				$stmt->execute([":id" => $id_usuario]);
				return (int) $stmt->fetch(PDO::FETCH_ASSOC)["total"];
			case "duelos_ganados":
				$stmt = $this->pdo->prepare("SELECT COUNT(*) AS total FROM duelos WHERE id_ganador = :id");
				$stmt->execute([":id" => $id_usuario]);
				return (int) $stmt->fetch(PDO::FETCH_ASSOC)["total"];
		}
		return 0;
	}

	// Misiones activas con el progreso YA calculado y si están reclamadas.
	// `periodo = ''` es el valor fijo de las misiones de una sola vez (ver 005);
	// una misión reclamada no vuelve a recalcular progreso, se queda en el
	// objetivo para no hacer trabajo de más.
	public function listarMisionesConProgreso($id_usuario) {
		$stmt = $this->pdo->prepare("
			SELECT m.*, mp.fecha_reclamada
			FROM misiones m
			LEFT JOIN misiones_progreso mp
				ON mp.id_mision = m.id_mision AND mp.id_usuario = :id_usuario AND mp.periodo = ''
			WHERE m.activo = 1
			ORDER BY m.id_mision
		");
		$stmt->execute([":id_usuario" => $id_usuario]);
		$misiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach ($misiones as &$m) {
			$m["reclamada"] = $m["fecha_reclamada"] !== null;
			$m["progreso"]  = $m["reclamada"] ? (int) $m["objetivo"] : $this->progresoMision($m["tipo"], $id_usuario);
			$m["completada"] = $m["progreso"] >= (int) $m["objetivo"];
		}
		return $misiones;
	}

	// Reclamo manual: comprueba objetivo y que no se haya reclamado ya antes de
	// pagar, bloqueando las filas implicadas (mismo patrón que comprarAnuncio()
	// y canjearCodigo()) para que dos clics simultáneos no paguen dos veces.
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

			$stmtYa = $this->pdo->prepare("
				SELECT 1 FROM misiones_progreso
				WHERE id_mision = :id_mision AND id_usuario = :id_usuario AND periodo = ''
				FOR UPDATE
			");
			$stmtYa->execute([":id_mision" => $id_mision, ":id_usuario" => $id_usuario]);
			if ($stmtYa->fetch()) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "Ya has reclamado esta misión."];
			}

			if ($this->progresoMision($mision["tipo"], $id_usuario) < (int) $mision["objetivo"]) {
				$this->pdo->rollBack();
				return ["ok" => false, "error" => "Todavía no cumples el objetivo."];
			}

			$this->pdo->prepare("
				INSERT INTO misiones_progreso (id_usuario, id_mision, periodo, fecha_reclamada)
				VALUES (:id_usuario, :id_mision, '', NOW())
			")->execute([":id_usuario" => $id_usuario, ":id_mision" => $id_mision]);

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
		$translit = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
		if ($translit !== false) { $s = $translit; }
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
		if ($mejor !== null && $mejorPct >= 75.0) {
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

	private const IMPORT_BASE_TOTAL = ['comun' => 165, 'poco_comun' => 190, 'raro' => 215, 'epico' => 240];
	private const IMPORT_RAREZA_CLAVE = [1 => 'comun', 2 => 'poco_comun', 3 => 'raro', 4 => 'epico'];
	private const IMPORT_SPLIT_POSICION = [
		'POR' => ['ataque' => 0.20, 'defensa' => 0.45, 'tecnica' => 0.35],
		'DF'  => ['ataque' => 0.25, 'defensa' => 0.45, 'tecnica' => 0.30],
		'MC'  => ['ataque' => 0.33, 'defensa' => 0.30, 'tecnica' => 0.37],
		'DC'  => ['ataque' => 0.45, 'defensa' => 0.25, 'tecnica' => 0.30],
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
		if (!isset(self::IMPORT_SPLIT_POSICION[$posicion])) {
			return ['ataque' => 0, 'defensa' => 0, 'tecnica' => 0]; // ENT/GER/ESCUDO
		}
		$clave = self::IMPORT_RAREZA_CLAVE[$idRareza] ?? 'comun';
		$total = self::IMPORT_BASE_TOTAL[$clave] * (mt_rand(92, 108) / 100);
		$split = self::IMPORT_SPLIT_POSICION[$posicion];
		return [
			'ataque'  => max(1, min(99, (int) round($total * $split['ataque']))),
			'defensa' => max(1, min(99, (int) round($total * $split['defensa']))),
			'tecnica' => max(1, min(99, (int) round($total * $split['tecnica']))),
		];
	}
}

?>