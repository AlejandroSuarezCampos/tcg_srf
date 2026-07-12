<?php
require_once __DIR__ . '.\db\conexion.php';

/**
 * Maqueta visual con datos de ejemplo. Cuando tengas tus métodos en Tcg
 * (consultas.php), sustituye este array por tu llamada real, p. ej.:
 *   $anuncios = $db->listarMercadoActivo($filtros);
 */
$anuncios = [
    ['carta'=>'Mark Slate',   'equipo'=>'Raimon FC',    'rareza'=>'Legendaria', 'clave'=>'r-legend', 'precio'=>780, 'vendedor'=>'FrostSharp'],
    ['carta'=>'Axel Blaze',   'equipo'=>'Zeus FC',      'rareza'=>'Épica',      'clave'=>'r-epic',   'precio'=>410, 'vendedor'=>'ZeusMainline'],
    ['carta'=>'Shawn Frost',  'equipo'=>'Diamond Dust', 'rareza'=>'Épica',      'clave'=>'r-epic',   'precio'=>390, 'vendedor'=>'ForestRoots'],
    ['carta'=>'Jude Sharp',   'equipo'=>'Zeus FC',      'rareza'=>'Rara',       'clave'=>'r-rare',   'precio'=>150, 'vendedor'=>'KazeStorm_7'],
    ['carta'=>'Xavier Foster','equipo'=>'Big Waves',    'rareza'=>'Épica',      'clave'=>'r-epic',   'precio'=>360, 'vendedor'=>'IceQueen'],
    ['carta'=>'Mark Evans',   'equipo'=>'Raimon FC',    'rareza'=>'Legendaria', 'clave'=>'r-legend', 'precio'=>820, 'vendedor'=>'RaimonCaptain'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mercado — Inazuma TCG</title>
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
    <a href="coleccion.php">Colección</a>
    <a href="mercado.php" class="active">Mercado</a>
    <a href="perfil.php">Perfil</a>
  </div>
  <div class="user-chip">
    <div class="avatar-sm">KS</div>
    <span class="coins">1 240</span>
  </div>
</nav>

<div class="page-banner">
  <div class="hero-grid"></div>
  <div class="page-banner-content" style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:24px;">
    <div>
      <h1>Mercado</h1>
      <p>Compra y vende cromos con otros invocadores.</p>
    </div>
    <button class="btn btn-primary">Vender una carta</button>
  </div>
</div>

<section>
  <div class="market-toolbar reveal">
    <input type="text" placeholder="Buscar carta por nombre...">
    <select>
      <option value="">Todas las rarezas</option>
      <option>Legendaria</option>
      <option>Épica</option>
      <option>Rara</option>
      <option>Común</option>
    </select>
    <select>
      <option value="">Ordenar por</option>
      <option>Precio: menor a mayor</option>
      <option>Precio: mayor a menor</option>
      <option>Más recientes</option>
    </select>
  </div>

  <div class="card-grid">
    <?php foreach ($anuncios as $a): ?>
    <div class="tcard reveal">
      <div class="tcard-inner" style="position:relative;">
        <span class="listing-price"><?= number_format($a['precio']) ?> ⛁</span>
        <span class="rarity <?= $a['clave'] ?>"><?= htmlspecialchars($a['rareza']) ?></span>
        <div class="portrait"><?= strtoupper(substr($a['carta'],0,2)) ?></div>
        <h3><?= htmlspecialchars($a['carta']) ?></h3>
        <div class="meta-row"><span><?= htmlspecialchars($a['equipo']) ?></span></div>
        <div class="listing-seller">
          <div class="avatar-sm"><?= strtoupper(substr($a['vendedor'],0,2)) ?></div>
          <span>Vende <?= htmlspecialchars($a['vendedor']) ?></span>
        </div>
        <button class="btn btn-primary buy-btn">Comprar</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="pagination">
    <button class="page-btn active">1</button>
    <button class="page-btn">2</button>
    <button class="page-btn">→</button>
  </div>
</section>

<script src="./assets/js/scriptMercado.js"></script>

</body>
</html>
