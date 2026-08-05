<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = $_SESSION['id_usuario'];

$TAMANO = Tcg::MAZO_TAMANO;

// ----- Acciones (patrón POST → redirección, como el resto del sitio) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $destino = 'mazos.php';

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre !== '') {
            $nuevo = $db->crearMazo($id_usuario, mb_substr($nombre, 0, 60));
            $destino = 'mazos.php?mazo=' . $nuevo;
        }

    } elseif ($accion === 'renombrar') {
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre !== '') {
            $db->renombrarMazo((int) $_POST['id_mazo'], $id_usuario, mb_substr($nombre, 0, 60));
        }
        $destino = 'mazos.php?mazo=' . (int) $_POST['id_mazo'];

    } elseif ($accion === 'eliminar') {
        $db->eliminarMazo((int) $_POST['id_mazo'], $id_usuario);

    } elseif ($accion === 'titular') {
        $db->marcarMazoTitular((int) $_POST['id_mazo'], $id_usuario);
        $destino = 'mazos.php?mazo=' . (int) $_POST['id_mazo'];

    } elseif ($accion === 'guardar_cartas') {
        $idMazo = (int) $_POST['id_mazo'];
        $res = $db->guardarCartasMazo($idMazo, $id_usuario, $_POST['huecos'] ?? []);
        $destino = 'mazos.php?mazo=' . $idMazo . ($res['ok'] ? '&ok=1' : '&error=' . urlencode($res['error']));
    }

    header('Location: ' . $destino);
    exit;
}

$mazos = $db->listarMazosUsuario($id_usuario);

// Mazo en edición: el de la URL si es suyo, y si no ninguno.
$mazoActivo = isset($_GET['mazo']) ? $db->obtenerMazo((int) $_GET['mazo'], $id_usuario) : null;
$cartasMazo = $mazoActivo ? $db->listarCartasMazo($mazoActivo['id_mazo']) : [];
$jugables   = $mazoActivo ? $db->listarColeccionJugable($id_usuario) : [];

// Copias agrupadas por cromo: todas las copias de un mismo jugador son
// intercambiables (mismas estadísticas) y solo una puede estar en la
// alineación a la vez, así que no tiene sentido listar 200 veces la misma
// carta común. Mismo criterio que ya usa coleccion.php con los duplicados;
// aquí no hace falta guardar cuál copia exacta se representa porque da igual
// cuál de ellas se use.
$porCromo = [];
foreach ($jugables as $c) {
    $idCromo = (int) $c['id_cromo'];
    if (!isset($porCromo[$idCromo])) {
        $porCromo[$idCromo] = ['fila' => $c, 'cantidad' => 0];
    }
    $porCromo[$idCromo]['cantidad']++;
}

// Alineación indexada por hueco, para pintar los 11 sitios estén llenos o no.
$alineacion = [];
foreach ($cartasMazo as $c) { $alineacion[(int) $c['hueco']] = $c; }

// Los jugadores ya alineados: un mismo cromo no puede repetirse en el once
// aunque tengas varias copias suyas, así que sus otras copias se bloquean.
$cromosDentro = [];
foreach ($cartasMazo as $c) { $cromosDentro[(int) $c['id_cromo']] = true; }

// Fuerza por líneas: cada carta puntúa con la estadística del hueco donde está,
// no con la mejor que tenga. Es lo que hace que colocar bien importe.
$fuerza = Tcg::fuerzaAlineacion($cartasMazo);

// Cuántas cartas están jugando fuera de su posición natural. No es un error
// (es legal y puede ser deliberado), pero conviene que se vea.
$fueraDePosicion = 0;
foreach ($cartasMazo as $c) {
    if (Tcg::HUECOS[(int) $c['hueco']] !== $c['posicion']) { $fueraDePosicion++; }
}

$etiquetaLinea = ['POR' => 'Portería', 'DF' => 'Defensa', 'MC' => 'Medio', 'DC' => 'Ataque'];
$etiquetaStat  = ['defensa' => 'DEF', 'tecnica' => 'TÉC', 'ataque' => 'ATA'];

$aviso = null;
if (isset($_GET['ok']))    { $aviso = ['tipo' => 'success', 'texto' => 'Mazo guardado.']; }
if (isset($_GET['error'])) { $aviso = ['tipo' => 'danger',  'texto' => $_GET['error']]; }

$paginaTitulo = 'Mazos';
$paginaDesc   = 'Arma tus alineaciones de 11 jugadores para los duelos.';
include __DIR__ . '/partials/head.php';

$activePage = 'mazos';
include __DIR__ . '/navbar.php';
?>

<header class="cabecera">
  <div class="linea-campo" aria-hidden="true"></div>
  <div class="wrap cabecera-contenido">
    <h1>Mazos</h1>
    <p>Una alineación son <?= $TAMANO ?> jugadores. El mazo titular es con el que disputas los duelos.</p>
    <div class="cabecera-datos">
      <div class="dato"><b><?= count($mazos) ?></b><span><?= count($mazos) === 1 ? 'Mazo creado' : 'Mazos creados' ?></span></div>
      <div class="dato"><b><?= count($db->listarColeccionJugable($id_usuario)) ?></b><span>Jugadores disponibles</span></div>
    </div>
  </div>
</header>

<main id="contenido" class="seccion wrap">

  <?php if ($aviso): ?>
    <p class="alerta alerta-<?= $aviso['tipo'] ?>" role="status"><?= htmlspecialchars($aviso['texto']) ?></p>
  <?php endif; ?>

  <div class="con-filtros">

    <!-- Columna de mazos -->
    <div class="stack stack-5">
      <section class="panel">
        <h2 class="t-h3">Tus mazos</h2>

        <?php if (empty($mazos)): ?>
          <p class="t-body-sm t-dim">Todavía no tienes ninguno.</p>
        <?php else: ?>
          <ul class="lista-mazos">
            <?php foreach ($mazos as $m): ?>
              <?php $completo = (int) $m['cartas'] === $TAMANO; ?>
              <li class="mazo-fila<?= $mazoActivo && $mazoActivo['id_mazo'] === $m['id_mazo'] ? ' es-activo' : '' ?>">
                <a href="mazos.php?mazo=<?= $m['id_mazo'] ?>" class="mazo-enlace">
                  <span class="mazo-nombre"><?= htmlspecialchars($m['nombre']) ?></span>
                  <span class="pastilla <?= $completo ? 'pastilla-on' : 'pastilla-warn' ?>">
                    <span class="mono"><?= (int) $m['cartas'] ?>/<?= $TAMANO ?></span>
                  </span>
                </a>
                <?php if ((int) $m['titular'] === 1): ?>
                  <span class="pastilla pastilla-titular">Titular</span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <section class="panel">
        <h2 class="t-h3">Nuevo mazo</h2>
        <form method="POST" class="stack stack-3">
          <input type="hidden" name="accion" value="crear">
          <div class="campo">
            <label for="m-nombre">Nombre</label>
            <input type="text" name="nombre" id="m-nombre" maxlength="60" required
                   placeholder="Ej. Once titular">
          </div>
          <button type="submit" class="btn btn-primary btn-bloque">Crear mazo</button>
        </form>
      </section>
    </div>

    <!-- Editor -->
    <div>
      <?php if (!$mazoActivo): ?>
        <div class="vacio">
          <span class="vacio-ico"><i class="ph ph-list-checks" aria-hidden="true"></i></span>
          <h3>Elige un mazo para editarlo</h3>
          <p>O crea uno nuevo para empezar a armar tu alineación.</p>
        </div>

      <?php else: ?>
        <form method="POST" id="formMazo">
          <input type="hidden" name="accion" value="guardar_cartas">
          <input type="hidden" name="id_mazo" value="<?= $mazoActivo['id_mazo'] ?>">

          <div class="mazo-cabecera">
            <div>
              <h2 class="t-h2"><?= htmlspecialchars($mazoActivo['nombre']) ?></h2>
              <p class="t-body-sm t-dim">
                <span class="mono" id="mazoConteo"><?= count($cartasMazo) ?></span> de
                <span class="mono"><?= $TAMANO ?></span> huecos cubiertos
                <?php if ($fueraDePosicion > 0): ?>
                  · <span class="mono"><?= $fueraDePosicion ?></span>
                  fuera de su posición
                <?php endif; ?>
              </p>
            </div>

            <div class="mazo-totales">
              <?php foreach (['POR', 'DF', 'MC', 'DC'] as $linea): ?>
                <div class="dato">
                  <b class="mono"><?= $fuerza[$linea] ?></b>
                  <span><?= $etiquetaLinea[$linea] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Los 11 huecos, colocados como en un campo de fútbol real: el
               portero abajo, la defensa y el medio en sus líneas, el ataque
               arriba. Cualquier carta puede ir en cualquier hueco (lo que
               cambia es con qué estadística puntúa una vez colocada, ver
               $etiquetaStat más arriba); aquí solo se ve un retrato compacto
               con el nombre, porque a este tamaño la tarjeta completa no
               cabría legible en 11 sitios a la vez. La tarjeta completa, con
               rareza y estadísticas, sigue viéndose en el selector de abajo,
               que es donde de verdad hace falta el detalle para elegir. -->
          <div class="alineacion" id="m-alineacion">
            <?php foreach (Tcg::HUECOS as $i => $linea): ?>
              <?php
              $stat = Tcg::ESTADISTICA_LINEA[$linea];
              $carta = $alineacion[$i] ?? null;
              $desubicado = $carta && $carta['posicion'] !== $linea;
              ?>
              <div class="hueco<?= $carta ? ' esta-lleno' : '' ?><?= $desubicado ? ' es-desubicado' : '' ?>"
                   data-hueco="<?= $i ?>" data-linea="<?= $linea ?>" data-stat="<?= $stat ?>"
                   <?= $carta ? 'data-rareza="' . (int) $carta['id_rareza'] . '"' : '' ?>>
                <input type="hidden" name="huecos[<?= $i ?>]"
                       value="<?= $carta ? (int) $carta['id_coleccion'] : '' ?>">

                <button type="button" class="hueco-boton"
                        aria-label="Hueco de <?= $etiquetaLinea[$linea] ?><?= $carta ? ': ' . htmlspecialchars($carta['nombre']) . ', ' . (int) $carta[$stat] . ' ' . $etiquetaStat[$stat] : ', vacío' ?>">
                  <span class="hueco-avatar">
                    <span class="hueco-avatar-int">
                      <?php if ($carta && $carta['imagen'] !== ''): ?>
                        <img src="<?= htmlspecialchars($carta['imagen']) ?>" alt="" loading="lazy">
                      <?php elseif ($carta): ?>
                        <i class="ph ph-user" aria-hidden="true"></i>
                      <?php else: ?>
                        <i class="ph ph-plus" aria-hidden="true"></i>
                      <?php endif; ?>
                    </span>
                  </span>
                  <span class="hueco-nombre"><?= $carta ? htmlspecialchars($carta['nombre']) : $etiquetaLinea[$linea] ?></span>
                </button>

                <?php if ($carta): ?>
                  <button type="button" class="hueco-quitar" data-quitar="<?= $i ?>"
                          aria-label="Quitar a <?= htmlspecialchars($carta['nombre']) ?> de <?= $etiquetaLinea[$linea] ?>">
                    <i class="ph ph-x" aria-hidden="true"></i>
                  </button>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="mazo-acciones">
            <button type="submit" class="btn btn-primary" id="mazoGuardar">
              Guardar alineación (<span id="mazoConteoBoton"><?= count($cartasMazo) ?></span>/<?= $TAMANO ?>)
            </button>
          </div>

          <div class="campo">
            <label for="m-buscar">Elige un hueco y luego el jugador</label>
            <input type="search" id="m-buscar" placeholder="Buscar entre tus jugadores"
                   autocomplete="off" aria-describedby="m-conteo">
            <span class="campo-hint" id="m-conteo" role="status" aria-live="polite">
              <?= count($porCromo) ?> jugadores disponibles
            </span>
          </div>

          <?php if (empty($porCromo)): ?>
            <div class="vacio">
              <span class="vacio-ico"><i class="ph ph-users" aria-hidden="true"></i></span>
              <h3>No tienes jugadores</h3>
              <p>Los escudos, entrenadores y gerentes no juegan. Abre sobres para conseguir jugadores.</p>
              <a class="btn btn-primary" href="sobres.php">Ir a sobres</a>
            </div>
          <?php else: ?>
            <div class="selector-cartas" id="m-lista" role="group" aria-label="Jugadores disponibles">
              <?php foreach ($porCromo as $idCromo => $grupo): ?>
                <?php
                $c = $grupo['fila'];
                $cantidad = $grupo['cantidad'];
                // bloqueado si este jugador (cualquiera de sus copias) ya está
                // en la alineación; da igual cuál copia concreta se use, todas
                // valen lo mismo
                $bloqueada = isset($cromosDentro[$idCromo]);
                ?>
                <button type="button"
                        class="selector-item<?= $bloqueada ? ' esta-elegida' : '' ?>"
                        data-carta="<?= (int) $c['id_coleccion'] ?>"
                        data-cromo="<?= $idCromo ?>"
                        data-nombre="<?= htmlspecialchars($c['nombre']) ?>"
                        data-equipo="<?= htmlspecialchars($c['equipo']) ?>"
                        data-posicion="<?= htmlspecialchars($c['posicion']) ?>"
                        data-imagen="<?= htmlspecialchars($c['imagen']) ?>"
                        data-rareza="<?= (int) $c['id_rareza'] ?>"
                        data-ataque="<?= (int) $c['ataque'] ?>"
                        data-defensa="<?= (int) $c['defensa'] ?>"
                        data-tecnica="<?= (int) $c['tecnica'] ?>"
                        <?= $bloqueada ? 'disabled' : '' ?>>
                  <?php render_carta($c, [
                      'tamano'   => 'sm',
                      'stats'    => ['ATA' => $c['ataque'], 'DEF' => $c['defensa'], 'TÉC' => $c['tecnica']],
                      'cantidad' => $cantidad,
                  ]); ?>
                </button>
              <?php endforeach; ?>

              <p class="selector-vacio" hidden>Ninguno de tus jugadores coincide con esa búsqueda.</p>
            </div>
          <?php endif; ?>
        </form>

        <div class="mazo-pie">
          <form method="POST" class="fila fila-entre">
            <input type="hidden" name="accion" value="titular">
            <input type="hidden" name="id_mazo" value="<?= $mazoActivo['id_mazo'] ?>">
            <button type="submit" class="btn btn-ghost"
                    <?= (int) $mazoActivo['titular'] === 1 ? 'disabled' : '' ?>>
              <?= (int) $mazoActivo['titular'] === 1 ? 'Ya es tu mazo titular' : 'Usar como titular' ?>
            </button>
          </form>

          <form method="POST" data-confirmar="¿Seguro que quieres borrar este mazo?">
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id_mazo" value="<?= $mazoActivo['id_mazo'] ?>">
            <button type="submit" class="btn btn-plano">Borrar mazo</button>
          </form>
        </div>
      <?php endif; ?>
    </div>

  </div>
</main>

<?php include __DIR__ . '/partials/confirmar.php'; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>

<script src="assets/js/mazos.js"></script>

</body>
</html>
