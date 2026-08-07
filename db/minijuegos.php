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
 *   "partido"  Reservado. Efecto más allá de la jugada (arrastra a las
 *              siguientes). Ninguna entrada lo usa todavía: exige mover la
 *              resolución del duelo a después del partido, y eso está sin
 *              decidir.
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
 * QUÉ COMBINACIONES (familia, lado) EXISTEN DE VERDAD — medido sobre 400
 * partidos simulados, no supuesto. narracionDuelo() solo ofrece decisión si la
 * jugada "tiene sentido" para ese lado: defendiendo exige `tipo === "gol"`, y
 * atacando exige `tipo !== "gol"`. Eso deja fuera muchas familias que el motor
 * sí emite:
 *
 *   porteria    | defiendo   1039   ← el gol del rival. Grupo A.
 *   disparo     | ataco      2453   ← parada, tiro fuera, despeje. Grupo B.
 *   balon_parado| ataco      1101   ← córner y falta. Grupo C.
 *
 *   defensa     | defiendo      0   ← INALCANZABLE: defender exige un gol, y
 *                                     un gol siempre es familia_def "porteria".
 *                                     (El CLAUDE.md la daba por "barata de
 *                                     empezar". No lo es: no llega nunca.)
 *   balon_parado| defiendo      0   ← mismo motivo.
 *   arbitro     | ataco       202   ← alcanzable pero INSERVIBLE: el evento de
 *                                     tarjeta no lleva `protagonistas`, así que
 *                                     el dato oculto caería siempre en su valor
 *                                     por defecto y una opción ganaría el 100 %.
 *
 * Antes de escribir una entrada para una familia nueva, comprueba que llega.
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
            "pvp" => 6,
            "facil" => 11, "medio" => 9, "dificil" => 7,
            "muy_dificil" => 5, "extremo" => 4,
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
            "pvp" => 6,
            "facil" => 10, "medio" => 8, "dificil" => 6,
            "muy_dificil" => 5, "extremo" => 4,
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
            "pvp" => 6,
            "facil" => 11, "medio" => 9, "dificil" => 7,
            "muy_dificil" => 5, "extremo" => 4,
        ],
    ],

    "lectura_de_cadera" => [
        "nombre"    => "Lectura de Cadera",
        "familia"   => "porteria",
        "lado"      => "defiendo",
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
            "pvp" => 6,
            "facil" => 11, "medio" => 9, "dificil" => 7,
            "muy_dificil" => 5, "extremo" => 4,
        ],
    ],

    "el_ultimo_palmo" => [
        "nombre"    => "El Último Palmo",
        "familia"   => "porteria",
        "lado"      => "defiendo",
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
            "pvp" => 6,
            "facil" => 11, "medio" => 9, "dificil" => 7,
            "muy_dificil" => 5, "extremo" => 4,
        ],
    ],

    /* #####################################################################
       GRUPO B — DISPARO, ATACANDO.  Dato oculto: "estilo_portero".
       Se disparan sobre una ocasión propia FALLADA (parada, tiro fuera o
       despeje): aciertas y la ocasión acaba en gol.

       Ciclo cerrado sobre los tres estilos de portero, y por tanto espejo
       exacto del grupo A: lo que aprendes parando te sirve para rematar.
       ##################################################################### */

    "el_regate_previo" => [
        "nombre"    => "El Regate Previo",
        "familia"   => "disparo",
        "lado"      => "ataco",
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
            "pvp" => 6,
            "facil" => 10, "medio" => 8, "dificil" => 6,
            "muy_dificil" => 5, "extremo" => 4,
        ],
    ],

    "desde_la_frontal" => [
        "nombre"    => "Desde la Frontal",
        "familia"   => "disparo",
        "lado"      => "ataco",
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
            "pvp" => 6,
            "facil" => 10, "medio" => 8, "dificil" => 6,
            "muy_dificil" => 5, "extremo" => 4,
        ],
    ],

    "primer_toque" => [
        "nombre"    => "A Primer Toque",
        "familia"   => "disparo",
        "lado"      => "ataco",
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
            "pvp" => 5,   // a primer toque se decide antes: la prisa es el tema
            "facil" => 9, "medio" => 7, "dificil" => 6,
            "muy_dificil" => 4, "extremo" => 3,
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
            "pvp" => 8,
            "facil" => 13, "medio" => 11, "dificil" => 9,
            "muy_dificil" => 7, "extremo" => 5,
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
            "pvp" => 8,
            "facil" => 13, "medio" => 11, "dificil" => 9,
            "muy_dificil" => 7, "extremo" => 5,
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
            "pvp" => 8,
            "facil" => 13, "medio" => 11, "dificil" => 9,
            "muy_dificil" => 7, "extremo" => 5,
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
            "pvp" => 7,
            "facil" => 12, "medio" => 10, "dificil" => 8,
            "muy_dificil" => 6, "extremo" => 5,
        ],
    ],
];
