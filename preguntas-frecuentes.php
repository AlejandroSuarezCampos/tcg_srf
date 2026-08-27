<?php
/**
 * PREGUNTAS FRECUENTES.
 *
 * Las cinco primeras no están elegidas a ojo: son literalmente las que la
 * auditoría del 2026-08-26 identificó como «preguntas reales que el sitio no
 * responde en ninguna página» — qué es esto, si es gratis, si es oficial,
 * cuántas cartas hay y qué rarezas existen. Cada una se responde entera y sin
 * depender de las demás, que es lo que hace que se puedan citar.
 *
 * ⚠️ NO LLEVA MARCADO `FAQPage`, Y ES A PROPÓSITO.
 *    Google retiró el resultado enriquecido de FAQ en 2023 para sitios que no
 *    sean administraciones o webs sanitarias: hoy no pinta nada distinto en los
 *    resultados. Añadirlo sería marcado que no sirve para nada y una cosa más
 *    que mantener sincronizada con el texto. La decisión y el motivo están en
 *    `tcgfrontier.es-audit/findings/schema.md`.
 *
 * Las cifras que aparecen se leen de la base, no se escriben aquí.
 */
session_start();

require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/partials/seo.php';

$totalFichas = $db->contarCromosTotales(0);

/* ⚠️ SE ESCAPA CADA NOMBRE ANTES DE UNIRLOS, NUNCA DESPUÉS.
   Aquí había un `htmlspecialchars(implode('</b>, <b>', …))`, que escapa también
   los `<b>` que uno acaba de meter como separador: en pantalla salía
   «Común</b>, <b>Poco común</b>, <b>Raro…» con las etiquetas a la vista.
   Primero se escapa el dato, luego se le añade el marcado. */
$rarezas = array_map(
    fn($r) => htmlspecialchars($r['nombre']),
    $db->listarRarezas()
);
$ultimaRareza = array_pop($rarezas);
$listaRarezas = '<b>' . implode('</b>, <b>', $rarezas) . '</b> y <b>' . $ultimaRareza . '</b>';

$paginaTitulo = 'Preguntas frecuentes';
$paginaDesc   = '¿Es gratis? ¿Es oficial de Inazuma Eleven? ¿Cuántas cartas hay? Las dudas más comunes sobre Superliga Frontier TCG, respondidas.';

seoCachePublica(3600);
include __DIR__ . '/partials/head.php';

$activePage = '';
include __DIR__ . '/navbar.php';

/**
 * Una pregunta y su respuesta.
 *
 * El encabezado ES la pregunta, tal como la escribiría alguien en un buscador.
 * Un `<h2>` que diga «Precio» no lo encuentra nadie; uno que diga «¿Cuánto
 * cuesta jugar?» sí.
 */
function pregunta(string $titulo, string $cuerpo): void { ?>
  <section class="panel faq-item">
    <div class="panel-head"><h2 class="panel-titulo"><?= $titulo ?></h2></div>
    <?= $cuerpo ?>
  </section>
<?php }
?>

<main id="contenido" class="seccion wrap">

  <header class="seccion-head">
    <p class="seccion-tag">Dudas</p>
    <h1>Preguntas frecuentes</h1>
  </header>

  <?php pregunta('¿Qué es Superliga Frontier TCG?', '
    <p class="t-body">
      Es un juego de cartas coleccionables gratuito que se juega en el navegador
      y está hecho por aficionados. Sus cartas representan a los jugadores,
      presidentes, entrenadores y escudos reales de la <b>Superliga Frontier</b>,
      una liga de creadores de contenido del videojuego <i>Inazuma Eleven:
      Victory Road</i>. Cada ficha tiene su ilustración, su equipo, su posición y
      sus estadísticas de ataque, defensa y técnica. Se empieza con un sobre
      gratuito de once jugadores, se monta una alineación eligiendo formación, y
      con ella se disputan duelos contra otras personas o cadenas de partidos
      contra la máquina. Hay siete rarezas, un mercado interno donde se compran y
      venden cartas con monedas del propio juego, y misiones que se renuevan.
    </p>
    <p class="t-body-sm t-dim">
      El detalle de cada mecánica está en <a href="como-se-juega">cómo se juega</a>.
    </p>
  '); ?>

  <?php pregunta('¿Es gratis? ¿Hay que pagar algo en algún momento?', '
    <p class="t-body">
      Es <b>gratis de principio a fin</b>. No hay compras dentro del juego, ni
      suscripción, ni pases de temporada, ni publicidad. La moneda del juego
      —con la que se compran los sobres y las cartas del mercado— <b>solo se gana
      jugando</b>: ganando duelos, avanzando en las cadenas, cumpliendo misiones
      y vendiendo cartas repetidas. No existe ninguna forma de comprarla con
      dinero real, y las cartas y monedas del juego no tienen valor económico
      fuera de él ni se pueden canjear por dinero. Crear la cuenta tampoco cuesta
      nada y no pide datos de pago: hacen falta un nombre y una contraseña, y ya.
      El primer sobre, con once jugadores, lo regala el juego al registrarse.
    </p>
  '); ?>

  <?php pregunta('¿Es un juego oficial de Inazuma Eleven o de Level-5?', '
    <p class="t-body">
      <b>No.</b> Superliga Frontier TCG es un proyecto de aficionados, sin ánimo
      de lucro, y <b>no está afiliado, patrocinado ni respaldado por Level-5</b>,
      por la marca <i>Inazuma Eleven</i> ni por ninguna de sus empresas
      asociadas. Nació dentro de la comunidad de la Superliga Frontier, una liga
      de creadores de contenido que compite en <i>Inazuma Eleven: Victory Road</i>,
      y está hecho por y para esa comunidad. Los nombres, escudos e ilustraciones
      de personajes de la franquicia pertenecen a sus titulares y se usan aquí de
      forma no comercial y por referencia. Si eres titular de derechos y quieres
      que se retire algo, basta con escribir y se retira.
    </p>
    <p class="t-body-sm t-dim">
      Más contexto en <a href="quienes-somos">qué es este proyecto</a> y en el
      <a href="legal">aviso legal</a>.
    </p>
  '); ?>

  <?php pregunta('¿Cuántas cartas hay?', '
    <p class="t-body">
      Ahora mismo hay <b>' . number_format($totalFichas, 0, ',', '.') . ' fichas</b>
      en el catálogo público, y se pueden consultar todas
      <b>sin registrarse</b> desde la página de <a href="plantilla">todas las
      fichas</a>. Cada una tiene su propia página con sus estadísticas, su equipo
      y sus compañeros. El número sube: entran cartas nuevas con cada expansión y
      con los eventos de la liga. Además de esas, hay <b>cartas exclusivas de las
      cadenas</b> que no aparecen en el catálogo hasta que se consiguen — son
      secretas a propósito, para que encontrarlas tenga gracia. Así que el
      catálogo público es el suelo, no el techo.
    </p>
  '); ?>

  <?php pregunta('¿Qué rarezas existen y cuál es la más difícil?', '
    <p class="t-body">
      Hay <b>siete</b>, de la más corriente a la más rara:
      ' . $listaRarezas . '.
      La rareza decide dos cosas a la vez: lo difícil que es que la carta salga de
      un sobre y lo altas que son sus estadísticas, así que una carta rara es
      además una carta buena. La más difícil de todas es <b>Numerada</b>, que
      tiene una particularidad que ninguna otra tiene: <b>existe en copias
      contadas</b>. Cuando se reparten todas las que hay, deja de salir por muchos
      sobres que se abran. Las probabilidades exactas de cada rareza, con el
      «sale una de cada tantas», están publicadas en la
      <a href="como-se-juega#rarezas">guía</a>.
    </p>
  '); ?>

  <?php pregunta('¿Puedo salir yo en una carta?', '
    <p class="t-body">
      Las cartas son de gente que participa en la <b>Superliga Frontier</b>:
      jugadores, presidentes de equipo, entrenadores y gerentes. No hay forma de
      pedir una carta desde el juego; salen de la liga. Lo que sí puedes hacer es
      pasarte por el <a href="https://discord.gg/KgEBHA87fF" rel="noopener">Discord
      de la liga</a> y participar.
    </p>
    <p class="t-body">
      Y al revés: <b>si ya sales en una carta y prefieres no aparecer, se
      retira</b>. No hace falta dar explicaciones. Lo mismo si quieres cambiar la
      ilustración o corregir tu nombre. El contacto está en el
      <a href="legal">aviso legal</a>.
    </p>
  '); ?>

  <?php pregunta('¿Qué datos guardáis de mí?', '
    <p class="t-body">
      Un <b>nombre de usuario</b> y una <b>contraseña cifrada</b>, y nada más. No
      se pide correo electrónico, ni edad, ni nombre real, ni teléfono, ni datos
      de pago. Se guarda también tu partida —cartas, monedas, mazos y duelos—,
      que es lo que hace que el juego sea un juego. No hay Google Analytics, ni
      píxeles de seguimiento, ni cookies de terceros: la única cookie es la de la
      sesión, que es la que te mantiene dentro mientras juegas. Nada se comparte
      con nadie y nada se usa para publicidad. Puedes pedir que se borre tu cuenta
      cuando quieras.
    </p>
    <p class="t-body-sm t-dim">
      El detalle completo está en la <a href="privacidad">política de privacidad</a>.
    </p>
  '); ?>

  <?php pregunta('¿Puedo perder mis cartas?', '
    <p class="t-body">
      Solo si las apuestas. En un duelo con cartas, las dos partes ponen el mismo
      número de cartas de la misma rareza y el ganador se lleva el bote: ahí sí se
      pueden perder. Fuera de eso no desaparece nada por su cuenta.
    </p>
    <p class="t-body">
      Para evitar sustos hay un <b>candado</b>: puedes proteger cualquier carta
      desde tu plantilla y, mientras esté protegida, queda fuera del mercado y
      fuera de las apuestas. Es lo primero que conviene hacer con las que no
      piensas soltar. Además, una carta que ya está comprometida en un duelo
      abierto no se puede vender ni meter en otro mazo hasta que ese duelo
      termine.
    </p>
  '); ?>

  <?php pregunta('¿Necesito jugar todos los días?', '
    <p class="t-body">
      No. No hay rachas que se pierdan, ni energía que se agote, ni nada que
      caduque por no entrar. Las <b>cadenas</b> se juegan contra la máquina cuando
      a uno le viene bien, sin esperar a que haya gente conectada, y un partido a
      medias se cierra solo al volver. Las misiones se van cumpliendo mientras
      juegas, no exigen entrar a una hora. El juego está pensado para ratos
      sueltos.
    </p>
  '); ?>

  <?php pregunta('La web está en beta, ¿voy a perder mi progreso?', '
    <p class="t-body">
      Puede pasar, y por eso está avisado en el pie de todas las páginas. El
      contenido se sigue revisando y añadiendo, y <b>las cuentas todavía no son
      las definitivas</b>: puede haber reinicios mientras dure la beta. Si te
      encuentras un fallo, contarlo por el
      <a href="https://discord.gg/KgEBHA87fF" rel="noopener">Discord</a> es la
      forma más rápida de que se arregle.
    </p>
  '); ?>

  <section class="panel">
    <p class="t-body">
      ¿No está tu pregunta? Pregunta en el
      <a href="https://discord.gg/KgEBHA87fF" rel="noopener">Discord de la liga</a>,
      que es donde está la gente.
    </p>
    <p class="fi-acciones">
      <a class="btn btn-primary" href="acceso?modo=crear">Crear cuenta gratis</a>
      <a class="btn btn-plano" href="como-se-juega">Leer cómo se juega</a>
    </p>
  </section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
