<?php
session_start();
require_once __DIR__ . '/../db/conexion.php';
require_once __DIR__ . '/../components/carta.php';
require_once __DIR__ . '/../partials/csrf.php';
require_once __DIR__ . '/../partials/subida_imagen.php';

if (isset($_SESSION['dictador'])) {
    if ($_SESSION['dictador'] != 1) {
        header('Location: ../landing.php');
        exit;
    }
} else {
    header('Location: ../landing.php');
    exit;
}

// ----- Borrado (?eliminar=ID) -----
if (isset($_GET['eliminar'])) {
    if (!csrfValido($_GET['csrf'] ?? null)) {
        header('Location: cromos.php?error=csrf');
        exit;
    }
    $borrado = $db->eliminarCromo((int) $_GET['eliminar']);
    header('Location: cromos.php' . ($borrado ? '' : '?error=cromo_en_uso'));
    exit;
}

// ----- Creación / edición (POST desde el modal) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        header('Location: cromos.php?error=csrf');
        exit;
    }
    $id_cromo     = $_POST['id_cromo'] ?? '';
    $nombre       = trim($_POST['nombre'] ?? '');
    $posicion     = $_POST['posicion'] ?? '';
    $descripcion  = trim($_POST['descripcion'] ?? '');
    $imagen       = trim($_POST['imagen'] ?? '');
    $id_expansion = (int) ($_POST['id_expansion'] ?? 0);
    $id_equipo    = (int) ($_POST['id_equipo'] ?? 0);
    $id_rareza    = (int) ($_POST['id_rareza'] ?? 0);
    $id_afinidad  = (int) ($_POST['id_afinidad'] ?? 0);
    $ataque       = max(0, min(99, (int) ($_POST['ataque'] ?? 0)));
    $defensa      = max(0, min(99, (int) ($_POST['defensa'] ?? 0)));
    $tecnica      = max(0, min(99, (int) ($_POST['tecnica'] ?? 0)));
    $compo        = $_POST['compo'] ?? '';
    $universo     = ($_POST['universo'] ?? 'srf') === 'ie' ? 'ie' : 'srf';
    /* Cupo de la tirada numerada. Vacío o 0 = la carta no es de tirada
       limitada. Se acota por arriba porque un cupo de siete cifras no es una
       tirada limitada, es una errata. */
    $cupoNumerado = max(0, min(99999, (int) ($_POST['cupo_numerado'] ?? 0)));
    /* Las dos banderas de visibilidad y reparto (migraciones `030` y `040`).
       Son ejes distintos: una carta puede verse en el álbum y no salir en
       sobres —una tirada numerada— o al revés. */
    $soloCadena = isset($_POST['solo_cadena']) ? 1 : 0;
    $enSobres   = isset($_POST['en_sobres']) ? 1 : 0;
    $errorSubida  = '';

    // Si se ha subido un archivo, gana a la ruta escrita a mano: se guarda en
    // assets/img/Cromos/<expansión>/ (misma carpeta que usan ya las cartas de
    // esa expansión) con un nombre generado, nunca el que trae el navegador.
    if (!empty($_FILES['imagen_archivo']) && $_FILES['imagen_archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $nombreExpansion = '';
        foreach ($db->listarExpansiones() as $e) {
            if ((int) $e['id_expansion'] === $id_expansion) { $nombreExpansion = $e['nombre']; break; }
        }
        $carpeta = slugCarpetaExpansion($nombreExpansion !== '' ? $nombreExpansion : 'Sin_expansion');
        $carpetaDisco = __DIR__ . '/../assets/img/Cromos/' . $carpeta . '/';
        $carpetaWeb   = './assets/img/Cromos/' . $carpeta . '/';

        $subida = subirImagenPanel($_FILES['imagen_archivo'], $carpetaDisco, $carpetaWeb, $nombre !== '' ? $nombre : 'cromo');
        if ($subida['ok']) {
            $imagen = $subida['ruta'];
        } else {
            $errorSubida = $subida['error'];
        }
    }

    if ($errorSubida !== '') {
        header('Location: cromos.php?error=' . urlencode($errorSubida));
        exit;
    }

    if ($id_cromo !== '') {
        $db->actualizarCromo((int) $id_cromo, $nombre, $posicion, $descripcion, $imagen, $id_expansion, $id_equipo, $id_rareza, $id_afinidad, $ataque, $defensa, $tecnica, $universo, $cupoNumerado, $soloCadena, $enSobres);
        $idCromoFinal = (int) $id_cromo;
    } else {
        $idCromoFinal = (int) $db->crearCromo($nombre, $posicion, $descripcion, $imagen, $id_expansion, $id_equipo, $id_rareza, $id_afinidad, $ataque, $defensa, $tecnica, 0, $universo, $cupoNumerado, $soloCadena, $enSobres);
    }

    // Compo (rasgo de configuración): "" = automático, "aleatorio" = uno al
    // azar entre los 4 de tipo configuración, o un id concreto elegido a mano.
    // Se resuelve ANTES de derivarRasgosConfiguracion() de abajo: fijar aquí
    // un id (o borrar el override para volver a automático) es justo lo que
    // decide si esa derivación toca o no esta carta.
    $configuracion = array_values(array_filter($db->rasgosCatalogo(), fn($r) => $r['tipo'] === 'configuracion'));
    $idsValidos    = array_column($configuracion, 'id_rasgo');

    if ($compo === 'aleatorio' && $configuracion) {
        $elegido = $configuracion[array_rand($configuracion)];
        $db->asignarRasgoManual($idCromoFinal, (int) $elegido['id_rasgo']);
    } elseif ($compo !== '' && in_array((int) $compo, $idsValidos, true)) {
        $db->asignarRasgoManual($idCromoFinal, (int) $compo);
    } else {
        // "Automático": si tenía un override manual, se quita para que la
        // derivación de abajo pueda asignarle el que le toque de verdad.
        $db->asignarRasgoManual($idCromoFinal, null);
    }

    // Capa 2: el rasgo de configuración sale del cruce puesto × afinidad, así
    // que cambiar cualquiera de los dos lo invalida. Se rederiva aquí para que
    // una carta nueva nunca se quede sin rasgo y una editada no conserve el que
    // le correspondía antes. No pisa las asignaciones marcadas como manuales
    // (ni la de arriba, recién fijada, ni ninguna otra carta ya curada a mano).
    $db->derivarRasgosConfiguracion();

    header('Location: cromos.php');
    exit;
}

// ----- Datos para la tabla y los selects del formulario -----
$equipos     = $db->listarEquipos();
$expansiones = $db->listarExpansiones();
$rarezasDB   = $db->listarRarezas();
$afinidades  = $db->listarAfinidades();
$posiciones  = ['POR', 'DF', 'MC', 'DC', 'ENT', 'GER', 'ESCUDO', 'PRESIDENTE'];

// Los 4 rasgos de "compo" que se pueden elegir a mano (Contraataque, Vínculo,
// Justicia, Brecha) — el resto del catálogo (afinidad/derivado) no aplica aquí.
$rasgosConfig = array_values(array_filter($db->rasgosCatalogo(), fn($r) => $r['tipo'] === 'configuracion'));
$rasgosConfigPorId = [];
foreach ($rasgosConfig as $r) { $rasgosConfigPorId[(int) $r['id_rasgo']] = $r['nombre']; }

$rarezas = [];
foreach ($rarezasDB as $r) {
    $rarezas[$r['id_rareza']] = $r['nombre'];
}

$cromos = $db->listarCromosAdmin();

// ----- Filtros opcionales (buscar por nombre / expansión) -----
$filtroTexto     = trim($_GET['q'] ?? '');
$filtroExpansion = $_GET['id_expansion'] ?? '';

if ($filtroTexto !== '') {
    $cromos = array_values(array_filter($cromos, fn($c) => stripos($c['nombre'], $filtroTexto) !== false));
}
if ($filtroExpansion !== '') {
    $cromos = array_values(array_filter($cromos, fn($c) => (string) $c['id_expansion'] === (string) $filtroExpansion));
}

$base         = '../';
$paginaTitulo = 'Cromos — Panel';
$paginaDesc   = 'Crea, edita y elimina los cromos disponibles en el juego.';
$cssExtra     = ['panel/assets/css/admin.css'];
include __DIR__ . '/../partials/head.php';

$activeAdmin = 'cromos';
?>

<div class="admin-shell">
  <?php include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main" id="contenido">
    <div class="admin-head">
      <div>
        <h1>Cromos</h1>
        <p>Crea, edita y elimina los cromos disponibles en el juego.</p>
      </div>
      <button type="button" class="btn btn-primary" onclick="abrirModalCromo()">
        <i class="ph ph-plus" aria-hidden="true"></i> Nuevo cromo
      </button>
    </div>

    <?php if (($_GET['error'] ?? '') === 'cromo_en_uso'): ?>
    <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--e-5);">
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span>No se pudo eliminar por un error inesperado. Inténtalo de nuevo.</span>
    </div>
    <?php elseif (($_GET['error'] ?? '') === 'csrf'): ?>
    <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--e-5);">
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span>La página ha caducado, inténtalo de nuevo.</span>
    </div>
    <?php elseif (!empty($_GET['error'])): ?>
    <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--e-5);">
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span><?= htmlspecialchars($_GET['error']) ?></span>
    </div>
    <?php endif; ?>

    <form method="GET" class="barra-filtros">
      <div class="campo">
        <label for="c-buscar">Buscar</label>
        <input type="search" name="q" id="c-buscar" value="<?= htmlspecialchars($filtroTexto) ?>"
               placeholder="Nombre del cromo">
      </div>
      <div class="campo">
        <label for="c-expansion">Expansión</label>
        <select name="id_expansion" id="c-expansion" onchange="this.form.submit()">
          <option value="">Todas</option>
          <?php foreach ($expansiones as $ex): ?>
          <option value="<?= (int) $ex['id_expansion'] ?>" <?= (string) $filtroExpansion === (string) $ex['id_expansion'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($ex['nombre']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="barra-filtros-acciones">
        <button type="submit" class="btn btn-ghost">Filtrar</button>
        <?php if ($filtroTexto !== '' || $filtroExpansion !== ''): ?>
        <a class="btn btn-plano" href="cromos.php">Quitar</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="tabla-wrap">
      <table class="tabla">
        <thead>
          <tr>
            <th scope="col">Cromo</th>
            <th scope="col">Equipo</th>
            <th scope="col">Expansión</th>
            <th scope="col">Posición</th>
            <th scope="col">Rareza</th>
            <th scope="col">ATA/DEF/TÉC</th>
            <th scope="col">Compo</th>
            <th scope="col" style="text-align:right;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cromos as $c):
              $imagenPanel = $c['imagen'] ? '.' . $c['imagen'] : '../assets/img/perfil/apple-icon-120x120.png';
          ?>
          <tr>
            <td>
              <div class="admin-row-main">
                <img class="admin-thumb" src="<?= htmlspecialchars($imagenPanel) ?>" alt="">
                <div>
                  <div class="admin-cell-title"><?= htmlspecialchars($c['nombre']) ?></div>
                  <div class="admin-cell-sub">ID #<?= (int) $c['id_cromo'] ?></div>
                </div>
              </div>
            </td>
            <td><?= htmlspecialchars($c['equipo']) ?></td>
            <td><?= htmlspecialchars($c['expansion']) ?></td>
            <td><?= htmlspecialchars($c['posicion']) ?></td>
            <td><?= render_rareza((int) $c['id_rareza'], $rarezas[$c['id_rareza']] ?? $c['rareza']) ?></td>
            <td class="mono"><?= (int) $c['ataque'] ?>/<?= (int) $c['defensa'] ?>/<?= (int) $c['tecnica'] ?></td>
            <td>
              <?php if ($c['id_rasgo_compo']): ?>
                <?= htmlspecialchars($rasgosConfigPorId[(int) $c['id_rasgo_compo']] ?? '—') ?>
                <?= ((int) $c['compo_manual'] === 1) ? '<span class="t-caption t-dim">(manual)</span>' : '' ?>
              <?php else: ?>
                <span class="t-dim">—</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <button type="button" class="icon-btn" title="Editar"
                        onclick='abrirModalCromo(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'>
                  <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                </button>
                <button type="button" class="icon-btn es-peligro" title="Eliminar"
                        onclick="pedirBorrado('<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>', <?= (int) $c['id_cromo'] ?>)">
                  <i class="ph ph-trash" aria-hidden="true"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($cromos)): ?>
          <tr><td colspan="8" style="text-align:center; color:var(--ceniza); padding:40px;">No se encontraron cromos con esos filtros.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <p class="t-caption t-dim" style="margin-top:var(--e-4);"><b class="mono"><?= count($cromos) ?></b> cromos mostrados</p>
  </main>
</div>

<!-- Modal crear / editar cromo -->
<div class="modal" id="modalCromo" role="dialog" aria-modal="true" aria-labelledby="modalCromoTitulo" aria-hidden="true">
  <div class="modal-caja modal-caja--ancha">
    <div class="modal-head">
      <h2 id="modalCromoTitulo">Nuevo cromo</h2>
      <button type="button" class="modal-cerrar" onclick="cerrarModalCromo()" aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <form method="POST" action="cromos.php" id="formCromo" enctype="multipart/form-data">
      <?= csrfCampo() ?>
      <input type="hidden" name="id_cromo" id="f_id_cromo">

      <div class="form-grid">
        <div class="campo campo-full">
          <label for="f_nombre">Nombre del cromo</label>
          <input type="text" name="nombre" id="f_nombre" placeholder="Ej. Mark Evans" required>
        </div>

        <div class="campo campo-full">
          <label>Imagen</label>
          <div class="thumb-upload">
            <img class="admin-thumb" id="f_preview" src="../assets/img/perfil/apple-icon-120x120.png" alt="">
            <div class="thumb-upload-text">
              <b>Sube un archivo o pega la ruta de una imagen ya subida</b>
              <code>./assets/img/Cromos/...</code>
            </div>
          </div>
          <input type="file" name="imagen_archivo" id="f_imagen_archivo" accept="image/png,image/jpeg,image/webp" style="margin-top:var(--e-2);">
          <input type="text" name="imagen" id="f_imagen" placeholder="./assets/img/Cromos/..." style="margin-top:var(--e-2);">
          <span class="campo-hint">Si eliges un archivo, se guarda en <code>assets/img/Cromos/&lt;expansión&gt;/</code> y sustituye a la ruta de abajo.</span>
        </div>

        <?php /* EQUIPO, con alta desde aquí mismo.
                 Antes esto era un desplegable a secas, y el orden natural al
                 meter contenido va al revés: llega un jugador de un equipo que
                 todavía no existe. Había que dejar el formulario a medias, ir a
                 la base de datos a crear el equipo y volver a empezar.
                 El desplegable lleva ahora una opción de alta al final; al
                 elegirla salen los dos campos mínimos y el equipo nuevo queda
                 seleccionado sin recargar ni perder lo escrito. */ ?>
        <div class="campo">
          <label for="f_id_equipo">Equipo</label>
          <select name="id_equipo" id="f_id_equipo" onchange="SRF.equipos.alternarAlta('f')">
            <?php foreach ($equipos as $eq): ?>
            <option value="<?= (int) $eq['id_equipo'] ?>"><?= htmlspecialchars($eq['nombre']) ?></option>
            <?php endforeach; ?>
            <option value="nuevo">&#65291; Crear un equipo nuevo&hellip;</option>
          </select>
        </div>

        <?php /* Los campos del alta. Ocultos hasta que se elige «crear uno
                 nuevo»: no son parte de crear un cromo, son un desvío. */ ?>
        <div class="campo campo-full equipo-alta" id="f_equipo_alta" hidden>
          <label for="f_equipo_nombre">Nombre del equipo nuevo</label>
          <div class="equipo-alta-fila">
            <input type="text" id="f_equipo_nombre" maxlength="100" placeholder="Ej. Instituto Raimon">
            <select id="f_equipo_universo" aria-label="Universo del equipo nuevo">
              <?php foreach (Tcg::UNIVERSOS as $cl => $nom): ?>
                <option value="<?= $cl ?>"><?= htmlspecialchars($nom) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-plano" onclick="SRF.equipos.crear('f')">Crear</button>
          </div>
          <span class="campo-hint" id="f_equipo_aviso" role="status" aria-live="polite">
            El universo es solo decorativo: se enseña en la carta y no cambia nada del juego.
          </span>
        </div>

        <?php /* UNIVERSO. Va en la carta y no en el equipo (migración `037`):
                 un equipo puede alinear a un personaje del Inazuma original
                 junto a jugadores propios. Es decorativo — no lo lee ningún
                 motor y no cambia estadísticas ni probabilidades. */ ?>
        <div class="campo">
          <label for="f_universo">Universo</label>
          <select name="universo" id="f_universo">
            <?php foreach (Tcg::UNIVERSOS as $cl => $nom): ?>
              <option value="<?= $cl ?>"><?= htmlspecialchars($nom) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="campo-hint">Solo decorativo: se enseña en la carta.</span>
        </div>

        <?php /* NUMERACIÓN (migración `038`). Cuántas copias existen de esta
                 carta en todo el juego. Cada una se entrega con su número
                 (#7/50) y cuando se agota el cupo deja de repartirse.
                 Vacío o 0 = carta normal, sin tirada limitada. */ ?>
        <div class="campo">
          <label for="f_cupo_numerado">Tirada numerada</label>
          <input type="number" name="cupo_numerado" id="f_cupo_numerado"
                 min="0" max="99999" step="1" placeholder="0 = sin numerar">
          <span class="campo-hint">
            Cuántas copias existirán. Cada una lleva su número (#7/50).
            Las numeradas <b>no salen en sobres</b>: se entregan como botín de cadena.
          </span>
        </div>

        <?php /* DÓNDE APARECE ESTA CARTA. Dos ejes independientes:
                 · el álbum enseña TODO lo que existe, así que esconder una
                   carta de ahí es lo que la convierte en secreta;
                 · y entrar o no en el sorteo de los sobres es otra decisión —
                   una tirada numerada tiene que poder verse y perseguirse
                   aunque solo se entregue como botín.
                 Juntarlos en un solo interruptor obligaría a esconder del
                 álbum todo lo que no salga en sobres. */ ?>
        <div class="campo campo-full">
          <div class="fila-interruptor">
            <span>
              Aparece en los sobres
              <span class="campo-hint">
                Al quitarlo, la carta solo se consigue por otras vías: botín de cadena,
                códigos o el mercado. Las numeradas suelen ir así.
              </span>
            </span>
            <label class="interruptor">
              <input type="checkbox" name="en_sobres" id="f_en_sobres" checked>
              <span class="interruptor-riel"></span>
            </label>
          </div>
        </div>

        <div class="campo campo-full">
          <div class="fila-interruptor">
            <span>
              Carta secreta
              <span class="campo-hint">
                No se enseña en el álbum ni cuenta para completarlo, y tampoco sale
                en sobres. Para cartas que solo existen como recompensa.
              </span>
            </span>
            <label class="interruptor">
              <input type="checkbox" name="solo_cadena" id="f_solo_cadena">
              <span class="interruptor-riel"></span>
            </label>
          </div>
        </div>

        <div class="campo">
          <label for="f_id_expansion">Expansión</label>
          <select name="id_expansion" id="f_id_expansion">
            <?php foreach ($expansiones as $ex): ?>
            <option value="<?= (int) $ex['id_expansion'] ?>"><?= htmlspecialchars($ex['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="f_posicion">Posición</label>
          <select name="posicion" id="f_posicion">
            <?php foreach ($posiciones as $p): ?>
            <option value="<?= $p ?>"><?= $p ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="f_id_rareza">Rareza</label>
          <select name="id_rareza" id="f_id_rareza">
            <?php foreach ($rarezas as $id => $nombre): ?>
            <option value="<?= (int) $id ?>"><?= htmlspecialchars($nombre) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo campo-full">
          <label for="f_id_afinidad">Afinidad</label>
          <select name="id_afinidad" id="f_id_afinidad">
            <?php foreach ($afinidades as $af): ?>
            <option value="<?= (int) $af['id'] ?>"><?= htmlspecialchars($af['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="f_ataque">Ataque</label>
          <input type="number" name="ataque" id="f_ataque" min="0" max="99" value="0">
        </div>
        <div class="campo">
          <label for="f_defensa">Defensa</label>
          <input type="number" name="defensa" id="f_defensa" min="0" max="99" value="0">
        </div>
        <div class="campo">
          <label for="f_tecnica">Técnica</label>
          <input type="number" name="tecnica" id="f_tecnica" min="0" max="99" value="0">
        </div>

        <?php /* Rellena las tres con un tiro dentro del rango real de la
                 rareza y la posición elegidas (la misma tabla que usa la
                 importación masiva). Escribir 24 combinaciones de números a
                 mano y de memoria es como acaban saliendo comunes más fuertes
                 que épicos. Los valores quedan editables: sugiere, no impone. */ ?>
        <div class="campo campo-full">
          <button type="button" class="btn btn-plano btn-sm" id="f_stats_aleatorias">
            <i class="ph ph-dice-five" aria-hidden="true"></i>
            Aleatorizar según rareza y posición
          </button>
          <p class="campo-hint" id="f_stats_aviso" role="status" aria-live="polite"></p>
        </div>

        <div class="campo campo-full">
          <label for="f_compo">Compo (rasgo de configuración)</label>
          <select name="compo" id="f_compo">
            <option value="">Automático — según posición y afinidad</option>
            <option value="aleatorio">Aleatorio</option>
            <?php foreach ($rasgosConfig as $r): ?>
            <option value="<?= (int) $r['id_rasgo'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="campo-hint">"Automático" deriva el rasgo del cruce posición × afinidad. Elegir uno a mano (o "Aleatorio", que fija uno al azar) lo deja fijado hasta que vuelvas a poner "Automático".</span>
        </div>

        <div class="campo campo-full">
          <label for="f_descripcion">Descripción</label>
          <textarea name="descripcion" id="f_descripcion" placeholder="Breve descripción o lore del cromo..."></textarea>
        </div>
      </div>

      <div class="modal-pie">
        <button type="button" class="btn btn-ghost" onclick="cerrarModalCromo()">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="f_submit">Crear cromo</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../partials/confirmar.php'; ?>

<?php $pieCompleto = false; include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= htmlspecialchars(assetUrl($base, 'panel/assets/js/scriptEquiposAlta.js')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl($base, 'panel/assets/js/scriptCromos.js')) ?>"></script>
</body>
</html>
