<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $tipo = $_POST['tipo_apuesta'] === 'carta' ? 'carta' : 'monedas';
        $res = $db->crearDuelo(
            $id_usuario,
            $tipo,
            (int) ($_POST['monedas'] ?? 0),
            $tipo === 'carta' ? (int) ($_POST['id_rareza'] ?? 0) : null,
            $tipo === 'carta' ? (int) ($_POST['id_coleccion'] ?? 0) : null
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
            (int) ($_POST['id_coleccion'] ?? 0) ?: null
        );
        if ($res['ok']) {
            header('Location: duelo.php?id=' . $res['id_duelo']);
            exit;
        }
        $error = $res['error'];
    }
}

$abiertos  = $db->listarDuelosAbiertos($id_usuario);
$misDuelos = $db->listarMisDuelos($id_usuario, 12);
$rarezas   = $db->listarRarezas();
$saldo     = (int) ($db->obtenerUsuario($id_usuario)['monedas'] ?? 0);

// Copias apostables agrupadas por rareza, para el selector de apuesta de carta.
$apostablesPorRareza = [];
foreach ($db->listarCopiasApostables($id_usuario) as $c) {
    $apostablesPorRareza[(int) $c['id_rareza']][] = $c;
}

$fuerzaTitular = $titular
    ? Tcg::fuerzaAlineacion($db->listarCartasMazo($titular['id_mazo']), $titular['formacion'])
    : null;

$paginaTitulo = 'Duelos';
$paginaDesc   = 'Reta a otro entrenador con tu alineación titular.';
include __DIR__ . '/partials/head.php';

$activePage = 'duelos';
include __DIR__ . '/navbar.php';
?>

<header class="cabecera">
  <div class="linea-campo" aria-hidden="true"></div>
  <div class="wrap cabecera-contenido">
    <h1>Duelos</h1>
    <p>Se juega con tu mazo titular. La alineación se congela al entrar: lo que cambies después no afecta al duelo.</p>
    <div class="cabecera-datos">
      <div class="dato"><b class="mono"><?= number_format($saldo, 0, ',', '.') ?></b><span>Monedas</span></div>
      <?php if ($fuerzaTitular): ?>
        <div class="dato"><b class="mono"><?= (int) round($fuerzaTitular['total']) ?></b><span>Fuerza titular</span></div>
      <?php endif; ?>
      <div class="dato"><b class="mono"><?= count($abiertos) ?></b><span>Salas abiertas</span></div>
    </div>
  </div>
</header>

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

    <section class="panel" style="margin-bottom:var(--space-6);">
      <div class="panel-head">
        <h2 class="panel-titulo">Abrir una sala</h2>
        <span class="pastilla pastilla-titular"><?= htmlspecialchars($titular['nombre']) ?></span>
      </div>

      <p class="t-body-sm t-dim" style="margin-bottom:var(--space-4);">
        Al abrirla te quedas dentro esperando rival. Si sales de la sala, se cancela y recuperas lo apostado.
      </p>

      <form method="POST" class="stack stack-4" id="formCrearDuelo">
        <input type="hidden" name="accion" value="crear">

        <fieldset class="campo">
          <legend class="campo-label">Qué se apuesta</legend>
          <label class="casilla">
            <input type="radio" name="tipo_apuesta" value="monedas" checked data-tipo>
            Monedas
          </label>
          <label class="casilla">
            <input type="radio" name="tipo_apuesta" value="carta" data-tipo>
            Una carta (ambos de la misma rareza)
          </label>
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
          <label for="d-rareza">Rareza en juego</label>
          <select name="id_rareza" id="d-rareza">
            <?php foreach ($rarezas as $r): ?>
              <?php $n = count($apostablesPorRareza[(int) $r['id_rareza']] ?? []); ?>
              <option value="<?= $r['id_rareza'] ?>" <?= $n === 0 ? 'disabled' : '' ?>>
                <?= htmlspecialchars($r['nombre']) ?> — <?= $n ?> disponible<?= $n === 1 ? '' : 's' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="campo-hint">Solo se listan cartas libres: ni protegidas, ni en venta, ni en un mazo, ni ya apostadas.</span>
        </div>

        <div class="campo" data-bloque="carta" hidden>
          <span class="campo-label">Tu carta</span>
          <div class="selector-cartas" id="d-cartas" role="radiogroup" aria-label="Cartas que puedes apostar">
            <?php foreach ($apostablesPorRareza as $idRareza => $copias): ?>
              <?php foreach ($copias as $c): ?>
                <label class="selector-item" data-rareza="<?= $idRareza ?>" hidden>
                  <input type="radio" name="id_coleccion" class="sr-only" value="<?= $c['id_coleccion'] ?>">
                  <?php render_carta($c, ['tamano' => 'sm']); ?>
                </label>
              <?php endforeach; ?>
            <?php endforeach; ?>
            <p class="selector-vacio" hidden>No tienes cartas libres de esa rareza.</p>
          </div>
        </div>

        <div><button type="submit" class="btn btn-primary">Abrir sala</button></div>
      </form>
    </section>

    <section style="margin-bottom:var(--space-6);">
      <h2 class="t-h3" style="margin-bottom:var(--space-4);">Salas abiertas</h2>

      <?php if (empty($abiertos)): ?>
        <div class="vacio">
          <span class="vacio-ico"><i class="ph ph-users-three" aria-hidden="true"></i></span>
          <h3>No hay nadie esperando</h3>
          <p>Abre tú una sala y espera a que entre alguien.</p>
        </div>
      <?php else: ?>
        <div class="tabla-wrap">
          <table class="tabla">
            <thead>
              <tr>
                <th scope="col">Entrenador</th>
                <th scope="col">Apuesta</th>
                <th scope="col"><span class="sr-only">Acción</span></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($abiertos as $d): ?>
                <?php
                $esCarta = $d['tipo_apuesta'] === 'carta';
                $idRz    = (int) $d['id_rareza_apuesta'];
                $misDeEsaRareza = $apostablesPorRareza[$idRz] ?? [];
                $puedo = $esCarta ? count($misDeEsaRareza) > 0 : $saldo >= (int) $d['monedas'];
                ?>
                <tr>
                  <td><?= htmlspecialchars($d['creador']) ?></td>
                  <td>
                    <?php if ($esCarta): ?>
                      Una carta <?= render_rareza($idRz, $d['rareza_apuesta']) ?>
                    <?php else: ?>
                      <span class="mono"><?= number_format((int) $d['monedas'], 0, ',', '.') ?></span> monedas
                    <?php endif; ?>
                  </td>
                  <td style="text-align:right;">
                    <?php if (!$puedo): ?>
                      <span class="t-caption t-dim">
                        <?= $esCarta ? 'Sin cartas de esa rareza' : 'Saldo insuficiente' ?>
                      </span>
                    <?php else: ?>
                      <form method="POST" class="js-aceptar" style="display:inline;"
                            data-confirmar="<?= $esCarta
                                ? 'Vas a apostar una carta ' . htmlspecialchars($d['rareza_apuesta']) . '. Si pierdes, cambia de dueño.'
                                : 'Vas a apostar ' . number_format((int) $d['monedas'], 0, ',', '.') . ' monedas.' ?>">
                        <input type="hidden" name="accion" value="aceptar">
                        <input type="hidden" name="id_duelo" value="<?= $d['id_duelo'] ?>">
                        <?php if ($esCarta): ?>
                          <select name="id_coleccion" class="campo-inline" aria-label="Carta que apuestas">
                            <?php foreach ($misDeEsaRareza as $c): ?>
                              <option value="<?= $c['id_coleccion'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                          </select>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-sm">Entrar</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section>
      <h2 class="t-h3" style="margin-bottom:var(--space-4);">Tus duelos</h2>

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

<?php include __DIR__ . '/partials/confirmar.php'; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>

<script src="assets/js/duelos.js"></script>

</body>
</html>
