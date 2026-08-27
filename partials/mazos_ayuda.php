<?php
/**
 * CÓMO FUNCIONA UN MAZO — el manual, dentro de la pantalla donde se usa.
 *
 * Todo esto (compos, afinidades, coherencia, tensión, aumentos) se calcula en
 * el servidor y se ve en pantalla ya resuelto: números que suben y bajan sin
 * que nada explique por qué. Aquí se explica.
 *
 * ⚠️ NINGÚN NÚMERO ESTÁ ESCRITO A MANO. Los porcentajes salen de la tabla
 *    `rasgos`, los umbrales de `configuracion` y las probabilidades de tier de
 *    `probabilidadesTier()` — o sea, de las MISMAS fuentes que usa el motor al
 *    resolver un duelo. Es la única forma de que esta ayuda no se convierta en
 *    mentira la primera vez que alguien calibre `line_cap` desde el panel: si
 *    los valores estuvieran copiados aquí, nadie se enteraría de que ya no
 *    coinciden hasta que un jugador hiciera la cuenta y no le saliera.
 *
 * Va en un <details> cerrado: quien monta su décimo mazo no necesita releerlo,
 * y quien monta el primero lo encuentra donde le surge la duda.
 */

$rasgos = $db->rasgosCatalogo();

// Los tres bloques del catálogo, cada uno con su explicación distinta.
$porTipo = ['afinidad' => [], 'configuracion' => [], 'derivado' => []];
foreach ($rasgos as $clave => $r) {
    if (isset($porTipo[$r['tipo']])) { $porTipo[$r['tipo']][$clave] = $r; }
}

$etiquetaLineaAyuda = ['POR' => 'Portería', 'DF' => 'Defensa', 'MC' => 'Medio', 'DC' => 'Ataque'];

// Calibración viva. Los mismos `config()` que lee calcularCompos().
$pesosDr    = array_map('floatval', explode(',', (string) $db->config('compo_pesos_dr', '1.0,0.7,0.45,0.25')));
$topeLinea  = (float) $db->config('line_cap', 20);
$umbralLib  = (float) $db->config('coherencia_umbral_libre', 2.5);
$rateCoh    = (float) $db->config('coherencia_malus_rate', 3.0);
$topeMalus  = (float) $db->config('coherencia_malus_tope', 18);
$bonoCiclo  = (float) $db->config('ciclo_contra_afinidad_bonus', 5.5);

$num = fn($v, $dec = 2) => number_format((float) $v, $dec, ',', '.');

/** El ciclo de afinidades, con los nombres que ve el jugador. */
$nombreAfi = [];
foreach ($porTipo['afinidad'] as $clave => $r) { $nombreAfi[$clave] = $r['nombre']; }
?>

<details class="ayuda-mazos" id="ayudaMazos">
  <summary>
    <i class="ph ph-question" aria-hidden="true"></i>
    <span>Cómo funciona un mazo</span>
    <span class="t-caption t-dim">compos, afinidades, coherencia, tensión y aumentos</span>
  </summary>

  <div class="ayuda-cuerpo">

    <!-- ------------------------------------------------------------------ -->
    <section class="ayuda-bloque">
      <h3>Dónde colocas a cada uno importa más que quién es</h3>
      <p class="t-body-sm t-dim">
        Cualquier carta puede ir en cualquier hueco: no hay reglas de posición.
        Lo que cambia es <b>con qué estadística puntúa</b>. Cada línea pesa las
        tres estadísticas de otra manera, así que un central en la punta rinde
        como lo que es, no como lo que pone la carta.
      </p>
      <div class="tabla-wrap">
        <table class="tabla tabla-compacta">
          <thead>
            <tr>
              <th scope="col">Línea</th>
              <th scope="col">Ataque</th>
              <th scope="col">Defensa</th>
              <th scope="col">Técnica</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (Tcg::PESOS_LINEA as $linea => $pesos): ?>
              <tr>
                <th scope="row"><?= $etiquetaLineaAyuda[$linea] ?></th>
                <?php foreach (['ataque', 'defensa', 'tecnica'] as $stat): ?>
                  <?php $esDueña = $pesos[$stat] === max($pesos); ?>
                  <td class="mono<?= $esDueña ? ' es-dueña' : '' ?>">×<?= $num($pesos[$stat], 2) ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="t-caption t-dim">
        Resaltada, la estadística que manda en cada línea. Pasa el ratón por un
        titular del campo y verás lo que aporta con estos mismos pesos.
      </p>

      <h4 class="ayuda-sub">Y jugar fuera de tu puesto pasa factura</h4>
      <p class="t-body-sm t-dim">
        Puedes poner a quien quieras donde quieras — no hay nada prohibido —,
        pero una carta <b>rinde menos fuera de su posición</b>. Moverse entre
        líneas de campo es barato; <b>la portería es otro oficio</b> y ahí el
        castigo es serio en los dos sentidos.
      </p>
      <div class="tabla-wrap">
        <table class="tabla tabla-compacta">
          <thead>
            <tr>
              <th scope="col">La carta es…</th>
              <?php foreach ($etiquetaLineaAyuda as $l => $nom): ?>
                <th scope="col">…jugando de <?= $nom ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach (Tcg::RENDIMIENTO_FUERA_DE_PUESTO as $pos => $fila): ?>
              <tr>
                <th scope="row"><?= $etiquetaLineaAyuda[$pos] ?></th>
                <?php foreach ($etiquetaLineaAyuda as $l => $nom): ?>
                  <td class="mono<?= $pos === $l ? ' es-dueña' : '' ?>">
                    <?= $num(($fila[$l] ?? 1) * 100, 0) ?> %
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="t-caption t-dim">
        Un once bien colocado no pierde ni un punto por esto: la diagonal es
        todo cien. Solo resta a quien coloca mal, y la ficha del titular te dice
        exactamente cuánto.
      </p>
    </section>

    <!-- ------------------------------------------------------------------ -->
    <section class="ayuda-bloque">
      <h3>Compos: repetir sale a cuenta</h3>
      <p class="t-body-sm t-dim">
        Un <b>rasgo</b> se activa cuando tienes suficientes cartas que lo
        comparten en el once. Hay tres niveles, y cada uno refuerza una línea
        concreta. Ninguna carta lo elige: su afinidad y su compo vienen dadas.
      </p>

      <h4 class="ayuda-sub">Por afinidad</h4>
      <p class="t-caption t-dim">La lleva cada carta en su hexágono.</p>
      <?php
      /* La misma tabla para los dos tipos: cambia lo que significan, no cómo
         se leen. Se pinta con un bucle en vez de dos veces a mano para que
         añadir un rasgo al catálogo no exija tocar esto. */
      $pintarRasgos = function (array $lista) use ($etiquetaLineaAyuda, $num) {
          if (!$lista) { return; }
          ?>
          <div class="tabla-wrap">
            <table class="tabla tabla-compacta">
              <thead>
                <tr>
                  <th scope="col">Rasgo</th>
                  <th scope="col">Refuerza</th>
                  <th scope="col">Nivel 1</th>
                  <th scope="col">Nivel 2</th>
                  <th scope="col">Nivel 3</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($lista as $r): ?>
                  <tr>
                    <th scope="row"><?= htmlspecialchars($r['nombre']) ?></th>
                    <td>
                      <?= $etiquetaLineaAyuda[$r['linea_1']] ?? '—' ?>
                      <?php if ($r['linea_2']): ?>
                        y <?= $etiquetaLineaAyuda[$r['linea_2']] ?>
                      <?php endif; ?>
                    </td>
                    <?php foreach ([1, 2, 3] as $n): ?>
                      <td class="mono">
                        <?= (int) $r['umbral_' . $n] ?> cartas
                        <span class="t-dim">· +<?= $num($r['pct_' . $n]) ?> %</span>
                      </td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php
      };
      $pintarRasgos($porTipo['afinidad']);
      ?>

      <h4 class="ayuda-sub">Por configuración</h4>
      <p class="t-caption t-dim">
        El estilo de juego de la carta. Sale en su ficha, bajo «Compo».
      </p>
      <?php $pintarRasgos($porTipo['configuracion']); ?>

      <div class="ayuda-aviso">
        <i class="ph ph-warning-circle" aria-hidden="true"></i>
        <div>
          <b>Acumular no multiplica.</b>
          Cuando varios rasgos refuerzan la MISMA línea, solo el mayor cuenta
          entero. Los siguientes valen
          <?php foreach (array_slice($pesosDr, 1) as $i => $p): ?>
            <span class="mono"><?= $num($p * 100, 0) ?> %</span><?= $i < count($pesosDr) - 2 ? ', ' : '' ?>
          <?php endforeach; ?>
          de lo suyo, y de ahí en adelante siempre el último.
          Además ninguna línea puede pasar de
          <span class="mono">+<?= $num($topeLinea, 0) ?> %</span> por compos,
          por muchas que apiles.
        </div>
      </div>
    </section>

    <!-- ------------------------------------------------------------------ -->
    <section class="ayuda-bloque">
      <h3>El ciclo de afinidades</h3>
      <p class="t-body-sm t-dim">
        Tu afinidad <b>dominante</b> es la que más se repite en tu once. Si hay
        empate, no tienes ninguna. Cuando la tuya <b>contra</b> a la del rival,
        ganas <span class="mono">+<?= $num($bonoCiclo) ?> %</span> al total —y
        es solo para quien contra: el otro no pierde nada por ello.
      </p>
      <ul class="ayuda-ciclo">
        <?php foreach (Tcg::CICLO_AFINIDAD as $gana => $pierde): ?>
          <li>
            <b><?= htmlspecialchars($nombreAfi[$gana] ?? $gana) ?></b>
            <i class="ph ph-arrow-right" aria-hidden="true"></i>
            <span><?= htmlspecialchars($nombreAfi[$pierde] ?? $pierde) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="t-caption t-dim">
        Se lee «gana a». Un once repartido entre las cuatro no contra a nadie,
        pero tampoco se deja contrar.
      </p>
    </section>

    <!-- ------------------------------------------------------------------ -->
    <section class="ayuda-bloque">
      <h3>Malus de coherencia: por qué un once de legendarias puede rendir peor</h3>
      <p class="t-body-sm t-dim">
        Es la regla que impide que ganar sea solo comprar cartas caras. Se
        comparan dos cosas de tu once: lo <b>raro</b> que es (la rareza media) y
        lo <b>trabado</b> que está (la suma de los niveles de tus compos).
      </p>
      <ul class="ayuda-pasos">
        <li>
          Hasta una rareza media de <span class="mono"><?= $num($umbralLib, 1) ?></span>
          no se te pide nada: monta lo que quieras.
        </li>
        <li>
          Por encima, cada punto de exceso te exige
          <span class="mono"><?= $num($rateCoh, 1) ?></span> de compo.
        </li>
        <li>
          Lo que te falte para llegar se te descuenta de la fuerza total, hasta
          un máximo de <span class="mono"><?= $num($topeMalus, 0) ?> %</span>.
        </li>
      </ul>
      <p class="t-caption t-dim">
        Lo verás arriba, en «Malus de coherencia». Si te sale, la salida no es
        bajar de rareza: es <b>repetir más afinidades o más compos</b> entre tus
        once para que el mazo se justifique solo.
      </p>
    </section>

    <!-- ------------------------------------------------------------------ -->
    <section class="ayuda-bloque">
      <h3>Tensión y aumentos</h3>
      <p class="t-body-sm t-dim">
        La <b>Tensión</b> no cuenta copias: cuenta cuántos rasgos
        <b>distintos</b> tienes activos. Y no da ni un punto de fuerza — lo que
        mejora es el sorteo del <b>Aumento</b>, la subida que eliges justo antes
        de cada partido.
      </p>
      <?php $tension = $porTipo['derivado']['tension'] ?? null; ?>
      <?php if ($tension): ?>
        <p class="t-caption t-dim">
          Se activa con
          <span class="mono"><?= (int) $tension['umbral_1'] ?></span>,
          <span class="mono"><?= (int) $tension['umbral_2'] ?></span> y
          <span class="mono"><?= (int) $tension['umbral_3'] ?></span>
          rasgos distintos.
        </p>
      <?php endif; ?>

      <div class="tabla-wrap">
        <table class="tabla tabla-compacta">
          <?php /* Tres tiers, no cuatro: `probabilidadesTier()` devuelve
                   plata/oro/prisma y nada más. Las columnas se sacan de la
                   propia respuesta para que añadir un tier al motor lo traiga
                   aquí solo, en vez de dejar una columna a cero que nadie
                   entiende. */ ?>
          <?php $tiers = array_keys($db->probabilidadesTier(0)); ?>
          <thead>
            <tr>
              <th scope="col">Tensión</th>
              <?php foreach ($tiers as $tier): ?>
                <th scope="col"><?= htmlspecialchars(ucfirst($tier)) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ([0, 1, 2, 3] as $nivel): ?>
              <?php $p = $db->probabilidadesTier($nivel); ?>
              <tr>
                <th scope="row">Nivel <?= $nivel ?><?= $nivel === 0 ? ' (sin tensión)' : '' ?></th>
                <?php foreach ($tiers as $tier): ?>
                  <td class="mono"><?= $num($p[$tier] ?? 0, 0) ?> %</td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="t-caption t-dim">
        Cuanto mejor el tier, más sube el Aumento. Por eso a un mazo muy
        repetido —mucha fuerza por compos, poca variedad— le conviene abrirse
        un poco: cambia potencia segura por mejores tiradas.
      </p>
    </section>

    <!-- ------------------------------------------------------------------ -->
    <section class="ayuda-bloque">
      <h3>Formaciones</h3>
      <p class="t-body-sm t-dim">
        Cambiar de formación <b>no mueve a nadie</b>: los once se quedan en sus
        huecos y lo que cambia es la línea de cada hueco, o sea con qué
        estadística puntúa cada carta. Puedes probar una y volver atrás sin
        perder la alineación.
      </p>
      <p class="t-body-sm t-dim">
        Las de más de tres líneas reparten roles dentro de una misma fila: en un
        <b>4-2-3-1</b>, de los tres de arriba los dos de banda puntúan como
        <b>Ataque</b> y el mediapunta como <b>Medio</b>. Por eso dos formaciones
        con el mismo dibujo pueden rendir distinto según a quién pongas dónde.
      </p>
      <p class="t-caption t-dim">
        Empiezas con
        <?= htmlspecialchars(implode(' y ', array_map(
            fn($c) => Tcg::FORMACIONES[$c]['nombre'], Tcg::FORMACIONES_LIBRES))) ?>.
        Las otras <span class="mono"><?= count(Tcg::FORMACIONES) - count(Tcg::FORMACIONES_LIBRES) ?></span>
        se ganan completando cadenas.
      </p>
    </section>

  </div>
</details>
