<?php
/**
 * JUGAR — el centro de mando de los cuatro modos.
 *
 * Responde a una pregunta distinta de la de `hoy.php`, y la diferencia importa
 * para que no acaben siendo la misma pantalla dos veces:
 *
 *   hoy.php    → «¿qué hago AHORA?»          una acción, elegida por prioridad
 *   jugar.php  → «¿puedo jugar, y a qué?»     disposición y oportunidades
 *
 * De ahí que lo primero de esta pantalla NO sea un modo de juego sino tu
 * alineación titular: sin once no se puede entrar en un duelo ni en una cadena,
 * y ese bloqueo no aparecía en ninguna parte del producto. Se descubría al
 * llegar a `duelos.php` y encontrarse el botón apagado.
 *
 * SUSTITUYE a la hoja desplegable que la barra abría en el bloque 2. Aquella
 * hoja se justificó diciendo que llevaba a cualquier modo «en un toque frente a
 * los dos de una pantalla intermedia», y eso era falso: abrir la hoja ya era el
 * primer toque. Costaban dos las dos. Con el mismo coste, esta enseña estado y
 * la hoja no enseñaba nada.
 */
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/partials/jugar_disposicion.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = $_SESSION['id_usuario'];

$usuario = $db->obtenerUsuario($id_usuario);
if (!$usuario) { header('Location: logout.php'); exit; }

/* ---------------------------------------------------------------------------
   DATOS
   Nueve consultas. Se deja fuera a propósito el mapa de cada cadena
   (`mapaCadena` + `requisitosPendientes` + `cadenaCompletada` son TRES por
   cadena, quince en total): ese detalle es lo que `cadenas.php` va a enseñar de
   todas formas al entrar, y pagarlo aquí para pintar una línea de resumen
   sería el tipo de coste que este rediseño lleva cuatro bloques quitando.
   --------------------------------------------------------------------------- */
$titular   = $db->obtenerMazoTitular($id_usuario);
$mazos     = $db->listarMazosUsuario($id_usuario);
$sobres    = $db->listarSobresActivos();
$sobreIni  = $db->sobreInicialPendiente($id_usuario);
$misDuelos = $db->listarMisDuelos($id_usuario, 8);
$abiertos  = $db->listarDuelosAbiertos($id_usuario);
$misiones  = $db->listarMisionesConProgreso($id_usuario);
$cadenas   = $db->listarCadenas($id_usuario);

$enJuego     = array_values(array_filter($misDuelos, fn($d) => $d['estado'] === 'en_juego'));
$salaPropia  = array_values(array_filter(
    $misDuelos,
    fn($d) => $d['estado'] === 'creado' && (int) $d['id_creador'] === (int) $id_usuario
));
$cobrables   = array_values(array_filter($misiones, fn($m) => $m['completada'] && !$m['reclamada']));

/* Progreso agregado de objetivos: cuántos de los que hay están cerrados. Es
   una barra, no una cuenta, porque «3 de 8» dice más que «3». */
$totalMisiones = count($misiones);
$cerradas = count(array_filter($misiones, fn($m) => $m['completada']));
$pctMisiones = $totalMisiones > 0 ? (int) round($cerradas / $totalMisiones * 100) : 0;

$navPendientes = count($enJuego) + count($cobrables) + ($sobreIni ? 1 : 0);

/* Cifras por modo para la hoja de la barra: los datos ya están cargados. */
$navEstado = [
    'sobres'   => $sobreIni ? 1 : 0,
    'duelos'   => count($enJuego) ?: count($abiertos),
    'misiones' => count($cobrables),
];

/* Disposición: ¿puede salir al campo? La cadena vive en
   partials/jugar_disposicion.php porque puede estar mal sin fallar —siempre
   devuelve un estado— y así se prueba sin base de datos.
   Ver db/pruebas/probar_jugar_disposicion.php */
$tamano = Tcg::MAZO_TAMANO;
$listo  = jugar_disposicion($titular, $mazos, $tamano);

/* ---------------------------------------------------------------------------
   LOS CUATRO MODOS
   Cada uno con su estado real y su gancho. Un bloque que solo dice su nombre
   no aporta nada que no dijera ya la barra de navegación.
   --------------------------------------------------------------------------- */
$modos = [
    [
        'clave' => 'sobres', 'url' => 'sobres.php', 'icono' => 'ph-package',
        'nombre' => 'Sobres', 'destacado' => true,
        'que' => 'Ábrelos y mira qué cae',
        'cifra' => $sobreIni ? '1' : (string) count($sobres),
        'pie'   => $sobreIni ? 'de bienvenida, gratis' : (count($sobres) === 1 ? 'sobre a la venta' : 'sobres a la venta'),
        'urgente' => (bool) $sobreIni,
    ],
    [
        'clave' => 'duelos', 'url' => 'duelos.php', 'icono' => 'ph-sword',
        'nombre' => 'Duelos', 'que' => 'Reta a alguien o entra en una sala',
        'cifra' => (string) (count($enJuego) ?: count($abiertos)),
        'pie'   => $enJuego
            ? (count($enJuego) === 1 ? 'partido tuyo en juego' : 'partidos tuyos en juego')
            : ($abiertos
                ? (count($abiertos) === 1 ? 'sala abierta esperando rival' : 'salas abiertas esperando rival')
                : ($salaPropia ? 'tu sala espera rival' : 'nadie esperando ahora mismo')),
        'urgente' => (bool) $enJuego,
    ],
    [
        'clave' => 'misiones', 'url' => 'misiones.php', 'icono' => 'ph-target',
        'nombre' => 'Objetivos', 'que' => 'Lo que te queda por cerrar',
        'cifra' => $cerradas . '/' . $totalMisiones,
        'pie'   => $cobrables
            ? (count($cobrables) === 1 ? '1 sin cobrar' : count($cobrables) . ' sin cobrar')
            : 'nada que cobrar todavía',
        'barra' => $pctMisiones,
        'urgente' => (bool) $cobrables,
    ],
    [
        'clave' => 'cadenas', 'url' => 'cadenas.php', 'icono' => 'ph-path',
        'nombre' => 'Cadenas', 'que' => 'Encadena partidos y sube de nodo',
        'cifra' => (string) count($cadenas),
        'pie'   => count($cadenas) === 0 ? 'ninguna abierta ahora'
                 : (count($cadenas) === 1 ? 'cadena abierta' : 'cadenas abiertas'),
    ],
];

$paginaTitulo = 'Jugar';
$paginaDesc   = 'Sobres, duelos, objetivos y cadenas: a qué puedes jugar ahora mismo.';
$cssExtra     = ['assets/css/jugar.css'];
include __DIR__ . '/partials/head.php';

$activePage = 'jugar';
include __DIR__ . '/navbar.php';
?>

<main id="contenido" class="jugar">

  <header class="jg-cabecera">
    <div class="rescoldo" aria-hidden="true"></div>
    <div class="trama" aria-hidden="true"></div>
    <div class="wrap jg-cabecera-cuerpo">
      <p class="label sube">Jugar</p>
      <h1 class="jg-titulo" data-revela="160">A qué juegas</h1>
    </div>
  </header>

  <div class="wrap jg-cuerpo">

    <?php /* ===== DISPOSICIÓN =====
             Va PRIMERO y no en el bento porque es la puerta: sin once completo,
             dos de los cuatro modos están cerrados. Antes esto no se decía en
             ningún sitio — te enterabas al llegar a duelos y ver el botón
             apagado sin explicación. */ ?>
    <a class="jg-listo es-<?= $listo['estado'] ?> sube" href="mazos.php">
      <span class="jg-listo-ico">
        <i class="ph <?= $listo['estado'] === 'si' ? 'ph-check-circle' : 'ph-list-checks' ?>" aria-hidden="true"></i>
      </span>
      <span class="jg-listo-cuerpo">
        <span class="label jg-listo-rotulo"><?= htmlspecialchars($listo['rotulo']) ?></span>
        <span class="jg-listo-titulo"><?= htmlspecialchars($listo['titulo']) ?></span>
        <span class="jg-listo-texto"><?= htmlspecialchars($listo['texto']) ?></span>
      </span>
      <span class="jg-listo-accion">
        <?= htmlspecialchars($listo['accion']) ?>
        <i class="ph ph-arrow-right" aria-hidden="true"></i>
      </span>
    </a>

    <?php /* ===== LOS CUATRO MODOS ===== */ ?>
    <h2 class="jg-seccion-titulo">Los cuatro modos</h2>
    <div class="jg-bento escalona">
      <?php foreach ($modos as $m): ?>
        <a class="jg-modo<?= !empty($m['destacado']) ? ' jg-modo--destacado' : '' ?><?= !empty($m['urgente']) ? ' es-urgente' : '' ?>"
           href="<?= htmlspecialchars($m['url']) ?>">
          <span class="jg-modo-placa"><i class="ph <?= $m['icono'] ?>" aria-hidden="true"></i></span>

          <span class="jg-modo-texto">
            <span class="jg-modo-nombre"><?= htmlspecialchars($m['nombre']) ?></span>
            <span class="jg-modo-que"><?= htmlspecialchars($m['que']) ?></span>
          </span>

          <span class="jg-modo-estado">
            <span class="cif"><?= htmlspecialchars($m['cifra']) ?></span>
            <span class="jg-modo-pie"><?= htmlspecialchars($m['pie']) ?></span>
          </span>

          <?php if (isset($m['barra'])): ?>
            <span class="barra-carril jg-modo-barra"
                  role="progressbar" aria-valuenow="<?= $m['barra'] ?>" aria-valuemin="0" aria-valuemax="100"
                  aria-label="Objetivos cerrados">
              <i style="--parte:<?= $m['barra'] / 100 ?>"></i>
            </span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php /* ===== SALAS ABIERTAS =====
             La oportunidad más directa que tiene el juego: alguien está
             esperando rival AHORA. No estaba en ninguna portada — había que
             entrar a duelos para descubrirlo. */ ?>
    <?php if ($abiertos): ?>
      <div class="jg-cabecera-fila">
        <h2 class="jg-seccion-titulo">Esperando rival</h2>
        <a class="jg-mas" href="duelos.php">Ver todas <i class="ph ph-arrow-right" aria-hidden="true"></i></a>
      </div>
      <ul class="jg-salas escalona" role="list">
        <?php foreach (array_slice($abiertos, 0, 4) as $d): ?>
          <li>
            <a class="jg-sala" href="duelos.php">
              <span class="jg-sala-quien"><?= htmlspecialchars($d['creador']) ?></span>
              <span class="jg-sala-apuesta">
                <?php if (!empty($d['rareza_apuesta'])): ?>
                  apuesta <?= htmlspecialchars($d['rareza_apuesta']) ?>
                <?php elseif (!empty($d['monedas'])): ?>
                  <span class="num"><?= number_format((int) $d['monedas'], 0, ',', '.') ?></span> monedas
                <?php else: ?>
                  amistoso
                <?php endif; ?>
              </span>
              <span class="jg-sala-entrar">Entrar <i class="ph ph-arrow-right" aria-hidden="true"></i></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php /* ===== TUS ALINEACIONES ===== */ ?>
    <div class="jg-cabecera-fila">
      <h2 class="jg-seccion-titulo">Tus alineaciones</h2>
      <a class="jg-mas" href="mazos.php">Gestionar <i class="ph ph-arrow-right" aria-hidden="true"></i></a>
    </div>

    <?php if (!$mazos): ?>
      <div class="vacio">
        <span class="vacio-ico"><i class="ph ph-list-checks" aria-hidden="true"></i></span>
        <h3>Todavía no has montado ninguna</h3>
        <p>Una alineación son once jugadores en una formación. Es lo único que hace falta para empezar a competir.</p>
        <a class="btn btn-primary" href="mazos.php">Montar la primera</a>
      </div>
    <?php else: ?>
      <ul class="jg-mazos escalona" role="list">
        <?php foreach ($mazos as $m): ?>
          <?php
          $completo = (int) $m['cartas'] >= $tamano;
          $form = Tcg::FORMACIONES[$m['formacion']]['nombre'] ?? $m['formacion'];
          ?>
          <li>
            <a class="jg-mazo<?= (int) $m['titular'] === 1 ? ' es-titular' : '' ?>" href="mazos.php">
              <span class="jg-mazo-alto">
                <span class="jg-mazo-nombre"><?= htmlspecialchars($m['nombre']) ?></span>
                <?php if ((int) $m['titular'] === 1): ?>
                  <span class="jg-mazo-insignia">Titular</span>
                <?php endif; ?>
              </span>
              <span class="jg-mazo-bajo">
                <span class="jg-mazo-form"><?= htmlspecialchars($form) ?></span>
                <span class="jg-mazo-cartas<?= $completo ? '' : ' es-incompleto' ?>">
                  <i class="ph <?= $completo ? 'ph-check' : 'ph-warning' ?>" aria-hidden="true"></i>
                  <span class="num"><?= (int) $m['cartas'] ?></span>/<span class="num"><?= $tamano ?></span>
                </span>
              </span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

</body>
</html>
