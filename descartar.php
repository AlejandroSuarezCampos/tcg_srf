<?php
/**
 * DESCARTE DE REPETIDAS — la venta rápida.
 *
 * Cambiar copias que sobran por monedas sin pasar por el mercado. Pantalla
 * propia y no un botón dentro de la colección a propósito: es una acción
 * IRREVERSIBLE en lote, y mezclarla con la pantalla donde se navega y se
 * filtra es cómo alguien vacía media colección con un clic mal dado.
 *
 * Toda la lógica vive en Tcg::repetidasDescartables() y Tcg::descartarCopias();
 * aquí solo está la pantalla. El servidor revalida cada copia dentro de la
 * transacción, así que lo que se marque en esta página es una propuesta, no
 * una orden.
 */
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

$error = null;
$hecho = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        $error = 'La página ha caducado, inténtalo de nuevo.';
    } else {
        $ids = array_map('intval', (array) ($_POST['copias'] ?? []));
        $res = $db->descartarCopias($ids, $id_usuario);
        if ($res['ok']) {
            $_SESSION['monedas'] = $res['monedas'];
            $hecho = $res;
        } else {
            $error = $res['error'];
        }
    }
}

$grupos = $db->repetidasDescartables($id_usuario);

$totalSobran = 0;
$totalMonedas = 0;
foreach ($grupos as $g) {
    $totalSobran  += (int) $g['sobran'];
    $totalMonedas += (int) $g['total'];
}

$paginaTitulo = 'Cortar del equipo';
$paginaDesc   = 'Cambia las copias que te sobran por monedas.';
include __DIR__ . '/partials/head.php';

$activePage = 'descartar';
include __DIR__ . '/navbar.php';
?>

<?php cabecera([
  'rotulo' => 'Plantilla',
  'titulo' => 'Cortar del equipo',
  'texto'  => 'Las copias que te sobran, cambiadas por monedas al instante. El precio es fijo por '
            . 'rareza y está por debajo de lo que sacarías en el mercado: esto es comodidad, no negocio.',
]); ?>

<main class="wrap seccion">

  <?php if ($error): ?>
    <p class="alerta alerta-danger" role="alert">
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span><?= htmlspecialchars($error) ?></span>
    </p>
  <?php endif; ?>

  <?php if ($hecho): ?>
    <p class="alerta alerta-ok" role="status">
      <i class="ph ph-check-circle" aria-hidden="true"></i>
      <span>
        Has descartado <?= (int) $hecho['descartadas'] ?>
        <?= $hecho['descartadas'] === 1 ? 'copia' : 'copias' ?>
        y has ganado <?= number_format($hecho['ganadas'], 0, ',', '.') ?> monedas.
      </span>
    </p>
  <?php endif; ?>

  <?php if (!$grupos): ?>
    <div class="vacio">
      <i class="ph ph-cards vacio-ico" aria-hidden="true"></i>
      <h3>No te sobra ninguna carta</h3>
      <p>
        Aquí solo aparecen los cromos de los que tienes más de una copia. La
        última copia de cada cromo nunca se puede descartar: es la que sostiene
        tu álbum.
      </p>
      <a class="btn btn-primario" href="plantilla.php">Volver a tu plantilla</a>
    </div>
  <?php else: ?>

    <form method="post" id="formDescarte">
      <?= csrfCampo() ?>

      <div class="descarte-barra">
        <div>
          <p class="t-h3">
            <?= (int) $totalSobran ?>
            <?= $totalSobran === 1 ? 'copia repetida' : 'copias repetidas' ?>
          </p>
          <p class="t-body-sm t-dim">
            Todas juntas valen <?= number_format($totalMonedas, 0, ',', '.') ?> monedas.
          </p>
        </div>
        <div class="descarte-acciones">
          <button type="button" class="btn btn-ghost btn-sm" id="btnTodas">
            Marcar todas
          </button>
          <button type="button" class="btn btn-ghost btn-sm" id="btnNinguna">
            Desmarcar
          </button>
          <button type="submit" class="btn btn-primario" id="btnDescartar" disabled
                  data-confirmar="Vas a descartar las copias marcadas. Esta acción no se puede deshacer.">
            <i class="ph ph-recycle" aria-hidden="true"></i>
            Descartar <span id="descarteCuenta">0</span>
            (<span id="descarteMonedas">0</span> monedas)
          </button>
        </div>
      </div>

      <ul class="descarte-lista">
        <?php foreach ($grupos as $g): ?>
          <li class="descarte-fila" data-rareza="<?= (int) $g['id_rareza'] ?>">
            <div class="descarte-carta">
              <?php render_carta($g, ['tamano' => 'sm']); ?>
            </div>
            <div class="descarte-datos">
              <p class="descarte-nombre"><?= htmlspecialchars($g['nombre']) ?></p>
              <p class="t-caption t-dim">
                <?= htmlspecialchars($g['rareza']) ?> ·
                tienes <?= (int) $g['copias'] ?>, te sobran <?= (int) $g['sobran'] ?> ·
                <?= number_format($g['precio_unidad'], 0, ',', '.') ?> monedas cada una
              </p>
              <?php /* Una casilla POR COPIA, no una por cromo: así se pueden
                       soltar dos de las cinco que sobran sin tener que ir todas
                       o ninguna. */ ?>
              <div class="descarte-copias">
                <?php foreach ($g['descartables'] as $i => $idCol): ?>
                  <label class="descarte-copia">
                    <input type="checkbox" name="copias[]" value="<?= (int) $idCol ?>"
                           data-precio="<?= (int) $g['precio_unidad'] ?>">
                    <span>#<?= $i + 2 ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </form>

  <?php endif; ?>
</main>

<script>
/* Contador en vivo de lo marcado. Sin él hay que sumar de cabeza cuánto vas a
   cobrar, que es justo el dato que decide si merece la pena. */
(function () {
  var form = document.getElementById('formDescarte');
  if (!form) return;

  var boton    = document.getElementById('btnDescartar');
  var cuentaEl = document.getElementById('descarteCuenta');
  var monedaEl = document.getElementById('descarteMonedas');
  var MAX      = <?= (int) Tcg::DESCARTE_MAX_POR_TANDA ?>;

  function casillas() {
    return form.querySelectorAll('input[name="copias[]"]');
  }

  function recontar() {
    var n = 0, monedas = 0;
    Array.prototype.forEach.call(casillas(), function (c) {
      if (!c.checked) return;
      n++;
      monedas += parseInt(c.dataset.precio, 10) || 0;
    });
    cuentaEl.textContent = n;
    monedaEl.textContent = monedas.toLocaleString('es-ES');
    boton.disabled = n === 0 || n > MAX;
    boton.title = n > MAX ? 'Máximo ' + MAX + ' por tanda' : '';
  }

  form.addEventListener('change', recontar);

  document.getElementById('btnTodas').addEventListener('click', function () {
    // Hasta el tope por tanda, y no más: marcar 400 para que el servidor las
    // rechace enteras es peor que marcar las 200 que sí van a entrar.
    var n = 0;
    Array.prototype.forEach.call(casillas(), function (c) {
      c.checked = n < MAX;
      if (c.checked) n++;
    });
    recontar();
  });

  document.getElementById('btnNinguna').addEventListener('click', function () {
    Array.prototype.forEach.call(casillas(), function (c) { c.checked = false; });
    recontar();
  });

  /* La confirmación va aquí y no en el `submit` del navegador porque el
     descarte no se puede deshacer y el aviso tiene que decir CUÁNTAS y POR
     CUÁNTO, que es lo que se acaba de contar. */
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    SRF.confirmar(
      'Vas a descartar ' + cuentaEl.textContent + ' copias por ' +
      monedaEl.textContent + ' monedas. No se puede deshacer.',
      function () { form.submit(); },
      { aceptar: 'Descartar' }
    );
  });

  recontar();
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
