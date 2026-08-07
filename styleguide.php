<?php
/**
 * GUÍA DE ESTILO — Fase 0.
 *
 * Página de referencia del sistema de diseño: aquí se construye y se valida
 * cada componente en aislamiento antes de que ninguna pantalla lo use.
 * No forma parte del producto para usuarios; es documentación viva.
 *
 * Si hay base de datos disponible, usa cartas reales (una por rareza) para
 * comprobar que el marco absorbe arte de proporciones distintas. Si no la hay,
 * cae en datos de muestra para que la guía siga siendo utilizable.
 */

require_once __DIR__ . '/components/carta.php';

$rarezas = [
    1 => ['nombre' => 'Común',      'prob' => '60 %',  'marca' => 'Sin adorno'],
    2 => ['nombre' => 'Poco común', 'prob' => '25 %',  'marca' => '1 chevrón'],
    3 => ['nombre' => 'Raro',       'prob' => '10 %',  'marca' => '2 chevrones'],
    4 => ['nombre' => 'Épico',      'prob' => '3,5 %', 'marca' => '3 chevrones'],
    5 => ['nombre' => 'Legendario', 'prob' => '1 %',   'marca' => 'Corona + borde metálico'],
    6 => ['nombre' => 'SRF',        'prob' => '0,5 %', 'marca' => 'Destello holográfico animado'],
];

// Una carta real por rareza, si la base de datos responde.
$muestras = [];
try {
    require_once __DIR__ . '/db/conexion.php';
    foreach ($db->listarColeccionCompleta() as $expansion) {
        foreach ($expansion['cromos'] as $cromo) {
            $id = (int) $cromo['id_rareza'];
            if (!isset($muestras[$id])) { $muestras[$id] = $cromo; }
        }
    }
} catch (Throwable $e) {
    $muestras = [];
}

// Relleno de muestra para las rarezas que no tengan carta real.
foreach ($rarezas as $id => $meta) {
    if (!isset($muestras[$id])) {
        $muestras[$id] = [
            'nombre'          => 'Carta de muestra',
            'imagen'          => '',
            'posicion'        => 'MC',
            'equipo'          => 'Sin equipo',
            'id_rareza'       => $id,
            'rareza'          => $meta['nombre'],
            'afinidad'        => 'No-afi',
            'afinidad_imagen' => '',
        ];
    }
}

$ejemplo = $muestras[6];

$paginaTitulo = 'Guía de estilo';
$paginaDesc   = 'Sistema de diseño de Superliga Frontier TCG: tokens y componentes.';
$cssExtra     = ['assets/css/styleguide.css'];
include __DIR__ . '/partials/head.php';
?>

<header class="cabecera">
  <div class="linea-campo" aria-hidden="true"></div>
  <div class="wrap cabecera-contenido">
    <span class="seccion-tag">Fase 0 · Fundamentos</span>
    <h1>Guía de estilo</h1>
    <p>
      Referencia del sistema de diseño. Todo lo que se construya a partir de
      aquí sale de estos tokens y de estos componentes, sin variantes propias
      por pantalla.
    </p>
  </div>
</header>

<main id="contenido" class="wrap">

  <!-- ===================================================================
       COLOR
       =================================================================== -->
  <section class="seccion" id="color">
    <div class="seccion-head">
      <div><span class="seccion-tag">01</span><h2>Color</h2></div>
    </div>

    <h3 class="sg-sub">Base</h3>
    <div class="sg-swatches">
      <?php
      $base_colores = [
        ['--void', '#0B0C10', 'Fondo principal'],
        ['--void-2', '#101218', 'Fondo hundido'],
        ['--panel', '#16181D', 'Superficie elevada'],
        ['--panel-2', '#1D2027', 'Superficie sobre superficie'],
        ['--frost', '#EDEEF1', 'Texto principal'],
        ['--frost-dim', '#93959F', 'Texto secundario'],
      ];
      foreach ($base_colores as [$token, $hex, $uso]): ?>
        <div class="sg-swatch">
          <span class="sg-muestra" style="background: var(<?= $token ?>);"></span>
          <b class="mono"><?= $token ?></b>
          <span class="mono t-caption-sm t-dim"><?= $hex ?></span>
          <span class="t-caption-sm t-dim"><?= $uso ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <h3 class="sg-sub">Acento y semánticos</h3>
    <div class="sg-swatches">
      <?php
      $acentos = [
        ['--amber', '#E8752A', 'Acciones primarias'],
        ['--amber-light', '#FFB168', 'Hover y resplandores'],
        ['--success', '#3DDC9B', 'Confirmación, victoria'],
        ['--warning', '#F2B134', 'Aviso (tarjeta amarilla)'],
        ['--danger', '#F0554A', 'Error, derrota (tarjeta roja)'],
        ['--info', '#5B96F2', 'Mensajes neutros'],
      ];
      foreach ($acentos as [$token, $hex, $uso]): ?>
        <div class="sg-swatch">
          <span class="sg-muestra" style="background: var(<?= $token ?>);"></span>
          <b class="mono"><?= $token ?></b>
          <span class="mono t-caption-sm t-dim"><?= $hex ?></span>
          <span class="t-caption-sm t-dim"><?= $uso ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ===================================================================
       TIPOGRAFÍA
       =================================================================== -->
  <section class="seccion" id="tipografia">
    <div class="seccion-head">
      <div><span class="seccion-tag">02</span><h2>Tipografía</h2></div>
      <p class="t-caption t-dim">Geist Sans · Geist Mono</p>
    </div>

    <div class="panel stack stack-5">
      <div>
        <span class="t-caption t-dim">Display · 56–96 / 700 · solo hero y revelado SRF</span>
        <p class="t-display">Superliga</p>
      </div>
      <div>
        <span class="t-caption t-dim">H1 · 40–48 / 700</span>
        <h1>Títulos de sección principal</h1>
      </div>
      <div>
        <span class="t-caption t-dim">H2 · 28–32 / 700</span>
        <h2>Títulos de bloque</h2>
      </div>
      <div>
        <span class="t-caption t-dim">H3 · 20–22 / 600</span>
        <h3>Subtítulos y cabeceras de tarjeta</h3>
      </div>
      <div>
        <span class="t-caption t-dim">Body · 15–16 / 400–500</span>
        <p class="t-body">
          Texto de lectura. El tono por defecto es editorial y preciso: frases
          cortas, datos claros, sin relleno.
        </p>
      </div>
      <div>
        <span class="t-caption t-dim">Caption · 12–13 / 600 · mayúsculas, tracking +8 %</span>
        <p class="t-caption">Rareza · posición · marca de tiempo</p>
      </div>
      <div>
        <span class="t-caption t-dim">Mono · datos, nunca texto editorial</span>
        <p class="mono" style="font-size:20px;">1 240 monedas · 04/08/2026 · 12–4</p>
      </div>
    </div>
  </section>

  <!-- ===================================================================
       RAREZAS
       =================================================================== -->
  <section class="seccion" id="rarezas">
    <div class="seccion-head">
      <div>
        <span class="seccion-tag">03</span>
        <h2>Rarezas</h2>
      </div>
    </div>

    <div class="alerta alerta-info" style="margin-bottom:var(--space-5);">
      <i class="ph ph-eye" aria-hidden="true"></i>
      <span>
        Cada rareza lleva una marca no cromática además del color. Es requisito
        de accesibilidad y de equidad en los duelos con apuesta: si dos cartas
        deben ser de la misma rareza, esa rareza tiene que poder leerse sin
        distinguir colores.
      </span>
    </div>

    <div class="tabla-wrap" style="margin-bottom:var(--space-6);">
      <table class="tabla">
        <thead>
          <tr>
            <th scope="col">Nivel</th>
            <th scope="col">Etiqueta</th>
            <th scope="col">Probabilidad</th>
            <th scope="col">Marca redundante</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rarezas as $id => $meta): ?>
          <tr>
            <td class="mono"><?= $id ?></td>
            <td><?= render_rareza($id, $meta['nombre']) ?></td>
            <td class="mono"><?= $meta['prob'] ?></td>
            <td class="t-dim"><?= $meta['marca'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h3 class="sg-sub">Las seis rarezas en la carta</h3>
    <div class="carta-grid">
      <?php foreach ($rarezas as $id => $meta): ?>
        <?php render_carta($muestras[$id], ['href' => '#rarezas', 'lazy' => false]); ?>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ===================================================================
       TARJETA DE CARTA
       =================================================================== -->
  <section class="seccion" id="carta">
    <div class="seccion-head">
      <div><span class="seccion-tag">04</span><h2>Tarjeta de carta</h2></div>
    </div>

    <div class="alerta alerta-warning" style="margin-bottom:var(--space-5);">
      <i class="ph ph-frame-corners" aria-hidden="true"></i>
      <span>
        El arte se muestra siempre completo (<code class="mono">object-fit: contain</code>),
        nunca recortado, sobre una placa con halo del color de la rareza. Así el
        marco absorbe que las ilustraciones de origen no compartan proporción ni
        resolución: la coherencia de marca vive en el marco, no en el arte.
      </span>
    </div>

    <h3 class="sg-sub">Tamaños</h3>
    <div class="sg-fila-cartas">
      <div class="sg-caso">
        <span class="t-caption t-dim">sm · rejilla densa, revelado, deck builder</span>
        <div style="max-width:150px;">
          <?php render_carta($ejemplo, ['tamano' => 'sm']); ?>
        </div>
      </div>
      <div class="sg-caso">
        <span class="t-caption t-dim">md · por defecto</span>
        <div style="max-width:220px;">
          <?php render_carta($ejemplo); ?>
        </div>
      </div>
      <div class="sg-caso">
        <span class="t-caption t-dim">lg · detalle y sala de duelo</span>
        <div style="max-width:300px;">
          <?php render_carta($ejemplo, [
            'tamano' => 'lg',
            'stats'  => ['ATA' => 88, 'DEF' => 72, 'TÉC' => 91],
          ]); ?>
        </div>
      </div>
    </div>

    <h3 class="sg-sub">Estados</h3>
    <div class="carta-grid">
      <div class="sg-caso">
        <span class="t-caption t-dim">Por defecto</span>
        <?php render_carta($muestras[3]); ?>
      </div>
      <div class="sg-caso">
        <span class="t-caption t-dim">Interactiva (hover / foco)</span>
        <?php render_carta($muestras[4], ['href' => '#carta']); ?>
      </div>
      <div class="sg-caso">
        <span class="t-caption t-dim">Sin conseguir (álbum)</span>
        <?php render_carta($muestras[5], ['poseida' => false]); ?>
      </div>
      <div class="sg-caso">
        <span class="t-caption t-dim">Protegida de venta</span>
        <?php render_carta($muestras[2], ['protegida' => true]); ?>
      </div>
      <div class="sg-caso">
        <span class="t-caption t-dim">En venta (mercado)</span>
        <?php render_carta($muestras[3], ['precio' => 1250]); ?>
      </div>
      <div class="sg-caso">
        <span class="t-caption t-dim">Seleccionada (deck builder)</span>
        <?php render_carta($muestras[4], ['seleccionada' => true]); ?>
      </div>
      <div class="sg-caso">
        <span class="t-caption t-dim">Con estadísticas de combate</span>
        <?php render_carta($muestras[6], ['stats' => ['ATA' => 94, 'DEF' => 80, 'TÉC' => 97]]); ?>
      </div>
      <div class="sg-caso">
        <span class="t-caption t-dim">Modo "debajo" (arte sin recortar)</span>
        <?php render_carta(['mostrar_stats' => 'debajo'] + $muestras[3], ['stats' => ['ATA' => 88, 'DEF' => 72, 'TÉC' => 91]]); ?>
      </div>
      <div class="sg-caso">
        <span class="t-caption t-dim">Dorso (ceremonia de sobre)</span>
        <article class="carta" data-rareza="1">
          <div class="carta-dorso"><i class="ph ph-soccer-ball" aria-hidden="true"></i></div>
        </article>
      </div>
    </div>
  </section>

  <!-- ===================================================================
       CEREMONIA DE APERTURA
       =================================================================== -->
  <section class="seccion" id="ceremonia">
    <div class="seccion-head">
      <div><span class="seccion-tag">05</span><h2>Ceremonia de apertura</h2></div>
    </div>

    <div class="alerta alerta-info" style="margin-bottom:var(--space-5);">
      <i class="ph ph-timer" aria-hidden="true"></i>
      <span>
        Previsualización sin gastar monedas. La ceremonia real usa exactamente
        este mismo código: se sirve la carta ya renderizada desde el servidor,
        así que el marcado es el mismo que en colección, álbum y mercado. El
        ritmo escala con la rareza (de 260&nbsp;ms en común a 1,1&nbsp;s en SRF)
        y el volteo es saltable en cualquier momento.
      </span>
    </div>

    <div class="fila">
      <button class="btn btn-primary" data-ceremonia="normal">Simular sobre corriente</button>
      <button class="btn btn-ghost" data-ceremonia="legendario">Simular con legendaria</button>
      <button class="btn btn-ghost" data-ceremonia="srf">Simular con SRF</button>
    </div>
  </section>

  <!-- ===================================================================
       BOTONES Y CONTROLES
       =================================================================== -->
  <section class="seccion" id="controles">
    <div class="seccion-head">
      <div><span class="seccion-tag">06</span><h2>Botones y controles</h2></div>
    </div>

    <div class="panel stack stack-5">
      <div class="sg-caso">
        <span class="t-caption t-dim">Variantes</span>
        <div class="fila">
          <button class="btn btn-primary">Abrir sobre</button>
          <button class="btn btn-ghost">Ver colección</button>
          <button class="btn btn-danger">Retirar del mercado</button>
          <button class="btn btn-plano">Cancelar</button>
        </div>
      </div>

      <div class="sg-caso">
        <span class="t-caption t-dim">Tamaños</span>
        <div class="fila">
          <button class="btn btn-primary btn-sm">Pequeño</button>
          <button class="btn btn-primary">Estándar</button>
          <button class="btn btn-primary btn-lg">Grande</button>
        </div>
      </div>

      <div class="sg-caso">
        <span class="t-caption t-dim">Estados</span>
        <div class="fila">
          <button class="btn btn-primary" disabled>No disponible</button>
          <button class="btn btn-primary is-cargando">Procesando</button>
          <button class="btn btn-ghost"><i class="ph ph-funnel" aria-hidden="true"></i> Con icono</button>
        </div>
      </div>

      <div class="hr"></div>

      <div class="sg-form">
        <div class="campo">
          <label for="sg-texto">Buscar por nombre</label>
          <input type="text" id="sg-texto" placeholder="Ej. Mark Evans">
          <span class="campo-hint">Busca en el nombre de la carta, no en el equipo.</span>
        </div>

        <div class="campo">
          <label for="sg-select">Rareza</label>
          <select id="sg-select">
            <option>Todas las rarezas</option>
            <?php foreach ($rarezas as $meta): ?>
              <option><?= htmlspecialchars($meta['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo is-error">
          <label for="sg-error">Precio de venta</label>
          <input type="text" id="sg-error" value="0" aria-describedby="sg-error-msg">
          <span class="campo-error" id="sg-error-msg">
            <i class="ph ph-warning-circle" aria-hidden="true"></i>
            El precio debe ser de al menos 1 moneda.
          </span>
        </div>

        <div class="campo">
          <span class="campo-label">Filtros</span>
          <label class="casilla"><input type="checkbox" checked> Solo cartas repetidas</label>
          <label class="casilla"><input type="checkbox"> Solo protegidas</label>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================================================================
       RETROALIMENTACIÓN
       =================================================================== -->
  <section class="seccion" id="feedback">
    <div class="seccion-head">
      <div><span class="seccion-tag">07</span><h2>Retroalimentación</h2></div>
    </div>

    <div class="sg-dos">
      <div class="stack stack-3">
        <span class="t-caption t-dim">Alertas</span>
        <div class="alerta alerta-success"><i class="ph ph-check-circle" aria-hidden="true"></i><span>Carta publicada en el mercado por 1 250 monedas.</span></div>
        <div class="alerta alerta-warning"><i class="ph ph-warning" aria-hidden="true"></i><span>Te quedan 40 monedas. No alcanza para este sobre.</span></div>
        <div class="alerta alerta-danger"><i class="ph ph-x-circle" aria-hidden="true"></i><span>No se pudo completar la compra: el anuncio ya no está activo.</span></div>
        <div class="alerta alerta-info"><i class="ph ph-info" aria-hidden="true"></i><span>Las cartas protegidas no aparecen en el listado de venta.</span></div>
      </div>

      <div class="stack stack-5">
        <div class="stack stack-3">
          <span class="t-caption t-dim">Pastillas de estado</span>
          <div class="fila">
            <span class="pastilla pastilla-on">En venta</span>
            <span class="pastilla pastilla-warn">Pendiente</span>
            <span class="pastilla pastilla-off">Retirada</span>
            <span class="pastilla">Sin actividad</span>
          </div>
        </div>

        <div class="stack stack-3">
          <span class="t-caption t-dim">Progreso</span>
          <div class="progreso">
            <div class="progreso-riel"><div class="progreso-relleno" style="width:64%"></div></div>
            <span class="progreso-label">41 / 64</span>
          </div>
        </div>

        <div class="stack stack-3">
          <span class="t-caption t-dim">Avisos flotantes</span>
          <div class="fila">
            <button class="btn btn-ghost btn-sm" onclick="SRF.toast('Carta añadida a tu colección.','success')">Éxito</button>
            <button class="btn btn-ghost btn-sm" onclick="SRF.toast('No tienes monedas suficientes.','danger')">Error</button>
          </div>
        </div>

        <div class="stack stack-3">
          <span class="t-caption t-dim">Carga</span>
          <div class="stack stack-2">
            <div class="skeleton skeleton-texto" style="width:70%"></div>
            <div class="skeleton skeleton-texto" style="width:45%"></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================================================================
       DATOS Y NAVEGACIÓN INTERNA
       =================================================================== -->
  <section class="seccion" id="datos">
    <div class="seccion-head">
      <div><span class="seccion-tag">08</span><h2>Datos y navegación</h2></div>
    </div>

    <div class="stack stack-6">
      <div class="stack stack-3">
        <span class="t-caption t-dim">Cifras de panel · el dato siempre en mono</span>
        <div class="sg-datos">
          <div class="panel"><div class="dato"><b>1 240</b><span>Monedas</span></div></div>
          <div class="panel"><div class="dato"><b>41</b><span>Cartas distintas</span></div></div>
          <div class="panel"><div class="dato"><b>3</b><span>Anuncios activos</span></div></div>
          <div class="panel"><div class="dato"><b>12–4</b><span>Duelos ganados</span></div></div>
        </div>
      </div>

      <div class="stack stack-3">
        <span class="t-caption t-dim">Tabla · en móvil hace scroll horizontal, nunca comprime columnas</span>
        <div class="tabla-wrap">
          <table class="tabla">
            <thead>
              <tr>
                <th scope="col">Carta</th>
                <th scope="col">Rareza</th>
                <th scope="col">Vendedor</th>
                <th scope="col" class="num">Precio</th>
                <th scope="col">Estado</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Ejemplo de carta</td>
                <td><?= render_rareza(5, 'Legendario') ?></td>
                <td class="t-dim">Alejandro</td>
                <td class="num">4 500</td>
                <td><span class="pastilla pastilla-on">En venta</span></td>
              </tr>
              <tr>
                <td>Otro ejemplo</td>
                <td><?= render_rareza(3, 'Raro') ?></td>
                <td class="t-dim">Gonzalo</td>
                <td class="num">820</td>
                <td><span class="pastilla pastilla-off">Retirada</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="stack stack-3">
        <span class="t-caption t-dim">Chip de usuario</span>
        <div class="fila">
          <div class="chip-usuario">
            <span class="avatar">AS</span>
            <span class="monedas"><i class="ph ph-coins" aria-hidden="true"></i> 1 240</span>
          </div>
        </div>
      </div>

      <div class="stack stack-3">
        <span class="t-caption t-dim">Pestañas</span>
        <div class="tabs" role="tablist">
          <button class="tab" role="tab" aria-selected="true">Colección</button>
          <button class="tab" role="tab" aria-selected="false">Anuncios</button>
          <button class="tab" role="tab" aria-selected="false">Historial</button>
        </div>
      </div>

      <div class="stack stack-3">
        <span class="t-caption t-dim">Paginación</span>
        <div class="paginacion">
          <button class="pag-btn" aria-label="Página anterior"><i class="ph ph-caret-left" aria-hidden="true"></i></button>
          <button class="pag-btn" aria-current="page">1</button>
          <button class="pag-btn">2</button>
          <button class="pag-btn">3</button>
          <button class="pag-btn" aria-label="Página siguiente"><i class="ph ph-caret-right" aria-hidden="true"></i></button>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================================================================
       ESTADOS VACÍOS Y MODAL
       =================================================================== -->
  <section class="seccion" id="vacios">
    <div class="seccion-head">
      <div><span class="seccion-tag">09</span><h2>Estados vacíos y modal</h2></div>
    </div>

    <div class="sg-dos">
      <div class="vacio">
        <span class="vacio-ico"><i class="ph ph-cards" aria-hidden="true"></i></span>
        <h3>Todavía no tienes cartas</h3>
        <p>Abre tu primer sobre para empezar la colección. Cada sobre reparte cartas al azar según la probabilidad de cada rareza.</p>
        <a class="btn btn-primary" href="#vacios">Ir a sobres</a>
      </div>

      <div class="stack stack-4">
        <div class="vacio">
          <span class="vacio-ico"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></span>
          <h3>Ningún resultado con esos filtros</h3>
          <p>Prueba a quitar alguna rareza o a buscar por otro nombre.</p>
        </div>
        <button class="btn btn-ghost" data-abrir-modal="sg-modal">Abrir modal de ejemplo</button>
      </div>
    </div>
  </section>

  <div class="alerta alerta-info" style="margin-bottom:var(--space-8);">
    <i class="ph ph-list-checks" aria-hidden="true"></i>
    <span>
      Comprobaciones de esta guía: recorre la página entera con el tabulador y
      verifica que el foco es siempre visible; abre el modal y ciérralo con Esc;
      activa «reducir movimiento» en el sistema y confirma que el holográfico
      SRF se detiene.
    </span>
  </div>

</main>

<!-- Modal de ejemplo: foco atrapado y cierre con Esc los aporta assets/js/ui.js -->
<div class="modal" id="sg-modal" role="dialog" aria-modal="true" aria-labelledby="sg-modal-titulo" aria-hidden="true">
  <div class="modal-caja">
    <div class="modal-head">
      <h2 id="sg-modal-titulo">Confirmar apuesta</h2>
      <button class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">
        <i class="ph ph-x" aria-hidden="true"></i>
      </button>
    </div>
    <p class="t-body-sm t-dim">
      Vas a apostar 500 monedas en este duelo. Tu rival apuesta la misma
      cantidad. La apuesta se descuenta al aceptar y no se puede deshacer.
    </p>
    <div class="modal-pie">
      <button class="btn btn-ghost" data-cerrar-modal>Cancelar</button>
      <button class="btn btn-primary" data-cerrar-modal>Confirmar apuesta</button>
    </div>
  </div>
</div>

<?php $pieCompleto = true; include __DIR__ . '/partials/footer.php'; ?>
<?php include __DIR__ . '/partials/ceremonia.php'; ?>

<script>
/* Previsualización de la ceremonia: se sirven las cartas de muestra ya
   renderizadas por el componente de PHP, igual que hace sobres.php. */
(function () {
  var MUESTRAS = <?= json_encode(array_map(function ($id) use ($muestras) {
      return [
          'nombre'    => $muestras[$id]['nombre'],
          'rareza'    => $muestras[$id]['rareza'],
          'id_rareza' => $id,
          'html'      => carta_html($muestras[$id], ['tamano' => 'sm', 'lazy' => false]),
      ];
  }, array_keys($rarezas)), JSON_UNESCAPED_UNICODE) ?>;

  var porRareza = {};
  MUESTRAS.forEach(function (c) { porRareza[c.id_rareza] = c; });

  var SOBRES = {
    normal:     [1, 1, 2, 3, 4],
    legendario: [1, 2, 2, 3, 5],
    srf:        [1, 2, 3, 4, 6]
  };

  document.querySelectorAll('[data-ceremonia]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var cartas = SOBRES[btn.dataset.ceremonia].map(function (id) { return porRareza[id]; });
      SRF.ceremonia(cartas);
    });
  });
})();
</script>

</body>
</html>
