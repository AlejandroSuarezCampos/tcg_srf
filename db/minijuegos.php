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
 *   "remate"         qué tipo de disparo llega   (lo elige el rematador rival)
 *   "estilo_portero" cómo sale el portero rival  (lo elige su portero)
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
];
