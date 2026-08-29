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
// no tiene ninguna guardada y fue, por definición, un 4-4-2.
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

$etiquetaLinea = Tcg::ETIQUETA_LINEA;   // la tabla vive en Tcg, no copiada aquí

/* PRESENTACIÓN DE ALINEACIONES — la intro previa al partido.
   Solo con `nuevo=1`, que es como se llega ACABANDO de elegir el aumento. Es
   deliberado que no salga al recargar a mitad de encuentro: la intro es la
   antesala del pitido inicial, y volver a verla cada vez que se refresca la
   pantalla la convertiría en un peaje. */
$presentacion = ($jugado && isset($_GET['nuevo']))
    ? $db->datosPresentacionDuelo($id_duelo, $id_usuario)
    : null;

// Su hoja va aparte y solo la carga esta pantalla: no la paga el resto de la web.
/* `cadena.css` no es solo el mapa de cadena: se lleva dentro el desglose por
   líneas (`.partido-lineas`) y la comparación de alineaciones
   (`.partido-alineaciones`) que también pinta un duelo terminado. Sin ella,
   el resultado final salía como texto corrido sin tabla ni rejilla. */
$cssExtra = array_merge($cssExtra ?? [], ['assets/css/ceremonia.css', 'assets/css/partido.css', 'assets/css/cadena.css']);
if ($presentacion) { $cssExtra[] = 'assets/css/presentacion.css'; }

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
          <?php /* Cuántas, no "una": desde la 031 una apuesta puede ser un lote
                   y decir "una carta" en un duelo a cuatro sería mentir sobre lo
                   que hay en riesgo justo antes de jugarlo. */ ?>
          <?php $nApostadas = max(1, (int) ($duelo['cartas_apuesta'] ?? 1)); ?>
          En juego: <?= $nApostadas === 1 ? 'una carta' : $nApostadas . ' cartas' ?>
          <?= htmlspecialchars($duelo['rareza_apuesta']) ?>.
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

      <?php /* ⚠️ SE DICE QUE ESTAS TRES SON LAS QUE HAY. Alguien descubrió que
               saliéndose de la pantalla y volviendo a entrar a la cadena se
               generaba otro partido con OTRAS tres opciones, y se dedicó a
               recargar hasta que le salía una buena. Ya no se puede —el
               partido a medias se reanuda en vez de crear otro— pero si no se
               dice, la gente lo intenta igual y lo que ve es "el juego no
               responde". Mejor contarlo que dejar que lo descubran a base de
               pelearse con la interfaz. */ ?>
      <?php if (!$conPlazo): ?>
        <p class="t-caption t-dim">
          <i class="ph ph-info" aria-hidden="true"></i>
          Estas tres son las tuyas para este partido. Salir y volver a entrar te
          devuelve aquí con las mismas.
        </p>
      <?php endif; ?>

      <?php if (isset($_GET['reanudado'])): ?>
        <p class="alerta alerta-info" role="status">
          <i class="ph ph-info" aria-hidden="true"></i>
          <span>Sigues el partido que habías dejado a medias.</span>
        </p>
      <?php endif; ?>

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
    <?php /* ⚠️ EL MARCADOR NO SE IMPRIME HASTA QUE EL DUELO ESTÁ `resuelto`.
             Y no es cosmética:

             `resolverDuelo()` simula el encuentro entero al montarlo y GUARDA
             el marcador; durante el partido los minijuegos lo suben y lo bajan.
             O sea que mientras se juega hay en la base un marcador que todavía
             no ha pasado, y esta pantalla lo imprimía en el HTML desde el primer
             byte. Se veía un instante antes de que el modal la tapara —y con la
             presentación de alineaciones delante, varios segundos— y cualquiera
             podía leerlo en el inspector durante todo el encuentro. En un duelo
             PvP eso es saber el resultado antes de jugarlo.

             El CONTENEDOR sí se queda pase lo que pase: `duelo.js` arranca desde
             `#partido` y sus `data-*`, así que quitarlo dejaría el partido sin
             empezar. Lo que se va es el contenido.

             No se pierde nada: al acabar, `partido.js` recarga con `?revelar=1`,
             el duelo ya está resuelto y esta misma pantalla se pinta entera. */ ?>
    <div class="partido<?= $enJuego ? '' : ($gane ? ' es-victoria' : ' es-derrota') ?>" id="partido"
         data-nuevo="<?= $ceremonia ? '1' : '0' ?>"
         data-revelar="<?= isset($_GET['revelar']) ? '1' : '0' ?>">

    <!-- EL PARTIDO JUGABLE. Todo lo que hay aquí lo rellena partido.js desde el
         sondeo; en HTML solo viven los huecos y el estado de partida. Vive
         AQUÍ, fuera del if($enJuego), porque es precisamente durante el
         partido en vivo cuando tiene que verse: antes vivía solo en la rama
         resuelta, donde nunca llegaba a aparecer mientras se jugaba. -->
    <section class="partido-jugable" aria-live="polite">
      <header class="partido-jugable-cab">
        <span class="partido-reloj" id="partido-minuto">0'</span>
        <div class="partido-campo" id="partido-zona" data-zona="salida">
          <span class="partido-campo-tramo" data-tramo="salida">Salida</span>
          <span class="partido-campo-tramo" data-tramo="creacion">Creación</span>
          <span class="partido-campo-tramo" data-tramo="area">Área</span>
        </div>
      </header>

      <div class="partido-acciones" id="partido-acciones" hidden></div>
      <div class="partido-lona" id="partido-lona" hidden></div>

      <?php /* LA TANDA DE PENALTIS (§15.11), dentro del partido y no en un modal
               aparte: es una fase más del encuentro, no otra pantalla. Los ids
               son NUEVOS (`partido-penaltis-*`) a propósito, herencia de cuando
               el modal narrado viejo (con sus `simTanda*`) todavía convivía en
               esta misma página.

               La clase base tampoco puede ser `.partido-tanda`: ese nombre ya lo
               usa el "en los penaltis" del titular de resultado (layout.css). */ ?>
      <div class="partido-penaltis" id="partido-penaltis" hidden>
        <p class="partido-penaltis-ronda" id="partido-penaltis-ronda"></p>
        <p class="partido-penaltis-marcador mono" id="partido-penaltis-marcador">0 – 0</p>
        <p class="partido-penaltis-orden" id="partido-penaltis-orden"></p>
        <div class="partido-penaltis-porteria" id="partido-penaltis-porteria"></div>
        <p class="partido-penaltis-reloj mono" id="partido-penaltis-reloj"></p>
        <ul class="partido-penaltis-historial" id="partido-penaltis-historial"></ul>
      </div>

      <p class="partido-espera" id="partido-espera">Empieza el partido…</p>
    </section>

    <?php if ($enJuego): ?>
      <?php /* Sin JavaScript la sección de arriba se queda tal cual, con "Empieza
               el partido…": no cuenta nada, pero tampoco miente. Con JavaScript,
               `Partido.iniciar()` (llamado al final de esta página) la rellena
               desde el sondeo — el mismo sondeo que antes solo alimentaba el
               modal viejo. */ ?>

    <?php else: ?>

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
        <div class="panel recompensas" style="max-width:420px;margin:0 auto var(--e-6);">
          <h2 class="t-h3" style="margin-bottom:var(--e-3);">Recompensas</h2>
          <ul class="lista-recompensas">
            <?php foreach ($drops as $d): ?>
              <li>
                <?php if ($d['tipo'] === 'monedas'): ?>
                  <i class="ph-fill ph-coins" aria-hidden="true"></i>
                  <span class="mono">+<?= number_format((int) $d['monedas'], 0, ',', '.') ?></span> monedas
                <?php elseif ($d['tipo'] === 'cromo_limitado'): ?>
                  <i class="ph-fill ph-seal-check" aria-hidden="true"></i>
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
        <h2 class="t-h3" style="text-align:center;margin-bottom:var(--e-4);">Compos del partido</h2>
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

      <p style="text-align:center;margin-top:var(--e-6);">
        <a class="btn btn-primary" href="<?= htmlspecialchars($volverUrl) ?>"><?= htmlspecialchars($volverTexto) ?></a>
      </p>

    <?php endif; /* $enJuego */ ?>
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

<?php include __DIR__ . '/partials/presentacion_duelo.php'; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>

<?php /* GSAP solo cuando hay intro que animar. En el resto de cargas de esta
         pantalla —la sala de espera, la elección de aumento, el resultado— no
         se descarga: `duelo.js` funciona sin él y la presentación se salta
         sola si no lo encuentra. */ ?>
<?php if ($presentacion): ?>
<script src="<?= htmlspecialchars(assetUrl($base ?? '', 'assets/js/vendor/gsap/gsap.min.js')) ?>"></script>
<?= assetScript($base ?? '', 'assets/js/presentacion.js') ?>
<?php endif; ?>

<?= assetScript($base ?? '', 'assets/js/duelo.js') ?>

<script src="<?= htmlspecialchars(assetUrl($base ?? '', 'assets/js/partido.js')) ?>"></script>
<script>
  Partido.iniciar({
    idDuelo: <?= (int) $id_duelo ?>,
    csrf: <?= json_encode(csrfToken()) ?>,
    base: <?= json_encode($base ?? '') ?>,
    <?php /* Si el duelo YA venía decidido, esta página es la de resultado y es
             correcta: recargarla al ver `estado: 'resuelto'` sería un bucle de
             recargas. Solo se va a buscar la pantalla nueva cuando el duelo se
             decide MIENTRAS se está mirando. Mismo guardia que el
             `data-decidido` del modal viejo. */ ?>
    decidido: <?= $resuelto ? 1 : 0 ?>,
    minutos: 90,
    narracionSeg: <?= (float) $db->config('partido_narracion_seg', 3) ?>
  });
</script>

</body>
</html>
