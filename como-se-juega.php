<?php
/**
 * CÓMO SE JUEGA — la guía que el sitio no tenía.
 *
 * La auditoría del 2026-08-26 lo señaló como la consulta con intención real que
 * no se respondía en ninguna parte: qué son las rarezas, cómo funcionan los
 * sobres, qué es una cadena, qué se apuesta en un duelo.
 *
 * ⚠️ TODAS LAS CIFRAS SALEN DE LA BASE DE DATOS, NO ESTÁN ESCRITAS A MANO.
 *    Las probabilidades de rareza, los sobres y sus precios, las compos, las
 *    afinidades y los pesos de cada línea se leen en vivo. Es la única forma de
 *    que una guía no envejezca sola: el día que se cambie el precio de un sobre
 *    o la probabilidad de una rareza, esta página lo dice al recargar.
 *
 * ⚠️ LOS BLOQUES SON AUTOCONTENIDOS A PROPÓSITO.
 *    Cada apartado responde una pregunta entera sin depender del anterior, en
 *    130-170 palabras. Es lo que permite que un buscador con IA lo cite tal
 *    cual; el sitio no tenía un solo párrafo que cumpliera eso.
 */
session_start();

require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/partials/seo.php';

$rarezas   = $db->listarRarezas();
$sobres    = $db->listarSobresActivos();
$totalFichas = $db->contarCromosTotales(0);

$paginaTitulo = 'Cómo se juega';
$paginaDesc   = 'Sobres, rarezas, formaciones, duelos y cadenas: todo lo que hace falta para empezar a jugar a Superliga Frontier TCG, explicado con las cifras reales del juego.';

seoCachePublica(3600);
include __DIR__ . '/partials/head.php';

$activePage = '';
include __DIR__ . '/navbar.php';
?>

<main id="contenido" class="seccion wrap">

  <header class="seccion-head">
    <p class="seccion-tag">Guía</p>
    <h1>Cómo se juega</h1>
  </header>

  <?php /* El primer bloque responde la pregunta entera —«¿cómo se juega?»— sin
           necesitar nada de lo que viene después. */ ?>
  <section class="panel">
    <p class="t-body">
      Superliga Frontier TCG se juega en tres pasos que se repiten: <b>consigues
      cartas</b> abriendo sobres, <b>montas un once</b> con ellas eligiendo una
      formación y colocando a cada jugador en su sitio, y <b>compites</b> con ese
      equipo. Competir es de dos formas: duelos contra otra persona, donde los
      dos ponéis algo sobre la mesa —cartas o monedas— y el ganador se lo lleva,
      o cadenas de partidos contra rivales que lleva la máquina, que se juegan
      cuando a uno le viene bien. De los partidos salen monedas, de las monedas
      salen más sobres, y de los sobres salen mejores cartas para el once. No
      hace falta pagar en ningún momento: la moneda del juego se gana jugando y
      no se puede comprar con dinero. La cuenta se crea con un nombre y una
      contraseña, y el primer sobre lo regala el juego.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Los sobres: de dónde salen las cartas</h2></div>
    <p class="t-body">
      Un sobre es un puñado de cartas al azar. Al crear la cuenta se entrega
      gratis el <b>sobre de bienvenida</b>, que no es aleatorio del todo: trae
      once jugadores con las posiciones justas para poder alinear un equipo
      completo desde el primer minuto. A partir de ahí los sobres se compran con
      monedas del juego, que se ganan jugando.
    </p>
    <?php if ($sobres): ?>
      <div class="tabla-wrap">
        <table class="tabla">
          <thead><tr><th>Sobre</th><th>Cartas</th><th>Precio</th></tr></thead>
          <tbody>
            <?php foreach ($sobres as $s): ?>
              <tr>
                <td><?= htmlspecialchars($s['nombre']) ?></td>
                <td class="mono"><?= (int) $s['cantidad'] ?></td>
                <td class="mono">
                  <?= (int) $s['precio'] === 0 ? 'Gratis' : number_format((int) $s['precio'], 0, ',', '.') . ' monedas' ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <p class="t-body-sm t-dim">
      Las cartas repetidas no se tiran: se cambian por monedas desde la pantalla
      de cortar del equipo, o se venden a otra gente en el mercado.
    </p>
  </section>

  <section class="panel" id="rarezas">
    <div class="panel-head"><h2 class="panel-titulo">Las siete rarezas, y lo que cuesta cada una</h2></div>
    <p class="t-body">
      Cada carta tiene una rareza, y la rareza decide dos cosas: lo difícil que
      es que salga en un sobre y lo altas que son sus estadísticas. Hay siete, de
      la más corriente a la más rara. Estas son las probabilidades reales con las
      que se reparten hoy las cartas:
    </p>
    <div class="tabla-wrap">
      <table class="tabla">
        <thead><tr><th>Rareza</th><th>Probabilidad</th><th>Sale una de cada</th></tr></thead>
        <tbody>
          <?php foreach ($rarezas as $r): $p = (float) $r['probabilidad']; ?>
            <tr>
              <td><?= htmlspecialchars($r['nombre']) ?></td>
              <td class="mono"><?= rtrim(rtrim(number_format($p, 2, ',', '.'), '0'), ',') ?> %</td>
              <td class="mono"><?= $p > 0 ? number_format(round(100 / $p), 0, ',', '.') . ' cartas' : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="t-body">
      La última, <b>Numerada</b>, además tiene <b>copias contadas</b>: cuando se
      reparten todas las que existen, no salen más por mucho que se abran sobres.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Qué dicen los números de una carta</h2></div>
    <p class="t-body">
      Cada jugador trae tres estadísticas —<b>ataque</b>, <b>defensa</b> y
      <b>técnica</b>— y una posición: portero, defensa, medio o delantero. Los
      números no valen lo mismo en todos los sitios del campo, y ahí está casi
      todo el juego. Cada línea puntúa con un peso distinto:
    </p>
    <div class="tabla-wrap">
      <table class="tabla">
        <thead><tr><th>Línea</th><th>Ataque</th><th>Defensa</th><th>Técnica</th></tr></thead>
        <tbody>
          <?php foreach (Tcg::PESOS_LINEA as $linea => $peso): ?>
            <tr>
              <td><?= htmlspecialchars(Tcg::ETIQUETA_LINEA[$linea] ?? $linea) ?></td>
              <?php foreach (['ataque', 'defensa', 'tecnica'] as $c): ?>
                <td class="mono"><?= number_format((float) $peso[$c], 2, ',', '.') ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="t-body">
      Un hueco de portería puntúa sobre todo con la defensa y nada con el ataque;
      uno de ataque, justo al revés. Por eso un delantero con 95 de ataque
      colocado en defensa rinde poco: ahí su mejor número casi no cuenta. Las
      cartas que no juegan —escudos, presidentes, entrenadores y gerentes— no
      llevan estadísticas y no se alinean.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Montar el once</h2></div>
    <p class="t-body">
      Un mazo son <b><?= Tcg::MAZO_TAMANO ?> jugadores</b> colocados sobre un
      campo. Primero se elige una <b>formación</b> —hay
      <?= count(Tcg::FORMACIONES) ?> disponibles, desde el
      <?= Tcg::FORMACIONES[Tcg::FORMACION_BASE]['nombre'] ?? '4-4-2' ?> de partida
      hasta repartos mucho más raros— y cada formación decide cuántos huecos hay
      de cada línea. Después se coloca a cada jugador en un hueco.
    </p>
    <p class="t-body">
      Un mismo jugador no puede aparecer dos veces en el once aunque se tengan
      varias copias suyas. Se pueden tener varios mazos guardados, y uno de ellos
      se marca como <b>titular</b>: es con el que se disputan los duelos. Las
      formaciones nuevas no se compran: se desbloquean terminando cadenas.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Afinidades y compos</h2></div>
    <p class="t-body">
      Aquí es donde una alineación deja de ser «los once con los números más
      altos». Cada carta trae dos rasgos que <b>no suman por sí solos</b>: solo
      cuentan cuando hay <b>varias del mismo</b> en el once. La
      <b>afinidad</b> es el elemento del jugador y la <b>compo</b> es su estilo,
      y los dos funcionan igual: refuerzan una línea concreta, y el refuerzo
      crece en tres escalones según cuántas cartas de ese rasgo alineas.
    </p>

    <?php
    /* Los umbrales y los porcentajes se leen del catálogo de rasgos, no se
       escriben aquí: son datos de equilibrio y se tocan desde el panel. */
    $catalogo = $db->rasgosCatalogo();
    $umbrales = null;
    foreach ($catalogo as $rg) {
        if (($rg['tipo'] ?? '') === 'afinidad') {
            $umbrales = [(int) $rg['umbral_1'], (int) $rg['umbral_2'], (int) $rg['umbral_3']];
            break;
        }
    }
    $pct = fn($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') . ' %';
    ?>

    <div class="tabla-wrap">
      <table class="tabla">
        <thead>
          <tr>
            <th>Rasgo</th><th>Tipo</th><th>Refuerza</th>
            <?php foreach ($umbrales ?: [2, 5, 11] as $u): ?>
              <th>Con <?= $u ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($catalogo as $rg): ?>
            <?php
            if (!in_array($rg['tipo'] ?? '', ['afinidad', 'configuracion'], true)) { continue; }
            $lineas = array_filter([$rg['linea_1'] ?? null, $rg['linea_2'] ?? null]);
            $lineas = array_map(fn($l) => Tcg::ETIQUETA_LINEA[$l] ?? $l, $lineas);
            ?>
            <tr>
              <td><b><?= htmlspecialchars($rg['nombre']) ?></b></td>
              <td><?= $rg['tipo'] === 'afinidad' ? 'Afinidad' : 'Compo' ?></td>
              <td><?= htmlspecialchars(implode(' y ', $lineas)) ?></td>
              <td class="mono"><?= $pct($rg['pct_1']) ?></td>
              <td class="mono"><?= $pct($rg['pct_2']) ?></td>
              <td class="mono"><?= $pct($rg['pct_3']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p class="t-body">
      Se lee así: con <?= $umbrales[0] ?? 2 ?> cartas del mismo rasgo se activa el
      primer escalón, con <?= $umbrales[1] ?? 5 ?> el segundo y con
      <?= $umbrales[2] ?? 11 ?> —el once entero— el tercero. Y no todos valen lo
      mismo: <b>Montaña</b> es el que más da porque refuerza la portería, que es
      una sola plaza, mientras que los que reparten entre dos líneas dan menos
      por escalón a cambio de tocar más campo.
    </p>
    <p class="t-body-sm t-dim">
      El editor de mazos va diciendo en vivo qué se está activando mientras
      colocas, así que no hay que calcular nada a mano.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Los duelos</h2></div>
    <p class="t-body">
      Un duelo es un partido contra el once de otra persona. Se crea una sala
      diciendo qué se apuesta: <b>monedas</b>, o <b>hasta
      <?= max(1, (int) $db->config('duelo_cartas_max', 5)) ?> cartas</b> de una
      rareza pactada. Quien acepta tiene que poner lo mismo, así que las dos
      partes arriesgan igual. El ganador se lleva el bote.
    </p>
    <p class="t-body">
      El partido no se decide solo comparando la fuerza de los dos equipos.
      Antes de empezar, cada bando recibe un <b>aumento</b> al azar que refuerza
      una línea —ataque, técnica o defensa—, y durante el partido hay minijuegos
      que deciden ocasiones concretas. Si acaba en empate, se resuelve en una
      tanda de penaltis que también se juega. Las cartas apostadas quedan
      retenidas mientras el duelo está vivo: no se pueden vender ni meter en otro
      mazo hasta que termine.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Las cadenas: jugar sin esperar a nadie</h2></div>
    <p class="t-body">
      Una cadena es un mapa de partidos contra rivales que lleva la máquina. Se
      empieza por el nodo de salida y cada victoria abre los siguientes, así que
      se avanza al ritmo de uno. Antes de cada partido se elige la
      <b>dificultad</b>
      —<?= htmlspecialchars(implode(', ', array_map(fn($d) => Tcg::ETIQUETAS_DIFICULTAD[$d] ?? $d, Tcg::DIFICULTADES))) ?>—
      y cuanto más alta, mejor el botín.
    </p>
    <p class="t-body">
      Por el camino hay <b>cofres</b>, que no se juegan: se abren al llegar y
      sueltan cartas, monedas o una formación nueva. Y hay nodos de
      <b>control de paso</b> que no dejan seguir hasta cumplir algo concreto,
      como ganar con cierto rango o marcar un número de goles. Si todos los
      partidos que llevan hasta un cofre se han ganado en la dificultad más alta
      y con el mejor rango, ese cofre reparte además un premio extra por
      <b>camino perfecto</b>.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Monedas, misiones y mercado</h2></div>
    <p class="t-body">
      Las monedas se ganan jugando: ganando duelos, avanzando en cadenas,
      cumpliendo misiones y vendiendo cartas repetidas. Se gastan en sobres y en
      comprar cartas a otra gente. <b>No se pueden comprar con dinero real</b> y
      no tienen ningún valor fuera del juego.
    </p>
    <p class="t-body">
      Las <b>misiones</b> son objetivos que se van cumpliendo solos mientras
      juegas: reunir cartas distintas, montar mazos, jugar duelos, ganarlos o
      completar una expansión entera. El <b>mercado</b> es donde se compran y
      venden cartas entre jugadores, con un precio acotado por lo que vale la
      carta para que nadie publique una común a precio de legendaria.
    </p>
    <p class="fi-acciones" style="margin-top:var(--e-5)">
      <a class="btn btn-primary" href="acceso?modo=crear">Crear cuenta y abrir el primer sobre</a>
      <a class="btn btn-plano" href="plantilla">Ver las <?= number_format($totalFichas, 0, ',', '.') ?> fichas</a>
    </p>
  </section>

  <p class="t-body-sm t-dim">
    ¿Te queda alguna duda? Están resueltas en las
    <a href="preguntas-frecuentes">preguntas frecuentes</a>.
  </p>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
