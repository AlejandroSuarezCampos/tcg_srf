<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';
require_once __DIR__ . '/partials/csrf.php';

function esPeticionAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = (int) $_SESSION['id_usuario'];
$id_cadena  = (int) ($_GET['id'] ?? 0);

$cadena = $db->obtenerCadena($id_cadena, $id_usuario);
if (!$cadena) {
    header('Location: cadenas.php');
    exit;
}

// El bloqueo se comprueba al entrar, no al listar (§5 del briefing).
$pendientes = $db->requisitosPendientes($id_cadena, $id_usuario);
if ($pendientes) {
    header('Location: cadenas.php');
    exit;
}

$aviso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfValido($_POST['csrf'] ?? null)) {
    if (esPeticionAjax()) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'La página ha caducado, inténtalo de nuevo.']);
        exit;
    }
    $_SESSION['cadena_error'] = 'La página ha caducado, inténtalo de nuevo.';
    header('Location: cadena.php?id=' . $id_cadena);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'jugar') {
        $res = $db->crearPartidoCadena($id_usuario, (int) ($_POST['id_nodo'] ?? 0), $_POST['dificultad'] ?? '');
        if ($res['ok']) {
            header('Location: duelo.php?id=' . $res['id_duelo']);
            exit;
        }
        $_SESSION['cadena_error'] = $res['error']
            . (!empty($res['cartas_excedidas'])
                ? ' Quita de tu mazo titular: ' . implode(', ', array_map(
                    fn($c) => $c['nombre'] . ' (' . $c['rareza'] . ')', $res['cartas_excedidas'])) . '.'
                : '');
        header('Location: cadena.php?id=' . $id_cadena . '&nodo=' . (int) ($_POST['id_nodo'] ?? 0));
        exit;
    }

    if ($accion === 'reclamar') {
        $idNodoReclamado = (int) ($_POST['id_nodo'] ?? 0);
        $res = $db->reclamarCofre($idNodoReclamado, $id_usuario);

        // Se relee con nombres ya resueltos (listarDropsCofre hace el JOIN
        // con cromos) en vez de formatear a mano el array de reclamarCofre.
        $drops = $res['ok'] ? $db->listarDropsCofre($idNodoReclamado, $id_usuario) : [];

        if (esPeticionAjax()) {
            // La ceremonia de apertura pinta las cartas con el MISMO componente
            // que el resto del sitio (igual que hace sobres.php): el HTML se
            // genera aquí, JS no reimplementa el marcado de la carta.
            $cartas = [];
            $monedasGanadas = 0;
            foreach ($drops as $d) {
                if ($d['tipo'] === 'monedas') {
                    $monedasGanadas += (int) $d['monedas'];
                } elseif ($d['tipo'] === 'cromo' || $d['tipo'] === 'cromo_limitado') {
                    $cartas[] = [
                        'nombre'        => $d['cromo_nombre'],
                        'rareza'        => $d['rareza'],
                        'id_rareza'     => (int) $d['id_rareza'],
                        'numero_serie'  => $d['numero_serie'] ? (int) $d['numero_serie'] : null,
                        'cupo_numerado' => $d['cupo_numerado'] ? (int) $d['cupo_numerado'] : null,
                        'html'          => carta_html([
                            'nombre'          => $d['cromo_nombre'],
                            'imagen'          => $d['imagen'],
                            'posicion'        => $d['posicion'],
                            'equipo'          => $d['equipo'],
                            'id_rareza'       => (int) $d['id_rareza'],
                            'rareza'          => $d['rareza'],
                            'afinidad'        => $d['afinidad'],
                            'afinidad_imagen' => $d['afinidad_imagen'],
                        ], ['tamano' => 'sm', 'lazy' => false]),
                    ];
                }
            }

            header('Content-Type: application/json');
            echo json_encode([
                'ok'              => $res['ok'],
                'error'           => $res['error'],
                'monedas'         => $monedasGanadas,
                'camino_perfecto' => !empty($res['camino_perfecto']),
                'formacion'       => (!empty($res['formacion']) && isset(Tcg::FORMACIONES[$res['formacion']]))
                    ? Tcg::FORMACIONES[$res['formacion']]['nombre'] : null,
                'cartas'          => $cartas,
            ]);
            exit;
        }

        // Sin JS: mismo aviso de texto de siempre, por si acaso.
        if ($res['ok']) {
            $partes = ['Cofre abierto.'];
            if (!empty($res['camino_perfecto'])) {
                $partes[] = 'Camino perfecto: llegaste con todos los partidos en rango S en Extremo.';
            }
            if (!empty($res['formacion']) && isset(Tcg::FORMACIONES[$res['formacion']])) {
                $partes[] = 'Has desbloqueado la formación ' . Tcg::FORMACIONES[$res['formacion']]['nombre'] . '.';
            }
            foreach ($drops as $d) {
                if ($d['tipo'] === 'monedas') {
                    $partes[] = '+' . number_format((int) $d['monedas'], 0, ',', '.') . ' monedas.';
                } elseif ($d['tipo'] === 'cromo_limitado') {
                    // sin htmlspecialchars aquí: $aviso['texto'] entero se escapa
                    // una sola vez al pintarlo, escaparlo ya en este punto lo
                    // habría escapado dos veces
                    $partes[] = $d['cromo_nombre'] . ' (#' . (int) $d['numero_serie']
                        . ($d['cupo_numerado'] ? '/' . (int) $d['cupo_numerado'] : '') . ').';
                } elseif ($d['tipo'] === 'cromo') {
                    $partes[] = $d['cromo_nombre'] . '.';
                }
            }
            $_SESSION['cadena_ok'] = implode(' ', $partes);
        } else {
            $_SESSION['cadena_error'] = $res['error'];
        }
        header('Location: cadena.php?id=' . $id_cadena);
        exit;
    }
}

if (!empty($_SESSION['cadena_error'])) {
    $aviso = ['tipo' => 'danger', 'texto' => $_SESSION['cadena_error']];
    unset($_SESSION['cadena_error']);
} elseif (!empty($_SESSION['cadena_ok'])) {
    $aviso = ['tipo' => 'success', 'texto' => $_SESSION['cadena_ok']];
    unset($_SESSION['cadena_ok']);
}

/* Cierra los partidos de cadena que quedaron a medias, ANTES de leer el mapa
   para que el nodo recién liquidado ya aparezca superado.

   Es la misma red perezosa que duelos.php pone para el PvP (§8: no hay cron),
   y hace falta desde que el partido decide también en PvE (§15.12): antes un
   partido de cadena nacía `resuelto` y no había nada que cerrar. Ahora nace
   `en_juego`, y quien se va a mitad vuelve POR AQUÍ, no por duelos.php — sin
   este enganche su nodo no constaría jugado nunca, ni ganado ni perdido, y el
   duelo se quedaría colgado para siempre. */
$db->cerrarPartidosPendientes($id_usuario);

$mapa  = $db->mapaCadena($id_cadena, $id_usuario);
$nodos = $mapa['nodos'];

// Nodo seleccionado: se elige navegando, sin JS, igual que el resto del sitio.
$idSel = (int) ($_GET['nodo'] ?? 0);
$sel   = $nodos[$idSel] ?? null;
if ($sel && !$sel['disponible']) { $sel = null; }

// --- geometría del mapa ---
/* ⚠️ ESTE MAPA USA LAS MISMAS COORDENADAS QUE EL EDITOR, y no una rejilla
   propia. Antes colocaba con `columna * 146` y `fila * 124` —su propia rejilla,
   y encima con celdas de otro tamaño que las del editor (190x120)—, así que lo
   que el administrador montaba y lo que el jugador veía no eran el mismo mapa:
   ni las proporciones ni las distancias coincidían, y desde la `044` la
   posición libre en píxeles ni siquiera llegaba aquí.

   Ahora se leen `pos_x`/`pos_y` tal cual. Lo único que se traduce es el ANCLA:
   el editor guarda la esquina superior izquierda de una caja de 150x64 y aquí
   los nodos se posicionan por su centro, así que se suma media caja. Con eso,
   mover un nodo tres píxeles en el editor lo mueve tres píxeles aquí.

   Las cadenas anteriores a la `044` no tienen píxeles: se cae a la rejilla
   vieja del editor (190x120), que es lo que tenían, no a la de 146x124 que
   usaba esta pantalla. */
const EDITOR_NODO_W = 150;
const EDITOR_NODO_H = 64;
const EDITOR_COL_W  = 190;
const EDITOR_FILA_H = 120;

$centro = function ($n) {
    $x = ($n['pos_x'] !== null && $n['pos_x'] !== '')
        ? (int) $n['pos_x'] : (int) $n['columna'] * EDITOR_COL_W + 40;
    $y = ($n['pos_y'] !== null && $n['pos_y'] !== '')
        ? (int) $n['pos_y'] : (int) $n['fila'] * EDITOR_FILA_H + 40;

    return ['x' => $x + EDITOR_NODO_W / 2, 'y' => $y + EDITOR_NODO_H / 2];
};

/* El lienzo mide lo que ocupen los nodos más un margen: sin margen, el nodo
   que esté más a la derecha se queda con medio hexágono fuera del recorte. */
$ancho = 0;
$alto  = 0;
foreach ($nodos as $n) {
    $p = $centro($n);
    $ancho = max($ancho, $p['x']);
    $alto  = max($alto,  $p['y']);
}
$ancho += 110;
$alto  += 90;

/* Quién lleva la marca de INICIO.

   Si la cadena tiene su casilla de SALIDA (`044`), es ella y solo ella: para
   eso se puso. Si no la tiene —las cadenas de antes—, se marca lo de siempre,
   los nodos sin ningún camino entrante. Misma regla que usa `mapaCadena()`
   para decidir qué está abierto, así que la marca y lo jugable no pueden
   contradecirse. */
$casillasSalida = array_keys(array_filter($nodos, fn($n) => $n['tipo'] === 'inicio'));

if ($casillasSalida) {
    $inicios = $casillasSalida;
} else {
    $conEntrada = [];
    foreach ($mapa['aristas'] as $a) { $conEntrada[(int) $a['id_destino']] = true; }
    $inicios = array_values(array_diff(array_keys($nodos), array_keys($conEntrada)));
}

$etiquetaDificultad = [
    'facil'       => 'Fácil',
    'medio'       => 'Medio',
    'dificil'     => 'Difícil',
    'muy_dificil' => 'Muy difícil',
    'extremo'     => 'Extremo',
];
$rarezaMax = [];
foreach (Tcg::DIFICULTADES as $d) {
    $rarezaMax[$d] = (int) $db->config('pve_rareza_max_' . $d, 0);
}
$rarezasNombre = [];
foreach ($db->listarRarezas() as $r) {
    $rarezasNombre[(int) $r['id_rareza']] = $r['nombre'];
}

$paginaTitulo = $cadena['nombre'];
$paginaDesc   = $cadena['descripcion'] ?: 'Ruta de partidos de la Superliga Frontier.';
include __DIR__ . '/partials/head.php';

$activePage = 'cadenas';
include __DIR__ . '/navbar.php';
?>

<header class="cabecera">
  <div class="linea-campo" aria-hidden="true"></div>
  <div class="wrap cabecera-contenido">
    <p class="t-caption t-dim"><a href="cadenas.php">Cadenas</a></p>
    <h1><?= htmlspecialchars($cadena['nombre']) ?></h1>
    <?php if ($cadena['descripcion']): ?>
      <p><?= htmlspecialchars($cadena['descripcion']) ?></p>
    <?php endif; ?>
  </div>
</header>

<main id="contenido" class="seccion wrap">

  <?php if ($aviso): ?>
    <p class="alerta alerta-<?= $aviso['tipo'] ?>" role="status"><?= htmlspecialchars($aviso['texto']) ?></p>
  <?php endif; ?>

  <!-- MAPA DE LA RUTA
       Nodos colocados por (columna, fila) y unidos por las aristas de la base
       de datos. Los caminos van en un SVG por debajo, con la misma aritmética
       de coordenadas que los nodos, para que no puedan descuadrarse entre sí.

       El trazado es ORTOGONAL (horizontal → vertical → horizontal) y no en
       diagonal: así es como se ven las rutas del Inazuma Eleven de DS, con
       aspecto de tubería/circuito en vez de un grafo suelto. Cada camino se
       dibuja dos veces, una de carcasa oscura y otra de "energía" encima, que
       es lo que le da el grosor de tubo. -->
  <div class="mapa-marco">
    <div class="mapa" style="width:<?= $ancho ?>px; height:<?= $alto ?>px;">

      <svg class="mapa-lineas" viewBox="0 0 <?= $ancho ?> <?= $alto ?>"
           width="<?= $ancho ?>" height="<?= $alto ?>" aria-hidden="true">
        <?php
        // Se pintan primero TODAS las carcasas y luego todas las energías, para
        // que en un cruce la carcasa de un camino no tape la energía de otro.
        $rutas = [];
        foreach ($mapa['aristas'] as $a) {
            $o = $nodos[(int) $a['id_origen']] ?? null;
            $d = $nodos[(int) $a['id_destino']] ?? null;
            if (!$o || !$d) { continue; }
            $po = $centro($o);
            $pd = $centro($d);
            $mx = (int) (($po['x'] + $pd['x']) / 2);
            $rutas[] = [
                'd' => $po['y'] === $pd['y']
                    ? "M {$po['x']} {$po['y']} H {$pd['x']}"
                    : "M {$po['x']} {$po['y']} H $mx V {$pd['y']} H {$pd['x']}",
                // un camino "recorrido" es el que ya has abierto al superar su origen
                'recorrida' => (bool) $o['superado'],
            ];
        }
        ?>
        <?php foreach ($rutas as $r): ?>
          <path class="mapa-carcasa" d="<?= $r['d'] ?>" />
        <?php endforeach; ?>
        <?php foreach ($rutas as $r): ?>
          <path class="mapa-energia <?= $r['recorrida'] ? 'es-recorrida' : '' ?>" d="<?= $r['d'] ?>" />
        <?php endforeach; ?>
      </svg>

      <?php foreach ($nodos as $id => $n): ?>
        <?php
        $p = $centro($n);
        $clases = ['nodo', 'nodo--' . $n['tipo']];
        if ($n['superado'])        { $clases[] = 'es-superado'; }
        elseif ($n['disponible'])  { $clases[] = 'es-disponible'; }
        else                       { $clases[] = 'es-bloqueado'; }
        if ($id === $idSel)        { $clases[] = 'es-elegido'; }
        if (in_array($id, $inicios, true)) { $clases[] = 'es-inicio'; }

        $descripcion = ['cofre' => 'Cofre', 'inicio' => 'Salida', 'bloqueo' => 'Bloqueo'][$n['tipo']] ?? 'Partido';
        $estadoTexto = $n['superado'] ? 'superado' : ($n['disponible'] ? 'disponible' : 'bloqueado');
        ?>
        <div class="<?= implode(' ', $clases) ?>"
             style="left:<?= $p['x'] ?>px; top:<?= $p['y'] ?>px;">
          <?php if ($n['disponible']): ?>
            <a class="nodo-boton" href="cadena.php?id=<?= $id_cadena ?>&nodo=<?= $id ?>#seleccion">
          <?php else: ?>
            <span class="nodo-boton" aria-disabled="true">
          <?php endif; ?>

            <span class="nodo-pieza" aria-hidden="true">
              <span class="nodo-hex">
                <span class="nodo-hex-int">
                  <?php if ($n['tipo'] === 'inicio'): ?>
                    <?php /* La casilla de SALIDA (`044`). No se juega ni se
                             bloquea nunca: solo marca por dónde se entra. */ ?>
                    <i class="ph-fill ph-play"></i>
                  <?php elseif ($n['tipo'] === 'bloqueo'): ?>
                    <?php /* EL STOP (`045`). Cumplido se queda en verde y ya no
                             estorba; sin cumplir es la señal de prohibido, que
                             es lo que se ve venir desde lejos en el mapa. */ ?>
                    <i class="ph-fill <?= $n['superado'] ? 'ph-check-circle' : 'ph-prohibit' ?>"></i>
                  <?php elseif ($n['tipo'] === 'cofre'): ?>
                    <i class="ph<?= $n['reclamado'] ? '' : '-fill' ?> ph-treasure-chest"></i>
                  <?php elseif (!$n['disponible']): ?>
                    <i class="ph ph-lock-simple"></i>
                  <?php elseif (!empty($n['escudo_rival'])): ?>
                    <!-- Escudo del rival en vez del icono de espada, si el
                         admin le asignó uno (panel/cadena_editor.php). Sin
                         escudo asignado se sigue viendo la espada de
                         siempre: el <img> nunca sustituye al <i> a medias. -->
                    <img class="nodo-escudo" src="<?= htmlspecialchars($n['escudo_rival']) ?>"
                         alt="Escudo de <?= htmlspecialchars($n['rival'] ?? '') ?>">
                  <?php else: ?>
                    <i class="ph-fill ph-sword"></i>
                  <?php endif; ?>
                </span>
              </span>

              <?php if ($n['mejor_rango']): ?>
                <!-- Sello de rango en la esquina, como los círculos de rango
                     del mapa de rutas del Inazuma Eleven de DS. -->
                <span class="nodo-sello rango-<?= strtolower($n['mejor_rango']) ?>"><?= $n['mejor_rango'] ?></span>
              <?php endif; ?>
            </span>

            <span class="nodo-nombre"><?= htmlspecialchars($n['nombre'] ?? '') ?></span>
            <span class="sr-only">
              <?= $descripcion ?>, <?= $estadoTexto ?><?php
                if ($n['mejor_rango']) { echo ', mejor rango ' . $n['mejor_rango']; } ?>
            </span>

          <?= $n['disponible'] ? '</a>' : '</span>' ?>
        </div>
      <?php endforeach; ?>

    </div>

    <p class="mapa-rotulo"><?= htmlspecialchars($cadena['nombre']) ?></p>
  </div>

  <p class="mapa-leyenda t-caption t-dim">
    <span><i class="ph ph-sword" aria-hidden="true"></i> Partido</span>
    <span><i class="ph-fill ph-treasure-chest" aria-hidden="true"></i> Cofre</span>
    <span><i class="ph-fill ph-prohibit" aria-hidden="true"></i> Control de paso</span>
    <span><i class="ph ph-lock-simple" aria-hidden="true"></i> Aún no alcanzado</span>
  </p>

  <!-- SELECCIÓN -->
  <section id="seleccion" class="panel" style="margin-top:var(--space-6);">
    <?php if (!$sel): ?>
      <p class="t-body-sm t-dim">
        Elige un nodo abierto del mapa para ver qué hay en él. Perder no cuesta
        nada: puedes reintentar las veces que quieras y el progreso se guarda
        partido a partido.
      </p>

    <?php elseif ($sel['tipo'] === 'cofre'): ?>
      <div class="panel-head">
        <h2 class="panel-titulo"><?= htmlspecialchars($sel['nombre']) ?></h2>
        <?php if ($sel['reclamado']): ?>
          <span class="pastilla pastilla-on">Abierto</span>
        <?php endif; ?>
      </div>

      <?php if ($sel['reclamado']): ?>
        <p class="t-body-sm t-dim">Ya abriste este cofre.</p>
      <?php else: ?>
        <p class="t-body-sm t-dim" style="margin-bottom:var(--space-4);">
          Has llegado hasta aquí. Ábrelo para seguir avanzando por la ruta.
          <?php if ((int) $sel['es_final'] === 1 && $cadena['formacion_recompensa']
                    && isset(Tcg::FORMACIONES[$cadena['formacion_recompensa']])): ?>
            Este es el cofre final: desbloquea la formación
            <b><?= htmlspecialchars(Tcg::FORMACIONES[$cadena['formacion_recompensa']]['nombre']) ?></b>.
          <?php endif; ?>
        </p>
        <form method="POST" id="formReclamarCofre">
          <?= csrfCampo() ?>
          <input type="hidden" name="accion" value="reclamar">
          <input type="hidden" name="id_nodo" value="<?= (int) $sel['id_nodo'] ?>">
          <button type="submit" class="btn btn-primary">
            <i class="ph ph-treasure-chest" aria-hidden="true"></i> Abrir cofre
          </button>
        </form>
      <?php endif; ?>

    <?php elseif ($sel['tipo'] === 'bloqueo'): ?>
      <?php /* EL STOP (`045`). No se juega ni se reclama: se abre solo en cuanto
               se cumple todo lo que pide, así que aquí no hay botón — solo la
               lista de lo que falta, con su progreso. */ ?>
      <div class="panel-head">
        <h2 class="panel-titulo"><?= htmlspecialchars($sel['nombre'] ?: 'Control de paso') ?></h2>
        <?php if ($sel['superado']): ?>
          <span class="pastilla pastilla-on">Superado</span>
        <?php endif; ?>
      </div>

      <?php if ($sel['superado']): ?>
        <p class="t-body-sm t-dim">
          Ya cumples lo que pedía este control. El camino sigue abierto.
        </p>
      <?php else: ?>
        <p class="t-body-sm t-dim" style="margin-bottom:var(--space-4);">
          De aquí no se pasa hasta cumplir <b>todo</b> lo de abajo. No hay que
          volver: en cuanto lo cumplas, el control se abre solo.
        </p>

        <ul class="lista-requisitos">
          <?php foreach ($sel['requisitos'] as $req): ?>
            <?php
            // La barra solo tiene sentido cuando lo que se pide se cuenta. En
            // los de sí-o-no (`pide` 1) enseñarla al 0 % es ruido.
            $pide  = max(1, (int) $req['pide']);
            $lleva = min((int) $req['lleva'], $pide);
            $pct   = $pide > 1 ? (int) round($lleva * 100 / $pide) : 0;
            ?>
            <li class="requisito">
              <i class="ph ph-lock-simple" aria-hidden="true"></i>
              <div class="requisito-cuerpo">
                <span class="requisito-texto"><?= htmlspecialchars($req['texto']) ?></span>
                <?php if ($pide > 1): ?>
                  <span class="requisito-barra" role="img"
                        aria-label="<?= $lleva ?> de <?= $pide ?>">
                    <span style="width:<?= $pct ?>%"></span>
                  </span>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

    <?php else: ?>
      <div class="panel-head">
        <h2 class="panel-titulo"><?= htmlspecialchars($sel['nombre']) ?></h2>
        <span class="pastilla"><?= htmlspecialchars($sel['rival'] ?? '') ?></span>
      </div>

      <!-- Ficha pre-partido: solo el rival. Ni su estilo ni sus compos, que es
           lo que evita que el PvE se memorice (§10 del briefing). -->
      <p class="t-body-sm t-dim" style="margin-bottom:var(--space-4);">
        No se sabe con qué alineación saldrá: este equipo tiene varias y elige
        una al azar en cada partido.
      </p>

      <?php if ($sel['progreso']): ?>
        <div class="tabla-wrap" style="margin-bottom:var(--space-4);">
          <table class="tabla">
            <thead>
              <tr>
                <th scope="col">Dificultad</th>
                <th scope="col">Jugado</th>
                <th scope="col">Ganado</th>
                <th scope="col">Mejor rango</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (Tcg::DIFICULTADES as $d): ?>
                <?php $p = $sel['progreso'][$d] ?? null; if (!$p) { continue; } ?>
                <tr>
                  <td><?= $etiquetaDificultad[$d] ?></td>
                  <td><span class="mono"><?= (int) $p['veces'] ?></span></td>
                  <td><span class="mono"><?= (int) $p['victorias'] ?></span></td>
                  <td>
                    <?php if ($p['mejor_rango']): ?>
                      <b class="mono rango-<?= strtolower($p['mejor_rango']) ?>"><?= $p['mejor_rango'] ?></b>
                    <?php else: ?>
                      <span class="t-dim">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <div class="dificultades">
        <?php foreach (Tcg::DIFICULTADES as $d): ?>
          <form method="POST">
            <?= csrfCampo() ?>
            <input type="hidden" name="accion" value="jugar">
            <input type="hidden" name="id_nodo" value="<?= (int) $sel['id_nodo'] ?>">
            <input type="hidden" name="dificultad" value="<?= $d ?>">
            <button type="submit" class="btn btn-ghost dificultad dificultad--<?= $d ?>">
              <span class="dificultad-nombre"><?= $etiquetaDificultad[$d] ?></span>
              <?php if ($rarezaMax[$d] > 0): ?>
                <span class="dificultad-nota">
                  hasta <?= htmlspecialchars($rarezasNombre[$rarezaMax[$d]] ?? '') ?>
                </span>
              <?php endif; ?>
            </button>
          </form>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

</main>

<?php include __DIR__ . '/partials/ceremonia_cofre.php'; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>

<script>
(function () {
  // La ceremonia sustituye al aviso de texto de siempre: se abre justo al
  // reclamar, centrada, así que no depende de por dónde ande el scroll (era
  // el problema real: el botón está al fondo del panel y el aviso salía
  // arriba del todo, fuera de vista — ver CLAUDE.md, sesión de hoy).
  var form = document.getElementById('formReclamarCofre');
  if (!form || typeof SRF === 'undefined' || !SRF.ceremoniaCofre) return;

  var recargarAlCerrar = false;
  document.addEventListener('click', function (e) {
    if (recargarAlCerrar && e.target.closest && e.target.closest('#modalCofre [data-cerrar-modal]')) {
      location.reload();
    }
  });
  document.addEventListener('keydown', function (e) {
    if (recargarAlCerrar && e.key === 'Escape') location.reload();
  });

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    var btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;

    try {
      var res = await fetch('cadena.php?id=<?= $id_cadena ?>', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams(new FormData(form))
      });
      var data = await res.json();

      if (!data.ok) {
        SRF.toast(data.error || 'No se pudo abrir el cofre.', 'danger');
        btn.disabled = false;
        return;
      }

      // El mapa (nodos desbloqueados, sello "Abierto") solo se pone al día
      // recargando: se hace al cerrar la ceremonia, no antes, para no
      // interrumpir el revelado.
      recargarAlCerrar = true;
      SRF.ceremoniaCofre(data);
    } catch (err) {
      console.error(err);
      SRF.toast('No se pudo conectar con el servidor.', 'danger');
      btn.disabled = false;
    }
  });
})();
</script>

</body>
</html>
