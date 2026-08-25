<?php
/**
 * CABECERA DE PANTALLA — compartida por las pantallas del sistema Ascua.
 *
 * Sustituye al bloque `<header class="cabecera">` que estaba copiado a mano en
 * seis pantallas, cada una con su propia mezcla de `linea-campo`,
 * `cabecera-datos` y `fila fila-entre`. Al estar repetido, cada retoque había
 * que hacerlo seis veces y siempre se quedaba alguna fuera.
 *
 * Uso:
 *   cabecera([
 *     'rotulo'  => 'Jugar',                      // etiqueta micro, opcional
 *     'titulo'  => 'Sobres',
 *     'texto'   => 'Cada sobre reparte…',        // opcional
 *     'datos'   => [['1.836', 'Tus monedas'], …] // opcional, con id opcional
 *     'accion'  => '<button …>',                 // HTML ya montado, opcional
 *     'avatar'  => '<span class="avatar">…</span>', // opcional, va a la izquierda
 *     'pastilla'=> 'Administración',              // opcional, junto al título
 *   ]);
 *
 * Un dato puede llevar id para que lo repinte el JS sin recargar:
 *   ['1.836', 'Tus monedas', 'saldoMonedas']
 */

function cabecera(array $o): void {
    $rotulo = $o['rotulo'] ?? '';
    $titulo = $o['titulo'] ?? '';
    $texto  = $o['texto']  ?? '';
    $datos  = $o['datos']  ?? [];
    $accion   = $o['accion']   ?? '';
    $avatar   = $o['avatar']   ?? '';
    $pastilla = $o['pastilla'] ?? '';
    ?>
    <header class="cab">
      <?php /* Un rescoldo por pantalla, y este es el de estas. No lleva trama:
               la retícula técnica se reserva a hero, hub y duelo — debajo de una
               rejilla de cartas compite con el arte. */ ?>
      <div class="rescoldo" aria-hidden="true"></div>

      <div class="wrap cab-cuerpo">
        <div class="cab-fila">
          <?php if ($avatar !== ''): ?>
            <div class="cab-avatar"><?= $avatar ?></div>
          <?php endif; ?>

          <div class="cab-texto">
            <?php if ($rotulo !== ''): ?>
              <p class="label sube"><?= htmlspecialchars($rotulo) ?></p>
            <?php endif; ?>

            <h1 class="cab-titulo" data-revela="160"><?= htmlspecialchars($titulo) ?></h1>

            <?php if ($pastilla !== ''): ?>
              <span class="cab-pastilla"><?= htmlspecialchars($pastilla) ?></span>
            <?php endif; ?>

            <?php if ($texto !== ''): ?>
              <p class="cab-texto-sub sube"><?= htmlspecialchars($texto) ?></p>
            <?php endif; ?>
          </div>

          <?php if ($accion !== ''): ?>
            <div class="cab-accion"><?= $accion ?></div>
          <?php endif; ?>
        </div>

        <?php if ($datos): ?>
          <dl class="cab-datos escalona">
            <?php foreach ($datos as $d): ?>
              <?php [$cifra, $etiqueta] = $d; $id = $d[2] ?? null; ?>
              <div class="cab-dato">
                <dd class="cif num"<?= $id ? ' id="' . htmlspecialchars($id) . '"' : '' ?>><?= htmlspecialchars((string) $cifra) ?></dd>
                <dt class="rot"><?= htmlspecialchars($etiqueta) ?></dt>
              </div>
            <?php endforeach; ?>
          </dl>
        <?php endif; ?>
      </div>
    </header>
    <?php
}
