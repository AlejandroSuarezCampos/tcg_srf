<?php
/**
 * CATÁLOGO DE MINIJUEGOS  (Biblia §2)
 *
 * Una entrada por minijuego. El motor NO sabe jugar a ninguno en concreto:
 * lee de aquí qué ofrecer, con qué opciones y con qué consecuencia, de forma
 * que añadir el minijuego número 40 sea añadir un array, no tocar el motor.
 *
 * ---------------------------------------------------------------------------
 * IMPACTO — decisión explícita de Alejandro: lo declara CADA MINIJUEGO, no el
 * tipo de duelo. La Biblia ya lo insinuaba al reservarle a El Momento de la
 * Verdad "un impacto mayor pero acotado a esa jugada" (§1.5 regla 1).
 *
 *   "ninguno"  Solo suma a la puntuación de actuación (§4.6). No cambia nada
 *              de lo que pasa en el campo.
 *   "jugada"   Puede cambiar el DESENLACE DE ESA JUGADA: un gol pasa a parada
 *              o al revés. Cambia el marcador, nunca el ganador.
 *   "partido"  Efecto más allá de la jugada: arrastra a las siguientes.
 *              **CONSTRUIDO** (Grupo G, más abajo). NO mueve la resolución del
 *              duelo —decisión de Alejandro tras medirlo, ver
 *              branding/impacto-partido-analisis.md—: lo que hace es AMPLIAR EL
 *              PRESUPUESTO con el que las jugadas siguientes pueden mover el
 *              marcador, o dar una decisión más.
 *              Exige la clave `efecto`: `presupuesto_gol`, `presupuesto_parada`
 *              o `decision`. El verificador la comprueba, porque un "partido"
 *              sin efecto sería una decisión que promete arrastrar y no arrastra.
 *              Y solo puede CONCEDER: resolverMinijuego() no castiga elegir mal.
 *
 * POR QUÉ "jugada" ES SEGURO EN PvP, verificado en el código, no supuesto:
 * el reparto de la apuesta usa solo `id_ganador`; las misiones cuentan duelos
 * y victorias, nunca goles; y el `rango` (que sí depende del marcador) se
 * calcula únicamente en PvE. En un duelo PvP el marcador no lo lee nadie más
 * que la pantalla que lo pinta. Cambiarlo no toca balance.
 * Si algún día el marcador PvP pasa a valer para algo, hay que releer esto.
 *
 * ---------------------------------------------------------------------------
 * OPCIONES — §1.5 regla 2: siempre una decisión real con consecuencias
 * distintas, nunca un "continuar" disfrazado. Cada opción declara contra qué
 * tipo de remate gana y contra cuál pierde, así que ninguna es dominante.
 *
 * `segura` marca la opción de perfil más conservador. Es la que se aplica sola
 * si el jugador no decide a tiempo (§1.5 regla 4: NUNCA la de más premio).
 *
 * PLAZO — §3.2 palanca 1: la ventana de decisión se estrecha con la
 * dificultad. En PvP no hay dificultad, así que se usa la clave "pvp".
 *
 * ---------------------------------------------------------------------------
 * PRIMITIVA — cómo se ELIGE, no qué se decide (Biblia §2.1). El servidor
 * resuelve igual en los dos casos, así que una entrada puede cambiar de
 * primitiva sin tocar ni el motor ni el verificador.
 *
 *   "eleccion"  tres botones. La primitiva más usada del catálogo.
 *   "medidor"   una aguja recorre las tres zonas en bucle y el jugador la
 *               detiene; la zona donde para ES la opción elegida. Añade una
 *               capa de EJECUCIÓN encima de la de lectura, sin tocar el
 *               equilibrio: las zonas son de ancho igual, así que a ciegas las
 *               tres siguen valiendo 1/3 y el verificador mide lo mismo.
 *
 *               DOS REGLAS PROPIAS, y las dos importan:
 *               · la opción `segura` va EN MEDIO del array `opciones`, porque
 *                 es la única zona que la aguja cruza dos veces por ciclo y por
 *                 tanto la más fácil de acertar. Fallar el pulso tiene que
 *                 dejarte en lo conservador, nunca en lo de más premio: es
 *                 §1.5 regla 4 llevada de la decisión a la ejecución.
 *               · necesita `velocidad`, en milisegundos de ida y vuelta de la
 *                 aguja, con las mismas seis claves que `plazo`. Es su palanca
 *                 de dificultad (§3.2): más rápida, más difícil de cazar.
 *
 *               Con movimiento reducido el cliente cae solo a "eleccion" — una
 *               aguja que hay que cazar es movimiento puro, y sin ella no hay
 *               medidor que jugar (§7: se reduce el movimiento, no el juego).
 *
 *   "zona"      las tres opciones se presentan sobre un MAPA (el marco de la
 *               portería, el área vista desde arriba, el campo) en el sitio que
 *               les corresponde, y el jugador pulsa la zona.
 *
 *               Lo que aporta es LEER LA POSICIÓN de un vistazo, no una capa de
 *               habilidad: la Biblia lo dice sin rodeos en §2.1 —"esto no
 *               requiere ningún tipo de física real: el servidor ya sabe qué
 *               probabilidad tiene cada zona antes de que el jugador elija, y su
 *               elección simplemente selecciona cuál se activa"—. O sea que es
 *               la misma decisión, colocada donde ocurre.
 *
 *               Necesita `lienzo` en la entrada y `zona` en cada opción, con el
 *               vocabulario fijo de ese lienzo (LIENZOS_ZONA, más abajo). Dos
 *               opciones no pueden compartir zona, y el verificador lo comprueba.
 *
 *               No hace falta degradarla: las zonas son <button> de verdad, así
 *               que el teclado ya funciona y no hay nada que animar.
 *
 * OCULTO — qué dato del rival hay que adivinar. El motor lo calcula en
 * servidor y NUNCA lo manda al navegador antes de decidir; lo único que viaja
 * es una pista sobre la tendencia de la carta implicada, que es honesta porque
 * esa misma tendencia es la que sesga el sorteo (§3.3).
 *   "remate"             qué disparo llega          (lo pone el rematador rival)
 *   "estilo_portero"     cómo sale el portero rival (lo pone su portero)
 *   "colocacion_defensa" cómo se planta la defensa  (lo pone su defensa)
 *
 * Los tres valen 3 valores y el motor los reparte a ~1/3 cada uno, así que
 * cualquier entrada con CICLO CERRADO queda equilibrada sola. Ver más abajo.
 *
 * ---------------------------------------------------------------------------
 * TIPOS (opcional) — acota la entrada a ciertos tipos de evento. Hace falta
 * porque una familia mezcla jugadas que no se narran igual: en `balon_parado`
 * caen córners Y faltas, y "¿dónde pones el córner?" sobre una falta se lee
 * como un fallo. Sin la clave, la entrada vale para toda su familia.
 *
 * ---------------------------------------------------------------------------
 * QUÉ HUECOS EXISTEN DE VERDAD — medido sobre 600 partidos simulados, no
 * supuesto. narracionDuelo() solo ofrece decisión si la jugada "tiene sentido"
 * para ese lado: defendiendo exige `tipo === "gol"`, y atacando exige
 * `tipo !== "gol"`. Eso deja fuera muchas familias que el motor sí emite.
 *
 * La unidad que importa NO es (familia, lado) sino (familia, lado, TIPO): una
 * misma familia agrupa desenlaces que no se narran igual, y una entrada sin
 * clave `tipos` se ofrece en todos ellos. Los seis huecos útiles y quién los
 * cubre hoy, con 4 variantes dedicadas cada uno:
 *
 *   defiendo | porteria     | gol         1527   Grupo A   (oculto: remate)
 *   ataco    | disparo      | parada      1488   Grupo B   (estilo_portero)
 *   ataco    | disparo      | tiro_fuera  1173   Grupo B   (estilo_portero)
 *   ataco    | balon_parado | falta       1056   Grupo C   (colocacion_defensa)
 *   ataco    | disparo      | despeje      983   Grupo B2  (colocacion_defensa)
 *   ataco    | balon_parado | corner       703   Grupo C   (colocacion_defensa)
 *
 * Y los que NO sirven:
 *
 *   ataco    | arbitro      | tarjeta      304   ← alcanzable pero INSERVIBLE:
 *                                     el evento de tarjeta no lleva
 *                                     `protagonistas`, así que el dato oculto
 *                                     caería siempre en su valor por defecto y
 *                                     una opción ganaría el 100 %.
 *   defiendo | defensa      | *              0   ← INALCANZABLE: defender exige
 *                                     un gol, y un gol siempre es familia_def
 *                                     "porteria". (El CLAUDE.md la daba por
 *                                     "barata de empezar". No lo es.)
 *   defiendo | balon_parado | *              0   ← mismo motivo.
 *
 * OJO A UNA TRAMPA QUE YA CONFUNDIÓ: el dato oculto NO está atado a la familia.
 * `datoOcultoLoPoneElDefensor()` solo decide de qué alineación se lee, y lo
 * único que hace falta es que el protagonista que se lee exista en ese evento.
 * Por eso el grupo B2 (despeje) adivina la DEFENSA aunque sea familia "disparo":
 * en un despeje quien toca el balón es un defensa, y leer al portero sería leer
 * a quien no interviene.
 *
 * Antes de escribir una entrada para un hueco nuevo, comprueba que llega.
 *
 * ---------------------------------------------------------------------------
 * VARIAS ENTRADAS POR COMBINACIÓN — el motor ya no coge la primera que case:
 * junta todas las candidatas y elige de forma determinista con la semilla del
 * duelo. Es lo que permite que este catálogo crezca (antes, la segunda entrada
 * de una misma familia+lado era código muerto) y además sirve a §1.5 regla 6:
 * repetir el mismo nodo no solo se lee distinto, ahora se JUEGA distinto.
 */
return [

    /* =====================================================================
       MURALLA HUMANA — el minijuego insignia defensivo (§2.4).
       Se dispara cuando el RIVAL genera una ocasión (familia "porteria") y el
       jugador se pone en la piel de su portero.

       El trade-off es el que describe la Biblia: adelantarse cierra el ángulo
       (bien contra un tiro colocado, mal contra un cañonazo), el cuerpo a
       tierra cubre el suelo (bien contra un raso, mal contra uno colocado), y
       la estirada no gana ni pierde fuerte contra nada — es la opción segura.
       ===================================================================== */
    "muralla_humana" => [
        "nombre"    => "Muralla Humana",
        "familia"   => "porteria",
        "lado"      => "defiendo",
        /* Solo sobre el GOL, y la clave es obligatoria desde que defender dejó
           de exigir un gol (§Grupo E). Sin ella esta entrada se ofrecería también
           al defender una PARADA, que comparte familia_def "porteria" — y ahí no
           hay gol que quitar: gastaría presupuesto de marcador para no poder
           mover nada, y el enunciado ("¿cómo sales?") hablaría de una jugada que
           tu portero ya ha resuelto. */
        "tipos"     => ["gol"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",     // §2.1, tercera primitiva
        "impacto"   => "jugada",
        "titulo"    => "Ocasión del rival",
        "enunciado" => "{jugador} encara a {portero}. ¿Cómo sales?",

        // Qué remate llega. Se deduce de la carta que remata, no se sortea a
        // ciegas: así la pista de §3.2 (palanca 3) puede ser honesta.
        "remates" => ["potente", "colocado", "raso"],

        /* CICLO CERRADO: cada opción para EXACTAMENTE un tipo de remate, y
           cada tipo de remate lo para exactamente una opción. No es un detalle
           estético — con la primera versión (cada opción ganaba a uno y perdía
           contra otro) el remate "potente" no lo paraba ninguna de las tres, o
           sea que un tercio de las jugadas estaban decididas antes de elegir.

           Ninguna opción es mejor que otra a ciegas: las tres paran 1 de cada
           3 remates. Lo que separa a un jugador de otro es LEER la pista, que
           se calcula de la carta que remata y por tanto es honesta (§3.3). */
        "opciones" => [
            [
                "clave"  => "adelantarse",
                "nombre" => "Achicar el ángulo",
                "pista"  => "Le comes la portería a un tiro colocado.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "cuerpo_a_tierra",
                "nombre" => "Cuerpo a tierra",
                "pista"  => "Aguantas el cañonazo con el cuerpo.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "estirada",
                "nombre" => "Estirada",
                "pista"  => "Llegas abajo, a los rasos.",
                "gana"   => "raso",
                // La que se aplica sola al agotarse el plazo (§1.5 regla 4).
                // No es la mejor ni la peor: para 1 de cada 3 como las demás,
                // así que no decidir nunca es mejor que decidir bien.
                "segura" => true,
            ],
        ],

        /* Segundos para decidir. §3.2: de 10-12 en Fácil a 3-4 en Extremo.
           En PvP el plazo es más corto que en Fácil a propósito: aquí el reloj
           está parado para los DOS, así que cada segundo que tardas en decidir
           es un segundo que el rival pasa mirando. En una cadena juegas solo y
           puedes pensarte lo que quieras. */
        "plazo" => [
            "pvp" => 9,
            "facil" => 14, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* =====================================================================
       ELIGE TU VENENO — el espejo ofensivo de Muralla Humana (§2.4).
       Se dispara en una ocasión PROPIA que se iba a fallar: el portero rival
       sale de una manera concreta y tú decides dónde rematar.

       Hacía falta por un hueco de experiencia real: con solo Muralla Humana
       el jugador únicamente decidía cuando atacaba el rival, y nunca tocaba
       nada en sus propias ocasiones — que además son la familia más numerosa
       del motor con diferencia (330 eventos frente a 243 de portería).

       DESVIACIÓN DOCUMENTADA: la Biblia lo describe como decisión binaria
       (cruzado o escuadra). Aquí son tres opciones para que encajen una a una
       con los tres estilos de portero de Muralla Humana. Así las dos entradas
       forman un duelo de lectura simétrico en vez de dos juegos sueltos: lo
       que aprendes parando te sirve para rematar, y al revés.
       ===================================================================== */
    "elige_tu_veneno" => [
        "nombre"    => "Elige tu Veneno",
        "familia"   => "disparo",
        "lado"      => "ataco",
        // Sobre una PARADA: tú disparaste y el portero llegó. La pregunta es
        // dónde la pusiste, así que el que te lee es él.
        "tipos"     => ["parada"],
        "oculto"    => "estilo_portero",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Ocasión tuya",
        "enunciado" => "{jugador} se queda solo ante {portero}. ¿Dónde la pones?",

        // Cómo sale el portero rival. Se deduce de SU carta, igual que el
        // remate se deduce de la del rematador.
        "estilos" => ["achica", "tierra", "espera"],

        "opciones" => [
            [
                "clave"  => "picada",
                "nombre" => "Picarla",
                "pista"  => "Por encima del que se lanza a achicar.",
                "gana"   => "achica",
            ],
            [
                "clave"  => "alto",
                "nombre" => "Arriba",
                "pista"  => "Donde no llega el que se tira al suelo.",
                "gana"   => "tierra",
            ],
            [
                "clave"  => "cruzado",
                "nombre" => "Cruzarla",
                "pista"  => "A la esquina larga, contra el que espera.",
                "gana"   => "espera",
                // El remate por defecto si se agota el plazo: el más corriente
                // de los tres, ni el más difícil ni el de más premio.
                "segura" => true,
            ],
        ],

        // Mismo criterio que Muralla Humana: en PvP el rival espera contigo.
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 11, "dificil" => 9,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* #####################################################################
       GRUPO A — PORTERÍA, DEFENDIENDO.  Dato oculto: "remate".
       Se disparan sobre el GOL del rival: aciertas y el gol no sube al
       marcador. Son las cuatro maneras distintas de contar la misma
       pregunta ("¿por dónde te la van a meter?"), que es justo lo que pide
       §1.5 regla 6: que repetir no se sienta repetido.

       Todas comparten el ciclo cerrado de Muralla Humana —cada opción para
       exactamente un remate y cada remate lo para exactamente una opción—,
       así que las tres opciones de cada entrada valen 1 de cada 3 a ciegas
       y lo único que separa a un jugador de otro es leer la pista.
       ##################################################################### */

    "mano_cambiada" => [
        "nombre"    => "Mano Cambiada",
        "familia"   => "porteria",
        "lado"      => "defiendo",
        "tipos"     => ["gol"],       // ver la nota de Muralla Humana
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Se te va a la mano contraria",
        "enunciado" => "{jugador} arma la pierna y {portero} tiene que elegir mano.",
        "opciones"  => [
            [
                "clave"  => "blocar",
                "nombre" => "Blocar con el cuerpo",
                "pista"  => "Pones todo detrás del cañonazo.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "volar_arriba",
                "nombre" => "Volar arriba",
                "pista"  => "Subes la mano a donde la colocan.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "mano_baja",
                "nombre" => "Mano abajo",
                "pista"  => "Bajas la mano a la altura del raso.",
                "gana"   => "raso",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 14, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    "lectura_de_cadera" => [
        "nombre"    => "Lectura de Cadera",
        "familia"   => "porteria",
        "lado"      => "defiendo",
        "tipos"     => ["gol"],       // ver la nota de Muralla Humana
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "¿Cuándo te tiras?",
        "enunciado" => "{portero} le mira la cadera a {jugador}. ¿Cuándo te comprometes?",
        // Aquí lo que se elige no es la zona sino el MOMENTO. Misma mecánica,
        // otra pregunta: el que se compromete tarde llega a los colocados, el
        // que sale pronto mata los rasos, y el que se planta aguanta la fuerza.
        "opciones"  => [
            [
                "clave"  => "aguantar_de_pie",
                "nombre" => "Aguantar de pie",
                "pista"  => "No te tiras: llegas al que la coloca.",
                "gana"   => "colocado",
                "segura" => true,
            ],
            [
                "clave"  => "salir_a_los_pies",
                "nombre" => "Salir a los pies",
                "pista"  => "Te echas encima antes de que la levante.",
                "gana"   => "raso",
            ],
            [
                "clave"  => "hacerse_grande",
                "nombre" => "Hacerse grande",
                "pista"  => "Abres brazos y piernas contra el disparo duro.",
                "gana"   => "potente",
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 14, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    "el_ultimo_palmo" => [
        "nombre"    => "El Último Palmo",
        "familia"   => "porteria",
        "lado"      => "defiendo",
        "tipos"     => ["gol"],       // ver la nota de Muralla Humana
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Elige qué palmo cubres",
        "enunciado" => "{jugador} dispara y {portero} no llega a todo. ¿Qué proteges?",
        "opciones"  => [
            [
                "clave"  => "cubrir_el_bajo",
                "nombre" => "El metro de abajo",
                "pista"  => "Nada pasa por el suelo.",
                "gana"   => "raso",
            ],
            [
                "clave"  => "ganar_el_palo",
                "nombre" => "Ganarle el palo",
                "pista"  => "Le tapas la esquina al que busca el palo.",
                "gana"   => "colocado",
                "segura" => true,
            ],
            [
                "clave"  => "sacar_el_cuerpo",
                "nombre" => "Sacar el cuerpo",
                "pista"  => "Te pones delante del pelotazo.",
                "gana"   => "potente",
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 14, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* El Paso Adelante (Biblia §2.4, familia de Portería) — PRIMERA ENTRADA DE
       MEDIDOR del catálogo. La Biblia la describe exactamente así: "medidor
       aplicado a la gestión del achique; adelantarse demasiado poco deja un
       ángulo de tiro amplio, pasarse del punto óptimo deja al portero fuera de
       posición". La aguja ES cuánto sales, así que aquí el medidor no es un
       adorno sobre una elección: es literalmente lo que se decide.

       Fíjate en el orden: `un_paso_corto` va EN MEDIO porque es la segura. */
    "el_paso_adelante" => [
        "nombre"    => "El Paso Adelante",
        "familia"   => "porteria",
        "lado"      => "defiendo",
        "tipos"     => ["gol"],       // ver la nota de Muralla Humana
        "oculto"    => "remate",
        "primitiva" => "medidor",
        "impacto"   => "jugada",
        "titulo"    => "¿Cuánto achicas?",
        "enunciado" => "{jugador} se le va encima a {portero}, que tiene que decidir cuánto sale.",
        "opciones"  => [
            [
                "clave"  => "en_la_linea",
                "nombre" => "Quedarte en la línea",
                "pista"  => "Desde atrás ves venir el cañonazo.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "un_paso_corto",
                "nombre" => "Un paso corto",
                "pista"  => "Cubres el suelo sin descolocarte.",
                "gana"   => "raso",
                "segura" => true,
            ],
            [
                "clave"  => "a_por_todo",
                "nombre" => "Salir a por todo",
                "pista"  => "Le comes la portería al que la coloca.",
                "gana"   => "colocado",
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 14, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
        // Ida y vuelta de la aguja. Un mano a mano es rápido, pero no tanto
        // como una volea: queda por debajo de El Golpe de Primeras.
        "velocidad" => [
            "pvp" => 2100,
            "facil" => 2800, "medio" => 2400, "dificil" => 1900,
            "muy_dificil" => 1500, "extremo" => 1200,
        ],
    ],

    /* #####################################################################
       GRUPO B — DISPARO, ATACANDO.
       Se disparan sobre una ocasión propia FALLADA: aciertas y acaba en gol.

       REPARTIDO POR TIPO DE EVENTO, y no por gusto. La familia "disparo"
       agrupa tres desenlaces que no se narran igual —te la para el portero
       (parada), se te va desviada (tiro_fuera) o te la despeja un defensa
       (despeje)—, y hasta la v7.1 las cuatro entradas de aquí no llevaban
       clave `tipos`, así que se ofrecían indistintamente en los tres. El
       resultado era leerse mal: "cómo la golpeas de lejos" salía sobre un
       balón que despejaba un central, y "no hay tiempo de controlar" sobre un
       tiro que se marchó a la grada.

         · parada     → dato oculto "estilo_portero"     (te lee el portero)
         · tiro_fuera → dato oculto "estilo_portero"     (te lee el portero)
         · despeje    → dato oculto "colocacion_defensa" (te lee el DEFENSA)

       El cambio de dato oculto en `despeje` es lo que lo hace honesto: en esa
       jugada quien toca el balón es un defensa, no el portero, así que leer al
       portero sería leer a quien no interviene. El dato oculto no está atado a
       la familia en ningún sitio (ver datoOcultoLoPoneElDefensor), solo hace
       falta que el protagonista que se lee exista en el evento — y `despeje`
       trae los cuatro papeles resueltos.
       ##################################################################### */

    "el_regate_previo" => [
        "nombre"    => "El Regate Previo",
        "familia"   => "disparo",
        "lado"      => "ataco",
        // También sobre una PARADA, porque el enunciado pone al portero saliendo
        // al balón: es él quien acaba tocándola.
        "tipos"     => ["parada"],
        "oculto"    => "estilo_portero",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "¿Tiras o le regateas?",
        "enunciado" => "{jugador} llega antes que {portero} al balón. ¿Qué haces con él?",
        // La única del grupo donde las tres opciones son ACCIONES distintas y
        // no colocaciones del disparo. Es la que mejor se lee cuando ya has
        // jugado las otras tres del mismo lado.
        "opciones"  => [
            [
                "clave"  => "regatear",
                "nombre" => "Regatearle",
                "pista"  => "Le sales del paso al que se lanza a achicar.",
                "gana"   => "achica",
            ],
            [
                "clave"  => "levantarla",
                "nombre" => "Levantarla",
                "pista"  => "Por encima del que se tira al suelo.",
                "gana"   => "tierra",
            ],
            [
                "clave"  => "tocarla_pronto",
                "nombre" => "Tocarla pronto",
                "pista"  => "La empujas antes de que el que espera se decida.",
                "gana"   => "espera",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 11, "dificil" => 9,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* Cara a Cara con el Destino (Biblia §2.4, familia de Disparo).
       DESVIACIÓN DOCUMENTADA, la misma que Elige tu Veneno: la Biblia lo
       describe binario ("definir con calma" o "disparar ya"); aquí son tres
       para cerrar el ciclo sobre los tres estilos de portero. El tema de la
       entrada es la PRISA, así que su plazo es el más corto del grupo. */
    "cara_a_cara" => [
        "nombre"    => "Cara a Cara con el Destino",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["parada"],
        "oculto"    => "estilo_portero",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Mano a mano",
        "enunciado" => "{jugador} se planta delante de {portero} sin nadie más.",
        "opciones"  => [
            [
                "clave"  => "recortarle",
                "nombre" => "Recortarle",
                "pista"  => "Al que se te echa encima lo dejas sentado.",
                "gana"   => "achica",
            ],
            [
                "clave"  => "pegarla_arriba",
                "nombre" => "Pegarla arriba",
                "pista"  => "Por encima del que se tira al suelo.",
                "gana"   => "tierra",
            ],
            [
                "clave"  => "definirla_cruzada",
                "nombre" => "Definirla cruzada",
                "pista"  => "Colocada al lado que deja el que no se mueve.",
                "gana"   => "espera",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 8,
            "facil" => 12, "medio" => 10, "dificil" => 9,
            "muy_dificil" => 7, "extremo" => 6,
        ],
    ],

    /* El Salto del Depredador (Biblia §2.4): remate de cabeza tras un centro.
       La Biblia lo pide con clic-en-zona y "margen deliberadamente más
       estrecho que con el pie"; sin esa primitiva, lo que traslada la
       dificultad extra es el plazo, más corto que en un remate normal. */
    "salto_depredador" => [
        "nombre"    => "El Salto del Depredador",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["parada"],
        "oculto"    => "estilo_portero",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Le llega el centro a la cabeza",
        "enunciado" => "{asiste} la cuelga y {jugador} salta antes que {portero}.",
        "opciones"  => [
            [
                "clave"  => "peinarla_primero",
                "nombre" => "Peinarla",
                "pista"  => "Un toque sutil antes de que salga a por ella.",
                "gana"   => "achica",
            ],
            [
                "clave"  => "cabezazo_arriba",
                "nombre" => "Cabezazo arriba",
                "pista"  => "Alto, donde no llega el que baja las manos.",
                "gana"   => "tierra",
            ],
            [
                "clave"  => "abajo_al_palo",
                "nombre" => "Abajo, al palo",
                "pista"  => "Picada al palo contra el que aguanta en la línea.",
                "gana"   => "espera",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 8,
            "facil" => 12, "medio" => 11, "dificil" => 9,
            "muy_dificil" => 7, "extremo" => 6,
        ],
    ],

    /* El Golpe de Primeras (Biblia §2.4) — MEDIDOR. La Biblia lo define como
       "medidor puro, sin ninguna elección de zona de por medio, de forma que
       toda la tensión procede únicamente del timing del jugador". Es la entrada
       más rápida del catálogo por eso: la aguja es el propio golpeo. */
    "golpe_de_primeras" => [
        "nombre"    => "El Golpe de Primeras",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["parada"],
        "oculto"    => "estilo_portero",
        "primitiva" => "medidor",
        "impacto"   => "jugada",
        "titulo"    => "Volea, sin controlar",
        "enunciado" => "Le cae de aire a {jugador} y {portero} ya se le viene.",
        "opciones"  => [
            [
                "clave"  => "pegarle_pronto",
                "nombre" => "Pegarle pronto",
                "pista"  => "Antes de que el que se adelanta llegue a ti.",
                "gana"   => "achica",
            ],
            [
                "clave"  => "al_punto",
                "nombre" => "Al punto",
                "pista"  => "Golpeo limpio y colocado al que aguarda.",
                "gana"   => "espera",
                "segura" => true,
            ],
            [
                "clave"  => "levantarla_tarde",
                "nombre" => "Levantarla tarde",
                "pista"  => "Dejas que se tumbe y la subes.",
                "gana"   => "tierra",
            ],
        ],
        "plazo" => [
            "pvp" => 8,
            "facil" => 12, "medio" => 10, "dificil" => 9,
            "muy_dificil" => 7, "extremo" => 6,
        ],
        // La más rápida del catálogo: una volea a la primera no da tiempo.
        "velocidad" => [
            "pvp" => 1500,
            "facil" => 2100, "medio" => 1800, "dificil" => 1400,
            "muy_dificil" => 1100, "extremo" => 900,
        ],
    ],

    "desde_la_frontal" => [
        "nombre"    => "Desde la Frontal",
        "familia"   => "disparo",
        "lado"      => "ataco",
        // Sobre un TIRO FUERA: un disparo lejano que se marchó. Antes esta
        // entrada podía caer sobre un DESPEJE, y "cómo la golpeas de lejos"
        // sobre un balón que despeja un defensa se lee como un fallo.
        "tipos"     => ["tiro_fuera"],
        "oculto"    => "estilo_portero",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Te la dejan en la frontal",
        "enunciado" => "Le cae a {jugador} en la frontal, con {portero} colocándose.",
        "opciones"  => [
            [
                "clave"  => "por_encima",
                "nombre" => "Por encima",
                "pista"  => "Al que se adelanta se le tira por arriba.",
                "gana"   => "achica",
            ],
            [
                "clave"  => "a_la_escuadra",
                "nombre" => "A la escuadra",
                "pista"  => "Donde no llega el que se va al suelo.",
                "gana"   => "tierra",
            ],
            [
                "clave"  => "rosca_al_palo",
                "nombre" => "Rosca al palo largo",
                "pista"  => "La comba se le va del alcance al que espera.",
                "gana"   => "espera",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 11, "dificil" => 9,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    "primer_toque" => [
        "nombre"    => "A Primer Toque",
        "familia"   => "disparo",
        "lado"      => "ataco",
        // También TIRO FUERA: un remate sin controlar que se va desviado es
        // exactamente lo que cuenta este evento.
        "tipos"     => ["tiro_fuera"],
        "oculto"    => "estilo_portero",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "No hay tiempo de controlar",
        "enunciado" => "Le llega a {jugador} y {portero} ya se le viene encima.",
        "opciones"  => [
            [
                "clave"  => "pisar_y_picar",
                "nombre" => "Pisarla y picarla",
                "pista"  => "Le pica al que se echa encima.",
                "gana"   => "achica",
            ],
            [
                "clave"  => "empalmar_arriba",
                "nombre" => "Empalmarla arriba",
                "pista"  => "Arriba, contra el que busca el suelo.",
                "gana"   => "tierra",
            ],
            [
                "clave"  => "abrir_el_pie",
                "nombre" => "Abrir el pie",
                "pista"  => "Colocada, al lado que el que espera deja libre.",
                "gana"   => "espera",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 8,   // a primer toque se decide antes: la prisa es el tema
            "facil" => 12, "medio" => 10, "dificil" => 9,
            "muy_dificil" => 7, "extremo" => 6,
        ],
    ],

    /* El Golpe de Fe (Biblia §2.4): el tiro especulativo desde lejos. La Biblia
       le da "porcentaje bajo de éxito pero sin apenas malus si falla, porque la
       ocasión ya era mala de partida" — eso aquí sale gratis: el minijuego solo
       puede mejorar la jugada, nunca empeorarla (ver resolverMinijuego). */
    "golpe_de_fe" => [
        "nombre"    => "El Golpe de Fe",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["tiro_fuera"],
        "oculto"    => "estilo_portero",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Desde muy lejos",
        "enunciado" => "{jugador} levanta la cabeza y ve a {portero} adelantado.",
        "opciones"  => [
            [
                "clave"  => "globito",
                "nombre" => "Colgársela",
                "pista"  => "Al que sale de su área se le tira por arriba.",
                "gana"   => "achica",
            ],
            [
                "clave"  => "empalmarla_seca",
                "nombre" => "Empalmarla seca",
                "pista"  => "Un latigazo alto contra el que busca el suelo.",
                "gana"   => "tierra",
            ],
            [
                "clave"  => "buscar_el_palo",
                "nombre" => "Buscar el palo",
                "pista"  => "A la escuadra del que no se compromete.",
                "gana"   => "espera",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 14, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* El Efecto Imposible (Biblia §2.4): el tiro con el exterior. Su gracia en
       la Biblia es introducir un trade-off distinto —efecto contra potencia en
       vez de potencia contra precisión—, y eso es lo que separan sus tres
       opciones: cuánta comba le das. */
    "efecto_imposible" => [
        "nombre"    => "El Efecto Imposible",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["tiro_fuera"],
        "oculto"    => "estilo_portero",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Con el exterior",
        "enunciado" => "{jugador} la agarra con el exterior y {portero} calcula la comba.",
        "opciones"  => [
            [
                "clave"  => "comba_corta",
                "nombre" => "Comba corta",
                "pista"  => "Se le va de las manos al que se adelanta.",
                "gana"   => "achica",
            ],
            [
                "clave"  => "sin_rosca_arriba",
                "nombre" => "Sin rosca, arriba",
                "pista"  => "Recta y alta, por donde no cubre el que se tumba.",
                "gana"   => "tierra",
            ],
            [
                "clave"  => "rosca_larga",
                "nombre" => "Rosca larga",
                "pista"  => "La curva se abre lejos del que aguanta el sitio.",
                "gana"   => "espera",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* #####################################################################
       GRUPO B2 — DISPARO SOBRE UN DESPEJE.  Dato oculto: "colocacion_defensa".

       El hueco que faltaba por cubrir con sentido: 983 apariciones medidas en
       las que la ocasión propia muere en el pie de un DEFENSA, no en las manos
       del portero. Leer al portero aquí sería leer a quien no interviene, así
       que estas cuatro entradas adivinan al defensa, igual que el balón parado.

       Mismo ciclo cerrado del grupo C, y por tanto la misma lectura reutilizada:
         · la que salta      se bate POR ABAJO
         · la que aguanta    se bate POR ENCIMA
         · la que sale       se bate A LA ESPALDA

       Las cuatro salen de la familia de Regate y Conducción de la Biblia, que
       hasta ahora no tenía ni una entrada construida.
       ##################################################################### */

    /* El Cara o Cruz del Regate: la Biblia lo señala como "el minijuego clásico
       pedido de forma explícita al inicio de la sesión". Binario allí
       (izquierda o derecha), tres aquí, para cerrar el ciclo. */
    "cara_o_cruz" => [
        "nombre"    => "El Cara o Cruz del Regate",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["despeje"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Te queda {defensa} por delante",
        "enunciado" => "{jugador} encara a {defensa} con el balón cosido al pie.",
        "opciones"  => [
            [
                "clave"  => "enganche_seco",
                "nombre" => "Enganche seco",
                "pista"  => "Por abajo, mientras el que salta está en el aire.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "sombrerito",
                "nombre" => "Sombrerito",
                "pista"  => "Por encima del que aguanta plantado.",
                "gana"   => "aguanta",
            ],
            [
                "clave"  => "al_hueco",
                "nombre" => "Al hueco",
                "pista"  => "A la espalda del que rompe hacia el balón.",
                "gana"   => "sale",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 11, "dificil" => 9,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* La Humillación (Biblia §2.4): el caño. Allí es "binaria de alto riesgo
       sin término medio"; el riesgo real no se puede modelar sin castigo (el
       minijuego nunca empeora la jugada), así que lo que queda es lo que de
       verdad decide: por dónde le pasas el balón. */
    "la_humillacion" => [
        "nombre"    => "La Humillación",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["despeje"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Se la puedes hacer",
        "enunciado" => "{jugador} amaga y {defensa} se le queda a un paso.",
        "opciones"  => [
            [
                "clave"  => "el_cano",
                "nombre" => "El caño",
                "pista"  => "Entre las piernas del que se levanta.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "la_bicicleta",
                "nombre" => "La bicicleta",
                "pista"  => "Amagas hasta levantar al que no se mueve.",
                "gana"   => "aguanta",
            ],
            [
                "clave"  => "la_ruleta",
                "nombre" => "La ruleta",
                "pista"  => "Giras sobre el que se lanza y sales por detrás.",
                "gana"   => "sale",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 8,
            "facil" => 12, "medio" => 10, "dificil" => 9,
            "muy_dificil" => 7, "extremo" => 6,
        ],
    ],

    /* Dentro o Fuera (Biblia §2.4): recortar al interior o abrir a la banda. */
    "dentro_o_fuera" => [
        "nombre"    => "Dentro o Fuera",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["despeje"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "¿Dentro o fuera?",
        "enunciado" => "{jugador} llega a la altura de {defensa} con sitio para elegir.",
        "opciones"  => [
            [
                "clave"  => "recorte_interior",
                "nombre" => "Recorte al interior",
                "pista"  => "Raso hacia dentro, bajo el que despega.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "globo_al_area",
                "nombre" => "Globo al área",
                "pista"  => "Por arriba, sobre el que no se despega del sitio.",
                "gana"   => "aguanta",
            ],
            [
                "clave"  => "abrir_a_la_banda",
                "nombre" => "Abrir a la banda",
                "pista"  => "Al espacio que deja el que va a por el balón.",
                "gana"   => "sale",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 11, "dificil" => 9,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* El Control Mágico (Biblia §2.4): el control orientado o la elástica en la
       recepción de un pase difícil. */
    "control_magico" => [
        "nombre"    => "El Control Mágico",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["despeje"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Le llega un balón difícil",
        "enunciado" => "{asiste} se la manda a {jugador} con {defensa} pegado a la espalda.",
        "opciones"  => [
            [
                "clave"  => "control_al_suelo",
                "nombre" => "Bajarla al suelo",
                "pista"  => "La muerdes abajo, donde el que vuela no llega.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "elastica_alta",
                "nombre" => "Elástica",
                "pista"  => "La levantas sobre el que espera quieto.",
                "gana"   => "aguanta",
            ],
            [
                "clave"  => "dejarla_correr",
                "nombre" => "Dejarla correr",
                "pista"  => "La dejas pasar y arrancas por detrás del que sale.",
                "gana"   => "sale",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 11, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* El Latigazo (Biblia §2.4, familia de Regate) — MEDIDOR. La Biblia le da un
       propósito muy concreto: "premia el timing sobre el número bruto, rompiendo
       el patrón habitual de que más estadística siempre gana". Aquí eso sale
       gratis y de verdad, porque quien decide es tu pulso con la aguja, no la
       diferencia de estadísticas entre las dos cartas. */
    "el_latigazo" => [
        "nombre"    => "El Latigazo",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["despeje"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "medidor",
        "impacto"   => "jugada",
        "titulo"    => "Cambio de ritmo",
        "enunciado" => "{jugador} lleva el balón lanzado con {defensa} midiéndole el paso.",
        "opciones"  => [
            [
                "clave"  => "arrancar_ya",
                "nombre" => "Arrancar ya",
                "pista"  => "Sales por abajo antes de que caiga el que salta.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "cambio_al_punto",
                "nombre" => "Cambio al punto",
                "pista"  => "Le rompes la cintura al que está plantado.",
                "gana"   => "aguanta",
                "segura" => true,
            ],
            [
                "clave"  => "frenar_en_seco",
                "nombre" => "Frenar en seco",
                "pista"  => "El que rompe hacia ti se va largo y lo dejas atrás.",
                "gana"   => "sale",
            ],
        ],
        "plazo" => [
            "pvp" => 8,
            "facil" => 12, "medio" => 11, "dificil" => 9,
            "muy_dificil" => 7, "extremo" => 6,
        ],
        "velocidad" => [
            "pvp" => 1800,
            "facil" => 2500, "medio" => 2100, "dificil" => 1700,
            "muy_dificil" => 1300, "extremo" => 1000,
        ],
    ],

    /* El Escudo Humano (Biblia §2.4, familia de Regate) — MEDIDOR. La Biblia:
       "el medidor sube conforme aguantas la posesión; detenerlo demasiado tarde
       pierde el balón por la presión acumulada, demasiado pronto desperdicia la
       oportunidad de progresar". Las tres zonas son ese pronto / justo / tarde. */
    "escudo_humano" => [
        "nombre"    => "El Escudo Humano",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["despeje"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "medidor",
        "impacto"   => "jugada",
        "titulo"    => "Protege el balón",
        "enunciado" => "{defensa} se le echa al cuerpo a {jugador}, que aguanta de espaldas.",
        "opciones"  => [
            [
                "clave"  => "soltarla_pronto",
                "nombre" => "Soltarla pronto",
                "pista"  => "La sacas rasa antes de que el que salta caiga.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "el_tiempo_justo",
                "nombre" => "El tiempo justo",
                "pista"  => "Te giras por encima del que no cede el sitio.",
                "gana"   => "aguanta",
                "segura" => true,
            ],
            [
                "clave"  => "resistir_de_mas",
                "nombre" => "Resistir de más",
                "pista"  => "Dejas que se lance y sales por su espalda.",
                "gana"   => "sale",
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 11, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
        "velocidad" => [
            "pvp" => 2000,
            "facil" => 2700, "medio" => 2300, "dificil" => 1800,
            "muy_dificil" => 1400, "extremo" => 1100,
        ],
    ],

    /* ¿Sigo o Suelto? (Biblia §2.4, familia de Regate): "continuar corriendo con
       el balón, que da más margen de progresión pero expone a perderlo, o pasarlo
       de inmediato, sin riesgo pero con menor premio". */
    "sigo_o_suelto" => [
        "nombre"    => "¿Sigo o Suelto?",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["despeje"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "¿Sigues o la sueltas?",
        "enunciado" => "{jugador} conduce con espacio y {defensa} viene a cerrarle.",
        "opciones"  => [
            [
                "clave"  => "seguir_conduciendo",
                "nombre" => "Seguir con ella",
                "pista"  => "Pasas por debajo del que se va arriba.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "buscar_la_pared",
                "nombre" => "Buscar la pared",
                "pista"  => "La devuelven por encima del que no se mueve.",
                "gana"   => "aguanta",
            ],
            [
                "clave"  => "soltarla_ya",
                "nombre" => "Soltarla ya",
                "pista"  => "Al compañero libre cuando el otro sale a ti.",
                "gana"   => "sale",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 8,
            "facil" => 12, "medio" => 11, "dificil" => 9,
            "muy_dificil" => 7, "extremo" => 6,
        ],
    ],

    /* El Pase de la Prudencia (Biblia §2.4, familia de Decisiones Negativas):
       "pasar el balón hacia atrás de forma segura frena por completo el ataque
       pero no conlleva ningún riesgo, frente a intentar un pase arriesgado hacia
       adelante, que si sale mal produce una pérdida en una zona particularmente
       peligrosa". El castigo por fallar no se modela —un minijuego nunca empeora
       la jugada, ver resolverMinijuego()—, así que lo que queda es la decisión:
       cuánto arriesgas con el balón bajo presión. */
    "pase_de_prudencia" => [
        "nombre"    => "El Pase de la Prudencia",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["despeje"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Te vienen encima",
        "enunciado" => "{jugador} recibe de espaldas con {defensa} pisándole los talones.",
        "opciones"  => [
            [
                "clave"  => "filtrarla_arriesgada",
                "nombre" => "Filtrarla arriesgada",
                "pista"  => "Rasa por debajo del que se va arriba.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "buscar_al_pivote",
                "nombre" => "Buscar al pivote",
                "pista"  => "Bombeada sobre el que no cede el sitio.",
                "gana"   => "aguanta",
            ],
            [
                "clave"  => "atras_al_portero",
                "nombre" => "Atrás, en seguro",
                "pista"  => "Al que rompe hacia ti le dejas el hueco a su espalda.",
                "gana"   => "sale",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 8,
            "facil" => 12, "medio" => 11, "dificil" => 9,
            "muy_dificil" => 7, "extremo" => 6,
        ],
    ],

    /* La Locura Acrobática (Biblia §2.4): la chilena. La Biblia la quiere "de
       riesgo puro sin gradiente intermedio... construida para ser memorable antes
       que frecuente". Va sobre un TIRO FUERA, que es exactamente el desenlace de
       una chilena que no sale. */
    "locura_acrobatica" => [
        "nombre"    => "La Locura Acrobática",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["tiro_fuera"],
        "oculto"    => "estilo_portero",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Le queda a media altura",
        "enunciado" => "El balón le llega alto a {jugador}, de espaldas, con {portero} atento.",
        "opciones"  => [
            [
                "clave"  => "chilena",
                "nombre" => "Chilena",
                "pista"  => "Sorprende al que se ha adelantado a por el centro.",
                "gana"   => "achica",
            ],
            [
                "clave"  => "tijera_arriba",
                "nombre" => "Tijera arriba",
                "pista"  => "Alta, por donde no llega el que se tumba.",
                "gana"   => "tierra",
            ],
            [
                "clave"  => "bajarla_y_girar",
                "nombre" => "Bajarla y girar",
                "pista"  => "Control y disparo colocado al que aguarda.",
                "gana"   => "espera",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 8,
            "facil" => 12, "medio" => 10, "dificil" => 9,
            "muy_dificil" => 7, "extremo" => 6,
        ],
    ],

    /* #####################################################################
       GRUPO C — BALÓN PARADO, ATACANDO.  Dato oculto: "colocacion_defensa".
       Familia entera SIN ESTRENAR hasta ahora, y la segunda más frecuente de
       las alcanzables (1101 de 4593 huecos medidos, ~24 %).

       Aquí el rival al que lees no es el portero sino el DEFENSA, y no por
       gusto: en una falta el motor no reparte portero, así que un minijuego
       que lo leyera devolvería siempre el mismo valor y dejaría una opción
       ganando el 100 % de las faltas. El defensa está en las dos jugadas de
       la familia.

       Ciclo cerrado sobre {salta, aguanta, sale}:
         · la que salta      se bate POR ABAJO
         · la que aguanta    se bate POR ENCIMA
         · la que sale       se bate A LA ESPALDA
       ##################################################################### */

    "la_barrera" => [
        "nombre"    => "La Barrera",
        "familia"   => "balon_parado",
        "lado"      => "ataco",
        "tipos"     => ["falta"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Falta peligrosa",
        "enunciado" => "{jugador} pone el balón y {defensa} ordena la barrera.",
        "opciones"  => [
            [
                "clave"  => "por_debajo",
                "nombre" => "Por debajo",
                "pista"  => "Rasa, bajo los pies de los que saltan.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "por_encima",
                "nombre" => "Por encima",
                "pista"  => "La levantas sobre los que se quedan plantados.",
                "gana"   => "aguanta",
            ],
            [
                "clave"  => "al_hueco",
                "nombre" => "Al hueco",
                "pista"  => "Al espacio que deja el que se adelanta.",
                "gana"   => "sale",
                "segura" => true,
            ],
        ],
        "plazo" => [
            // Un balón parado se piensa: no hay prisa real en el campo y el
            // plazo puede ser más largo que en una ocasión en movimiento.
            "pvp" => 11,
            "facil" => 16, "medio" => 14, "dificil" => 12,
            "muy_dificil" => 10, "extremo" => 8,
        ],
    ],

    "la_pizarra" => [
        "nombre"    => "La Pizarra",
        "familia"   => "balon_parado",
        "lado"      => "ataco",
        "tipos"     => ["falta"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Jugada ensayada",
        "enunciado" => "{jugador} levanta la mano: va ensayada. {defensa} lo ha visto.",
        "opciones"  => [
            [
                "clave"  => "disparo_seco",
                "nombre" => "Disparo seco",
                "pista"  => "Fuerte y baja, por donde saltan.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "centro_medido",
                "nombre" => "Centro medido",
                "pista"  => "Colgada sobre la línea que aguanta.",
                "gana"   => "aguanta",
                "segura" => true,
            ],
            [
                "clave"  => "pase_a_la_espalda",
                "nombre" => "Pase a la espalda",
                "pista"  => "Por detrás del que rompe hacia el balón.",
                "gana"   => "sale",
            ],
        ],
        "plazo" => [
            "pvp" => 11,
            "facil" => 16, "medio" => 14, "dificil" => 12,
            "muy_dificil" => 10, "extremo" => 8,
        ],
    ],

    "el_corner" => [
        "nombre"    => "El Córner",
        "familia"   => "balon_parado",
        "lado"      => "ataco",
        "tipos"     => ["corner"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Saque de esquina",
        "enunciado" => "{jugador} se va a la esquina. {defensa} reparte marcas en el área.",
        "opciones"  => [
            [
                "clave"  => "rasa_al_borde",
                "nombre" => "Rasa al borde",
                "pista"  => "Atrás, lejos de los que se van arriba.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "al_segundo_palo",
                "nombre" => "Al segundo palo",
                "pista"  => "Por encima de la línea que no se mueve.",
                "gana"   => "aguanta",
            ],
            [
                "clave"  => "en_corto",
                "nombre" => "En corto",
                "pista"  => "Aprovechas al que sale a achicar.",
                "gana"   => "sale",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 11,
            "facil" => 16, "medio" => 14, "dificil" => 12,
            "muy_dificil" => 10, "extremo" => 8,
        ],
    ],

    "segunda_jugada" => [
        "nombre"    => "Segunda Jugada",
        "familia"   => "balon_parado",
        "lado"      => "ataco",
        "tipos"     => ["corner"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "El rechace vuelve fuera",
        "enunciado" => "Despejan corto y le cae a {jugador}, con {defensa} recomponiéndose.",
        "opciones"  => [
            [
                "clave"  => "volea_de_primeras",
                "nombre" => "Volea de primeras",
                "pista"  => "Le pegas antes de que caigan los que saltaron.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "colgarla_otra_vez",
                "nombre" => "Colgarla otra vez",
                "pista"  => "De vuelta al área, sobre los que aguantan.",
                "gana"   => "aguanta",
                "segura" => true,
            ],
            [
                "clave"  => "filtrarla",
                "nombre" => "Filtrarla",
                "pista"  => "Entre líneas, tras el que se ha adelantado.",
                "gana"   => "sale",
            ],
        ],
        "plazo" => [
            "pvp" => 10,
            "facil" => 15, "medio" => 13, "dificil" => 11,
            "muy_dificil" => 9, "extremo" => 8,
        ],
    ],

    /* Bombardeo Aéreo (Biblia §2.4): la falta lateral colgada al área. La
       Biblia hace que la zona elegida decida "qué carta concreta recibe la
       oportunidad de rematar"; eso exige elegir protagonista después del
       enunciado, que es justo lo que el contrato prohíbe (los protagonistas
       viajan ya resueltos con el evento, §15.1), así que aquí la zona decide
       contra qué colocación de la defensa gana, no quién remata.

       PRIMITIVA "zona": es la entrada del catálogo que más literalmente elegía
       un SITIO —primer palo, punto de penalti, al área—, así que enseñarla sobre
       el área vista desde arriba es leer la jugada en vez de leer tres frases.
       Nació como `eleccion` porque la primitiva no existía todavía; el cambio es
       una clave y las zonas, nada más. */
    "bombardeo_aereo" => [
        "nombre"    => "Bombardeo Aéreo",
        "familia"   => "balon_parado",
        "lado"      => "ataco",
        "tipos"     => ["falta"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "zona",
        "lienzo"    => "area",
        "impacto"   => "jugada",
        "titulo"    => "Falta lateral para colgarla",
        "enunciado" => "{jugador} la pone en el área con {defensa} dirigiendo la línea.",
        "opciones"  => [
            [
                "clave"  => "al_primer_palo",
                "nombre" => "Al primer palo",
                "pista"  => "Tensa y baja, por debajo de los que despegan.",
                "gana"   => "salta",
                "zona"   => "primer_palo",
            ],
            [
                "clave"  => "colgarla_al_area",
                "nombre" => "Al segundo palo",
                "pista"  => "Alta, sobre la línea que no se despega del sitio.",
                "gana"   => "aguanta",
                "zona"   => "segundo_palo",
            ],
            [
                "clave"  => "al_punto_de_penalti",
                "nombre" => "Al punto de penalti",
                "pista"  => "Al hueco que abre el que rompe hacia el balón.",
                "gana"   => "sale",
                "segura" => true,
                "zona"   => "punto_penalti",
            ],
        ],
        "plazo" => [
            "pvp" => 10,
            "facil" => 15, "medio" => 13, "dificil" => 11,
            "muy_dificil" => 9, "extremo" => 8,
        ],
    ],

    /* El Doble Engaño (Biblia §2.4): el tiro libre indirecto con señuelo. En la
       Biblia el señuelo puede "robar la marca de un defensor concreto"; aquí eso
       se traduce en que la opción del señuelo es la que gana al defensa que
       ROMPE, porque es al que de verdad se puede arrastrar fuera de sitio. */
    "doble_engano" => [
        "nombre"    => "El Doble Engaño",
        "familia"   => "balon_parado",
        "lado"      => "ataco",
        "tipos"     => ["falta"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Falta ensayada",
        "enunciado" => "{jugador} y {asiste} se colocan sobre el balón; {defensa} no sabe quién le pega.",
        "opciones"  => [
            [
                "clave"  => "tocarla_en_corto",
                "nombre" => "Tocarla en corto",
                "pista"  => "Rasa al compañero mientras la barrera despega.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "golpeo_por_encima",
                "nombre" => "Pegarle por encima",
                "pista"  => "Sobre la barrera que aguanta el sitio.",
                "gana"   => "aguanta",
            ],
            [
                "clave"  => "senuelo_y_hueco",
                "nombre" => "Señuelo y hueco",
                "pista"  => "El señuelo se lleva al que sale y tú pasas por ahí.",
                "gana"   => "sale",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 11,
            "facil" => 16, "medio" => 14, "dificil" => 12,
            "muy_dificil" => 10, "extremo" => 8,
        ],
    ],

    /* Disparo de Francotirador (Biblia §2.4) — MEDIDOR, y la entrada que la
       Biblia describe con las DOS primitivas a la vez: "combina la elección de
       zona de portería con un medidor de potencia que debe detener". Sin
       clic-en-zona construido, lo que queda es el medidor de potencia, que es
       justo la mitad que sí tenemos — y la que la Biblia carga de tensión
       ("esquina alta con potencia alta, el mayor premio y el mayor riesgo").

       Es el medidor más LENTO del catálogo a propósito: una falta directa es un
       momento detenido, con el árbitro midiendo la barrera. */
    "francotirador" => [
        "nombre"    => "Disparo de Francotirador",
        "familia"   => "balon_parado",
        "lado"      => "ataco",
        "tipos"     => ["falta"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "medidor",
        "impacto"   => "jugada",
        "titulo"    => "Falta al borde del área",
        "enunciado" => "{jugador} mide la barrera que ordena {defensa}.",
        "opciones"  => [
            [
                "clave"  => "rosca_baja",
                "nombre" => "Rosca baja",
                "pista"  => "Por debajo de la barrera que despega.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "sobre_la_barrera",
                "nombre" => "Sobre la barrera",
                "pista"  => "La justa para pasar al que aguanta el sitio.",
                "gana"   => "aguanta",
                "segura" => true,
            ],
            [
                "clave"  => "un_misil_al_hueco",
                "nombre" => "Un misil al hueco",
                "pista"  => "Al espacio que abre el que se lanza al balón.",
                "gana"   => "sale",
            ],
        ],
        "plazo" => [
            "pvp" => 11,
            "facil" => 16, "medio" => 14, "dificil" => 12,
            "muy_dificil" => 10, "extremo" => 8,
        ],
        // El más lento: una falta directa se piensa, no se caza.
        "velocidad" => [
            "pvp" => 2600,
            "facil" => 3400, "medio" => 3000, "dificil" => 2400,
            "muy_dificil" => 1900, "extremo" => 1500,
        ],
    ],

    /* Córner de Bolsillo (Biblia §2.4): el córner cerrado, de margen más
       estrecho. El "margen reducido" de la Biblia se traslada al plazo, que es
       el más corto de los cuatro córners. */
    "corner_de_bolsillo" => [
        "nombre"    => "Córner de Bolsillo",
        "familia"   => "balon_parado",
        "lado"      => "ataco",
        "tipos"     => ["corner"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Córner cerrado",
        "enunciado" => "{jugador} la cierra hacia la portería y {defensa} ajusta la marca.",
        "opciones"  => [
            [
                "clave"  => "cerrarla_al_area_chica",
                "nombre" => "Al área chica",
                "pista"  => "Tensa y rasa entre los que se van arriba.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "combarla_por_encima",
                "nombre" => "Combarla por encima",
                "pista"  => "La curva pasa sobre los que no se mueven.",
                "gana"   => "aguanta",
            ],
            [
                "clave"  => "al_palo_largo",
                "nombre" => "Al palo largo",
                "pista"  => "Donde deja de cubrir el que sale a por ella.",
                "gana"   => "sale",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 14, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* Jugada de Laboratorio (Biblia §2.4): el córner en corto ensayado. */
    "jugada_laboratorio" => [
        "nombre"    => "Jugada de Laboratorio",
        "familia"   => "balon_parado",
        "lado"      => "ataco",
        "tipos"     => ["corner"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "La tenéis ensayada",
        "enunciado" => "{jugador} llama a {asiste} al córner; {defensa} se lo huele.",
        "opciones"  => [
            [
                "clave"  => "en_corto_al_borde",
                "nombre" => "En corto al borde",
                "pista"  => "Al pie, mientras el área entera mira hacia arriba.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "centro_directo_alto",
                "nombre" => "Centro directo",
                "pista"  => "Directa y alta, sobre los que esperan quietos.",
                "gana"   => "aguanta",
            ],
            [
                "clave"  => "atras_para_el_tiro",
                "nombre" => "Atrás para el tiro",
                "pista"  => "A la frontal que abandona el que da un paso al balón.",
                "gana"   => "sale",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 10,
            "facil" => 15, "medio" => 13, "dificil" => 11,
            "muy_dificil" => 9, "extremo" => 8,
        ],
    ],

    /* #####################################################################
       PRIMITIVA "zona" — las opciones sobre el mapa (Biblia §2.1, clic-en-zona).
       Van repartidas por sus huecos como cualquier otra entrada; se agrupan aquí
       solo para poder revisar la primitiva de un vistazo.
       ##################################################################### */

    /* El Cazador de Rebotes (Biblia §2.4): "remate de segunda jugada tras un
       rechace, con una ventana de tiempo muy corta, que recompensa los reflejos
       antes que la puntería fina". De ahí el plazo más corto del hueco. */
    "cazador_de_rebotes" => [
        "nombre"    => "El Cazador de Rebotes",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["parada"],
        "oculto"    => "estilo_portero",
        "primitiva" => "zona",
        "lienzo"    => "porteria",
        "impacto"   => "jugada",
        "titulo"    => "El rechace le queda a {jugador}",
        "enunciado" => "{portero} despeja corto y {jugador} llega al rebote sin pensar.",
        "opciones"  => [
            [
                "clave"  => "por_encima_del_portero",
                "nombre" => "Por encima",
                "pista"  => "Sobre el que ya se ha lanzado a por ti.",
                "gana"   => "achica",
                "zona"   => "centro_alto",
            ],
            [
                "clave"  => "a_la_escuadra",
                "nombre" => "A la escuadra",
                "pista"  => "Arriba, donde no llega el que busca el suelo.",
                "gana"   => "tierra",
                "zona"   => "escuadra_izq",
            ],
            [
                "clave"  => "al_palo_lejano",
                "nombre" => "Al palo lejano",
                "pista"  => "Rasa y lejos del que aguanta plantado.",
                "gana"   => "espera",
                "segura" => true,
                "zona"   => "raso_der",
            ],
        ],
        "plazo" => [
            "pvp" => 7,
            "facil" => 11, "medio" => 9, "dificil" => 8,
            "muy_dificil" => 7, "extremo" => 6,
        ],
    ],

    /* El Cálculo Perfecto (Biblia §2.4): la falta directa con altura calculada.
       Es la única entrada que la Biblia describe combinando DOS primitivas
       —clic-en-zona para el destino y un medidor para la altura sobre la
       barrera—. El contrato admite una por entrada, así que aquí va la mitad de
       la zona, que es la que decide de verdad; la de la altura la cubre
       Disparo de Francotirador, que sí es un medidor sobre el mismo hueco. */
    "calculo_perfecto" => [
        "nombre"    => "El Cálculo Perfecto",
        "familia"   => "balon_parado",
        "lado"      => "ataco",
        "tipos"     => ["falta"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "zona",
        "lienzo"    => "porteria",
        "impacto"   => "jugada",
        "titulo"    => "Falta directa",
        "enunciado" => "{jugador} coloca el balón y mira la barrera de {defensa}.",
        "opciones"  => [
            [
                "clave"  => "por_debajo_de_la_barrera",
                "nombre" => "Por debajo",
                "pista"  => "Rasa bajo los pies de los que despegan.",
                "gana"   => "salta",
                "zona"   => "raso_izq",
            ],
            [
                "clave"  => "justa_por_encima",
                "nombre" => "Justa por encima",
                "pista"  => "La rosca pasa sobre los que no se mueven.",
                "gana"   => "aguanta",
                "zona"   => "centro_alto",
            ],
            [
                "clave"  => "a_la_escuadra_lejana",
                "nombre" => "A la escuadra lejana",
                "pista"  => "Al hueco que deja el que se lanza al balón.",
                "gana"   => "sale",
                "segura" => true,
                "zona"   => "escuadra_der",
            ],
        ],
        "plazo" => [
            "pvp" => 11,
            "facil" => 16, "medio" => 14, "dificil" => 12,
            "muy_dificil" => 10, "extremo" => 8,
        ],
    ],

    /* Uno-Dos Perfecto (Biblia §2.4): la pared con un compañero cercano. La
       Biblia premia combinar con quien comparte afinidad elemental; eso exige
       elegir compañero después del enunciado y los protagonistas viajan ya
       resueltos (§15.1), así que lo que se elige es POR DÓNDE se hace la pared. */
    "uno_dos_perfecto" => [
        "nombre"    => "Uno-Dos Perfecto",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["despeje"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "zona",
        "lienzo"    => "campo",
        "impacto"   => "jugada",
        "titulo"    => "Pared con {asiste}",
        "enunciado" => "{jugador} se la deja a {asiste} con {defensa} cerrándole el paso.",
        "opciones"  => [
            [
                "clave"  => "pared_por_el_centro",
                "nombre" => "Por el centro",
                "pista"  => "Rasa entre las piernas del que despega.",
                "gana"   => "salta",
                "zona"   => "centro",
            ],
            [
                "clave"  => "abrirla_a_la_derecha",
                "nombre" => "Abrirla por fuera",
                "pista"  => "Por encima y alrededor del que aguanta el sitio.",
                "gana"   => "aguanta",
                "zona"   => "banda_der",
            ],
            [
                "clave"  => "cambio_al_otro_lado",
                "nombre" => "Cambio de lado",
                "pista"  => "Al flanco que abandona el que sale a por ti.",
                "gana"   => "sale",
                "segura" => true,
                "zona"   => "banda_izq",
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 11, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* El Punto Débil (Biblia §2.4, familia de Lectura del Rival): "dirigir el
       ataque de forma consciente hacia la zona que está rindiendo peor produce un
       bono mayor que el resto de ocasiones". Aquí la zona es del área, en un
       córner a favor. */
    "el_punto_debil" => [
        "nombre"    => "El Punto Débil",
        "familia"   => "balon_parado",
        "lado"      => "ataco",
        "tipos"     => ["corner"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "zona",
        "lienzo"    => "area",
        "impacto"   => "jugada",
        "titulo"    => "Les falla una zona",
        "enunciado" => "{jugador} ve dónde va corta la marca de {defensa}.",
        "opciones"  => [
            [
                "clave"  => "a_la_frontal",
                "nombre" => "A la frontal",
                "pista"  => "Atrás, con todos los que saltan en el aire.",
                "gana"   => "salta",
                "zona"   => "frontal",
            ],
            [
                "clave"  => "al_segundo",
                "nombre" => "Al segundo palo",
                "pista"  => "Por encima de la línea que no se mueve.",
                "gana"   => "aguanta",
                "zona"   => "segundo_palo",
            ],
            [
                "clave"  => "al_corazon_del_area",
                "nombre" => "Al corazón del área",
                "pista"  => "Al sitio que deja el que rompe hacia el balón.",
                "gana"   => "sale",
                "segura" => true,
                "zona"   => "punto_penalti",
            ],
        ],
        "plazo" => [
            "pvp" => 10,
            "facil" => 15, "medio" => 13, "dificil" => 11,
            "muy_dificil" => 9, "extremo" => 8,
        ],
    ],

    /* #####################################################################
       GRUPO G — IMPACTO "partido": lo que arrastra al resto del encuentro.

       La tercera clase de impacto, y la que la Biblia pide para sus entradas de
       ritmo y moral. El efecto **no toca el desenlace de ninguna jugada**: lo
       que hace un `partido` es AMPLIAR EL PRESUPUESTO con el que las jugadas
       siguientes pueden mover el marcador, o dar una decisión más.

       Desde que el partido decide el duelo, ese presupuesto
       (`partido_presupuesto_marcador`) es un tope de diseño y no el margen que
       dejaba libre un ganador pre-sorteado, así que ampliarlo es una recompensa
       acotada y directa: un gol más de los que puedes mover.

       Cada entrada declara `efecto`, y el verificador lo exige:
         · `presupuesto_gol`    → una ocasión propia más podrá acabar en gol
         · `presupuesto_parada` → un gol del rival más podrá pararse
         · `decision`           → una decisión más en el partido (tope +1)

       ⚠️ SOLO PUEDEN CONCEDER, y no es una elección de diseño mía:
       resolverMinijuego() no castiga elegir mal a propósito ("el minijuego solo
       puede mejorar tu partido, nunca empeorarlo, así que ofrecerlo jamás es una
       trampa"). Eso deja fuera del motor a la familia de Decisiones Negativas de
       la Biblia, donde una rama "solo puede salir peor" — El Baile Provocador,
       Perder los Papeles en su versión con castigo, La Fiesta Peligrosa. No es
       un olvido: es esa regla, y cambiarla es otra decisión.

       El efecto se reconstruye de las filas de `duelo_minijuegos` en cada sondeo
       (ver narracionDuelo), así que los dos jugadores ven lo mismo y sobrevive a
       recargar la página.
       ##################################################################### */

    /* El Grito de Guerra (Biblia §2.4, familia de Táctica y Ritmo): "charla
       motivacional cuando el equipo va perdiendo, tres opciones, cada una con un
       efecto distinto sobre el Momentum, sin tocar las líneas de fuerza".

       El momentum por sí solo es cosmético hoy (§1.4: "no toca ningún cálculo"),
       así que una entrada que solo lo moviera sería hueca. Aquí el acierto se
       traduce en algo real: una ocasión propia más podrá acabar en gol. */
    "grito_de_guerra" => [
        "nombre"    => "El Grito de Guerra",
        "familia"   => "porteria",
        "lado"      => "defiendo",
        "tipos"     => ["gol"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "partido",
        "efecto"    => "presupuesto_gol",
        "titulo"    => "Hay que levantar al equipo",
        "enunciado" => "Acaba de marcar {jugador} y el banquillo pide una reacción.",
        "opciones"  => [
            [
                "clave"  => "transmitir_calma",
                "nombre" => "Transmitir calma",
                "pista"  => "Contra quien pega fuerte, cabeza fría.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "apretar_exigiendo",
                "nombre" => "Apretar exigiendo",
                "pista"  => "Al que la coloca se le gana con intensidad.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "dejar_hacer",
                "nombre" => "Dejar hacer",
                "pista"  => "Si no fue un gran gol, no hay nada que corregir.",
                "gana"   => "raso",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 10,
            "facil" => 15, "medio" => 13, "dificil" => 11,
            "muy_dificil" => 9, "extremo" => 8,
        ],
    ],

    /* Dormir el Partido (Biblia §2.4): "cuando el equipo va ganando por un margen
       pequeño, jugar de forma deliberadamente conservadora priorizando la
       posesión, lo cual baja el ritmo del resto del partido".

       La Biblia lo condiciona a ir ganando por poco; el motor no tiene ese
       contexto, así que va sobre una parada de tu portero, que es el momento en
       que de verdad decides si te echas atrás. El acierto se traduce en poder
       parar un gol más, que es literalmente proteger el resultado. */
    "dormir_el_partido" => [
        "nombre"    => "Dormir el Partido",
        "familia"   => "porteria",
        "lado"      => "defiendo",
        "tipos"     => ["parada"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "partido",
        "efecto"    => "presupuesto_parada",
        "titulo"    => "¿Bajamos el ritmo?",
        "enunciado" => "{portero} despeja y hay un momento para decidir cómo seguir.",
        "opciones"  => [
            [
                "clave"  => "achicar_los_espacios",
                "nombre" => "Juntar las líneas",
                "pista"  => "Al que dispara fuerte se le quita el hueco.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "guardar_el_balon",
                "nombre" => "Guardar el balón",
                "pista"  => "Sin balón no puede colocarla en ningún sitio.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "seguir_igual_de_intenso",
                "nombre" => "Seguir igual",
                "pista"  => "Los rasos ya se están parando; no toques nada.",
                "gana"   => "raso",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 10,
            "facil" => 15, "medio" => 13, "dificil" => 11,
            "muy_dificil" => 9, "extremo" => 8,
        ],
    ],

    /* La Sincronía Perfecta (Biblia §2.4, familia Narrativa): "disponible si el
       jugador tiene activo un nivel alto de algún rasgo de Compos, permite
       potenciarlo de cara a la siguiente jugada, a cambio de gastar ese impulso".

       El "gastar el impulso" sale gratis: el efecto se aplica una vez y ya está.
       La condición de tener compos altas no se comprueba —el motor no la conoce
       en el partido—, así que queda como desviación anotada. El acierto da una
       DECISIÓN más, que es la lectura más fiel de "potenciar la siguiente
       jugada". */
    "sincronia_perfecta" => [
        "nombre"    => "La Sincronía Perfecta",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["parada"],
        "oculto"    => "estilo_portero",
        "primitiva" => "eleccion",
        "impacto"   => "partido",
        "efecto"    => "decision",
        "titulo"    => "El equipo se ha enchufado",
        "enunciado" => "{jugador} y {asiste} se entienden sin mirarse, con {portero} atento.",
        "opciones"  => [
            [
                "clave"  => "acelerar_la_jugada",
                "nombre" => "Acelerar",
                "pista"  => "Antes de que el que se adelanta llegue.",
                "gana"   => "achica",
            ],
            [
                "clave"  => "elevar_el_balon",
                "nombre" => "Elevarla",
                "pista"  => "Sobre el que se tira al suelo.",
                "gana"   => "tierra",
            ],
            [
                "clave"  => "tocar_y_seguir",
                "nombre" => "Tocar y seguir",
                "pista"  => "Paciencia contra el que aguarda plantado.",
                "gana"   => "espera",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 14, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* Jugar con el Reloj (Biblia §2.4, Decisiones Negativas): "perder tiempo
       cuando vas ganando asegura el resultado, pero el árbitro puede detectarlo y
       añadir descuento". El castigo no se modela (el motor no castiga), así que
       queda la parte que sí: asegurar lo que tienes. */
    "jugar_con_el_reloj" => [
        "nombre"    => "Jugar con el Reloj",
        "familia"   => "porteria",
        "lado"      => "defiendo",
        "tipos"     => ["parada"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "partido",
        "efecto"    => "presupuesto_parada",
        "titulo"    => "Se puede dormir el balón",
        "enunciado" => "{portero} tiene el balón y nadie le mete prisa.",
        "opciones"  => [
            [
                "clave"  => "tumbarse_en_el_area",
                "nombre" => "Tumbarse en el área",
                "pista"  => "Al que la revienta le quitas el ritmo.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "sacar_en_largo",
                "nombre" => "Sacar en largo",
                "pista"  => "Lejos del que busca colocarla en corto.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "jugarla_normal",
                "nombre" => "Jugarla normal",
                "pista"  => "No hace falta teatro para los rasos.",
                "gana"   => "raso",
                "segura" => true,
            ],
        ],
        "plazo" => ["pvp" => 10, "facil" => 15, "medio" => 13, "dificil" => 11, "muy_dificil" => 9, "extremo" => 8],
    ],

    /* La Relajación Peligrosa (Biblia §2.4): "tras ir ganando por diferencia
       amplia, el equipo entra en una racha de relajación con malus temporal; el
       jugador solo puede elegir cómo reaccionar, nunca evitarlo". El malus no se
       modela; el acierto mitiga concediendo una parada más. */
    "relajacion_peligrosa" => [
        "nombre"    => "La Relajación Peligrosa",
        "familia"   => "porteria",
        "lado"      => "defiendo",
        "tipos"     => ["gol"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "partido",
        "efecto"    => "presupuesto_parada",
        "titulo"    => "El equipo se ha relajado",
        "enunciado" => "{jugador} marca en una jugada que nadie disputó.",
        "opciones"  => [
            [
                "clave"  => "rotar_la_intensidad",
                "nombre" => "Rotar la intensidad",
                "pista"  => "Piernas frescas contra quien pega fuerte.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "mantener_el_once",
                "nombre" => "Mantener el once",
                "pista"  => "Los que ya leen al que la coloca siguen dentro.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "hablar_sin_cambiar_nada",
                "nombre" => "Hablarlo y seguir",
                "pista"  => "Un aviso basta para los rasos.",
                "gana"   => "raso",
                "segura" => true,
            ],
        ],
        "plazo" => ["pvp" => 10, "facil" => 15, "medio" => 13, "dificil" => 11, "muy_dificil" => 9, "extremo" => 8],
    ],

    /* Crisis en el Vestuario (Biblia §2.4): "dos cartas con afinidades
       incompatibles generan fricción tras una jugada fallida, con malus breve al
       Medio; el jugador solo elige a cuál calma primero el capitán". */
    "crisis_en_el_vestuario" => [
        "nombre"    => "Crisis en el Vestuario",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["tiro_fuera"],
        "oculto"    => "estilo_portero",
        "primitiva" => "eleccion",
        "impacto"   => "partido",
        "efecto"    => "decision",
        "titulo"    => "Se están señalando",
        "enunciado" => "{jugador} la manda fuera y {asiste} le reprocha la decisión.",
        "opciones"  => [
            [
                "clave"  => "calmar_al_que_falla",
                "nombre" => "Calmar al que falló",
                "pista"  => "Volverá a tener el hueco del que se adelanta.",
                "gana"   => "achica",
            ],
            [
                "clave"  => "calmar_al_que_asiste",
                "nombre" => "Calmar al que asistía",
                "pista"  => "Seguirá levantando balones sobre el que se tumba.",
                "gana"   => "tierra",
            ],
            [
                "clave"  => "que_lo_arregle_el_capitan",
                "nombre" => "Que lo lleve el capitán",
                "pista"  => "Sin ruido, contra el portero que aguarda.",
                "gana"   => "espera",
                "segura" => true,
            ],
        ],
        "plazo" => ["pvp" => 9, "facil" => 14, "medio" => 12, "dificil" => 10, "muy_dificil" => 8, "extremo" => 7],
    ],

    /* Abrir o Cerrar el Juego (Biblia §2.4): "la versión simple del cambio de
       formación, pensada para dificultad baja: abrir a las bandas o concentrar
       por el centro". */
    "abrir_o_cerrar" => [
        "nombre"    => "Abrir o Cerrar el Juego",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["despeje"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "partido",
        "efecto"    => "presupuesto_gol",
        "titulo"    => "¿Por dónde insistimos?",
        "enunciado" => "{defensa} corta otra vez y toca decidir por dónde seguir.",
        "opciones"  => [
            [
                "clave"  => "insistir_por_abajo",
                "nombre" => "Insistir por abajo",
                "pista"  => "Rasas bajo los que van al aire.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "colgarla_siempre",
                "nombre" => "Colgarla siempre",
                "pista"  => "Por arriba de los que no ceden el sitio.",
                "gana"   => "aguanta",
            ],
            [
                "clave"  => "abrir_a_las_bandas",
                "nombre" => "Abrir a las bandas",
                "pista"  => "Al espacio que dejan los que rompen al balón.",
                "gana"   => "sale",
                "segura" => true,
            ],
        ],
        "plazo" => ["pvp" => 9, "facil" => 14, "medio" => 12, "dificil" => 10, "muy_dificil" => 8, "extremo" => 7],
    ],

    /* La Furia del Clima (Biblia §2.4, familia Narrativa): "viento, campo
       encharcado o condiciones de torneo ajustan el peso de las líneas; el
       jugador tiene una decisión de adaptación". */
    "furia_del_clima" => [
        "nombre"    => "La Furia del Clima",
        "familia"   => "balon_parado",
        "lado"      => "ataco",
        "tipos"     => ["corner"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "partido",
        "efecto"    => "presupuesto_parada",
        "titulo"    => "El campo está imposible",
        "enunciado" => "El balón pesa y {defensa} tiene el área encharcada.",
        "opciones"  => [
            [
                "clave"  => "jugarla_al_suelo",
                "nombre" => "Todo al suelo",
                "pista"  => "El barro frena a los que despegan.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "buscar_el_bote_raro",
                "nombre" => "Buscar el bote raro",
                "pista"  => "Sobre los que no se mueven, el bote decide.",
                "gana"   => "aguanta",
            ],
            [
                "clave"  => "esperar_el_error",
                "nombre" => "Esperar el error",
                "pista"  => "Al que rompe con este suelo se le va largo.",
                "gana"   => "sale",
                "segura" => true,
            ],
        ],
        "plazo" => ["pvp" => 10, "facil" => 15, "medio" => 13, "dificil" => 11, "muy_dificil" => 9, "extremo" => 8],
    ],

    /* El Milagro Imposible (Biblia §2.4): "reservado a Extremo y a un marcador muy
       desfavorable; activar la remontada total sube el riesgo de todo lo que queda
       pero es la única vía". El contexto de marcador no se comprueba —el motor no
       lo conoce en la jugada—, así que queda como desviación anotada. */
    "milagro_imposible" => [
        "nombre"    => "El Milagro Imposible",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["parada"],
        "oculto"    => "estilo_portero",
        "primitiva" => "eleccion",
        "impacto"   => "partido",
        "efecto"    => "decision",
        "titulo"    => "O ahora o nunca",
        "enunciado" => "{portero} vuelve a salvarla y quedan pocos minutos.",
        "opciones"  => [
            [
                "clave"  => "todos_arriba",
                "nombre" => "Todos arriba",
                "pista"  => "Al que se adelanta se le llena el área.",
                "gana"   => "achica",
            ],
            [
                "clave"  => "centros_constantes",
                "nombre" => "Centros constantes",
                "pista"  => "Por arriba del que se echa al suelo.",
                "gana"   => "tierra",
            ],
            [
                "clave"  => "sin_perder_la_cabeza",
                "nombre" => "Sin perder la cabeza",
                "pista"  => "Paciencia con el que no se compromete.",
                "gana"   => "espera",
                "segura" => true,
            ],
        ],
        "plazo" => ["pvp" => 9, "facil" => 14, "medio" => 12, "dificil" => 10, "muy_dificil" => 8, "extremo" => 7],
    ],

    /* El Cambio de Flanco (Biblia §2.4, Lectura del Rival): "si el rival defiende
       peor por un lado, cambiar el peso del ataque cuesta una adaptación pero
       rinde mejor a partir de ahí". */
    "cambio_de_flanco" => [
        "nombre"    => "El Cambio de Flanco",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["despeje"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "partido",
        "efecto"    => "presupuesto_gol",
        "titulo"    => "Cojean de un lado",
        "enunciado" => "{defensa} llega tarde otra vez por el mismo carril.",
        "opciones"  => [
            [
                "clave"  => "cargar_ese_carril",
                "nombre" => "Cargar ese carril",
                "pista"  => "Por abajo, mientras despegan al otro lado.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "cambiar_de_lado",
                "nombre" => "Cambiar de lado",
                "pista"  => "Por encima de los que se quedan quietos.",
                "gana"   => "aguanta",
            ],
            [
                "clave"  => "alternar_los_dos",
                "nombre" => "Alternar los dos",
                "pista"  => "A la espalda del que rompe a tapar.",
                "gana"   => "sale",
                "segura" => true,
            ],
        ],
        "plazo" => ["pvp" => 9, "facil" => 14, "medio" => 12, "dificil" => 10, "muy_dificil" => 8, "extremo" => 7],
    ],

    /* El Detective del Área (Biblia §2.4): "el único del grupo que recompensa la
       paciencia de observar; cada saque del portero rival da una pista, y acumular
       lecturas desbloquea un bono en el tramo final". La acumulación no se modela
       (no hay estado por observación), así que el acierto concede la decisión
       extra de una vez. */
    "detective_del_area" => [
        "nombre"    => "El Detective del Área",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["parada"],
        "oculto"    => "estilo_portero",
        "primitiva" => "eleccion",
        "impacto"   => "partido",
        "efecto"    => "decision",
        "titulo"    => "Le has cogido el patrón",
        "enunciado" => "Es la tercera vez que {portero} repite el mismo gesto.",
        "opciones"  => [
            [
                "clave"  => "apuntar_que_se_adelanta",
                "nombre" => "Siempre se adelanta",
                "pista"  => "Si sale a comerte, ya lo sabes.",
                "gana"   => "achica",
            ],
            [
                "clave"  => "apuntar_que_se_tumba",
                "nombre" => "Siempre se tumba",
                "pista"  => "Se va al suelo antes de tiempo.",
                "gana"   => "tierra",
            ],
            [
                "clave"  => "apuntar_que_no_se_moja",
                "nombre" => "Nunca se compromete",
                "pista"  => "Aguanta de pie hasta el final.",
                "gana"   => "espera",
                "segura" => true,
            ],
        ],
        "plazo" => ["pvp" => 10, "facil" => 15, "medio" => 13, "dificil" => 11, "muy_dificil" => 9, "extremo" => 8],
    ],

    /* La Última Trinchera (Biblia §2.4): "riesgo máximo, reservado al contexto más
       duro del catálogo: los últimos cinco minutos perdiendo por la mínima contra
       un rival Difícil o Extremo, con el coste de fallo más alto de todos". El
       coste de fallo no se modela y el contexto no se comprueba: quedan anotados. */
    "ultima_trinchera" => [
        "nombre"    => "La Última Trinchera",
        "familia"   => "defensa",
        "lado"      => "defiendo",
        "tipos"     => ["despeje"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "partido",
        "efecto"    => "presupuesto_parada",
        "titulo"    => "No pasa ni uno más",
        "enunciado" => "{defensa} saca otra y el equipo se juega el partido aquí.",
        "opciones"  => [
            [
                "clave"  => "todos_al_area",
                "nombre" => "Todos al área",
                "pista"  => "Cuerpos delante de los que revientan.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "achicar_los_pasillos",
                "nombre" => "Cerrar los pasillos",
                "pista"  => "Sin hueco para los que la colocan.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "aguantar_la_linea_atras",
                "nombre" => "Aguantar la línea",
                "pista"  => "Compactos: las rasas mueren en el barullo.",
                "gana"   => "raso",
                "segura" => true,
            ],
        ],
        "plazo" => ["pvp" => 8, "facil" => 12, "medio" => 11, "dificil" => 9, "muy_dificil" => 7, "extremo" => 6],
    ],

    /* El Segundo Definitivo (Biblia §2.4): "en un descuento ya avanzado se añade un
       tramo extra con una única jugada final de riesgo y recompensa muy altos, el
       cierre narrativo más intenso posible". El tramo extra no se añade —la
       duración la fija `partido_duracion_seg`—, así que va sobre una falta del
       final y lo que concede es una ocasión más. */
    "segundo_definitivo" => [
        "nombre"    => "El Segundo Definitivo",
        "familia"   => "balon_parado",
        "lado"      => "ataco",
        "tipos"     => ["falta"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "eleccion",
        "impacto"   => "partido",
        "efecto"    => "presupuesto_gol",
        "titulo"    => "La última que se juega",
        "enunciado" => "El árbitro avisa: esta falta de {jugador} es la última jugada.",
        "opciones"  => [
            [
                "clave"  => "todo_al_area_chica",
                "nombre" => "Todos al área chica",
                "pista"  => "Rasa entre los que se van arriba.",
                "gana"   => "salta",
            ],
            [
                "clave"  => "que_suba_el_portero",
                "nombre" => "Que suba el portero",
                "pista"  => "Un cuerpo más sobre los que aguantan el sitio.",
                "gana"   => "aguanta",
            ],
            [
                "clave"  => "golpearla_directa",
                "nombre" => "Golpearla directa",
                "pista"  => "Al hueco que abre el que se lanza al balón.",
                "gana"   => "sale",
                "segura" => true,
            ],
        ],
        "plazo" => ["pvp" => 11, "facil" => 16, "medio" => 14, "dificil" => 12, "muy_dificil" => 10, "extremo" => 8],
    ],

    /* #####################################################################
       GRUPO F — EL PENALTI.  Familia propia: `penalti`.

       El evento NO es un tipo nuevo: el motor lo emite con los tipos `gol`,
       `parada` y `tiro_fuera` de siempre y solo cambia la familia, así que el
       marcador sigue naciendo del sorteo y estas entradas se enganchan igual que
       cualquier otra. Frecuencia medida: una de cada 25 ocasiones, ~0,2 por
       partido, como en el fútbol de verdad.

       Aquí hay algo que no pasa en ningún otro hueco: la MISMA jugada da decisión
       a los dos. Si el penalti entra, el que defiende puede sacarlo (Leer la
       Mente); si se falla, el que ataca puede meterlo (El Momento de la Verdad).
       ##################################################################### */

    /* EL MOMENTO DE LA VERDAD — la Biblia lo llama "el minijuego insignia de todo
       el catálogo" (§2.4).

       DESVIACIONES DOCUMENTADAS, y son dos: allí el jugador elige primero QUIÉN
       tira entre sus dos o tres mejores y luego con qué SUPERTÉCNICA. Lo primero
       contradice el contrato —los protagonistas viajan ya resueltos con el evento
       (§15.1), y elegir tirador después del enunciado desdiría el texto que el
       jugador acaba de leer—; lo segundo necesita las supertécnicas como datos, y
       no existen. Queda lo que la propia Biblia pone en el centro de un penalti:
       dónde la pones, sobre la portería dividida en zonas (§2.1). */
    "momento_de_la_verdad" => [
        "nombre"    => "El Momento de la Verdad",
        "familia"   => "penalti",
        "lado"      => "ataco",
        "oculto"    => "estilo_portero",
        "primitiva" => "zona",
        "lienzo"    => "porteria",
        "impacto"   => "jugada",
        "titulo"    => "Penalti para {jugador}",
        "enunciado" => "{jugador} coloca el balón en el punto. Enfrente, {portero}.",
        "opciones"  => [
            [
                "clave"  => "picarla_al_centro",
                "nombre" => "Picarla al centro",
                "pista"  => "Se queda sin nada si se lanza a un lado.",
                "gana"   => "achica",
                "zona"   => "centro_alto",
            ],
            [
                "clave"  => "arriba_a_la_escuadra",
                "nombre" => "Arriba, a la escuadra",
                "pista"  => "Donde no llega el que se tira al suelo.",
                "gana"   => "tierra",
                "zona"   => "escuadra_izq",
            ],
            [
                "clave"  => "rasa_al_palo",
                "nombre" => "Rasa, al palo",
                "pista"  => "Ajustada abajo, contra el que aguanta de pie.",
                "gana"   => "espera",
                "segura" => true,
                "zona"   => "raso_der",
            ],
        ],
        // Un penalti se piensa: es el plazo más largo del catálogo junto al de
        // las faltas directas. La Biblia insiste en que aquí hay TIEMPO.
        "plazo" => [
            "pvp" => 12,
            "facil" => 18, "medio" => 16, "dificil" => 13,
            "muy_dificil" => 11, "extremo" => 9,
        ],
    ],

    /* Leer la Mente (Biblia §2.4): "variante de estilo de portero reservada al
       penalti, distinta de la Muralla Humana general porque en un penalti el
       portero dispone de mucho más tiempo para pensar y de mucha más información
       sobre el lanzador". Eso es literalmente su plazo: el más largo de todos los
       minijuegos defensivos. */
    "leer_la_mente" => [
        "nombre"    => "Leer la Mente",
        "familia"   => "penalti",
        "lado"      => "defiendo",
        "oculto"    => "remate",
        "primitiva" => "zona",
        "lienzo"    => "porteria",
        "impacto"   => "jugada",
        "titulo"    => "Penalti en contra",
        "enunciado" => "{jugador} va a tirar y {portero} elige dónde esperarle.",
        "opciones"  => [
            [
                "clave"  => "cubrir_el_centro",
                "nombre" => "Quedarte en el centro",
                "pista"  => "Si la revienta de frente, ahí la tienes.",
                "gana"   => "potente",
                "zona"   => "centro_bajo",
            ],
            [
                "clave"  => "volar_a_la_escuadra",
                "nombre" => "Volar arriba",
                "pista"  => "A la escuadra que busca el que la coloca.",
                "gana"   => "colocado",
                "zona"   => "escuadra_der",
            ],
            [
                "clave"  => "abajo_al_palo_corto",
                "nombre" => "Abajo, al palo corto",
                "pista"  => "Los rasos mueren en esa mano.",
                "gana"   => "raso",
                "segura" => true,
                "zona"   => "raso_izq",
            ],
        ],
        "plazo" => [
            "pvp" => 12,
            "facil" => 18, "medio" => 16, "dificil" => 13,
            "muy_dificil" => 11, "extremo" => 9,
        ],
    ],

    /* La Mano que Nadie Vio (Biblia §2.4, familia de Decisiones Negativas): "un
       posible penalti en contra tras una mano involuntaria propia; no existe
       ninguna decisión de evitarlo, dado que el evento ya ha ocurrido, la única
       decisión disponible es la de la revisión del VAR desde esta perspectiva
       invertida".

       Encaja aquí y en ningún otro sitio: hasta que existió el penalti este hueco
       —defender una pena máxima que ha entrado— no existía. Y la Biblia dice de
       Ojo de Halcón que una reclamación acertada "anula un gol en contra", que es
       literalmente lo que hace un acierto en este hueco: descontarGolRival(). */
    "la_mano_que_nadie_vio" => [
        "nombre"    => "La Mano que Nadie Vio",
        "familia"   => "penalti",
        "lado"      => "defiendo",
        "tipos"     => ["gol"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "Penalti muy dudoso",
        "enunciado" => "Se lo pitan por una mano de {defensa} que nadie vio, y {jugador} lo transforma.",
        "opciones"  => [
            [
                "clave"  => "que_vean_el_rechace",
                "nombre" => "Que vean el rechace",
                "pista"  => "Si venía un balonazo, le pegó sin querer.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "que_vean_la_distancia",
                "nombre" => "Que vean la distancia",
                "pista"  => "Con un balón medido no había tiempo de retirarla.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "dejarlo_correr",
                "nombre" => "Dejarlo correr",
                "pista"  => "Con un balón a ras de suelo, discutirlo es peor.",
                "gana"   => "raso",
                "segura" => true,
            ],
        ],
        // La Biblia le da a Ojo de Halcón "una ventana de tiempo corta".
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* La Cucharita del Descaro (Biblia §2.4): la Panenka. "Si el portero muerde
       el amago y se lanza, la precisión es extrema; si decide no moverse y
       esperar, el fallo está prácticamente garantizado" — así que la cucharita es
       la opción que gana a los dos porteros que se tiran y pierde contra el que
       aguarda, y por eso NO puede ser la segura. La segura es el golpeo normal. */
    "cucharita_del_descaro" => [
        "nombre"    => "La Cucharita del Descaro",
        "familia"   => "penalti",
        "lado"      => "ataco",
        "oculto"    => "estilo_portero",
        "primitiva" => "eleccion",
        "impacto"   => "jugada",
        "titulo"    => "¿Te atreves?",
        "enunciado" => "{jugador} mira a {portero} y el estadio se calla.",
        "opciones"  => [
            [
                "clave"  => "la_cucharita",
                "nombre" => "La cucharita",
                "pista"  => "Ridículo si no se mueve; obra de arte si se lanza.",
                "gana"   => "achica",
            ],
            [
                "clave"  => "reventarla_arriba_del_todo",
                "nombre" => "Reventarla arriba",
                "pista"  => "Sin sutilezas, donde no llega el que se tumba.",
                "gana"   => "tierra",
            ],
            [
                "clave"  => "golpeo_de_libro",
                "nombre" => "Golpeo de libro",
                "pista"  => "Fuerte y colocada al lado que deja el que aguarda.",
                "gana"   => "espera",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 11,
            "facil" => 17, "medio" => 15, "dificil" => 13,
            "muy_dificil" => 10, "extremo" => 8,
        ],
    ],

    /* #####################################################################
       FAMILIA DS — PRIMITIVA "arrastre" (Biblia §2.2).
       La familia que la Biblia construyó entera alrededor del control táctil de
       Inazuma Eleven en DS: se arrastra desde el balón y el ángulo cae en uno de
       tres sectores predefinidos, que es la opción.

       Las tres opciones declaran `sector` (izquierda | centro | derecha). Son
       sectores IGUALES de 60°, así que a ciegas siguen valiendo 1/3 y el
       equilibrio que mide el verificador no cambia — igual que con el medidor y
       con las zonas: cambia el mando, no la decisión.

       Y los botones siguen en pantalla con esta primitiva: WCAG 2.2 SC 2.5.7
       (Dragging Movements) exige alternativa de un solo puntero, así que son
       parte de la primitiva y no un añadido.
       ##################################################################### */

    /* El Pase Filtrado (Biblia §2.2): "arrastrar hacia la dirección general
       donde se quiere que llegue el pase, apuntando a un compañero". */
    "pase_filtrado" => [
        "nombre"    => "El Pase Filtrado",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["despeje"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "arrastre",
        "impacto"   => "jugada",
        "titulo"    => "Hay un pase entre líneas",
        "enunciado" => "{jugador} levanta la cabeza con {defensa} cerrando el pasillo.",
        "opciones"  => [
            [
                "clave"  => "filtrarla_al_hueco",
                "nombre" => "Al hueco de la izquierda",
                "pista"  => "Raso por debajo del que despega.",
                "gana"   => "salta",
                "sector" => "izquierda",
            ],
            [
                "clave"  => "bombearla_por_dentro",
                "nombre" => "Bombeada por dentro",
                "pista"  => "Por encima del que no cede el sitio.",
                "gana"   => "aguanta",
                "sector" => "centro",
            ],
            [
                "clave"  => "abrirla_al_lado_libre",
                "nombre" => "Al lado libre",
                "pista"  => "Al espacio que deja el que sale al balón.",
                "gana"   => "sale",
                "segura" => true,
                "sector" => "derecha",
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 11, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* El Regate Dirigido (Biblia §2.2): "la evolución granular del Cara o Cruz;
       se arrastra en un arco de direcciones y el sistema compara el ángulo con la
       tendencia oculta del defensor". */
    "regate_dirigido" => [
        "nombre"    => "El Regate Dirigido",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["despeje"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "arrastre",
        "impacto"   => "jugada",
        "titulo"    => "Uno contra uno con {defensa}",
        "enunciado" => "{jugador} se le va encima a {defensa}. ¿Por dónde sales?",
        "opciones"  => [
            [
                "clave"  => "salirle_por_dentro",
                "nombre" => "Por dentro",
                "pista"  => "Rasa bajo el que se va arriba.",
                "gana"   => "salta",
                "sector" => "izquierda",
            ],
            [
                "clave"  => "pasarle_por_encima",
                "nombre" => "Por encima",
                "pista"  => "Se la levantas al que aguanta la posición.",
                "gana"   => "aguanta",
                "sector" => "centro",
            ],
            [
                "clave"  => "irse_por_fuera",
                "nombre" => "Por fuera",
                "pista"  => "A la espalda del que rompe hacia ti.",
                "gana"   => "sale",
                "segura" => true,
                "sector" => "derecha",
            ],
        ],
        "plazo" => [
            "pvp" => 8,
            "facil" => 12, "medio" => 11, "dificil" => 9,
            "muy_dificil" => 7, "extremo" => 6,
        ],
    ],

    /* El Disparo Guiado (Biblia §2.2): "arrastrar desde el balón hacia la
       portería, donde el ángulo y la velocidad del gesto determinan dirección y
       potencia". La velocidad del gesto no se modela: el contrato resuelve por
       sector, y meter la velocidad haría que dos jugadores con el mismo ángulo
       obtuvieran resultados distintos sin poder verlo venir. */
    "disparo_guiado" => [
        "nombre"    => "El Disparo Guiado",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["tiro_fuera"],
        "oculto"    => "estilo_portero",
        "primitiva" => "arrastre",
        "impacto"   => "jugada",
        "titulo"    => "Sitio para armarla",
        "enunciado" => "{jugador} apunta con {portero} ajustando la posición.",
        "opciones"  => [
            [
                "clave"  => "trazo_alto_cruzado",
                "nombre" => "Alto y cruzado",
                "pista"  => "Por encima del que se lanza a achicarte.",
                "gana"   => "achica",
                "sector" => "izquierda",
            ],
            [
                "clave"  => "trazo_recto_arriba",
                "nombre" => "Recto arriba",
                "pista"  => "Donde no llega el que busca el suelo.",
                "gana"   => "tierra",
                "sector" => "centro",
            ],
            [
                "clave"  => "trazo_al_palo",
                "nombre" => "Ajustada al palo",
                "pista"  => "Al lado que descubre el que aguarda.",
                "gana"   => "espera",
                "segura" => true,
                "sector" => "derecha",
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* El Centro al Área (Biblia §2.2): el mismo gesto aplicado a córners y
       centros laterales, "manteniendo la lógica de qué carta recibe la opción de
       rematar según hacia dónde se dirigió el arrastre". */
    "centro_al_area" => [
        "nombre"    => "El Centro al Área",
        "familia"   => "balon_parado",
        "lado"      => "ataco",
        "tipos"     => ["corner"],
        "oculto"    => "colocacion_defensa",
        "primitiva" => "arrastre",
        "impacto"   => "jugada",
        "titulo"    => "Tú pones el centro",
        "enunciado" => "{jugador} se coloca en el córner y mira la marca de {defensa}.",
        "opciones"  => [
            [
                "clave"  => "tensa_al_primero",
                "nombre" => "Tensa al primer palo",
                "pista"  => "Por debajo de los que despegan.",
                "gana"   => "salta",
                "sector" => "izquierda",
            ],
            [
                "clave"  => "colgada_al_corazon",
                "nombre" => "Colgada al centro",
                "pista"  => "Alta, sobre la línea que no se mueve.",
                "gana"   => "aguanta",
                "sector" => "centro",
            ],
            [
                "clave"  => "al_area_grande",
                "nombre" => "Atrás, al área grande",
                "pista"  => "Al sitio que abandona el que sale a por ella.",
                "gana"   => "sale",
                "segura" => true,
                "sector" => "derecha",
            ],
        ],
        "plazo" => [
            "pvp" => 10,
            "facil" => 15, "medio" => 13, "dificil" => 11,
            "muy_dificil" => 9, "extremo" => 8,
        ],
    ],

    /* La Conducción Serpenteante (Biblia §2.2) — DESVIACIÓN DOCUMENTADA, y de las
       grandes. La Biblia la define como el único minijuego de trazo PROLONGADO:
       "no se resuelve con un solo gesto puntual sino con un trazo mantenido
       durante varios segundos... la fidelidad con la que se sigue ese camino
       determina si termina en ocasión clara o en pérdida".
       Eso no cabe en el contrato actual, que resuelve una opción contra un dato
       oculto: haría falta puntuar un recorrido, no elegir. Aquí queda como la
       ELECCIÓN DE RUTA con el mismo gesto, y la fidelidad al trazo sigue sin
       construirse. Si algún día se hace, es una primitiva nueva, no esta. */
    "conduccion_serpenteante" => [
        "nombre"    => "La Conducción Serpenteante",
        "familia"   => "disparo",
        "lado"      => "ataco",
        "tipos"     => ["parada"],
        "oculto"    => "estilo_portero",
        "primitiva" => "arrastre",
        "impacto"   => "jugada",
        "titulo"    => "Se abre una galopada",
        "enunciado" => "{jugador} arranca con espacio y {portero} le sale al paso.",
        "opciones"  => [
            [
                "clave"  => "ruta_por_el_costado",
                "nombre" => "Por el costado",
                "pista"  => "Te abres del que viene a achicarte.",
                "gana"   => "achica",
                "sector" => "izquierda",
            ],
            [
                "clave"  => "ruta_recta_al_area",
                "nombre" => "Recto al área",
                "pista"  => "Le llegas antes de que se tire al suelo.",
                "gana"   => "tierra",
                "sector" => "centro",
            ],
            [
                "clave"  => "ruta_frenando",
                "nombre" => "Frenando y girando",
                "pista"  => "Esperas a que el que aguarda se decida.",
                "gana"   => "espera",
                "segura" => true,
                "sector" => "derecha",
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* #####################################################################
       GRUPO D — ÁRBITRO Y DISCIPLINA.  Dato oculto: "reaccion_rival".
       Hueco: ataco | arbitro | tarjeta, 304 apariciones medidas de cada 600
       partidos (media de una cada dos encuentros).

       LAS PRIMERAS ENTRADAS DEL CATÁLOGO CON `impacto: "ninguno"`. No mueven el
       marcador porque no hay gol que mover: una tarjeta no es una ocasión.
       Cuentan para la puntuación de actuación (§4.6) y ahí se quedan, que es
       exactamente el caso de uso para el que la clave `impacto` existía desde el
       principio — solo que hasta ahora el motor no la leía y una entrada así
       habría sumado un gol de la nada.

       AQUÍ EL LADO ESTÁ DEL REVÉS respecto al resto del catálogo, y hay que
       tenerlo presente para escribir un enunciado que se lea bien:
         · el `lado` del evento es el equipo SANCIONADO — o sea, quien decide
         · {defensa} es TU jugador, el que acaba de ver la tarjeta
         · {jugador} es el RIVAL que está en el suelo, y es a quien se lee

       Ciclo cerrado sobre {protesta, teatro, sigue}, que es cómo se toma la
       falta ese rival. Se deduce de SU carta: mucha Técnica lo alarga en el
       suelo, mucho Ataque se levanta a por el árbitro.

       La Biblia (§2.3) pide explícitamente que esta familia aparezca MENOS que
       las de disparo, "porque un partido dominado por decisiones donde el propio
       jugador puede salir perjudicado sin contrapartida se volvería frustrante".
       Sale gratis: el motor solo emite tarjeta en el 22 % de las faltas.
       ##################################################################### */

    /* Perder los Papeles (Biblia §2.4, familia de Decisiones Negativas). La
       Biblia lo pone como su ejemplo más claro de "una opción solo puede salir
       peor y la otra es completamente neutra". Callarse es la segura. */
    "perder_los_papeles" => [
        "nombre"    => "Perder los Papeles",
        "familia"   => "arbitro",
        "lado"      => "ataco",
        "tipos"     => ["tarjeta"],
        "oculto"    => "reaccion_rival",
        "primitiva" => "eleccion",
        "impacto"   => "ninguno",
        "titulo"    => "Amarilla para {defensa}",
        "enunciado" => "{defensa} ve la tarjeta y {jugador} sigue en el suelo. ¿Qué hacéis?",
        "opciones"  => [
            [
                "clave"  => "senalar_la_pantomima",
                "nombre" => "Señalar la pantomima",
                "pista"  => "Si lo está alargando, que se le vea.",
                "gana"   => "teatro",
            ],
            [
                "clave"  => "hablarle_al_arbitro",
                "nombre" => "Hablarle al árbitro",
                "pista"  => "Con el que se levanta sin drama, se puede razonar.",
                "gana"   => "sigue",
            ],
            [
                "clave"  => "callarse",
                "nombre" => "Callarse",
                "pista"  => "Al que va a ir a por el árbitro, déjale ir solo.",
                "gana"   => "protesta",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 10,
            "facil" => 15, "medio" => 13, "dificil" => 11,
            "muy_dificil" => 9, "extremo" => 8,
        ],
    ],

    /* El Motín (Biblia §2.4): la versión colectiva de Perder los Papeles, "con
       más riesgo pero más premio si el árbitro revierte". */
    "el_motin" => [
        "nombre"    => "El Motín",
        "familia"   => "arbitro",
        "lado"      => "ataco",
        "tipos"     => ["tarjeta"],
        "oculto"    => "reaccion_rival",
        "primitiva" => "eleccion",
        "impacto"   => "ninguno",
        "titulo"    => "Se calienta el partido",
        "enunciado" => "Media plantilla va hacia el árbitro por la tarjeta de {defensa}.",
        "opciones"  => [
            [
                "clave"  => "rodear_al_arbitro",
                "nombre" => "Rodearle en grupo",
                "pista"  => "Sin nadie más gritando, el árbitro os escucha.",
                "gana"   => "sigue",
            ],
            [
                "clave"  => "parar_el_juego",
                "nombre" => "Pedir que se pare",
                "pista"  => "Si está en el suelo de más, que lo atiendan y se vea.",
                "gana"   => "teatro",
            ],
            [
                "clave"  => "mandar_al_capitan",
                "nombre" => "Que hable el capitán",
                "pista"  => "Uno tranquilo gana al rival que llega gritando.",
                "gana"   => "protesta",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 10,
            "facil" => 15, "medio" => 13, "dificil" => 11,
            "muy_dificil" => 9, "extremo" => 8,
        ],
    ],

    /* El Último Aviso (Biblia §2.4): "moderar la intensidad, sin coste inmediato
       pero evitando el riesgo de que la próxima entrada ya se sancione, o
       ignorar el aviso y seguir igual". */
    "el_ultimo_aviso" => [
        "nombre"    => "El Último Aviso",
        "familia"   => "arbitro",
        "lado"      => "ataco",
        "tipos"     => ["tarjeta"],
        "oculto"    => "reaccion_rival",
        "primitiva" => "eleccion",
        "impacto"   => "ninguno",
        "titulo"    => "Aviso serio",
        "enunciado" => "El árbitro avisa a {defensa} de que la próxima va a doble amarilla.",
        "opciones"  => [
            [
                "clave"  => "cambiar_el_marcaje",
                "nombre" => "Cambiar el marcaje",
                "pista"  => "Que no le toque más al que busca el contacto.",
                "gana"   => "teatro",
            ],
            [
                "clave"  => "no_tocar_nada",
                "nombre" => "No tocar nada",
                "pista"  => "Con un rival que va al grano, no hace falta ajustar.",
                "gana"   => "sigue",
            ],
            [
                "clave"  => "bajar_la_intensidad",
                "nombre" => "Bajar la intensidad",
                "pista"  => "Con el partido caliente, mejor no darle motivos.",
                "gana"   => "protesta",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 11,
            "facil" => 16, "medio" => 14, "dificil" => 12,
            "muy_dificil" => 10, "extremo" => 8,
        ],
    ],

    /* El Capricho del Árbitro (Biblia §2.4, familia de Táctica y Ritmo). La
       Biblia lo define como "deliberadamente pequeño y de bajo coste, pensado
       explícitamente como contenido de relleno para partidos donde no conviene
       saturar al jugador con decisiones grandes de forma constante... con un
       efecto mecánico prácticamente mínimo". Impacto "ninguno", como toda la
       familia: lo que se gana es puntuación de actuación. */
    "capricho_del_arbitro" => [
        "nombre"    => "El Capricho del Árbitro",
        "familia"   => "arbitro",
        "lado"      => "ataco",
        "tipos"     => ["tarjeta"],
        "oculto"    => "reaccion_rival",
        "primitiva" => "eleccion",
        "impacto"   => "ninguno",
        "titulo"    => "Se para el juego",
        "enunciado" => "Con {jugador} todavía en el suelo, hay un momento para pedir algo pequeño.",
        "opciones"  => [
            [
                "clave"  => "pedir_que_le_atiendan",
                "nombre" => "Que le atiendan",
                "pista"  => "Si lo está alargando, que entre la camilla.",
                "gana"   => "teatro",
            ],
            [
                "clave"  => "pedir_cambio_de_balon",
                "nombre" => "Cambiar el balón",
                "pista"  => "Con un rival que va al grano, un detalle basta.",
                "gana"   => "sigue",
            ],
            [
                "clave"  => "no_pedir_nada",
                "nombre" => "No pedir nada",
                "pista"  => "Con el partido caliente, mejor no dar motivos.",
                "gana"   => "protesta",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 14, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* Ojo de Halcón (Biblia §2.4): la revisión del VAR. La Biblia le da "una
       ventana de tiempo corta" y un coste por reclamar; el coste no se modela
       (el minijuego nunca empeora nada), así que lo que queda es la ventana —
       es la entrada de plazo más corto del grupo. */
    "ojo_de_halcon" => [
        "nombre"    => "Ojo de Halcón",
        "familia"   => "arbitro",
        "lado"      => "ataco",
        "tipos"     => ["tarjeta"],
        "oculto"    => "reaccion_rival",
        "primitiva" => "eleccion",
        "impacto"   => "ninguno",
        "titulo"    => "Jugada polémica",
        "enunciado" => "La tarjeta de {defensa} admite revisión, y {jugador} no se levanta.",
        "opciones"  => [
            [
                "clave"  => "pedir_revision",
                "nombre" => "Pedir revisión",
                "pista"  => "Si lo está alargando, la repetición lo canta.",
                "gana"   => "teatro",
            ],
            [
                "clave"  => "pedir_su_tarjeta",
                "nombre" => "Pedir la suya",
                "pista"  => "Al que se encara con el árbitro le pueden sacar otra.",
                "gana"   => "protesta",
            ],
            [
                "clave"  => "no_reclamar",
                "nombre" => "No reclamar",
                "pista"  => "Si se levanta y sigue, no hay nada que revisar.",
                "gana"   => "sigue",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 8,
            "facil" => 12, "medio" => 11, "dificil" => 9,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* #####################################################################
       GRUPO E — DEFENDIENDO JUGADAS QUE NO SON GOL.  Dato oculto: "remate".

       Tres huecos que hasta ahora NO EXISTÍAN. Defender exigía `tipo === "gol"`,
       y como un gol siempre es familia_def "porteria", las familias `defensa` y
       `balon_parado` defensivas se quedaban en cero huecos por construcción. El
       CLAUDE.md las daba por "baratas de empezar"; en realidad eran imposibles.
       Al relajar el filtro a "cualquier jugada del rival con familia_def" se
       abren, con estas apariciones medidas de cada 600 partidos:

         defiendo | porteria     | parada    1488   tu portero la saca
         defiendo | defensa      | despeje    983   tu defensa la despeja
         defiendo | balon_parado | corner     703   te han sacado un córner

       TODAS de impacto "ninguno", y no por prudencia sino porque no hay nada que
       mover: esas jugadas ya acabaron sin gol, así que no existe gol que quitar.
       Suman a la puntuación de actuación (§4.6) y ahí se quedan. Por eso hay un
       tope propio (`partido_minijuegos_sin_impacto_max`): sin él estos tres
       huecos, que juntos superan al del gol, se comían el presupuesto y dejaban
       a un jugador con sus dos decisiones sin poder tocar el resultado.

       El dato oculto sigue siendo `remate` y se lee del RIVAL que remata, igual
       que en el Grupo A: en las tres jugadas ese protagonista existe y es de la
       alineación contraria, así que no hacía falta ningún dato nuevo. Lo que
       adivinas es qué le llega a tu defensa o a tu portero.

       De aquí sale la familia de Portería y Defensa de la Biblia, que tenía doce
       entradas descritas y solo las de parada construidas.
       ##################################################################### */

    /* Agarrar o Golpear (Biblia §2.4): "blocar da control total de la posesión
       pero con riesgo si la Portería de esa carta es baja; despejar es siempre
       seguro pero regala el balón". */
    "agarrar_o_golpear" => [
        "nombre"    => "Agarrar o Golpear",
        "familia"   => "porteria",
        "lado"      => "defiendo",
        "tipos"     => ["parada"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "ninguno",
        "titulo"    => "La tiene {portero}",
        "enunciado" => "{portero} llega al disparo de {jugador}. ¿La blocas o la sacas?",
        "opciones"  => [
            [
                "clave"  => "sacarla_de_punos",
                "nombre" => "Sacarla de puños",
                "pista"  => "Un cañonazo no se bloca, se saca lejos.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "atraparla_arriba",
                "nombre" => "Atraparla arriba",
                "pista"  => "La que viene medida se abraza.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "cubrir_el_bote",
                "nombre" => "Cubrir el bote",
                "pista"  => "Abajo, sobre la que llega a ras de suelo.",
                "gana"   => "raso",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* El Farol (Biblia §2.4): el amago. La Biblia lo quiere "de aparición poco
       frecuente y tensión muy alta", y avisa de que "se vuelve aburrido si
       aparece demasiado" — aquí sale gratis, porque comparte hueco con otras
       tres entradas y el reparto es determinista. */
    "el_farol" => [
        "nombre"    => "El Farol",
        "familia"   => "porteria",
        "lado"      => "defiendo",
        "tipos"     => ["parada"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "ninguno",
        "titulo"    => "Amaga y no tira",
        "enunciado" => "{jugador} amaga el disparo y {portero} tiene que decidir sin verlo.",
        "opciones"  => [
            [
                "clave"  => "tirarse_al_amague",
                "nombre" => "Tirarse al amague",
                "pista"  => "Al que va a reventarla no le da tiempo a cambiar.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "aguantar_de_pie",
                "nombre" => "Aguantar de pie",
                "pista"  => "El que la coloca necesita que te muevas antes.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "esperar_el_bote",
                "nombre" => "Esperar el bote",
                "pista"  => "Si acaba llegando rasa, la ves venir.",
                "gana"   => "raso",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 8,
            "facil" => 12, "medio" => 10, "dificil" => 9,
            "muy_dificil" => 7, "extremo" => 6,
        ],
    ],

    /* La Entrada al Límite (Biblia §2.4): la única entrada defensiva de la
       familia de Regate. "Entrada fuerte, con más porcentaje de recuperar pero
       riesgo alto de tarjeta, frente a una más prudente y menos efectiva."
       PRIMERA ENTRADA DE LA FAMILIA `defensa` DEL CATÁLOGO. */
    "entrada_al_limite" => [
        "nombre"    => "La Entrada al Límite",
        "familia"   => "defensa",
        "lado"      => "defiendo",
        "tipos"     => ["despeje"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "ninguno",
        "titulo"    => "{defensa} llega justo",
        "enunciado" => "{jugador} arma el disparo y {defensa} tiene medio metro para llegar.",
        "opciones"  => [
            [
                "clave"  => "entrar_fuerte",
                "nombre" => "Entrar fuerte",
                "pista"  => "Al que va a reventarla hay que cortarle el golpeo.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "aguantar_la_linea",
                "nombre" => "Aguantar la línea",
                "pista"  => "Al que busca el hueco se le tapa sin irse al suelo.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "meter_el_pie",
                "nombre" => "Meter el pie",
                "pista"  => "Abajo, donde va a pasar la rasa.",
                "gana"   => "raso",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 8,
            "facil" => 12, "medio" => 11, "dificil" => 9,
            "muy_dificil" => 7, "extremo" => 6,
        ],
    ],

    /* El Susto en Propia Puerta (Biblia §2.4, familia de Decisiones Negativas)
       — MEDIDOR. La Biblia lo describe con "una franja de peligro claramente
       visible: detenerlo ahí produce un rechace corto que deja una ocasión
       servida al rival". La franja es la zona central de la pista, que es
       justamente la que aquí lleva la opción segura: el despeje limpio. */
    "susto_propia_puerta" => [
        "nombre"    => "El Susto en Propia Puerta",
        "familia"   => "defensa",
        "lado"      => "defiendo",
        "tipos"     => ["despeje"],
        "oculto"    => "remate",
        "primitiva" => "medidor",
        "impacto"   => "ninguno",
        "titulo"    => "Despeje con {jugador} encima",
        "enunciado" => "{defensa} tiene que sacarla con {jugador} llegando al remate.",
        "opciones"  => [
            [
                "clave"  => "reventarla_arriba",
                "nombre" => "Reventarla arriba",
                "pista"  => "Sin mirar, lejos del que viene a golpearla fuerte.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "despeje_limpio",
                "nombre" => "Despeje limpio",
                "pista"  => "Al punto, sin regalar el rechace al que la coloca.",
                "gana"   => "colocado",
                "segura" => true,
            ],
            [
                "clave"  => "sacarla_en_corto",
                "nombre" => "Sacarla en corto",
                "pista"  => "Jugada al pie, por debajo de la que viene rasa.",
                "gana"   => "raso",
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 11, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
        "velocidad" => [
            "pvp" => 1900,
            "facil" => 2600, "medio" => 2200, "dificil" => 1800,
            "muy_dificil" => 1400, "extremo" => 1100,
        ],
    ],

    /* Hombre a Hombre o Zona (Biblia §2.4): "el marcaje al hombre funciona bien
       contra un rematador estrella concreto pero peor si rotan las llegadas; el
       zonal rinde parejo contra cualquiera". */
    "hombre_o_zona" => [
        "nombre"    => "Hombre a Hombre o Zona",
        "familia"   => "balon_parado",
        "lado"      => "defiendo",
        "tipos"     => ["corner"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "ninguno",
        "titulo"    => "Córner en contra",
        "enunciado" => "Saca el rival y {portero} ordena el área con {jugador} rondando.",
        "opciones"  => [
            [
                "clave"  => "al_hombre",
                "nombre" => "Al hombre",
                "pista"  => "Al que cabecea fuerte se le pega uno encima.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "mixto_en_el_area",
                "nombre" => "Mixto",
                "pista"  => "Contra el que la busca medida, mejor repartir.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "zonal",
                "nombre" => "Zonal",
                "pista"  => "Cubres las zonas bajas y el segundo palo.",
                "gana"   => "raso",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 10,
            "facil" => 15, "medio" => 13, "dificil" => 11,
            "muy_dificil" => 9, "extremo" => 8,
        ],
    ],

    /* Salir o Quedarse (Biblia §2.4): "el portero puede salir a por el balón, y
       si el timing es correcto corta la jugada de raíz, pero si falla deja la
       portería vacía; quedarse en la línea es seguro pero concede la segunda
       jugada". */
    "salir_o_quedarse" => [
        "nombre"    => "Salir o Quedarse",
        "familia"   => "balon_parado",
        "lado"      => "defiendo",
        "tipos"     => ["corner"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "ninguno",
        "titulo"    => "¿Sale {portero}?",
        "enunciado" => "El centro entra al área y {portero} decide si va a por él.",
        "opciones"  => [
            [
                "clave"  => "salir_a_por_ella",
                "nombre" => "Salir a por ella",
                "pista"  => "Le quitas el balón al que iba a golpearla fuerte.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "quedarse_en_la_linea",
                "nombre" => "Quedarse en la línea",
                "pista"  => "Desde la línea llegas a la que va medida al palo.",
                "gana"   => "colocado",
                "segura" => true,
            ],
            [
                "clave"  => "cerrar_el_primer_palo",
                "nombre" => "Cerrar el primer palo",
                "pista"  => "Tapas la tensa y rasa al primer palo.",
                "gana"   => "raso",
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 14, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* Cerrar el Ángulo (Biblia §2.4): la Biblia subraya que es "una decisión
       PREVIA y no reactiva, a diferencia de la mayoría de los minijuegos de
       portería" — eliges de antemano qué mitad regalas para forzar el disparo
       hacia tu lado fuerte. */
    "cerrar_el_angulo" => [
        "nombre"    => "Cerrar el Ángulo",
        "familia"   => "porteria",
        "lado"      => "defiendo",
        "tipos"     => ["parada"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "ninguno",
        "titulo"    => "Elige qué le regalas",
        "enunciado" => "{portero} se perfila antes de que {jugador} llegue al disparo.",
        "opciones"  => [
            [
                "clave"  => "regalar_el_palo_corto",
                "nombre" => "Regalar el palo corto",
                "pista"  => "Le obligas a buscar potencia donde tú llegas.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "abrir_el_palo_largo",
                "nombre" => "Abrir el palo largo",
                "pista"  => "Al que la coloca le dejas el sitio que tú cubres.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "cerrar_todo_abajo",
                "nombre" => "Cerrarlo todo abajo",
                "pista"  => "No le dejas hueco a ras de suelo.",
                "gana"   => "raso",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 14, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* El Sacrificio Final (Biblia §2.4): "la decisión más dramática de toda la
       familia defensiva" — la entrada desesperada del último hombre, que para el
       gol pero conlleva expulsión casi segura. Sin expulsiones con efecto
       mecánico construidas, lo que queda es la decisión de CÓMO llegas. */
    "el_sacrificio_final" => [
        "nombre"    => "El Sacrificio Final",
        "familia"   => "defensa",
        "lado"      => "defiendo",
        "tipos"     => ["despeje"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "ninguno",
        "titulo"    => "{defensa} es el último",
        "enunciado" => "{jugador} se va de todos y solo queda {defensa} entre él y {portero}.",
        "opciones"  => [
            [
                "clave"  => "entrada_desesperada",
                "nombre" => "Entrada desesperada",
                "pista"  => "Le cortas el golpeo antes de que la reviente.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "acompanarle_sin_tocar",
                "nombre" => "Acompañarle sin tocar",
                "pista"  => "Le tapas el hueco al que la quiere colocar.",
                "gana"   => "colocado",
                "segura" => true,
            ],
            [
                "clave"  => "barrerle_el_balon",
                "nombre" => "Barrerle el balón",
                "pista"  => "Al suelo, a la trayectoria rasa.",
                "gana"   => "raso",
            ],
        ],
        "plazo" => [
            "pvp" => 8,
            "facil" => 12, "medio" => 10, "dificil" => 9,
            "muy_dificil" => 7, "extremo" => 6,
        ],
    ],

    /* Pedir Ayuda (Biblia §2.4): "mandar un centrocampista a cubrir tapa el
       problema pero debilita la línea de Medio durante esa jugada; dejar al
       lateral solo no cuesta nada pero lo deja en desventaja". */
    "pedir_ayuda" => [
        "nombre"    => "Pedir Ayuda",
        "familia"   => "defensa",
        "lado"      => "defiendo",
        "tipos"     => ["despeje"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "ninguno",
        "titulo"    => "Han desbordado a {defensa}",
        "enunciado" => "{jugador} le ha ganado la espalda a {defensa} y encara el área.",
        "opciones"  => [
            [
                "clave"  => "doblar_con_el_medio",
                "nombre" => "Doblar con el medio",
                "pista"  => "Dos encima le quitan sitio para armar el disparo.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "cerrar_por_dentro",
                "nombre" => "Cerrar por dentro",
                "pista"  => "Le tapas el interior al que busca la escuadra.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "dejarle_solo",
                "nombre" => "Dejarle solo",
                "pista"  => "Sin abrir huecos: la rasa se para sin ayuda.",
                "gana"   => "raso",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 11, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* La Emboscada (Biblia §2.4, familia de Portería y Defensa): "presionar de
       forma agresiva en la zona alta puede robar el balón y generar una ocasión
       inmediata, pero si falla deja un espacio enorme a la espalda de esa línea;
       replegarse no genera riesgo ni oportunidad". */
    "la_emboscada" => [
        "nombre"    => "La Emboscada",
        "familia"   => "defensa",
        "lado"      => "defiendo",
        "tipos"     => ["despeje"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "ninguno",
        "titulo"    => "Sale el balón desde atrás",
        "enunciado" => "{defensa} decide dónde esperar a {jugador}.",
        "opciones"  => [
            [
                "clave"  => "presion_alta",
                "nombre" => "Presión alta",
                "pista"  => "No le dejas armar el disparo fuerte.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "achicar_el_pasillo",
                "nombre" => "Cerrar el pasillo",
                "pista"  => "Le tapas el hueco al que la coloca.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "replegarse",
                "nombre" => "Replegarse",
                "pista"  => "Atrás y compacto: la rasa muere ahí.",
                "gana"   => "raso",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 13, "medio" => 11, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* Choque de Titanes (Biblia §2.4, familia Narrativa): dos cartas de afinidades
       enfrentadas pelean el mismo balón; "empeñarse en ganar el duelo, con más
       riesgo y más premio, o ceder la posición sin más consecuencias". */
    "choque_de_titanes" => [
        "nombre"    => "Choque de Titanes",
        "familia"   => "balon_parado",
        "lado"      => "defiendo",
        "tipos"     => ["corner"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "ninguno",
        "titulo"    => "Duelo en el área",
        "enunciado" => "{defensa} y {jugador} se miden en el salto antes del centro.",
        "opciones"  => [
            [
                "clave"  => "pelearle_el_salto",
                "nombre" => "Pelearle el salto",
                "pista"  => "Al que va a golpearla fuerte se le disputa arriba.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "anticiparse",
                "nombre" => "Anticiparse",
                "pista"  => "Le llegas antes al balón medido.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "ceder_la_posicion",
                "nombre" => "Ceder la posición",
                "pista"  => "Te quedas al rechace bajo sin arriesgar.",
                "gana"   => "raso",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 9,
            "facil" => 14, "medio" => 12, "dificil" => 10,
            "muy_dificil" => 8, "extremo" => 7,
        ],
    ],

    /* Vigilancia Aérea (Biblia §2.4): "asignar qué defensor cubre cada zona
       probable de llegada del centro contrario". */
    "vigilancia_aerea" => [
        "nombre"    => "Vigilancia Aérea",
        "familia"   => "balon_parado",
        "lado"      => "defiendo",
        "tipos"     => ["corner"],
        "oculto"    => "remate",
        "primitiva" => "eleccion",
        "impacto"   => "ninguno",
        "titulo"    => "Reparte el área",
        "enunciado" => "{defensa} coloca a los suyos antes de que saquen el córner.",
        "opciones"  => [
            [
                "clave"  => "doblar_el_area_chica",
                "nombre" => "Doblar el área chica",
                "pista"  => "Donde caen los centros tensos y fuertes.",
                "gana"   => "potente",
            ],
            [
                "clave"  => "cubrir_el_segundo_palo",
                "nombre" => "Cubrir el segundo palo",
                "pista"  => "Adonde va el que la pone medida.",
                "gana"   => "colocado",
            ],
            [
                "clave"  => "poner_gente_en_el_borde",
                "nombre" => "Gente en el borde",
                "pista"  => "Para el saque bajo y el rechace corto.",
                "gana"   => "raso",
                "segura" => true,
            ],
        ],
        "plazo" => [
            "pvp" => 10,
            "facil" => 15, "medio" => 13, "dificil" => 11,
            "muy_dificil" => 9, "extremo" => 8,
        ],
    ],
];
