<?php
/**
 * PLANTILLAS DE NARRACIÓN DEL MOTOR DE EVENTOS
 *
 * Biblia §1.5 (regla 6, "la corrección más importante de toda la sesión"):
 * repetir el mismo nodo contra el mismo rival tiene que leerse distinto cada
 * vez. Eso no se consigue con probabilidad libre sino con VOLUMEN de frases:
 * si un tipo de evento tiene tres variantes, el décimo intento repite palabras
 * seguro. El motor elige sin reemplazo dentro de cada tipo mientras le queden
 * variantes sin usar en ese partido (ver Tcg::frase()), así que el número de
 * variantes de cada bloque es literalmente el techo de variedad de ese partido.
 *
 * **Al añadir variantes nuevas, añádelas al final del array**: el motor elige
 * de forma determinista a partir del valor de sorteo ya guardado del duelo
 * (para que un mismo partido siempre se narre igual al volver a abrirlo), así
 * que insertar en medio reescribe la narración de los duelos ya jugados.
 *
 * Marcadores disponibles (los que no apliquen al evento se ignoran):
 *   {jugador}  carta protagonista de la jugada
 *   {asiste}   carta que da el pase previo
 *   {portero}  portero del equipo que defiende
 *   {defensa}  carta que despeja, corta o comete la falta
 *   {equipo}   nombre del equipo/usuario que ataca
 *   {rival}    nombre del equipo/usuario que defiende
 *
 * Tono (CLAUDE.md §6): editorial y serio, como una retransmisión de ficha
 * oficial. Sin argot gamer, sin superlativos vacíos, sin bromas sobre nombres
 * de jugadores reales.
 */
return [

    /* ------------------------------------------------------------------
       GOL — el evento de mayor peso narrativo. Es el que más variantes
       necesita: es el que el jugador lee con más atención y el que acaba
       en el veredicto compartible.
       ------------------------------------------------------------------ */
    "gol" => [
        "{jugador} la empuja dentro. No perdona en el área pequeña.",
        "Remate cruzado de {jugador} y el balón entra junto al palo largo.",
        "{jugador} se anticipa a su par y bate a {portero} de primeras.",
        "Definición seca de {jugador}. {portero} no llega a rozarla.",
        "{jugador} recorta hacia dentro y la coloca lejos del alcance de {portero}.",
        "Llega {jugador} al segundo palo y la manda al fondo.",
        "Disparo raso de {jugador} desde la frontal. Entra pegada al poste.",
        "{jugador} aprovecha el rechace y marca con la portería abierta.",
        "Cabezazo de {jugador}, que gana el salto y dirige el balón abajo.",
        "{jugador} se planta ante {portero} y resuelve con calma.",
        "Vaselina de {jugador} por encima de {portero}. Gol de mucha lectura.",
        "{jugador} engancha una volea que se cuela por la escuadra.",
    ],

    "gol_asistido" => [
        "{asiste} filtra el pase y {jugador} solo tiene que empujarla.",
        "Centro medido de {asiste} y {jugador} remata a placer.",
        "{asiste} abre a la banda, devuelven al área y {jugador} la manda dentro.",
        "Pared entre {asiste} y {jugador}: el segundo define ante {portero}.",
        "{asiste} levanta la cabeza y encuentra a {jugador}, que no falla.",
        "Saque en corto, {asiste} pone el balón atrás y {jugador} fusila.",
        "{jugador} ataca el primer palo tras el envío de {asiste} y marca.",
        "{asiste} rompe la línea con un pase al hueco y {jugador} lo resuelve.",
    ],

    /* ------------------------------------------------------------------
       OCASIÓN FALLADA — la mayoría de las ocasiones generadas mueren aquí.
       Es el relleno más frecuente del partido, así que necesita tanta
       variedad como el gol o el partido se vuelve monótono enseguida.
       ------------------------------------------------------------------ */
    "tiro_fuera" => [
        "{jugador} prueba desde lejos y se marcha alta.",
        "Se le abre el interior a {jugador} y el balón sale rozando el palo.",
        "{jugador} dispara con todo pero la manda por encima del larguero.",
        "Remate desviado de {jugador}. No encuentra portería.",
        "{jugador} llega forzado y el disparo se va fuera sin peligro.",
        "Buscó la escuadra {jugador}, pero el balón se pierde por la línea de fondo.",
        "{jugador} no golpea limpio y el intento muere en las nubes.",
        "El disparo de {jugador} se estrella en la barrera y sale despejado.",
    ],

    "parada" => [
        "{portero} responde abajo y saca el disparo de {jugador}.",
        "Buena mano de {portero} para desviar el remate de {jugador}.",
        "{jugador} la golpea bien pero {portero} llega con los puños.",
        "{portero} achica y le tapa el ángulo por completo a {jugador}.",
        "Intervención de {portero}, que blocá el tiro de {jugador} sin ceder rechace.",
        "{portero} se estira y manda a córner el remate de {jugador}.",
        "{jugador} buscó el palo corto y {portero} estaba bien colocado.",
        "Reflejo de {portero} para evitar el gol de {jugador} desde cerca.",
    ],

    "despeje" => [
        "{defensa} se cruza en el momento justo y evita el remate.",
        "Corta {defensa} el centro antes de que llegue {jugador}.",
        "{defensa} despeja de cabeza y aleja el peligro del área.",
        "Aparece {defensa} para robar el balón dentro del área.",
        "{defensa} achica bien y el ataque de {equipo} se queda sin salida.",
        "El envío no encuentra rematador: {defensa} lo resuelve sin apuros.",
    ],

    /* ------------------------------------------------------------------
       BALÓN PARADO Y JUEGO INTERRUMPIDO — dan textura y sirven de
       disparador narrativo para la familia de minijuegos de balón parado
       (Biblia §2.4) cuando esa capa se construya encima de esta.
       ------------------------------------------------------------------ */
    "corner" => [
        "Saque de esquina para {equipo}. {defensa} había desviado a córner.",
        "Córner a favor de {equipo} tras el rechace de la defensa.",
        "{equipo} vuelve a cargar el área desde la esquina.",
        "Otro córner para {equipo}, que insiste por ese costado.",
    ],

    "falta" => [
        "Falta de {defensa} sobre {jugador} en la frontal.",
        "{defensa} corta con falta el avance de {jugador}.",
        "El árbitro señala falta de {defensa}. {equipo} tiene el balón parado.",
        "Entrada tardía de {defensa} sobre {jugador}. Falta clara.",
    ],

    "tarjeta" => [
        "Amarilla para {defensa}. El árbitro no perdona la entrada.",
        "{defensa} ve la cartulina tras protestar la decisión anterior.",
        "Amonestación a {defensa} por cortar el contragolpe.",
        "El colegiado amonesta a {defensa}. Deberá medirse el resto del partido.",
    ],

    /* ------------------------------------------------------------------
       CONTEXTO DE PARTIDO — no son ocasiones, son los respiros que dan
       ritmo. Sin ellos el partido se lee como una lista de disparos.
       ------------------------------------------------------------------ */
    "posesion" => [
        "{equipo} mueve el balón con criterio y hace correr al rival.",
        "Tramo de control de {equipo}, que no encuentra el hueco todavía.",
        "{equipo} adelanta líneas y encierra a {rival} en su campo.",
        "El partido baja de ritmo. {equipo} administra la posesión.",
        "{equipo} circula de lado a lado buscando abrir la defensa de {rival}.",
        "{jugador} baja a recibir y ordena el juego de {equipo}.",
    ],

    "presion" => [
        "{rival} sale a presionar arriba y obliga a {equipo} a jugar largo.",
        "Se rompe el partido: ida y vuelta constante entre las dos áreas.",
        "{rival} apreta y recupera en campo contrario.",
        "Duelo físico en el centro del campo. Nadie da un balón por perdido.",
    ],

    /* ------------------------------------------------------------------
       HITOS DE CRONÓMETRO — anclan la narración en el tiempo real del
       partido y marcan los tres actos de un partido "jefe" (Biblia §4.5).
       Una sola variante a propósito: son marcas, no color.
       ------------------------------------------------------------------ */
    "inicio"    => ["Rueda el balón en el estadio. Comienza el partido."],
    "descanso"  => ["Final del primer tiempo."],
    "reanuda"   => ["Se reanuda el juego para la segunda mitad."],
    "descuento" => ["El colegiado añade tiempo de descuento."],
    "final"     => ["El árbitro señala el final del encuentro."],
];
