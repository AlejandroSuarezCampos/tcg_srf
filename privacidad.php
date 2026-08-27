<?php

session_start();

$paginaTitulo = 'Privacidad';
$paginaDesc   = 'Qué datos guarda Superliga Frontier TCG, para qué, cuánto tiempo y cómo pedir que se borren.';
include __DIR__ . '/partials/head.php';
require_once __DIR__ . '/partials/seo.php';
seoCachePublica(3600);

$activePage = '';
include __DIR__ . '/navbar.php';
?>

<main id="contenido" class="seccion wrap">

  <header class="seccion-head">
    <p class="seccion-tag">Legal</p>
    <h1>Política de privacidad</h1>
  </header>

  <?php /* El resumen va primero y en una frase, porque es la respuesta que
           busca el 95 % de quien abre esta página. */ ?>
  <section class="panel">
    <p class="t-body">
      Resumen: para jugar hacen falta <b>un nombre y una contraseña</b>, y nada
      más. No se pide correo, ni edad, ni nombre real. No hay analítica, ni
      publicidad, ni botones de redes que espíen. No se comparte nada con nadie.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Quién trata los datos</h2></div>
    <p class="t-body">
      Responsable: Franshu &amp; Lulu Lulez.
      Contacto: <a href="mailto:soporte@tcgfrontier.es">soporte@tcgfrontier.es</a>.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Qué se guarda, y por qué</h2></div>
    <ul class="t-body">
      <li>
        <b>Tu nombre de usuario.</b> Es tu identidad dentro del juego y lo ven
        los demás en el mercado, los duelos y las clasificaciones.
      </li>
      <li>
        <b>Tu contraseña, cifrada.</b> Se guarda con un resumen criptográfico
        (<code>password_hash</code>): ni siquiera quien administra el sitio puede
        leerla.
      </li>
      <li>
        <b>Tu foto de perfil</b>, si subes una.
      </li>
      <li>
        <b>Tu partida:</b> cartas, monedas, mazos, duelos, misiones y compras del
        mercado. Sin esto no hay juego.
      </li>
      <li>
        <b>Los intentos de acceso fallidos</b>, con la dirección IP y durante un
        rato. Es lo que frena a quien intenta adivinar contraseñas a lo bruto.
        Se borran solos.
      </li>
    </ul>
    <p class="t-body">
      No se pide ni se guarda correo electrónico, teléfono, nombre real, fecha de
      nacimiento ni forma de pago. No hay pagos.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Cookies</h2></div>
    <p class="t-body">
      Una, <code>PHPSESSID</code>, y es la que mantiene tu sesión abierta
      mientras juegas. Es técnica e imprescindible, así que no lleva banner de
      consentimiento: sin ella no se puede iniciar sesión. Se borra al cerrar el
      navegador o al salir de la cuenta.
    </p>
    <p class="t-body">
      El navegador también guarda tu preferencia de animaciones
      (<code>srf-animaciones</code>) en tu propio equipo. Eso no sale de tu
      dispositivo y no llega al servidor.
    </p>
    <p class="t-body">
      <b>No hay Google Analytics</b>, ni píxeles, ni cookies de terceros.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Con quién se comparte</h2></div>
    <p class="t-body">
      Con nadie. Los datos viven en el servidor donde está alojada la web,
      que actúa solo como quien guarda el disco. No se venden, no se ceden y no
      se usan para publicidad.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Cuánto tiempo</h2></div>
    <p class="t-body">
      Mientras tengas la cuenta. Cuando pides que se borre, se borra la cuenta y
      su partida. Los registros de intentos de acceso caducan solos en cuestión
      de minutos.
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Tus derechos</h2></div>
    <p class="t-body">
      Puedes pedir ver lo que hay sobre ti, corregirlo, llevártelo o borrarlo.
      Basta con escribir a <a href="mailto:soporte@tcgfrontier.es">soporte@tcgfrontier.es</a> desde
      dentro del juego o por el Discord, diciendo tu nombre de usuario.
    </p>
    <p class="t-body">
      Si crees que algo no se está haciendo bien, puedes reclamar ante la
      autoridad de protección de datos que te corresponda
      (<a href="https://www.aepd.es" rel="noopener">www.aepd.es</a>).
    </p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2 class="panel-titulo">Menores</h2></div>
    <p class="t-body">
      El juego no pide edad y no está dirigido a menores de
      14 años. Si eres
      madre, padre o tutor y quieres que se borre la cuenta de un menor,
      escríbenos y se borra.
    </p>
    <p class="t-body-sm t-dim">
      Última actualización: <?= date('j') ?> de <?= ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'][(int) date('n')] ?> de <?= date('Y') ?>.
    </p>
  </section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
