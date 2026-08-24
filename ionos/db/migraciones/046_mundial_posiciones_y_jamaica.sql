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
