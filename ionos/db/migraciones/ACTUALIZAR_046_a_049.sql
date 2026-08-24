-- ============================================================================
-- ACTUALIZACIÓN 046 → 049 · TODO EN UN SOLO FICHERO
--
-- Es la unión, en orden, de las cuatro migraciones sueltas de db/migraciones/:
--
--   046  El mundial con sus puestos: posiciones, afinidades, rareza y
--        estadísticas de las siete selecciones importadas, rutas de imagen y
--        la mudanza de la plantilla de Haití a Jamaica.
--   047  El hexágono de afinidad, que no se veía en ninguna parte porque la
--        base apuntaba a `.png` y en disco son `.webp`.
--   048  Deshacer los «nodos finales» que nadie marcó (bug de `isset` en el
--        editor de cadenas).
--   049  Más aire antes del pitido: la espera del servidor sube a 22 s para
--        que quepa la presentación de alineaciones alargada.
--
-- Se puede pasar entero de una vez sobre una base que esté en la 045. Las
-- cuatro son idempotentes por separado y juntas también: son UPDATEs con
-- valores fijos, así que ejecutarlo dos veces deja el mismo resultado.
--
-- ⚠️ HAY VARIOS `SELECT` DE COMPROBACIÓN AL FINAL DE CADA TRAMO. No cambian
--    nada; están para poder mirar el resultado en phpMyAdmin. Si lo ejecutas
--    desde la línea de comandos y te molesta la salida, se pueden borrar.
--
-- ⚠️ LO QUE ESTE FICHERO NO TRAE. Las formaciones nuevas, los pesos por línea
--    y los tiempos de los minijuegos NO son datos de la base: viven en
--    `db/consultas.php` y `db/minijuegos.php`. Para esos basta con subir el
--    código; no hay nada que ejecutar aquí.
-- ============================================================================



-- ==========================================================================
-- TRAMO: 046_mundial_posiciones_y_jamaica.sql
-- ==========================================================================

-- ============================================================================
-- 046 · EL MUNDIAL, CON SUS PUESTOS
--
--   1) Las siete selecciones importadas (Australia, China, Congo, España,
--      Jamaica, Japón y Países Bajos) dejan de ser once medios centros: cada
--      cromo recibe la posición que ocupa en la alineación oficial.
--   2) Y su afinidad, que estaba entera en 'no-afi'.
--   3) Rareza y estadísticas repartidas, en vez de todos Común y del montón.
--   4) Las imágenes apuntan por fin a `InazumaWorldCup/`, donde están.
--   5) Jamaica (equipo 38) recupera su plantilla.
--
-- Serbia (equipo 37) y las nueve exclusivas legendarias (cromos 617-625) NO se
-- tocan: son la referencia de la que sale todo lo demás.
--
-- Idempotente: son UPDATEs por `id_cromo` con valores fijos. Ejecutarla dos
-- veces deja exactamente el mismo resultado.
--
-- ⚠️ DE DÓNDE SALE TODO ESTO.
--    De las ocho fotos numeradas que hay en
--    `assets/img/Cromos/InazumaWorldCup/` (1. China, 2. España, ... 8. Australia).
--    Son capturas de la plantilla en el juego: cada ficha lleva escrito el
--    puesto y, en el cuadradito de al lado, el icono de la afinidad — montaña
--    naranja, llama roja, remolino azul u hoja verde, que son las afinidades
--    1 a 4 de la tabla `afinidad`. La correspondencia está comprobada contra
--    Serbia, cuyos once ya tenían la afinidad puesta a mano y coinciden con su
--    foto uno por uno.
--
--    La del Congo es la excepción: es del creador de alineaciones de
--    zukan.inazuma.jp y ahí las fichas no llevan ni puesto ni afinidad. El
--    puesto sale del dibujo (portero en el área, tres atrás, dos medios y la
--    delantera de cinco en W), y la afinidad se queda en 5 ('no-afi') hasta que
--    haya una captura del juego.
--
-- ⚠️ POR QUÉ JAMAICA Y NO HAITÍ.
--    Los once cromos 573-583 estaban en el equipo 34 ('Haití') porque la
--    carpeta de importación se llamaba `haiti/`. La foto «4. Jamaica.jpg» lista
--    a esos mismos once, y la legendaria de Jamaica (cromo 621) es Maximino
--    Cruz, que es el capitán de esa alineación. Era la misma selección con dos
--    nombres, así que la plantilla se muda al equipo 38 y la carpeta de
--    imágenes pasa de `Haiti/` a `Jamaica/`.
--
--    El equipo 34 se queda solo con la legendaria Keines Elvain (cromo 623),
--    que es legendaria y por tanto queda fuera de esta migración.
--
-- ⚠️ CÓMO SE REPARTEN RAREZA Y ESTADÍSTICAS.
--    Copiando lo que hace Serbia: cada selección reparte sus once cartas entre
--    las rarezas 1-4 (tres, tres, tres y dos), y las notas salen de la media de
--    esa rareza (57 / 63 / 72 / 77) más el sesgo del puesto — el portero para
--    y no marca, el delantero al revés — más un pellizco aleatorio. Ninguna
--    entra en rareza 5+, que es donde viven las exclusivas.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- LAS SIETE SELECCIONES
--
-- `id_equipo` va en todas las filas aunque solo cambie en las de Jamaica: así
-- la migración es la foto completa de dónde tiene que acabar cada cromo y no
-- hace falta cruzarla con el volcado para saberlo.
-- ----------------------------------------------------------------------------

-- AUSTRALIA · 3-6-1 · afinidad de la foto
UPDATE cromos SET posicion='MC', id_equipo=30, id_rareza=2, id_afinidad=1, ataque=56, defensa=62, tecnica=71, imagen='./assets/img/Cromos/InazumaWorldCup/Australia/alec sarker.webp' WHERE id_cromo=529;  -- Alec Sarker
UPDATE cromos SET posicion='MC', id_equipo=30, id_rareza=3, id_afinidad=3, ataque=64, defensa=67, tecnica=79, imagen='./assets/img/Cromos/InazumaWorldCup/Australia/bill sye.webp' WHERE id_cromo=530;  -- Bill Sye
UPDATE cromos SET posicion='DF', id_equipo=30, id_rareza=1, id_afinidad=1, ataque=44, defensa=70, tecnica=57, imagen='./assets/img/Cromos/InazumaWorldCup/Australia/carlam.webp' WHERE id_cromo=531;  -- Carlam Buer
UPDATE cromos SET posicion='MC', id_equipo=30, id_rareza=2, id_afinidad=1, ataque=63, defensa=61, tecnica=66, imagen='./assets/img/Cromos/InazumaWorldCup/Australia/cole bear.webp' WHERE id_cromo=532;  -- Cole Bear
UPDATE cromos SET posicion='DC', id_equipo=30, id_rareza=4, id_afinidad=4, ataque=83, defensa=63, tecnica=75, imagen='./assets/img/Cromos/InazumaWorldCup/Australia/elliot.webp' WHERE id_cromo=533;  -- Elliot Ember
UPDATE cromos SET posicion='POR', id_equipo=30, id_rareza=3, id_afinidad=3, ataque=47, defensa=89, tecnica=69, imagen='./assets/img/Cromos/InazumaWorldCup/Australia/gordon.webp' WHERE id_cromo=534;  -- Jude Gordon
UPDATE cromos SET posicion='DF', id_equipo=30, id_rareza=1, id_afinidad=3, ataque=37, defensa=62, tecnica=58, imagen='./assets/img/Cromos/InazumaWorldCup/Australia/hurley.webp' WHERE id_cromo=535;  -- Hurley Kane
UPDATE cromos SET posicion='MC', id_equipo=30, id_rareza=2, id_afinidad=3, ataque=62, defensa=57, tecnica=67, imagen='./assets/img/Cromos/InazumaWorldCup/Australia/josefina.webp' WHERE id_cromo=536;  -- Josefina
UPDATE cromos SET posicion='MC', id_equipo=30, id_rareza=1, id_afinidad=1, ataque=54, defensa=54, tecnica=65, imagen='./assets/img/Cromos/InazumaWorldCup/Australia/nyira.webp' WHERE id_cromo=537;  -- Nyira Fezun
UPDATE cromos SET posicion='DF', id_equipo=30, id_rareza=3, id_afinidad=2, ataque=65, defensa=90, tecnica=74, imagen='./assets/img/Cromos/InazumaWorldCup/Australia/octavus.webp' WHERE id_cromo=538;  -- Octavus Kraken
UPDATE cromos SET posicion='MC', id_equipo=30, id_rareza=4, id_afinidad=1, ataque=76, defensa=70, tecnica=88, imagen='./assets/img/Cromos/InazumaWorldCup/Australia/seth bael.webp' WHERE id_cromo=539;  -- Seth Bael

-- CHINA · 4-5-1 · afinidad de la foto
UPDATE cromos SET posicion='DF', id_equipo=31, id_rareza=4, id_afinidad=3, ataque=56, defensa=86, tecnica=77, imagen='./assets/img/Cromos/InazumaWorldCup/China/Drakaina.webp' WHERE id_cromo=540;  -- Drakaina Eastwick
UPDATE cromos SET posicion='DF', id_equipo=31, id_rareza=2, id_afinidad=4, ataque=48, defensa=78, tecnica=67, imagen='./assets/img/Cromos/InazumaWorldCup/China/Tortus.webp' WHERE id_cromo=541;  -- Tortus Northe
UPDATE cromos SET posicion='MC', id_equipo=31, id_rareza=1, id_afinidad=2, ataque=52, defensa=55, tecnica=65, imagen='./assets/img/Cromos/InazumaWorldCup/China/chomo.webp' WHERE id_cromo=542;  -- Chomo Ba
UPDATE cromos SET posicion='DF', id_equipo=31, id_rareza=1, id_afinidad=4, ataque=49, defensa=69, tecnica=64, imagen='./assets/img/Cromos/InazumaWorldCup/China/confucia.webp' WHERE id_cromo=543;  -- Connie Fuchsia
UPDATE cromos SET posicion='MC', id_equipo=31, id_rareza=3, id_afinidad=4, ataque=73, defensa=66, tecnica=80, imagen='./assets/img/Cromos/InazumaWorldCup/China/nutmeg.webp' WHERE id_cromo=544;  -- Nat Meg
UPDATE cromos SET posicion='MC', id_equipo=31, id_rareza=1, id_afinidad=2, ataque=51, defensa=50, tecnica=58, imagen='./assets/img/Cromos/InazumaWorldCup/China/phoenix.webp' WHERE id_cromo=545;  -- Phoenix Southers
UPDATE cromos SET posicion='DF', id_equipo=31, id_rareza=2, id_afinidad=4, ataque=52, defensa=77, tecnica=63, imagen='./assets/img/Cromos/InazumaWorldCup/China/pilgreen.webp' WHERE id_cromo=546;  -- Becca Pilgreen
UPDATE cromos SET posicion='MC', id_equipo=31, id_rareza=3, id_afinidad=3, ataque=69, defensa=65, tecnica=75, imagen='./assets/img/Cromos/InazumaWorldCup/China/quincy.webp' WHERE id_cromo=547;  -- Quincy Wang
UPDATE cromos SET posicion='MC', id_equipo=31, id_rareza=4, id_afinidad=1, ataque=77, defensa=72, tecnica=80, imagen='./assets/img/Cromos/InazumaWorldCup/China/ryuun.webp' WHERE id_cromo=548;  -- Ryuun Cho
UPDATE cromos SET posicion='DC', id_equipo=31, id_rareza=3, id_afinidad=1, ataque=84, defensa=57, tecnica=73, imagen='./assets/img/Cromos/InazumaWorldCup/China/steady.webp' WHERE id_cromo=549;  -- Eddy Steady
UPDATE cromos SET posicion='POR', id_equipo=31, id_rareza=2, id_afinidad=1, ataque=45, defensa=81, tecnica=60, imagen='./assets/img/Cromos/InazumaWorldCup/China/taiga west.webp' WHERE id_cromo=550;  -- Taiga West

-- CONGO · 3-2-5 · sin iconos de afinidad en la foto
UPDATE cromos SET posicion='POR', id_equipo=32, id_rareza=2, id_afinidad=5, ataque=38, defensa=86, tecnica=65, imagen='./assets/img/Cromos/InazumaWorldCup/Congo/Udom.webp' WHERE id_cromo=551;  -- Udom Kaochuea
UPDATE cromos SET posicion='DC', id_equipo=32, id_rareza=3, id_afinidad=5, ataque=86, defensa=61, tecnica=78, imagen='./assets/img/Cromos/InazumaWorldCup/Congo/bombasta.webp' WHERE id_cromo=552;  -- Bombasta Flamboyanzi
UPDATE cromos SET posicion='DC', id_equipo=32, id_rareza=3, id_afinidad=5, ataque=79, defensa=57, tecnica=81, imagen='./assets/img/Cromos/InazumaWorldCup/Congo/drago.webp' WHERE id_cromo=553;  -- Drago Hill
UPDATE cromos SET posicion='DF', id_equipo=32, id_rareza=3, id_afinidad=5, ataque=58, defensa=79, tecnica=75, imagen='./assets/img/Cromos/InazumaWorldCup/Congo/dynamo.webp' WHERE id_cromo=554;  -- Drake Dynamo
UPDATE cromos SET posicion='DF', id_equipo=32, id_rareza=1, id_afinidad=5, ataque=47, defensa=65, tecnica=64, imagen='./assets/img/Cromos/InazumaWorldCup/Congo/furman.webp' WHERE id_cromo=555;  -- Dakarai Furman
UPDATE cromos SET posicion='DC', id_equipo=32, id_rareza=4, id_afinidad=5, ataque=84, defensa=58, tecnica=82, imagen='./assets/img/Cromos/InazumaWorldCup/Congo/joey.webp' WHERE id_cromo=556;  -- Joey Beat
UPDATE cromos SET posicion='DC', id_equipo=32, id_rareza=4, id_afinidad=5, ataque=88, defensa=61, tecnica=78, imagen='./assets/img/Cromos/InazumaWorldCup/Congo/moai.webp' WHERE id_cromo=557;  -- Moai
UPDATE cromos SET posicion='DC', id_equipo=32, id_rareza=1, id_afinidad=5, ataque=69, defensa=42, tecnica=65, imagen='./assets/img/Cromos/InazumaWorldCup/Congo/montayne.webp' WHERE id_cromo=558;  -- Adam Montayne
UPDATE cromos SET posicion='MC', id_equipo=32, id_rareza=2, id_afinidad=5, ataque=63, defensa=56, tecnica=73, imagen='./assets/img/Cromos/InazumaWorldCup/Congo/palme.webp' WHERE id_cromo=559;  -- Olivia Palme
UPDATE cromos SET posicion='MC', id_equipo=32, id_rareza=1, id_afinidad=5, ataque=59, defensa=53, tecnica=67, imagen='./assets/img/Cromos/InazumaWorldCup/Congo/richmen.webp' WHERE id_cromo=560;  -- Ulric Richmen
UPDATE cromos SET posicion='DF', id_equipo=32, id_rareza=2, id_afinidad=5, ataque=45, defensa=70, tecnica=66, imagen='./assets/img/Cromos/InazumaWorldCup/Congo/tivator.webp' WHERE id_cromo=561;  -- Cole Tivator

-- ESPAÑA · 5-4-1 · afinidad de la foto
UPDATE cromos SET posicion='MC', id_equipo=33, id_rareza=1, id_afinidad=2, ataque=57, defensa=59, tecnica=64, imagen='./assets/img/Cromos/InazumaWorldCup/España/baek bull-wo.webp' WHERE id_cromo=562;  -- Baek Bull-Wo
UPDATE cromos SET posicion='DF', id_equipo=33, id_rareza=3, id_afinidad=4, ataque=61, defensa=82, tecnica=71, imagen='./assets/img/Cromos/InazumaWorldCup/España/carl gnu.webp' WHERE id_cromo=563;  -- Carl Gnu
UPDATE cromos SET posicion='MC', id_equipo=33, id_rareza=2, id_afinidad=2, ataque=60, defensa=56, tecnica=71, imagen='./assets/img/Cromos/InazumaWorldCup/España/chicho.webp' WHERE id_cromo=564;  -- Chicho Muñoz
UPDATE cromos SET posicion='DF', id_equipo=33, id_rareza=3, id_afinidad=4, ataque=62, defensa=84, tecnica=80, imagen='./assets/img/Cromos/InazumaWorldCup/España/gus martin.webp' WHERE id_cromo=565;  -- Gus Martin
UPDATE cromos SET posicion='MC', id_equipo=33, id_rareza=4, id_afinidad=3, ataque=67, defensa=71, tecnica=83, imagen='./assets/img/Cromos/InazumaWorldCup/España/illojuan.webp' WHERE id_cromo=566;  -- Illojuan
UPDATE cromos SET posicion='DF', id_equipo=33, id_rareza=1, id_afinidad=3, ataque=40, defensa=72, tecnica=57, imagen='./assets/img/Cromos/InazumaWorldCup/España/jose mari.webp' WHERE id_cromo=567;  -- José Mari González
UPDATE cromos SET posicion='POR', id_equipo=33, id_rareza=2, id_afinidad=1, ataque=41, defensa=83, tecnica=61, imagen='./assets/img/Cromos/InazumaWorldCup/España/salvador.webp' WHERE id_cromo=568;  -- Salvador Castell
UPDATE cromos SET posicion='MC', id_equipo=33, id_rareza=3, id_afinidad=4, ataque=70, defensa=62, tecnica=78, imagen='./assets/img/Cromos/InazumaWorldCup/España/sierra.webp' WHERE id_cromo=569;  -- Sierra
UPDATE cromos SET posicion='DC', id_equipo=33, id_rareza=1, id_afinidad=3, ataque=70, defensa=40, tecnica=66, imagen='./assets/img/Cromos/InazumaWorldCup/España/tom skipper.webp' WHERE id_cromo=570;  -- Tom Skipper
UPDATE cromos SET posicion='DF', id_equipo=33, id_rareza=2, id_afinidad=1, ataque=42, defensa=72, tecnica=64, imagen='./assets/img/Cromos/InazumaWorldCup/España/victor garcia.webp' WHERE id_cromo=571;  -- Victor García
UPDATE cromos SET posicion='DF', id_equipo=33, id_rareza=4, id_afinidad=3, ataque=61, defensa=86, tecnica=77, imagen='./assets/img/Cromos/InazumaWorldCup/España/xocas.webp' WHERE id_cromo=572;  -- Xocas

-- JAMAICA · 4-3-3 · afinidad de la foto
UPDATE cromos SET posicion='DF', id_equipo=38, id_rareza=1, id_afinidad=3, ataque=43, defensa=70, tecnica=66, imagen='./assets/img/Cromos/InazumaWorldCup/Jamaica/adam marunga.webp' WHERE id_cromo=573;  -- Adam Marunga
UPDATE cromos SET posicion='MC', id_equipo=38, id_rareza=3, id_afinidad=3, ataque=68, defensa=74, tecnica=80, imagen='./assets/img/Cromos/InazumaWorldCup/Jamaica/ade kebe.webp' WHERE id_cromo=574;  -- Adé Kébé
UPDATE cromos SET posicion='DF', id_equipo=38, id_rareza=4, id_afinidad=2, ataque=66, defensa=89, tecnica=79, imagen='./assets/img/Cromos/InazumaWorldCup/Jamaica/buff bagenal.webp' WHERE id_cromo=575;  -- Buff Bagenal
UPDATE cromos SET posicion='DC', id_equipo=38, id_rareza=3, id_afinidad=2, ataque=76, defensa=51, tecnica=73, imagen='./assets/img/Cromos/InazumaWorldCup/Jamaica/ernest byer.webp' WHERE id_cromo=576;  -- Ernest Byer
UPDATE cromos SET posicion='POR', id_equipo=38, id_rareza=2, id_afinidad=3, ataque=48, defensa=86, tecnica=62, imagen='./assets/img/Cromos/InazumaWorldCup/Jamaica/ewan liner.webp' WHERE id_cromo=577;  -- Ewan Liner
UPDATE cromos SET posicion='DF', id_equipo=38, id_rareza=2, id_afinidad=2, ataque=48, defensa=81, tecnica=67, imagen='./assets/img/Cromos/InazumaWorldCup/Jamaica/gigy maharaja.webp' WHERE id_cromo=578;  -- Gigy Maharaja
UPDATE cromos SET posicion='DF', id_equipo=38, id_rareza=2, id_afinidad=3, ataque=50, defensa=78, tecnica=69, imagen='./assets/img/Cromos/InazumaWorldCup/Jamaica/hillary bush.webp' WHERE id_cromo=579;  -- Hillary Bush
UPDATE cromos SET posicion='DC', id_equipo=38, id_rareza=3, id_afinidad=2, ataque=80, defensa=56, tecnica=69, imagen='./assets/img/Cromos/InazumaWorldCup/Jamaica/jazzy hedgeer.webp' WHERE id_cromo=580;  -- Jazzy Hedgeer
UPDATE cromos SET posicion='DC', id_equipo=38, id_rareza=1, id_afinidad=3, ataque=66, defensa=41, tecnica=57, imagen='./assets/img/Cromos/InazumaWorldCup/Jamaica/juliet.webp' WHERE id_cromo=581;  -- Julieta
UPDATE cromos SET posicion='MC', id_equipo=38, id_rareza=4, id_afinidad=3, ataque=71, defensa=72, tecnica=83, imagen='./assets/img/Cromos/InazumaWorldCup/Jamaica/maximino cruz.webp' WHERE id_cromo=582;  -- Maximino Cruz
UPDATE cromos SET posicion='MC', id_equipo=38, id_rareza=1, id_afinidad=2, ataque=56, defensa=58, tecnica=64, imagen='./assets/img/Cromos/InazumaWorldCup/Jamaica/prisca grav.webp' WHERE id_cromo=583;  -- Prisca Grav

-- JAPÓN · 3-6-1 · afinidad de la foto
UPDATE cromos SET posicion='POR', id_equipo=35, id_rareza=1, id_afinidad=4, ataque=38, defensa=80, tecnica=56, imagen='./assets/img/Cromos/InazumaWorldCup/Japon/Musgo.webp' WHERE id_cromo=584;  -- Lykan Moss
UPDATE cromos SET posicion='MC', id_equipo=35, id_rareza=4, id_afinidad=1, ataque=80, defensa=77, tecnica=82, imagen='./assets/img/Cromos/InazumaWorldCup/Japon/anthropic.webp' WHERE id_cromo=585;  -- Phil Anthropic
UPDATE cromos SET posicion='DF', id_equipo=35, id_rareza=3, id_afinidad=3, ataque=51, defensa=76, tecnica=75, imagen='./assets/img/Cromos/InazumaWorldCup/Japon/claude snap.webp' WHERE id_cromo=586;  -- Claude Snap
UPDATE cromos SET posicion='DF', id_equipo=35, id_rareza=1, id_afinidad=4, ataque=43, defensa=67, tecnica=57, imagen='./assets/img/Cromos/InazumaWorldCup/Japon/heading.webp' WHERE id_cromo=587;  -- Eggbert Heading
UPDATE cromos SET posicion='MC', id_equipo=35, id_rareza=3, id_afinidad=4, ataque=74, defensa=73, tecnica=81, imagen='./assets/img/Cromos/InazumaWorldCup/Japon/horse jr.webp' WHERE id_cromo=588;  -- Chester Horse Jr
UPDATE cromos SET posicion='MC', id_equipo=35, id_rareza=1, id_afinidad=1, ataque=55, defensa=54, tecnica=65, imagen='./assets/img/Cromos/InazumaWorldCup/Japon/libra.webp' WHERE id_cromo=589;  -- Cuba Libra
UPDATE cromos SET posicion='DC', id_equipo=35, id_rareza=2, id_afinidad=4, ataque=77, defensa=54, tecnica=68, imagen='./assets/img/Cromos/InazumaWorldCup/Japon/sart.webp' WHERE id_cromo=590;  -- Moe Sart
UPDATE cromos SET posicion='MC', id_equipo=35, id_rareza=3, id_afinidad=2, ataque=74, defensa=69, tecnica=77, imagen='./assets/img/Cromos/InazumaWorldCup/Japon/scaris.webp' WHERE id_cromo=591;  -- Scaris Cowler
UPDATE cromos SET posicion='MC', id_equipo=35, id_rareza=2, id_afinidad=3, ataque=66, defensa=62, tecnica=75, imagen='./assets/img/Cromos/InazumaWorldCup/Japon/solomon.webp' WHERE id_cromo=592;  -- Solomon Roundhay
UPDATE cromos SET posicion='DF', id_equipo=35, id_rareza=2, id_afinidad=2, ataque=53, defensa=73, tecnica=69, imagen='./assets/img/Cromos/InazumaWorldCup/Japon/verence.webp' WHERE id_cromo=593;  -- Percy Verence
UPDATE cromos SET posicion='MC', id_equipo=35, id_rareza=4, id_afinidad=4, ataque=74, defensa=77, tecnica=85, imagen='./assets/img/Cromos/InazumaWorldCup/Japon/willy glass.webp' WHERE id_cromo=594;  -- Willy Glass

-- PAÍSES BAJOS · 4-5-1 · afinidad de la foto
UPDATE cromos SET posicion='POR', id_equipo=36, id_rareza=2, id_afinidad=3, ataque=46, defensa=86, tecnica=59, imagen='./assets/img/Cromos/InazumaWorldCup/Paises Bajos/Eka.webp' WHERE id_cromo=595;  -- Eka
UPDATE cromos SET posicion='MC', id_equipo=36, id_rareza=3, id_afinidad=2, ataque=71, defensa=65, tecnica=82, imagen='./assets/img/Cromos/InazumaWorldCup/Paises Bajos/aster.webp' WHERE id_cromo=596;  -- Aster
UPDATE cromos SET posicion='MC', id_equipo=36, id_rareza=4, id_afinidad=4, ataque=68, defensa=69, tecnica=79, imagen='./assets/img/Cromos/InazumaWorldCup/Paises Bajos/azrael.webp' WHERE id_cromo=597;  -- Azrael Andrews
UPDATE cromos SET posicion='MC', id_equipo=36, id_rareza=2, id_afinidad=3, ataque=61, defensa=55, tecnica=66, imagen='./assets/img/Cromos/InazumaWorldCup/Paises Bajos/bai long.webp' WHERE id_cromo=598;  -- Bailong
UPDATE cromos SET posicion='DF', id_equipo=36, id_rareza=3, id_afinidad=1, ataque=61, defensa=84, tecnica=80, imagen='./assets/img/Cromos/InazumaWorldCup/Paises Bajos/bjorn.webp' WHERE id_cromo=599;  -- Björn Brocken
UPDATE cromos SET posicion='DF', id_equipo=36, id_rareza=1, id_afinidad=4, ataque=47, defensa=71, tecnica=64, imagen='./assets/img/Cromos/InazumaWorldCup/Paises Bajos/buddy plains.webp' WHERE id_cromo=600;  -- Buddy Plains
UPDATE cromos SET posicion='DF', id_equipo=36, id_rareza=4, id_afinidad=3, ataque=62, defensa=87, tecnica=84, imagen='./assets/img/Cromos/InazumaWorldCup/Paises Bajos/capro lynes.webp' WHERE id_cromo=601;  -- Capro Lynes
UPDATE cromos SET posicion='DC', id_equipo=36, id_rareza=1, id_afinidad=4, ataque=64, defensa=42, tecnica=61, imagen='./assets/img/Cromos/InazumaWorldCup/Paises Bajos/thaddeus.webp' WHERE id_cromo=602;  -- Thaddeus Bellefax
UPDATE cromos SET posicion='DF', id_equipo=36, id_rareza=1, id_afinidad=2, ataque=50, defensa=68, tecnica=63, imagen='./assets/img/Cromos/InazumaWorldCup/Paises Bajos/verne spring.webp' WHERE id_cromo=603;  -- Verne Spring
UPDATE cromos SET posicion='MC', id_equipo=36, id_rareza=3, id_afinidad=2, ataque=67, defensa=70, tecnica=85, imagen='./assets/img/Cromos/InazumaWorldCup/Paises Bajos/wenel.webp' WHERE id_cromo=604;  -- Wenel
UPDATE cromos SET posicion='MC', id_equipo=36, id_rareza=2, id_afinidad=1, ataque=57, defensa=55, tecnica=67, imagen='./assets/img/Cromos/InazumaWorldCup/Paises Bajos/zanark avalonic.webp' WHERE id_cromo=605;  -- Zanark Avalonic


-- ----------------------------------------------------------------------------
-- COMPROBACIÓN
--
-- Cada selección tiene que quedar con once jugadores y un portero, y con las
-- cuatro rarezas repartidas. `sin_afinidad` solo puede valer 11 en el Congo y
-- 0 en el resto.
-- ----------------------------------------------------------------------------
SELECT e.nombre                     AS seleccion,
       COUNT(*)                     AS jugadores,
       SUM(c.posicion = 'POR')      AS porteros,
       SUM(c.posicion = 'DF')       AS defensas,
       SUM(c.posicion = 'MC')       AS medios,
       SUM(c.posicion = 'DC')       AS delanteros,
       COUNT(DISTINCT c.id_rareza)  AS rarezas,
       SUM(c.id_afinidad = 5)       AS sin_afinidad
  FROM cromos c
  JOIN equipos e ON e.id_equipo = c.id_equipo
 WHERE c.id_cromo BETWEEN 529 AND 616
 GROUP BY e.id_equipo, e.nombre
 ORDER BY e.nombre;



-- ==========================================================================
-- TRAMO: 047_afinidad_webp.sql
-- ==========================================================================

-- ============================================================================
-- 047 · EL HEXÁGONO DE AFINIDAD, QUE NO SE VEÍA EN NINGUNA PARTE
--
-- `afinidad.imagen` apuntaba a `.png` y en disco los cuatro iconos son
-- `.webp` desde hace tiempo. Las cuatro rutas daban 404.
--
-- ⚠️ POR QUÉ ESO BORRABA EL HEXÁGONO Y NO SALÍA UNA IMAGEN ROTA.
--    `assets/js/ui.js` escucha `error` de <img> en captura y, cuando la que
--    falla vive dentro de un `.carta-afinidad`, quita el hexágono entero —es
--    deliberado: una afinidad sin gráfico se prefiere invisible antes que con
--    el icono roto del navegador. Con las cuatro rutas muertas, la regla se
--    disparaba SIEMPRE: el hexágono no aparecía ni en las cartas ni en el
--    modal de ficha, y el síntoma que se veía («en el modal del jugador no
--    sale la afinidad») no tenía nada que ver con el modal.
--
--    Por eso esto se arregla en el dato y no en el modal: el modal era el
--    sitio donde más se notaba, no donde estaba el fallo.
--
-- Idempotente: reescribe la ruta entera a partir del nombre del fichero, así
-- que da igual cuántas veces se ejecute y da igual de qué extensión se venga.
-- 'no-afi' se queda con la cadena vacía, que es lo que significa "esta carta
-- no tiene afinidad" y lo que hace que el hexágono no se pinte.
-- ============================================================================

UPDATE afinidad SET imagen = './assets/img/Afinidades/montaña.webp' WHERE id = 1;
UPDATE afinidad SET imagen = './assets/img/Afinidades/fuego.webp'   WHERE id = 2;
UPDATE afinidad SET imagen = './assets/img/Afinidades/aire.webp'    WHERE id = 3;
UPDATE afinidad SET imagen = './assets/img/Afinidades/bosque.webp'  WHERE id = 4;
UPDATE afinidad SET imagen = ''                                     WHERE id = 5;


-- ----------------------------------------------------------------------------
-- COMPROBACIÓN
--
-- Las cuatro afinidades con gráfico tienen que acabar en `.webp`; 'no-afi',
-- vacía. Cualquier otra cosa y el hexágono vuelve a desaparecer.
-- ----------------------------------------------------------------------------
SELECT id, nombre, imagen,
       CASE
         WHEN id = 5 AND imagen = ''            THEN 'ok · sin gráfico a propósito'
         WHEN imagen LIKE '%.webp'              THEN 'ok'
         ELSE '⚠ revisar'
       END AS estado
  FROM afinidad
 ORDER BY id;



-- ==========================================================================
-- TRAMO: 048_reparar_es_final.sql
-- ==========================================================================

-- ============================================================================
-- 048 · DESHACER LOS «NODOS FINALES» QUE NADIE MARCÓ
--
-- Reparación de datos del bug de `assets/ajax/cadena_admin.php`: el endpoint
-- `actualizar_nodo` leía la casilla con `isset($_POST['es_final'])`, y como el
-- editor manda SIEMPRE la clave (vacía cuando está desmarcada), `isset('')` es
-- `true` y cada guardado ponía `es_final = 1`. El código ya está arreglado;
-- esto limpia lo que dejó escrito.
--
-- ⚠️ NO ERA SOLO LA CASILLA. `cadenaCompletada()` pregunta por
--    `es_final = 1`, así que una cadena se daba por completada en cuanto se
--    reclamaba el cofre de CUALQUIER nodo marcado — es decir, del primero. Y
--    `cofreFinalCadena()` cuelga de ahí la recompensa de formación. Con medio
--    mapa marcado como final, las dos cosas respondían al nodo equivocado.
--
-- ⚠️ SOLO SE TOCAN LAS CADENAS CON MÁS DE UN FINAL, y se resetean enteras.
--    Una cadena tiene un final o no tiene ninguno; dos o más es imposible por
--    diseño, así que esas son exactamente las contaminadas y no hay forma de
--    adivinar cuál de todos era el bueno. Las que tienen uno solo se quedan
--    como están: o nunca las tocó el bug, o ya se corrigieron a mano, y en los
--    dos casos el dato es de fiar.
--
--    Después de pasar esto hay que volver a marcar a mano el cofre final de
--    las cadenas afectadas. Sin final marcado, la cadena no se puede completar
--    y no entrega su formación — la consulta del pie dice cuáles se han
--    quedado así.
--
-- Idempotente: en cuanto no queda ninguna cadena con dos finales, no hace nada.
-- ============================================================================

UPDATE cadena_nodos n
  JOIN (
    SELECT id_cadena
      FROM cadena_nodos
     WHERE es_final = 1
     GROUP BY id_cadena
    HAVING COUNT(*) > 1
  ) AS contaminadas ON contaminadas.id_cadena = n.id_cadena
   SET n.es_final = 0
 WHERE n.es_final = 1;


-- ----------------------------------------------------------------------------
-- QUÉ REVISAR A MANO
--
-- Ninguna cadena puede quedar con más de un final. Las que se queden con
-- CERO y tengan cofres son las que hay que volver a marcar en el editor.
-- ----------------------------------------------------------------------------
SELECT c.id_cadena,
       c.nombre,
       SUM(n.es_final = 1)              AS finales,
       SUM(n.tipo = 'cofre')            AS cofres,
       CASE
         WHEN SUM(n.es_final = 1) > 1                             THEN '⚠ sigue contaminada'
         WHEN SUM(n.es_final = 1) = 0 AND SUM(n.tipo = 'cofre') > 0
              THEN '→ marca su cofre final en el editor'
         WHEN SUM(n.es_final = 1) = 1                             THEN 'ok'
         ELSE 'sin cofres, nada que marcar'
       END AS estado
  FROM cadenas c
  JOIN cadena_nodos n ON n.id_cadena = c.id_cadena
 GROUP BY c.id_cadena, c.nombre
 ORDER BY finales DESC, c.id_cadena;



-- ==========================================================================
-- TRAMO: 049_espera_partido.sql
-- ==========================================================================

-- ============================================================================
-- 049 · MÁS AIRE ANTES DEL PITIDO
--
-- `partido_espera_seg` son los segundos que el servidor aguanta a que aparezcan
-- los dos jugadores antes de arrancar el reloj igualmente. Estaba en 15.
--
-- ⚠️ POR QUÉ SUBE. La presentación de alineaciones duraba 7,4 s y no daba
--    tiempo a leer el nombre del rival, su formación, sus cuatro líneas y su
--    aumento antes de que la pantalla pasara a lo siguiente. Alargada a ~11 s
--    —más los hasta 2,5 s de precarga de imágenes— se juntaba peligrosamente
--    con los 15: quien tuviera la conexión regular podía ver cómo el partido
--    arrancaba con la intro todavía en pantalla, perdiendo minutos reales.
--
--    22 s deja margen para la intro completa en el peor caso y sigue siendo
--    corto para el propósito de la espera, que es no dejar a nadie colgado
--    indefinidamente esperando a un rival que no va a venir.
--
-- Idempotente: es un UPDATE con valor fijo sobre una clave concreta.
-- ============================================================================

UPDATE configuracion SET valor = '22' WHERE clave = 'partido_espera_seg';

-- Por si la fila no existiera todavía en alguna instalación.
INSERT INTO configuracion (clave, valor, descripcion)
SELECT 'partido_espera_seg', '22',
       'Segundos que se espera a que aparezcan los dos antes de arrancar igualmente. Quien no esté, se pierde el partido.'
 WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'partido_espera_seg');


-- ----------------------------------------------------------------------------
-- COMPROBACIÓN
-- ----------------------------------------------------------------------------
SELECT clave, valor, descripcion
  FROM configuracion
 WHERE clave = 'partido_espera_seg';


