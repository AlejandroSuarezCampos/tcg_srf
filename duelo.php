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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'elegir_aumento') {
    $db->elegirAumento($id_duelo, $id_usuario, (int) ($_POST['opcion'] ?? 0));
    header('Location: duelo.php?id=' . $id_duelo);
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

$soyCreador = (int) $duelo['id_creador'] === $id_usuario;
$idRival    = $soyCreador ? (int) $duelo['id_rival'] : (int) $duelo['id_creador'];
$nombreYo   = $soyCreador ? $duelo['creador'] : $duelo['rival'];
$nombreOtro = $soyCreador ? ($duelo['rival'] ?? 'Esperando rival') : $duelo['creador'];

$esperando = $duelo['estado'] === 'creado';
$resuelto  = $duelo['estado'] === 'resuelto';
$eligiendo = $duelo['estado'] === 'aumento_pendiente';

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
$miAlineacion    = $resuelto ? $db->listarAlineacionDuelo($id_duelo, $id_usuario) : [];
$suAlineacion    = $resuelto ? $db->listarAlineacionDuelo($id_duelo, $idRival) : [];

// Los aumentos se destapan A LA VEZ, y solo con el duelo ya resuelto: verlos
// antes daría ventaja a quien eligiera después (§6.3 lo marca como anti-abuso).
$miAumento = $resuelto ? $db->aumentoElegido($id_duelo, $id_usuario) : null;
$suAumento = $resuelto ? $db->aumentoElegido($id_duelo, $idRival) : null;
$miFuerza        = $miAlineacion ? Tcg::fuerzaAlineacion($miAlineacion) : null;
$suFuerza        = $suAlineacion ? Tcg::fuerzaAlineacion($suAlineacion) : null;

// Capa 2. Se leen las compos CONGELADAS del duelo, no se recalculan: si un
// rasgo se reasignó después desde el panel, este duelo debe seguir explicándose
// con lo que había cuando se jugó.
$misCompos = $resuelto ? $db->listarComposDuelo($id_duelo, $id_usuario) : [];
$susCompos = $resuelto ? $db->listarComposDuelo($id_duelo, $idRival) : [];

$miCiclo  = $resuelto ? (float) ($soyCreador ? $duelo['ciclo_bonus_creador'] : $duelo['ciclo_bonus_rival']) : 0;
$suCiclo  = $resuelto ? (float) ($soyCreador ? $duelo['ciclo_bonus_rival'] : $duelo['ciclo_bonus_creador']) : 0;
$miMalus  = $resuelto ? (float) ($soyCreador ? $duelo['malus_coh_creador'] : $duelo['malus_coh_rival']) : 0;
$suMalus  = $resuelto ? (float) ($soyCreador ? $duelo['malus_coh_rival'] : $duelo['malus_coh_creador']) : 0;
$miAfinDom = $resuelto ? ($soyCreador ? $duelo['afinidad_dom_creador'] : $duelo['afinidad_dom_rival']) : null;
$suAfinDom = $resuelto ? ($soyCreador ? $duelo['afinidad_dom_rival'] : $duelo['afinidad_dom_creador']) : null;

$catalogoRasgos = $db->rasgosCatalogo();

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

$misGoles = $resuelto ? (int) ($soyCreador ? $duelo['goles_creador'] : $duelo['goles_rival']) : 0;
$susGoles = $resuelto ? (int) ($soyCreador ? $duelo['goles_rival'] : $duelo['goles_creador']) : 0;
$gane     = $resuelto && (int) $duelo['id_ganador'] === $id_usuario;

// Probabilidad que tuvo ESTE jugador, no siempre la del creador.
$miProbabilidad = null;
if ($resuelto && $duelo['probabilidad_victoria_creador'] !== null) {
    $p = (float) $duelo['probabilidad_victoria_creador'];
    $miProbabilidad = $soyCreador ? $p : 1 - $p;
}

$etiquetaLinea = ['POR' => 'Portería', 'DF' => 'Defensa', 'MC' => 'Medio', 'DC' => 'Ataque'];

$paginaTitulo = $esperando ? 'Sala de duelo' : 'Duelo';
$paginaDesc   = 'Sala de duelo de la Superliga Frontier.';
include __DIR__ . '/partials/head.php';

$activePage = 'duelos';
include __DIR__ . '/navbar.php';
?>

<main id="contenido" class="seccion wrap">

  <?php if ($duelo['estado'] === 'cancelado'): ?>
    <div class="vacio">
      <span class="vacio-ico"><i class="ph ph-x-circle" aria-hidden="true"></i></span>
      <h1>Sala cancelada</h1>
      <p>Se ha devuelto lo que hubiera apostado.</p>
      <a class="btn btn-primary" href="duelos.php">Volver a duelos</a>
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
    <div class="aumento" id="faseAumento"
         data-duelo="<?= $id_duelo ?>" data-restante="<?= $segundosRest ?>">

      <h1>Elige tu aumento</h1>
      <p class="t-body-sm t-dim">
        Vale solo para este duelo. Si no eliges a tiempo, se elegirá una al azar
        para no parar el partido.
      </p>

      <p class="aumento-reloj" id="aumentoReloj" role="timer" aria-live="off">
        <span class="mono" id="aumentoSegundos"><?= $segundosRest ?></span> s
      </p>

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

  <?php elseif ($resuelto): ?>
    <!-- PANTALLA DE PARTIDO -->
    <div class="partido<?= $gane ? ' es-victoria' : ' es-derrota' ?>" id="partido"
         data-nuevo="<?= isset($_GET['nuevo']) ? '1' : '0' ?>">

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
        <?= $gane ? 'Victoria' : 'Derrota' ?>
        <span class="sr-only">
          contra <?= htmlspecialchars($nombreOtro) ?>,
          <?= $misGoles ?> a <?= $susGoles ?>
        </span>
      </h1>

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
          <h2 class="t-h3"><?= htmlspecialchars($nombreYo) ?></h2>
          <div class="carta-grid carta-grid--compacta">
            <?php foreach ($miAlineacion as $c): ?>
              <?php
              $linea = Tcg::HUECOS[(int) $c['hueco']];
              $stat  = Tcg::ESTADISTICA_LINEA[$linea];
              render_carta($c, ['tamano' => 'sm', 'pie' =>
                  '<span class="carta-aporte"><b class="mono">' . (int) $c[$stat] . '</b> '
                  . htmlspecialchars($etiquetaLinea[$linea]) . '</span>']);
              ?>
            <?php endforeach; ?>
          </div>
        </section>

        <section>
          <h2 class="t-h3"><?= htmlspecialchars($nombreOtro) ?></h2>
          <div class="carta-grid carta-grid--compacta">
            <?php foreach ($suAlineacion as $c): ?>
              <?php
              $linea = Tcg::HUECOS[(int) $c['hueco']];
              $stat  = Tcg::ESTADISTICA_LINEA[$linea];
              render_carta($c, ['tamano' => 'sm', 'pie' =>
                  '<span class="carta-aporte"><b class="mono">' . (int) $c[$stat] . '</b> '
                  . htmlspecialchars($etiquetaLinea[$linea]) . '</span>']);
              ?>
            <?php endforeach; ?>
          </div>
        </section>
      </div>

      <p style="text-align:center;margin-top:var(--space-6);">
        <a class="btn btn-primary" href="duelos.php">Volver a duelos</a>
      </p>
    </div>

  <?php else: ?>
    <div class="vacio">
      <span class="vacio-ico"><i class="ph ph-hourglass" aria-hidden="true"></i></span>
      <h1>Duelo en curso</h1>
      <p>Este duelo todavía no se ha resuelto.</p>
      <a class="btn btn-primary" href="duelos.php">Volver a duelos</a>
    </div>
  <?php endif; ?>

</main>

<?php if ($resuelto && isset($_GET['nuevo'])): ?>
<!-- ==========================================================================
     SIMULACIÓN DEL PARTIDO
     El resultado ya está decidido en el servidor (arriba, en $duelo). Esto es
     solo la puesta en escena de "verlo pasar" antes de enseñar la pantalla de
     resultado, que ya está renderizada debajo, cubierta por este modal.
     Reloj de partido + goles que van apareciendo: un marcador deportivo, nunca
     ruleta ni tragaperras (briefing: "evitar estética de casino").
     Sin JavaScript, este modal nunca se abre y el resultado ya está visible.
     ========================================================================== -->
<div class="modal simulacion" id="simulacionPartido" role="dialog" aria-modal="true"
     aria-labelledby="simulacionTitulo" aria-hidden="true">
  <div class="modal-caja modal-caja--ancha">
    <div class="modal-head">
      <h2 id="simulacionTitulo">Partido en juego</h2>
      <button type="button" class="modal-cerrar" data-saltar-simulacion aria-label="Ver resultado">
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

      <div class="simulacion-eventos" id="simEventos" aria-hidden="true"></div>
    </div>

    <div class="modal-pie">
      <button type="button" class="btn btn-ghost" data-saltar-simulacion>Ver resultado</button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>

<script src="assets/js/duelo.js"></script>

</body>
</html>
