<?php
/**
 * PRESENTACIÓN DE ALINEACIONES — la intro previa al partido.
 *
 * Se pinta ENTRE la elección del aumento y el pitido inicial: el jugador acaba
 * de tomar dos decisiones que deciden el encuentro —su once y su aumento— y
 * hasta ahora pasaba del formulario al marcador sin que ninguna de las dos
 * pesara nada. Esto es el momento en el que pesan.
 *
 * ⚠️ AQUÍ NO SE DECIDE NADA. El servidor manda los dos lados ya resueltos
 * (`Tcg::datosPresentacionDuelo`): quién es local, si enfrente hay una persona
 * o la máquina, qué imagen toca. Este archivo pinta una CÁSCARA vacía y
 * `assets/js/presentacion.js` la rellena desde esos datos. Es lo que permite
 * que el mismo componente valga para un duelo entre dos personas, para un nodo
 * de cadena y para el amistoso del tutorial sin un solo `if` de tipo de
 * partida en el marcado.
 *
 * Espera en el ámbito: $presentacion (el array del servidor) y $base.
 */

if (empty($presentacion)) { return; }
?>

<div class="pres" id="presentacionDuelo" hidden
     role="dialog" aria-modal="true" aria-labelledby="presLadoNombre"
     data-id-duelo="<?= (int) $presentacion['id_duelo'] ?>">

  <?php /* FONDO. Tres capas separadas para poder moverlas a distinta velocidad
           (paralaje) sin repintar nada: los focos, la trama del campo y las
           motas. Todo son degradados y transformaciones — ni una imagen, ni un
           lienzo, ni una sola propiedad que obligue a recalcular la página. */ ?>
  <div class="pres-fondo" aria-hidden="true">
    <div class="pres-focos"></div>
    <div class="pres-trama"></div>
    <div class="pres-motas"></div>
    <div class="pres-barrido"></div>
  </div>

  <?php /* Saltar. Siempre visible y siempre en el mismo sitio: quien ya ha
           visto la intro veinte veces tiene que poder irse al partido sin
           buscar el botón. */ ?>
  <button type="button" class="pres-saltar" id="presSaltar">
    Saltar presentación <i class="ph ph-fast-forward" aria-hidden="true"></i>
  </button>

  <?php /* ---------- ESCENA 1 y 2: cada equipo, por turnos ---------- */ ?>
  <div class="pres-equipo" id="presEquipo" hidden>

    <div class="pres-identidad">
      <p class="pres-etiqueta" id="presLadoEtiqueta">LOCAL</p>

      <div class="pres-retrato" id="presRetrato">
        <img class="pres-retrato-img" id="presRetratoImg" alt="">
        <span class="pres-retrato-vacio" id="presRetratoVacio" hidden aria-hidden="true">
          <i class="ph ph-user"></i>
        </span>
      </div>

      <h2 class="pres-nombre" id="presLadoNombre"></h2>

      <dl class="pres-datos">
        <div class="pres-dato" id="presMazoBloque">
          <dt>Mazo</dt>
          <dd id="presMazo"></dd>
        </div>
        <div class="pres-dato">
          <dt>Formación</dt>
          <dd id="presFormacion"></dd>
        </div>
      </dl>

      <?php /* EL AUMENTO NO ES UNA LÍNEA MÁS. Es la decisión que el jugador
               acaba de tomar, así que tiene su propia placa, su brillo y su
               momento en la línea de tiempo. */ ?>
      <div class="pres-aumento" id="presAumento" hidden>
        <span class="pres-aumento-halo" aria-hidden="true"></span>
        <span class="pres-aumento-ico" id="presAumentoIco" aria-hidden="true"></span>
        <div class="pres-aumento-texto">
          <p class="pres-aumento-titulo">Aumento</p>
          <p class="pres-aumento-nombre"><b id="presAumentoPct"></b> <span id="presAumentoStat"></span></p>
          <p class="pres-aumento-desc" id="presAumentoDesc"></p>
        </div>
      </div>
    </div>

    <?php /* ---------- LA ALINEACIÓN ---------- */ ?>
    <div class="pres-alineacion">
      <p class="pres-alineacion-titulo">Alineación</p>
      <?php
      /* LAS ALINEACIONES SE PINTAN AQUÍ, EN EL SERVIDOR, Y CON `render_carta()`.
         Dos razones:

         1. Son las CARTAS DE VERDAD. Antes el JavaScript se dibujaba unas
            placas de nombre propias con un recorte cuadrado de la foto, y eso
            no es lo que el jugador tiene en su colección: faltaban la
            plantilla de la rareza, el arte a sangre y el borde. Con el
            componente de siempre no hay nada que replicar ni que mantener al
            día por duplicado.

         2. UN CONTENEDOR POR LADO Y LOS DOS YA PUESTOS. GSAP resuelve los
            elementos cuando CONSTRUYE la línea de tiempo, no cuando llega su
            turno: unas filas creadas más tarde no se animan nunca. Y de paso
            desaparece la única reescritura de HTML a mitad de secuencia, que
            era la peor forma de pagar una remaquetación. */
      foreach (['local', 'visitante'] as $ladoClave):
          $lado = $presentacion[$ladoClave];
      ?>
      <div class="pres-lineas" id="presLineas<?= ucfirst($ladoClave) ?>"
           <?= $ladoClave === 'visitante' ? 'hidden' : '' ?>>
        <?php foreach ($lado['lineas'] as $linea): ?>
          <div class="pres-linea">
            <span class="pres-linea-nombre"><?= htmlspecialchars(
              Tcg::ETIQUETA_LINEA[$linea['linea']] ?? $linea['linea']
            ) ?></span>
            <div class="pres-unidades">
              <?php foreach ($linea['unidades'] as $u): ?>
                <?php /* `lazy => false`: entran en pantalla a los dos segundos
                         de abrirse la intro, así que diferirlas solo garantiza
                         que lleguen tarde a su propia animación. */ ?>
                <?php render_carta($u, ['modo' => 'arte', 'lazy' => false]); ?>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php /* ---------- ESCENA 3: el enfrentamiento ---------- */ ?>
  <div class="pres-vs" id="presVs" hidden aria-hidden="true">
    <div class="pres-vs-lado pres-vs-lado--local">
      <span class="pres-vs-escudo" id="presVsLocalImg"></span>
      <span class="pres-vs-nombre" id="presVsLocal"></span>
    </div>
    <span class="pres-vs-marca">VS</span>
    <div class="pres-vs-lado pres-vs-lado--visitante">
      <span class="pres-vs-escudo" id="presVsVisitanteImg"></span>
      <span class="pres-vs-nombre" id="presVsVisitante"></span>
    </div>
  </div>

  <?php /* ---------- ESCENA 4: el pitido ---------- */ ?>
  <div class="pres-arranque" id="presArranque" hidden aria-hidden="true">
    <span class="pres-arranque-linea"></span>
    <p class="pres-arranque-texto">El partido comienza</p>
    <span class="pres-arranque-linea"></span>
  </div>

  <?php /* Lo que va pasando, para quien no ve la pantalla. La animación es
           decorativa; el ORDEN de la información no lo es, y por aquí llega
           igual a un lector de pantalla. */ ?>
  <p class="sr-only" role="status" aria-live="polite" id="presNarracion"></p>

  <script type="application/json" id="presDatos">
    <?= json_encode($presentacion, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
  </script>
</div>
