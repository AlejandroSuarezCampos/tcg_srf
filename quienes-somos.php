<?php
/**
 * QUIÉNES SOMOS — la página que dice qué es esto y qué NO es.
 *
 * No existía, y era el hueco más caro del sitio para alguien que llega de
 * fuera: la auditoría del 2026-08-26 midió que un fan de Inazuma que aterriza
 * aquí sin conocer la liga no tiene forma de saber si esto es un producto
 * oficial de Level-5 o un proyecto de aficionados. Eso hunde la confianza —la
 * pata de E-E-A-T que más pesa— y es lo primero que pregunta cualquiera.
 *
 * También es la página que sostiene el `sameAs` del JSON-LD: es donde un
 * buscador ata este dominio con el Discord, el Instagram y la web de la liga.
 */
session_start();

$paginaTitulo = 'Qué es Superliga Frontier TCG';
$paginaDesc   = 'Un juego de cartas hecho por aficionados con los jugadores de la Superliga Frontier. Gratis, sin ánimo de lucro y sin relación con Level-5.';
include __DIR__ . '/partials/head.php';
require_once __DIR__ . '/partials/seo.php';
seoCachePublica(3600);

$activePage = '';
include __DIR__ . '/navbar.php';
?>

<main id="contenido" class="seccion wrap">

  <header class="seccion-head">
    <p class="seccion-tag">Sobre el proyecto</p>
    <h1>Qué es Superliga Frontier TCG</h1>
  </header>

  <?php /* Este primer bloque está escrito para poder citarse entero. Responde
           la pregunta completa —qué es, de quién, cuánto cuesta, qué se hace—
           en un solo párrafo autocontenido, sin depender de lo que hay antes
           ni después. Es lo que un buscador con IA puede tomar tal cual como
           respuesta, y lo que el sitio no tenía en ninguna de sus páginas. */ ?>
  <section class="panel">
    <p class="t-body">
      <b>Superliga Frontier TCG</b> es un juego de cartas coleccionables gratuito
      que se juega en el navegador y está hecho por aficionados. Sus cartas
      representan a los jugadores, presidentes, entrenadores y escudos reales de
      la <b>Superliga Frontier</b>, una liga de creadores de contenido del
      videojuego <i>Inazuma Eleven: Victory Road</i>. Cada ficha tiene su
      ilustración, su equipo, su posición y sus estadísticas de ataque, defensa y
      técnica. Se empieza con un sobre gratuito de once jugadores, se monta una
      alineación con una formación, y con ella se disputan duelos contra otras
      personas o cadenas de partidos contra la máquina. Hay siete rarezas, un
      mercado interno donde se compran y venden cartas con monedas del propio
      juego, y misiones diarias. No se paga dinero real en ningún momento: no
      hay compras, ni suscripción, ni publicidad.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Un proyecto de fans, no oficial</h2></div>
    <p class="t-body">
      Este sitio <b>no es un producto oficial</b> y no está afiliado, patrocinado
      ni respaldado por <b>Level-5</b>, por la marca <i>Inazuma Eleven</i> ni por
      ninguna de sus empresas asociadas. Es un proyecto de aficionados, hecho sin
      ánimo de lucro y por gusto, dentro de la comunidad de la Superliga Frontier.
    </p>
    <p class="t-body">
      Los nombres, escudos e ilustraciones de personajes de <i>Inazuma Eleven</i>
      pertenecen a sus titulares. Se usan aquí de forma no comercial y por
      referencia a una competición de la comunidad.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Las cartas son personas reales</h2></div>
    <p class="t-body">
      Buena parte del catálogo son fichas de personas de carne y hueso: los
      creadores, presidentes y jugadores que participan en la Superliga Frontier.
      Aparecen aquí con el nombre o el alias con el que compiten en la liga.
    </p>
    <p class="t-body">
      Si sales en una carta y prefieres no aparecer, o quieres que se cambie tu
      ilustración o tu nombre, <b>escríbenos y se retira</b>. No hace falta
      explicar por qué. El contacto está en el
      <a href="legal">aviso legal</a> y en el
      <a href="https://discord.gg/KgEBHA87fF" rel="noopener">Discord de la liga</a>.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Dónde está la comunidad</h2></div>
    <p class="t-body">
      El juego es una pieza de algo más grande. La liga vive en
      <a href="https://superligafrontier.es" rel="noopener">superligafrontier.es</a>,
      y el día a día está en
      <a href="https://discord.gg/KgEBHA87fF" rel="noopener">Discord</a>,
      <a href="https://www.instagram.com/superligafrontier/" rel="noopener">Instagram</a> y
      <a href="https://x.com/supligafrontier" rel="noopener">X</a>.
    </p>
    <p>
      <a class="btn btn-primary" href="acceso?modo=crear">Abre tu primer sobre gratis</a>
    </p>
  </section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
