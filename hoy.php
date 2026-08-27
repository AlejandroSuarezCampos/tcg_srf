<?php
/**
 * HOY — la portada del jugador con sesión iniciada.
 *
 * Existe porque hasta ahora quien volvía el martes por la tarde aterrizaba en
 * `landing.php`, una página de captación idéntica con y sin sesión: no veía si
 * tenía sobres pendientes, si un duelo estaba a medias ni si le faltaba un paso
 * para cerrar un objetivo. Tenía que adivinar y navegar.
 *
 * La regla de esta pantalla es UNA SOLA: en tres segundos hay que saber qué
 * hacer ahora. Por eso «Lo siguiente» es una única tarjeta destacada con halo,
 * y no una lista de cinco cosas igual de importantes — que es exactamente el
 * problema que tenía la interfaz anterior.
 */
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';
require_once __DIR__ . '/partials/hoy_prioridad.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = $_SESSION['id_usuario'];

$usuario = $db->obtenerUsuario($id_usuario);
if (!$usuario) {   // sesión de un usuario borrado
    header('Location: logout.php');
    exit;
}

/* ---------------------------------------------------------------------------
   DATOS
   Ocho consultas. La cara es `listarMisionesConProgreso`, que resuelve el
   progreso de cada misión por separado; se paga aquí a propósito porque de
   ella salen DOS cosas que valen el viaje: la insignia de la navegación y,
   sobre todo, el "te falta un paso" — que es el mejor motivo para volver que
   tiene el juego. Si algún día pesa, lo que hay que cachear es esa, no partir
   la pantalla.
   --------------------------------------------------------------------------- */
$totalCromos    = $db->contarCromosTotales($id_usuario);
$totalObtenidas = $db->contarColeccionUsuario($id_usuario);
$pctPlantilla   = $totalCromos > 0 ? (int) round($totalObtenidas / $totalCromos * 100) : 0;

$sobreInicial = $db->sobreInicialPendiente($id_usuario);
$misDuelos    = $db->listarMisDuelos($id_usuario, 8);
$misiones     = $db->listarMisionesConProgreso($id_usuario);
$cadenas      = $db->listarCadenas($id_usuario);
$recientes    = $db->listarColeccionRecienteUsuario($id_usuario, 8);
$mercado      = $db->listarMercadoActivo(['limite' => 4]);
/* Un COUNT sobre un índice: lo único que hace falta para saber si alguien está
   esperando respuesta. La pantalla de intercambios trae las ofertas enteras;
   aquí solo se decide si hay algo que enseñar. */
$intercambiosPend = $db->contarIntercambiosPendientes($id_usuario);

/* Un partido en `en_juego` está literalmente a medias: el rival espera, el
   marcador corre y los minijuegos se resuelven solos con la opción segura si
   nadie mira. Es lo más urgente que puede haber en la cuenta. */
$duelosEnJuego = array_values(array_filter($misDuelos, fn($d) => $d['estado'] === 'en_juego'));

/* Salas propias abiertas esperando rival: no es urgente, pero conviene saber
   que están ahí, porque tienen cartas o monedas apostadas dentro. */
$salasPropias = array_values(array_filter(
    $misDuelos,
    fn($d) => $d['estado'] === 'creado' && (int) $d['id_creador'] === (int) $id_usuario
));

$misionesListas = array_values(array_filter($misiones, fn($m) => $m['completada'] && !$m['reclamada']));

/* La que está más cerca de caer, entre las que aún no se han completado. Es el
   gancho de "te falta un paso" y se calcula por porcentaje, no por diferencia
   absoluta: faltar 1 de 2 no es lo mismo que faltar 1 de 50. */
$misionCerca = null;
$mejorPct = -1;
foreach ($misiones as $m) {
    if ($m['completada'] || $m['reclamada'] || (int) $m['objetivo'] <= 0) continue;
    $pct = $m['progreso'] / $m['objetivo'];
    if ($pct > $mejorPct) { $mejorPct = $pct; $misionCerca = $m; }
}

/* La insignia de la barra de navegación. Se calcula aquí porque los datos ya
   están sobre la mesa: ninguna otra pantalla paga una consulta por esta cifra.
   Cuenta solo lo ACCIONABLE —cosas que se resuelven con un toque—, no todo lo
   que existe: una insignia que nunca baja a cero deja de significar nada. */
$navPendientes = count($duelosEnJuego) + count($misionesListas) + ($sobreInicial ? 1 : 0);

/* Las cifras por modo de la hoja de «Jugar». Salen de datos que esta pantalla
   ya tiene sobre la mesa: ninguna consulta extra por ellas. Solo se cuenta lo
   ACCIONABLE, así que un cero significa de verdad «aquí no hay nada». */
$navEstado = [
    'sobres'   => $sobreInicial ? 1 : 0,
    'duelos'   => count($duelosEnJuego),
    'misiones' => count($misionesListas),
];

/* «Lo siguiente»: una sola acción, elegida por prioridad. La cadena de
   decisión vive en partials/hoy_prioridad.php porque es lo único de esta
   pantalla que puede estar mal sin que se note —siempre devuelve algo— y así
   se puede probar sin base de datos.
   Ver db/pruebas/probar_hoy_prioridad.php */
$duelo = $duelosEnJuego
    ? ['id' => (int) $duelosEnJuego[0]['id_duelo'],
       'rival' => (int) $duelosEnJuego[0]['id_creador'] === (int) $id_usuario
           ? ($duelosEnJuego[0]['rival'] ?? '')
           : $duelosEnJuego[0]['creador']]
    : null;

$siguiente = hoy_siguiente([
    'sobre_inicial'  => (bool) $sobreInicial,
    'duelo_en_juego' => $duelo,
    'intercambios'   => $intercambiosPend,
    'listas'         => $misionesListas,
    'cerca'          => $misionCerca ? $misionCerca + ['ratio' => $mejorPct] : null,
    'faltan'         => max(0, $totalCromos - $totalObtenidas),
]);

/* Las cuatro celdas del bento, cada una con su estado REAL. Un bloque que solo
   dice su nombre no aporta nada que no dijera ya la barra de navegación. */
$bento = [
    ['Sobres', 'sobres.php', 'ph-package', $sobreInicial ? '1' : '—',
     $sobreInicial ? 'de bienvenida, sin abrir' : 'compra uno cuando quieras'],
    ['Duelos', 'duelos.php', 'ph-sword',
     (string) (count($duelosEnJuego) + count($salasPropias)),
     $duelosEnJuego ? 'en juego ahora mismo' : ($salasPropias ? 'sala tuya esperando rival' : 'sin partidos abiertos')],
    ['Objetivos', 'misiones.php', 'ph-target',
     (string) count($misionesListas),
     count($misionesListas) ? 'listos para cobrar' : 'nada que cobrar todavía'],
    ['Cadenas', 'cadenas.php', 'ph-path',
     (string) count($cadenas),
     count($cadenas) === 1 ? 'cadena abierta' : 'cadenas abiertas'],
];

$paginaTitulo = 'Hoy';
$paginaDesc   = 'Tu portada: lo que tienes pendiente y lo que puedes hacer ahora.';
/* Hoja PROPIA de esta pantalla, no una sección más en la monolítica: es el
   patrón que sustituye a servir las reglas de las diecisiete en cada carga. */
$cssExtra     = ['assets/css/hoy.css'];
include __DIR__ . '/partials/head.php';

$activePage = 'hoy';
include __DIR__ . '/navbar.php';
?>

<main id="contenido" class="hoy">

  <?php /* ===== MARCADOR =====
           Nombre y tres cifras reales. Nada de "racha": la base de datos no
           guarda ninguna, e inventar una métrica para llenar un hueco es
           exactamente cómo se pierde la confianza en un panel. */ ?>
  <section class="hoy-marcador">
    <div class="rescoldo" aria-hidden="true"></div>
    <div class="trama" aria-hidden="true"></div>

    <div class="wrap hoy-marcador-cuerpo">
      <?php
      /* La fecha a mano y no con strftime(): está obsoleta desde PHP 8.1, y
         IntlDateFormatter necesita la extensión intl, que en el hosting no está
         garantizada. Dos arrays resuelven el único formato que se usa. */
      $DIAS  = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
      $MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
      $hoyTexto = $DIAS[(int) date('w')] . ' ' . (int) date('j') . ' de ' . $MESES[(int) date('n') - 1];
      ?>
      <p class="label sube">Hoy · <?= htmlspecialchars($hoyTexto) ?></p>
      <h1 class="hoy-saludo" data-revela="160"><?= htmlspecialchars($usuario['nombre']) ?></h1>

      <dl class="hoy-cifras escalona">
        <div class="hoy-cifra">
          <dt class="rot">Monedas</dt>
          <dd class="cif" data-cifra="<?= (int) $usuario['monedas'] ?>"><?= number_format((int) $usuario['monedas'], 0, ',', '.') ?></dd>
        </div>
        <div class="hoy-cifra">
          <dt class="rot">Plantilla</dt>
          <dd class="cif" data-cifra="<?= $pctPlantilla ?>" data-cifra-sufijo="&#37;"><?= $pctPlantilla ?>&#37;</dd>
        </div>
        <div class="hoy-cifra">
          <dt class="rot">Fichas</dt>
          <dd class="cif" data-cifra="<?= $totalObtenidas ?>"><?= number_format($totalObtenidas, 0, ',', '.') ?></dd>
        </div>
      </dl>

      <div class="barra-carril hoy-progreso escalona"
           role="progressbar" aria-valuenow="<?= $pctPlantilla ?>" aria-valuemin="0" aria-valuemax="100"
           aria-label="Progreso de tu plantilla">
        <i style="--parte:<?= $pctPlantilla / 100 ?>"></i>
      </div>
      <p class="t-body-sm t-dim hoy-progreso-pie">
        <span class="num"><?= number_format($totalObtenidas, 0, ',', '.') ?></span> de
        <span class="num"><?= number_format($totalCromos, 0, ',', '.') ?></span> fichas de la liga
      </p>
    </div>
  </section>

  <?php /* ===== LO SIGUIENTE =====
           UNA tarjeta, con halo y filo. Es el único elemento de la pantalla que
           los lleva: si dos cosas gritan, no grita ninguna. */ ?>
  <section class="wrap hoy-seccion">
    <a class="siguiente filo es-<?= $siguiente['tono'] ?> sube" href="<?= htmlspecialchars($siguiente['url']) ?>">
      <span class="siguiente-ico"><i class="ph <?= $siguiente['icono'] ?>" aria-hidden="true"></i></span>
      <span class="siguiente-cuerpo">
        <span class="label siguiente-rotulo"><?= htmlspecialchars($siguiente['rotulo']) ?></span>
        <span class="siguiente-titulo"><?= htmlspecialchars($siguiente['titulo']) ?></span>
        <span class="siguiente-texto"><?= htmlspecialchars($siguiente['texto']) ?></span>
      </span>
      <span class="siguiente-accion">
        <?= htmlspecialchars($siguiente['accion']) ?>
        <i class="ph ph-arrow-right" aria-hidden="true"></i>
      </span>
    </a>
  </section>

  <?php /* ===== BENTO ===== */ ?>
  <section class="wrap hoy-seccion">
    <h2 class="hoy-titulo">A qué juegas</h2>
    <div class="hoy-bento escalona">
      <?php foreach ($bento as $i => [$nombre, $url, $icono, $cifra, $pie]): ?>
        <a class="hoy-celda<?= $i === 0 ? ' hoy-celda--destacada' : '' ?>" href="<?= $url ?>">
          <span class="hoy-celda-alto">
            <i class="ph <?= $icono ?>" aria-hidden="true"></i>
            <span class="rot"><?= htmlspecialchars($nombre) ?></span>
          </span>
          <span class="hoy-celda-bajo">
            <span class="cif"><?= htmlspecialchars($cifra) ?></span>
            <span class="hoy-celda-pie"><?= htmlspecialchars($pie) ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <?php /* ===== ÚLTIMAS FICHAS ===== */ ?>
  <section class="wrap hoy-seccion">
    <div class="hoy-cabecera">
      <h2 class="hoy-titulo">Lo último que has fichado</h2>
      <a class="hoy-mas" href="plantilla.php">Ver la plantilla <i class="ph ph-arrow-right" aria-hidden="true"></i></a>
    </div>

    <?php if (empty($recientes)): ?>
      <div class="vacio">
        <span class="vacio-ico"><i class="ph ph-cards" aria-hidden="true"></i></span>
        <h3>Tu plantilla está vacía</h3>
        <p>Todavía no tienes ninguna ficha. El primer sobre lo pone la casa.</p>
        <a class="btn btn-primary" href="sobres.php">Abrir el primero</a>
      </div>
    <?php else: ?>
      <?php /* Tira horizontal y no rejilla: en móvil una rejilla de ocho cartas
               empuja todo lo de abajo fuera de la pantalla, y esto es un vistazo,
               no la pantalla de colección. */ ?>
      <ul class="hoy-tira-cartas" role="list">
        <?php foreach ($recientes as $c): ?>
          <li><?php render_carta($c, ['href' => 'plantilla.php']); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <?php /* ===== MOVIMIENTO EN LA LIGA =====
           Prueba social: lo que otros están vendiendo ahora mismo. Es lo que
           convierte una cuenta en solitario en un juego con gente dentro. */ ?>
  <?php if (!empty($mercado)): ?>
  <section class="wrap hoy-seccion">
    <div class="hoy-cabecera">
      <h2 class="hoy-titulo">Movimiento en la liga</h2>
      <a class="hoy-mas" href="mercado.php">Ir al mercado <i class="ph ph-arrow-right" aria-hidden="true"></i></a>
    </div>

    <ul class="hoy-mercado escalona" role="list">
      <?php foreach ($mercado as $a): ?>
        <li>
          <a class="hoy-anuncio" href="mercado.php">
            <span class="hoy-anuncio-rz rz" data-rareza="<?= (int) $a['id_rareza'] ?>">
              <?= htmlspecialchars($a['rareza']) ?>
            </span>
            <span class="hoy-anuncio-carta"><?= htmlspecialchars($a['carta']) ?></span>
            <span class="hoy-anuncio-quien">vende <?= htmlspecialchars($a['vendedor']) ?></span>
            <span class="hoy-anuncio-precio num"><?= number_format((int) $a['precio'], 0, ',', '.') ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

</body>
</html>
