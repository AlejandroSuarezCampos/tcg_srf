<?php
/**
 * INTERCAMBIOS — cartas por cartas, sin monedas (migración `053`).
 *
 * La cuarta vía por la que una carta cambia de dueño. Aquí no se cobra: para
 * pagar está `mercado.php`, que además acota los precios. Meter monedas en un
 * intercambio abriría un mercado paralelo sin esa horquilla.
 *
 * DOS VÍAS, LA MISMA TABLA (ver la migración):
 *   · OFERTA DIRIGIDA    se propone desde el perfil de alguien.
 *   · ANUNCIO DEL TABLÓN abierto a todos. Se puede aceptar tal cual —dice qué
 *                        cromos busca— o contraofertar otra cosa.
 *
 * ESTA PANTALLA TIENE DOS CARAS y no dos archivos, porque comparten las tres
 * acciones POST, el guardián de sesión y la limpieza de caducadas:
 *   BANDEJA   (sin parámetros)  lo que te espera, lo que enviaste y el tablón.
 *   COMPONER  (`con`/`nuevo`/`responder`)  el formulario del trato.
 *
 * Lo único que cambia entre los tres modos de componer es DE DÓNDE sale la
 * lista de la derecha —lo que puedes pedir—; el formulario es el mismo.
 */
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';
require_once __DIR__ . '/partials/cabecera.php';
require_once __DIR__ . '/partials/csrf.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login');
    exit;
}
$id_usuario = (int) $_SESSION['id_usuario'];

/* Quien enciende la luz paga la limpieza, igual que `cancelarSalasAbandonadas()`
   con los duelos: sin cron. No es lo que libera las cartas —de eso se encarga
   el `vence > NOW()` de la consulta—, sino lo que deja de pintarlas como vivas. */
$db->caducarIntercambios();

$mensaje = '';
$error   = '';

$esPostMutante = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']);
if ($esPostMutante && !csrfValido($_POST['csrf'] ?? null)) {
    $error = 'La página ha caducado, inténtalo de nuevo.';
    $esPostMutante = false;
}

if ($esPostMutante && $_POST['accion'] === 'proponer') {
    /* `destinatario` vacío = anuncio del tablón. Se manda como cadena vacía y
       no como 0 porque 0 es un id de usuario tan válido como cualquiera para
       un `(int)` despistado, y ahí la diferencia es "para todos" o "para
       nadie". */
    $destinatario = ($_POST['destinatario'] ?? '') === '' ? null : (int) $_POST['destinatario'];
    $origen       = ($_POST['origen'] ?? '') === ''       ? null : (int) $_POST['origen'];

    $res = $db->crearIntercambio(
        $id_usuario,
        $destinatario,
        (array) ($_POST['da'] ?? []),
        (array) ($_POST['busca'] ?? []),
        $origen
    );
    if ($res['ok']) {
        header('Location: intercambios?ok=' . ($destinatario === null ? 'publicado' : 'enviado'));
        exit;
    }
    $error = $res['error'];
}

if ($esPostMutante && $_POST['accion'] === 'aceptar') {
    $res = $db->aceptarIntercambio((int) ($_POST['id_intercambio'] ?? 0), $id_usuario);
    if ($res['ok']) {
        header('Location: intercambios?ok=aceptado');
        exit;
    }
    $error = $res['error'];
}

if ($esPostMutante && $_POST['accion'] === 'cerrar') {
    $res = $db->cerrarIntercambio((int) ($_POST['id_intercambio'] ?? 0), $id_usuario);
    if ($res['ok']) {
        header('Location: intercambios?ok=cerrado');
        exit;
    }
    $error = $res['error'];
}

if (isset($_GET['ok'])) {
    $mensajes = [
        'enviado'   => 'Oferta enviada. Caduca a las 48 horas si no contesta.',
        'publicado' => 'Tu anuncio ya está en el tablón.',
        'aceptado'  => 'Trato cerrado. Las cartas ya han cambiado de manos.',
        'cerrado'   => 'Oferta cerrada. Las cartas vuelven a estar libres.',
    ];
    $mensaje = $mensajes[$_GET['ok']] ?? '';
}

// ---------------------------------------------------------------------------
// QUÉ CARA SE PINTA
// ---------------------------------------------------------------------------
$con       = (int) ($_GET['con'] ?? 0);
$responder = (int) ($_GET['responder'] ?? 0);
$nuevo     = isset($_GET['nuevo']);
$componer  = $con > 0 || $responder > 0 || $nuevo;

$maxLado = (int) $db->config('intercambio_cartas_max', 10);

/** Cuánto le queda a una oferta, en un texto que se lee de un vistazo. */
function plazo_restante(string $vence): array {
    $seg = strtotime($vence) - time();
    if ($seg <= 0)      { return ['caducada', true]; }
    if ($seg < 3600)    { return ['quedan ' . max(1, (int) round($seg / 60)) . ' min', true]; }
    $horas = (int) round($seg / 3600);
    return ['quedan ' . $horas . ' h', $horas <= 6];
}

/**
 * Un lado del formulario: rejilla de cartas con un contador por cada una.
 *
 * `$tope` por carta es el número de copias libres cuando son tuyas (no puedes
 * dar cinco si tienes tres) y el tope del lado cuando se pide, porque de lo
 * que tenga el otro no hay dato aquí — y si no le quedan, la comprobación al
 * aceptar lo dirá.
 */
function selector_trato(string $prefijo, string $campo, array $cromos, int $maxLado): void {
    ?>
    <div class="campo">
      <label for="<?= $prefijo ?>-buscar">Buscar</label>
      <input type="search" id="<?= $prefijo ?>-buscar" placeholder="Nombre, equipo o rareza"
             autocomplete="off" data-busca-en="<?= $prefijo ?>-lista">
    </div>

    <div class="selector-cartas" id="<?= $prefijo ?>-lista">
      <?php foreach ($cromos as $c): ?>
        <?php $tope = isset($c['copias']) ? min((int) $c['copias'], $maxLado) : $maxLado; ?>
        <label class="selector-item"
               data-nombre="<?= htmlspecialchars($c['nombre']) ?>"
               data-equipo="<?= htmlspecialchars($c['equipo'] ?? '') ?>"
               data-rareza-nombre="<?= htmlspecialchars($c['rareza'] ?? '') ?>">
          <?php render_carta($c, [
              'tamano'   => 'sm',
              'cantidad' => isset($c['copias']) ? (int) $c['copias'] : null,
          ]); ?>
          <input class="selector-cant" type="number"
                 name="<?= $campo ?>[<?= (int) $c['id_cromo'] ?>]"
                 min="0" max="<?= $tope ?>" step="1" value="0"
                 aria-label="Cuántas de <?= htmlspecialchars($c['nombre']) ?>">
        </label>
      <?php endforeach; ?>
      <p class="selector-vacio" hidden>Ninguna carta coincide con esa búsqueda.</p>
    </div>
    <?php
}

/** Las cartas de una oferta ya hecha, en pequeño y sin tocar nada. */
function tira_trato(array $cartas, string $vacio): void {
    if (!$cartas) {
        echo '<p class="t-body-sm t-dim">' . htmlspecialchars($vacio) . '</p>';
        return;
    }
    echo '<div class="trato-cartas">';
    foreach ($cartas as $c) {
        render_carta($c, [
            'tamano'   => 'sm',
            'cantidad' => isset($c['cantidad']) ? (int) $c['cantidad'] : null,
        ]);
    }
    echo '</div>';
}

// ---------------------------------------------------------------------------
// DATOS DE CADA CARA
// ---------------------------------------------------------------------------
$mias = $componer ? $db->listarColeccionVendible($id_usuario) : [];

$otro = null;          // con quién se negocia (null = tablón)
$anuncio = null;       // el anuncio al que se contraoferta
$suyas = [];           // lo que se le puede pedir
$tituloComponer = '';
$textoComponer  = '';

if ($componer) {
    if ($responder > 0) {
        $anuncio = $db->detalleIntercambio($responder);
        if (!$anuncio || $anuncio['estado'] !== 'abierto' || $anuncio['id_destinatario'] !== null
            || (int) $anuncio['id_emisor'] === $id_usuario) {
            header('Location: intercambios');
            exit;
        }
        /* Contraofertar a un anuncio es pedirle LO QUE OFRECE. No tiene sentido
           enseñarle su colección entera: lo que ha puesto sobre la mesa son
           esas cartas, y solo esas están retenidas para él.

           Se AGRUPAN POR CROMO. `intercambio_da` guarda copias, así que un
           anuncio con dos copias del mismo cromo trae dos filas iguales, y el
           selector pintaría dos campos con el mismo `name="busca[X]"`: el
           navegador manda los dos y PHP se queda con el último, así que pedir
           las dos copias contaría como una. */
        $suyas = [];
        foreach ($anuncio['da'] as $c) {
            $k = (int) $c['id_cromo'];
            if (isset($suyas[$k])) { $suyas[$k]['copias']++; continue; }
            $suyas[$k] = $c + ['copias' => 1];
        }
        $suyas = array_values($suyas);
        $otro  = ['id' => (int) $anuncio['id_emisor'], 'nombre' => $anuncio['emisor']];
        $tituloComponer = 'Contraoferta a ' . $anuncio['emisor'];
        $textoComponer  = 'Pídele lo que ha publicado y ofrécele lo que quieras a cambio.';

    } elseif ($con > 0) {
        $perfil = $db->perfilPublico($con);
        if (!$perfil || $con === $id_usuario) {
            header('Location: intercambios');
            exit;
        }
        /* Lo que se le puede pedir es lo que tiene LIBRE, con el mismo criterio
           que usa el mercado para decidir qué puede vender. Así no se propone
           algo que no podría entregar aunque quisiera. De paso, sus cartas
           protegidas no salen —y no salen marcadas como protegidas, que es lo
           que `usuario.php` ya evita a propósito: el candado dice qué no
           piensas soltar, y eso es información suya. */
        $suyas = $db->listarColeccionVendible($con);
        $otro  = ['id' => $con, 'nombre' => $perfil['nombre']];
        $tituloComponer = 'Intercambio con ' . $perfil['nombre'];
        $textoComponer  = 'Elige qué le das y qué le pides. Tiene 48 horas para contestar.';

    } else {
        /* Anuncio del tablón: se pide del catálogo entero, porque no se sabe
           quién va a leerlo. `listarColeccionCompleta()` viene agrupada por
           expansión y aquí da igual de cuál sea cada carta. */
        foreach ($db->listarColeccionCompleta($id_usuario) as $exp) {
            foreach ($exp['cromos'] as $c) { $suyas[] = $c; }
        }
        $tituloComponer = 'Publicar en el tablón';
        $textoComponer  = 'Lo verá toda la liga. Di qué das y qué buscas a cambio.';
    }
}

$recibidas = [];
$enviadas  = [];
$tablon    = [];

if (!$componer) {
    foreach ($db->listarIntercambiosDe($id_usuario) as $o) {
        if ((int) $o['id_emisor'] === $id_usuario) { $enviadas[] = $o; }
        else                                       { $recibidas[] = $o; }
    }
    /* Los propios se filtran aquí y no en SQL: el tablón de todos es la misma
       consulta, y quitarlos en la vista evita una segunda variante. Los tuyos
       ya salen arriba, en «Has enviado». */
    foreach ($db->listarTablonIntercambios() as $a) {
        if ((int) $a['id_emisor'] !== $id_usuario) { $tablon[] = $a; }
    }
}

$paginaTitulo = $componer ? $tituloComponer : 'Intercambios';
$paginaDesc   = 'Cambia cartas con el resto de la liga. Sin monedas: cartas por cartas.';
$cssExtra     = ['assets/css/intercambios.css'];
include __DIR__ . '/partials/head.php';

$activePage = 'intercambios';
include __DIR__ . '/navbar.php';
?>

<?php if ($componer): ?>
  <?php cabecera([
    'rotulo' => 'Coleccionar',
    'titulo' => $tituloComponer,
    'texto'  => $textoComponer,
    'datos'  => [[$maxLado, 'máximo por lado']],
  ]); ?>
<?php else: ?>
  <?php cabecera([
    'rotulo' => 'Coleccionar',
    'titulo' => 'Intercambios',
    'texto'  => 'Cartas por cartas con el resto de la liga. Sin monedas de por medio.',
    'accion' => '<a class="btn btn-primary" href="intercambios?nuevo=1">'
              . '<i class="ph ph-megaphone" aria-hidden="true"></i> Publicar en el tablón</a>',
    'datos'  => [
      [count($recibidas), count($recibidas) === 1 ? 'te espera' : 'te esperan'],
      [count($enviadas),  count($enviadas) === 1 ? 'enviada' : 'enviadas'],
      [count($tablon),    count($tablon) === 1 ? 'en el tablón' : 'en el tablón'],
    ],
  ]); ?>
<?php endif; ?>

<main id="contenido" class="seccion wrap stack stack-6">

  <?php if ($mensaje): ?>
  <div class="alerta alerta-success" role="status">
    <i class="ph ph-check-circle" aria-hidden="true"></i>
    <span><?= htmlspecialchars($mensaje) ?></span>
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="alerta alerta-danger" role="alert">
    <i class="ph ph-warning-circle" aria-hidden="true"></i>
    <span><?= htmlspecialchars($error) ?></span>
  </div>
  <?php endif; ?>

<?php if ($componer): ?>

  <?php if (empty($mias)): ?>
    <div class="vacio">
      <span class="vacio-ico"><i class="ph ph-lock-simple" aria-hidden="true"></i></span>
      <h3>No tienes cartas libres que ofrecer</h3>
      <p>
        Puede que estén protegidas, en un mazo, a la venta, apostadas en un duelo
        o ya ofrecidas en otro intercambio.
      </p>
      <a class="btn btn-ghost" href="plantilla">Ir a tu plantilla</a>
    </div>
  <?php else: ?>

  <form method="POST" action="intercambios" class="stack stack-5" id="formTrato"
        data-max-lado="<?= $maxLado ?>">
    <?= csrfCampo() ?>
    <input type="hidden" name="accion" value="proponer">
    <input type="hidden" name="destinatario" value="<?= $otro ? (int) $otro['id'] : '' ?>">
    <input type="hidden" name="origen" value="<?= $anuncio ? (int) $anuncio['id_intercambio'] : '' ?>">

    <div class="trato">
      <section class="trato-lado panel">
        <h3>Tú das <span class="mono" data-conteo="da">0</span>/<?= $maxLado ?></h3>
        <?php selector_trato('da', 'da', $mias, $maxLado); ?>
      </section>

      <span class="trato-flecha" aria-hidden="true">
        <i class="ph ph-arrow-right"></i>
      </span>

      <section class="trato-lado panel">
        <h3>
          <?= $otro ? 'Te da ' . htmlspecialchars($otro['nombre']) : 'Buscas' ?>
          <span class="mono" data-conteo="busca">0</span>/<?= $maxLado ?>
        </h3>
        <?php if (empty($suyas)): ?>
          <p class="t-body-sm t-dim">
            <?= $otro
                ? 'Ahora mismo no tiene ninguna carta libre que pueda entregar.'
                : 'No hay cartas en el catálogo.' ?>
          </p>
        <?php else: ?>
          <?php selector_trato('busca', 'busca', $suyas, $maxLado); ?>
        <?php endif; ?>
      </section>
    </div>

    <?php /* Una oferta dirigida SIN pedir nada es un regalo, y regalar cartas
             es legítimo; el anuncio del tablón sí tiene que decir qué busca, o
             es un cartel en blanco. Lo comprueba el servidor de todas formas. */ ?>
    <p class="alerta alerta-danger" id="trato-error" role="alert" hidden>
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span></span>
    </p>

    <div class="fila fila-entre">
      <a class="btn btn-ghost" href="intercambios">Cancelar</a>
      <button type="submit" class="btn btn-primary">
        <?= $otro ? 'Enviar la oferta' : 'Publicar el anuncio' ?>
      </button>
    </div>
  </form>

  <?php endif; ?>

<?php else: ?>

  <?php
  /* Una oferta pintada entera: quién, qué se da, qué se pide y los botones.
     Se declara aquí y no arriba porque solo la usa esta cara, y las tres
     secciones de la bandeja son la misma tarjeta con distintos botones. */
  $pintarOferta = function (array $o, string $papel) use ($id_usuario) {
      [$plazoTexto, $plazoJusto] = plazo_restante($o['vence']);
      $esMia = (int) $o['id_emisor'] === $id_usuario;
      ?>
      <article class="panel">
        <div class="oferta-cab">
          <div>
            <p class="t-caption t-dim">
              <?php if ($papel === 'tablon'): ?>
                Anuncio de
                <a href="usuario?u=<?= (int) $o['id_emisor'] ?>"><?= htmlspecialchars($o['emisor']) ?></a>
              <?php elseif ($esMia): ?>
                <?= $o['id_destinatario'] === null
                    ? 'Tu anuncio del tablón'
                    : 'Enviada a <a href="usuario?u=' . (int) $o['id_destinatario'] . '">'
                      . htmlspecialchars($o['destinatario']) . '</a>' ?>
              <?php else: ?>
                <a href="usuario?u=<?= (int) $o['id_emisor'] ?>"><?= htmlspecialchars($o['emisor']) ?></a>
                te propone
              <?php endif; ?>
            </p>
            <p class="t-caption-sm oferta-plazo<?= $plazoJusto ? ' es-justo' : '' ?>">
              <i class="ph ph-clock" aria-hidden="true"></i> <?= htmlspecialchars($plazoTexto) ?>
              <?php if ((int) $o['contraofertas'] > 0): ?>
                · <?= (int) $o['contraofertas'] ?>
                <?= (int) $o['contraofertas'] === 1 ? 'contraoferta' : 'contraofertas' ?>
              <?php endif; ?>
            </p>
          </div>

          <div class="oferta-acciones">
            <?php if (!$esMia): ?>
              <form method="POST" action="intercambios" class="js-intercambio"
                    data-confirmar="Vas a cerrar el trato con <?= htmlspecialchars($o['emisor'], ENT_QUOTES) ?>. Las cartas cambian de dueño y no se puede deshacer.">
                <?= csrfCampo() ?>
                <input type="hidden" name="accion" value="aceptar">
                <input type="hidden" name="id_intercambio" value="<?= (int) $o['id_intercambio'] ?>">
                <button type="submit" class="btn btn-primary btn-sm">Aceptar</button>
              </form>
            <?php endif; ?>

            <?php if ($papel === 'tablon'): ?>
              <a class="btn btn-ghost btn-sm"
                 href="intercambios?responder=<?= (int) $o['id_intercambio'] ?>">Contraofertar</a>
            <?php else: ?>
              <form method="POST" action="intercambios" class="js-intercambio"
                    data-confirmar="<?= $esMia ? 'Vas a retirar esta oferta.' : 'Vas a rechazar esta oferta.' ?> Las cartas vuelven a quedar libres.">
                <?= csrfCampo() ?>
                <input type="hidden" name="accion" value="cerrar">
                <input type="hidden" name="id_intercambio" value="<?= (int) $o['id_intercambio'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm">
                  <?= $esMia ? 'Retirar' : 'Rechazar' ?>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <div class="trato">
          <div class="trato-lado">
            <h3><?= $esMia ? 'Das' : 'Recibes' ?></h3>
            <?php tira_trato($o['da'], 'Nada.'); ?>
          </div>
          <span class="trato-flecha" aria-hidden="true"><i class="ph ph-arrow-right"></i></span>
          <div class="trato-lado">
            <h3><?= $esMia ? 'Pides' : 'Entregas' ?></h3>
            <?php tira_trato($o['busca'], $esMia ? 'Nada: es un regalo.' : 'Nada. Te la llevas gratis.'); ?>
          </div>
        </div>
      </article>
      <?php
  };
  ?>

  <section class="stack stack-4">
    <div class="panel-head">
      <h2 class="panel-titulo">Te esperan</h2>
      <p class="t-caption t-dim">Ofertas dirigidas a ti. Caducan a las 48 horas.</p>
    </div>
    <?php if (empty($recibidas)): ?>
      <div class="vacio">
        <span class="vacio-ico"><i class="ph ph-tray" aria-hidden="true"></i></span>
        <h3>Nadie te ha propuesto nada</h3>
        <p>Puedes empezar tú desde el perfil de cualquier jugador, o publicar un anuncio.</p>
      </div>
    <?php else: ?>
      <?php foreach ($recibidas as $o) { $pintarOferta($o, 'recibida'); } ?>
    <?php endif; ?>
  </section>

  <?php if (!empty($enviadas)): ?>
  <section class="stack stack-4">
    <div class="panel-head">
      <h2 class="panel-titulo">Has enviado</h2>
      <p class="t-caption t-dim">Mientras estén abiertas, esas cartas tuyas quedan retenidas.</p>
    </div>
    <?php foreach ($enviadas as $o) { $pintarOferta($o, 'enviada'); } ?>
  </section>
  <?php endif; ?>

  <section class="stack stack-4">
    <div class="panel-head">
      <h2 class="panel-titulo">El tablón</h2>
      <p class="t-caption t-dim">Anuncios abiertos a toda la liga.</p>
    </div>
    <?php if (empty($tablon)): ?>
      <div class="vacio">
        <span class="vacio-ico"><i class="ph ph-megaphone" aria-hidden="true"></i></span>
        <h3>El tablón está vacío</h3>
        <p>Nadie ha publicado nada todavía. Puedes ser el primero.</p>
        <a class="btn btn-primary" href="intercambios?nuevo=1">Publicar un anuncio</a>
      </div>
    <?php else: ?>
      <?php foreach ($tablon as $o) { $pintarOferta($o, 'tablon'); } ?>
    <?php endif; ?>
  </section>

<?php endif; ?>

</main>

<?php include __DIR__ . '/partials/ficha_carta.php'; ?>
<?php include __DIR__ . '/partials/confirmar.php'; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>

<?= assetScript($base ?? '', 'assets/js/intercambios.js') ?>

</body>
</html>
