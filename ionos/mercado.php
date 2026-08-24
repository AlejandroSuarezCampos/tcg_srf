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

function esPeticionAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

$mensaje = '';
$error   = '';

$esPostMutante = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']);

if ($esPostMutante && !csrfValido($_POST['csrf'] ?? null)) {
    if (esPeticionAjax()) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'La página ha caducado, inténtalo de nuevo.']);
        exit;
    }
    $error = 'La página ha caducado, inténtalo de nuevo.';
    $esPostMutante = false;
}

// ----- Publicar un anuncio nuevo -----
if ($esPostMutante && $_POST['accion'] === 'publicar') {
    $id_coleccion = (int) ($_POST['id_coleccion'] ?? 0);
    $precio       = (int) ($_POST['precio'] ?? 0);

    if ($id_coleccion <= 0 || $precio <= 0) {
        $error = 'Elige una carta y un precio de al menos 1 moneda.';
    } elseif (!$db->publicarAnuncio($id_coleccion, $id_usuario, $precio)) {
        /* «No se pudo publicar» a secas deja al vendedor sin saber qué
           arreglar, y desde que hay horquilla de precio el motivo más probable
           es justo ese. Se vuelve a tasar la carta para poder decir el rango
           exacto en vez de un no genérico. */
        $cartaFallo = $db->cartaDeCopia($id_coleccion, $id_usuario);
        if ($cartaFallo) {
            $tasaFallo = $db->valorCarta($cartaFallo);
            if ($precio < $tasaFallo['min'] || $precio > $tasaFallo['max']) {
                $error = 'Ese precio se sale de lo que vale la carta. '
                       . htmlspecialchars($cartaFallo['nombre']) . ' se puede publicar entre '
                       . number_format($tasaFallo['min'], 0, ',', '.') . ' y '
                       . number_format($tasaFallo['max'], 0, ',', '.') . ' monedas.';
            }
        }
        if ($error === null || $error === '') {
            $error = 'No se pudo publicar el anuncio. Comprueba que la carta sigue siendo tuya y que no está protegida.';
        }
    } else {
        header('Location: mercado.php?ok=publicado');
        exit;
    }
}

// ----- Retirar un anuncio propio -----
if ($esPostMutante && $_POST['accion'] === 'retirar') {
    $db->retirarAnuncio((int) $_POST['id_anuncio'], $id_usuario);

    if (esPeticionAjax()) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    header('Location: mercado.php?ok=retirado');
    exit;
}

// ----- Comprar una carta -----
if ($esPostMutante && $_POST['accion'] === 'comprar') {
    $resultado = $db->comprarAnuncio((int) $_POST['id_anuncio'], $id_usuario);

    if ($resultado['ok']) {
        // Sincronizamos la sesión con el saldo real tras la compra
        $usuarioActualizado = $db->obtenerUsuario($id_usuario);
        if ($usuarioActualizado) {
            $_SESSION['monedas'] = $usuarioActualizado['monedas'];
        }
    }

    if (esPeticionAjax()) {
        header('Content-Type: application/json');
        echo json_encode([
            'ok'      => $resultado['ok'],
            'error'   => $resultado['error'],
            'monedas' => $_SESSION['monedas'] ?? null,
        ]);
        exit;
    }

    if ($resultado['ok']) {
        header('Location: mercado.php?ok=comprado');
        exit;
    } else {
        $error = $resultado['error'];
    }
}

if (isset($_GET['ok'])) {
    $mensajes = [
        'publicado' => 'Tu carta ya está publicada en el mercado.',
        'retirado'  => 'Anuncio retirado.',
        'comprado'  => 'Compra completada. La carta ya está en tu colección.',
    ];
    $mensaje = $mensajes[$_GET['ok']] ?? '';
}

$rarezasDB = $db->listarRarezas();
$rarezas = [];
foreach ($rarezasDB as $r) {
    $rarezas[$r['id_rareza']] = $r['nombre'];
}

$filtroNombre = trim($_GET['q'] ?? '');
$filtroRareza = $_GET['id_rareza'] ?? '';
$orden        = $_GET['orden'] ?? '';

$anuncios = $db->listarMercadoActivo([
    'nombre'    => $filtroNombre,
    'id_rareza' => $filtroRareza,
    'orden'     => $orden,
]);

// Copias agrupadas por cromo: para vender da igual cuál copia concreta se
// publique (son intercambiables), así que no tiene sentido pintar una tarjeta
// completa por cada una. Con muchas copias de comunes (sobres grandes) pintar
// una por copia llegaba a cientos de nodos y colgaba el navegador al abrir el
// modal. Mismo criterio que ya usan coleccion.php y mazos.php.
$porCromoVendible = [];
foreach ($db->listarColeccionVendible($id_usuario) as $c) {
    $idCromo = (int) $c['id_cromo'];
    if (!isset($porCromoVendible[$idCromo])) {
        $porCromoVendible[$idCromo] = ['fila' => $c, 'cantidad' => 0];
    }
    $porCromoVendible[$idCromo]['cantidad']++;
}
$monedasActuales = $_SESSION['monedas'] ?? 0;
$hayFiltros = $filtroNombre !== '' || $filtroRareza !== '' || $orden !== '';

// El mercado nace en lista: aquí se viene a comparar precios y estadísticas,
// no a mirar el arte. La rejilla sigue a un clic.
$vista = vista_actual('lista');

$paginaTitulo = 'Mercado';
$paginaDesc   = 'Compra y vende cromos con el resto de participantes de la liga.';
include __DIR__ . '/partials/head.php';

$activePage = 'mercado';
include __DIR__ . '/navbar.php';
?>

<header class="cabecera">
  <div class="linea-campo" aria-hidden="true"></div>
  <div class="wrap cabecera-contenido">
    <div class="fila fila-entre" style="align-items:flex-end;">
      <div>
        <h1>Mercado</h1>
        <p>Compra y vende cromos con el resto de participantes de la liga.</p>
      </div>
      <button class="btn btn-primary" data-abrir-modal="modalVender">
        <i class="ph ph-tag" aria-hidden="true"></i> Vender una carta
      </button>
    </div>
    <div class="cabecera-datos">
      <div class="dato"><b><?= number_format($monedasActuales, 0, ',', '.') ?></b><span>Tus monedas</span></div>
      <div class="dato"><b><?= count($anuncios) ?></b><span>Anuncios activos</span></div>
    </div>
  </div>
</header>

<main id="contenido" class="seccion wrap">

  <?php if ($mensaje): ?>
  <div class="alerta alerta-success" role="status" style="margin-bottom:var(--space-5);">
    <i class="ph ph-check-circle" aria-hidden="true"></i>
    <span><?= htmlspecialchars($mensaje) ?></span>
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--space-5);">
    <i class="ph ph-warning-circle" aria-hidden="true"></i>
    <span><?= htmlspecialchars($error) ?></span>
  </div>
  <?php endif; ?>

  <form method="GET" class="barra-filtros">
    <div class="campo">
      <label for="m-buscar">Buscar</label>
      <input type="search" name="q" id="m-buscar" value="<?= htmlspecialchars($filtroNombre) ?>"
             placeholder="Nombre de la carta">
    </div>

    <div class="campo">
      <label for="m-rareza">Rareza</label>
      <select name="id_rareza" id="m-rareza">
        <option value="">Todas</option>
        <?php foreach ($rarezas as $id => $nombre): ?>
        <option value="<?= $id ?>" <?= (string) $filtroRareza === (string) $id ? 'selected' : '' ?>>
          <?= htmlspecialchars($nombre) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="campo">
      <label for="m-orden">Ordenar por</label>
      <select name="orden" id="m-orden">
        <option value="">Más recientes</option>
        <option value="precio_asc" <?= $orden === 'precio_asc' ? 'selected' : '' ?>>Precio: de menor a mayor</option>
        <option value="precio_desc" <?= $orden === 'precio_desc' ? 'selected' : '' ?>>Precio: de mayor a menor</option>
      </select>
    </div>

    <div class="barra-filtros-acciones">
      <?php // La vista viaja en la URL: se reenvía con el resto de filtros. ?>
      <input type="hidden" name="vista" value="<?= htmlspecialchars($vista) ?>">
      <button type="submit" class="btn btn-ghost">Filtrar</button>
      <?php if ($hayFiltros): ?>
      <a class="btn btn-plano" href="mercado.php?vista=<?= htmlspecialchars($vista) ?>">Quitar</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if (!empty($anuncios)): ?>
    <div class="fila fila-entre" style="margin-bottom:var(--space-4);">
      <p class="t-body-sm t-dim">
        <b class="mono" style="color:var(--frost);"><?= count($anuncios) ?></b>
        <?= count($anuncios) === 1 ? 'carta a la venta' : 'cartas a la venta' ?>
      </p>
      <?php render_vista_conmutador($vista); ?>
    </div>
  <?php endif; ?>

  <?php if (empty($anuncios)): ?>
    <div class="vacio">
      <span class="vacio-ico"><i class="ph ph-storefront" aria-hidden="true"></i></span>
      <?php if ($hayFiltros): ?>
        <h3>Ningún anuncio con esos filtros</h3>
        <p>Prueba a cambiar la búsqueda o a mirar todas las rarezas.</p>
        <a class="btn btn-ghost" href="mercado.php">Quitar filtros</a>
      <?php else: ?>
        <h3>El mercado está vacío</h3>
        <p>Todavía no hay cartas a la venta. Puedes ser quien abra la puja.</p>
        <button class="btn btn-primary" data-abrir-modal="modalVender">Vender una carta</button>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php // Las dos vistas comparten el MISMO bloque de datos y el mismo
          // formulario de compra: lo único que cambia es el envoltorio y qué
          // componente los pinta. ?>
    <<?= $vista === 'lista' ? 'ul class="carta-lista"' : 'div class="carta-grid"' ?>>
      <?php foreach ($anuncios as $a): ?>
        <?php
        $esTuyo = (int) $a['id_vendedor'] === (int) $id_usuario;
        $sinSaldo = !$esTuyo && $monedasActuales < (int) $a['precio'];

        // El componente de carta espera la clave `nombre`; el mercado la
        // devuelve como `carta`, así que la adaptamos aquí.
        $datosCarta = $a + ['nombre' => $a['carta']];

        $iniciales = mb_strtoupper(mb_substr($a['vendedor'], 0, 2));

        if ($esTuyo) {
            $boton = '<button type="submit" class="btn btn-ghost btn-sm btn-bloque">Retirar anuncio</button>';
            $confirmar = '¿Retirar del mercado «' . htmlspecialchars($a['carta'], ENT_QUOTES) . '»?';
            $accionNombre = 'retirar';
        } else {
            $boton = '<button type="submit" class="btn btn-primary btn-sm btn-bloque"'
                . ($sinSaldo ? ' disabled title="No tienes monedas suficientes"' : '') . '>Comprar</button>';
            $confirmar = 'Vas a comprar «' . htmlspecialchars($a['carta'], ENT_QUOTES) . '» por '
                . number_format((int) $a['precio'], 0, ',', '.') . ' monedas. Esta acción no se puede deshacer.';
            $accionNombre = 'comprar';
        }

        $formulario = '<form method="POST" action="mercado.php" class="js-mercado" data-confirmar="'
            . htmlspecialchars($confirmar, ENT_QUOTES) . '">'
            . csrfCampo()
            . '<input type="hidden" name="accion" value="' . $accionNombre . '">'
            . '<input type="hidden" name="id_anuncio" value="' . (int) $a['id_anuncio'] . '">'
            . $boton . '</form>';

        /* El nombre del vendedor lleva a su perfil. Es el sitio donde de verdad
           da curiosidad saber quién es el que vende, y hasta ahora era texto
           muerto. El propio no se enlaza: llevaría a `perfil.php`, que ya está
           en la barra. */
        $vendedor = '<span class="carta-vendedor"><span class="avatar avatar--sm">' . htmlspecialchars($iniciales)
            . '</span>Vende ' . ($esTuyo
                ? htmlspecialchars($a['vendedor'])
                : '<a href="usuario.php?u=' . (int) $a['id_vendedor'] . '">'
                  . htmlspecialchars($a['vendedor']) . '</a>')
            . '</span>';

        if ($vista === 'lista') {
            $precioHtml = '<span class="carta-fila-precio">'
                . '<i class="ph ph-coins" aria-hidden="true"></i>'
                . number_format((int) $a['precio'], 0, ',', '.')
                . '<span class="sr-only">monedas</span></span>';

            render_carta_fila($datosCarta, [
                'ficha'   => true,
                // Quién vende es lo que más se mira aquí después del precio,
                // así que sustituye al equipo bajo el nombre.
                'meta'    => htmlspecialchars($a['equipo']) . ' · Vende ' . htmlspecialchars($a['vendedor']),
                'derecha' => $precioHtml . $formulario,
                'datos'   => ['anuncio' => (int) $a['id_anuncio']],
            ]);
        } else {
            render_carta($datosCarta, [
                'modo'   => 'arte',
                'ficha'  => true,
                'precio' => (int) $a['precio'],
                'pie'    => $vendedor . $formulario,
                'datos'  => ['anuncio' => (int) $a['id_anuncio']],
            ]);
        }
        ?>
      <?php endforeach; ?>
    </<?= $vista === 'lista' ? 'ul' : 'div' ?>>
  <?php endif; ?>

</main>

<!-- Modal: vender una carta -->
<div class="modal" id="modalVender" role="dialog" aria-modal="true"
     aria-labelledby="venderTitulo" aria-hidden="true">
  <div class="modal-caja">
    <div class="modal-head">
      <h2 id="venderTitulo">Vender una carta</h2>
      <button class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <?php if (empty($porCromoVendible)): ?>
      <div class="vacio">
        <span class="vacio-ico"><i class="ph ph-lock-simple" aria-hidden="true"></i></span>
        <h3>No tienes cartas disponibles</h3>
        <p>Puede que estén protegidas o ya publicadas. Puedes revisarlo en tu colección.</p>
        <a class="btn btn-ghost" href="coleccion.php">Ir a la colección</a>
      </div>
    <?php else: ?>
      <form method="POST" action="mercado.php" class="stack stack-5" id="formVender">
        <?= csrfCampo() ?>
        <input type="hidden" name="accion" value="publicar">
        <input type="hidden" name="id_coleccion" id="v-carta" required>

        <!-- Selector visual: con muchas cartas un <select> se vuelve una lista
             interminable e imposible de reconocer. Aquí se busca y se elige
             viendo la carta, que es como el usuario las tiene en la cabeza. -->
        <div class="campo">
          <label for="v-buscar">Elige la carta</label>
          <input type="search" id="v-buscar" placeholder="Buscar entre tus cartas"
                 autocomplete="off" aria-describedby="v-conteo">
          <span class="campo-hint" id="v-conteo" role="status" aria-live="polite">
            <?= count($porCromoVendible) ?> cartas disponibles
          </span>
        </div>

        <div class="selector-cartas" id="v-lista" role="radiogroup" aria-label="Cartas disponibles para vender">
          <?php foreach ($porCromoVendible as $grupo): ?>
            <?php $c = $grupo['fila']; ?>
            <?php /* La horquilla se calcula en el servidor y viaja con cada
                     carta: así el precio se puede acotar y sugerir en cuanto
                     se elige una, sin ir a preguntar. El servidor la vuelve a
                     comprobar al publicar de todas formas — esto es ayuda para
                     el vendedor, no la defensa. */
                  $tasa = $db->valorCarta($c); ?>
            <label class="selector-item"
                   data-nombre="<?= htmlspecialchars($c['nombre']) ?>"
                   data-equipo="<?= htmlspecialchars($c['equipo']) ?>"
                   data-rareza-nombre="<?= htmlspecialchars($c['rareza']) ?>"
                   data-precio-min="<?= (int) $tasa['min'] ?>"
                   data-precio-max="<?= (int) $tasa['max'] ?>"
                   data-precio-sug="<?= (int) $tasa['valor'] ?>">
              <input type="radio" name="seleccion_carta" class="sr-only"
                     value="<?= $c['id_coleccion'] ?>">
              <?php render_carta($c, ['tamano' => 'sm', 'cantidad' => $grupo['cantidad']]); ?>
            </label>
          <?php endforeach; ?>

          <p class="selector-vacio" hidden>Ninguna de tus cartas coincide con esa búsqueda.</p>
        </div>

        <div class="campo">
          <label for="v-precio">Precio en monedas</label>
          <input type="number" name="precio" id="v-precio" min="1" step="1" required
                 placeholder="250" aria-describedby="v-precio-hint">
          <span class="campo-hint" id="v-precio-hint">Elige una carta y aquí saldrá entre qué precios se puede publicar.</span>
        </div>

        <p class="alerta alerta-danger" id="v-error" role="alert" hidden>
          <i class="ph ph-warning-circle" aria-hidden="true"></i>
          <span>Elige primero una carta de la lista.</span>
        </p>

        <div class="modal-pie">
          <button type="button" class="btn btn-ghost" data-cerrar-modal>Cancelar</button>
          <button type="submit" class="btn btn-primary">Publicar anuncio</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/partials/ficha_carta.php'; ?>

<!-- Confirmación de acciones con consecuencia económica -->
<?php include __DIR__ . '/partials/confirmar.php'; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>

<?= assetScript($base ?? '', 'assets/js/mercado.js') ?>

</body>
</html>
