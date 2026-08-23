<?php
/**
 * TUTORIAL DE BIENVENIDA — marcado y guion.
 *
 * Lo incluye partials/footer.php en todas las páginas, y no pinta NADA salvo
 * que el usuario esté de verdad a mitad del tutorial. Así ninguna pantalla
 * tiene que acordarse de incluirlo ni de comprobar nada.
 *
 * El guion (los doce pasos) vive en Tcg::TUTORIAL_PASOS. Aquí solo se elige
 * qué se le manda al navegador y se pinta la caja vacía que el JS rellena.
 *
 * Lo que SÍ se decide en servidor y no se le pregunta al cliente:
 *   · en qué paso va cada uno,
 *   · y si ha cumplido los dos requisitos (mazo titular y partido jugado).
 * Son las dos puertas del tutorial, y una puerta que el navegador puede abrir
 * solo no es una puerta.
 */

if (empty($_SESSION['id_usuario'])) { return; }

$tutorialPaso = $db->tutorialPaso($_SESSION['id_usuario']);
if (in_array($tutorialPaso, Tcg::TUTORIAL_TERMINADO, true)) { return; }

/* DENTRO DE UN PARTIDO, EL TUTORIAL NO APARECE.
   El propio tutorial te manda al amistoso de prueba, y al llegar se plantaba
   con su velo encima del partido y un botón de "Ir a Inicio" — te mandaba a
   jugar y acto seguido te tapaba lo que te acababa de mandar a hacer. El
   partido es a pantalla completa y con decisiones a contrarreloj: aquí lo
   único correcto es apartarse. Al volver, el requisito ya está cumplido y el
   paso se desbloquea solo. */
if (basename($_SERVER['SCRIPT_NAME']) === 'duelo.php') { return; }

$tutorialLogros = $db->tutorialLogros((int) $_SESSION['id_usuario']);
$tutorialBase   = $base ?? '';

/* Los pasos viajan con su URL ya montada: el JS no tiene que saber en qué
   carpeta está la página actual, que en el panel y en la raíz no es la misma. */
$tutorialPasos = array_map(function ($p) use ($tutorialBase) {
    $p['url'] = $tutorialBase . $p['destino'];
    return $p;
}, Tcg::TUTORIAL_PASOS);
?>

<div class="tutorial" id="tutorial" hidden
     role="dialog" aria-modal="true" aria-labelledby="tutorialTitulo">

  <?php /* El velo oscurece la página y RECORTA un hueco alrededor de lo que se
           está señalando. Es un solo elemento con `clip-path`, no cuatro
           rectángulos: con cuatro, las esquinas nunca acaban de cuadrar al
           redimensionar. */ ?>
  <div class="tutorial-velo" id="tutorialVelo"></div>

  <?php /* El anillo del foco va aparte del velo porque tiene borde y sombra, y
           un `clip-path` no puede llevar ninguna de las dos. */ ?>
  <div class="tutorial-foco" id="tutorialFoco" aria-hidden="true"></div>

  <div class="tutorial-globo" id="tutorialGlobo">
    <p class="tutorial-cuenta">
      Paso <span id="tutorialNumero">1</span> de <?= count($tutorialPasos) ?>
    </p>
    <h2 id="tutorialTitulo"></h2>
    <p id="tutorialTexto"></p>

    <?php /* Aviso de los pasos que exigen haber hecho algo. Se enseña solo
             mientras no está hecho, y dice qué falta. */ ?>
    <p class="tutorial-pendiente" id="tutorialPendiente" hidden></p>

    <?php /* Botón de acción del paso: solo lo tienen los que hacen algo por su
             cuenta, como crear el partido de prueba. */ ?>
    <button type="button" class="btn btn-primary btn-sm tutorial-accion" id="tutorialAccion" hidden></button>

    <div class="tutorial-botones">
      <button type="button" class="btn btn-plano btn-sm" id="tutorialSaltar">
        Saltar el tutorial
      </button>
      <span class="tutorial-botones-avance">
        <button type="button" class="btn btn-ghost btn-sm" id="tutorialAtras">Atrás</button>
        <button type="button" class="btn btn-primary btn-sm" id="tutorialSiguiente">Siguiente</button>
      </span>
    </div>
  </div>
</div>

<script>
window.TUTORIAL = {
  paso: <?= json_encode($tutorialPaso) ?>,
  pasos: <?= json_encode($tutorialPasos) ?>,
  logros: <?= json_encode($tutorialLogros) ?>,
  <?php /* La pantalla se saca del NOMBRE DEL ARCHIVO, no de `$activePage`.
           `$activePage` es para pintar el enlace activo de la barra, y ahí
           varias pantallas comparten clave a propósito: configuracion.php se
           marca como 'perfil'. Para el tutorial eso significaría que un paso de
           ajustes se da por visto estando en el perfil, y nunca llevaría a
           donde tiene que llevar. El nombre del archivo es uno por pantalla. */ ?>
  pagina: <?= json_encode(basename($_SERVER['SCRIPT_NAME'], '.php')) ?>,
  url: <?= json_encode($tutorialBase . 'assets/ajax/tutorial.php') ?>
};
</script>
<?= assetScript($tutorialBase, 'assets/js/tutorial.js') ?>
