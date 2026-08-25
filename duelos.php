<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';
require_once __DIR__ . '/partials/cabecera.php';
require_once __DIR__ . '/partials/csrf.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = $_SESSION['id_usuario'];

// El duelo se juega SIEMPRE con el mazo titular. Si no hay uno completo, no se
// puede ni crear ni aceptar nada: se dice aquí, una vez, en vez de dejar que
// falle cada acción por su cuenta.
$titular = $db->obtenerMazoTitular($id_usuario);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfValido($_POST['csrf'] ?? null)) {
    $error = 'La página ha caducado, inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $tipo = $_POST['tipo_apuesta'] === 'carta' ? 'carta' : 'monedas';
        /* `id_coleccion` llega como lista: desde la 031 se apuesta un lote.
           El saneado de verdad (rareza, disponibilidad, techo) lo hace
           crearDuelo(); aquí solo se pasa lo que venga, sin fiarse de nada. */
        $cartasElegidas = (array) ($_POST['id_coleccion'] ?? []);
        $res = $db->crearDuelo(
            $id_usuario,
            $tipo,
            (int) ($_POST['monedas'] ?? 0),
            $tipo === 'carta' ? (int) ($_POST['id_rareza'] ?? 0) : null,
            $tipo === 'carta' ? $cartasElegidas : null
        );
        if ($res['ok']) {
            header('Location: duelo.php?id=' . $res['id_duelo']);
            exit;
        }
        $error = $res['error'];

    } elseif ($accion === 'aceptar') {
        $res = $db->aceptarDuelo(
            (int) $_POST['id_duelo'],
            $id_usuario,
            (array) ($_POST['id_coleccion'] ?? [])
        );
        if ($res['ok']) {
            header('Location: duelo.php?id=' . $res['id_duelo']);
            exit;
        }
        $error = $res['error'];
    }
}

/* Red de seguridad del bote (§8, todo perezoso). Un partido en juego tiene el
   dinero de los dos RETENIDO hasta que alguien lo liquide, y si los dos cierran
   la pestaña a mitad no queda nadie que lo haga. Volver a la lista de duelos es
   lo primero que hace cualquiera al reaparecer, así que aquí se cierran los
   partidos propios que ya han terminado. */
$db->cerrarPartidosPendientes($id_usuario);

$abiertos  = $db->listarDuelosAbiertos($id_usuario);
$misDuelos = $db->listarMisDuelos($id_usuario, 12);
$rarezas   = $db->listarRarezas();
$saldo     = (int) ($db->obtenerUsuario($id_usuario)['monedas'] ?? 0);

/* Copias apostables, APILADAS POR CROMO y agrupadas por rareza.
   Sin apilar, alguien con la colección hecha veía una lista de cientos de
   filas con el mismo jugador repetido decenas de veces. Todas las copias de un
   cromo valen exactamente lo mismo —mismas estadísticas, misma rareza—, así
   que da igual cuál se apueste: se enseña una fila con "×N" y se manda la
   primera copia. Mismo criterio que ya usan coleccion.php y mazos.php. */
$apostablesPorRareza = [];
$porCromoApostable   = [];
foreach ($db->listarCopiasApostables($id_usuario) as $c) {
    $idCromo = (int) $c['id_cromo'];
    if (!isset($porCromoApostable[$idCromo])) {
        $porCromoApostable[$idCromo] = ['fila' => $c, 'cantidad' => 0];
    }
    $porCromoApostable[$idCromo]['cantidad']++;
}
foreach ($porCromoApostable as $g) {
    $g['fila']['cantidad'] = $g['cantidad'];
    $apostablesPorRareza[(int) $g['fila']['id_rareza']][] = $g['fila'];
}

// Para los filtros del selector: solo lo que el jugador tiene de verdad.
$equiposApostables = $posicionesApostables = [];
foreach ($porCromoApostable as $g) {
    $f = $g['fila'];
    if ($f['equipo'] !== '')   { $equiposApostables[$f['equipo']] = true; }
    if ($f['posicion'] !== '') { $posicionesApostables[$f['posicion']] = true; }
}
ksort($equiposApostables);
$ordenPosDuelo = ['POR' => 0, 'DF' => 1, 'MC' => 2, 'DC' => 3];
uksort($posicionesApostables, fn($a, $b) => ($ordenPosDuelo[$a] ?? 9) <=> ($ordenPosDuelo[$b] ?? 9));
$etiquetaPosDuelo = Tcg::ETIQUETA_LINEA;   // la tabla vive en Tcg, no copiada aquí

/* Techo de cartas por apuesta. Se lee de configuración, no se fija aquí: es
   una decisión de equilibrio y el panel puede cambiarla sin tocar código. */
$maxCartasApuesta = max(1, (int) $db->config('duelo_cartas_max', 5));

$fuerzaTitular = $titular
    ? Tcg::fuerzaAlineacion($db->listarCartasMazo($titular['id_mazo']), $titular['formacion'])
    : null;

$paginaTitulo = 'Duelos';
$paginaDesc   = 'Reta a otro entrenador con tu alineación titular.';
include __DIR__ . '/partials/head.php';

$activePage = 'duelos';
include __DIR__ . '/navbar.php';
?>

<?php
$datosDuelos = [[number_format($saldo, 0, ',', '.'), 'monedas']];
if ($fuerzaTitular) {
  $datosDuelos[] = [(int) round($fuerzaTitular['total']), 'fuerza de tu once'];
}
$datosDuelos[] = [count($abiertos), count($abiertos) === 1 ? 'sala abierta' : 'salas abiertas'];

cabecera([
  'rotulo' => 'Jugar',
  'titulo' => 'Duelos',
  'texto'  => 'Se juega con tu alineación titular. Se congela al entrar: lo que cambies después no afecta al duelo.',
  'datos'  => $datosDuelos,
]);
?>

<main id="contenido" class="seccion wrap">

  <?php if ($error !== ''): ?>
    <p class="alerta alerta-danger" role="alert"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <?php if (!$titular): ?>
    <div class="vacio">
      <span class="vacio-ico"><i class="ph ph-list-checks" aria-hidden="true"></i></span>
      <h3>Necesitas un mazo titular</h3>
      <p>Los duelos se juegan con la alineación que marques como titular, y tiene que tener los 11 huecos cubiertos.</p>
      <a class="btn btn-primary" href="mazos.php">Ir a mazos</a>
    </div>

  <?php else: ?>

    <?php /* Sin borde: el panel ya se distingue por su fondo, y el marco
             alrededor de un formulario largo era justo lo que hacía que la
             pantalla se leyera como "un formulario" y no como parte del juego. */ ?>
    <section class="panel panel--sin-borde" style="margin-bottom:var(--e-6);">
      <div class="panel-head">
        <h2 class="panel-titulo">Abrir una sala</h2>
        <span class="pastilla pastilla-titular"><?= htmlspecialchars($titular['nombre']) ?></span>
      </div>

      <p class="t-body-sm t-dim" style="margin-bottom:var(--e-4);">
        Al abrirla te quedas dentro esperando rival. Si sales de la sala, se cancela y recuperas lo apostado.
      </p>

      <form method="POST" class="stack stack-4" id="formCrearDuelo">
        <?= csrfCampo() ?>
        <input type="hidden" name="accion" value="crear">

        <?php /* Qué se apuesta: dos opciones grandes con su consecuencia
                 escrita, no dos radios en una lista. Es la decisión más
                 importante de la pantalla y antes tenía el mismo peso visual
                 que un campo cualquiera. El input sigue siendo un radio real
                 —oculto, no falso—, así que el teclado y el envío funcionan
                 igual sin JavaScript. */ ?>
        <fieldset class="campo">
          <legend class="campo-label">Qué se apuesta</legend>
          <div class="apuesta-opciones">
            <label class="apuesta-opcion">
              <input type="radio" name="tipo_apuesta" value="monedas" class="sr-only" checked data-tipo>
              <span class="apuesta-opcion-ico"><i class="ph-fill ph-coins" aria-hidden="true"></i></span>
              <span class="apuesta-opcion-texto">
                <b>Monedas</b>
                <span class="t-caption t-dim">Los dos ponéis la misma cantidad. El ganador se lleva el bote.</span>
              </span>
            </label>
            <label class="apuesta-opcion">
              <input type="radio" name="tipo_apuesta" value="carta" class="sr-only" data-tipo>
              <span class="apuesta-opcion-ico"><i class="ph-fill ph-cards" aria-hidden="true"></i></span>
              <span class="apuesta-opcion-texto">
                <b>Una carta</b>
                <span class="t-caption t-dim">Los dos ponéis una de la misma rareza. La que pierde cambia de dueño.</span>
              </span>
            </label>
          </div>
        </fieldset>

        <div class="campo" data-bloque="monedas">
          <label for="d-monedas">Monedas por cabeza</label>
          <input type="number" name="monedas" id="d-monedas" min="1" step="1" value="100"
                 max="<?= $saldo ?>" aria-describedby="d-monedas-hint">
          <span class="campo-hint" id="d-monedas-hint">
            Tu rival apostará lo mismo. Tienes <span class="mono"><?= number_format($saldo, 0, ',', '.') ?></span> monedas.
          </span>
        </div>

        <div class="campo" data-bloque="carta" hidden>
          <span class="campo-label">Cuáles apuestas</span>

          <?php /* La rareza ERA un campo aparte ("Rareza en juego") encima de
                   este bloque, y sobraba: elegía lo mismo que ahora elige el
                   primer filtro —qué cartas se ven— pero desde otro sitio, así
                   que había dos controles para una decisión. Ahora es uno solo:
                   filtra la lista Y viaja en el formulario como la rareza
                   pactada de la sala. Puede hacer los dos papeles porque todas
                   las cartas de una apuesta son forzosamente de la misma
                   rareza: es la condición del duelo.
                   Los demás filtros existen porque con la colección hecha "las
                   cartas de rareza X" siguen siendo demasiadas para elegir a
                   ojo. */ ?>
          <div class="apuesta-filtros">
            <div class="campo">
              <label for="d-rareza">Rareza</label>
              <select name="id_rareza" id="d-rareza">
                <?php foreach ($rarezas as $r): ?>
                  <?php $n = count($apostablesPorRareza[(int) $r['id_rareza']] ?? []); ?>
                  <option value="<?= $r['id_rareza'] ?>" <?= $n === 0 ? 'disabled' : '' ?>>
                    <?= htmlspecialchars($r['nombre']) ?> — <?= $n ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="campo">
              <label for="d-buscar">Buscar</label>
              <input type="search" id="d-buscar" placeholder="Nombre o equipo" autocomplete="off">
            </div>
            <div class="campo">
              <label for="d-f-posicion">Posición</label>
              <select id="d-f-posicion" class="d-filtro" data-campo="posicion">
                <option value="">Todas</option>
                <?php foreach (array_keys($posicionesApostables) as $p): ?>
                  <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($etiquetaPosDuelo[$p] ?? $p) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="campo">
              <label for="d-f-equipo">Equipo</label>
              <select id="d-f-equipo" class="d-filtro" data-campo="equipo">
                <option value="">Todos</option>
                <?php foreach (array_keys($equiposApostables) as $eq): ?>
                  <option value="<?= htmlspecialchars($eq) ?>"><?= htmlspecialchars($eq) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <p class="apuesta-contador" id="d-contador" data-max="<?= $maxCartasApuesta ?>" role="status" aria-live="polite">
            <span class="mono" id="d-contador-n">0</span> de <?= $maxCartasApuesta ?> elegidas
            · <span class="t-dim">tu rival tendrá que poner las mismas</span>
          </p>

          <?php /* Lista horizontal, no rejilla de miniaturas: para decidir qué
                   cartas arriesgas hacen falta sus estadísticas y su compo a la
                   vista, que es justo lo que la miniatura no daba.
                   `role="group"` y no `radiogroup`: desde que se pueden apostar
                   varias, las opciones no son excluyentes y anunciarlas como si
                   lo fueran engañaría a un lector de pantalla. */ ?>
          <ul class="carta-lista carta-lista--apuesta" id="d-cartas" role="group" aria-label="Cartas que puedes apostar">
            <?php foreach ($apostablesPorRareza as $idRareza => $copias): ?>
              <?php foreach ($copias as $c): ?>
                <?php /* La rareza para filtrar ya la escribe el componente en
                         `data-rareza`; no hace falta un atributo aparte.
                         ponytail: una fila apilada apuesta UNA copia de ese
                         jugador, no N. Para poner dos copias del mismo cromo
                         haría falta un contador por fila; si alguien lo pide,
                         ahí es donde va. */ ?>
                <?php render_carta_fila($c, [
                    'radio' => ['name' => 'id_coleccion[]', 'value' => (int) $c['id_coleccion'],
                                'tipo' => 'checkbox'],
                    'meta'  => htmlspecialchars($c['equipo'])
                        . ((int) $c['cantidad'] > 1 ? ' · ×' . (int) $c['cantidad'] : ''),
                ]); ?>
              <?php endforeach; ?>
            <?php endforeach; ?>
            <li class="selector-vacio" hidden>Ninguna carta libre coincide con esos filtros.</li>
          </ul>
        </div>

        <div><button type="submit" class="btn btn-primary">Abrir sala</button></div>
      </form>
    </section>

    <section style="margin-bottom:var(--e-6);">
      <h2 class="t-h3" style="margin-bottom:var(--e-4);">Salas abiertas</h2>

      <?php if (empty($abiertos)): ?>
        <div class="vacio">
          <span class="vacio-ico"><i class="ph ph-users-three" aria-hidden="true"></i></span>
          <h3>No hay nadie esperando</h3>
          <p>Abre tú una sala y espera a que entre alguien.</p>
        </div>
      <?php else: ?>
        <?php /* Antes esto era una tabla de tres columnas donde la apuesta era
                 una línea de texto y, si era de carta, se elegía cuál en un
                 desplegable de nombres sueltos: apostabas a ciegas un objeto
                 que puede cambiar de dueño. Ahora cada sala es una tarjeta que
                 enseña QUÉ hay en juego, y elegir carta abre el selector de
                 verdad, con arte y estadísticas. */ ?>
        <ul class="salas">
          <?php foreach ($abiertos as $d): ?>
            <?php
            $esCarta = $d['tipo_apuesta'] === 'carta';
            $idRz    = (int) $d['id_rareza_apuesta'];
            $nCartasSala = max(1, (int) ($d['cartas_apuesta'] ?? 1));
            $misDeEsaRareza = $apostablesPorRareza[$idRz] ?? [];
            /* Con lote no basta con tener UNA de esa rareza: hay que poder
               igualar el número exacto, o entrar acabaría en error después de
               haber elegido. */
            $puedo = $esCarta ? count($misDeEsaRareza) >= $nCartasSala : $saldo >= (int) $d['monedas'];
            $iniciales = mb_strtoupper(mb_substr($d['creador'], 0, 2));
            ?>
            <li class="sala<?= $esCarta ? ' sala--carta' : '' ?>" data-rareza="<?= $esCarta ? $idRz : '' ?>">

              <span class="sala-entrenador">
                <span class="avatar avatar--sm"><?= htmlspecialchars($iniciales) ?></span>
                <span>
                  <b><?= htmlspecialchars($d['creador']) ?></b>
                  <span class="t-caption t-dim">Esperando rival</span>
                </span>
              </span>

              <span class="sala-apuesta">
                <span class="sala-apuesta-etiqueta">En juego</span>
                <?php if ($esCarta): ?>
                  <span class="sala-apuesta-valor">
                    <i class="ph-fill ph-cards" aria-hidden="true"></i>
                    <?= $nCartasSala === 1 ? 'Una carta' : $nCartasSala . ' cartas' ?>
                  </span>
                  <?= render_rareza($idRz, $d['rareza_apuesta']) ?>
                <?php else: ?>
                  <span class="sala-apuesta-valor sala-apuesta-valor--monedas">
                    <i class="ph-fill ph-coins" aria-hidden="true"></i>
                    <span class="mono"><?= number_format((int) $d['monedas'], 0, ',', '.') ?></span>
                  </span>
                  <span class="t-caption t-dim">por cabeza</span>
                <?php endif; ?>
              </span>

              <span class="sala-accion">
                <?php if (!$puedo): ?>
                  <span class="t-caption t-dim">
                    <i class="ph ph-prohibit" aria-hidden="true"></i>
                    <?= $esCarta
                          ? ($nCartasSala === 1
                              ? 'No tienes cartas de esa rareza'
                              : 'Te faltan cartas de esa rareza (hacen falta ' . $nCartasSala . ')')
                          : 'Saldo insuficiente' ?>
                  </span>
                <?php elseif ($esCarta): ?>
                  <?php /* Con carta de por medio no se entra desde aquí: primero
                           se elige cuál, en el modal, viéndola. */ ?>
                  <button type="button" class="btn btn-primary btn-sm"
                          data-entrar-carta="<?= (int) $d['id_duelo'] ?>"
                          data-rareza="<?= $idRz ?>"
                          data-rareza-nombre="<?= htmlspecialchars($d['rareza_apuesta']) ?>"
                          data-cartas="<?= $nCartasSala ?>"
                          data-creador="<?= htmlspecialchars($d['creador']) ?>">
                    <?= $nCartasSala === 1 ? 'Elegir carta y entrar' : 'Elegir cartas y entrar' ?>
                  </button>
                <?php else: ?>
                  <form method="POST" class="js-aceptar"
                        data-confirmar="Vas a apostar <?= number_format((int) $d['monedas'], 0, ',', '.') ?> monedas contra <?= htmlspecialchars($d['creador'], ENT_QUOTES) ?>.">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="accion" value="aceptar">
                    <input type="hidden" name="id_duelo" value="<?= $d['id_duelo'] ?>">
                    <button type="submit" class="btn btn-primary btn-sm">Entrar</button>
                  </form>
                <?php endif; ?>
              </span>

            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section>
      <h2 class="t-h3" style="margin-bottom:var(--e-4);">Tus duelos</h2>

      <?php if (empty($misDuelos)): ?>
        <p class="t-body-sm t-dim">Todavía no has disputado ninguno.</p>
      <?php else: ?>
        <div class="tabla-wrap">
          <table class="tabla">
            <thead>
              <tr>
                <th scope="col">Rival</th>
                <th scope="col">Resultado</th>
                <th scope="col">Estado</th>
                <th scope="col"><span class="sr-only">Ver</span></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($misDuelos as $d): ?>
                <?php
                $soyCreador = (int) $d['id_creador'] === (int) $id_usuario;
                $rival = $soyCreador ? ($d['rival'] ?? '—') : $d['creador'];
                $resuelto = $d['estado'] === 'resuelto';
                $gane = $resuelto && (int) $d['id_ganador'] === (int) $id_usuario;
                ?>
                <tr>
                  <td><?= htmlspecialchars($rival) ?></td>
                  <td>
                    <?php if ($resuelto): ?>
                      <span class="mono">
                        <?= $soyCreador ? (int) $d['goles_creador'] : (int) $d['goles_rival'] ?>
                        –
                        <?= $soyCreador ? (int) $d['goles_rival'] : (int) $d['goles_creador'] ?>
                      </span>
                      <?php /* Sin esto un empate aparecería junto a "Victoria" sin
                               nada que lo explique. */ ?>
                      <?php if (!empty($d['resuelto_por_tanda'])): ?>
                        <span class="t-caption t-dim">pen.</span>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="t-dim">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($resuelto): ?>
                      <span class="pastilla <?= $gane ? 'pastilla-on' : 'pastilla-off' ?>">
                        <?= $gane ? 'Victoria' : 'Derrota' ?>
                      </span>
                    <?php elseif ($d['estado'] === 'cancelado'): ?>
                      <span class="pastilla">Cancelado</span>
                    <?php else: ?>
                      <span class="pastilla pastilla-warn">En juego</span>
                    <?php endif; ?>
                  </td>
                  <td style="text-align:right;">
                    <?php if ($d['estado'] !== 'cancelado'): ?>
                      <a class="btn btn-plano btn-sm" href="duelo.php?id=<?= $d['id_duelo'] ?>">Ver</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

  <?php endif; ?>
</main>

<?php if ($titular && !empty($apostablesPorRareza)): ?>
<!-- Elegir qué carta apuestas al entrar en una sala.
     Existe porque antes esto era un <select> con nombres sueltos dentro de la
     fila de una tabla: se apostaba a ciegas un objeto que cambia de dueño si
     pierdes. Es un único modal reutilizado por todas las salas; el duelo
     concreto lo rellena duelos.js al abrirlo. -->
<div class="modal modal--ancha" id="modalApostar" role="dialog" aria-modal="true"
     aria-labelledby="apostarTitulo" aria-hidden="true">
  <div class="modal-caja modal-caja--ancha">
    <div class="modal-head">
      <div>
        <h2 id="apostarTitulo">Elige las cartas que apuestas</h2>
        <p class="t-body-sm t-dim" id="apostarSubtitulo"></p>
      </div>
      <button type="button" class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <p class="alerta alerta-warning" role="status">
      <i class="ph ph-warning" aria-hidden="true"></i>
      <span>Si pierdes el duelo, lo que pongas aquí pasa a ser de tu rival.</span>
    </p>

    <form method="POST" id="formApostar" class="stack stack-4">
      <?= csrfCampo() ?>
      <input type="hidden" name="accion" value="aceptar">
      <input type="hidden" name="id_duelo" id="apostarDuelo" value="">

      <p class="apuesta-contador" id="apostarContador" role="status" aria-live="polite"></p>

      <ul class="carta-lista carta-lista--apuesta" id="apostarCartas" role="group" aria-label="Cartas que puedes apostar">
        <?php foreach ($apostablesPorRareza as $idRareza => $copias): ?>
          <?php foreach ($copias as $c): ?>
            <?php render_carta_fila($c, [
                'radio' => ['name' => 'id_coleccion[]', 'value' => (int) $c['id_coleccion'],
                            'tipo' => 'checkbox'],
                'meta'  => htmlspecialchars($c['equipo'])
                    . ((int) $c['cantidad'] > 1 ? ' · ×' . (int) $c['cantidad'] : ''),
            ]); ?>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </ul>

      <div class="modal-pie">
        <button type="button" class="btn btn-ghost" data-cerrar-modal>Cancelar</button>
        <?php /* Arranca desactivado: entrar con menos cartas de las pactadas lo
                 rechaza el servidor, y es mejor que el botón lo diga antes que
                 enterarse tras recargar. duelos.js lo abre cuando cuadra. */ ?>
        <button type="submit" class="btn btn-primary" id="apostarEnviar" disabled>Apostar y entrar</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials/confirmar.php'; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>

<?= assetScript($base ?? '', 'assets/js/duelos.js') ?>

</body>
</html>
