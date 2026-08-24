<?php
/**
 * Endpoint del editor visual de Cadenas (panel/cadena_editor.php).
 *
 * Vive aparte del render, como todo endpoint de sondeo/mutación del proyecto
 * (§2 del CLAUDE.md de branding). El editor es un lienzo con estado en
 * memoria del navegador (posiciones de nodos, líneas de aristas ya
 * dibujadas): una recarga de página completa en cada clic lo destrozaría, así
 * que cada acción viaja por aquí y devuelve solo lo que el JS necesita para
 * actualizar su copia local, nunca la página entera.
 *
 * Guardado igual que assets/ajax/importacion_ejecutar.php: solo dictador=1.
 */
session_start();
require_once __DIR__ . '/../../db/conexion.php';
require_once __DIR__ . '/../../partials/csrf.php';
require_once __DIR__ . '/../../partials/subida_imagen.php';

header('Content-Type: application/json');

if (empty($_SESSION['dictador']) || $_SESSION['dictador'] != 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado.']);
    exit;
}

if (!csrfValido($_POST['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.']);
    exit;
}

// Escudo subido como archivo (crear_rival / actualizar_rival): gana al campo
// de texto si viene, se guarda siempre en assets/img/Escudos/ (carpeta plana,
// sin subcarpetas por expansión — un rival no pertenece a ninguna).
$escudoSubido = null;
if (!empty($_FILES['escudo_archivo']) && $_FILES['escudo_archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $subida = subirImagenPanel(
        $_FILES['escudo_archivo'],
        __DIR__ . '/../../assets/img/Escudos/',
        './assets/img/Escudos/',
        $_POST['nombre'] ?? 'escudo'
    );
    if (!$subida['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $subida['error']]);
        exit;
    }
    $escudoSubido = $subida['ruta'];
}

$accion = $_POST['accion'] ?? '';

switch ($accion) {

    /* Coordenadas EN PÍXELES desde la `044`. El editor ya no coloca en una
       rejilla de 190x120 sino donde uno suelte el nodo. */
    case 'mover_nodo':
        $db->moverNodo((int) $_POST['id_nodo'], (int) $_POST['x'], (int) $_POST['y']);
        echo json_encode(['ok' => true]);
        break;

    case 'crear_nodo':
        $tipoNodo = in_array($_POST['tipo'] ?? '', ['cofre', 'inicio', 'bloqueo'], true) ? $_POST['tipo'] : 'partido';

        /* SOLO UNA CASILLA DE SALIDA POR CADENA. Dos salidas son dos rutas de
           inicio, y entonces la casilla deja de decir por dónde se empieza,
           que es lo único que hace. */
        if ($tipoNodo === 'inicio' && $db->cadenaTieneInicio((int) $_POST['id_cadena'])) {
            echo json_encode(['ok' => false,
                'error' => 'Esta cadena ya tiene su casilla de salida. Mueve la que hay o bórrala.']);
            break;
        }

        $idNodo = $db->crearNodo(
            (int) $_POST['id_cadena'],
            $tipoNodo,
            trim($_POST['nombre'] ?? '') ?: null,
            (int) $_POST['x'],
            (int) $_POST['y'],
            0
        );
        echo json_encode(['ok' => true, 'nodo' => [
            'id_nodo' => $idNodo, 'tipo' => $tipoNodo,
            'nombre' => trim($_POST['nombre'] ?? ''),
            'pos_x' => (int) $_POST['x'], 'pos_y' => (int) $_POST['y'],
            'es_final' => 0, 'id_rival' => null, 'rival' => null,
        ]]);
        break;

    case 'actualizar_nodo':
        /* ⚠️ `!empty` Y NO `isset`, QUE ES LO QUE MARCABA TODOS LOS NODOS COMO
           FINALES. Esto no es un formulario HTML, donde una casilla sin marcar
           simplemente no viaja: lo manda `fetch` con `URLSearchParams`, y el
           editor incluye SIEMPRE la clave —`es_final: n.es_final ? 1 : ''`—.
           Con la casilla desmarcada llegaba `es_final=`, o sea la cadena
           vacía, y `isset()` de una cadena vacía es `true`: cada vez que se
           guardaba un nodo se le ponía la bandera de final de cadena.

           El síntoma que se veía era el de después: abrir cualquier nodo y
           encontrarse la casilla marcada sin haberla tocado, en todos. Y no
           era solo cosmético — `cofreFinalCadena()` busca por `es_final = 1`,
           así que la recompensa de formación colgaba del nodo equivocado.

           `!empty` es además lo que ya usaba la línea de abajo para
           `id_rival`: ahora las dos leen igual. */
        $db->actualizarNodo(
            (int) $_POST['id_nodo'],
            in_array($_POST['tipo'] ?? '', ['cofre', 'inicio', 'bloqueo'], true) ? $_POST['tipo'] : 'partido',
            trim($_POST['nombre'] ?? '') ?: null,
            !empty($_POST['es_final']) ? 1 : 0,
            !empty($_POST['id_rival']) ? (int) $_POST['id_rival'] : null
        );
        echo json_encode(['ok' => true]);
        break;

    case 'eliminar_nodo':
        $db->eliminarNodo((int) $_POST['id_nodo']);
        echo json_encode(['ok' => true]);
        break;

    case 'crear_arista':
        $db->crearArista((int) $_POST['id_origen'], (int) $_POST['id_destino']);
        echo json_encode(['ok' => true]);
        break;

    case 'eliminar_arista':
        $db->eliminarArista((int) $_POST['id_origen'], (int) $_POST['id_destino']);
        echo json_encode(['ok' => true]);
        break;

    case 'crear_rival':
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') { echo json_encode(['ok' => false, 'error' => 'Falta el nombre.']); break; }
        $escudo = $escudoSubido ?? trim($_POST['escudo'] ?? '');
        $idRival = $db->crearRival($nombre, $escudo, trim($_POST['descripcion'] ?? ''), 1);
        echo json_encode(['ok' => true, 'rival' => [
            'id_rival' => $idRival, 'nombre' => $nombre, 'total_estilos' => 0, 'escudo' => $escudo,
        ]]);
        break;

    case 'actualizar_rival':
        $idRival = (int) ($_POST['id_rival'] ?? 0);
        $rival = $db->obtenerRival($idRival);
        if (!$rival) { echo json_encode(['ok' => false, 'error' => 'Rival no encontrado.']); break; }
        $nombre = trim($_POST['nombre'] ?? '') ?: $rival['nombre'];
        $escudo = $escudoSubido ?? trim($_POST['escudo'] ?? '');
        /* La descripción se toma de lo enviado. Antes se reescribía siempre con
           la que ya había en la base: esta acción solo la usaba el botón de
           "guardar escudo", que no mandaba ninguna. Desde que el editor tiene
           un único formulario de rival —el mismo para crear y para editar—, lo
           que se escriba aquí tiene que guardarse, o el campo mentiría.
           `??` y no `?:` a propósito: una descripción borrada a posta es una
           cadena vacía, y con `?:` se habría deshecho el borrado. */
        $descripcion = isset($_POST['descripcion'])
            ? trim($_POST['descripcion'])
            : $rival['descripcion'];
        $db->actualizarRival(
            $idRival, $nombre, $escudo,
            $descripcion, (int) $rival['activo']
        );
        echo json_encode(['ok' => true, 'escudo' => $escudo]);
        break;

    case 'crear_estilo':
        $formacion = $_POST['formacion'] ?? '';
        if (!isset(Tcg::FORMACIONES[$formacion])) { echo json_encode(['ok' => false, 'error' => 'Formación no válida.']); break; }
        $nombre = trim($_POST['nombre'] ?? '') ?: Tcg::FORMACIONES[$formacion]['nombre'];
        $idEstilo = $db->crearEstiloRival((int) $_POST['id_rival'], $nombre, $formacion);
        echo json_encode(['ok' => true, 'estilo' => [
            'id_estilo' => $idEstilo, 'nombre' => $nombre, 'formacion' => $formacion,
        ], 'huecos' => Tcg::huecosDe($formacion)]);
        break;

    /* Tres estadísticas al azar dentro del rango real de esa rareza y
       posición. Se resuelve en el servidor y no en el navegador para que la
       tabla de rangos siga viviendo en un solo sitio (`IMPORT_RANGOS_STATS`):
       una copia en JavaScript se quedaría desfasada a la primera. */
    case 'stats_aleatorias':
        echo json_encode([
            'ok' => true,
            'stats' => $db->statsBaseImportacion(
                $_POST['posicion'] ?? '', (int) ($_POST['id_rareza'] ?? 1)
            ),
        ]);
        break;

    /* Encender o apagar una dificultad en TODA la cadena. Es como se monta una
       cadena "solo Extremo" sin entrar nodo por nodo. */
    case 'dificultad_cadena':
        $n = $db->activarDificultadCadena(
            (int) $_POST['id_cadena'], $_POST['dificultad'] ?? '', !empty($_POST['activa'])
        );
        echo json_encode(['ok' => $n > 0, 'nodos' => $n,
            'error' => $n > 0 ? null : 'Esa cadena no tiene nodos de partido.']);
        break;

    /* Encender o apagar una TRAMPA del rival en toda la cadena. Es lo que
       permite dejar una cadena entera en modo "jefe final" sin abrir los
       veinte nodos uno a uno. */
    case 'trampa_cadena':
        $n = $db->trampaCadena(
            (int) $_POST['id_cadena'],
            $_POST['dificultad'] ?? '',
            $_POST['columna'] ?? '',
            // '' se manda a propósito para decir "como el general"
            ($_POST['valor'] ?? '') === '' ? null : $_POST['valor']
        );
        echo json_encode(['ok' => $n > 0, 'nodos' => $n,
            'error' => $n > 0 ? null : 'Esa cadena no tiene nodos de partido, o la columna no es válida.']);
        break;

    case 'trampas_cadena':
        echo json_encode(['ok' => true, 'estado' => $db->trampasCadena((int) $_POST['id_cadena'])]);
        break;

    case 'dificultades_cadena':
        echo json_encode(['ok' => true, 'estado' => $db->dificultadesCadena((int) $_POST['id_cadena'])]);
        break;

    /* Lo que reparte de verdad un nodo, rango por rango. Lo calcula el
       servidor con el MISMO filtro que usa el reparto real: si se hiciera en
       el navegador, el resumen y el premio podrían discrepar. */
    case 'resumen_loot':
        echo json_encode(['ok' => true, 'resumen' => $db->resumenLootNodo((int) $_POST['id_nodo'])]);
        break;

    case 'borrar_estilo':
        echo json_encode($db->eliminarEstiloRival((int) $_POST['id_estilo']));
        break;

    case 'listar_cartas_estilo':
        echo json_encode(['ok' => true, 'cartas' => $db->listarCartasEstilo((int) $_POST['id_estilo'])]);
        break;

    case 'asignar_carta':
        $db->asignarCartaEstilo((int) $_POST['id_estilo'], (int) $_POST['hueco'], (int) $_POST['id_cromo']);
        echo json_encode(['ok' => true]);
        break;

    case 'crear_loot':
        $tipo = in_array($_POST['tipo'] ?? '', ['monedas', 'cromo', 'cromo_limitado'], true) ? $_POST['tipo'] : 'monedas';
        $idLoot = $db->crearLoot(
            (int) $_POST['id_nodo'],
            $tipo,
            !empty($_POST['id_cromo']) ? (int) $_POST['id_cromo'] : null,
            !empty($_POST['monedas']) ? (int) $_POST['monedas'] : null,
            (float) ($_POST['probabilidad'] ?? 100),
            trim($_POST['rango_minimo'] ?? '') ?: null
        );
        echo json_encode(['ok' => true, 'id_loot' => $idLoot]);
        break;

    /* --- Requisitos de un nodo de BLOQUEO (`045`) ---
       El STOP del mapa. Se listan, se añaden y se quitan uno a uno, igual que
       el botín: son pocas filas y el editor ya sabe repintar una lista. */
    case 'listar_requisitos_nodo':
        echo json_encode(['ok' => true,
            'requisitos' => $db->listarRequisitosNodo((int) $_POST['id_nodo'])]);
        break;

    case 'crear_requisito_nodo':
        $ok = $db->crearRequisitoNodo(
            (int) $_POST['id_nodo'],
            $_POST['tipo'] ?? '',
            (int) ($_POST['valor'] ?? 0),
            isset($_POST['cantidad']) ? (int) $_POST['cantidad'] : null,
            trim($_POST['dificultad'] ?? '') ?: null
        );
        // El false llega de la validación de crearRequisitoNodo(): tipo que no
        // existe, o un bloqueo que exigía completar su propia cadena.
        echo json_encode($ok
            ? ['ok' => true, 'requisitos' => $db->listarRequisitosNodo((int) $_POST['id_nodo'])]
            : ['ok' => false, 'error' => 'Ese requisito no vale: o el tipo no existe, o pedía completar la cadena en la que vive este nodo (no se abriría nunca).']);
        break;

    case 'eliminar_requisito_nodo':
        $db->eliminarRequisitoNodo((int) $_POST['id_requisito']);
        echo json_encode(['ok' => true,
            'requisitos' => $db->listarRequisitosNodo((int) $_POST['id_nodo'])]);
        break;

    case 'eliminar_loot':
        $db->eliminarLoot((int) $_POST['id_loot']);
        echo json_encode(['ok' => true]);
        break;

    // ---- Dificultad por nodo (migración `029`) ----
    case 'listar_ajustes_nodo':
        echo json_encode(['ok' => true, 'ajustes' => $db->listarAjustesNodoAdmin((int) $_POST['id_nodo'])]);
        break;

    case 'guardar_ajuste_nodo':
        $dif = $_POST['dificultad'] ?? '';
        if (!in_array($dif, Tcg::DIFICULTADES, true)) {
            echo json_encode(['ok' => false, 'error' => 'Dificultad no válida.']);
            break;
        }
        /* Se pasa el array crudo a propósito: guardarAjusteNodo() distingue
           "" (no pisar → NULL) de 0 (pisar con cero), y castear aquí a int
           convertiría todos los vacíos en ceros, que es justo lo contrario. */
        $db->guardarAjusteNodo((int) $_POST['id_nodo'], $dif, [
            'activa'        => $_POST['activa']        ?? 1,
            'mult_fuerza'   => $_POST['mult_fuerza']   ?? '',
            'mult_compos'   => $_POST['mult_compos']   ?? '',
            'subir_rareza'  => $_POST['subir_rareza']  ?? '',
            'pesos_ia'      => $_POST['pesos_ia']      ?? '',
            'compos_libres' => $_POST['compos_libres'] ?? '',
            'sin_malus'     => $_POST['sin_malus']     ?? '',
            'rareza_max'    => $_POST['rareza_max']    ?? '',
            'tiers'         => trim($_POST['tiers']    ?? ''),
            'id_estilo'     => $_POST['id_estilo']     ?? '',
        ]);
        echo json_encode(['ok' => true]);
        break;

    case 'borrar_ajuste_nodo':
        $dif = $_POST['dificultad'] ?? '';
        if (!in_array($dif, Tcg::DIFICULTADES, true)) {
            echo json_encode(['ok' => false, 'error' => 'Dificultad no válida.']);
            break;
        }
        $db->borrarAjusteNodo((int) $_POST['id_nodo'], $dif);
        echo json_encode(['ok' => true]);
        break;

    // ---- Carta exclusiva de cadena, creada desde el propio editor (`030`) ----
    case 'crear_cromo_cadena':
        $nombre   = trim($_POST['nombre'] ?? '');
        $posicion = $_POST['posicion'] ?? '';
        if ($nombre === '' || !in_array($posicion, ['POR', 'DF', 'MC', 'DC'], true)) {
            echo json_encode(['ok' => false, 'error' => 'Hace falta nombre y una posición jugable.']);
            break;
        }
        $idRareza = max(1, min(6, (int) ($_POST['id_rareza'] ?? 1)));

        /* Estadísticas: o las que venga escritas, o un tiro dentro del rango
           real de esa rareza y posición. Se reutiliza statsBaseImportacion(),
           que ya tiene la tabla de Rangos_estadisticas_SRF.csv — no se
           reimplementa aquí una segunda copia de esos números. */
        if (!empty($_POST['aleatorias'])) {
            $stats = $db->statsBaseImportacion($posicion, $idRareza);
        } else {
            $stats = [
                'ataque'  => max(0, min(99, (int) ($_POST['ataque']  ?? 0))),
                'defensa' => max(0, min(99, (int) ($_POST['defensa'] ?? 0))),
                'tecnica' => max(0, min(99, (int) ($_POST['tecnica'] ?? 0))),
            ];
        }

        /* El arte va a assets/img/Cromos/, no a Escudos/: el bloque de arriba
           solo trata `escudo_archivo`, que es otra cosa (el emblema del rival).
           Mismo validador, carpeta distinta.

           Y LA CARPETA DECIDE EL ASPECTO: `carta_usa_marco()` monta la
           plantilla de la rareza solo sobre lo que cuelga de `Cromos/Importados/`.
           Una foto de jugador tiene que ir ahí para salir con su marco; un
           artwork completo va a `Cromos/Cadenas/`, porque ya trae su propio
           fondo y el marco le taparía la ilustración. */
        $esFoto = ($_POST['tipo_imagen'] ?? 'artwork') === 'foto';
        $carpeta = $esFoto ? 'Importados/Cadenas/' : 'Cadenas/';

        $imagen = '';
        if (!empty($_FILES['arte_archivo']) && $_FILES['arte_archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $subidaArte = subirImagenPanel(
                $_FILES['arte_archivo'],
                __DIR__ . '/../../assets/img/Cromos/' . $carpeta,
                './assets/img/Cromos/' . $carpeta,
                $nombre
            );
            if (!$subidaArte['ok']) {
                echo json_encode(['ok' => false, 'error' => $subidaArte['error']]);
                break;
            }
            $imagen = $subidaArte['ruta'];
        }

        $idCromo = (int) $db->crearCromoSoloCadena(
            $nombre, $posicion, $imagen,
            (int) $_POST['id_expansion'], (int) $_POST['id_equipo'],
            $idRareza, (int) $_POST['id_afinidad'],
            $stats['ataque'], $stats['defensa'], $stats['tecnica']
        );
        /* LA COMPO, que es lo que se olvidaba aquí.
           El alta del panel de cromos llama a `derivarRasgosConfiguracion()`
           justo después de crear; esta no lo hacía, así que todo jugador
           creado desde el editor de nodos salía SIN rasgo de configuración
           —sin contraataque, sin brecha, sin vínculo, sin justicia— y por
           tanto sin aportar nada a las compos de su alineación. Se nota poco
           al crearla y mucho al jugarla.
           Acotado a esta carta: recorrer el catálogo entero para una sola son
           casi mil quinientas consultas. */
        $db->derivarRasgosConfiguracion($idCromo);

        echo json_encode(['ok' => true, 'cromo' => $db->obtenerCromoAdmin($idCromo)]);
        break;

    /* --- Calibración de dificultad PvE (migración `033`) ---
       Las dos acciones simulan miles de partidos con el motor real, así que
       tardan segundos, no milisegundos. Se sube el límite de ejecución a
       propósito: con el de 30 s por defecto, calibrar una cadena grande moría
       a media faena y dejaba la mitad de los nodos con el preset nuevo y la
       otra mitad con el viejo — el peor estado posible. */
    case 'calibrar_pve_global':
        set_time_limit(300);
        echo json_encode($db->calibrarPveGlobal($_POST['preset'] ?? ''));
        break;

    case 'calibrar_pve_cadena':
        set_time_limit(300);
        echo json_encode($db->calibrarPveCadena((int) ($_POST['id_cadena'] ?? 0), $_POST['preset'] ?? ''));
        break;

    case 'calibrar_pve_nodo':
        set_time_limit(300);
        echo json_encode($db->calibrarPveNodo((int) ($_POST['id_nodo'] ?? 0), $_POST['preset'] ?? ''));
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Acción desconocida.']);
}
