# `impacto: "partido"` — qué se rompe y qué caminos hay

> # ✅ CERRADO Y CONSTRUIDO (2026-08-10) — LEE ESTO PRIMERO
>
> **Este documento es un registro de decisiones, y la mitad de lo que contiene
> quedó SUPERADO por la decisión final.** Se conserva entero porque las mediciones
> siguen valiendo y porque explica por qué se cambió de opinión dos veces, pero
> **no lo leas como especificación**: para eso está el **§15.10 del CLAUDE.md**.
>
> **Lo que se construyó al final fue la opción C: el partido decide el duelo.**
>
> | | dónde |
> |---|---|
> | `resolverDuelo()` deja el duelo en `en_juego`, sin ganador y sin pagar | `consultas.php` |
> | La simulación corre en **modo natural** (sin `gana`): empates posibles, **32 %** entre iguales | `generarEventosPartido()` |
> | Los minijuegos mueven el marcador de verdad, con tope `partido_presupuesto_marcador` | `narracionDuelo()` |
> | Al final, `liquidarPartido()` decide, rompe el empate en la tanda y entrega el bote | `consultas.php` |
> | `tandaDePenaltis()` — 5 tiros y muerte súbita, determinista | `consultas.php` |
> | `cerrarPartidoSiToca()` — enganche perezoso, con **dos ramas de abandono** | `consultas.php` |
> | Migraciones `019`–`022`; **`019` y `021` son obligatorias** | `db/migraciones/` |
>
> **Lo que se retiró:** `cabeCambioMarcador()` (borrada, no dejada muerta) y la
> condición de §1.3 dentro del `UPDATE` de `descontarGolRival()` /
> `sumarGolPropio()`. La §1.3 **ya no existe**.
>
> **Lo que se confirmó al construirlo:** las dos cosas que este documento marcaba
> como caras resultaron no serlo.
> - **`valor_sorteo` NO hubo que separar en dos números** (§3.1). En PvP dejó de
>   decidir el ganador y se quedó solo como semilla del relato, así que la garantía
>   de que relato y resultado no se contradigan sigue saliendo gratis.
> - **El abandono no necesitó ninguna regla nueva** (§3.2), porque lo apostado ya
>   estaba retenido de los dos. Lo que sí hizo falta fue *garantizar que el bote
>   acabe entregándose*: de ahí las dos ramas de abandono.
>
> **Verificado jugando un duelo real entre dos cuentas** sobre una copia
> desechable: acabó **2-2, lo decidió la tanda**, el bote se entregó una sola vez y
> la pantalla de resultado llegó correcta. Las cadenas (PvE) siguen intactas, con
> prueba propia.
>
> **El coste sigue siendo el que este documento midió y Alejandro aceptó: el
> favorito pasa del 69,1 % al 91,0 %.**

 ## 🔄 DECISIÓN POSTERIOR (2026-08-10, misma sesión) — el partido SÍ decide
>
> Con la opción B construida, Alejandro señaló el agujero que la dejaba hueca:
> **en PvP los minijuegos no cambian quién gana**, y se comprobó que el marcador y
> la actuación **no los lee nada mecánico** — 4 sitios para pintar goles
> (`duelo.php:201-202`, `duelos.php:267-269`) y 2 para escribir una frase sobre la
> actuación (`duelo.js:849`, `duelo.php:352`). Setenta y cinco minijuegos colgados
> de un resultado intocable, alimentando una puntuación que no se gasta.
>
> ### Lo que se construye ahora
>
> 1. **No se decide nada al empezar.** El partido arranca sin ganador.
> 2. **El marcador se construye durante el partido.** Ganar un minijuego **sube la
>    probabilidad de marcar en ESA ocasión**; no regala el gol.
> 3. **Empate → tanda de penaltis.** Y con eso se cierra el partido.
>
> ### ✅ El pago: la parte que asustaba YA ESTÁ RESUELTA
>
> Decisión de Alejandro: *"aceptas el duelo, el dinero se le quita a ambos, y
> cuando ya se ha decidido un ganador se entrega al que gane."* **Eso ya es
> exactamente lo que hace el código**, y verificarlo cambia el tamaño del Paso 3:
>
> - el creador paga al crear la sala (`consultas.php:1961`),
> - el rival paga al aceptar (`consultas.php:2024`),
> - las cartas apostadas quedan retenidas. **CORRECCIÓN posterior:** aquí decía
>   *"se marcan `bloqueada`"* y **era falso** — no hay ni una línea que lo haga.
>   Lo que las retiene es que las consultas de apostar y vender excluyen las
>   copias con fila en `duelo_apuestas` cuyo duelo esté en `estado NOT IN
>   ('resuelto','cancelado')`. Ver el aviso del §15.10 del CLAUDE.md.
>
> O sea que **el bote ya está retenido antes del partido** y lo único que
> `resolverDuelo()` hace al final es ENTREGARLO (`monedas = monedas + :bote`).
> No hay que inventar retención, ni tocar `crearDuelo()`, ni `aceptarDuelo()`:
> solo **mover el momento de la entrega** y decidir el ganador ahí.
>
> **Y eso cierra la pregunta del abandono sin regla nueva.** El dinero no es de
> nadie mientras se juega, así que irse no lo recupera: al que falta se le aplican
> las opciones seguras —lo que el motor ya hace al vencer un plazo (§15.3, *"si no
> estás atento, te lo pierdes"*)—, el partido se resuelve solo y el bote va a quien
> gane.
>
> ### ⚠️ Compensación aceptada explícitamente por Alejandro
>
> Está medido y avisado dos veces: con el partido decidiendo, **el favorito gana
> mucho más** (240 contra 100 pasa del 69,1 % al 91,0 %; el débil cae del 30,9 % al
> 9,0 %). La influencia de los minijuegos **no lo compensa**: son 2 decisiones
> sobre ~10 ocasiones, un lever demasiado pequeño.
>
> Eso va en contra de lo que el §15.8 documenta como el problema principal —que un
> equipo flojo bien pensado pueda ganar—. **Alejandro lo asume a cambio de que los
> minijuegos cuenten de verdad**, que es la queja que originó todo el §15.
>
> Los diales para recalibrarlo cuando se quiera están extraídos y medidos:
> `gol_base` / `gol_sens` (conversión de ocasión en gol) y `duelo_k`. La tabla
> completa del barrido está más abajo.
>
> ---
>
> ## ✅ Decisión intermedia — opción B, CONSTRUIDA (sigue en pie lo que aporta)
>
> Alejandro decidió primero mover la resolución al partido; **las mediciones de
> más abajo le hicieron cambiar de decisión**, y la final es la **opción B**: los
> minijuegos pueden arrastrar al resto del encuentro, pero **el ganador lo sigue
> decidiendo la curva Elo** y `resolverDuelo()` no se toca.
>
> **Cómo quedó construido** (`impacto: "partido"`, 3 entradas de arranque):
> el efecto no toca el desenlace de ninguna jugada — **amplía el presupuesto** con
> el que las jugadas siguientes pueden mover el marcador, o concede una decisión
> más. Cada entrada declara `efecto`, y el verificador lo exige:
> `presupuesto_gol`, `presupuesto_parada` o `decision`.
>
> Es seguro por construcción, y no por cuidado al escribirlo: la condición del
> §1.3 vive **dentro del `UPDATE`** de `descontarGolRival()` (`consultas.php:4417`),
> así que el presupuesto es solo una OFERTA y la base de datos sigue siendo el
> juez. Un efecto de partido puede darte más oportunidades; no puede contradecir
> al ganador.
>
> **Lo que esta regla deja fuera, y conviene saberlo:** `resolverMinijuego()` no
> castiga elegir mal a propósito ("el minijuego solo puede mejorar tu partido,
> nunca empeorarlo, así que ofrecerlo jamás es una trampa"). Por eso un `partido`
> solo puede CONCEDER, y por eso la familia de Decisiones Negativas de la Biblia
> —donde una rama "solo puede salir peor"— sigue siendo inexpresable: El Baile
> Provocador, La Fiesta Peligrosa. No es un olvido del catálogo, es esa regla.
>
> ### Decisiones que quedan EN SUSPENSO
>
> **La tanda de penaltis** se había decidido para romper empates, pero con la
> opción B **no hay empates posibles** (el §1.3 fuerza un ganador), así que no hay
> nada que romper. Las 4 entradas que desbloqueaba siguen pendientes.
>
> **Se midió si darle disparador dejando EMPATAR a quien pierde** (hoy imposible:
> el presupuesto de gol propio, `consultas.php:4590`, exige `$suyos > $mios +
> $golesLibres + 1`, o sea quedarse estrictamente por detrás). **No es viable:**
>
> | sobre 800 partidos | |
> |---|---|
> | duelos con margen final de **1 gol** | **91,8 %** |
> | …y el que pierde recibe ocasión de empatar | 87,5 % del total |
> | tanda si acierta a ciegas (33 %) | **29,2 %** de los duelos |
> | tanda si lee la pista (45 %) | **39,4 %** de los duelos |
>
> Uno de cada tres duelos a penaltis no es fútbol, y con el coste de pausas del
> §15.5 el partido se haría interminable.
>
> ### 🔎 Pero la medición destapó un problema mayor que la tanda
>
> **El 91,8 % de los duelos acaban por un solo gol.** La causa es el bucle del
> §1.3 (`consultas.php:3013`), que corrige *"con el mínimo destrozo posible"*:
> asciende **exactamente una** ocasión hasta que el ganador va por delante, y
> para. Así que casi todos los partidos son 1-0 o 2-1 y prácticamente nunca hay
> una goleada ni un partido cómodo — una **flatness narrativa** que nadie había
> medido y que no tiene nada que ver con la tanda.
>
> Y arreglarla es **requisito previo** para la tanda: con los márgenes repartidos
> hay menos partidos de un gol, menos ocasiones de empatar, y la tanda bajaría a
> una frecuencia razonable. **Ese es el orden correcto de trabajo**, no al revés.
>
> **Mover la resolución al partido** queda descartado por ahora, no cerrado: el
> estado reconstruible que B ha construido es la pieza que haría falta igual.
>
> ---
>
> ## Lo que se decidió antes y por qué cambió
>
> **1. Primera decisión (descartada):** el alcance de B con la resolución de C —
> que el resultado se resolviera a lo largo del partido.
>
> **2. El empate se rompía con una TANDA DE PENALTIS.**
> Y esta decisión resulta ser la que abarata todo lo demás: al mantener siempre un
> ganador, **nada del inventario del §3 hay que tocarlo** — ni el reparto de la
> apuesta, ni `id_ganador`, ni la misión `duelos_ganados`, ni el rango y el loot
> de PvE, ni las vistas que pintan "ganaste". Además desbloquea 4 entradas de la
> Biblia que estaban muertas: El Orden del Destino, Guerra Psicológica, Tiempo
> Extra y El Gol que lo Cambia Todo.
>
> ### ⚠️ Lo que la medición destapó, y que hay que resolver con esto
>
> Con el partido decidiendo, **el favorito gana mucho más** que con la curva Elo:
> de 240 contra 100, del 69,1 % al **91,0 %** (el débil cae del 30,9 % al 9,0 %).
> La curva Elo va acotada a 0,05–0,95 con `k=400`, y eso es justo lo que daba
> opciones al mazo flojo; sumar diez ocasiones independientes es mucho más
> determinista.
>
> **Tiene una cara buena y otra mala, y son el mismo dial.** La buena: el §15.8 se
> queja de que hoy pensar bien el equipo aporta *dos décimas*, y con el partido
> decidiendo la fuerza sí pesa. La mala: en los extremos se pasa de largo. El dial
> es la conversión de ocasión en gol (`pGol`, `consultas.php:3006`), y por §5.4
> tiene que salir del código a `configuracion`.
>
> ### ⛔ MEDIDO DESPUÉS: no se pueden tener las tres cosas a la vez
>
> Se intentó calibrar `pGol` para reproducir las probabilidades de hoy (criterio
> "que el cambio sea arquitectónico, no de balance"). **No hay valores que lo
> consigan sin destrozar el partido.** Barrido completo, 1.200 partidos por celda:
>
> | dial (`base`/`sens`) | error vs Elo | empates | goles/partido |
> |---|---|---|---|
> | 0,06 / 0,30 *(el de hoy)* | 8,98 pp | 28,7 % | 1,74 |
> | 0,06 / 0,14 | 4,52 pp | 40,7 % | 1,13 |
> | 0,06 / 0,06 *(el que mejor casa)* | **1,39 pp** | **52,1 %** | **0,84** |
> | 0,12 / 0,30 *(el más futbolístico)* | 10,47 pp | 23,0 % | 2,34 |
>
> Se probó también meter la varianza donde la tiene el fútbol —**la forma del
> día**, la fuerza de cada equipo multiplicada por un factor aleatorio del
> partido— para no aplanar la conversión. **Ayuda poco:** a ±45 % el error solo
> baja de 8,98 a 6,78 pp, y el caso extremo (240 contra 100) se queda en 83,5 %
> frente al 69,1 % de la Elo. Un ±45 % casi nunca invierte esa diferencia.
>
> **La razón es estructural, no de ajuste:** el acotado 5–95 % de la curva Elo es
> un **suelo arbitrario** que no tiene equivalente natural en un partido simulado.
> Diez ocasiones sumadas son inherentemente más deterministas que una moneda
> acotada.
>
> ### Y el conflicto de fondo con el §15.8
>
> El §15.8 documenta DOS deseos que ya estaban en tensión entre sí:
> 1. que un equipo flojo bien pensado pueda ganar a uno de todo SRF (hoy 11,2 %),
> 2. que pensar bien el equipo importe (hoy aporta *dos décimas*).
>
> Más azar sirve al primero y traiciona al segundo; menos azar, al revés. **Que el
> partido decida empuja fuerte hacia el segundo**: la fuerza del mazo pasa a pesar
> de verdad. Pero con ello el débil cae del 30,9 % al 9,0 %, que va justo en contra
> del primero, que es el que el §15.8 señala como el problema principal.
>
> **Esto ya no es una decisión de implementación.** Hay que elegir cuál de los tres
> objetivos se sacrifica, y eso lo decide Alejandro.
>
> ---

> Análisis para que Alejandro decida. Escrito el 2026-08-10, con el catálogo en 62
> entradas (52 nombres de la Biblia).
>
> Lo pide el CLAUDE.md §12 punto 7: es el bloque más grande de lo que queda,
> **17 de las 48 entradas pendientes**, y el propio documento lo deja escrito
> como *"exige mover la resolución del duelo a después del partido, y eso está
> sin decidir"*.

---

## 1. Qué es `impacto: "partido"`

El catálogo declara tres impactos (`db/minijuegos.php`, cabecera). Los dos
primeros están construidos y en uso:

- `ninguno` — solo suma a la puntuación de actuación. **14 entradas.**
- `jugada` — puede cambiar el desenlace de ESA jugada. **48 entradas.**
- `partido` — **reservado, sin usar.** Efecto más allá de la jugada: arrastra a
  las siguientes.

Las 17 entradas que lo necesitan son casi todas de ritmo y de moral: El Grito de
Guerra, Dormir el Partido, La Relajación Peligrosa, Crisis en el Vestuario, La
Fiesta Peligrosa, El Baile Provocador, La Furia del Clima, La Sincronía Perfecta,
Abrir o Cerrar el Juego, El Cambio de Flanco, El Detective del Área, El Milagro
Imposible, La Última Trinchera, El Segundo Definitivo, Jugar con el Reloj, El
Golpe de Timón, Salir a Matar o Caminar.

---

## 2. Cómo funciona hoy, en orden real

Todo esto pasa dentro de **una sola transacción** de `resolverDuelo()`
(`db/consultas.php:4808-5030`), **antes de que se juegue un solo minuto**:

1. Congela compos y calcula la fuerza de cada uno con sus aumentos.
2. Curva Elo → probabilidad `$p` (`:4899`, con `duelo_k` en `:4895`).
3. **`$sorteo = mt_rand() / (mt_getrandmax() + 1)`** (`:4904`) **→ `$ganaCreador = $sorteo < $p`** (`:4905`).
4. Simula el partido con la restricción `["gana" => …]` para sacar los goles (`:4936`).
5. PvE: `rangoPartido($golesCreador, $golesRival)` (`:4946`).
6. **Mueve la apuesta**: el bote al ganador, o traspasa la carta (`:4949-4966`).
7. Escribe la fila: `estado='resuelto'`, `id_ganador`, goles, `valor_sorteo` (`:4969`).
8. PvE: progreso de nodo, recompensa de monedas y loot (`:5007`).
9. `commit`.

El partido narrado se juega **después**, contra un duelo ya resuelto y ya pagado.
Los minijuegos de impacto `jugada` mueven el marcador dentro del margen que
`cabeCambioMarcador()` autoriza, y **nunca al ganador**.

---

## 3. Lo que depende de que el resultado esté decidido antes

| Depende | Dónde | Qué pasa si el partido decide |
|---|---|---|
| **Reparto de la apuesta** | `consultas.php:4949-4966` | Las monedas y la carta ya cambiaron de dueño antes del primer minuto. Habría que aplazar el pago. |
| **`id_ganador` en la fila** | `consultas.php:4972` | Lo leen `duelo.php:203` y `duelos.php:260` para pintar "ganaste". Tendría que quedar en `NULL` durante el partido. |
| **Misión `duelos_ganados`** | `consultas.php:6003-6004` | Cuenta `WHERE id_ganador = :id`. Si el ganador puede cambiar, un progreso ya reclamado podría dejar de ser cierto. |
| **Rango y loot de PvE** | `consultas.php:4946`, `:5007` | El rango sale del marcador y el loot ya se otorgó en la misma transacción. |
| **Invariante §1.3** | `cabeCambioMarcador()` | Existe *precisamente* porque el ganador es intocable. Si dejara de serlo, el presupuesto entero de §15.5 sobra. |
| **Destape de aumentos** | `duelo.php:435` | La regla antiabuso es "solo con el duelo ya resuelto". |

### 3.1 El hallazgo que no estaba documentado: `valor_sorteo` hace dos trabajos

`$sorteo` es **el mismo número** que decide el ganador (`$sorteo < $p`, `:4905`) y
el que luego siembra toda la narración, los minijuegos y los datos ocultos
(`valor_sorteo`, §15.1).

Hoy eso es una virtud: garantiza que el relato y el resultado no puedan
contradecirse. Si el resultado pasa a decidirse al final, **hay que separarlos en
dos números**, y con ello se pierde esa garantía gratis. No es un bloqueo, pero es
trabajo y es una fuente de bugs sutiles (un relato que no cuadra con el marcador
ya ha pasado una vez en este proyecto, ver §15.7).

### 3.2 Y el que más me preocupa: abandonar pasaría a ser una jugada

§15.3 fija la regla *"si no estás atento, te lo pierdes"*: el partido arranca
cuando los dos han latido o al vencer `partido_espera_seg`, y no espera a nadie.
**Eso es seguro hoy porque irse no cuesta nada** — el duelo ya está resuelto y
pagado.

Si el partido decidiera el resultado, irse a mitad del encuentro se convierte en
una decisión con consecuencias, y hay que responder a algo que hoy no existe:
¿pierde quien se va? ¿se resuelve por Elo como hoy? ¿se pausa y se reanuda?
Cualquiera de las tres cambia el juego, y las tres necesitan que el aviso al rival
sea honesto. **Esta es la parte de diseño, no de código.**

---

## 4. Los caminos

### A) No hacerlo. Reinterpretar las 17 con los impactos que ya existen

Coste bajo. Se pierde parte de la intención de la Biblia, pero no toda: varias de
las 17 son de moral, y el **momentum ya existe** (§1.4) sin tocar el resultado.

### B) Acotar `partido` al MARCADOR y al RITMO, nunca al ganador ← *recomendado*

Un minijuego puede arrastrar a las jugadas siguientes —mover el momentum, poner
un malus temporal a una línea, cambiar el ritmo, abrir un bono tardío— pero el
**ganador lo sigue decidiendo la curva Elo** y la invariante del §1.3 sigue viva.

**No hay que mover la resolución.** Nada de la tabla del §3 se rompe.

Cubre **15 de las 17**: El Grito de Guerra, Dormir el Partido, La Relajación
Peligrosa, Crisis en el Vestuario, La Fiesta Peligrosa, El Baile Provocador, La
Furia del Clima, La Sincronía Perfecta, Abrir o Cerrar el Juego, El Cambio de
Flanco, El Detective del Área, El Milagro Imposible, La Última Trinchera, El
Segundo Definitivo y Jugar con el Reloj. Se quedan fuera **Salir a Matar o
Caminar** (necesita cansancio) y **El Golpe de Timón** (cambio de formación en
caliente, que exige recalcular fuerza a mitad de partido).

**Lo que sí hay que construir:** hoy el partido en vivo **no guarda estado**.
`estadoPartido()` deriva todo del reloj de pared y `narracionDuelo()` regenera el
relato entero desde `valor_sorteo` en cada sondeo. Un malus aplicado en el minuto
30 tiene que valer del 30 al 90, así que la narración pasa a ser función de
*(valor_sorteo + decisiones tomadas)*. Las decisiones ya están guardadas
(`duelo_minijuegos`), así que es reconstruible: `narracionDuelo()` tendría que
**reproducir las decisiones almacenadas** para rearmar el estado.

> ⚠️ **Restricción dura de este camino:** los modificadores pueden cambiar el
> relato, el momentum y **qué minijuegos se ofrecen**, pero el marcador debe
> seguir moviéndose SOLO por los deltas explícitos que pasan por
> `descontarGolRival()` / `sumarGolPropio()`. Si un malus alterara qué ocasiones
> acaban en gol dentro de la simulación, el marcador podría salirse del margen que
> autoriza `cabeCambioMarcador()` y contradecir al ganador. Los modificadores
> cambian **oportunidad y color, no desenlaces**.

### C) Mover la resolución a después del partido — lo que la Biblia pide de verdad

El partido decide. Coste alto y toca el núcleo: separar `valor_sorteo` en dos,
aplazar el pago de la apuesta, dejar `id_ganador` en `NULL` mientras se juega,
aplazar rango y loot de PvE, revisar la misión `duelos_ganados`, y **resolver la
pregunta del abandono** (§3.2), que es la que de verdad decide si esto es viable.

A cambio: el partido pasa a importar de verdad, que es la intención original de la
Biblia (§0.2, *"un duelo era un botón que se pulsa y un resultado que aparece"*).

### D) Híbrido: el marcador nace del partido y el ganador sale del marcador

"El partido decide" de verdad, con la Elo solo rompiendo empates. Requiere todo lo
de C, más definir qué pasa cuando el marcador contradice a la fuerza de los mazos
—que es justo lo que la Elo y el balance de la Capa 2 vienen a garantizar—.

---

## 5. Recomendación

**B.** Desbloquea 15 de las 17 sin tocar el modelo de resolución, no rompe nada de
la tabla del §3, y **deja C abierto para después**: si más adelante quieres que el
partido decida, el estado persistente que B construye es exactamente la pieza que
C necesita de todas formas.

C es la versión fiel a la Biblia, pero su parte difícil no es el código: es
decidir qué pasa cuando alguien se va a mitad de partido, y eso no lo puede
decidir un programador por ti.

---

## 6. Si eliges B, el orden de trabajo sería

1. **Estado de partido reconstruible.** `narracionDuelo()` reproduce las
   decisiones guardadas para rearmar momentum y modificadores. Sin entradas
   nuevas todavía: solo el andamio, verificando que el relato sigue saliendo
   idéntico cuando no hay decisiones con impacto `partido`.
2. **Que el motor lea `impacto: "partido"`**, igual que se hizo con `ninguno`
   (§15.5) — y con su propio tope, porque una decisión que arrastra vale más que
   una que no.
3. **Las 3 más simples primero**: El Grito de Guerra, El Baile Provocador y Dormir
   el Partido, que son puro momentum y no tocan líneas.
4. Medir que el marcador sigue dentro del margen del §1.3 sobre 4.000 partidos
   sintéticos, que es la comprobación que ya exige §15.9.
5. Las 12 restantes, y actualizar §15.4 y §12.
