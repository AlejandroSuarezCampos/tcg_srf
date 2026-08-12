<?php
session_start();
require_once __DIR__ . '/../db/conexion.php';

if (isset($_SESSION['dictador'])) {
    if ($_SESSION['dictador'] != 1) {
        header('Location: ../landing.php');
        exit;
    }
} else {
    header('Location: ../landing.php');
    exit;
}

$id_cadena = (int) ($_GET['id'] ?? 0);
$cadena = $db->obtenerCadenaAdmin($id_cadena);
if (!$cadena) {
    header('Location: cadenas.php');
    exit;
}

$nodos   = $db->listarNodosAdmin($id_cadena);
$aristas = $db->listarAristasAdmin($id_cadena);
$rivales = $db->listarRivalesAdmin();
foreach ($rivales as &$r) {
    $r['estilos'] = $db->listarEstilosRivalAdmin((int) $r['id_rival']);
}
unset($r);

// El botín va por nodo: se embebe ya resuelto para no tener que pedirlo por
// AJAX nodo a nodo con lo poco que pesa (una cadena no pasa de una veintena).
foreach ($nodos as &$n) {
    $n['loot'] = $db->listarLootNodo((int) $n['id_nodo']);
}
unset($n);

$cromos = $db->listarCromosAdmin();

$huecosPorFormacion = [];
foreach (Tcg::FORMACIONES as $clave => $f) {
    $huecosPorFormacion[$clave] = Tcg::huecosDe($clave);
}

$datosEditor = [
    'idCadena' => $id_cadena,
    'nodos' => $nodos,
    'aristas' => $aristas,
    'rivales' => $rivales,
    'cromos' => $cromos,
    'formaciones' => Tcg::FORMACIONES,
    'huecosPorFormacion' => $huecosPorFormacion,
];

$base         = '../';
$paginaTitulo = 'Editor de mapa — ' . $cadena['nombre'] . ' — Panel';
$paginaDesc   = 'Editor visual del mapa de nodos de una Cadena PvE.';
$cssExtra     = ['panel/assets/css/admin.css'];
include __DIR__ . '/../partials/head.php';

$activeAdmin = 'cadenas';
?>

<div class="admin-shell">
  <?php include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main" id="contenido">
    <div class="admin-head">
      <div>
        <p class="t-caption t-dim"><a href="cadenas.php">&larr; Cadenas</a></p>
        <h1><?= htmlspecialchars($cadena['nombre']) ?></h1>
        <p>Arrastra los nodos para moverlos. Tira de la anilla de un nodo hasta otro para conectarlos. Clic en un nodo para editarlo.</p>
      </div>
      <div class="row-actions" style="gap:var(--space-3);">
        <button type="button" class="btn btn-ghost" onclick="SRF.cadenaEditor.nuevoNodo('partido')">
          <i class="ph ph-flag" aria-hidden="true"></i> Nuevo partido
        </button>
        <button type="button" class="btn btn-ghost" onclick="SRF.cadenaEditor.nuevoNodo('cofre')">
          <i class="ph ph-gift" aria-hidden="true"></i> Nuevo cofre
        </button>
      </div>
    </div>

    <div class="cadena-editor-lienzo-wrap">
      <div class="cadena-editor-lienzo" id="cadenaLienzo">
        <svg class="cadena-editor-svg" id="cadenaSvg"></svg>
        <!-- Los nodos los pinta JS: son demasiado dinámicos (arrastre, alta, baja) para tenerlos como marcado fijo. -->
      </div>
    </div>
  </main>
</div>

<!-- Modal de edición de nodo -->
<div class="modal modal--ancha" id="modalNodo" role="dialog" aria-modal="true" aria-labelledby="modalNodoTitulo" aria-hidden="true">
  <div class="modal-caja modal-caja--ancha">
    <div class="modal-head">
      <h2 id="modalNodoTitulo">Nodo</h2>
      <button type="button" class="modal-cerrar" onclick="SRF.cadenaEditor.cerrarModalNodo()" aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <div class="form-grid">
      <div class="campo">
        <label for="fn_tipo">Tipo de nodo</label>
        <select id="fn_tipo" onchange="SRF.cadenaEditor.alternarTipoNodo()">
          <option value="partido">Partido</option>
          <option value="cofre">Cofre</option>
        </select>
      </div>

      <div class="campo">
        <label for="fn_nombre">Nombre (opcional)</label>
        <input type="text" id="fn_nombre" placeholder="Se muestra en el mapa">
      </div>

      <div class="campo campo-full">
        <div class="fila-interruptor">
          <span>Es el nodo final (el cofre que cierra la cadena)</span>
          <label class="interruptor">
            <input type="checkbox" id="fn_es_final">
            <span class="interruptor-riel"></span>
          </label>
        </div>
      </div>

      <div class="campo campo-full">
        <button type="button" class="btn btn-primary" onclick="SRF.cadenaEditor.guardarNodo()">Guardar nodo</button>
        <button type="button" class="btn btn-ghost es-peligro" onclick="SRF.cadenaEditor.borrarNodo()">
          <i class="ph ph-trash" aria-hidden="true"></i> Eliminar nodo
        </button>
      </div>
    </div>

    <div id="fn_bloque_rival" class="cadena-editor-bloque">
      <h3>Rival</h3>
      <div class="form-grid">
        <div class="campo">
          <label for="fn_rival">Rival</label>
          <select id="fn_rival" onchange="SRF.cadenaEditor.cambiarRival()">
            <option value="">— Sin rival asignado —</option>
          </select>
        </div>
        <div class="campo" style="align-self:end;">
          <button type="button" class="btn btn-plano" onclick="SRF.cadenaEditor.mostrarNuevoRival()">
            <i class="ph ph-plus" aria-hidden="true"></i> Nuevo rival
          </button>
        </div>
      </div>

      <div id="fn_nuevo_rival" class="form-grid" hidden>
        <div class="campo">
          <label for="fn_rival_nombre">Nombre del rival</label>
          <input type="text" id="fn_rival_nombre" placeholder="Ej. Escuadra Fantasma">
        </div>
        <div class="campo">
          <label for="fn_rival_escudo">Escudo (ruta, opcional)</label>
          <input type="text" id="fn_rival_escudo" placeholder="./assets/img/...">
        </div>
        <div class="campo campo-full">
          <label for="fn_rival_descripcion">Descripción (opcional)</label>
          <input type="text" id="fn_rival_descripcion">
        </div>
        <div class="campo campo-full">
          <button type="button" class="btn btn-plano" onclick="SRF.cadenaEditor.crearRival()">Crear rival</button>
        </div>
      </div>

      <div id="fn_bloque_estilo" hidden>
        <h3>Estilo (alineación del rival)</h3>
        <div class="form-grid">
          <div class="campo">
            <label for="fn_estilo">Estilo ya creado</label>
            <select id="fn_estilo" onchange="SRF.cadenaEditor.cambiarEstilo()">
              <option value="">— Elige un estilo —</option>
            </select>
          </div>
          <div class="campo" style="align-self:end;">
            <button type="button" class="btn btn-plano" onclick="SRF.cadenaEditor.mostrarNuevoEstilo()">
              <i class="ph ph-plus" aria-hidden="true"></i> Nuevo estilo
            </button>
          </div>
        </div>

        <div id="fn_nuevo_estilo" class="form-grid" hidden>
          <div class="campo">
            <label for="fn_estilo_nombre">Nombre del estilo</label>
            <input type="text" id="fn_estilo_nombre" placeholder="Ej. 4-4-2 ofensivo">
          </div>
          <div class="campo">
            <label for="fn_estilo_formacion">Formación</label>
            <select id="fn_estilo_formacion"></select>
          </div>
          <div class="campo campo-full">
            <button type="button" class="btn btn-plano" onclick="SRF.cadenaEditor.crearEstilo()">Crear estilo</button>
          </div>
        </div>

        <div id="fn_cartas_wrap"></div>
      </div>
    </div>

    <div class="cadena-editor-bloque">
      <h3>Botín de este nodo</h3>
      <div id="fn_loot_lista"></div>

      <div class="form-grid">
        <div class="campo">
          <label for="fl_tipo">Tipo</label>
          <select id="fl_tipo" onchange="SRF.cadenaEditor.alternarTipoLoot()">
            <option value="monedas">Monedas</option>
            <option value="cromo">Cromo</option>
            <option value="cromo_limitado">Cromo limitado (numerado)</option>
          </select>
        </div>
        <div class="campo" id="fl_grupo_monedas">
          <label for="fl_monedas">Monedas</label>
          <input type="number" id="fl_monedas" min="0" value="100">
        </div>
        <div class="campo" id="fl_grupo_cromo" hidden>
          <label for="fl_cromo">Cromo</label>
          <select id="fl_cromo"></select>
        </div>
        <div class="campo">
          <label for="fl_probabilidad">Probabilidad %</label>
          <input type="number" id="fl_probabilidad" min="0" max="100" step="0.01" value="100">
        </div>
        <div class="campo">
          <label for="fl_rango">Rango mínimo exigido</label>
          <select id="fl_rango">
            <option value="">Cualquiera (o el cofre)</option>
            <option value="S">S</option>
            <option value="A">A o mejor</option>
            <option value="B">B o mejor</option>
          </select>
        </div>
        <div class="campo campo-full">
          <button type="button" class="btn btn-plano" onclick="SRF.cadenaEditor.crearLoot()">Añadir botín</button>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/confirmar.php'; ?>

<?php $pieCompleto = false; include __DIR__ . '/../partials/footer.php'; ?>
<script>window.CADENA_EDITOR_DATOS = <?= json_encode($datosEditor) ?>;</script>
<script src="<?= htmlspecialchars(assetUrl($base, 'panel/assets/js/scriptCadenaEditor.js')) ?>"></script>
</body>
</html>
