<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = (int) $_SESSION['id_usuario'];
$id_duelo   = (int) ($_GET['id'] ?? 0);

$duelo = $db->obtenerDuelo($id_duelo, $id_usuario);
if (!$duelo) {
    header('Location: duelos.php');
    exit;
}

// Cancelar la propia sala mientras nadie ha entrado.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cancelar') {
    $db->cancelarDuelo($id_duelo, $id_usuario);
    header('Location: duelos.php');
    exit;
}

// Elegir aumento. Es definitivo: elegirAumento() ignora un segundo intento.
//
// Se vuelve con `nuevo=1` para pedir la ceremonia de partido. En un duelo de
// cadena es la única forma de que se vea: el rival es el bot y ya eligió al
// crearse el partido, así que esta elección cierra la fase y el duelo se
// resuelve en la carga siguiente, sin que llegue a correr el sondeo de
// duelo.js que en PvP es quien añade ese parámetro.
//
// En PvP también hacía falta: quien elegía SEGUNDO resolvía el duelo en esta
// misma carga y se quedaba sin ceremonia, porque ya no había sondeo que la
// pidiera. Si el rival aún no ha elegido, el parámetro no molesta: el modal
// solo se renderiza con el duelo resuelto.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'elegir_aumento') {
    $db->elegirAumento($id_duelo, $id_usuario, (int) ($_POST['opcion'] ?? 0));
    header('Location: duelo.php?id=' . $id_duelo . '&nuevo=1');
    exit;
}

// Avance del duelo. Todo perezoso: no hay cron, así que cada visita empuja el
// duelo tan lejos como pueda llegar.
//   · vencido el plazo, se elige por quien no eligió;
//   · con ambas elecciones cerradas, se resuelve.
if ($duelo['estado'] === 'aumento_pendiente') {
    $db->cerrarFaseAumento($id_duelo);
    $duelo = $db->obtenerDuelo($id_duelo, $id_usuario);
}

if (in_array($duelo['estado'], ['aceptado', 'listo_para_resolver'], true)) {
    $db->resolverDuelo($id_duelo);
    $duelo = $db->obtenerDuelo($id_duelo, $id_usuario);
}

// Y un partido que ya ha llegado al minuto final se CIERRA: se decide el
// ganador con el marcador que ha quedado y se entrega el bote. Está aquí además
// de en el sondeo porque así basta con que uno de los dos vuelva a mirar el
// duelo para que se liquide, aunque los dos cerraran la pestaña a mitad.
if ($duelo['estado'] === 'en_juego') {
    $db->cerrarPartidoSiToca($id_duelo);
    $duelo = $db->obtenerDuelo($id_duelo, $id_usuario);
}

$soyCreador = (int) $duelo['id_creador'] === $id_usuario;
$idRival    = $soyCreador ? (int) $duelo['id_rival'] : (int) $duelo['id_creador'];
$nombreYo   = $soyCreador ? $duelo['creador'] : $duelo['rival'];
$nombreOtro = $soyCreador ? ($duelo['rival'] ?? 'Esperando rival') : $duelo['creador'];

$esperando = $duelo['estado'] === 'creado';
$eligiendo = $duelo['estado'] === 'aumento_pendiente';

/* DOS COSAS DISTINTAS, y confundirlas es el error fácil de esta pantalla:
     · $jugado   — el partido EXISTE: alineaciones y compos congeladas, sorteo
       escrito, hay marcador que mirar. Vale también mientras se juega.
     · $resuelto — el duelo está DECIDIDO: hay ganador y el bote ya se entregó.
   Durante `en_juego` la primera es cierta y la segunda no, así que todo lo que
   dependa de quién ganó tiene que colgar de $resuelto, nunca de $jugado. */
$enJuego  = $duelo['estado'] === 'en_juego';
$resuelto = $duelo['estado'] === 'resuelto';
$jugado   = $enJuego || $resuelto;
$porTanda = $resuelto && !empty($duelo['resuelto_por_tanda']);

/* La ceremonia (el modal del partido) se hace al llegar recién montado el
   duelo... y SIEMPRE que el partido siga en juego. Esto último es lo que hace
   que recargar a mitad de encuentro vuelva a meterte en el partido en vez de
   dejarte fuera mirando una pantalla de resultado que todavía no existe: el
   minuto lo manda el servidor, así que reincorporarse es sumarse donde va. */
$ceremonia = $enJuego || isset($_GET['nuevo']);

// Un partido de cadena (PvE) no tiene plazo: el briefing lo dice explícitamente
// y, además, no hay nadie esperando al otro lado a quien hacer esperar. Sin
// esto el reloj arrancaría en cero y el JS recargaría la pantalla en bucle.
$esCadena = $duelo['dificultad'] !== null;

// Volver de un partido de cadena lleva al MAPA de su ruta, no a la lista: es
// donde estaba el jugador y donde se ve el nodo que acaba de superar.
$volverUrl = 'duelos.php';
$volverTexto = 'Volver a duelos';
if ($esCadena) {
    $nodoDuelo = $duelo['id_nodo'] ? $db->obtenerNodo((int) $duelo['id_nodo']) : null;
    $volverUrl = $nodoDuelo
        ? 'cadena.php?id=' . (int) $nodoDuelo['id_cadena'] . '&nodo=' . (int) $duelo['id_nodo']
        : 'cadenas.php';
    $volverTexto = 'Volver a la ruta';
}
$conPlazo = !$esCadena && $duelo['aumento_vence'] !== null;

// Fase de aumento: solo se piden LAS PROPIAS opciones. Las del rival no se
// consultan siquiera, para que no puedan filtrarse al HTML (§6.3).
$misAumentos  = $eligiendo ? $db->listarAumentos($id_duelo, $id_usuario) : [];
$yaElegi      = $db->aumentoElegido($id_duelo, $id_usuario);
$segundosRest = 0;
if ($eligiendo && $duelo['aumento_vence']) {
    $segundosRest = max(0, strtotime($duelo['aumento_vence']) - time());
}

$etiquetaStat = ['ataque' => 'Ataque', 'defensa' => 'Defensa', 'tecnica' => 'Técnica'];
$efectoStat   = [
    'ataque'  => 'Sube tu línea de Ataque',
    'tecnica' => 'Sube tu línea de Medio',
    'defensa' => 'Sube Portería y Defensa',
];

// Alineaciones congeladas. Ambas visibles: no dan ventaja (están congeladas y
// el duelo ya está decidido) y son lo que permite entender el resultado.
$miAlineacion    = $jugado ? $db->listarAlineacionDuelo($id_duelo, $id_usuario) : [];
$suAlineacion    = $jugado ? $db->listarAlineacionDuelo($id_duelo, $idRival) : [];

// Los aumentos se destapan A LA VEZ, y solo con el partido ya montado: verlos
// antes daría ventaja a quien eligiera después (§6.3 lo marca como anti-abuso).
// Con el partido en juego ya no hay ventaja posible — están congelados y no se
// pueden cambiar— así que se destapan al empezar, no al terminar.
$miAumento = $jugado ? $db->aumentoElegido($id_duelo, $id_usuario) : null;
$suAumento = $jugado ? $db->aumentoElegido($id_duelo, $idRival) : null;
// Formaciones CONGELADAS. Un duelo anterior a que existieran las formaciones
// no tiene ninguna guardada y fue, por definición, un 1-4-4-2.
$miFormacion = ($soyCreador ? $duelo['formacion_creador'] : $duelo['formacion_rival']) ?: Tcg::FORMACION_BASE;
$suFormacion = ($soyCreador ? $duelo['formacion_rival'] : $duelo['formacion_creador']) ?: Tcg::FORMACION_BASE;

$miFuerza        = $miAlineacion ? Tcg::fuerzaAlineacion($miAlineacion, $miFormacion) : null;
$suFuerza        = $suAlineacion ? Tcg::fuerzaAlineacion($suAlineacion, $suFormacion) : null;

// Capa 2. Se leen las compos CONGELADAS del duelo, no se recalculan: si un
// rasgo se reasignó después desde el panel, este duelo debe seguir explicándose
// con lo que había cuando se jugó.
$misCompos = $jugado ? $db->listarComposDuelo($id_duelo, $id_usuario) : [];
$susCompos = $jugado ? $db->listarComposDuelo($id_duelo, $idRival) : [];

$miCiclo  = $jugado ? (float) ($soyCreador ? $duelo['ciclo_bonus_creador'] : $duelo['ciclo_bonus_rival']) : 0;
$suCiclo  = $jugado ? (float) ($soyCreador ? $duelo['ciclo_bonus_rival'] : $duelo['ciclo_bonus_creador']) : 0;
$miMalus  = $jugado ? (float) ($soyCreador ? $duelo['malus_coh_creador'] : $duelo['malus_coh_rival']) : 0;
$suMalus  = $jugado ? (float) ($soyCreador ? $duelo['malus_coh_rival'] : $duelo['malus_coh_creador']) : 0;
$miAfinDom = $jugado ? ($soyCreador ? $duelo['afinidad_dom_creador'] : $duelo['afinidad_dom_rival']) : null;
$suAfinDom = $jugado ? ($soyCreador ? $duelo['afinidad_dom_rival'] : $duelo['afinidad_dom_creador']) : null;

$catalogoRasgos = $db->rasgosCatalogo();

// Recompensas de cadena (bloque D): solo tiene sentido pedirlas si se ganó,
// que es la única vez que se reparte algo. Las reparte liquidarPartido() al
// terminar el partido, no resolverDuelo() al montarlo (§15.12), así que
// exigir $resuelto no es cosmética: mientras el duelo está `en_juego` todavía
// no hay botín que listar. $gane se calcula más abajo; aquí se repite la misma
// condición porque todavía no existe.
$drops = ($esCadena && $resuelto && (int) $duelo['id_ganador'] === $id_usuario)
    ? $db->listarDropsDuelo($id_duelo) : [];

/** Pinta el panel de compos de un jugador en la pantalla de resultado. */
function panel_compos($quien, array $compos, $afinDom, $ciclo, $malus, array $catalogo, array $etiquetaLinea) {
    ?>
    <section class="compos compos--duelo">
      <div class="compos-cabecera">
        <h3 class="t-h3"><?= htmlspecialchars($quien) ?></h3>
        <p class="t-caption t-dim">
          <?= $afinDom && $afinDom !== 'neutro'
              ? htmlspecialchars($catalogo[$afinDom]['nombre'] ?? $afinDom)
              : 'Neutro' ?>
        </p>
      </div>

      <?php if (!$compos): ?>
        <p class="t-body-sm t-dim">Ningún rasgo activo en este once.</p>
      <?php else: ?>
        <ul class="compos-lista">
          <?php foreach ($compos as $c): ?>
            <li class="compo compo--<?= htmlspecialchars($c['tipo']) ?>">
              <span class="compo-nombre"><?= htmlspecialchars($c['nombre']) ?></span>
              <span class="compo-nivel" aria-label="Nivel <?= (int) $c['nivel'] ?> de 3">
                <?php for ($n = 1; $n <= 3; $n++): ?>
                  <span class="compo-punto<?= $n <= (int) $c['nivel'] ? ' esta-lleno' : '' ?>"></span>
                <?php endfor; ?>
              </span>
              <span class="compo-detalle t-dim">
                <?php if ($c['clave'] === 'tension'): ?>
                  <span class="mono"><?= (int) $c['copias'] ?></span> rasgos distintos · mejoró el sorteo de Aumento
                <?php else: ?>
                  <span class="mono"><?= (int) $c['copias'] ?></span> en el once
                  <?php if ((float) $c['pct_nominal'] > 0): ?>
                    · <span class="mono">+<?= number_format((float) $c['pct_nominal'], 2, ',', '.') ?> %</span>
                    <?= htmlspecialchars($etiquetaLinea[$c['linea_1']] ?? '') ?><?php
                      if ($c['linea_2']) echo ' y ' . htmlspecialchars($etiquetaLinea[$c['linea_2']]); ?>
                  <?php endif; ?>
                <?php endif; ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($ciclo > 0 || $malus > 0): ?>
        <div class="compos-resumen">
          <?php if ($ciclo > 0): ?>
            <div class="dato">
              <b class="mono">+<?= number_format($ciclo, 2, ',', '.') ?> %</b>
              <span>Ventaja de afinidad</span>
            </div>
          <?php endif; ?>
          <?php if ($malus > 0): ?>
            <div class="dato es-malo">
              <b class="mono">−<?= number_format($malus, 2, ',', '.') ?> %</b>
              <span>Malus de coherencia</span>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>
    <?php
}

/* El marcador guardado. Mientras el partido está EN JUEGO este número es el
   resultado que la simulación tiene previsto, no el que se ha visto: el modal
   lo tapa y lo va destapando minuto a minuto, y las decisiones de los dos
   jugadores todavía pueden moverlo. Al terminar, el JS recarga la página y este
   valor ya es el definitivo. */
$misGoles = $jugado ? (int) ($soyCreador ? $duelo['goles_creador'] : $duelo['goles_rival']) : 0;
$susGoles = $jugado ? (int) ($soyCreador ? $duelo['goles_rival'] : $duelo['goles_creador']) : 0;
$gane     = $resuelto && (int) $duelo['id_ganador'] === $id_usuario;

// Probabilidad que tuvo ESTE jugador, no siempre la del creador.
$miProbabilidad = null;
if ($jugado && $duelo['probabilidad_victoria_creador'] !== null) {
    $p = (float) $duelo['probabilidad_victoria_creador'];
    $miProbabilidad = $soyCreador ? $p : 1 - $p;
}

$etiquetaLinea = ['POR' => 'Portería', 'DF' => 'Defensa', 'MC' => 'Medio', 'DC' => 'Ataque'];

$paginaTitulo = $esperando ? 'Sala de duelo' : 'Duelo';
$paginaDesc   = 'Sala de duelo de la Superliga Frontier.';
include __DIR__ . '/partials/head.php';

// Un partido de cadena marca "Cadenas" activo en la nav, no "Duelos": es
// la sección en la que realmente está el jugador, aunque el archivo que
// renderiza la pantalla (sala/aumento/resultado) se comparta con PvP.
$activePage = $esCadena ? 'cadenas' : 'duelos';
include __DIR__ . '/navbar.php';
?>

<main id="contenido" class="seccion wrap">

  <?php if ($duelo['estado'] === 'cancelado'): ?>
    <div class="vacio">
      <span class="vacio-ico"><i class="ph ph-x-circle" aria-hidden="true"></i></span>
      <h1>Sala cancelada</h1>
      <p>Se ha devuelto lo que hubiera apostado.</p>
      <a class="btn btn-primary" href="<?= htmlspecialchars($volverUrl) ?>"><?= htmlspecialchars($volverTexto) ?></a>
    </div>

  <?php elseif ($esperando): ?>
    <!-- SALA DE ESPERA
         El creador se queda aquí. Su pantalla late contra el servidor cada
         pocos segundos; si se va, la sala se cancela sola. -->
    <div class="sala-espera" id="salaEspera" data-duelo="<?= $id_duelo ?>">
      <h1>Esperando rival</h1>

      <p class="t-body-sm t-dim">
        <?php if ($duelo['tipo_apuesta'] === 'carta'): ?>
          En juego: una carta <?= htmlspecialchars($duelo['rareza_apuesta']) ?>.
        <?php else: ?>
          En juego: <span class="mono"><?= number_format((int) $duelo['monedas'], 0, ',', '.') ?></span> monedas por cabeza.
        <?php endif; ?>
      </p>

      <div class="sala-latido" aria-hidden="true"><span></span><span></span><span></span></div>

      <p class="t-caption t-dim" id="salaAviso" role="status" aria-live="polite">
        Sigue en esta pantalla. Si la cierras o te vas, la sala se cancela y recuperas lo apostado.
      </p>

      <form method="POST">
        <input type="hidden" name="accion" value="cancelar">
        <button type="submit" class="btn btn-ghost" id="salaCancelar">Cancelar sala</button>
      </form>
    </div>

  <?php elseif ($eligiendo): ?>
    <!-- FASE DE AUMENTO
         Ambos jugadores están aquí a la vez. Cada uno ve SOLO sus 3 opciones;
         las del rival no se consultan ni llegan al HTML. -->
    <div class="aumento" <?= $conPlazo ? 'id="faseAumento"' : '' ?>
         data-duelo="<?= $id_duelo ?>" data-restante="<?= $segundosRest ?>">

      <h1>Elige tu aumento</h1>
      <p class="t-body-sm t-dim">
        <?php if ($conPlazo): ?>
          Vale solo para este duelo. Si no eliges a tiempo, se elegirá una al azar
          para no parar el partido.
        <?php else: ?>
          Vale solo para este partido. Tómate el tiempo que quieras: aquí no
          hay nadie esperando.
        <?php endif; ?>
      </p>

      <?php if ($conPlazo): ?>
        <p class="aumento-reloj" id="aumentoReloj" role="timer" aria-live="off">
          <span class="mono" id="aumentoSegundos"><?= $segundosRest ?></span> s
        </p>
      <?php endif; ?>

      <?php if ($yaElegi): ?>
        <p class="alerta alerta-info" role="status">
          Has elegido <b><?= htmlspecialchars($etiquetaStat[$yaElegi['stat']]) ?>
          +<?= number_format((float) $yaElegi['porcentaje'], 2, ',', '.') ?> %</b>.
          Esperando a tu rival…
        </p>
      <?php else: ?>
        <div class="aumento-opciones">
          <?php foreach ($misAumentos as $a): ?>
            <form method="POST" class="aumento-opcion es-<?= $a['tier'] ?>">
              <input type="hidden" name="accion" value="elegir_aumento">
              <input type="hidden" name="opcion" value="<?= (int) $a['opcion'] ?>">
              <button type="submit" class="aumento-boton">
                <span class="aumento-tier"><?= htmlspecialchars(ucfirst($a['tier'])) ?></span>
                <b class="aumento-pct mono">+<?= number_format((float) $a['porcentaje'], 2, ',', '.') ?> %</b>
                <span class="aumento-stat"><?= htmlspecialchars($etiquetaStat[$a['stat']]) ?></span>
                <span class="aumento-efecto"><?= htmlspecialchars($efectoStat[$a['stat']]) ?></span>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  <?php elseif ($jugado): ?>
    <!-- PANTALLA DE PARTIDO
         Mientras el duelo está en juego esto queda DEBAJO del modal del partido
         y todavía no hay ganador, así que no se pinta ni victoria ni derrota: al
         acabar, el JS recarga la página y entonces sí. -->
    <div class="partido<?= $enJuego ? '' : ($gane ? ' es-victoria' : ' es-derrota') ?>" id="partido"
         data-nuevo="<?= $ceremonia ? '1' : '0' ?>"
         data-revelar="<?= isset($_GET['revelar']) ? '1' : '0' ?>">

      <div class="partido-marcador">
        <div class="partido-lado">
          <span class="partido-nombre"><?= htmlspecialchars($nombreYo) ?></span>
          <b class="partido-goles mono"><?= $misGoles ?></b>
        </div>
        <span class="partido-guion" aria-hidden="true">–</span>
        <div class="partido-lado">
          <b class="partido-goles mono"><?= $susGoles ?></b>
          <span class="partido-nombre"><?= htmlspecialchars($nombreOtro) ?></span>
        </div>
      </div>

      <!-- El veredicto ES el titular de esta pantalla, así que es el <h1>: no
           hay ningún otro encabezado de nivel 1 aquí y la página no puede
           quedarse sin él. tabindex="-1" permite que la simulación de abajo
           mueva el foco aquí al terminar, para que un lector de pantalla
           anuncie el resultado sin tener que ir a buscarlo. -->
      <h1 class="partido-veredicto" tabindex="-1">
        <?php if ($enJuego): ?>
          Partido en juego
        <?php else: ?>
          <?= $gane ? 'Victoria' : 'Derrota' ?>
          <?php /* Un empate con la palabra "Victoria" al lado se lee como un
                   error del juego, así que la tanda se dice en el titular y no
                   solo en el veredicto de abajo. */ ?>
          <?php if ($porTanda): ?><span class="partido-tanda">en los penaltis</span><?php endif; ?>
          <span class="sr-only">
            contra <?= htmlspecialchars($nombreOtro) ?>,
            <?= $misGoles ?> a <?= $susGoles ?>
            <?php if ($porTanda): ?>, decidido en la tanda de penaltis<?php endif; ?>
            <?php if ($esCadena && $duelo['rango']): ?>, rango <?= $duelo['rango'] ?><?php endif; ?>
          </span>
        <?php endif; ?>
      </h1>

      <?php
      /* VEREDICTO DEL PARTIDO (§1.5 regla 7). Solo en duelos PvP: las cadenas
         tienen su propio sello de rango justo debajo y no conviene que compitan.
         Se calcula en servidor para que el texto sea el mismo en las dos
         cuentas y para que compartirlo no dependa de lo que viera el navegador. */
      $veredicto = (!$esCadena && $resuelto) ? $db->veredictoDuelo($id_duelo, $id_usuario) : null;
      if ($veredicto):
      ?>
        <div class="veredicto">
          <p class="veredicto-detalle"><?= htmlspecialchars($veredicto['detalle']) ?></p>
          <?php if ($veredicto['actuacion']): ?>
            <p class="veredicto-actuacion"><?= htmlspecialchars($veredicto['actuacion']) ?></p>
          <?php endif; ?>

          <?php /* Mecanismo 4 de Copero (§6.1): el resumen tiene que poder
                   pegarse en Discord tal cual, sin que el jugador lo redacte. */ ?>
          <button type="button" class="btn btn-ghost btn-sm veredicto-copiar"
                  data-copiar="<?= htmlspecialchars($veredicto['compartible']) ?>">
            <i class="ph ph-copy" aria-hidden="true"></i> Copiar resumen
          </button>

          <?php /* Aquí sí caben las estadísticas: el partido ya terminó, así
                   que no compiten con el relato ni con el momentum (§1.4). */ ?>
          <dl class="veredicto-stats">
            <?php foreach ([
                'Posesión' => [$veredicto['stats']['mias']['posesion'] . '%', $veredicto['stats']['suyas']['posesion'] . '%'],
                'Tiros'    => [$veredicto['stats']['mias']['tiros'],    $veredicto['stats']['suyas']['tiros']],
                'A puerta' => [$veredicto['stats']['mias']['a_puerta'], $veredicto['stats']['suyas']['a_puerta']],
                'Paradas'  => [$veredicto['stats']['mias']['paradas'],  $veredicto['stats']['suyas']['paradas']],
                'Córners'  => [$veredicto['stats']['mias']['corners'],  $veredicto['stats']['suyas']['corners']],
                'Faltas'   => [$veredicto['stats']['mias']['faltas'],   $veredicto['stats']['suyas']['faltas']],
            ] as $etiqueta => $par): ?>
              <div>
                <dt><?= $etiqueta ?></dt>
                <dd class="mono"><?= $par[0] ?> <span aria-hidden="true">·</span> <?= $par[1] ?></dd>
              </div>
            <?php endforeach; ?>
          </dl>
        </div>
      <?php endif; ?>

      <?php if ($esCadena && $duelo['rango']): ?>
        <p class="rango-sello rango-<?= strtolower($duelo['rango']) ?>">
          <span class="rango-letra" aria-hidden="true"><?= $duelo['rango'] ?></span>
          <span class="rango-texto">
            <?php
            // El rango no es decorativo: decide qué tabla de botín se aplica,
            // así que se dice en qué consiste y no solo qué letra ha tocado.
            echo [
                'S' => 'Victoria total: goleada sin encajar.',
                'A' => 'Victoria clara.',
                'B' => 'Victoria ajustada.',
            ][$duelo['rango']] ?? '';
            ?>
          </span>
        </p>
      <?php endif; ?>

      <?php if ($drops): ?>
        <!-- Recompensas del partido. Las monedas y cada carta son un drop
             aparte (uno por fila de cadena_loot que tocó), así que se listan
             sueltas en vez de intentar resumirlas en una frase. -->
        <div class="panel recompensas" style="max-width:420px;margin:0 auto var(--space-6);">
          <h2 class="t-h3" style="margin-bottom:var(--space-3);">Recompensas</h2>
          <ul class="lista-recompensas">
            <?php foreach ($drops as $d): ?>
              <li>
                <?php if ($d['tipo'] === 'monedas'): ?>
                  <i class="ph-fill ph-coins" aria-hidden="true"></i>
                  <span class="mono">+<?= number_format((int) $d['monedas'], 0, ',', '.') ?></span> monedas
                <?php elseif ($d['tipo'] === 'cromo_limitado'): ?>
                  <i class="ph-fill ph-seal-star" aria-hidden="true"></i>
                  <?= htmlspecialchars($d['cromo_nombre']) ?>
                  <span class="pastilla pastilla-titular">
                    #<?= (int) $d['numero_serie'] ?><?= $d['cupo_numerado'] ? '/' . (int) $d['cupo_numerado'] : '' ?>
                  </span>
                <?php elseif ($d['tipo'] === 'cromo'): ?>
                  <i class="ph ph-cards" aria-hidden="true"></i>
                  <?= htmlspecialchars($d['cromo_nombre']) ?>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($miProbabilidad !== null): ?>
        <p class="t-body-sm t-dim" style="text-align:center;">
          Partías con un <span class="mono"><?= number_format($miProbabilidad * 100, 1, ',', '.') ?> %</span>
          de probabilidad según la fuerza de las dos alineaciones.
        </p>
      <?php endif; ?>

      <?php if ($miAumento || $suAumento): ?>
        <div class="aumento-destape">
          <?php foreach ([[$nombreYo, $miAumento], [$nombreOtro, $suAumento]] as [$quien, $a]): ?>
            <div class="aumento-destape-lado<?= $a ? ' es-' . $a['tier'] : '' ?>">
              <span class="t-caption t-dim"><?= htmlspecialchars($quien) ?></span>
              <?php if ($a): ?>
                <b class="mono">+<?= number_format((float) $a['porcentaje'], 2, ',', '.') ?> %</b>
                <span><?= htmlspecialchars($etiquetaStat[$a['stat']]) ?></span>
                <?php if ((int) $a['por_defecto'] === 1): ?>
                  <span class="t-caption t-dim">(automático)</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="t-dim">Sin aumento</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Desglose por líneas: es lo que hace legible el resultado en vez de
           dejarlo en un número que hay que aceptar sin más. -->
      <div class="partido-lineas">
        <div class="partido-lineas-cab">
          <span><?= htmlspecialchars($nombreYo) ?></span>
          <span></span>
          <span><?= htmlspecialchars($nombreOtro) ?></span>
        </div>
        <?php foreach (['POR', 'DF', 'MC', 'DC'] as $linea): ?>
          <?php
          $mio = (int) $miFuerza[$linea];
          $suyo = (int) $suFuerza[$linea];
          ?>
          <div class="partido-linea">
            <b class="mono<?= $mio > $suyo ? ' es-mejor' : '' ?>"><?= $mio ?></b>
            <span><?= $etiquetaLinea[$linea] ?></span>
            <b class="mono<?= $suyo > $mio ? ' es-mejor' : '' ?>"><?= $suyo ?></b>
          </div>
        <?php endforeach; ?>
        <div class="partido-linea es-total">
          <b class="mono"><?= (int) $miFuerza['total'] ?></b>
          <span>Total</span>
          <b class="mono"><?= (int) $suFuerza['total'] ?></b>
        </div>
      </div>

      <!-- CAPA 2 — de dónde salió el ajuste sobre la fuerza bruta -->
      <?php if ($misCompos || $susCompos): ?>
        <h2 class="t-h3" style="text-align:center;margin-bottom:var(--space-4);">Compos del partido</h2>
        <div class="compos-enfrentadas">
          <?php
          panel_compos($nombreYo, $misCompos, $miAfinDom, $miCiclo, $miMalus, $catalogoRasgos, $etiquetaLinea);
          panel_compos($nombreOtro, $susCompos, $suAfinDom, $suCiclo, $suMalus, $catalogoRasgos, $etiquetaLinea);
          ?>
        </div>
      <?php endif; ?>

      <div class="partido-alineaciones">
        <section>
          <h2 class="t-h3">
            <?= htmlspecialchars($nombreYo) ?>
            <span class="pastilla"><?= htmlspecialchars(Tcg::FORMACIONES[$miFormacion]['nombre']) ?></span>
          </h2>
          <div class="carta-grid carta-grid--compacta">
            <?php foreach ($miAlineacion as $c): ?>
              <?php
              $linea = Tcg::huecosDe($miFormacion)[(int) $c['hueco']];
              $aporte = (int) round(Tcg::aportarCarta($c, $linea));
              render_carta($c, ['tamano' => 'sm', 'pie' =>
                  '<span class="carta-aporte"><b class="mono">' . $aporte . '</b> '
                  . htmlspecialchars($etiquetaLinea[$linea]) . '</span>']);
              ?>
            <?php endforeach; ?>
          </div>
        </section>

        <section>
          <h2 class="t-h3">
            <?= htmlspecialchars($nombreOtro) ?>
            <span class="pastilla"><?= htmlspecialchars(Tcg::FORMACIONES[$suFormacion]['nombre']) ?></span>
          </h2>
          <div class="carta-grid carta-grid--compacta">
            <?php foreach ($suAlineacion as $c): ?>
              <?php
              $linea = Tcg::huecosDe($suFormacion)[(int) $c['hueco']];
              $aporte = (int) round(Tcg::aportarCarta($c, $linea));
              render_carta($c, ['tamano' => 'sm', 'pie' =>
                  '<span class="carta-aporte"><b class="mono">' . $aporte . '</b> '
                  . htmlspecialchars($etiquetaLinea[$linea]) . '</span>']);
              ?>
            <?php endforeach; ?>
          </div>
        </section>
      </div>

      <p style="text-align:center;margin-top:var(--space-6);">
        <a class="btn btn-primary" href="<?= htmlspecialchars($volverUrl) ?>"><?= htmlspecialchars($volverTexto) ?></a>
      </p>
    </div>

  <?php else: ?>
    <div class="vacio">
      <span class="vacio-ico"><i class="ph ph-hourglass" aria-hidden="true"></i></span>
      <h1>Duelo en curso</h1>
      <p>Este duelo todavía no se ha resuelto.</p>
      <a class="btn btn-primary" href="<?= htmlspecialchars($volverUrl) ?>"><?= htmlspecialchars($volverTexto) ?></a>
    </div>
  <?php endif; ?>

</main>

<?php if ($jugado && $ceremonia): ?>
<!-- ==========================================================================
     SIMULACIÓN DEL PARTIDO
     Esto NO es una puesta en escena: es el partido. El duelo está en
     `en_juego` y sin ganador, el minuto lo manda el servidor y las decisiones
     que se toman aquí mueven el marcador de verdad. Cuando el reloj llega al
     final se liquida el duelo y esta pantalla va a buscar el resultado.

     Sin JavaScript este modal nunca se abre; el partido se juega igual —lo
     lleva el servidor— y las decisiones se resuelven solas con la opción
     segura, así que el duelo acaba cerrándose y pagando.

     YA NO HAY DOS MODOS. Hasta el §15.12 las cadenas usaban uno propio
     ('clasico': reloj local y una insignia de gol suelta) porque su resultado
     venía decidido de antes y el modal era decoración. Desde que el partido
     decide también en PvE eso dejó de ser cierto, así que el modo clásico se
     RETIRÓ en vez de dejarlo apagado: un `data-modo="clasico"` olvidado en
     cualquier sitio dejaría el duelo en `en_juego` para siempre, porque nadie
     jugaría el partido que tiene que liquidarlo.
     ========================================================================== -->
<div class="modal simulacion" id="simulacionPartido" role="dialog" aria-modal="true"
     aria-labelledby="simulacionTitulo" aria-hidden="true"
     data-id-duelo="<?= (int) $duelo['id_duelo'] ?>"
     <?php /* Si el duelo YA está decidido, la pantalla de resultado que hay
              debajo es correcta y cerrar el modal basta para destaparla. Si no
              lo está —el caso normal en PvP—, el ganador se escribe cuando el
              partido termina, así que al acabar hay que ir a buscar esa pantalla
              al servidor en vez de destapar una que no dice nada. */ ?>
     data-decidido="<?= $resuelto ? '1' : '0' ?>">
  <div class="modal-caja modal-caja--ancha">
    <div class="modal-head">
      <h2 id="simulacionTitulo">Partido en juego</h2>
      <?php /* El aspa dice "Salir del partido" y no "Ver resultado", porque lo
               segundo sería mentir: el partido no ha terminado. En PvP sigue
               para el rival aunque tú cierres; en una cadena, salirte deja el
               marcador donde esté y el duelo lo cierra el plazo de abandono.
               Lo que verías es el marcador de este instante, no el final. */ ?>
      <button type="button" class="modal-cerrar" data-saltar-simulacion
              aria-label="Salir del partido">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <div class="simulacion-cuerpo">
      <div class="linea-campo" aria-hidden="true"></div>

      <div class="partido-marcador">
        <div class="partido-lado">
          <span class="partido-nombre"><?= htmlspecialchars($nombreYo) ?></span>
          <b class="partido-goles mono" id="simGolesYo">0</b>
        </div>
        <span class="partido-guion" aria-hidden="true">–</span>
        <div class="partido-lado">
          <b class="partido-goles mono" id="simGolesOtro">0</b>
          <span class="partido-nombre"><?= htmlspecialchars($nombreOtro) ?></span>
        </div>
      </div>

      <p class="simulacion-reloj mono" id="simReloj" role="timer" aria-live="off">0'</p>

      <div class="progreso simulacion-barra">
        <div class="progreso-riel"><div class="progreso-relleno" id="simBarra" style="width:0%"></div></div>
      </div>

      <!-- MOMENTUM (Biblia §1.4): media móvil de quién genera las ocasiones
           más recientes. Es un indicador de lectura, no toca ningún cálculo.
           El centro es el empate; la aguja se va hacia el lado que manda. -->
      <div class="sim-momentum" id="simMomentum" aria-hidden="true">
        <span class="sim-momentum-riel"><span class="sim-momentum-aguja" id="simMomentumAguja"></span></span>
        <span class="sim-momentum-etiqueta">Momentum</span>
      </div>

      <!-- El relato. aria-live="polite" y no "assertive": son decenas de
           eventos seguidos y un lector de pantalla interrumpiría sin parar.
           El veredicto final sí se anuncia aparte, en la pantalla de debajo. -->
      <div class="sim-relato" id="simRelato" aria-live="polite" aria-atomic="false"></div>

      <!-- MINIJUEGO (Biblia §2). El partido se detiene aquí y espera una
           decisión real. El plazo lo fija el catálogo; si se agota, se
           aplica la opción SEGURA, nunca la de más premio (§1.5 regla 4). -->
      <div class="sim-minijuego" id="simMinijuego" hidden>
        <p class="sim-mj-titulo" id="simMjTitulo"></p>
        <p class="sim-mj-enunciado" id="simMjEnunciado"></p>
        <!-- Reloj de decisión. La barra comunica la urgencia de un vistazo
             (§3.4), pero con "reducir movimiento" no se anima, así que el
             número de al lado es el que lleva de verdad la cuenta: sin él,
             esa preferencia te dejaría decidiendo a ciegas. -->
        <div class="sim-mj-tiempo">
          <div class="sim-mj-reloj" aria-hidden="true">
            <span class="sim-mj-reloj-relleno" id="simMjBarra"></span>
          </div>
          <span class="sim-mj-segundos mono" id="simMjSegundos" role="timer" aria-live="off"></span>
        </div>
        <div class="sim-mj-opciones" id="simMjOpciones" role="group"
             aria-labelledby="simMjEnunciado"></div>
        <!-- MEDIDOR (Biblia §2.1, segunda primitiva). La aguja recorre las
             tres zonas en bucle y el jugador la detiene: la zona donde para
             ES la opción elegida, así que viaja la misma clave por el mismo
             endpoint que un botón. Se alterna con las opciones de arriba
             desde JS, nunca se ven los dos.

             Con "reducir movimiento" esto NO se usa: duelo.js pinta los
             botones. La regla es la del §7 — se reduce el movimiento, nunca
             el juego: quien tenga la preferencia puesta sigue decidiendo lo
             mismo, solo con otro mando. -->
        <div class="sim-mj-medidor" id="simMjMedidor" hidden>
          <div class="sim-mj-pista" id="simMjPista">
            <span class="sim-mj-aguja" id="simMjAguja" aria-hidden="true"></span>
          </div>
          <button type="button" class="sim-mj-parar" id="simMjParar">Parar</button>
        </div>
        <!-- CLIC-EN-ZONA (Biblia §2.1, primera primitiva). Las tres opciones
             se colocan sobre un mapa —el marco de la portería, el área desde
             arriba o el campo— en el sitio que les toca, y se pulsa la zona.
             Son <button> de verdad, así que el teclado funciona sin nada
             añadido y no hay movimiento que reducir. El mapa lo dice `lienzo`
             y el sitio de cada opción su `zona`, que el JS pone tal cual como
             grid-area (ver Tcg::LIENZOS_ZONA). -->
        <div class="sim-mj-zonas" id="simMjZonas" hidden>
          <div class="sim-mj-lienzo" id="simMjLienzo" role="group"
               aria-labelledby="simMjEnunciado"></div>
        </div>
        <!-- ARRASTRE (Biblia §2.2, Familia DS). Se arrastra desde el balón
             hacia donde se quiere jugar; el ángulo cae en uno de tres sectores
             y ese sector ES la opción.

             ⚠️ Los botones de arriba SIGUEN VISIBLES con esta primitiva, y no
             es redundancia: WCAG 2.2 SC 2.5.7 (Dragging Movements) exige que
             toda función de arrastre tenga alternativa de un solo puntero. Sin
             ellos, esta primitiva sería inoperable con teclado y quedaría
             fuera del §7. -->
        <div class="sim-mj-arrastre" id="simMjArrastre" hidden>
          <div class="sim-mj-lona" id="simMjLona" aria-hidden="true">
            <span class="sim-mj-balon" id="simMjBalon"></span>
            <span class="sim-mj-guia" id="simMjGuia"></span>
            <span class="sim-mj-sector-nombre" id="simMjSectorNombre"></span>
          </div>
          <p class="sim-mj-arrastre-ayuda">Arrastra desde el balón, o usa los botones.</p>
        </div>
        <p class="sim-mj-resultado" id="simMjResultado" role="status" hidden></p>
      </div>

      <!-- ==================================================================
           LA TANDA DE PENALTIS (§15.11)
           Cuatro huecos. Tiras o paras, y si los dos elegís el mismo, parada.

           ⚠️ Es la ÚNICA pantalla del juego donde los dos jugadores deciden a
           la vez y uno contra otro. Por eso el panel no dice nunca qué ha
           elegido el rival hasta que el tiro está resuelto: el servidor no lo
           manda (ver Tcg::tandaParaCliente).

           Son <button> de verdad dentro de un grid 2x2, así que el teclado
           funciona sin nada añadido y no hay movimiento que reducir.
           ================================================================== -->
      <div class="sim-tanda" id="simTanda" hidden>
        <p class="sim-tanda-cab">
          <span id="simTandaRonda"></span>
          <b class="mono" id="simTandaMarcador">0 – 0</b>
        </p>
        <p class="sim-tanda-orden" id="simTandaOrden" role="status" aria-live="polite"></p>

        <div class="sim-mj-lienzo es-porteria4" id="simTandaPorteria" role="group"
             aria-labelledby="simTandaOrden"></div>

        <p class="sim-tanda-reloj mono" id="simTandaReloj" role="timer" aria-live="off"></p>
        <ol class="sim-tanda-historial" id="simTandaHistorial"></ol>
      </div>

      <?php /* Las estadísticas en vivo (posesión, tiros, paradas) se quitaron
               de aquí por decisión de Alejandro: durante el partido distraen
               de lo que de verdad se está mirando, que es el relato y el
               momentum. La §1.4 de la Biblia las pedía en pantalla; siguen
               calculándose en el motor y el sondeo las sigue enviando, así
               que reaparecen en cuanto haya dónde ponerlas sin estorbar. */ ?>
    </div>

    <?php /* NO HAY BOTÓN DE SALTAR, tampoco en cadenas desde el §15.12. En PvP
             el partido es compartido: saltártelo no detiene al rival, que puede
             seguir parando goles y moviendo el marcador después de que tú hayas
             salido. Con el botón, una cuenta terminaba viendo 4-2 y la otra 4-3.
             Y en una cadena saltarlo dejaría de tener sentido por otro motivo:
             el resultado ya no está decidido de antes, así que el botón te
             ofrecería un resultado que todavía no existe. Cerrar sigue siendo
             posible con Esc o con el aspa —un modal tiene que poder cerrarse
             (§13)—, pero deja de ser lo que la pantalla te invita a hacer. */ ?>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>

<?= assetScript($base ?? '', 'assets/js/duelo.js') ?>

</body>
</html>
