<?php

session_start();

$paginaTitulo = 'Aviso legal';
$paginaDesc   = 'Quién responde de Superliga Frontier TCG, condiciones de uso y cómo pedir que se retire una carta.';
include __DIR__ . '/partials/head.php';
require_once __DIR__ . '/partials/seo.php';
seoCachePublica(3600);

$activePage = '';
include __DIR__ . '/navbar.php';
?>

<main id="contenido" class="seccion wrap">

  <header class="seccion-head">
    <p class="seccion-tag">Legal</p>
    <h1>Aviso legal</h1>
  </header>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Quién responde de este sitio</h2></div>
    <ul class="t-body">
      <li>Responsable: Franshu &amp; Lulu Lulez</li>
      <li>Correo de contacto: <a href="mailto:soporte@tcgfrontier.es">soporte@tcgfrontier.es</a></li>
      <li>Sitio web: <b>tcgfrontier.es</b></li>
      <li>Actividad: juego de cartas coleccionables en línea, gratuito y sin ánimo de lucro.</li>
    </ul>
    <p class="t-body-sm t-dim">
      No hay actividad comercial: no se vende nada, no hay publicidad y no se
      cobra por ningún servicio.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Proyecto de aficionados</h2></div>
    <p class="t-body">
      Superliga Frontier TCG <b>no es un producto oficial</b> y no está afiliado,
      patrocinado ni respaldado por Level-5, por la marca <i>Inazuma Eleven</i>
      ni por ninguna empresa asociada. Los nombres, escudos e ilustraciones de
      personajes pertenecen a sus titulares y se usan de forma no comercial.
      Algunas cartas también pueden referenciar otras marcas registradas (por
      ejemplo, Disney o Nike) por motivos puramente estéticos o de fan-art;
      pertenecen a sus respectivos titulares y su aparición no implica
      afiliación ni patrocinio.
      Más contexto en <a href="quienes-somos">qué es este proyecto</a>.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Cartas de personas reales</h2></div>
    <p class="t-body">
      Muchas fichas representan a personas identificables de la comunidad de la
      Superliga Frontier, con el nombre o alias con el que compiten.
    </p>
    <p class="t-body">
      <b>Si sales en una carta y quieres que se retire, se retira.</b> Escribe a
      <a href="mailto:soporte@tcgfrontier.es">soporte@tcgfrontier.es</a> o dilo en el
      <a href="https://discord.gg/KgEBHA87fF" rel="noopener">Discord de la liga</a>,
      indicando qué carta es. No hace falta motivar la petición ni justificar
      nada. Lo mismo vale para cambiar una ilustración o corregir un nombre.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Condiciones de uso</h2></div>
    <ul class="t-body">
      <li>La cuenta es personal. No se comparte ni se cede.</li>
      <li>
        Las monedas, cartas y objetos del juego <b>no tienen valor económico</b>,
        no son canjeables por dinero y no se pueden vender fuera del juego.
      </li>
      <li>
        No se permite usar programas automáticos, fallos del juego ni cuentas
        múltiples para conseguir ventaja. Puede suponer el cierre de la cuenta.
      </li>
      <li>
        Al ser un proyecto de aficionados, el servicio se presta <b>tal cual</b>,
        sin garantía de disponibilidad. Puede haber caídas, cambios de reglas o
        reinicios de temporada.
      </li>
      <li>
        Puedes pedir que se borre tu cuenta cuando quieras, escribiendo a
        <a href="mailto:soporte@tcgfrontier.es">soporte@tcgfrontier.es</a>.
      </li>
    </ul>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Datos personales</h2></div>
    <p class="t-body">
      Qué se guarda, para qué y cómo borrarlo está en la
      <a href="privacidad">política de privacidad</a>.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Ley aplicable</h2></div>
    <p class="t-body">
      Se aplica la legislación española.
      Cualquier discrepancia se intentará resolver primero por el correo de
      contacto de arriba.
    </p>
    <p class="t-body-sm t-dim">
      Última actualización: <?= date('j') ?> de <?= ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'][(int) date('n')] ?> de <?= date('Y') ?>.
    </p>
  </section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
