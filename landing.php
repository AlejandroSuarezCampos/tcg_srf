<?php

session_start();

require_once(__DIR__ . '/db/conexion.php');
require_once __DIR__ . '/rareza-clases.php';

$topJugadores = [
    ['nombre' => 'KazeStorm_7',  'rating' => 248],
    ['nombre' => 'FrostSharp',   'rating' => 231],
    ['nombre' => 'ZeusMainline', 'rating' => 219],
    ['nombre' => 'ForestRoots',  'rating' => 204],
];

$cromos=$db->listarDestacados();
$expansiones=$db->listarExpansionesActivas();
//var_dump($expansiones);

$cartasInusuales = [
    ['carta' => 'Mark Slate',   'rareza' => 'Legendaria', 'probabilidad' => 0.8,  'poseedores' => 12],
    ['carta' => 'Shawn Frost',  'rareza' => 'Épica',      'probabilidad' => 2.5,  'poseedores' => 34],
    ['carta' => 'Xavier Foster','rareza' => 'Épica',      'probabilidad' => 2.5,  'poseedores' => 41],
    ['carta' => 'Jude Sharp',   'rareza' => 'Rara',       'probabilidad' => 6.0,  'poseedores' => 88],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SUPERLIGA FRONTIER TCG</title>
<link rel="icon" type="image/png" href="assets/img/iconos/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" type="image/png" href="./assets/img/iconos/favicon.ico">
</head>
<body>

<nav id="nav">
  <div class="logo">SUPERLIGA FRONTIER<span>·</span>TCG</div>

  <input type="checkbox" id="navToggle" class="nav-toggle-input">

  <div class="nav-collapse">
    <div class="nav-links">
      <a href="#cartas">Cartas</a>
      <a href="#expansiones">Expansiones</a>
      <a href="#ranking">Ranking</a>
    </div>
    <div class="nav-actions">
      <?php if (!empty($_SESSION['id_usuario'])): ?>
        <a href="perfil.php"><button class="btn btn-ghost" style="padding:10px 22px; font-size:12px;">Hola, <?= htmlspecialchars($_SESSION['nombre']) ?></button></a>
        <a href="logout.php"><button class="btn btn-ghost" style="padding:10px 22px; font-size:12px;">Salir</button></a>
      <?php else: ?>
        <a href="login.php"><button class="btn btn-ghost" style="padding:10px 22px; font-size:12px;">Entrar</button></a>
      <?php endif; ?>
      <?php if (isset($_SESSION['dictador'])){
                if($_SESSION['dictador']==1){
      ?>
        <a href="./panel/index.php"><button class="btn btn-ghost" style="padding:10px 22px; font-size:12px;">Panel de admin</button></a>
      <?php }}?>
    </div>
  </div>

  <div class="nav-right">
    <label for="navToggle" class="nav-burger" aria-label="Abrir menú">
      <span></span><span></span><span></span>
    </label>
  </div>
</nav>

<section class="hero">
  <canvas id="particles"></canvas>
  <div class="hero-grid"></div>
  <svg class="bolt" id="bolt1" width="120" height="200" style="top:12%; left:12%;" viewBox="0 0 60 100"><polygon points="35,0 10,55 28,55 20,100 55,40 35,40" fill="#FFD23F"/></svg>
  <svg class="bolt" id="bolt2" width="90" height="150" style="top:55%; right:10%;" viewBox="0 0 60 100"><polygon points="35,0 10,55 28,55 20,100 55,40 35,40" fill="#6E93FF"/></svg>

  <div class="hero-content">
    <a href="https://superligafrontier.es"><span class="eyebrow">Temporada 02 · Ya disponible</span></a>
    <h1>EL TCG DEFINITIVO<br>DE LA <em>SUPERRUINA FRONTIER</em></h1>
    <p class="sub">Forma tu equipo, domina el campo y colecciona a las leyendas del fútbol más ruinero jamás jugado.</p>
    <div class="hero-cta">
      <?php if(!isset($_SESSION['id_usuario'])) : ?>
      <button class="btn btn-primary" onclick="window.location.href='login.php'">Empieza tu colección</button>
      <?php endif ?>
      <button class="btn btn-ghost" onclick="window.location.href='album.php'">Ver cartas</button>
    </div>
  </div>

  <div class="scroll-cue"><div class="line"></div><span class="mono" style="font-size:10px;">Scroll</span></div>
</section>

<section id="cartas">
  <div class="section-head reveal">
    <div>
      <span class="section-tag">Colección activa</span>
      <h2>Cartas destacadas</h2>
    </div>
    <a href="album.php"><button class="btn btn-ghost" style="font-size:12px; padding:12px 24px;">Ver todas</button></a>
  </div>

  <div class="card-grid">
    <?php foreach ($cromos as $carta): ?>
    <div class="tcard reveal">
      <div class="tcard-inner">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <span class="rarity <?= $claseRarezaPorId[$carta['id_rareza']] ?? 'r-comun' ?>"><?= $carta["rareza"] ?></span>
          <div class="hex"><img src="<?= $carta["afinidad_imagen"] ?>"/></div>
        </div>
        <div class="portrait"><img src="<?= $carta["imagen"] ?>"/></div>
        <h3><?= $carta["nombre"] ?></h3>
        <div class="meta-row"><span><?= $carta["equipo"] ?></span><span><?= $carta["posicion"] ?></span></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<section id="expansiones" style="background:linear-gradient(180deg, transparent, rgba(59,107,255,.04), transparent);">
  <div class="section-head reveal">
    <div>
      <span class="section-tag">Nuevo contenido</span>
      <h2>Últimas expansiones</h2>
    </div>
  </div>

  <div class="hscroll reveal">
    <?php foreach ($expansiones as $expansion): 
      $ncartas = $db->cartasExpansion($expansion["id_expansion"]);
    ?>
    <div class="exp-card">
      <div class="glow-orb" style="background:var(--volt);"></div>
      <div class="exp-inner">
        <span class="exp-date"><?= $expansion["fecha_salida"] ?></span>
        <h3><?= $expansion["nombre"] ?></h3>
        <span class="exp-count"><?=$ncartas?> cartas nuevas</span>
        <span class="exp-link">Explorar →</span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<section id="ranking">
  <div class="section-head reveal">
    <div>
      <span class="section-tag">Estadísticas globales</span>
      <h2>Ranking</h2>
    </div>
  </div>

  <div class="rank-tabs reveal">
    <button class="rank-tab active" data-target="rv-jugadores">Top jugadores</button>
    <button class="rank-tab" data-target="rv-cartas">Cartas más inusuales</button>
  </div>

  <div class="rank-table-wrap reveal">
    <div class="rank-view active" id="rv-jugadores">
      <table>
        <thead><tr><th>#</th><th>Jugador</th><th>Rating</th></tr></thead>
        <tbody>
<?php foreach ($topJugadores as $i => $j): ?>
          <tr><td class="rank-pos"><?= $i + 1 ?></td><td><?= htmlspecialchars($j['nombre']) ?></td><td><?= number_format($j['rating']) ?></td></tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="rank-view" id="rv-cartas">
      <table>
        <thead><tr><th>#</th><th>Carta</th><th>Rareza</th><th>Probabilidad</th><th>Jugadores que la tienen</th></tr></thead>
        <tbody>
<?php foreach ($cartasInusuales as $i => $c): ?>
          <tr><td class="rank-pos"><?= $i + 1 ?></td><td><?= htmlspecialchars($c['carta']) ?></td><td><?= htmlspecialchars($c['rareza']) ?></td><td><?= number_format($c['probabilidad'], 2) ?>%</td><td><?= (int)$c['poseedores'] ?></td></tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<footer>
  <div class="foot-grid">
    <div>
      <div class="logo" style="margin-bottom:14px;">INAZUMA<span>·</span>TCG</div>
      <p style="color:var(--frost-dim); font-size:14px; max-width:280px;">El juego de cartas coleccionables definitivo para revivir la energía del fútbol más eléctrico.</p>
      <div class="socials" style="margin-top:22px;">
        <a href="https://x.com/supligafrontier">
          <i class="fa-brands fa-x-twitter"></i>
        </a>
        <a href="https://www.instagram.com/superligafrontier/">
          <i class="fa-brands fa-instagram"></i>
        </a>
        <a href="https://discord.gg/KgEBHA87fF">
          <i class="fa-brands fa-discord"></i>
        </a>
      </div>
    </div>
    <div>
      <h4>Juego</h4>
      <ul><li><a href="#cartas">Cartas</a></li><li><a href="#expansiones">Expansiones</a></li><li><a href="#ranking">Ranking</a></li><li><a href="#">Mazos</a></li></ul>
    </div>
    <div>
      <h4>Soporte</h4>
      <ul><li><a href="#">FAQ</a></li><li><a href="#">Contacto</a></li><li><a href="#">Comunidad</a></li><li><a href="#">Reportar bug</a></li></ul>
    </div>
  </div>
  <div class="foot-bottom">
    <p>© 2026 Inazuma TCG. Proyecto de fan no oficial — Inazuma Eleven es propiedad de Level-5.</p>
    <p class="mono" style="font-size:10px;">Al Gonzalo ese le gano fácil</p>
  </div>
</footer>

<script src="./assets/js/scriptLanding.js"></script>

</body>
</html>