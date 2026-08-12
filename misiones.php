<?php
session_start();
require_once __DIR__ . '/db/conexion.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = $_SESSION['id_usuario'];

$mensaje = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reclamar') {
    $res = $db->reclamarMision((int) $_POST['id_mision'], $id_usuario);
    if ($res['ok']) {
        header('Location: misiones.php?reclamada=' . $res['recompensa']);
        exit;
    }
    $error = $res['error'];
}

if (isset($_GET['reclamada'])) {
    $mensaje = 'Misión reclamada: +' . number_format((int) $_GET['reclamada'], 0, ',', '.') . ' monedas.';
}

$misiones   = $db->listarMisionesConProgreso($id_usuario);
$reinicios  = $db->proximosReinicios();

// Un solo recorrido, tres cubos: es la misma agrupación que ya hace el motor
// por `ciclo`, solo que aquí decide en qué apartado se pinta cada tarjeta.
$porCiclo = ['diaria' => [], 'semanal' => [], 'unica' => []];
foreach ($misiones as $m) {
    $porCiclo[$m['ciclo']][] = $m;
}

$apartados = [
    'diaria'  => ['titulo' => 'Diarias',  'ico' => 'ph-sun',      'vacio' => 'No hay misiones diarias activas.'],
    'semanal' => ['titulo' => 'Semanales', 'ico' => 'ph-calendar', 'vacio' => 'No hay misiones semanales activas.'],
    'unica'   => ['titulo' => 'Objetivos', 'ico' => 'ph-target',   'vacio' => 'No hay objetivos activos.'],
];

function formatoSegundos(int $s): string {
    $s = max(0, $s);
    $dias = intdiv($s, 86400);
    $horas = intdiv($s % 86400, 3600);
    $min   = intdiv($s % 3600, 60);
    $seg   = $s % 60;
    return $dias > 0
        ? sprintf('%dd %02dh %02dm %02ds', $dias, $horas, $min, $seg)
        : sprintf('%02dh %02dm %02ds', $horas, $min, $seg);
}

$paginaTitulo = 'Misiones';
$paginaDesc   = 'Misiones diarias, semanales y objetivos de la Superliga Frontier.';
include __DIR__ . '/partials/head.php';

$activePage = 'misiones';
include __DIR__ . '/navbar.php';
?>

<header class="cabecera">
  <div class="linea-campo" aria-hidden="true"></div>
  <div class="wrap cabecera-contenido">
    <h1>Misiones</h1>
    <p>Diarias y semanales se reinician solas; los objetivos se reclaman una sola vez.</p>
  </div>
</header>

<main id="contenido" class="seccion wrap">

  <?php if ($mensaje !== ''): ?>
    <p class="alerta alerta-success" role="status"><?= htmlspecialchars($mensaje) ?></p>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <p class="alerta alerta-danger" role="alert"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <?php if (empty($misiones)): ?>
    <div class="vacio">
      <span class="vacio-ico"><i class="ph ph-target" aria-hidden="true"></i></span>
      <h3>No hay misiones activas</h3>
      <p>Vuelve más adelante.</p>
    </div>
  <?php else: ?>

    <div class="tabs" role="tablist" aria-label="Tipos de misión">
      <?php foreach ($apartados as $ciclo => $info): ?>
      <button class="tab" role="tab" id="tab-<?= $ciclo ?>" aria-controls="panel-<?= $ciclo ?>"
              aria-selected="<?= $ciclo === 'diaria' ? 'true' : 'false' ?>"><?= $info['titulo'] ?></button>
      <?php endforeach; ?>
    </div>

    <?php foreach ($apartados as $ciclo => $info): ?>
    <div class="tab-panel" role="tabpanel" id="panel-<?= $ciclo ?>" aria-labelledby="tab-<?= $ciclo ?>" tabindex="0"
         style="padding-top:var(--space-6);"<?= $ciclo === 'diaria' ? '' : ' hidden' ?>>

      <?php if ($ciclo === 'diaria' || $ciclo === 'semanal'): ?>
      <span class="mision-cuenta-atras" data-ciclo="<?= $ciclo ?>" data-segundos="<?= (int) $reinicios[$ciclo] ?>" role="timer" aria-live="off">
        <i class="ph ph-hourglass" aria-hidden="true"></i>
        Se reinicia en <b class="mono mision-cuenta-atras-valor"><?= htmlspecialchars(formatoSegundos($reinicios[$ciclo])) ?></b>
      </span>
      <?php endif; ?>

      <?php if (empty($porCiclo[$ciclo])): ?>
        <div class="vacio" style="margin-top:var(--space-6);">
          <span class="vacio-ico"><i class="ph <?= $info['ico'] ?>" aria-hidden="true"></i></span>
          <h3><?= htmlspecialchars($info['vacio']) ?></h3>
        </div>
      <?php else: ?>
        <div class="stack stack-4" style="margin-top:var(--space-6);">
          <?php foreach ($porCiclo[$ciclo] as $m): ?>
            <?php $pct = $m['objetivo'] > 0 ? min(100, (int) round($m['progreso'] / $m['objetivo'] * 100)) : 100; ?>
            <section class="panel">
              <div class="panel-head">
                <h3 class="panel-titulo"><?= htmlspecialchars($m['nombre']) ?></h3>
                <?php if ($m['reclamada']): ?>
                  <span class="pastilla pastilla-on">Reclamada</span>
                <?php elseif ($m['completada']): ?>
                  <span class="pastilla pastilla-warn">Completada</span>
                <?php endif; ?>
              </div>

              <p class="t-body-sm t-dim" style="margin-bottom:var(--space-4);">
                <?= htmlspecialchars($m['descripcion']) ?>
              </p>

              <div class="progreso" style="margin-bottom:var(--space-4);">
                <div class="progreso-riel">
                  <div class="progreso-relleno" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="progreso-label"><?= (int) $m['progreso'] ?> / <?= (int) $m['objetivo'] ?></span>
              </div>

              <div style="display:flex; align-items:center; justify-content:space-between;">
                <span class="mono"><?= number_format((int) $m['recompensa_monedas'], 0, ',', '.') ?> monedas</span>

                <?php if ($m['reclamada']): ?>
                  <button type="button" class="btn btn-plano btn-sm" disabled>Ya reclamada</button>
                <?php elseif ($m['completada']): ?>
                  <form method="POST">
                    <input type="hidden" name="accion" value="reclamar">
                    <input type="hidden" name="id_mision" value="<?= $m['id_mision'] ?>">
                    <button type="submit" class="btn btn-primary btn-sm">Reclamar</button>
                  </form>
                <?php else: ?>
                  <button type="button" class="btn btn-plano btn-sm" disabled>Todavía no</button>
                <?php endif; ?>
              </div>
            </section>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

  <?php endif; ?>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

<?= assetScript($base ?? '', 'assets/js/misiones.js') ?>

</body>
</html>
