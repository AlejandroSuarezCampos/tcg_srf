<?php
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/rareza-clases.php';

$coleccion = $db->listarColeccionCompleta();

//filtros

$equipos = [];
$afinidades = [];
$rarezas = [];
$totalCromos = 0;

foreach ($coleccion as $expansion) {
    foreach ($expansion["cromos"] as $cromo) {
        $totalCromos++;

        $equipos[$cromo["equipo"]] = $cromo["equipo"];
        $afinidades[$cromo["afinidad"]] = $cromo["afinidad"];
        $rarezas[$cromo["id_rareza"]] = $cromo["rareza"];
    }
}

ksort($rarezas);
sort($equipos);
sort($afinidades);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Álbum — Inazuma TCG</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <link rel="icon" type="image/png" href="assets/img/iconos/favicon.ico">
    
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<?php
$activePage = "album";
include __DIR__ . "/navbar.php";
?>

<div class="page-banner">
    <div class="hero-grid"></div>

    <div class="page-banner-content">
        <h1>Álbum</h1>
        <p>
            Todas las cartas del juego organizadas por expansión.
            Actualmente existen <strong><?= $totalCromos ?></strong> cromos.
        </p>
    </div>
</div>

<section>

    <div class="collection-layout">

        <aside class="filters-panel reveal">

            <h3>Filtrar cromos</h3>

            <div class="filter-block">
                <label class="f-label" for="f-buscar">
                    Buscar por nombre
                </label>

                <input
                    type="text"
                    id="f-buscar"
                    placeholder="Ej. Mark Evans">
            </div>

            <div class="filter-block">

                <label class="f-label" for="f-equipo">
                    Equipo
                </label>

                <select id="f-equipo">

                    <option value="">
                        Todos los equipos
                    </option>

                    <?php foreach ($equipos as $equipo): ?>

                        <option value="<?= htmlspecialchars($equipo) ?>">
                            <?= htmlspecialchars($equipo) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="filter-block">

                <label class="f-label" for="f-afinidad">
                    Afinidad
                </label>

                <select id="f-afinidad">

                    <option value="">
                        Todas las afinidades
                    </option>

                    <?php foreach ($afinidades as $afinidad): ?>

                        <option value="<?= htmlspecialchars($afinidad) ?>">
                            <?= htmlspecialchars($afinidad) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="filter-block">

                <span class="f-label">
                    Rareza
                </span>

                <?php foreach ($rarezas as $idRareza => $rareza): ?>

                    <label class="checkbox-row">

                        <input
                            type="checkbox"
                            value="<?= $idRareza ?>">

                        <?= htmlspecialchars($rareza) ?>

                    </label>

                <?php endforeach; ?>

            </div>

            <button
                id="btnFiltrar"
                class="btn btn-primary filter-apply">
                Aplicar filtros
            </button>

        </aside>

        <div>

            <?php foreach ($coleccion as $expansion): ?>

                <?php
                $total = count($expansion["cromos"]);
                ?>

                <div class="expansion-group reveal">

                    <div class="expansion-head">

                        <div>

                            <h2>
                                <?= htmlspecialchars($expansion["info"]["nombre"]) ?>
                            </h2>

                            <span class="exp-sub">
                                <?= $total ?> cromos
                            </span>

                        </div>

                    </div>

                    <div class="card-grid">

                        <?php foreach ($expansion["cromos"] as $cromo): ?>

                            <div
                              class="tcard reveal"
                              data-nombre="<?= htmlspecialchars($cromo["nombre"]) ?>"
                              data-equipo="<?= htmlspecialchars($cromo["equipo"]) ?>"
                              data-afinidad="<?= htmlspecialchars($cromo["afinidad"]) ?>"
                              data-posicion="<?= htmlspecialchars($cromo["posicion"]) ?>"
                              data-rareza="<?= $cromo["id_rareza"] ?>">

                              <div class="tcard-inner">

                                  <div style="display:flex; justify-content:space-between; align-items:center;">

                                      <span class="rarity <?= $claseRarezaPorId[$cromo["id_rareza"]] ?? "r-comun" ?>">
                                          <?= htmlspecialchars($cromo["rareza"]) ?>
                                      </span>

                                      <?php if (strcasecmp($cromo["afinidad"], "No-afi") !== 0): ?>
                                      <div class="hex">
                                          <img
                                              src="<?= htmlspecialchars($cromo["afinidad_imagen"]) ?>"
                                              alt="<?= htmlspecialchars($cromo["afinidad"]) ?>">
                                      </div>
                                      <?php endif; ?>

                                  </div>

                                  <div class="portrait">

                                      <img
                                          src="<?= htmlspecialchars($cromo["imagen"]) ?>"
                                          alt="<?= htmlspecialchars($cromo["nombre"]) ?>"
                                          loading="lazy">

                                  </div>

                                  <h3>
                                      <?= htmlspecialchars($cromo["nombre"]) ?>
                                  </h3>

                                  <div class="meta-row">

                                      <span>
                                          <?= htmlspecialchars($cromo["equipo"]) ?>
                                      </span>

                                      <span>
                                          <?= htmlspecialchars($cromo["posicion"]) ?>
                                      </span>

                                  </div>

                              </div>

                          </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>

<script src="./assets/js/scriptAlbum.js"></script>

</body>

</html>