<?php
session_start();
require_once __DIR__ . '/../db/conexion.php';
require_once __DIR__ . '/../components/carta.php';

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

// Las rutas de imagen en BD son relativas a la raíz del sitio ("./assets/...",
// pensadas para páginas como mercado.php o coleccion.php). Desde panel/ un
// "./" así apunta a panel/assets/... y la imagen no existe — mismo ajuste que
// ya hace panel/cromos.php con sus miniaturas (anteponer un "." más).
// Se usa una copia aparte para no tocar $cromos, que también viaja tal cual
// a CADENA_EDITOR_DATOS.cromos (ese JSON no usa la imagen para nada).
$cromosParaSelector = array_map(function ($c) {
    if (!empty($c['imagen']))          { $c['imagen']          = '.' . $c['imagen']; }
    if (!empty($c['afinidad_imagen'])) { $c['afinidad_imagen'] = '.' . $c['afinidad_imagen']; }
    return $c;
}, $cromos);

$huecosPorFormacion = [];
$coordsPorFormacion = [];
foreach (Tcg::FORMACIONES as $clave => $f) {
    $huecosPorFormacion[$clave] = Tcg::huecosDe($clave);
    // Las coordenadas del campo salen de PHP, igual que en mazos.php: con ocho
    // formaciones, tenerlas en CSS serían 88 reglas que mantener a mano.
    $coordsPorFormacion[$clave] = array_values(Tcg::coordenadasDe($clave));
}

// Catálogos para crear una carta exclusiva de cadena sin salir del editor.
// Para el requisito "completar otra cadena" de un nodo de bloqueo (`045`).
// La actual se filtra en la pantalla: exigirse a sí misma no se abriría nunca
// (el servidor también lo rechaza, en crearRequisitoNodo()).
$cadenasTodas = $db->listarCadenasAdmin();

$equipos     = $db->listarEquipos();
$expansiones = $db->listarExpansiones();
$rarezasCat  = $db->listarRarezas();
$afinidades  = $db->listarAfinidades();

$datosEditor = [
    'idCadena' => $id_cadena,
    'nodos' => $nodos,
    'aristas' => $aristas,
    'rivales' => $rivales,
    'cromos' => $cromos,
    'formaciones' => Tcg::FORMACIONES,
    'huecosPorFormacion' => $huecosPorFormacion,
    'coordsPorFormacion' => $coordsPorFormacion,
    'dificultades' => Tcg::DIFICULTADES,
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
      <div class="row-actions" style="gap:var(--e-3);">
        <?php /* Calibrar la cadena ENTERA de una vez. Era la petición: sin esto
                 hay que abrir nodo por nodo y escribir cinco multiplicadores en
                 cada uno, y encima a ojo. */ ?>
        <button type="button" class="btn btn-ghost" data-abrir-modal="modalCalibrar">
          <i class="ph ph-gauge" aria-hidden="true"></i> Calibrar dificultad
        </button>
        <button type="button" class="btn btn-ghost" onclick="SRF.cadenaEditor.nuevoNodo('partido')">
          <i class="ph ph-flag" aria-hidden="true"></i> Nuevo partido
        </button>
        <button type="button" class="btn btn-ghost" onclick="SRF.cadenaEditor.nuevoNodo('cofre')">
          <i class="ph ph-gift" aria-hidden="true"></i> Nuevo cofre
        </button>
        <?php /* LA CASILLA DE SALIDA (migración `044`).
                 Antes el comienzo era implícito: arrancaba abierto cualquier
                 nodo sin aristas de entrada, así que un mapa ramificado abría
                 de golpe todas sus puntas sueltas. Con una salida puesta, solo
                 es accesible lo que cuelgue de ella. Una por cadena. */ ?>
        <button type="button" class="btn btn-ghost" onclick="SRF.cadenaEditor.nuevoNodo('inicio')">
          <i class="ph ph-play-circle" aria-hidden="true"></i> Casilla de salida
        </button>
        <?php /* EL STOP DEL MAPA (migración `045`).
                 No se juega ni se reclama: corta el paso hasta que el jugador
                 cumple lo que pide, y entonces se abre solo. Puestos varios en
                 una cadena, cada uno cierra su rama. */ ?>
        <button type="button" class="btn btn-ghost" onclick="SRF.cadenaEditor.nuevoNodo('bloqueo')">
          <i class="ph ph-prohibit" aria-hidden="true"></i> Nuevo bloqueo
        </button>
      </div>
    </div>

    <p class="t-caption t-dim" style="margin:0 0 var(--e-2);">
      Arrastra los nodos donde quieras: se pegan a una rejilla de 10&nbsp;px, y
      con <kbd>Shift</kbd> pulsado se colocan al píxel. Arrastra desde la anilla
      de un nodo hasta otro para conectarlos.
    </p>

    <div class="cadena-editor-lienzo-wrap">
      <div class="cadena-editor-lienzo" id="cadenaLienzo">
        <svg class="cadena-editor-svg" id="cadenaSvg"></svg>
        <!-- Los nodos los pinta JS: son demasiado dinámicos (arrastre, alta, baja) para tenerlos como marcado fijo. -->
      </div>
    </div>
  </main>
</div>

<!-- ===========================================================================
     MODAL DE NODO — reconstruido en PESTAÑAS.

     Antes era un único formulario en vertical con TODO seguido: tipo de nodo,
     rival, escudo del rival, alta de rival, estilo, alta de estilo, once
     huecos de alineación, tabla de dificultad y botín. Dos metros de scroll
     donde no se sabía dónde empezaba una cosa y acababa la otra, y con dos
     formularios de rival y dos de estilo compitiendo por la misma pantalla.

     Ahora son cinco pasos, en el orden en que se piensa un nodo:
       Nodo → Rival → Equipo → Dificultad → Botín

     Y cada cosa tiene UN formulario, no dos: el mismo bloque de rival sirve
     para crear uno nuevo y para editar el que ya está elegido; el mismo bloque
     de estilo, igual. Es literalmente lo que se repetía.

     Un cofre no se juega contra nadie, así que sus tres pestañas de partido se
     desactivan en vez de esconderse: desaparecer sin explicación se lee como
     un fallo, y una pestaña apagada con su motivo escrito no.
     =========================================================================== -->
<div class="modal modal--ancha" id="modalNodo" role="dialog" aria-modal="true" aria-labelledby="modalNodoTitulo" aria-hidden="true">
  <div class="modal-caja modal-caja--ancha editor-nodo">
    <div class="modal-head">
      <div>
        <h2 id="modalNodoTitulo">Nodo</h2>
        <p class="t-body-sm t-dim" id="fn_resumen"></p>
      </div>
      <button type="button" class="modal-cerrar" onclick="SRF.cadenaEditor.cerrarModalNodo()" aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <div class="editor-pestanas" role="tablist" aria-label="Partes del nodo">
      <button type="button" role="tab" class="editor-pestana" id="tab-nodo"
              aria-selected="true" aria-controls="pn-nodo" data-panel="pn-nodo">
        <i class="ph ph-map-pin" aria-hidden="true"></i> Nodo
      </button>
      <button type="button" role="tab" class="editor-pestana" id="tab-rival"
              aria-selected="false" aria-controls="pn-rival" data-panel="pn-rival" data-solo-partido>
        <i class="ph ph-shield" aria-hidden="true"></i> Rival
      </button>
      <button type="button" role="tab" class="editor-pestana" id="tab-equipo"
              aria-selected="false" aria-controls="pn-equipo" data-panel="pn-equipo" data-solo-partido>
        <i class="ph ph-users-three" aria-hidden="true"></i> Equipo
      </button>
      <button type="button" role="tab" class="editor-pestana" id="tab-dificultad"
              aria-selected="false" aria-controls="pn-dificultad" data-panel="pn-dificultad" data-solo-partido>
        <i class="ph ph-gauge" aria-hidden="true"></i> Dificultad
      </button>
      <button type="button" role="tab" class="editor-pestana" id="tab-botin"
              aria-selected="false" aria-controls="pn-botin" data-panel="pn-botin">
        <i class="ph ph-gift" aria-hidden="true"></i> Botín
      </button>
      <?php /* Solo para nodos de BLOQUEO (`045`): es lo único que configuran. */ ?>
      <button type="button" role="tab" class="editor-pestana" id="tab-requisitos"
              aria-selected="false" aria-controls="pn-requisitos" data-panel="pn-requisitos" data-solo-bloqueo>
        <i class="ph ph-lock-key" aria-hidden="true"></i> Requisitos
      </button>
    </div>

    <!-- ================= 1. NODO ================= -->
    <div class="editor-panel" id="pn-nodo" role="tabpanel" aria-labelledby="tab-nodo">
      <div class="form-grid">
        <div class="campo">
          <label for="fn_tipo">Tipo de nodo</label>
          <?php /* ⚠️ TIENEN QUE ESTAR LOS CUATRO TIPOS QUE EXISTEN.
                   Faltaban `inicio` (`044`) y `bloqueo` (`045`), y el efecto no
                   era que no se pudieran elegir: `abrirModalNodo()` hace
                   `fn_tipo.value = n.tipo`, y asignar a un <select> un valor que
                   no tiene ninguna <option> NO falla — deja el select en la
                   primera opción. Así que al abrir una casilla de salida o un
                   bloqueo, el desplegable decía "Partido", y darle a Guardar
                   nodo lo convertía de verdad en un partido, borrando el nodo
                   especial sin avisar. */ ?>
          <select id="fn_tipo" onchange="SRF.cadenaEditor.alternarTipoNodo()">
            <option value="partido">Partido — se juega contra un rival</option>
            <option value="cofre">Cofre — solo entrega botín</option>
            <option value="inicio">Casilla de salida — por aquí se empieza</option>
            <option value="bloqueo">Bloqueo — corta el paso hasta cumplir requisitos</option>
          </select>
        </div>

        <div class="campo">
          <label for="fn_nombre">Nombre (opcional)</label>
          <input type="text" id="fn_nombre" placeholder="Se muestra en el mapa">
        </div>

        <div class="campo campo-full">
          <div class="fila-interruptor">
            <span>
              Es el nodo final
              <span class="campo-hint">El cofre que cierra la cadena. Reclamarlo la marca como completada.</span>
            </span>
            <label class="interruptor">
              <input type="checkbox" id="fn_es_final">
              <span class="interruptor-riel"></span>
            </label>
          </div>
        </div>
      </div>

      <div class="editor-zona-peligro">
        <div>
          <b>Eliminar este nodo</b>
          <p class="t-caption t-dim">Se llevará también sus conexiones, su botín y el progreso real de los jugadores en él.</p>
        </div>
        <button type="button" class="btn btn-ghost es-peligro" onclick="SRF.cadenaEditor.borrarNodo()">
          <i class="ph ph-trash" aria-hidden="true"></i> Eliminar
        </button>
      </div>
    </div>

    <!-- ================= 2. RIVAL =================
         UN solo formulario. El desplegable elige a quién se enfrenta el nodo;
         los campos de debajo son los datos de ESE rival y se guardan sobre él.
         Eligiendo «Crear uno nuevo» los mismos campos salen en blanco y el
         botón pasa a crear. Antes esto eran tres bloques distintos —el
         desplegable, «Escudo de este rival» y «Nuevo rival»— con el campo de
         escudo duplicado en dos de ellos. -->
    <div class="editor-panel" id="pn-rival" role="tabpanel" aria-labelledby="tab-rival" hidden>
      <div class="campo">
        <label for="fn_rival">Contra quién se juega</label>
        <select id="fn_rival" onchange="SRF.cadenaEditor.cambiarRival()">
          <option value="">— Sin rival asignado —</option>
          <option value="nuevo">＋ Crear un rival nuevo…</option>
        </select>
      </div>

      <div class="editor-ficha-rival" id="fn_rival_ficha" hidden>
        <div class="form-grid">
          <div class="campo">
            <label for="fn_rival_nombre">Nombre</label>
            <input type="text" id="fn_rival_nombre" placeholder="Ej. Escuadra Fantasma">
          </div>
          <div class="campo">
            <label for="fn_rival_descripcion">Descripción (opcional)</label>
            <input type="text" id="fn_rival_descripcion">
          </div>

          <div class="campo campo-full">
            <label for="fn_rival_escudo_archivo">Escudo</label>
            <div class="editor-escudo">
              <span class="editor-escudo-vista" id="fn_rival_escudo_vista" aria-hidden="true">
                <i class="ph ph-sword"></i>
              </span>
              <div class="editor-escudo-campos">
                <input type="file" id="fn_rival_escudo_archivo" accept="image/png,image/jpeg,image/webp">
                <input type="text" id="fn_rival_escudo" placeholder="…o pega una ruta ya subida: ./assets/img/Escudos/…">
                <span class="campo-hint">Sin escudo, el mapa sigue enseñando el icono de espada.</span>
              </div>
            </div>
          </div>
        </div>

        <div class="editor-panel-pie">
          <button type="button" class="btn btn-primary" id="fn_rival_guardar"
                  onclick="SRF.cadenaEditor.guardarRival()">Guardar rival</button>
        </div>
      </div>
    </div>

    <!-- ================= 3. EQUIPO =================
         El estilo es la alineación con la que sale el rival. Mismo criterio
         que arriba: un desplegable con «Crear uno nuevo» y un solo formulario.

         El campo sustituyó hace tiempo a once `<select>` con el catálogo
         repetido once veces. Se coloca arrastrando la carta al hueco O
         pulsando el hueco y luego la carta: lo segundo no es un extra, es lo
         que hace que esto se pueda usar con teclado, y la regla del proyecto
         es que nada dependa de un gesto de arrastre. -->
    <div class="editor-panel" id="pn-equipo" role="tabpanel" aria-labelledby="tab-equipo" hidden>
      <div class="editor-aviso" id="fn_equipo_sin_rival">
        <i class="ph ph-info" aria-hidden="true"></i>
        <span>Elige primero un rival en la pestaña <b>Rival</b>: la alineación es suya, no del nodo.</span>
      </div>

      <div id="fn_equipo_cuerpo" hidden>
        <div class="form-grid">
          <div class="campo">
            <label for="fn_estilo">Alineación del rival</label>
            <select id="fn_estilo" onchange="SRF.cadenaEditor.cambiarEstilo()">
              <option value="">— Elige una —</option>
              <option value="nuevo">＋ Crear una nueva…</option>
            </select>
          </div>
          <div class="campo" id="fn_estilo_nuevo_nombre_wrap" hidden>
            <label for="fn_estilo_nombre">Nombre</label>
            <input type="text" id="fn_estilo_nombre" placeholder="Ej. 4-4-2 ofensivo">
          </div>
          <div class="campo" id="fn_estilo_nuevo_formacion_wrap" hidden>
            <label for="fn_estilo_formacion">Formación</label>
            <select id="fn_estilo_formacion"></select>
          </div>
          <div class="campo campo-full" id="fn_estilo_crear_wrap" hidden>
            <button type="button" class="btn btn-primary" onclick="SRF.cadenaEditor.crearEstilo()">
              Crear alineación
            </button>
          </div>
          <?php /* Borrar la alineación elegida. Solo aparece con una elegida:
                   con "Crear una nueva…" seleccionada no hay nada que borrar. */ ?>
          <div class="campo campo-full" id="fn_estilo_borrar_wrap" hidden>
            <button type="button" class="btn btn-plano btn-sm" id="fn_estilo_borrar"
                    onclick="SRF.cadenaEditor.borrarEstilo()">
              <i class="ph ph-trash" aria-hidden="true"></i> Borrar esta alineación
            </button>
          </div>
        </div>

        <div class="editor-alineacion" id="fn_alineacion" hidden>
          <div class="editor-alineacion-campo">
            <div class="alineacion" id="fn_campo"></div>
            <p class="t-caption t-dim" id="fn_campo_ayuda">
              Arrastra un jugador desde la derecha, o pulsa un hueco y luego el jugador.
            </p>
          </div>

          <aside class="editor-alineacion-selector" aria-label="Cromos disponibles">
            <div class="mazo-filtros">
              <div class="campo">
                <label for="fn_c_buscar">Buscar</label>
                <input type="search" id="fn_c_buscar" placeholder="Nombre o equipo" autocomplete="off">
              </div>
              <div class="mazo-filtros-fila">
                <div class="campo">
                  <label for="fn_c_posicion">Posición</label>
                  <select id="fn_c_posicion" class="fn-c-filtro" data-campo="posicion">
                    <option value="">Todas</option>
                    <option value="POR">Portería</option>
                    <option value="DF">Defensa</option>
                    <option value="MC">Medio</option>
                    <option value="DC">Ataque</option>
                  </select>
                </div>
                <div class="campo">
                  <label for="fn_c_afinidad">Afinidad</label>
                  <select id="fn_c_afinidad" class="fn-c-filtro" data-campo="afinidad">
                    <option value="">Todas</option>
                    <?php foreach ($afinidades as $af): ?>
                      <?php if (strcasecmp($af['nombre'], 'No-afi') === 0) continue; ?>
                      <option value="<?= htmlspecialchars($af['nombre']) ?>"><?= htmlspecialchars($af['nombre']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="mazo-filtros-fila">
                <div class="campo">
                  <label for="fn_c_equipo">Equipo</label>
                  <select id="fn_c_equipo" class="fn-c-filtro" data-campo="equipo">
                    <option value="">Todos</option>
                    <?php foreach ($equipos as $eq): ?>
                      <option value="<?= htmlspecialchars($eq['nombre']) ?>"><?= htmlspecialchars($eq['nombre']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="campo">
                  <label for="fn_c_rareza">Rareza</label>
                  <select id="fn_c_rareza" class="fn-c-filtro" data-campo="rareza">
                    <option value="">Todas</option>
                    <?php foreach ($rarezasCat as $r): ?>
                      <option value="<?= (int) $r['id_rareza'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="mazo-filtros-pie">
                <span class="t-caption t-dim" id="fn_c_conteo" role="status" aria-live="polite"></span>
                <button type="button" class="btn btn-plano btn-sm"
                        onclick="SRF.cadenaEditor.nuevaCartaCadena()">
                  <i class="ph ph-plus" aria-hidden="true"></i> Carta exclusiva
                </button>
              </div>
            </div>

            <ul class="carta-lista editor-cromos-lista" id="fn_c_lista"></ul>
          </aside>
        </div>
      </div>
    </div>

    <!-- ================= 4. DIFICULTAD =================
         Lo que pidió Alejandro: que dentro de un mismo Extremo haya partidos
         más duros y más blandos. Cada fila pisa, SOLO en este nodo, el
         parámetro global de esa dificultad; en blanco = manda el global.
         La tabla que hay detrás (`cadena_nodo_dificultad`) ya existía en la
         base local sin migración ni código — la `029` la formaliza. -->
    <div class="editor-panel" id="pn-dificultad" role="tabpanel" aria-labelledby="tab-dificultad" hidden>
      <p class="t-body-sm t-dim">
        Cada fila ajusta <b>este nodo</b> sin tocar el resto del juego.
        Un campo <b>en blanco usa el valor general</b> de esa dificultad; un <span class="mono">0</span> escrito
        a mano sí lo pisa. Sube <span class="mono">Fuerza</span> para hacerlo más difícil.
      </p>

      <?php /* El mismo selector de preset que la cadena y que el ajuste global,
               aquí para UN nodo. Sirve para lo que un preset de cadena no puede:
               que el jefe final duela más que el resto de su cadena, o que un
               nodo de tutorial duela menos, sin tocar los demás.
               Rellena la tabla de abajo midiendo partidos de verdad, así que
               sigue siendo posible ajustar cualquier fila a mano después. */ ?>
      <div class="editor-calibracion-nodo">
        <div class="calibracion-presets" role="radiogroup" aria-label="Preset de dificultad de este nodo">
          <?php foreach (Tcg::ETIQUETAS_PRESET_PVE as $clave => $etiqueta): ?>
            <?php $obj = Tcg::PRESETS_PVE[$clave]; $pctN = fn($v) => rtrim(rtrim(number_format($v * 100, 1, ',', ''), '0'), ','); ?>
            <label class="calibracion-preset<?= $clave === 'normal' ? ' esta-elegido' : '' ?>">
              <input type="radio" name="nodo_preset" value="<?= $clave ?>" class="sr-only" <?= $clave === 'normal' ? 'checked' : '' ?>>
              <b><?= htmlspecialchars($etiqueta) ?></b>
              <span class="t-caption t-dim">
                Fácil <span class="mono"><?= $pctN($obj['facil']) ?>&nbsp;%</span> ·
                Extremo <span class="mono"><?= $pctN($obj['extremo']) ?>&nbsp;%</span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="calibracion-pie">
          <button type="button" class="btn btn-plano" id="btnCalibrarNodo">
            <i class="ph ph-gauge" aria-hidden="true"></i> Calibrar solo este nodo
          </button>
          <span class="t-caption t-dim" id="calibrarNodoEstado" role="status" aria-live="polite"></span>
        </div>
      </div>

      <?php /* APAGAR UNA DIFICULTAD EN TODA LA CADENA DE UNA VEZ.
               Se podía nodo a nodo desde la `029`, pero montar una cadena
               "solo Extremo" con veinte nodos eran veinte visitas. Esto
               escribe el mismo ajuste en todos los nodos de partido. */ ?>
      <div class="editor-bloque">
        <h3 class="t-h3">Dificultades de TODA la cadena</h3>
        <p class="t-caption t-dim">
          Apaga aquí las que no quieras y desaparecen de todos los partidos de
          esta cadena. Es como se monta una cadena de un solo nivel.
        </p>
        <div class="dificultades-cadena" id="fn_dif_cadena"></div>
      </div>

      <?php /* LAS TRAMPAS, PARA TODA LA CADENA DE GOLPE.

               Ya se podían poner nodo a nodo en la tabla de abajo (columnas
               «Sin malus» y «Compos libres»), pero solo así: dejar una cadena
               de veinte partidos en modo jefe final eran veinte modales por
               cinco dificultades, tocando dos desplegables en cada uno. En la
               práctica no se usaba, que es lo mismo que no existir.

               Las dos son reglas de equilibrio ENTRE PERSONAS: que nadie apile
               compos sin límite y que un mazo carísimo sin coherencia pague
               por ello. A un rival de cadena, que no compite por nada ni sube
               en ninguna clasificación, solo le impedían ser un jefe final. */ ?>
      <div class="editor-bloque">
        <h3 class="t-h3">Trampas del rival en TODA la cadena</h3>
        <p class="t-caption t-dim">
          <b>Sin malus</b>: el rival no paga el malus de coherencia, así que
          puede llevar once legendarias sin castigo.
          <b>Compos libres</b>: se salta el tope de bonus por línea y puede
          apilar compos sin techo.
          Se aplican a todos los partidos de la cadena; «general» devuelve cada
          uno a lo que diga la configuración global.
        </p>
        <div class="trampas-cadena" id="fn_trampas_cadena"></div>
      </div>

      <div class="tabla-wrap">
        <table class="tabla tabla-dificultad">
          <thead>
            <tr>
              <th scope="col">Dificultad</th>
              <th scope="col">Jugable</th>
              <th scope="col" title="Multiplica la fuerza del rival. Pisa pve_mult_&lt;dif&gt;">Fuerza</th>
              <th scope="col" title="Multiplica los bonos de compo del rival">Compos</th>
              <th scope="col" title="Sube la rareza de las cartas del rival, nunca la baja">+Rareza</th>
              <?php /* Las dos trampas del rival automático. Son reglas de
                       equilibrio ENTRE PERSONAS; a un jefe final solo le
                       impedían serlo. Ver migración `043`. */ ?>
              <th scope="col" title="El rival no paga malus de coherencia de rareza: puede llevar once legendarias sin castigo">Sin malus</th>
              <th scope="col" title="El rival se salta el tope de bonus por línea">Compos libres</th>
              <th scope="col" title="Rareza máxima que puede llevar EL JUGADOR. 0 = sin límite">Tope jug.</th>
              <th scope="col" title="Alineación forzada; vacío = una al azar del rival">Alineación</th>
              <th scope="col"><span class="sr-only">Acciones</span></th>
            </tr>
          </thead>
          <tbody id="fn_dificultad_cuerpo"></tbody>
        </table>
      </div>
    </div>

    <!-- ================= 5. BOTÍN ================= -->
    <div class="editor-panel" id="pn-botin" role="tabpanel" aria-labelledby="tab-botin" hidden>
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

        <div class="campo campo-full" id="fl_grupo_cromo" hidden>
          <label for="fl_buscar_cromo">Cromo</label>
          <!-- Selector visual, no un <select>: con el catálogo entero (varios
               cientos de cromos) una lista desplegable se vuelve interminable
               e irreconocible — mismo problema y misma solución que ya se
               aplicó en mercado.php (§3 del CLAUDE.md, "Elige la carta"). -->
          <input type="search" id="fl_buscar_cromo" placeholder="Buscar por nombre, equipo o rareza"
                 autocomplete="off" aria-describedby="fl_conteo_cromo">
          <span class="campo-hint" id="fl_conteo_cromo" role="status" aria-live="polite">
            <?= count($cromos) ?> cromos
          </span>
          <input type="hidden" id="fl_cromo" required>

          <div class="selector-cartas" id="fl_lista_cromos" role="radiogroup" aria-label="Cromos disponibles para el botín">
            <?php foreach ($cromosParaSelector as $c): ?>
              <label class="selector-item"
                     data-nombre="<?= htmlspecialchars($c['nombre']) ?>"
                     data-equipo="<?= htmlspecialchars($c['equipo']) ?>"
                     data-rareza-nombre="<?= htmlspecialchars($c['rareza']) ?>">
                <input type="radio" name="fl_cromo_radio" class="sr-only" value="<?= $c['id_cromo'] ?>">
                <?php render_carta($c, ['tamano' => 'sm']); ?>
              </label>
            <?php endforeach; ?>
            <p class="selector-vacio" hidden>Ningún cromo coincide con esa búsqueda.</p>
          </div>
        </div>

        <div class="campo campo-full">
          <button type="button" class="btn btn-plano" onclick="SRF.cadenaEditor.crearLoot()">Añadir botín</button>
        </div>
      </div>
    </div>

    <!-- ================= 6. REQUISITOS (nodo de BLOQUEO, `045`) =================
         El STOP no se juega: se abre solo en cuanto se cumple TODO lo que pide.
         Sin requisitos deja pasar, que es lo que hace que ponerlo en una cadena
         publicada no la corte por accidente mientras se configura. -->
    <div class="editor-panel" id="pn-requisitos" role="tabpanel" aria-labelledby="tab-requisitos" hidden>
      <p class="t-body-sm t-dim">
        El jugador no pasa de aquí hasta cumplirlos <b>todos</b>. Se comprueban
        cada vez que mira el mapa, así que en cuanto cumpla el último, el
        bloqueo se abre solo. <b>Sin ningún requisito, deja pasar.</b>
      </p>

      <div id="fn_req_lista"></div>

      <div class="form-grid">
        <div class="campo campo-full">
          <label for="fr_tipo">Qué se exige</label>
          <select id="fr_tipo" onchange="SRF.cadenaEditor.alternarTipoRequisito()">
            <?php foreach (Tcg::REQUISITOS_NODO as $clave => $etiqueta): ?>
              <option value="<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($etiqueta) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php /* Cada tipo lee SU campo, igual que en panel/cadenas.php: un
                 único `valor` compartido por once desplegables significaba que
                 el navegador mandaba los once y el servidor cogía el que
                 tocase por casualidad. */ ?>
        <div class="campo" id="fr_grupo_rango">
          <label for="fr_rango">Rango mínimo</label>
          <select id="fr_rango">
            <option value="1">S</option>
            <option value="2">A o mejor</option>
            <option value="3">B o mejor (basta con ganar)</option>
          </select>
        </div>

        <div class="campo" id="fr_grupo_numero">
          <label for="fr_numero" id="fr_numero_label">Cuántos</label>
          <input type="number" id="fr_numero" min="1" value="3">
        </div>

        <div class="campo" id="fr_grupo_cadena" hidden>
          <label for="fr_cadena">Cadena que hay que completar</label>
          <select id="fr_cadena">
            <?php foreach ($cadenasTodas as $c): if ((int) $c['id_cadena'] === (int) $id_cadena) continue; ?>
              <option value="<?= (int) $c['id_cadena'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo" id="fr_grupo_rareza" hidden>
          <label for="fr_rareza">Rareza</label>
          <select id="fr_rareza">
            <?php foreach ($rarezasCat as $r): ?>
              <option value="<?= (int) $r['id_rareza'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo" id="fr_grupo_cromo" hidden>
          <label for="fr_cromo">Id del cromo que hay que tener</label>
          <input type="number" id="fr_cromo" min="1" placeholder="p. ej. 128">
        </div>

        <?php /* ⚠️ La dificultad importa más de lo que parece: las cinco están
                 siempre abiertas y el rango se guarda por dificultad, así que
                 sin fijarla se puede granjear la S entera en Fácil y abrir un
                 STOP pensado para Extremo. */ ?>
        <div class="campo" id="fr_grupo_dificultad">
          <label for="fr_dificultad">Solo cuenta en</label>
          <select id="fr_dificultad">
            <option value="">Cualquier dificultad</option>
            <?php foreach (Tcg::ETIQUETAS_DIFICULTAD as $clave => $etiqueta): ?>
              <option value="<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($etiqueta) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo campo-full">
          <button type="button" class="btn btn-plano" onclick="SRF.cadenaEditor.crearRequisito()">
            Añadir requisito
          </button>
        </div>
      </div>
    </div>

    <!-- El pie es fijo y vale para todas las pestañas: el nodo se guarda una
         vez, no una por pestaña. Rival, estilo, dificultad y botín se guardan
         solos con sus propios botones porque son entidades aparte —viven en
         sus propias tablas y los comparten otros nodos. -->
    <div class="modal-pie">
      <button type="button" class="btn btn-ghost" onclick="SRF.cadenaEditor.cerrarModalNodo()">Cerrar</button>
      <button type="button" class="btn btn-primary" onclick="SRF.cadenaEditor.guardarNodo()">Guardar nodo</button>
    </div>
  </div>
</div>

<!-- Carta EXCLUSIVA de cadena, creada sin salir del editor.
     No se puede conseguir jugando: no sale en sobres ni cuenta para el álbum
     (migración `030`). Existe para poblar los equipos de las cadenas con
     rivales que no desequilibren la colección. -->
<div class="modal" id="modalCromoCadena" role="dialog" aria-modal="true"
     aria-labelledby="cromoCadenaTitulo" aria-hidden="true">
  <div class="modal-caja modal-caja--ancha">
    <div class="modal-head">
      <div>
        <h2 id="cromoCadenaTitulo">Carta exclusiva de cadena</h2>
        <p class="t-body-sm t-dim">No saldrá en sobres ni contará para el álbum.</p>
      </div>
      <button type="button" class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <div class="form-grid">
      <div class="campo">
        <label for="fc_nombre">Nombre</label>
        <input type="text" id="fc_nombre" maxlength="100" placeholder="Ej. Guardián del Umbral">
      </div>
      <div class="campo">
        <label for="fc_posicion">Posición</label>
        <select id="fc_posicion">
          <option value="POR">Portero</option>
          <option value="DF">Defensa</option>
          <option value="MC" selected>Centrocampista</option>
          <option value="DC">Delantero</option>
        </select>
      </div>
      <div class="campo">
        <label for="fc_rareza">Rareza</label>
        <select id="fc_rareza">
          <?php foreach ($rarezasCat as $r): ?>
            <option value="<?= (int) $r['id_rareza'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php /* Mismo desvío de alta que en el panel de cromos: si el equipo no
               existe todavía, se crea aquí sin abandonar la carta a medias. */ ?>
      <div class="campo">
        <label for="fc_equipo">Equipo</label>
        <select id="fc_equipo" onchange="SRF.equipos.alternarAlta('fc')">
          <?php foreach ($equipos as $eq): ?>
            <option value="<?= (int) $eq['id_equipo'] ?>"><?= htmlspecialchars($eq['nombre']) ?></option>
          <?php endforeach; ?>
          <option value="nuevo">&#65291; Crear un equipo nuevo&hellip;</option>
        </select>
      </div>

      <div class="campo campo-full equipo-alta" id="fc_equipo_alta" hidden>
        <label for="fc_equipo_nombre">Nombre del equipo nuevo</label>
        <div class="equipo-alta-fila">
          <input type="text" id="fc_equipo_nombre" maxlength="100" placeholder="Ej. Escuadra Fantasma">
          <select id="fc_equipo_universo" aria-label="Universo del equipo nuevo">
            <?php foreach (Tcg::UNIVERSOS as $cl => $nom): ?>
              <option value="<?= $cl ?>"><?= htmlspecialchars($nom) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-plano" onclick="SRF.equipos.crear('fc')">Crear</button>
        </div>
        <span class="campo-hint" id="fc_equipo_aviso" role="status" aria-live="polite"></span>
      </div>
      <div class="campo">
        <label for="fc_expansion">Expansión</label>
        <select id="fc_expansion">
          <?php foreach ($expansiones as $ex): ?>
            <option value="<?= (int) $ex['id_expansion'] ?>"><?= htmlspecialchars($ex['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="fc_afinidad">Afinidad</label>
        <select id="fc_afinidad">
          <?php foreach ($afinidades as $af): ?>
            <option value="<?= (int) $af['id'] ?>"><?= htmlspecialchars($af['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="campo campo-full">
        <div class="fila-interruptor">
          <span>
            Estadísticas aleatorias según rareza y posición
            <span class="campo-hint">Usa los rangos reales de <span class="mono">Rangos_estadisticas_SRF.csv</span>.</span>
          </span>
          <label class="interruptor">
            <input type="checkbox" id="fc_aleatorias" checked onchange="SRF.cadenaEditor.alternarStatsCarta()">
            <span class="interruptor-riel"></span>
          </label>
        </div>
      </div>

      <div class="campo" data-stats-manual hidden>
        <label for="fc_ataque">Ataque</label>
        <input type="number" id="fc_ataque" min="0" max="99" value="0">
      </div>
      <div class="campo" data-stats-manual hidden>
        <label for="fc_defensa">Defensa</label>
        <input type="number" id="fc_defensa" min="0" max="99" value="0">
      </div>
      <div class="campo" data-stats-manual hidden>
        <label for="fc_tecnica">Técnica</label>
        <input type="number" id="fc_tecnica" min="0" max="99" value="0">
      </div>

      <div class="campo campo-full">
        <label for="fc_arte">Imagen (opcional)</label>
        <input type="file" id="fc_arte" accept="image/png,image/jpeg,image/webp">
      </div>

      <?php /* QUÉ ES LA IMAGEN QUE SE SUBE, que no es lo mismo en los dos casos:
               · una FOTO de jugador se monta sobre la plantilla de su rareza —el
                 marco—, que es lo que da el aspecto de cromo de álbum. Para eso
                 tiene que ir a `Cromos/Importados/`, que es la carpeta por la que
                 pregunta `carta_usa_marco()`.
               · un ARTWORK completo ya trae su propio fondo y ocupa la carta
                 entera: ponerle un marco encima taparía la ilustración.
               Antes esto no se preguntaba y todo iba a `Cromos/Cadenas/`, así que
               las fotos de jugador salían sueltas sobre el fondo, sin marco. */ ?>
      <div class="campo campo-full">
        <label for="fc_tipo_imagen">Qué estás subiendo</label>
        <select id="fc_tipo_imagen" onchange="SRF.cadenaEditor.explicarTipoImagen()">
          <option value="artwork">Artwork completo — ocupa la carta entera, sin marco</option>
          <option value="foto">Foto del jugador — se monta sobre el marco de su rareza</option>
        </select>
        <span class="campo-hint" id="fc_tipo_imagen_hint"></span>
      </div>
    </div>

    <div class="modal-pie">
      <button type="button" class="btn btn-ghost" data-cerrar-modal>Cancelar</button>
      <button type="button" class="btn btn-primary" onclick="SRF.cadenaEditor.crearCartaCadena()">Crear carta</button>
    </div>
  </div>
</div>

<!-- Calibrar la dificultad de TODA la cadena.
     Cada nodo se mide contra el mismo jugador de referencia, así que todos los
     Extremos de la cadena acaban pidiendo lo mismo aunque sus alineaciones
     rivales sean muy distintas entre sí — que es justo lo que un multiplicador
     común no consigue. -->
<div class="modal" id="modalCalibrar" role="dialog" aria-modal="true"
     aria-labelledby="calibrarTitulo" aria-hidden="true">
  <div class="modal-caja">
    <div class="modal-head">
      <div>
        <h2 id="calibrarTitulo">Calibrar dificultad de la cadena</h2>
        <p class="t-body-sm t-dim">
          Se ajusta cada nodo por separado para que todos pidan lo mismo en cada nivel.
          <?php if (!empty($cadena['pve_preset'])): ?>
            Ahora está en <b><?= htmlspecialchars(Tcg::ETIQUETAS_PRESET_PVE[$cadena['pve_preset']] ?? $cadena['pve_preset']) ?></b>.
          <?php else: ?>
            Todavía no se ha calibrado nunca.
          <?php endif; ?>
        </p>
      </div>
      <button type="button" class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>

    <div class="calibracion-presets" role="radiogroup" aria-label="Preset de dificultad">
      <?php foreach (Tcg::ETIQUETAS_PRESET_PVE as $clave => $etiqueta): ?>
        <?php
        $obj = Tcg::PRESETS_PVE[$clave];
        $elegido = ($cadena['pve_preset'] ?? 'normal') === $clave;
        $pct = fn($v) => rtrim(rtrim(number_format($v * 100, 1, ',', ''), '0'), ',');
        ?>
        <label class="calibracion-preset<?= $elegido ? ' esta-elegido' : '' ?>">
          <input type="radio" name="cal_preset" value="<?= $clave ?>" class="sr-only" <?= $elegido ? 'checked' : '' ?>>
          <b><?= htmlspecialchars($etiqueta) ?></b>
          <span class="t-caption t-dim">
            Fácil <span class="mono"><?= $pct($obj['facil']) ?>&nbsp;%</span> ·
            Extremo <span class="mono"><?= $pct($obj['extremo']) ?>&nbsp;%</span>
          </span>
        </label>
      <?php endforeach; ?>
    </div>

    <p class="alerta alerta-warning" role="status" style="margin-top:var(--e-4);">
      <i class="ph ph-warning" aria-hidden="true"></i>
      <span>
        Pisa el multiplicador de fuerza de <b>todos</b> los nodos de partido de esta cadena.
        El resto de sus ajustes (compos, subida de rareza, tope del jugador, alineación forzada)
        se quedan como están.
      </span>
    </p>

    <p class="t-caption t-dim" id="calibrarEstado" role="status" aria-live="polite"></p>
    <div id="calibrarResultado"></div>

    <div class="modal-pie">
      <button type="button" class="btn btn-ghost" data-cerrar-modal>Cancelar</button>
      <button type="button" class="btn btn-primary" id="btnCalibrarCadena">Calibrar la cadena</button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/confirmar.php'; ?>

<?php $pieCompleto = false; include __DIR__ . '/../partials/footer.php'; ?>
<script>window.CADENA_EDITOR_DATOS = <?= json_encode($datosEditor) ?>;</script>
<script src="<?= htmlspecialchars(assetUrl($base, 'panel/assets/js/scriptEquiposAlta.js')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl($base, 'panel/assets/js/scriptCadenaEditor.js')) ?>"></script>
</body>
</html>
