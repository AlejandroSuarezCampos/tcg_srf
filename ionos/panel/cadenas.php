<?php
session_start();
require_once __DIR__ . '/../db/conexion.php';
require_once __DIR__ . '/../partials/csrf.php';

if (isset($_SESSION['dictador'])) {
    if ($_SESSION['dictador'] != 1) {
        header('Location: ../landing.php');
        exit;
    }
} else {
    header('Location: ../landing.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['eliminar']) || isset($_GET['eliminar_requisito']))
    && !csrfValido($_REQUEST['csrf'] ?? null)) {
    header('Location: cadenas.php?error=csrf');
    exit;
}

// ----- Borrado de una cadena entera (?eliminar=ID) -----
if (isset($_GET['eliminar'])) {
    $borrada = $db->eliminarCadenaAdmin((int) $_GET['eliminar']);
    header('Location: cadenas.php' . ($borrada ? '' : '?error=progreso_en_uso'));
    exit;
}

// ----- Borrado de un requisito (?eliminar_requisito=ID) -----
if (isset($_GET['eliminar_requisito'])) {
    $db->eliminarRequisito((int) $_GET['eliminar_requisito']);
    header('Location: cadenas.php?requisitos=' . (int) ($_GET['id_cadena'] ?? 0));
    exit;
}

// ----- Añadir un requisito (POST desde el modal de requisitos) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_requisito'])) {
    $id_cadena = (int) $_POST['id_cadena'];
    $tipo = isset(Tcg::REQUISITOS_CADENA[$_POST['tipo'] ?? '']) ? $_POST['tipo'] : 'cadena';

    /* Cada tipo lee SU campo. Un único `valor` compartido por seis desplegables
       significaba que el navegador mandaba los seis y el servidor cogía el que
       tocase por casualidad. */
    $porTipo = [
        'cadena'      => (int) ($_POST['valor_cadena'] ?? 0),
        'cromo'       => (int) ($_POST['valor_cromo']  ?? 0),
        'nivel_album' => (int) ($_POST['valor_album']  ?? 0),
        'monedas'     => (int) ($_POST['valor_monedas'] ?? 0),
        'duelos'      => (int) ($_POST['valor_duelos'] ?? 0),
        'rareza'      => (int) ($_POST['valor_rareza'] ?? 0),
    ];
    $valor    = $porTipo[$tipo];
    $cantidad = (int) ($_POST['cantidad_rareza'] ?? 0);

    // El porcentaje del álbum se acota aquí: pedir el 300 % es pedir lo imposible.
    if ($tipo === 'nivel_album') { $valor = max(1, min(100, $valor)); }

    $ok = true;
    if ($valor > 0) {
        $ok = $db->crearRequisito($id_cadena, $tipo, $valor, $cantidad);
    }
    header('Location: cadenas.php?requisitos=' . $id_cadena . ($ok ? '' : '&error=ciclo_requisito'));
    exit;
}

// ----- Creación / edición de una cadena (POST desde el modal principal) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre']) && !isset($_POST['nuevo_requisito'])) {
    $id_cadena            = $_POST['id_cadena'] ?? '';
    $nombre               = trim($_POST['nombre'] ?? '');
    $descripcion          = trim($_POST['descripcion'] ?? '');
    $anfitrion            = trim($_POST['anfitrion'] ?? '');
    $orden                = (int) ($_POST['orden'] ?? 0);
    $activa               = isset($_POST['activa']) ? 1 : 0;
    $formacion_recompensa = $_POST['formacion_recompensa'] ?? '';
    $fecha_fin            = trim($_POST['fecha_fin'] ?? '');
    $visibilidad          = ($_POST['visibilidad'] ?? 'todos') === 'elegidos' ? 'elegidos' : 'todos';

    if ($id_cadena !== '') {
        $db->actualizarCadenaAdmin((int) $id_cadena, $nombre, $descripcion, $anfitrion, $orden, $activa, $formacion_recompensa, $fecha_fin, $visibilidad);
        $idParaEditor = (int) $id_cadena;
    } else {
        $idParaEditor = $db->crearCadenaAdmin($nombre, $descripcion, $anfitrion, $orden, $activa, $formacion_recompensa, $fecha_fin, $visibilidad);
    }

    /* Los invitados se guardan SIEMPRE, también al pasar la cadena a pública.
       Si solo se guardaran cuando está restringida, volverla pública y luego
       restringida otra vez resucitaría una lista de invitados vieja que nadie
       ha vuelto a mirar. Guardando siempre, lo que se ve en el panel es lo que
       hay. */
    $db->fijarInvitadosCadena($idParaEditor, (array) ($_POST['invitados'] ?? []));

    // Una cadena recién creada no tiene mapa: se manda directa al editor para
    // que el siguiente paso natural sea dibujar el primer nodo, no volver a
    // esta lista sin nada más que hacer.
    if ($id_cadena === '') {
        header('Location: cadena_editor.php?id=' . $idParaEditor);
    } else {
        header('Location: cadenas.php');
    }
    exit;
}

$cadenas = $db->listarCadenasAdmin();

$idRequisitos = isset($_GET['requisitos']) ? (int) $_GET['requisitos'] : null;
$cadenaRequisitos = $idRequisitos ? $db->obtenerCadenaAdmin($idRequisitos) : null;
$requisitos = $idRequisitos ? $db->listarRequisitosAdmin($idRequisitos) : [];
$todasCadenas = $cadenas; // para el select "completar esta otra cadena antes"
$cromosParaRequisito = $idRequisitos ? $db->listarCromosAdmin() : [];
$rarezasRequisito    = $idRequisitos ? $db->listarRarezas() : [];

/* Para el selector de invitados. Se cargan las cuentas una vez y los invitados
   de cada cadena se anexan a su fila: el modal se rellena en cliente desde el
   JSON de la cadena, así que sin esto habría que ir a buscarlos por AJAX solo
   para abrir un modal. */
$usuariosPanel = $db->listarUsuariosAdmin();

// Preset de dificultad PvE aplicado por última vez y sus multiplicadores, para
// poder enseñar en qué está el juego antes de tocarlo.
$presetActual = (string) $db->config('pve_preset', 'normal');
$multsActuales = [];
foreach (Tcg::DIFICULTADES as $dif) {
    $multsActuales[$dif] = (float) $db->config('pve_mult_' . $dif, 1.0);
}
$etiquetaDif = [
    'facil' => 'Fácil', 'medio' => 'Medio', 'dificil' => 'Difícil',
    'muy_dificil' => 'Muy difícil', 'extremo' => 'Extremo',
];
foreach ($cadenas as &$cadenaFila) {
    $cadenaFila['invitados'] = array_map(
        fn($u) => (int) $u['id_usuario'],
        $db->listarInvitadosCadena((int) $cadenaFila['id_cadena'])
    );
}
unset($cadenaFila);

$base         = '../';
$paginaTitulo = 'Cadenas PvE — Panel';
$paginaDesc   = 'Crea y edita las Cadenas de Partido (PvE): el mapa de nodos, los rivales y el botín.';
$cssExtra     = ['panel/assets/css/admin.css'];
include __DIR__ . '/../partials/head.php';

$activeAdmin = 'cadenas';
?>

<div class="admin-shell">
  <?php include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main" id="contenido">
    <div class="admin-head">
      <div>
        <h1>Cadenas PvE</h1>
        <p>Cada cadena es un mapa de nodos (partidos y cofres). Aquí se crean sus datos generales; el mapa, los rivales y el botín se editan en el editor visual.</p>
      </div>
      <button type="button" class="btn btn-primary" onclick="abrirModalCadena()">
        <i class="ph ph-plus" aria-hidden="true"></i> Nueva cadena
      </button>
    </div>

    <?php if (($_GET['error'] ?? '') === 'progreso_en_uso'): ?>
    <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--space-5);">
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span>No se pudo eliminar por un error inesperado. Inténtalo de nuevo.</span>
    </div>
    <?php endif; ?>

    <?php /* CALIBRACIÓN DE DIFICULTAD PvE (migración `033`)
             Antes esto se tocaba escribiendo `pve_mult_<dificultad>` a mano en
             la tabla de configuración, sin saber a qué porcentaje de victorias
             correspondía cada número. Ahora se elige el porcentaje —en forma de
             cuatro presets— y el servidor busca los multiplicadores simulando
             partidos con el mismo motor que los juega. */ ?>
    <section class="panel-calibracion" aria-labelledby="calibracionTitulo">
      <div class="panel-calibracion-texto">
        <h2 id="calibracionTitulo">Dificultad general del PvE</h2>
        <p class="t-body-sm t-dim">
          Elige cuánto quieres que cueste ganar y el servidor ajusta las cinco dificultades.
          Los porcentajes son para un jugador con el equipo de referencia
          (rareza <span class="mono"><?= (int) $db->config('pve_ref_rareza', 3) ?></span>);
          con mejores cartas se gana más, que es de lo que se trata.
        </p>
      </div>

      <div class="calibracion-presets" role="radiogroup" aria-label="Preset de dificultad">
        <?php foreach (Tcg::ETIQUETAS_PRESET_PVE as $clave => $etiqueta): ?>
          <?php $obj = Tcg::PRESETS_PVE[$clave]; ?>
          <label class="calibracion-preset<?= $presetActual === $clave ? ' esta-elegido' : '' ?>">
            <input type="radio" name="pve_preset" value="<?= $clave ?>" class="sr-only"
                   <?= $presetActual === $clave ? 'checked' : '' ?>>
            <b><?= htmlspecialchars($etiqueta) ?></b>
            <?php /* Se enseña el Extremo porque es el número con el que se habló
                     el ajuste y el que de verdad distingue un preset de otro:
                     entre el más blando y el más duro va del 7 % al 1 %. */ ?>
            <span class="t-caption t-dim">
              Extremo: gana el <span class="mono"><?= rtrim(rtrim(number_format($obj['extremo'] * 100, 1, ',', ''), '0'), ',') ?> %</span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="calibracion-pie">
        <button type="button" class="btn btn-primary" id="btnCalibrarGlobal">
          <i class="ph ph-gauge" aria-hidden="true"></i> Aplicar a todo el juego
        </button>
        <span class="t-caption t-dim" id="calibracionEstado" role="status" aria-live="polite">
          Ahora mismo: <b><?= htmlspecialchars(Tcg::ETIQUETAS_PRESET_PVE[$presetActual] ?? $presetActual) ?></b>
        </span>
      </div>

      <div class="tabla-wrap">
        <table class="tabla">
          <thead>
            <tr>
              <th scope="col">Dificultad</th>
              <th scope="col">Fuerza del rival</th>
              <th scope="col">Victorias objetivo</th>
              <th scope="col">Medido</th>
            </tr>
          </thead>
          <tbody id="calibracionCuerpo">
            <?php foreach (Tcg::DIFICULTADES as $dif): ?>
            <tr data-dificultad="<?= $dif ?>">
              <th scope="row"><?= htmlspecialchars($etiquetaDif[$dif] ?? $dif) ?></th>
              <td class="mono" data-celda="mult">×<?= rtrim(rtrim(number_format($multsActuales[$dif], 3, ',', ''), '0'), ',') ?></td>
              <td class="mono" data-celda="objetivo">—</td>
              <td class="mono" data-celda="medido">—</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <div class="tabla-wrap">
      <table class="tabla">
        <thead>
          <tr>
            <th scope="col">Cadena</th>
            <th scope="col">Orden</th>
            <th scope="col">Nodos</th>
            <th scope="col">Recompensa final</th>
            <th scope="col">Estado</th>
            <th scope="col">Quién la ve</th>
            <th scope="col" style="text-align:right;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cadenas as $c):
              $caducada = $c['fecha_fin'] && strtotime($c['fecha_fin']) <= time();
          ?>
          <tr>
            <td>
              <div class="admin-cell-title"><?= htmlspecialchars($c['nombre']) ?></div>
              <div class="admin-cell-sub">ID #<?= (int) $c['id_cadena'] ?><?= $c['anfitrion'] ? ' · ' . htmlspecialchars($c['anfitrion']) : '' ?></div>
            </td>
            <td class="mono"><?= (int) $c['orden'] ?></td>
            <td class="mono"><?= (int) $c['total_nodos'] ?></td>
            <td class="mono"><?= $c['formacion_recompensa'] ? htmlspecialchars($c['formacion_recompensa']) : '—' ?></td>
            <td>
              <?php if ($caducada): ?>
              <span class="status-pill esta-inactivo">Caducada</span>
              <?php elseif ($c['activa']): ?>
              <span class="status-pill esta-activo">Activa</span>
              <?php else: ?>
              <span class="status-pill esta-inactivo">Inactiva</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($c['visibilidad'] === 'elegidos'): ?>
                <?php /* El número importa: una cadena restringida y con cero
                         invitados no la ve NADIE, y es un estado fácil de dejarse
                         puesto sin querer. Se dice aquí, en la lista, en vez de
                         obligar a abrir el modal para descubrirlo. */ ?>
                <?php $nInv = (int) ($c['total_invitados'] ?? 0); ?>
                <span class="status-pill <?= $nInv === 0 ? 'esta-inactivo' : '' ?>">
                  <i class="ph ph-users" aria-hidden="true"></i>
                  <?= $nInv === 0 ? 'Nadie' : $nInv . ($nInv === 1 ? ' persona' : ' personas') ?>
                </span>
              <?php else: ?>
                <span class="admin-cell-sub">Todos</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <a class="icon-btn" title="Editar el mapa" href="cadena_editor.php?id=<?= (int) $c['id_cadena'] ?>">
                  <i class="ph ph-flow-arrow" aria-hidden="true"></i>
                </a>
                <a class="icon-btn" title="Requisitos de entrada" href="cadenas.php?requisitos=<?= (int) $c['id_cadena'] ?>">
                  <i class="ph ph-lock-key" aria-hidden="true"></i>
                </a>
                <button type="button" class="icon-btn" title="Editar datos"
                        onclick='abrirModalCadena(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'>
                  <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                </button>
                <button type="button" class="icon-btn es-peligro" title="Eliminar"
                        onclick="pedirBorradoCadena('<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>', <?= (int) $c['id_cadena'] ?>)">
                  <i class="ph ph-trash" aria-hidden="true"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($cadenas)): ?>
          <tr><td colspan="7" style="text-align:center; color:var(--frost-dim); padding:40px;">Todavía no hay ninguna cadena. Crea la primera con el botón de arriba.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <p class="t-caption t-dim" style="margin-top:var(--space-4);"><b class="mono"><?= count($cadenas) ?></b> cadenas</p>
  </main>
</div>

<!-- Modal crear / editar cadena -->
<div class="modal" id="modalCadena" role="dialog" aria-modal="true" aria-labelledby="modalCadenaTitulo" aria-hidden="true">
  <div class="modal-caja">
    <div class="modal-head">
      <h2 id="modalCadenaTitulo">Nueva cadena</h2>
      <button type="button" class="modal-cerrar" onclick="cerrarModalCadena()" aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <form method="POST" action="cadenas.php" id="formCadena">
      <?= csrfCampo() ?>
      <input type="hidden" name="id_cadena" id="fc_id_cadena">

      <div class="form-grid">
        <div class="campo campo-full">
          <label for="fc_nombre">Nombre</label>
          <input type="text" name="nombre" id="fc_nombre" placeholder="Ej. Ruta de ascenso" required>
        </div>

        <div class="campo campo-full">
          <label for="fc_descripcion">Descripción</label>
          <textarea name="descripcion" id="fc_descripcion" rows="2"></textarea>
        </div>

        <div class="campo">
          <label for="fc_anfitrion">Anfitrión</label>
          <input type="text" name="anfitrion" id="fc_anfitrion" placeholder="Ej. Escuadra Fantasma">
        </div>

        <div class="campo">
          <label for="fc_orden">Orden de presentación</label>
          <input type="number" name="orden" id="fc_orden" value="0" min="0">
        </div>

        <div class="campo">
          <label for="fc_formacion_recompensa">Formación que desbloquea al completarla</label>
          <select name="formacion_recompensa" id="fc_formacion_recompensa">
            <option value="">Ninguna</option>
            <?php foreach (Tcg::FORMACIONES as $clave => $f): ?>
            <option value="<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($f['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="fc_fecha_fin">Caduca el (opcional)</label>
          <input type="date" name="fecha_fin" id="fc_fecha_fin">
        </div>

        <div class="campo campo-full">
          <div class="fila-interruptor">
            <span>Cadena activa (publicada en el sitio)</span>
            <label class="interruptor">
              <input type="checkbox" name="activa" id="fc_activa">
              <span class="interruptor-riel"></span>
            </label>
          </div>
        </div>

        <?php /* «Activa» y «quién la ve» son dos preguntas distintas y por eso
                 son dos controles: activa dice si la cadena está publicada, y
                 esto para quién. Una cadena inactiva no la ve nadie por muchos
                 invitados que tenga — así se puede tener una cadena terminada,
                 restringida a dos personas para probarla, y publicarla luego
                 sin tocar la lista. */ ?>
        <div class="campo campo-full">
          <span class="campo-label">Quién puede verla</span>
          <div class="opciones-visibilidad">
            <label class="opcion-visibilidad">
              <input type="radio" name="visibilidad" value="todos" id="fc_vis_todos" checked
                     onchange="alternarInvitados()">
              <span>
                <b>Todo el mundo</b>
                <span class="t-caption t-dim">Aparece en Cadenas para cualquier jugador.</span>
              </span>
            </label>
            <label class="opcion-visibilidad">
              <input type="radio" name="visibilidad" value="elegidos" id="fc_vis_elegidos"
                     onchange="alternarInvitados()">
              <span>
                <b>Solo quien yo elija</b>
                <span class="t-caption t-dim">Los demás no la ven ni pueden abrirla por su enlace.</span>
              </span>
            </label>
          </div>
        </div>

        <div class="campo campo-full" id="fc_grupo_invitados" hidden>
          <label for="fc_buscar_invitado">Quién entra</label>
          <input type="search" id="fc_buscar_invitado" placeholder="Buscar por nombre" autocomplete="off"
                 oninput="filtrarInvitados(this.value)">
          <p class="t-caption t-dim" id="fc_invitados_resumen" role="status" aria-live="polite"></p>
          <ul class="lista-invitados" id="fc_invitados">
            <?php foreach ($usuariosPanel as $u): ?>
            <li data-nombre="<?= htmlspecialchars(mb_strtolower($u['nombre'])) ?>">
              <label>
                <input type="checkbox" name="invitados[]" value="<?= (int) $u['id_usuario'] ?>"
                       onchange="contarInvitados()">
                <span><?= htmlspecialchars($u['nombre']) ?></span>
              </label>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

      <div class="modal-pie">
        <button type="button" class="btn btn-ghost" onclick="cerrarModalCadena()">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="fc_submit">Crear cadena</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal de requisitos de entrada, abierto directamente si ?requisitos=ID viene en la URL -->
<div class="modal<?= $cadenaRequisitos ? ' is-abierto' : '' ?>" id="modalRequisitos" role="dialog" aria-modal="true" aria-labelledby="modalRequisitosTitulo" aria-hidden="<?= $cadenaRequisitos ? 'false' : 'true' ?>">
  <div class="modal-caja">
    <div class="modal-head">
      <h2 id="modalRequisitosTitulo">Requisitos — <?= $cadenaRequisitos ? htmlspecialchars($cadenaRequisitos['nombre']) : '' ?></h2>
      <a class="modal-cerrar" href="cadenas.php" aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </a>
    </div>

    <?php if ($cadenaRequisitos): ?>
    <?php if (($_GET['error'] ?? '') === 'ciclo_requisito'): ?>
    <div class="alerta alerta-danger" role="alert" style="margin-bottom:var(--space-4);">
      <i class="ph ph-warning-circle" aria-hidden="true"></i>
      <span>Ese requisito cerraría un ciclo: esa cadena (directa o indirectamente) ya exige completar esta. Ninguna de las dos se podría empezar nunca.</span>
    </div>
    <?php endif; ?>
    <div class="tabla-wrap">
      <table class="tabla">
        <thead><tr><th>Tipo</th><th>Exige</th><th style="text-align:right;">Quitar</th></tr></thead>
        <tbody>
          <?php foreach ($requisitos as $r): ?>
          <tr>
            <td><?= htmlspecialchars(Tcg::REQUISITOS_CADENA[$r['tipo']] ?? $r['tipo']) ?></td>
            <td>
              <?php
              /* Lo que exige, en palabras. Los tipos que apuntan a una fila
                 enseñan su nombre; los que guardan un número lo enseñan con su
                 unidad, porque "40" a secas no dice si son monedas o duelos. */
              switch ($r['tipo']) {
                  case 'nivel_album': echo 'Álbum al ' . (int) $r['valor'] . ' %'; break;
                  case 'monedas':     echo number_format((int) $r['valor'], 0, ',', '.') . ' monedas'; break;
                  case 'duelos':      echo (int) $r['valor'] . ' duelos jugados'; break;
                  case 'rareza':      echo (int) $r['cantidad'] . ' × '
                                         . htmlspecialchars($r['nombre_valor'] ?? ('rareza #' . $r['valor'])); break;
                  default:            echo htmlspecialchars($r['nombre_valor'] ?? ('#' . $r['valor']));
              }
              ?>
            </td>
            <td style="text-align:right;">
              <a class="icon-btn es-peligro" title="Quitar"
                 href="cadenas.php?eliminar_requisito=<?= (int) $r['id_requisito'] ?>&id_cadena=<?= (int) $cadenaRequisitos['id_cadena'] ?>&csrf=<?= urlencode(csrfToken()) ?>">
                <i class="ph ph-trash" aria-hidden="true"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($requisitos)): ?>
          <tr><td colspan="3" style="text-align:center; color:var(--frost-dim);">Sin requisitos: se puede entrar libremente.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <form method="POST" action="cadenas.php" class="form-grid" style="margin-top:var(--space-4);">
      <?= csrfCampo() ?>
      <input type="hidden" name="nuevo_requisito" value="1">
      <input type="hidden" name="id_cadena" value="<?= (int) $cadenaRequisitos['id_cadena'] ?>">

      <div class="campo">
        <label for="fr_tipo">Tipo</label>
        <select name="tipo" id="fr_tipo" onchange="SRF.cadenasAlternarTipoRequisito(this.value)">
          <?php foreach (Tcg::REQUISITOS_CADENA as $clave => $etiqueta): ?>
          <option value="<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($etiqueta) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="campo" id="fr_grupo_cadena">
        <label for="fr_valor_cadena">Cadena exigida</label>
        <select name="valor_cadena" id="fr_valor_cadena">
          <?php foreach ($todasCadenas as $tc):
              if ((int) $tc['id_cadena'] === (int) $cadenaRequisitos['id_cadena']) continue;
          ?>
          <option value="<?= (int) $tc['id_cadena'] ?>"><?= htmlspecialchars($tc['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php /* ---- BUSCADOR DE CARTAS ----
               El desplegable llevaba las 469 cartas del catálogo en una lista
               plana: encontrar una era bajar con la rueda. Ahora se filtra por
               texto, rareza, posición y equipo antes de elegir, y todo en el
               navegador — el catálogo ya está aquí, no hace falta ir al
               servidor a cada tecla. */ ?>
      <div class="campo campo-full" id="fr_grupo_cromo" hidden>
        <label for="fr_buscar_cromo">Carta exigida</label>

        <div class="fila-filtros">
          <input type="search" id="fr_buscar_cromo" placeholder="Nombre o equipo"
                 oninput="SRF.cadenasFiltrarCromos()" autocomplete="off">
          <select id="fr_f_rareza" onchange="SRF.cadenasFiltrarCromos()">
            <option value="">Toda rareza</option>
            <?php foreach ($rarezasRequisito as $rz): ?>
            <option value="<?= (int) $rz['id_rareza'] ?>"><?= htmlspecialchars($rz['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="fr_f_posicion" onchange="SRF.cadenasFiltrarCromos()">
            <option value="">Toda posición</option>
            <?php foreach (['POR' => 'Portería', 'DF' => 'Defensa', 'MC' => 'Medio', 'DC' => 'Ataque'] as $k => $v): ?>
            <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach; ?>
          </select>
          <select id="fr_f_equipo" onchange="SRF.cadenasFiltrarCromos()">
            <option value="">Todo equipo</option>
            <?php
            $equiposReq = array_values(array_unique(array_filter(array_column($cromosParaRequisito, 'equipo'))));
            sort($equiposReq);
            foreach ($equiposReq as $eq): ?>
            <option value="<?= htmlspecialchars($eq) ?>"><?= htmlspecialchars($eq) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <select name="valor_cromo" id="fr_valor_cromo" size="8" class="lista-cromos">
          <?php foreach ($cromosParaRequisito as $cr): ?>
          <option value="<?= (int) $cr['id_cromo'] ?>"
                  data-rareza="<?= (int) $cr['id_rareza'] ?>"
                  data-posicion="<?= htmlspecialchars($cr['posicion']) ?>"
                  data-equipo="<?= htmlspecialchars($cr['equipo']) ?>"
                  data-busca="<?= htmlspecialchars(mb_strtolower($cr['nombre'] . ' ' . $cr['equipo'])) ?>">
            <?= htmlspecialchars($cr['nombre']) ?> — <?= htmlspecialchars($cr['equipo']) ?>
            (<?= htmlspecialchars($cr['posicion']) ?>, <?= htmlspecialchars($cr['rareza'] ?? '') ?>)
          </option>
          <?php endforeach; ?>
        </select>
        <p class="campo-hint" id="fr_conteo_cromos" role="status" aria-live="polite"></p>
      </div>

      <div class="campo" id="fr_grupo_album" hidden>
        <label for="fr_valor_album">Porcentaje del álbum</label>
        <input type="number" name="valor_album" id="fr_valor_album" min="1" max="100" value="25">
        <span class="campo-hint">Cuántas de las cartas que existen tiene que tener ya.</span>
      </div>

      <div class="campo" id="fr_grupo_monedas" hidden>
        <label for="fr_valor_monedas">Monedas</label>
        <input type="number" name="valor_monedas" id="fr_valor_monedas" min="1" value="1000">
        <span class="campo-hint">Se comprueba al entrar; no se le cobra nada.</span>
      </div>

      <div class="campo" id="fr_grupo_duelos" hidden>
        <label for="fr_valor_duelos">Duelos jugados</label>
        <input type="number" name="valor_duelos" id="fr_valor_duelos" min="1" value="5">
        <span class="campo-hint">Duelos resueltos. Los amistosos de prueba no cuentan.</span>
      </div>

      <div class="campo" id="fr_grupo_rareza" hidden>
        <label for="fr_valor_rareza">Rareza y cuántas</label>
        <div class="fila-filtros">
          <select name="valor_rareza" id="fr_valor_rareza">
            <?php foreach ($rarezasRequisito as $rz): ?>
            <option value="<?= (int) $rz['id_rareza'] ?>"><?= htmlspecialchars($rz['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="number" name="cantidad_rareza" id="fr_cantidad_rareza" min="1" value="3"
                 aria-label="Cuántas cartas">
        </div>
        <span class="campo-hint">Cartas DISTINTAS de esa rareza; las repetidas cuentan una vez.</span>
      </div>

      <div class="campo campo-full">
        <button type="submit" class="btn btn-plano">Añadir requisito</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../partials/confirmar.php'; ?>

<?php $pieCompleto = false; include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= htmlspecialchars(assetUrl($base, 'panel/assets/js/scriptCadenas.js')) ?>"></script>
</body>
</html>
