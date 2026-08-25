<?php
/**
 * PORTADA — captación. SOLO para quien no ha entrado.
 *
 * EL ORDEN ES LA DECISIÓN. La versión anterior era hero → cartas destacadas →
 * expansiones: enseñaba el producto ANTES de explicarlo, así que alguien de
 * fuera del nicho veía una rejilla de caras que no conocía y se iba. Aquí se
 * explica, se demuestra, se prueba y se convierte:
 *
 *   1. Hero                qué es, en una frase, y el botón
 *   2. Qué es esto         tres frases para quien no ha oído hablar de la liga
 *   3. Cómo se juega       tres pasos, el bucle completo del juego
 *   4. Las siete rarezas   el gancho del coleccionismo, con sus odds REALES
 *   5. Las más raras       ahora sí: enseñar cartas, cuando ya se sabe qué son
 *   6. Los números         prueba social con cifras de la base, no inventadas
 *   7. Cierre              el mismo botón, para quien ha bajado hasta aquí
 *
 * Las secciones 2, 3, 4 y 6 no existían.
 */
session_start();

/* Quien ya ha entrado no tiene nada que hacer en una página de captación: su
   portada es `hoy.php`, que le dice qué tiene pendiente. Antes esta pantalla
   era idéntica con y sin sesión salvo un botón, y era el fallo más caro del
   producto. La redirección va ANTES de conectar con la base de datos: así una
   visita con sesión no paga ninguna de las consultas de abajo. */
if (!empty($_SESSION['id_usuario'])) {
    header('Location: hoy.php');
    exit;
}

require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';

$destacadas = $db->listarDestacados(6);
$stats      = $db->estadisticasPublicas();
$rarezas    = $db->listarRarezas();

/* La carta del hero es la más rara que haya. Si algún día no hubiera ninguna
   —base recién montada—, el hero se pinta igual sin ella. */
$cartel = $destacadas[0] ?? null;

$paginaTitulo = 'Cada jugador de la liga, una carta';
$paginaDesc   = 'El juego de cartas coleccionables de la Superliga Frontier: '
              . number_format($stats['fichas'], 0, ',', '.')
              . ' fichas, siete rarezas y duelos contra otros jugadores.';
$cssExtra     = ['assets/css/landing.css'];
include __DIR__ . '/partials/head.php';

$activePage = 'landing';
include __DIR__ . '/navbar.php';
?>

<main id="contenido" class="portada">

  <?php /* ===== 1. HERO =====
           Tres dispositivos de golpe: rescoldo dominante, rejilla técnica y
           tipografía a sangre. Es la única pantalla del sitio donde se permite
           el tamaño display, y aparece UNA vez. */ ?>
  <section class="pt-hero">
    <div class="rescoldo" aria-hidden="true"></div>
    <div class="trama" aria-hidden="true"></div>

    <div class="wrap pt-hero-cuerpo">
      <div class="pt-hero-texto">
        <p class="label sube">Superliga Frontier · Temporada 03</p>
        <h1 class="pt-display a-sangre" data-revela="200">Cada jugador de la liga es una carta</h1>
        <p class="pt-lede sube">
          Consíguelas en sobres, monta tu once y sácalo al campo contra el de otro.
        </p>
        <div class="pt-acciones sube">
          <a class="pt-cta" href="registro.php">
            Abre tu primer sobre gratis
            <i class="ph ph-arrow-right" aria-hidden="true"></i>
          </a>
          <a class="pt-cta-suave" href="plantilla.php?ver=todas">
            Ver las <?= number_format($stats['fichas'], 0, ',', '.') ?> fichas
          </a>
        </div>
      </div>

      <?php if ($cartel): ?>
        <?php /* Dispositivo 2: la carta va DELANTE de la palabra en hueco. Dos
                 capas apiladas, no un 3D — la inclinación la pone motion.js, y
                 solo con puntero fino y movimiento completo. */ ?>
        <div class="pt-escena inclina-escena" aria-hidden="true">
          <span class="tipo-fondo"><?= htmlspecialchars($cartel['rareza']) ?></span>
          <div class="pt-carta inclina">
            <?php render_carta($cartel, ['modo' => 'arte', 'lazy' => false]); ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php /* ===== 2. QUÉ ES ESTO ===== ← NUEVA
           No existía. La portada enseñaba una rejilla de caras a alguien que no
           sabía de qué liga le estaban hablando. */ ?>
  <section class="wrap pt-seccion">
    <div class="pt-que escalona">
      <p class="label label--mudo">Qué es esto</p>
      <h2 class="pt-h2">Un TCG hecho con la gente de la Superliga Frontier</h2>
      <p class="pt-parrafo">
        La Superliga Frontier es una competición de <b>Inazuma Eleven: Victory Road</b>.
        Cada jugador, presidente y escudo que ha pasado por ella tiene aquí su ficha,
        con sus estadísticas de ataque, defensa y técnica.
      </p>
      <p class="pt-parrafo">
        También hay cartas del Inazuma original, porque la cantera no se cierra.
        Es un proyecto de fans, gratis y sin ánimo de lucro.
      </p>
    </div>
  </section>

  <?php /* ===== 3. CÓMO SE JUEGA ===== ← NUEVA
           El bucle entero en tres pasos. Numerados de verdad: aquí el orden SÍ
           es información, porque cada paso lleva al siguiente. */ ?>
  <section class="wrap pt-seccion">
    <p class="label label--mudo">Cómo se juega</p>
    <h2 class="pt-h2">Tres pasos y estás dentro</h2>

    <ol class="pt-pasos escalona">
      <li class="pt-paso">
        <span class="pt-paso-n" aria-hidden="true">01</span>
        <i class="ph ph-package pt-paso-ico" aria-hidden="true"></i>
        <h3 class="pt-paso-titulo">Abre</h3>
        <p>Cada sobre trae cartas al azar. El primero lo pone la casa, con once jugadores y las posiciones justas para empezar.</p>
      </li>
      <li class="pt-paso pt-paso--claro">
        <span class="pt-paso-n" aria-hidden="true">02</span>
        <i class="ph ph-list-checks pt-paso-ico" aria-hidden="true"></i>
        <h3 class="pt-paso-titulo">Alinea</h3>
        <p>Elige formación y coloca a tus once. La afinidad entre compañeros y su posición natural cambian lo que rinden.</p>
      </li>
      <li class="pt-paso">
        <span class="pt-paso-n" aria-hidden="true">03</span>
        <i class="ph ph-sword pt-paso-ico" aria-hidden="true"></i>
        <h3 class="pt-paso-titulo">Compite</h3>
        <p>Duelos contra otra gente, con cartas o monedas apostadas. Y cadenas de partidos contra la máquina si prefieres ir a tu ritmo.</p>
      </li>
    </ol>
  </section>

  <?php /* ===== 4. LAS SIETE RAREZAS ===== ← NUEVA
           El gancho del coleccionismo, y estaba completamente escondido: no se
           mencionaba en ninguna parte de la portada vieja. Con las
           probabilidades REALES de la base — enseñar las odds es lo que hace
           que una legendaria signifique algo. */ ?>
  <section class="wrap pt-seccion">
    <p class="label label--mudo">La escalera</p>
    <h2 class="pt-h2">Siete rarezas. La última existe en copias contadas</h2>

    <ul class="pt-rarezas escalona" role="list">
      <?php foreach ($rarezas as $r): ?>
        <?php
        $id = (int) $r['id_rareza'];
        // 60.00 → «60», 3.50 → «3,5», 0.25 → «0,25»
        $odds = rtrim(rtrim(number_format((float) $r['probabilidad'], 2, ',', '.'), '0'), ',');
        ?>
        <li class="pt-rareza" style="--tinta:var(--rz<?= $id ?>-ink); --tono:var(--rz<?= $id ?>);">
          <span class="pt-rareza-punto" aria-hidden="true"></span>
          <span class="pt-rareza-nombre"><?= htmlspecialchars($r['nombre']) ?></span>
          <span class="pt-rareza-odds num"><?= $odds ?>&#37;</span>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="pt-nota">
      La probabilidad de que salga cada una en un sobre. La numerada lleva su
      número de serie grabado: cuando se acaba la tirada, no hay más.
    </p>
  </section>

  <?php /* ===== 5. LAS MÁS RARAS =====
           La rejilla que ANTES abría la página. Ahora llega cuando ya se sabe
           qué se está mirando y por qué una SRF cuesta sacarla. */ ?>
  <?php if ($destacadas): ?>
  <section class="wrap pt-seccion">
    <div class="pt-cabecera-fila">
      <div>
        <p class="label label--mudo">El escaparate</p>
        <h2 class="pt-h2">Las más difíciles de sacar</h2>
      </div>
      <a class="pt-mas" href="plantilla.php?ver=todas">
        Ver todas <i class="ph ph-arrow-right" aria-hidden="true"></i>
      </a>
    </div>

    <ul class="pt-vitrina escalona" role="list">
      <?php foreach ($destacadas as $c): ?>
        <li><?php render_carta($c, ['modo' => 'arte', 'href' => 'plantilla.php?ver=todas']); ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php /* ===== 6. LOS NÚMEROS ===== ← NUEVA
           Prueba social. Las cuatro cifras salen de `estadisticasPublicas()`,
           que las cuenta de la base: no hay ni una inventada ni redondeada
           hacia arriba. */ ?>
  <section class="pt-numeros">
    <div class="wrap">
      <p class="label label--mudo">Ahora mismo</p>
      <dl class="pt-cifras escalona">
        <div class="pt-cifra">
          <dd class="cif num" data-cifra="<?= $stats['fichas'] ?>"><?= number_format($stats['fichas'], 0, ',', '.') ?></dd>
          <dt class="rot">fichas en juego</dt>
        </div>
        <div class="pt-cifra">
          <dd class="cif num" data-cifra="<?= $stats['equipos'] ?>"><?= number_format($stats['equipos'], 0, ',', '.') ?></dd>
          <dt class="rot">equipos de la liga</dt>
        </div>
        <div class="pt-cifra">
          <dd class="cif num" data-cifra="<?= $stats['repartidas'] ?>"><?= number_format($stats['repartidas'], 0, ',', '.') ?></dd>
          <dt class="rot">cartas repartidas</dt>
        </div>
        <div class="pt-cifra">
          <dd class="cif num" data-cifra="<?= $stats['duelos'] ?>"><?= number_format($stats['duelos'], 0, ',', '.') ?></dd>
          <dt class="rot">duelos jugados</dt>
        </div>
      </dl>
      <p class="pt-nota">
        El juego está en beta y se sigue añadiendo contenido. Lo que se cuece se
        cuenta en el <a href="https://discord.gg/KgEBHA87fF">Discord de la liga</a>.
      </p>
    </div>
  </section>

  <?php /* ===== 7. CIERRE =====
           La segunda y última tipografía a sangre de la página. Quien llega
           hasta aquí ya ha leído todo: solo necesita el botón otra vez. */ ?>
  <section class="pt-cierre">
    <div class="rescoldo" aria-hidden="true"></div>
    <div class="wrap pt-cierre-cuerpo">
      <h2 class="pt-display a-sangre" data-revela="0">Salta al campo</h2>
      <p class="pt-lede sube">Crear la cuenta son treinta segundos. El primer sobre es gratis.</p>
      <div class="pt-acciones sube">
        <a class="pt-cta" href="registro.php">
          Empezar
          <i class="ph ph-arrow-right" aria-hidden="true"></i>
        </a>
        <a class="pt-cta-suave" href="login.php">Ya tengo cuenta</a>
      </div>
    </div>
  </section>

</main>

<?php $pieCompleto = true; include __DIR__ . '/partials/footer.php'; ?>

</body>
</html>
