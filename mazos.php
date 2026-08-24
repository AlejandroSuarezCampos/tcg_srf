<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';
require_once __DIR__ . '/partials/csrf.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = $_SESSION['id_usuario'];

$TAMANO = Tcg::MAZO_TAMANO;

// ----- Acciones (patrón POST → redirección, como el resto del sitio) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        header('Location: mazos.php?error=' . urlencode('La página ha caducado, inténtalo de nuevo.'));
        exit;
    }

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
        $res = $db->guardarCartasMazo(
            $idMazo,
            $id_usuario,
            $_POST['huecos'] ?? [],
            $_POST['formacion'] ?? null
        );
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

// Capa 2. Se calcula sobre la alineación actual del mazo (aunque esté a
// medias) para que el jugador vea en vivo qué compos está activando mientras
// la arma; en el duelo se recalcula sobre la alineación ya congelada.
$compos = $mazoActivo ? $db->calcularCompos($cartasMazo) : null;
$catalogoRasgos = $db->rasgosCatalogo();

// Formación del mazo y las que este jugador puede usar. La lista se valida
// otra vez al guardar: que el selector solo pinte las suyas no basta.
$formacion   = $mazoActivo ? ($mazoActivo['formacion'] ?: Tcg::FORMACION_BASE) : Tcg::FORMACION_BASE;
$disponibles = $db->formacionesDisponibles($id_usuario);
$huecos      = Tcg::huecosDe($formacion);
$coords      = Tcg::coordenadasDe($formacion);

// Huecos y coordenadas de TODAS las formaciones disponibles, para que cambiar
// de formación se vea al instante sin recargar y sin perder lo ya colocado.
// Es dato serializado, no lógica duplicada: lo genera el mismo PHP que manda.
$formacionesJs = [];
foreach ($disponibles as $clave) {
    $huecosDe = Tcg::huecosDe($clave);
    $formacionesJs[$clave] = [
        'nombre' => Tcg::FORMACIONES[$clave]['nombre'],
        'huecos' => $huecosDe,
        'coords' => array_values(Tcg::coordenadasDe($clave)),
        // el peso de cada hueco viaja ya resuelto para que el JS no tenga que
        // llevar su propia copia de PESOS_LINEA
        'pesos'  => array_map(fn($l) => Tcg::PESOS_LINEA[$l], $huecosDe),
    ];
}

// Fuerza por líneas: cada carta puntúa según los pesos del hueco donde está,
// no con la mejor estadística que tenga. Es lo que hace que colocar bien importe.
$fuerza = Tcg::fuerzaAlineacion($cartasMazo, $formacion);

// Cuántas cartas están jugando fuera de su posición natural. No es un error
// (es legal y puede ser deliberado), pero conviene que se vea.
$fueraDePosicion = 0;
foreach ($cartasMazo as $c) {
    if ($huecos[(int) $c['hueco']] !== $c['posicion']) { $fueraDePosicion++; }
}

$etiquetaLinea = Tcg::ETIQUETA_LINEA;   // la tabla vive en Tcg, no copiada aquí

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

  <?php /* Los mazos y el alta van ARRIBA, a lo ancho, no en una columna
           estrecha a la izquierda. En columna, con el taller de dos paneles al
           lado, la lista bajaba de ~260px y los nombres largos y las insignias
           se cortaban; y encima robaba anchura al campo, que es lo que hay que
           ver. Arriba caben en fila y el taller se queda con la página entera. */ ?>
  <div class="mazos-cabecera">

    <div class="mazos-cabecera-lista">
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
                  <span class="mazo-nombre">
                    <span class="mazo-nombre-texto"><?= htmlspecialchars($m['nombre']) ?></span>
                    <span class="t-caption t-dim mono mazo-formacion"><?= htmlspecialchars(
                        Tcg::FORMACIONES[$m['formacion']]['nombre'] ?? $m['formacion']) ?></span>
                  </span>
                  <span class="mazo-insignias">
                    <span class="pastilla <?= $completo ? 'pastilla-on' : 'pastilla-warn' ?>">
                      <span class="mono"><?= (int) $m['cartas'] ?>/<?= $TAMANO ?></span>
                    </span>
                    <?php if ((int) $m['titular'] === 1): ?>
                      <span class="pastilla pastilla-titular">Titular</span>
                    <?php endif; ?>
                  </span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    </div>

    <div class="mazos-cabecera-nuevo">
      <section class="panel">
        <h2 class="t-h3">Nuevo mazo</h2>
        <form method="POST" class="stack stack-3">
          <?= csrfCampo() ?>
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

  </div>

  <!-- Editor -->
  <div class="mazos-editor">
      <?php if (!$mazoActivo): ?>
        <div class="vacio">
          <span class="vacio-ico"><i class="ph ph-list-checks" aria-hidden="true"></i></span>
          <h3>Elige un mazo para editarlo</h3>
          <p>O crea uno nuevo para empezar a armar tu alineación.</p>
        </div>

      <?php else: ?>
        <form method="POST" id="formMazo">
          <?= csrfCampo() ?>
          <input type="hidden" name="accion" value="guardar_cartas">
          <input type="hidden" name="id_mazo" value="<?= $mazoActivo['id_mazo'] ?>">

          <div class="mazo-cabecera">
            <div>
              <h2 class="t-h2"><?= htmlspecialchars($mazoActivo['nombre']) ?></h2>
              <p class="t-body-sm t-dim">
                <span class="mono" id="mazoConteo"><?= count($cartasMazo) ?></span> de
                <span class="mono"><?= $TAMANO ?></span> huecos cubiertos
                <span id="mazoDesubicadosBloque" <?= $fueraDePosicion > 0 ? '' : 'hidden' ?>>
                  · <span class="mono" id="mazoDesubicados"><?= $fueraDePosicion ?></span>
                  fuera de su posición
                </span>
              </p>

              <!-- Cambiar de formación NO mueve las cartas: los once siguen en
                   sus huecos y lo que cambia es con qué estadística puntúa cada
                   uno. Por eso se puede probar una formación y volver atrás sin
                   perder la alineación. Se guarda con el botón de abajo. -->
              <div class="campo campo-formacion">
                <label for="m-formacion">Formación</label>
                <select name="formacion" id="m-formacion">
                  <?php foreach ($disponibles as $clave): ?>
                    <option value="<?= $clave ?>" <?= $clave === $formacion ? 'selected' : '' ?>>
                      <?= htmlspecialchars(Tcg::FORMACIONES[$clave]['nombre']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if (count($disponibles) < count(Tcg::FORMACIONES)): ?>
                  <span class="campo-hint">
                    Te quedan <span class="mono"><?= count(Tcg::FORMACIONES) - count($disponibles) ?></span>
                    por desbloquear jugando cadenas.
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <div class="mazo-totales">
              <?php foreach (['POR', 'DF', 'MC', 'DC'] as $linea): ?>
                <div class="dato">
                  <b class="mono"><?= (int) round($fuerza[$linea]) ?></b>
                  <span><?= $etiquetaLinea[$linea] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <?php /* EL TALLER, EN DOS PANELES
                   Antes esto era una columna única y muy larga: campo, compos,
                   guardar y —al final del todo— el buscador y los jugadores.
                   Elegir a alguien obligaba a bajar hasta abajo, colocarlo, y
                   volver a subir para ver el campo. Ahora el campo se queda
                   fijo a la izquierda y la selección vive a la derecha con su
                   propio scroll, así que se ven las dos cosas a la vez y
                   colocar once jugadores no son veintidós viajes de scroll.
                   Por debajo de 1100px vuelve a apilarse, que en móvil es lo
                   correcto. */ ?>
          <div class="mazo-taller">

          <div class="mazo-taller-campo">

          <!-- Los 11 huecos, colocados como en un campo de fútbol real: el
               portero abajo, la defensa y el medio en sus líneas, el ataque
               arriba. Cualquier carta puede ir en cualquier hueco (lo que
               cambia es cuánto puntúa una vez colocada, ver Tcg::PESOS_LINEA);
               aquí solo se ve un retrato compacto con el nombre, porque a este
               tamaño la tarjeta completa no cabría legible en 11 sitios a la
               vez. La tarjeta completa, con rareza y estadísticas, sigue
               viéndose en el selector de abajo, que es donde de verdad hace
               falta el detalle para elegir.

               Las coordenadas vienen de Tcg::coordenadasDe(), no del CSS: con
               ocho formaciones el CSS tendría 88 reglas que mantener a mano en
               sincronía con el orden de los huecos. -->
          <div class="alineacion" id="m-alineacion"
               data-formaciones="<?= htmlspecialchars(json_encode($formacionesJs), ENT_QUOTES) ?>">
            <?php foreach ($huecos as $i => $linea): ?>
              <?php
              $carta = $alineacion[$i] ?? null;
              $desubicado = $carta && $carta['posicion'] !== $linea;
              $aporte = $carta ? (int) round(Tcg::aportarCarta($carta, $linea)) : null;
              ?>
              <?php
              /* La ficha que sale al pasar el ratón. Se manda ya resuelta
                 desde aquí y no se recalcula en el navegador: `aporte` y la
                 comparación posición/línea ya están hechas arriba con las
                 MISMAS funciones que puntúan el duelo (Tcg::aportarCarta),
                 así que lo que se lee en la ficha no puede discrepar de lo
                 que vale la carta de verdad. */
              $detalle = $carta ? json_encode([
                  'nombre'    => $carta['nombre'],
                  'posicion'  => $carta['posicion'],
                  'linea'     => $linea,
                  'lineaTexto'=> $etiquetaLinea[$linea],
                  'desubicado'=> $desubicado,
                  'equipo'    => $carta['equipo'],
                  'rareza'    => $carta['rareza'],
                  'idRareza'  => (int) $carta['id_rareza'],
                  'afinidad'  => $carta['afinidad'],
                  'afinidadImg' => $carta['afinidad_imagen'],
                  'rasgo'     => $carta['rasgo'],
                  'ataque'    => (int) $carta['ataque'],
                  'defensa'   => (int) $carta['defensa'],
                  'tecnica'   => (int) $carta['tecnica'],
                  'aporte'    => $aporte,
                  'pesos'     => Tcg::PESOS_LINEA[$linea],
                  // Cuánto rinde por estar (o no) en su puesto: 1,00 en su
                  // sitio, menos cuanto más lejos. La ficha lo dice cuando
                  // no es 1, que es cuando explica un número bajo.
                  'rendimiento' => Tcg::rendimientoPuesto($carta['posicion'], $linea),
              ], JSON_UNESCAPED_UNICODE) : null;
              ?>
              <div class="hueco<?= $carta ? ' esta-lleno' : '' ?><?= $desubicado ? ' es-desubicado' : '' ?>"
                   style="left:<?= $coords[$i]['x'] ?>%; top:<?= $coords[$i]['y'] ?>%;"
                   data-hueco="<?= $i ?>" data-linea="<?= $linea ?>"
                   data-pesos="<?= htmlspecialchars(json_encode(Tcg::PESOS_LINEA[$linea]), ENT_QUOTES) ?>"
                   <?= $detalle ? 'data-detalle="' . htmlspecialchars($detalle, ENT_QUOTES) . '"' : '' ?>
                   <?= $carta ? 'data-rareza="' . (int) $carta['id_rareza'] . '"' : '' ?>>
                <input type="hidden" name="huecos[<?= $i ?>]"
                       value="<?= $carta ? (int) $carta['id_coleccion'] : '' ?>">

                <button type="button" class="hueco-boton"
                        aria-label="Hueco de <?= $etiquetaLinea[$linea] ?><?= $carta ? ': ' . htmlspecialchars($carta['nombre']) . ', ' . $aporte . ' puntos' : ', vacío' ?>">
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

            <?php /* LA FICHA AL VUELO. Uno solo para los once huecos: se
                     rellena y se recoloca al pasar por cada uno. Vive DENTRO
                     de .alineacion para poder posicionarse en % sobre el campo
                     igual que los huecos, sin medir nada con getBoundingRect.

                     `aria-hidden` porque no aporta nada a un lector de
                     pantalla: el `aria-label` del botón del hueco ya dice
                     nombre, línea y puntos. Esto es ayuda visual, y duplicarlo
                     en la capa de accesibilidad sería leer dos veces lo mismo. */ ?>
            <div class="hueco-ficha" id="m-huecoFicha" hidden aria-hidden="true"></div>
          </div>

          <!-- CAPA 2 — COMPOS
               Se recalcula al guardar, no en vivo con cada clic: el cálculo
               vive en servidor (es el mismo que resuelve el duelo) y duplicarlo
               en JavaScript sería tener dos fuentes de la misma verdad, que es
               justo como se desincronizan estas cosas. -->
          <section class="compos" aria-labelledby="composTitulo">
            <div class="compos-cabecera">
              <h3 id="composTitulo" class="t-h3">Compos activas</h3>
              <p class="t-caption t-dim">Se recalculan al guardar la alineación</p>
            </div>

            <?php if (empty($compos['activos'])): ?>
              <p class="t-body-sm t-dim">
                Ninguna todavía. Repite un mismo elemento o rasgo en al menos
                <span class="mono">2</span> jugadores del once para activar su primer nivel.
              </p>
            <?php else: ?>
              <ul class="compos-lista">
                <?php foreach ($compos['activos'] as $clave => $info): ?>
                  <?php $r = $catalogoRasgos[$clave] ?? null; if (!$r) continue; ?>
                  <li class="compo compo--<?= htmlspecialchars($r['tipo']) ?>">
                    <span class="compo-nombre"><?= htmlspecialchars($r['nombre']) ?></span>
                    <span class="compo-nivel" aria-label="Nivel <?= $info['nivel'] ?> de 3">
                      <?php for ($n = 1; $n <= 3; $n++): ?>
                        <span class="compo-punto<?= $n <= $info['nivel'] ? ' esta-lleno' : '' ?>"></span>
                      <?php endfor; ?>
                    </span>
                    <span class="compo-detalle t-dim">
                      <span class="mono"><?= $info['copias'] ?></span> en el once
                      <?php if ($info['pct'] > 0): ?>
                        · <span class="mono">+<?= number_format($info['pct'], 2, ',', '.') ?> %</span>
                        <?= htmlspecialchars($etiquetaLinea[$r['linea_1']] ?? '') ?><?php
                          if ($r['linea_2']) echo ' y ' . htmlspecialchars($etiquetaLinea[$r['linea_2']]); ?>
                      <?php endif; ?>
                    </span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <div class="compos-resumen">
              <div class="dato">
                <b class="mono"><?= $compos['afinidad_dom'] === 'neutro'
                    ? 'Neutro'
                    : htmlspecialchars($catalogoRasgos[$compos['afinidad_dom']]['nombre'] ?? '—') ?></b>
                <span>Afinidad dominante</span>
              </div>
              <div class="dato">
                <b class="mono"><?= (int) $compos['tension_nivel'] ?>/3</b>
                <span>Tensión</span>
              </div>
              <div class="dato">
                <b class="mono"><?= number_format($compos['rareza_index'], 2, ',', '.') ?></b>
                <span>Rareza media</span>
              </div>
              <div class="dato<?= $compos['malus'] > 0 ? ' es-malo' : '' ?>">
                <b class="mono"><?= $compos['malus'] > 0
                    ? '−' . number_format($compos['malus'], 2, ',', '.') . ' %'
                    : '0 %' ?></b>
                <span>Malus de coherencia</span>
              </div>
            </div>

            <?php if ($compos['malus'] > 0): ?>
              <p class="alerta alerta-warning" role="status">
                <i class="ph ph-warning" aria-hidden="true"></i>
                <span>
                  Tu once es más raro de lo que sus compos justifican, y eso resta
                  <b class="mono"><?= number_format($compos['malus'], 2, ',', '.') ?> %</b>
                  a tu fuerza total. Repite más elementos o rasgos entre tus once
                  para compensarlo.
                </span>
              </p>
            <?php endif; ?>

            <?php if ($compos['tension_nivel'] > 0): ?>
              <?php $probs = $db->probabilidadesTier($compos['tension_nivel']); ?>
              <p class="t-caption t-dim">
                Tensión <?= (int) $compos['tension_nivel'] ?>: no suma fuerza, pero mejora
                tu sorteo de Aumento a Plata <span class="mono"><?= (int) $probs['plata'] ?> %</span> ·
                Oro <span class="mono"><?= (int) $probs['oro'] ?> %</span> ·
                Prisma <span class="mono"><?= (int) $probs['prisma'] ?> %</span>.
              </p>
            <?php endif; ?>
          </section>

          <div class="mazo-acciones">
            <button type="submit" class="btn btn-primary" id="mazoGuardar">
              Guardar alineación (<span id="mazoConteoBoton"><?= count($cartasMazo) ?></span>/<?= $TAMANO ?>)
            </button>
          </div>

          </div><!-- /.mazo-taller-campo -->

          <?php /* PANEL DE SELECCIÓN
                   Selector horizontal, no rejilla de tarjetas: en una fila
                   caben el arte, el nombre, el equipo, la posición, la rareza,
                   la COMPO y las tres estadísticas a la vez. En la rejilla
                   anterior había que abrir cada carta —o adivinar— para saber
                   si un jugador sumaba a una compo. */ ?>
          <aside class="mazo-taller-selector" aria-label="Jugadores disponibles">

            <div class="mazo-selector-cabecera">
              <h3 class="t-h3">Tus jugadores</h3>
              <p class="t-caption t-dim">Elige un hueco y luego el jugador</p>
            </div>

            <?php if (empty($porCromo)): ?>
              <div class="vacio">
                <span class="vacio-ico"><i class="ph ph-users" aria-hidden="true"></i></span>
                <h3>No tienes jugadores</h3>
                <p>Los escudos, entrenadores y gerentes no juegan. Abre sobres para conseguir jugadores.</p>
                <a class="btn btn-primary" href="sobres.php">Ir a sobres</a>
              </div>
            <?php else: ?>

              <?php
              /* Los desplegables se construyen a partir de lo que el jugador
                 TIENE de verdad, no del catálogo entero: filtrar por un equipo
                 del que no tienes a nadie solo sirve para vaciar la lista. */
              $equiposMazo = $posicionesMazo = $afinidadesMazo = [];
              foreach ($porCromo as $g) {
                  $f = $g['fila'];
                  if ($f['equipo'] !== '')   { $equiposMazo[$f['equipo']] = true; }
                  if ($f['posicion'] !== '') { $posicionesMazo[$f['posicion']] = true; }
                  if (!empty($f['afinidad']) && strcasecmp($f['afinidad'], 'No-afi') !== 0) {
                      $afinidadesMazo[$f['afinidad']] = true;
                  }
              }
              ksort($equiposMazo); ksort($afinidadesMazo);
              // Las posiciones se ordenan por el campo (portero → delantero),
              // no alfabéticamente: DC, DF, MC, POR no le dice nada a nadie.
              $ordenPos = ['POR' => 0, 'DF' => 1, 'MC' => 2, 'DC' => 3];
              uksort($posicionesMazo, fn($a, $b) => ($ordenPos[$a] ?? 9) <=> ($ordenPos[$b] ?? 9));
              ?>

              <div class="mazo-filtros">
                <div class="campo">
                  <label for="m-buscar">Buscar</label>
                  <input type="search" id="m-buscar" placeholder="Nombre o equipo"
                         autocomplete="off" aria-describedby="m-conteo">
                </div>

                <div class="mazo-filtros-fila">
                  <div class="campo">
                    <label for="m-f-posicion">Posición</label>
                    <select id="m-f-posicion" class="m-filtro" data-campo="posicion">
                      <option value="">Todas</option>
                      <?php foreach (array_keys($posicionesMazo) as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($etiquetaLinea[$p] ?? $p) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="campo">
                    <label for="m-f-afinidad">Afinidad</label>
                    <select id="m-f-afinidad" class="m-filtro" data-campo="afinidad">
                      <option value="">Todas</option>
                      <?php foreach (array_keys($afinidadesMazo) as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="mazo-filtros-fila">
                  <div class="campo">
                    <label for="m-f-equipo">Equipo</label>
                    <select id="m-f-equipo" class="m-filtro" data-campo="equipo">
                      <option value="">Todos</option>
                      <?php foreach (array_keys($equiposMazo) as $eq): ?>
                        <option value="<?= htmlspecialchars($eq) ?>"><?= htmlspecialchars($eq) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="campo">
                    <label for="m-f-rareza">Rareza</label>
                    <select id="m-f-rareza" class="m-filtro" data-campo="rareza">
                      <option value="">Todas</option>
                      <?php foreach ($db->listarRarezas() as $r): ?>
                        <option value="<?= (int) $r['id_rareza'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <?php /* ORDENAR POR ESTADÍSTICA.

                         Va en su propia fila y NO lleva `.m-filtro`: los
                         filtros esconden filas y este las recoloca, así que
                         compartir clase con ellos lo metería en el `every()`
                         de filtrar() y no coincidiría con nada — todas las
                         cartas desaparecerían al elegir un orden.

                         Un solo desplegable con las seis combinaciones en vez
                         de "campo" + "dirección": son dos clics para lo mismo,
                         y en móvil dos desplegables ocupan una fila entera. */ ?>
                <div class="mazo-filtros-fila">
                  <div class="campo campo-ancho">
                    <label for="m-orden">Ordenar por</label>
                    <select id="m-orden">
                      <option value="">Sin ordenar (los de tu colección)</option>
                      <option value="ataque-desc">Ataque · de mayor a menor</option>
                      <option value="ataque-asc">Ataque · de menor a mayor</option>
                      <option value="defensa-desc">Defensa · de mayor a menor</option>
                      <option value="defensa-asc">Defensa · de menor a mayor</option>
                      <option value="tecnica-desc">Técnica · de mayor a menor</option>
                      <option value="tecnica-asc">Técnica · de menor a mayor</option>
                    </select>
                  </div>
                </div>

                <div class="mazo-filtros-pie">
                  <span class="t-caption t-dim" id="m-conteo" role="status" aria-live="polite">
                    <?= count($porCromo) ?> jugadores disponibles
                  </span>
                  <button type="button" class="btn btn-plano btn-sm" id="m-limpiar">Quitar filtros</button>
                </div>
              </div>

              <ul class="carta-lista mazo-selector-lista" id="m-lista">
                <?php foreach ($porCromo as $idCromo => $grupo): ?>
                  <?php
                  $c = $grupo['fila'];
                  $cantidad = $grupo['cantidad'];
                  // bloqueado si este jugador (cualquiera de sus copias) ya está
                  // en la alineación; da igual cuál copia concreta se use, todas
                  // valen lo mismo
                  $bloqueada = isset($cromosDentro[$idCromo]);

                  render_carta_fila($c, [
                      'accion'      => true,
                      'elegida'     => $bloqueada,
                      'desactivada' => $bloqueada,
                      'meta'        => htmlspecialchars($c['equipo'])
                          . ($cantidad > 1 ? ' · ×' . (int) $cantidad : ''),
                      'datos'       => [
                          'carta'  => (int) $c['id_coleccion'],
                          'cromo'  => $idCromo,
                          // mazos.js lee estos tres para repintar el hueco
                          'ataque'  => (int) $c['ataque'],
                          'defensa' => (int) $c['defensa'],
                          'tecnica' => (int) $c['tecnica'],
                      ],
                  ]);
                  ?>
                <?php endforeach; ?>

                <li class="selector-vacio" hidden>Ninguno de tus jugadores coincide con esos filtros.</li>
              </ul>
            <?php endif; ?>
          </aside>

          </div><!-- /.mazo-taller -->
        </form>

        <?php include __DIR__ . '/partials/mazos_ayuda.php'; ?>

        <div class="mazo-pie">
          <form method="POST" class="fila fila-entre">
            <?= csrfCampo() ?>
            <input type="hidden" name="accion" value="titular">
            <input type="hidden" name="id_mazo" value="<?= $mazoActivo['id_mazo'] ?>">
            <?php /* `id` para que el tutorial pueda señalarlo. Es el último
                     paso del mazo y el que desbloquea el resto de la vuelta:
                     sin un ancla concreta, el globo apuntaba al pie entero. */ ?>
            <button type="submit" class="btn btn-ghost" id="mazoTitular"
                    <?= (int) $mazoActivo['titular'] === 1 ? 'disabled' : '' ?>>
              <?= (int) $mazoActivo['titular'] === 1 ? 'Ya es tu mazo titular' : 'Usar como titular' ?>
            </button>
          </form>

          <form method="POST" data-confirmar="¿Seguro que quieres borrar este mazo?">
            <?= csrfCampo() ?>
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id_mazo" value="<?= $mazoActivo['id_mazo'] ?>">
            <button type="submit" class="btn btn-plano">Borrar mazo</button>
          </form>
        </div>
      <?php endif; ?>
  </div><!-- /.mazos-editor -->
</main>

<?php include __DIR__ . '/partials/confirmar.php'; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>

<?= assetScript($base ?? '', 'assets/js/mazos.js') ?>

</body>
</html>
