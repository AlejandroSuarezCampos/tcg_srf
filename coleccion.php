<?php
require_once __DIR__ . '.\db\conexion.php';

/**
 * Maqueta visual con datos de ejemplo. Cuando tengas tus métodos en Tcg
 * (consultas.php), sustituye estos arrays por tus llamadas reales, p. ej.:
 *   $equipos    = $db->listarEquipos();
 *   $rarezas    = $db->listarRarezas();
 *   $expansiones= $db->listarExpansiones();
 *   $cromos     = $db->listarColeccionFiltrada($id_usuario, $filtros);
 */
$equipos = ['Raimon FC', 'Zeus FC', 'Diamond Dust', 'Big Waves', 'Occult FC'];
$rarezas = ['Legendaria', 'Épica', 'Rara', 'Común'];
$expansiones = ['Tormenta de Raimon', 'Llamas de Zeus', 'Hielo Eterno', 'Raíces del Bosque'];

$cromos = [
    ['nombre'=>'Mark Slate',    'equipo'=>'Raimon FC',   'rareza'=>'Legendaria', 'clave'=>'r-legend', 'bloqueada'=>true],
    ['nombre'=>'Axel Blaze',    'equipo'=>'Zeus FC',     'rareza'=>'Épica',      'clave'=>'r-epic',   'bloqueada'=>false],
    ['nombre'=>'Jude Sharp',    'equipo'=>'Zeus FC',     'rareza'=>'Rara',       'clave'=>'r-rare',   'bloqueada'=>false],
    ['nombre'=>'Shawn Frost',   'equipo'=>'Diamond Dust','rareza'=>'Épica',      'clave'=>'r-epic',   'bloqueada'=>false],
    ['nombre'=>'Xavier Foster', 'equipo'=>'Big Waves',   'rareza'=>'Épica',      'clave'=>'r-epic',   'bloqueada'=>true],
    ['nombre'=>'Mark Evans',    'equipo'=>'Raimon FC',   'rareza'=>'Legendaria', 'clave'=>'r-legend', 'bloqueada'=>false],
    ['nombre'=>'Kevin Dragonfly','equipo'=>'Occult FC',  'rareza'=>'Rara',       'clave'=>'r-rare',   'bloqueada'=>false],
    ['nombre'=>'Erwin Kruger',  'equipo'=>'Zeus FC',     'rareza'=>'Común',      'clave'=>'r-rare',   'bloqueada'=>false],
];
$totalColeccion = 86;
$totalObtenidas = count($cromos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi colección — Inazuma TCG</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav id="nav">
  <div class="logo">INAZUMA<span>·</span>TCG</div>
  <div class="nav-links">
    <a href="landing.php">Inicio</a>
    <a href="coleccion.php" class="active">Colección</a>
    <a href="mercado.php">Mercado</a>
    <a href="perfil.php">Perfil</a>
  </div>
  <div class="user-chip">
    <div class="avatar-sm">KS</div>
    <span class="coins">1 240</span>
  </div>
</nav>

<div class="page-banner">
  <div class="hero-grid"></div>
  <div class="page-banner-content">
    <h1>Tu colección</h1>
    <p>Explora, filtra y organiza todos los cromos que has conseguido.</p>
    <div class="page-banner-stats">
      <div class="pb-stat"><b><?= $totalObtenidas ?> / <?= $totalColeccion ?></b><span>Cromos obtenidos</span></div>
      <div class="pb-stat"><b><?= round($totalObtenidas / $totalColeccion * 100) ?>%</b><span>Completado</span></div>
    </div>
  </div>
</div>

<section>
  <div class="collection-layout">

    <aside class="filters-panel reveal">
      <h3>Filtrar cromos</h3>

      <div class="filter-block">
        <label class="f-label" for="f-buscar">Buscar por nombre</label>
        <input type="text" id="f-buscar" placeholder="Ej. Mark Evans">
      </div>

      <div class="filter-block">
        <label class="f-label" for="f-equipo">Equipo</label>
        <select id="f-equipo">
          <option value="">Todos los equipos</option>
          <?php foreach ($equipos as $eq): ?>
          <option value="<?= htmlspecialchars($eq) ?>"><?= htmlspecialchars($eq) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-block">
        <label class="f-label" for="f-expansion">Expansión</label>
        <select id="f-expansion">
          <option value="">Todas las expansiones</option>
          <?php foreach ($expansiones as $ex): ?>
          <option value="<?= htmlspecialchars($ex) ?>"><?= htmlspecialchars($ex) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-block">
        <span class="f-label">Rareza</span>
        <?php foreach ($rarezas as $r): ?>
        <label class="checkbox-row"><input type="checkbox" value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></label>
        <?php endforeach; ?>
      </div>

      <div class="filter-block">
        <label class="f-label" for="f-estado">Estado</label>
        <select id="f-estado">
          <option value="">Todas</option>
          <option value="bloqueada">Bloqueadas</option>
          <option value="desbloqueada">Desbloqueadas</option>
        </select>
      </div>

      <button class="btn btn-primary filter-apply">Aplicar filtros</button>
    </aside>

    <div>
      <div class="collection-toolbar reveal">
        <span class="collection-count"><b><?= $totalObtenidas ?></b> cromos mostrados</span>
      </div>

      <div class="card-grid">
        <?php foreach ($cromos as $c): ?>
        <div class="tcard reveal">
          <div class="tcard-inner" style="position:relative;">
            <?php if ($c['bloqueada']): ?><span class="lock-badge">🔒</span><?php endif; ?>
            <span class="rarity <?= $c['clave'] ?>"><?= htmlspecialchars($c['rareza']) ?></span>
            <div class="portrait"><?= strtoupper(substr($c['nombre'],0,2)) ?></div>
            <h3><?= htmlspecialchars($c['nombre']) ?></h3>
            <div class="meta-row"><span><?= htmlspecialchars($c['equipo']) ?></span></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="pagination">
        <button class="page-btn active">1</button>
        <button class="page-btn">2</button>
        <button class="page-btn">3</button>
        <button class="page-btn">→</button>
      </div>
    </div>

  </div>
</section>

<script src="./assets/js/scriptColeccion.js"></script>

</body>
</html>
