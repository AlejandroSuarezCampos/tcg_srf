<?php
/**
 * MODAL DE FICHA DE CARTA — el destino de todo lo que el modo 'arte' quita
 * de encima del arte.
 *
 * Una sola instancia por página, vacía. La rellena `ui.js` desde los atributos
 * `data-*` que el componente de carta ya escribe en modo 'arte': no hay
 * segunda consulta, ni endpoint AJAX, ni un JSON de todas las cartas
 * duplicando lo que ya está en el HTML.
 *
 * Uso: incluirlo una vez al final de cualquier página que pinte cartas con
 * `['modo' => 'arte', 'ficha' => true]`.
 *
 *   <?php include __DIR__ . '/partials/ficha_carta.php'; ?>
 *
 * El modal se abre y se cierra con el mecanismo compartido de siempre
 * (`SRF.abrirModal`), así que hereda el foco atrapado, Esc-para-cerrar y el
 * clic en el fondo sin volver a implementarlos.
 */
?>
<div class="modal modal--ficha" id="modalFicha" role="dialog" aria-modal="true"
     aria-labelledby="fichaNombre" aria-hidden="true">
  <div class="modal-caja modal-caja--ficha">

    <button type="button" class="modal-cerrar ficha-cerrar" data-cerrar-modal aria-label="Cerrar ficha">
      <i class="ph ph-x" aria-hidden="true"></i>
    </button>

    <div class="ficha-arte">
      <?php /* La carta del modal se monta en JS reutilizando el mismo marcado
               de `.carta--arte`: así el borde de rareza —incluido el arcoíris
               animado de la SRF— sale gratis y no hay una segunda forma de
               pintar una carta que mantener. */ ?>
      <div class="ficha-carta-hueco" id="fichaCarta"></div>
    </div>

    <div class="ficha-datos">
      <p class="ficha-titulo-fila">
        <span class="t-h2" id="fichaNombre"></span>
        <span id="fichaRareza"></span>
      </p>

      <p class="ficha-meta t-dim" id="fichaMeta"></p>
      <?php /* De qué universo viene la carta. Se pinta desde ui.js con el dato
               que la propia carta trae en `data-universo`. */ ?>
      <p id="fichaUniverso"></p>

      <div class="ficha-stats" id="fichaStats">
        <?php
        /* Las tres estadísticas reales. El color NUNCA va solo (§7): cada una
           lleva icono y etiqueta escrita, así que se distinguen sin depender
           de ver verde, rojo o ámbar. */
        $statsFicha = [
            ['clave' => 'ataque',  'etiqueta' => 'Ataque',  'icono' => 'ph-sword',     'mod' => 'ata'],
            ['clave' => 'defensa', 'etiqueta' => 'Defensa', 'icono' => 'ph-shield',    'mod' => 'def'],
            ['clave' => 'tecnica', 'etiqueta' => 'Técnica', 'icono' => 'ph-lightning', 'mod' => 'tec'],
        ];
        foreach ($statsFicha as $s): ?>
          <div class="ficha-stat ficha-stat--<?= $s['mod'] ?>" data-stat="<?= $s['clave'] ?>">
            <p class="ficha-stat-cifra">
              <i class="ph-fill <?= $s['icono'] ?>" aria-hidden="true"></i>
              <b class="mono" data-valor>0</b>
            </p>
            <p class="ficha-stat-etiqueta"><?= $s['etiqueta'] ?></p>
            <?php /* La barra es redundante con el número, por eso es
                     aria-hidden: un lector de pantalla ya ha leído el valor. */ ?>
            <span class="ficha-stat-barra" aria-hidden="true"><i data-barra></i></span>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="ficha-bloque" id="fichaAfinidadBloque" hidden>
        <p class="ficha-etiqueta">Afinidad</p>
        <?php /* Con su hexágono, como en la carta de siempre: la afinidad se
                 reconoce por el icono antes que por el nombre. */ ?>
        <?php /* ⚠️ EL <img> VA SIN `src`, Y NO ES UN DESCUIDO.
                 Un `src=""` es una URL inválida: el navegador dispara `error`
                 sobre esa imagen NADA MÁS CARGAR LA PÁGINA. Y este proyecto
                 tiene un manejador global de `error` que, al ver una imagen
                 rota dentro de un `.carta-afinidad`, BORRA el hexágono entero
                 —pensado para las cartas, donde una afinidad sin gráfico
                 simplemente no se pinta—.
                 Resultado: este span desaparecía del DOM antes de que nadie
                 abriera el modal, y el código que le pone la imagen al abrirlo
                 no encontraba nada a lo que ponérsela. Por eso el icono no
                 salía nunca, por mucho que el dato llegara bien.
                 Sin atributo `src` no hay petición y no hay error; la ruta la
                 pone ui.js al abrir la ficha. */ ?>
        <p class="ficha-afinidad">
          <span class="carta-afinidad" id="fichaAfinidadIcono" hidden><img alt=""></span>
          <span id="fichaAfinidad"></span>
        </p>
      </div>

      <div class="ficha-bloque" id="fichaRasgoBloque" hidden>
        <p class="ficha-etiqueta">Compo</p>
        <p id="fichaRasgo"></p>
      </div>

      <div class="ficha-acciones" id="fichaAcciones"></div>
    </div>

  </div>
</div>
