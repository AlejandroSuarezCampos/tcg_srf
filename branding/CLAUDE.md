# Superliga Frontier TCG — contexto de trabajo

> Documento de traspaso, versión 7.5 (2026-08-11).
> Léelo entero antes de tocar código. Si trabajas desde otro equipo con **la
> misma copia del proyecto** (mismos ficheros, misma base de datos `tcg`), este
> fichero es todo el contexto necesario: no hace falta la conversación anterior.
>
> **Sustituye a las versiones anteriores.**
>
> **Lo grande de la v7: el partido dejó de ser un botón.** Un duelo PvP ya no
> se resuelve y se enseña — ahora se JUEGA: el servidor narra el encuentro
> minuto a minuto, los dos jugadores lo ven a la vez, y el reloj se detiene
> para ambos cuando salta un minijuego. Todo eso es nuevo y vive en el §15,
> que es la sección que hay que leer antes de tocar nada de duelos.
>
> Con ello entraron: el **motor de eventos** (§15.1), el **marcador PvP nacido
> de la simulación** —que jubiló la fórmula provisional `marcadorDuelo()`—, el
> **partido en vivo** (migración `016`), los **dos primeros minijuegos** del
> catálogo (§15.4), la **puntuación de actuación** y el **veredicto de partido**
> (§15.6). Las **cadenas se quedaron intactas** a propósito (§15.7).
>
> **Hay una biblia de diseño nueva** en `branding/Biblia/` (cuatro ficheros
> `.md`) con el catálogo completo de ~90 minijuegos, la psicología de
> retención y el veredicto de priorización. Lo construido en la v7 sale de
> ahí. Léela si vas a seguir por los minijuegos; el §15 cita sus secciones.
>
> **Aviso de balance medido, no supuesto** (§15.8): hoy pensar bien un equipo
> aporta prácticamente nada frente a la rareza bruta, porque los compos premian
> MEZCLAR afinidades en vez de enfocarlas. Está cuantificado y no se ha tocado:
> es una decisión de Alejandro.
>
> **De la v6 sigue vigente:** esta copia se abrió con la BD local
> desactualizada (faltaba la `013`) aunque las comprobaciones de §5.2 daban
> bien. Antes de diagnosticar algo como "roto", comprueba si la BD
> sencillamente no está al día: lo más probable es que ninguna migración falle
> en silencio, sino que faltaba aplicarla. El `gd` de PHP, que el §8 daba por
> problemático, **ya está activo** en CLI y en Apache.
>
> **Sesión de arranque 2026-08-07 (misma tarde que la v7):** dos cosas
> encontradas y ya resueltas, ninguna toca código:
> 1. **MariaDB no arrancaba.** `mysql_error.log` daba `Aria engine: log data
>    error` / `log initialization failed` al iniciar — el log de recuperación
>    de Aria quedó corrupto, probablemente por un apagado no limpio de una
>    sesión anterior de XAMPP. Se arregló **moviendo** (no borrando)
>    `mysql\data\aria_log.00000001` y `aria_log_control` a
>    `mysql\data\_aria_backup_corrupt\`: MariaDB los regenera solo al arrancar.
>    Es la reparación estándar para este error y no toca `ibdata1` ni las
>    tablas InnoDB reales (que son casi todas en este proyecto). Nueva entrada
>    de trampa en §8.
> 2. **Esta copia tampoco tenía la `016` aplicada** (faltaban `duelo_minijuegos`
>    y las 4 filas `partido_*` de `configuracion`) — mismo patrón que la `013`
>    en la v6. Aplicada con el flag `--default-character-set=utf8mb4` de
>    siempre. Confirma otra vez la regla: **comprueba migraciones cada sesión,
>    no asumas que la copia local está al día por tener `.git` o por haber
>    funcionado antes.**
>
> Tras ambos arreglos: Apache y MariaDB arriba, las 5 comprobaciones del paso 5
> dan los valores esperados, `styleguide.php` responde 200, los 42 ficheros
> `.php` pasan `php -l` sin error, y la codificación de `rasgos.montana` sigue
> en `c3b1`. El proyecto queda listo para seguir sin arrastrar nada pendiente
> de entorno.
>
> **v7.1 — el catálogo de minijuegos pasa de 2 a 12 (§15.4).** Lo importante no
> son las diez entradas nuevas sino los **dos límites que había debajo**:
> `minijuegoDeEvento()` cogía la primera entrada que casaba, así que solo cabía
> **una por (familia, lado)** y cualquier segunda era código muerto; y las
> familias que la v7 daba por "libres y baratas" no lo eran —`defensa` es
> **inalcanzable** y la que de verdad estaba sin estrenar era `balon_parado`
> atacando, la segunda más frecuente—. Con la elección determinista entre
> candidatas, la clave `tipos` y un tercer dato oculto (`colocacion_defensa`,
> leído del defensa porque **en una falta no hay portero**), las tres
> combinaciones que existen quedan cubiertas con cuatro entradas cada una.
> Hay verificador: `db/verificar_minijuegos.php` (§15.9).
>
> ---
>
> ## ⚠️ v7.2 — ESTA RAMA UNE DOS HISTORIAS Y **QUITA EL REDISEÑO DE CROMOS**
>
> **Léelo antes de tocar nada si vienes de `srf-franshu`.**
>
> Hasta ahora había **dos líneas de trabajo paralelas que no compartían ni un
> solo commit**: la de `master`/`srf-franshu` en GitHub, y una local que nació
> de un `git init` sobre una copia descomprimida. Esta rama las une por primera
> vez. Lo que ha entrado y lo que ha salido:
>
> **Entra** (de la línea local): el §14 reescrito (cajas y sobres en pseudo-3D
> ya CONSTRUIDO, no el plan de la v4), el §15 entero (partido narrado en vivo,
> minijuegos, veredicto) y el catálogo de 12 minijuegos de la v7.1.
>
> **Entra** (de `srf-franshu`): el **importador de datos oficiales** completo,
> que era su otra mitad. Está aquí como **§16** — antes era su §15, renumerado
> porque en la línea local el §15 ya era el partido narrado. Sus referencias
> internas `§15.x` se han reescrito a `§16.x`; las que apuntan a §12 y §13 valen
> igual porque esas secciones significan lo mismo en los dos documentos.
>
> **SALE, por decisión explícita de Alejandro: el rediseño del componente de
> tarjeta (el §16 de `srf-franshu`).** Se han revertido a la versión local
> `components/carta.php`, `assets/css/components.css`, `album.php`,
> `coleccion.php`, `mercado.php`, `mazos.php`, `styleguide.php`,
> `panel/cromos.php`, `panel/assets/js/scriptCromos.js` y `partials/head.php`;
> y se han borrado `partials/carta_detalle.php`, `assets/js/detalle-carta.js` y
> `db/migraciones/015_mostrar_stats.sql`. También se ha quitado la columna
> `mostrar_stats` de todas las consultas y de `crearCromo()`/`actualizarCromo()`
> en `db/consultas.php`, **sin tocar `origen_importacion`**, que es del
> importador y se queda.
>
> **Nada de eso se ha perdido.** El estado anterior de la rama está intacto en
> GitHub, en **`srf-franshu-backup-20260807`**, y los commits del rediseño
> siguen siendo ancestros de esta rama: la unión es un merge, no un reescrito de
> historia. Para recuperar el rediseño basta con sacar esos ficheros de la rama
> de respaldo.
>
> **Colisión de migraciones, ya resuelta:** las dos líneas crearon una `014`
> distinta. Se ha conservado la de `srf-franshu`
> (`014_importador_origen.sql`, ya publicada) y **la del partido en vivo ha
> pasado de `014` a `016`** (`016_partido_en_vivo.sql`). La `015` queda libre
> porque era la del rediseño retirado. Si ya habías aplicado la vieja `014` del
> partido en vivo, tu base de datos está bien: solo cambió el nombre del
> fichero, el SQL es el mismo y es re-ejecutable.
>
> **Ojo al leer:** `§16` es la sección del **importador**, mientras que `016` en
> backticks es la **migración** del partido en vivo. Son numeraciones distintas
> que por casualidad coinciden en el número.
>
> ---
>
> ## v7.3 — el catálogo de minijuegos pasa de 12 a 43, y ya no queda hueco sin cubrir
>
> Lo importante no son las 31 entradas nuevas sino **cuatro cosas que había
> debajo y que el §15 daba por buenas sin serlo**:
>
> 1. **`impacto` estaba declarado en el catálogo pero el motor NO lo leía.** Las
>    tres menciones en `db/consultas.php` eran comentarios. `resolverMinijuegoDuelo()`
>    movía el marcador solo por `lado` + `acierto`, así que `impacto: "ninguno"`
>    no existía de hecho y cualquier entrada disciplinaria o defensiva habría
>    sumado un gol de la nada. Sin arreglar esto no se podía construir nada de
>    lo demás.
> 2. **El cliente no sabía traducir `colocacion_defensa`.** El mapa de motes de
>    `duelo.js` solo tenía los seis valores de `remate` y `estilo_portero`, así
>    que los cuatro minijuegos de balón parado de la v7.1 llevaban desde
>    entonces cerrando con *"Salió salta y se la adivinaste"* — enseñando el
>    valor crudo del servidor. Ahora cada valor declara su **grupo** y la frase
>    de cierre se elige por grupo.
> 3. **Las 4 entradas de `disparo` no tenían clave `tipos`**, así que se
>    ofrecían igual en `parada`, `tiro_fuera` y `despeje`. Se leía mal: *"cómo la
>    golpeas de lejos"* caía sobre un despeje de un central. Repartidas por tipo.
> 4. **`defensa` y `balon_parado` defensivas no eran "baratas de empezar" ni
>    simplemente inalcanzables: eran inalcanzables POR UNA LÍNEA.** El filtro
>    `$tieneSentido` exigía `tipo === "gol"` para defender. Cambiado a "cualquier
>    jugada del rival que traiga `familia_def`", aparecen tres huecos nuevos con
>    3.174 apariciones medidas de cada 600 partidos.
>
> **Entró además la segunda primitiva de la Biblia, el `medidor`** (§2.1): una
> aguja recorre las tres zonas en bucle y el jugador la detiene. La clave del
> diseño es que **las zonas SON las opciones**, así que el ciclo cerrado, la
> pista, la opción segura y el verificador siguen valiendo sin tocarse: cambia
> cómo eliges, no qué se decide.
>
> **Los 10 huecos que el motor puede producir están cubiertos.** No queda
> ninguno sin entradas, incluido el de `arbitro` que el §15.4 daba por
> "inservible" (se arregla adjuntando al evento de tarjeta unos protagonistas
> que el motor ya calculaba y tiraba).
>
> **Migración nueva, la `017`**, con un parámetro de balance que hizo falta y
> está medido: sin él, **1 de cada 7 jugadores** (13,94 %) gastaba sus dos
> decisiones en minijuegos incapaces de tocar el marcador. Ver §15.5.
>
> ### Y dos fallos que solo aparecieron AL JUGAR, no al medir el catálogo
>
> Alejandro reportó *"solo me están saliendo el mismo minijuego"* con las 43
> entradas ya dentro. Eran dos causas encadenadas, las dos ya corregidas:
>
> 1. **`azarSembrado()` se estaba usando como función hash.** Sembrar con
>    `semilla * K + id_evento * c` y coger el primer valor produce ciclos cortos,
>    porque el primer valor de un LCG es casi lineal en su estado inicial. Con 5
>    candidatas la colisión entre eventos consecutivos era del **0,0 %** —
>    imposible, no improbable. Arreglado en `Tcg::azarDeJugada()`, que siembra una
>    vez y **avanza** el generador. Afectaba a los cinco sitios que adivinan algo:
>    qué minijuego sale y los cuatro datos ocultos. Nueva trampa en §8, con la
>    métrica que hay que usar para detectarlo (repetición, no variedad).
> 2. **Las decisiones se apelotonaban al principio del partido**, porque se
>    cogían las primeras jugadas que valían y los huecos defensivos nuevos
>    multiplicaron las tempranas. Minuto mediano 10', última decisión en el 17'.
>    Arreglado en `Tcg::repartirDecisiones()`, que reparte por ventanas del
>    encuentro y evita repetir minijuego: mediana 46', última 68', y la
>    repetición dentro de un partido baja al 0,75 %. Ver §15.5.
>
> **Lección para la próxima:** el catálogo se puede verificar en frío —y el
> verificador daba verde con las dos cosas mal—, pero *cuándo* y *con qué
> variedad* aparecen las decisiones solo se ve jugando o midiendo el reparto
> dentro de un mismo partido. Ninguna de las dos las habría cazado contando
> entradas.
>
> ---
>
> ## ⚠️ v7.4 — EL PARTIDO DECIDE EL DUELO. Es el cambio más grande del motor.
>
> **Si vienes a tocar duelos, lee el §15.10 antes que nada de esta sección.**
>
> Hasta la v7.3 el ganador se sorteaba **antes del primer minuto** y el partido
> era su puesta en escena. Alejandro lo vio y lo dijo mejor que cualquier
> análisis: *"pero entonces no tendrían sentido los minijuegos, porque el
> resultado viene dado ya de antes"*. Se comprobó y era literal — en PvP los goles
> se leían en 4 sitios y la actuación en 2, **todos de pantalla**.
>
> Ahora: `resolverDuelo()` deja el duelo en **`en_juego`** sin ganador y sin pagar,
> la simulación corre en **modo natural** (empates incluidos: **32 %** entre
> iguales), los minijuegos mueven el marcador de verdad, y al llegar al minuto
> final `liquidarPartido()` escribe el ganador y entrega el bote. **Un empate se
> decide en la tanda de penaltis.**
>
> Cuatro cosas que hay que saber sí o sí:
>
> 1. **Las migraciones `019` y `021` son OBLIGATORIAS.** Es la primera vez que hay
>    migraciones que no se pueden saltar: sin `en_juego` en el enum, ningún duelo
>    PvP se monta. Ver §5.2.
> 2. **La §1.3 ya no existe.** `cabeCambioMarcador()` se borró y su condición salió
>    del `UPDATE` de `descontarGolRival()`. Si la ves en un comentario, el
>    comentario está desfasado. **No la vuelvas a poner**: con `id_ganador` en
>    `NULL` durante `en_juego` daría siempre falso y ninguna parada contaría.
> 3. **Un duelo `en_juego` tiene el dinero de los dos retenido.** Por eso
>    `cerrarPartidoSiToca()` tiene dos ramas de abandono, y por eso el cierre se
>    llama desde tres sitios (sondeo, `duelo.php`, `duelos.php`). Un partido a
>    medias ya no es un partido perdido: es un bote que no vuelve a nadie.
> 4. **El coste está medido y aceptado: el favorito pasa del 69,1 % al 91,0 %.**
>    Alejandro lo aceptó a cambio de que los minijuegos cuenten. Las palancas para
>    recalibrar son `duelo_k` y `partido_presupuesto_marcador`.
>
> **Las cadenas (PvE) están intactas** y tienen prueba propia que lo demuestra,
> porque el cambio pasa justo por dentro de `resolverDuelo()`.
>
> ---
>
> ## v7.5 — la tanda de penaltis se JUEGA (§15.11)
>
> El §15.10 dejó un número incómodo: **el 27,7 % de los duelos acaba empatado**, o
> sea que más de uno de cada cuatro se decidía en una tanda que el jugador no veía.
> Ya se juega, con la regla que pidió Alejandro: **cuatro huecos; si tirador y
> portero eligen el mismo, parada; si no, gol.** Ni Ataque ni Portería cuentan.
>
> **Es la primera interacción simultánea del juego**, y eso importa más que la
> pantalla: en todos los minijuegos el dato oculto sale de las cartas y el servidor
> lo recalcula cuando quiere, y por eso la narración entera es función de
> `valor_sorteo` sin guardar nada. Aquí el dato oculto es **lo que el otro está
> eligiendo ahora**, que no se deriva de nada — de ahí la tabla `duelo_penaltis`.
>
> Lo que hay que saber antes de tocarla:
> 1. **La migración `024` es OBLIGATORIA**: sin la tabla, ningún duelo empatado se
>    puede cerrar y el bote se queda retenido.
> 2. **La elección del rival no puede viajar al cliente**, ni en el sondeo ni en la
>    respuesta del endpoint. Es la única protección que tiene el juego; hay prueba
>    específica de que no se filtra.
> 3. **Hay una tercera rama de abandono**: partido empatado + tanda a medias + nadie
>    que vuelva. La cubre `cerrarConTandaSiHace()`.
> 4. `tandaDePenaltis()` (la automática) **se borró**, mismo criterio que
>    `cabeCambioMarcador()`: dejarla invitaría a decidir el duelo sin que el jugador
>    tocase nada.
>
> Dos propiedades que salen gratis de la regla: con los dos eligiendo a ciegas
> **entra el 75 % de los penaltis**, casi exactamente el porcentaje del fútbol real
> sin haber ajustado ningún número; y es **el único sitio donde el mazo no importa**,
> así que un mazo flojo que llega al empate tiene la tanda al 50 %.
>
> Medido sobre 300 duelos jugados enteros: **92 tandas, 932 tiros, 26,1 % de
> paradas** contra el 25 % teórico. Esa cifra es la comprobación de que la regla se
> aplica tal cual.
>
> ---
>
> ## Lo siguiente: llevar todo esto a las CADENAS (§15.12)
>
> **Diseñado, decidido y medido el 2026-08-11; sin escribir.** El partido decidirá
> también en PvE y los minijuegos influirán en las recompensas **de ese partido**.
> Los cofres mantienen su contenido fijo —ya lo mantienen— y dan **premio extra si
> todos los partidos previos al cofre están en S en Extremo**. El plan son cinco
> piezas y está entero en el §15.12, con las tres trampas de la parte de cliente y
> el aviso de que el rango S quedará inalcanzable hasta que se calibre.
>
> **Verificado sobre 300 duelos jugados enteros por el camino real** (25 con carta),
> más uno a mano en el navegador entre dos cuentas que acabó 2-2 y lo decidió la
> tanda. Las cifras que importan: **300/300 liquidados, 0 colgados, el total de
> monedas y de copias no cambia, y el 58,3 % de los duelos acabó con un marcador
> distinto al que salió de la simulación** — antes era el 0 %. Tabla completa en el
> §15.10.

---

## Cómo arrancar en un chat nuevo

Estás recogiendo un proyecto con **Fases 0, 1 y 2 cerradas**, más la ceremonia
de sobres en pseudo-3D (§14) y el **partido narrado en vivo con minijuegos**
(§15) construidos encima. Está construido y funcionando contra la base de datos
real: Deck Builder, Duelos PvP completos (Capas 1, 2 y 3), Misiones,
formaciones alternativas, **Cadenas de Partido (PvE)**, la **apertura de sobres
con cajas 3D CSS** y el **partido PvP jugable minuto a minuto**. No lo rehagas
ni lo revises salvo que Alejandro lo pida.

**Si vas a tocar duelos, lee el §15 antes que nada.** Es lo más nuevo, lo que
más se ha movido, y tiene reglas propias que no se deducen del resto.

> ## ⚠️ ESTADO EXACTO AL CERRAR LA SESIÓN DEL 2026-08-11 — LEE ESTO PRIMERO
>
> **1. El interruptor de pruebas ya está QUITADO** (`depuracion_forzar_empate = 0`
> desde el 2026-08-11, por decisión de Alejandro). Los partidos PvP vuelven a
> decidirse por el marcador y solo van a la tanda los que acaban empatados de
> verdad (~27,7 %, §15.10). El interruptor **sigue existiendo** y es la forma de
> probar los penaltis a mano: ponlo a `1` para que todo partido acabe 1-1, y
> **acuérdate de devolverlo a `0`** al terminar. Si algún día ves todos los duelos
> empatados, esto es lo primero que hay que mirar — no está roto:
> ```
> C:\xampp\mysql\bin\mysql.exe -u root -e "UPDATE tcg.configuracion SET valor='0' WHERE clave='depuracion_forzar_empate';"
> ```
>
> **2. La rama es `minijuegos-x75` y SÍ hay remoto.** Está en
> `github.com/AlejandroSuarezCampos/tcg_srf`, empujada y al día (10 commits sobre
> `bb27722`). La nota antigua de "hay `.git` pero sin remoto" **ya no vale**.
>
> **3. Migraciones hasta la `025`. Tres son OBLIGATORIAS**, y es la primera vez que
> hay migraciones que no se pueden saltar: la `019` (añade `en_juego` al enum), la
> `021` (columna `resuelto_por_tanda`) y la `024` (tabla `duelo_penaltis`). Sin
> ellas los duelos PvP no se montan o no se pueden cerrar. Ver §5.2.
>
> **4. Hay una suite de pruebas EN EL REPO. Ejecútala antes y después de tocar el
> partido:**
> ```
> C:\xampp\php\php.exe db/pruebas/correr_todas.php
> ```
> Monta y borra `tcg_prueba` ella sola, **nunca toca la base real**, y sale con
> código 1 si algo falla. Hoy: 5 suites en verde. La grande, `probar_300.php`
> (300 duelos de punta a punta, ~7 min), se lanza aparte.
>
> **5. Lo siguiente que toca está decidido y medido: las CADENAS, §15.12.** Cinco
> piezas, con las decisiones de Alejandro textuales. No hace falta volver a
> preguntárselas.
>
> **6. Los 16 PNG originales ya no existen y el borrado está commiteado**
> (2026-08-11). Alejandro confirmó que los eliminó a propósito, así que la carpeta
> `assets/img/_originales_sin_optimizar/` **ha desaparecido entera** del proyecto y
> del árbol del §2. Ningún fichero de código la referenciaba — se comprobó antes de
> registrar el borrado. El arte servido es el WebP optimizado de
> `assets/img/Cromos/`, que no se ha tocado.

**Lo primero que tienes que hacer, en este orden:**

1. Leer este documento entero.
2. Comprobar el entorno: `Get-Process httpd`, `Get-Process mysqld`. Si algo está
   parado, lánzalo desde `C:\xampp\`. Luego abre
   `http://localhost/tcg_srf/styleguide.php` para ver el sistema de diseño.
3. Verificar que el repo está intacto:
   ```
   for f in *.php partials/*.php components/*.php db/*.php assets/ajax/*.php panel/*.php; do C:/xampp/php/php.exe -l "$f"; done
   ```
4. **Hay `.git` Y hay remoto**, en la rama `minijuegos-x75`:
   ```
   git remote -v      # github.com/AlejandroSuarezCampos/tcg_srf
   git status -sb     # debe estar a la par con origin/minijuegos-x75
   ```
   *(La nota antigua decía "sin remoto". Se configuró el 2026-08-11.)*
   Si en tu copia NO hay `.git` porque Alejandro volvió a descomprimir un ZIP,
   entonces sí aplica el aviso viejo: no asumas commits ni ramas, y considera
   `git init` antes de cambios grandes, avisando primero.
5. **Comprobar que la BD está al día. Una sola consulta lo dice todo:**
   ```
   C:\xampp\mysql\bin\mysql.exe -u root tcg -e "SELECT (SELECT COUNT(*) FROM rasgos) rasgos, (SELECT COUNT(*) FROM cromo_rasgos) compos, (SELECT COUNT(*) FROM cadena_nodos) nodos, (SELECT COUNT(*) FROM duelo_minijuegos) mj, (SELECT COUNT(*) FROM duelo_penaltis) pen, (SELECT COUNT(*) FROM configuracion WHERE clave LIKE 'partido%' OR clave LIKE 'tanda%' OR clave LIKE 'depuracion%') params;"
   ```
   Esperado: `rasgos` 9, `compos` 38, `nodos` 18, y **`params` 12**. Si una tabla
   no existe, el error te dice cuál falta:
   - `duelo_minijuegos` → falta la `016`, el partido en vivo no funciona.
   - `duelo_penaltis` → falta la **`024`**, ningún duelo empatado se puede cerrar.
   - `params` por debajo de 12 → falta algún parámetro de calibrado (§5.2 dice
     cuál trae cada migración). El código tiene valores por defecto, así que
     funciona igual, pero Alejandro no puede calibrar sin las filas.

   Y el enum, que es el que rompe en silencio (ver la primera trampa del §8):
   ```
   C:\xampp\mysql\bin\mysql.exe -u root tcg -e "SHOW COLUMNS FROM duelos LIKE 'estado';"
   ```
   **Tiene que aparecer `en_juego`.** Si no, falta la `019` y `resolverDuelo()` se
   negará a montar partidos — con un error que nombra la migración, porque hay una
   red puesta para eso.
6. **Correr la suite del partido**, que es más fiable que mirar tablas:
   ```
   C:\xampp\php\php.exe db/pruebas/correr_todas.php
   C:\xampp\php\php.exe db/verificar_minijuegos.php
   ```
7. Auditar la codificación (§5.3), que ya ha mordido dos veces:
   `C:\xampp\php\php.exe db/migraciones/004_reparar_codificacion.php`

**Antes de escribir código nuevo, presenta un plan corto y espera el visto
bueno.** Es la forma de trabajar acordada: plan → aprobación → implementación →
resumen de cierre. Si algo tiene dos lecturas razonables que llevarían a trabajo
distinto, pregúntalo con opciones concretas en vez de decidir por tu cuenta.

**Si Alejandro no dice por dónde seguir, lo siguiente que toca son las CADENAS
(§15.12).** Está diseñado, decidido y medido; solo falta escribirlo, y son cinco
piezas con las decisiones textuales dentro — **no hace falta volver a
preguntárselas**. Empieza por la 1 y la 2, que van juntas.

Si por lo que sea eso no toca, los otros frentes abiertos: **el desequilibrio de
compos** que el §15.8 deja medido (es lo que más afecta a la sensación de juego),
subir arte real a **panel/plantillas.php** (§14), o la **Fase 3** (§12).

**Más minijuegos NO es un frente útil ya**: van 75 y **no queda ninguno que sea
escribir una entrada más** — lo que falta está bloqueado por sistemas que no
existen (banquillo, cansancio, supertécnicas como dato) o excluido a propósito por
la regla de que un minijuego nunca castiga. El detalle, con el inventario de qué
bloquea qué, está en el punto 7 del §12.

---

## 0. Qué es esto

TCG coleccionable fan-made de la **Superliga Frontier**, la liga de Inazuma
Eleven: Victory Road. Las cartas representan jugadores, presidentes,
entrenadores y escudos reales de una comunidad activa, no personajes de ficción.

- **Stack:** PHP 8 + MariaDB sobre XAMPP. Sin framework, sin build, sin npm
  (con la excepción de GSAP, vendorizado en `assets/js/vendor/gsap/` y ya
  usado en la ceremonia de cajas 3D — ver §14. Three.js sigue sin vendorizar).
- **Raíz:** carpeta actual del proyecto (esta sesión la ve como
  `C:\xampp\htdocs\tcg_srf-master`; el resto del documento usa `tcg_srf` como
  nombre corto porque así se sirve normalmente: `http://localhost/tcg_srf/`
  o `http://localhost/tcg_srf-master/` según cómo esté montada la copia local).
- **Control de versiones:** hay `.git` **y hay remoto** —
  `github.com/AlejandroSuarezCampos/tcg_srf`, configurado el 2026-08-11. La rama
  de trabajo es **`minijuegos-x75`**, empujada y a la par con `origin`.
  *(Este punto decía "repo local, sin remoto configurado". Ya no es cierto.)*
  El `.git` se creó como red de seguridad antes de una reescritura destructiva del
  sistema de cajas/sobres (§14), y el primer commit
  (`"Checkpoint antes de reescribir..."`) es el estado previo a ella — útil si algo
  hay que revertir. **Nunca `--force`, nunca reescribir historial** sin que lo pida
  explícitamente Alejandro. Commitea y sube **solo cuando él lo pida**.
- **Ejecutado por una sola persona** (Alejandro), sin fecha de lanzamiento fija.
- **Gratuito**, sin monetización, exclusivo para participantes de la liga.
- **Legal:** proyecto fan-made sin ánimo de lucro. Inazuma Eleven es propiedad
  de Level-5. Sin afiliación. Aviso visible en **todas** las páginas
  (lo pone `partials/footer.php`).

**Documentos maestros** (en `branding/`):
- `Brand-Identity-Briefing-Superliga-Frontier-TCG.docx` — marca y diseño, 38 secciones.
- `Superliga_Frontier_TCG_Sistema_Compos_Balance.md` — la especificación de la
  Capa 2, el balance y el PvE, con más de 100M de duelos simulados detrás.
  **Está en Descargas, no en el repo; pídeselo a Alejandro si lo necesitas.**
- **`Biblia/` — cuatro `.md` con la sesión de diseño completa.** De aquí sale
  todo el §15. Contiene el motor de eventos (§1 de la Biblia), el catálogo de
  ~90 minijuegos (§2), el escalado de dificultad (§3), la psicología de
  retención (§4), el análisis de Copero/Wordle/Suika/Balatro (§6), el Pase de
  Temporada (§7) y el **veredicto de priorización** (§13.3), que es el que
  marcó el orden de trabajo de la v7. Cuando el §15 cita "§1.5 regla 6" o
  "§4.6" se refiere a ESTA biblia, no a este documento.
- `Rangos_estadisticas_SRF.xlsx` / `.csv` — rangos de estadísticas por rareza
  y posición para crear cartas nuevas (§15.8). El `.csv` es el que sirve para
  inserciones automáticas; el `.xlsx` lleva además el porqué y la
  verificación de balance.

**Cuenta de pruebas dedicada:** usuario `Claude` (id 9), contraseña `123456`,
`dictador=1`, ~1M de monedas, 22 cromos distintos (48 copias) y un mazo titular
"Once titular" completo en formación 442. **Úsala siempre** en vez de pedir
credenciales o improvisar. Iniciar sesión desde el navegador de pruebas:
```js
await fetch('/tcg_srf/login.php', {method:'POST',
  body: new URLSearchParams({nombre:'Claude', password:'123456'})});
```

**`CPU` (id 10) no es una persona:** es el rival virtual del PvE
(`Tcg::USUARIO_BOT`). No lo borres ni le cambies el nombre — las Cadenas lo
buscan por nombre.

> ⚠️ **Y `CPU` NO tiene `dictador = 1`.** Así que un *"borro todos los usuarios
> menos los admins"* **se lo lleva por delante**, y con él los 20 duelos de
> cadena en los que es el rival (`duelos.id_rival` es CASCADE desde la `023`).
> Cualquier limpieza de usuarios tiene que excluirlo a mano: `dictador` no basta
> como filtro.

### Limpieza previa a producción — el orden importa

Plan de Alejandro: **se mantiene ESTA base de datos**, y antes de subir se borran
las cartas de prueba, se testea el balance y se borran los usuarios menos los
admins. Dos cosas que hay que saber antes de ejecutarlo:

- **Los usuarios van primero, las cartas después.** Las claves ajenas de `cromos`
  son **RESTRICT** en `coleccion` y `duelo_alineaciones`, así que borrar una carta
  falla mientras alguien tenga una copia o mientras aparezca en la alineación de un
  duelo pasado. Al revés no funciona.
- **Borrar un usuario limpia lo suyo solo** (casi todo CASCADE: colección, mazos,
  duelos, aumentos, progreso, misiones). Lo único que queda son filas de
  `duelo_minijuegos`, que **no tiene ninguna clave ajena**: basura inofensiva,
  porque los `id_duelo` no se reutilizan y nadie las consulta sin su duelo.

---

## 1. Estado del trabajo

| Fase / bloque | Contenido | Estado |
|---|---|---|
| **Fase 0 — Fundamentos** | Tokens, componente de tarjeta, guía de estilo | ✅ Cerrada |
| **Fase 1 — Núcleo** | 9 pantallas migradas, aviso legal en todas | ✅ Cerrada |
| **Fase 2 — Migración BD** | `002`, 10 tablas nuevas, stats de combate | ✅ Aplicada |
| **Fase 2 — Deck Builder** | `mazos.php`, alineación sobre campo real | ✅ Construido |
| **Fase 2 — Duelos Capa 1** | Fuerza de mazo + curva Elo + sala en vivo | ✅ Construido |
| **Fase 2 — Duelos Capa 3** | Aumento pre-partido (tiers, plazo, fallback) | ✅ Construido |
| **Fase 2 — Duelos Capa 2** | **Compos: rasgos, ciclo, malus, Tensión** | ✅ **Construido (§10)** |
| **Fase 2 — Simulación de partido** | Modal de reloj + goles. **Ya solo lo usan las CADENAS** (modo clásico, §15.7) | ✅ Construido |
| **Fase 2 — Misiones** | `misiones.php`, progreso derivado, reclamo | ✅ **Construido (§11)** |
| **Fase 2 — Formaciones** | 8 formaciones, desbloqueables por cofre de cadena | ✅ Construido |
| **Fase 2 — Cadenas de Partido (PvE)** | Mapa de nodos, 5 dificultades, rangos, loot | ✅ **Construido (§11b)** |
| **§14 — Cajas y sobres en pseudo-3D** | `caja3d.php`, `panel/plantillas.php`, migración `013` | ✅ **Construido; arte real en curso** — 3 elementos recortados en disco, ver nota de v6 |
| **§15 — Motor de eventos narrado** | El partido se cuenta minuto a minuto (Biblia §1) | ✅ **Construido** |
| **§15 — Marcador PvP desde la simulación** | Jubila la fórmula provisional `marcadorDuelo()` (Biblia §1.3) | ✅ **Construido** |
| **§15 — Partido en vivo (PvP)** | Reloj en servidor, los dos jugadores a la vez, migración `016` | ✅ **Construido** |
| **§15 — Minijuegos** | Catálogo + **75** entradas jugables de ~100 (65 nombres de la Biblia) (Biblia §2). **Los 10 huecos alcanzables están cubiertos** y **las 4 primitivas construidas** | 🟡 **En marcha** (lo que falta necesita sistemas nuevos, no entradas) |
| **§15 — Las 4 primitivas de interfaz** | `eleccion` 44 · `medidor` 6 · `zona` 7 · `arrastre` 5 (§15.4b). Las cuatro de la Biblia | ✅ **Construido** |
| **§15 — `impacto: "partido"`** | 13 entradas que arrastran al resto del encuentro ampliando el presupuesto de marcador (§15.4d) | ✅ **Construido** |
| **§15 — Margen de los partidos** | El bucle del §1.3 aplanaba el 88,8 % de los duelos a un gol; ahora conserva la forma natural (§15.4e) | ✅ **Arreglado** |
| **§15 — EL PARTIDO DECIDE EL DUELO** | Se acabó el ganador pre-sorteado: el duelo queda `en_juego`, el marcador manda, el empate va a **la tanda de penaltis** y el bote se entrega al terminar. Migraciones `019`–`022` (§15.10) | ✅ **Construido** |
| **§15 — La tanda de penaltis SE JUEGA** | Portería de 4 huecos: si tirador y portero coinciden, parada; si no, gol. **La primera interacción simultánea del juego.** Migración `024` (§15.11) | ✅ **Construido** |
| **§15 — El penalti** | Familia propia, con el insignia del catálogo y su espejo defensivo. Migración `018` (§15.4c) | ✅ **Construido** |
| **§15 — Familia Árbitro** | Decisiones disciplinarias sobre el evento de tarjeta, con un 4.º dato oculto | ✅ **Construido** |
| **§15 — Minijuegos defensivos** | Defender ya no exige un gol: parada, despeje y córner en contra. Abre la familia `defensa` | ✅ **Construido** |
| **§15 — Veredicto y actuación** | Dato memorable por partido + puntuación (Biblia §1.5 r7, §4.6) | ✅ **Construido** |
| **§15 — Partido narrado en cadenas** | **Diseñado, decidido y medido; sin escribir.** El partido decide también en PvE, los minijuegos influyen en las recompensas DEL PARTIDO, el cofre mantiene su contenido fijo y da premio extra si todos los partidos previos están en **S en Extremo**. Plan de 5 piezas en §15.12 | 🟡 **Listo para construir** |
| **Escalado de dificultad de minijuegos** | Plazo y ritmo ya salen por dificultad; faltan las otras palancas (Biblia §3) | 🟡 Parcial |
| **§16 — Importador de datos oficiales** | `panel/importar.php`, importación por lotes con barra de progreso, borrado por expansión, migración `014` | ✅ **Construido (viene de `srf-franshu`)** |
| **Rediseño del componente de tarjeta** | Modo artwork, `mostrar_stats`, stats en modal | ❌ **Retirado de esta rama a propósito** — ver el aviso v7.2 y `srf-franshu-backup-20260807` |
| **Fase 3 — Pulido y escala** | Panel admin, motion unificado, doc de expansiones | ⬜ Pendiente |

> **El modelo de combate cambió en `93642b2`.** Cada línea ya no puntúa con una
> sola estadística: pondera las tres (`Tcg::PESOS_LINEA`). Eso movió el peso de
> Portería del ~8,9 % al ~15,45 % del total y obligó a recalibrar todos los
> porcentajes de compos (`009_recalibrar_compos.sql`). **Si vuelves a tocar
> `PESOS_LINEA`, hay que recalcular la tabla de §10.1 con el mismo método.**

**Regla de trabajo:** cada bloque se cierra por completo y deja el sitio
desplegable antes de abrir el siguiente. Al terminar, resumir qué cambió y por qué.

### Qué cubrió cada fase cerrada

**Fase 0** — No se tocó ninguna pantalla hasta tener el sistema debajo:
`tokens.css`, Geist autoalojada, `components.css` con los 16 componentes, el
componente de tarjeta con todos sus estados, y `styleguide.php`.

**Fase 1** — Migración pantalla a pantalla: `navbar` → `landing` →
`login`/`registro` → `sobres` → `coleccion` → `album` → `mercado` →
`perfil`/`configuracion`. CSS/JS antiguos retirados a `_legacy/`.

> **Nota de honestidad sobre la ceremonia de sobres:** la de la Fase 1 es
> **plana** (modal + reveal escalado por rareza). **No incluye** vitrina 3D,
> tilt al cursor, flip de reverso ni secuencia FUT: eso sigue en §14, sin
> empezar. El §4 describe el aspecto SRF como objetivo de marca, no como
> inventario de lo construido.

**Fase 2** — Ver §10 para el detalle de la Capa 2. El resto: base de datos
migrada de forma aditiva y repetible, constructor de mazos con formación real
sobre un campo, y duelos jugables de principio a fin (crear sala → esperar
rival con latido → aumento pre-partido → **compos** → resolución con curva de
probabilidad → **partido narrado en vivo con minijuegos (§15)** → resultado con
veredicto y desglose completo).

---

## 2. Arquitectura de ficheros

```
tcg_srf/
├── partials/
│   ├── head.php          ← abre el documento: fuentes, CSS, <body>, skip-link
│   ├── footer.php        ← pie + AVISO LEGAL + carga de ui.js
│   ├── ceremonia.php     ← modal de apertura de sobres (reveal de cartas)
│   └── confirmar.php     ← modal de confirmación compartido (SRF.confirmar)
├── components/
│   ├── carta.php         ← EL componente de tarjeta (render_carta, carta_html)
│   └── caja3d.php        ← cajas/sobres en pseudo-3D CSS (§14): pack3d_caja_html,
│                            pack3d_sobre_html. Compartido por sobres.php y
│                            panel/plantillas.php.
├── navbar.php            ← clúster "Jugar": Sobres / Mazos / Duelos
├── mazos.php             ← constructor de mazos + panel de COMPOS (§10.4)
├── duelos.php            ← lobby de duelos
├── duelo.php             ← sala en vivo + partido + resultado con compos
├── assets/
│   ├── css/
│   │   ├── tokens.css      variables + @font-face. Fuente de verdad.
│   │   ├── base.css        reset, tipografía, foco, layout de página
│   │   ├── components.css  los 16 componentes + ceremonia de sobres
│   │   ├── layout.css      nav, hero, filtros, pie, MAZOS, DUELOS, COMPOS
│   │   └── styleguide.css  solo para styleguide.php
│   ├── js/
│   │   ├── ui.js         modales, toasts, tabs, nav, plegables, SRF.confirmar,
│   │   │                 SRF.copiar (portapapeles, §15.6)
│   │   ├── ceremonia.js  SRF.ceremonia(cartas)
│   │   ├── sobres.js · mercado.js · album.js · coleccion.js
│   │   ├── perfil.js · configuracion.js
│   │   ├── mazos.js      asignación hueco→jugador
│   │   ├── duelos.js     lobby, tipo de apuesta, confirmación
│   │   ├── duelo.js      latido, sondeo, cuenta atrás, y LOS DOS MODOS de
│   │   │                 partido: narrado (PvP, sondea al servidor) y clásico
│   │   │                 (cadenas, reloj local). Ver §15.7.
│   │   ├── sobres.js     apertura de cajas/sobres 3D + tilt (§14, usa GSAP)
│   │   └── vendor/
│   │       └── gsap/gsap.min.js  ← único vendor real hoy. Three.js sigue sin usarse.
│   ├── fonts/            ← Geist autoalojada (4 .woff2)
│   ├── ajax/             ← canjear_codigo.php, monedas.php, duelo_estado.php,
│   │                       duelo_narracion.php (sondeo del partido, §15.3),
│   │                       duelo_minijuego.php (resuelve una jugada, §15.4)
│   └── img/
│       ├── Cromos/...    ← arte optimizado a WebP
│       └── plantillas/   ← creada al vuelo por subirPlantilla(); vacía si
│                            nadie ha subido arte de caja/sobre todavía
│       (_originales_sin_optimizar/ se borró el 2026-08-11: PNG sin optimizar
│        que no leía ningún fichero de código)
├── db/
│   ├── conexion.php      ← instancia $db (sin tocar)
│   ├── consultas.php     ← clase Tcg, ~4600 líneas. TODA la lógica vive aquí.
│   ├── plantillas_narracion.php  ← frases del relato por tipo de evento (§15.2).
│   │                               Es DATOS, no lógica: un array y nada más.
│   ├── minijuegos.php    ← catálogo de minijuegos (§15.4). Cada entrada declara
│   │                        su familia, su impacto y sus opciones. Añadir uno
│   │                        es añadir un array, no tocar el motor.
│   ├── verificar_minijuegos.php  ← comprueba las invariantes del catálogo
│   │                        (ciclo cerrado, sin opción dominante, determinismo,
│   │                        valor de la pista). Solo CLI. Pásalo al añadir uno.
│   ├── pruebas/          ← SUITES DEL PARTIDO. Solo CLI, y **nunca tocan la base
│   │   │                    real**: montan y borran `tcg_prueba` (§8). Han cazado
│   │   │                    cinco bugs que el razonamiento no cazó, tres con
│   │   │                    dinero de por medio.
│   │   ├── correr_todas.php   ← el lanzador. Un comando, cinco suites, código 1
│   │   │                        si algo falla. Es el que hay que ejecutar.
│   │   ├── probar_tope.php    ← el tope de goles que puede mover un jugador
│   │   ├── probar_tanda.php   ← la tanda jugable, incluido que la elección del
│   │   │                        rival NO viaje al cliente (§15.11)
│   │   ├── probar_paso3.php   ← el partido decide el duelo, de punta a punta
│   │   ├── probar_pve.php     ← que las cadenas siguen intactas
│   │   ├── probar_liquidar.php ← liquidación e idempotencia del bote
│   │   ├── probar_300.php     ← LA GRANDE: 300 duelos jugados enteros, 25 con
│   │   │                        carta. ~7 min, se lanza aparte y hay que montar
│   │   │                        `tcg_prueba` antes. Comprueba la contabilidad:
│   │   │                        que el total de monedas y de copias no cambie.
│   │   ├── probar_sin_migracion.php ← qué pasa sin la `019` (§8, trampa 1)
│   │   ├── probar_cascade.php  ← el CASCADE de `duelos.id_rival` (`023`)
│   │   └── tanda_rival_cpu.php ← herramienta, no prueba: hace de segundo jugador
│   │                             en la tanda para poder verla con un solo
│   │                             navegador. Apunta a la base REAL a propósito.
│   ├── migraciones/
│   │   ├── 002_duelos_misiones_mazos.sql   Fase 2
│   │   ├── 003_capa2_compos.sql            Capa 2
│   │   ├── 004_reparar_codificacion.php    utilidad (§5.3)
│   │   ├── 005 a 012                       Misiones, formaciones, PvE (§11, §11b)
│   │   ├── 013_plantillas_3d.sql           tabla plantillas_3d (§14)
│   │   ├── 014_importador_origen.sql       origen_importacion en cromos (§16)
│   │   ├── 016_partido_en_vivo.sql         reloj de partido + duelo_minijuegos (§15)
│   │   │   ↑ la 015 está libre: era la del rediseño de tarjeta retirado (v7.2)
│   │   ├── 017_minijuegos_sin_impacto.sql  tope de decisiones que no mueven
│   │   │                                   marcador (§15.5)
│   │   └── 018_penalti.sql                 frecuencia del penalti (§15.4c)
│   └── tcg.sql
├── branding/
│   ├── CLAUDE.md         ← este documento
│   ├── impacto-partido-analisis.md  ← análisis para decidir el bloque de 17
│   │                        entradas que espera esa decisión (§12 punto 7)
│   ├── Biblia/           ← 4 .md: la sesión de diseño de la que sale el §15
│   └── Rangos_estadisticas_SRF.xlsx/.csv  ← rangos para crear cartas (§15.8)
├── panel/                ← admin, TODAVÍA CON EL SISTEMA VIEJO salvo
│                            plantillas.php (Fase 3, ver §12)
│   ├── plantillas.php    ← sube/recorta/previsualiza el arte de cajas y
│   │                        sobres (§14), con el motor 3D real
│   └── importar.php      ← importador de datos oficiales (§16), con
│                            assets/ajax/importacion_{ejecutar,progreso}.php
├── _legacy/              ← CSS y JS retirados. Nadie los referencia.
└── styleguide.php        ← guía de estilo viva
```

### Esqueleto de una página

```php
<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';

// ... lógica; los POST se procesan aquí arriba y SIEMPRE acaban en
//     header('Location: ...'); exit;  — nunca se renderiza tras un POST.

$paginaTitulo = 'Misiones';
$paginaDesc   = 'Descripción para el <meta>.';
include __DIR__ . '/partials/head.php';

$activePage = 'misiones';
include __DIR__ . '/navbar.php';
?>

<header class="cabecera"> ... </header>
<main id="contenido" class="seccion wrap"> ... </main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
```

`head.php` acepta `$base` (`''` en la raíz, `'../'` en `panel/`) y `$cssExtra`.
Si una pantalla necesita sondeo periódico, el endpoint va en su propio fichero
dentro de `assets/ajax/`, nunca mezclado con el render.

---

## 3. El componente de tarjeta

Vive en `components/carta.php`. **Nunca se copia su marcado con variaciones.**
Si una pantalla necesita algo distinto, se añade una opción al componente.

```php
render_carta($cromo, $opts);   // imprime
carta_html($cromo, $opts);     // devuelve string (lo usa la ceremonia por AJAX)
render_rareza($idRareza, $nombreRareza);  // etiqueta suelta
```

Opciones: `tamano` (`sm`/`md`/`lg`), `href`, `poseida`, `protegida`, `precio`,
`seleccionada`, `cantidad` (insignia "×N"), `stats`, `acciones` (HTML flotante),
`pie` (HTML al final), `datos` (atributos `data-*`), `clase`, `lazy`.

### Tres reglas que garantiza

1. **El arte se muestra siempre completo.** `object-fit: contain`, nunca
   `cover`, y la imagen va **posicionada en absoluto** contra la placa: con
   `height:100%` a secas el porcentaje no resuelve (la altura viene de
   `aspect-ratio`) y un arte muy alto desbordaba y se recortaba. Ya se rompió
   una vez; no lo cambies sin volver a medirlo.
2. **La rareza lleva marca no cromática:** 0/1/2/3 chevrones en CSS, corona
   para legendaria, destello para SRF.
3. **Todo arte lleva texto alternativo.**

### El borde real de una carta

**No es un `border` CSS.** Es `padding + background`: `.carta` tiene
`padding: 1px; background: var(--rz-borde)` y `.carta-marco` pinta encima
dejando ver 1px. `--rz-borde` es: rareza 1 `var(--line-strong)`; 2-4
`color-mix(in srgb, var(--rzN) 34-42%, transparent)`; 5 un degradado metálico;
6 `var(--rz6-grad)` animado con `holoDeriva`.

**Nunca uses el tono puro de `--rzN` como borde sólido** — ya se cometió una vez
en el chip del mazo y se corrigió reutilizando esta técnica.

`.carta-marco` lleva `overflow: hidden` como red de seguridad: sin él, una
etiqueta de rareza larga ("Poco común") empujaba la insignia de cantidad fuera
de la tarjeta. Y `.rz` lleva `min-width: 0` con `.rz-texto` truncando por
elipsis, para que lo que se recorte sea el texto y **nunca** las marcas no
cromáticas, que son la señal de accesibilidad.

---

## 4. Sistema de diseño

### Color (`tokens.css`)

```
--void #0B0C10   --panel #16181D   --frost #EDEEF1   --frost-dim #93959F
--amber #E8752A  --amber-light #FFB168  --amber-ink #2B1204
--success #3DDC9B  --warning #F2B134  --danger #F0554A  --info #5B96F2
```

Los semánticos son literalmente los colores de las tarjetas arbitrales del
fútbol. Decisión deliberada, no un sistema genérico.

### Rarezas

| id | Nombre | Prob. | Marca no cromática |
|---|---|---|---|
| 1 | Común | 60 % | sin adorno |
| 2 | Poco común | 25 % | 1 chevrón |
| 3 | Raro | 10 % | 2 chevrones |
| 4 | Épico | 3,5 % | 3 chevrones |
| 5 | Legendario | 1 % | corona + borde metálico |
| 6 | SRF | 0,5 % | borde arcoíris animado + aura + barrido |

**Probabilidades auditadas y correctas**: 200.000 tiradas simuladas con el
algoritmo real dieron SRF al 0,481 % contra el 0,5 % esperado. Si en otra
máquina la SRF "sale mucho", lo primero a mirar es `SELECT * FROM rarezas;` ahí,
no el código.

La **SRF tiene que ganar visualmente a la legendaria sin discusión**. Sigue
siendo aspiración de §14 en cuanto a ceremonia, aunque su tratamiento en la
tarjeta (borde arcoíris + aura + barrido) ya está construido.

### Tipografía, espaciado, motion

- Geist Sans para UI. **Geist Mono solo para datos**: monedas, estadísticas,
  contadores, marcas de tiempo, fuerza de mazo, marcador de duelo.
- Espaciado `--space-1..8` = 4·8·12·16·24·32·48·64px.
- Radio 8 (controles) / 12 / 16 / 22px (carta y modales).
- Una sola curva `--ease`. `--t-micro` 160ms, `--t-media` 380ms,
  `--t-ceremonia` 700ms **solo para sobre y duelo**.
- Phosphor Icons por CDN pinneado a `@2.1.1`. **No dependas de un glifo para
  información crítica**: los chevrones de rareza y el check del selector van
  dibujados en CSS por eso.

---

## 5. Base de datos

### 5.1 Tablas

Originales: `usuarios`, `cromos`, `coleccion`, `mercado`, `sobre`,
`expansiones`, `equipos`, `rarezas`, `afinidad`, `codigos`, `codigos_canjeados`.

De la Fase 2 (`002`): `mazos`, `mazo_cartas`, `duelos`, `duelo_apuestas`,
`duelo_alineaciones`, `duelo_aumentos`, `configuracion`, `misiones`,
`misiones_progreso`, `minijuegos_partidas`.

> `minijuegos_partidas` es de la Fase 2 y **no la usa el sistema de minijuegos
> del §15**, que guarda en `duelo_minijuegos`. Sigue vacía y sin referencias en
> el código: no la confundas con la nueva ni escribas en ella.

De la Capa 2 (`003`): `rasgos`, `cromo_rasgos`, `duelo_compos`.

De Misiones/Formaciones/PvE (`005`-`012`): ver §11 y §11b.

De la ceremonia 3D (`013`): `plantillas_3d` — una fila por caja/sobre con
plantilla subida (§14).

Del partido en vivo (`016`, §15):
- `duelo_minijuegos` — una fila por jugada de minijuego resuelta. La clave
  primaria es `(id_duelo, id_evento, id_usuario)` y **es la defensa contra
  resolver dos veces la misma jugada**: sin ella, repetir la petición con la
  opción ganadora restaría un gol por envío.
- Columnas nuevas en `duelos`: `partido_inicio`, `partido_pausado_en`,
  `partido_pausa_seg` (el reloj) y `latido_creador` / `latido_rival` (presencia
  real de cada uno; el `ultimo_latido` de siempre solo servía para el creador
  esperando en la sala).

### 5.2 Cómo aplicar las migraciones

```
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root tcg < db/migraciones/002_duelos_misiones_mazos.sql
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root tcg < db/migraciones/003_capa2_compos.sql
C:\xampp\php\php.exe db/migraciones/004_reparar_codificacion.php --aplicar
```

Las dos SQL son aditivas y re-ejecutables. Después de `003`, ejecuta una vez:

```php
$db->derivarRasgosConfiguracion();   // rellena cromo_rasgos, debe devolver 38
```

**`013` puede faltar aunque `002`/`003` estén bien** — ya pasó (v6, 2026-08-07):
una copia con `rasgos`/`cromo_rasgos`/`cadena_nodos` correctos tenía
`plantillas_3d` inexistente. Aplícala igual que las anteriores:
```
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root tcg < db/migraciones/013_plantillas_3d.sql
```
Si la tabla falta, `Tcg::subirPlantilla()` (§14.1) **ya ha escrito los PNG
recortados en disco** antes de intentar el `INSERT` — el fallo es solo en la
última línea de la función. Síntoma: `assets/img/plantillas/{tipo}_{id}/`
tiene los recortes correctos pero la caja/sobre sigue mostrando el degradado
por defecto (`rutasPlantilla()` lee de la tabla, no del disco). Aplicar la
migración no revive esas filas solas: si esto pasa, hay que volver a subir la
plantilla desde `panel/plantillas.php` para que el `INSERT` se ejecute.

**`016` es obligatoria para que los duelos funcionen.** Sin ella no existe
`duelo_minijuegos` ni el reloj del partido, y el sondeo de §15.3 revienta:
```
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root tcg < db/migraciones/016_partido_en_vivo.sql
```
Es aditiva y re-ejecutable. **Ojo con sus valores de `configuracion`: entran con
`INSERT IGNORE`**, así que cambiar el valor por defecto dentro del `.sql` NO
toca una base ya migrada — hay que hacer el `UPDATE` a mano. Es a propósito:
si sobrescribiera, cada re-ejecución borraría el calibrado de Alejandro.

**`017` añade un parámetro de balance** (`partido_minijuegos_sin_impacto_max`,
§15.5). Mismo patrón `INSERT IGNORE`, así que la misma advertencia:
```
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root tcg < db/migraciones/017_minijuegos_sin_impacto.sql
```
No es obligatoria para que los duelos funcionen —el código lee 1 por defecto—,
pero sin ella el valor no se puede calibrar sin tocar código.

**`018` a `022` son las del partido que decide el duelo** (§15.10). De estas
**`019` y `021` SÍ son obligatorias**, y es la primera vez que hay migraciones que
no se pueden saltar:

| | qué hace | ¿obligatoria? |
|---|---|---|
| `018_penalti.sql` | parámetros del penalti como evento | no, hay valores por defecto |
| `019_partido_decide.sql` | **añade `en_juego` al enum `estado`** | **SÍ** — sin ella ningún duelo PvP se monta |
| `020_minijuego_prob_gol.sql` | `partido_minijuego_prob_gol` | no |
| `021_resuelto_por_tanda.sql` | **columna `resuelto_por_tanda`** | **SÍ** — la escribe `liquidarPartido()` en cada cierre |
| `022_presupuesto_marcador.sql` | presupuesto de marcador y plazo de abandono | no, pero sin ella no se calibran |
| `023_duelos_rival_cascade.sql` | `duelos.id_rival` de SET NULL a **CASCADE** | no para jugar, **sí antes de limpiar usuarios** |
| `024_tanda_jugable.sql` | tabla **`duelo_penaltis`** + `tanda_plazo_seg` | **SÍ** — sin ella ningún duelo empatado se puede cerrar (§15.11) |

**Sobre la `023`:** corrige una asimetría, no añade una pérdida. `id_creador` ya era
CASCADE, así que al borrar una cuenta los duelos que esa cuenta CREÓ ya
desaparecían del historial del rival, mientras que los duelos en los que fue RIVAL
sobrevivían apuntando a nadie. Ahora se van los dos. **Ojo con `ADD CONSTRAINT IF
NOT EXISTS`: no existe para claves ajenas en MariaDB 10.4** (error de sintaxis,
comprobado). La re-ejecutabilidad sale del `DROP FOREIGN KEY IF EXISTS` que va
delante.

**La diferencia entre obligatoria y opcional es de qué cambian**, y conviene
entenderla para clasificar bien las que vengan: las opcionales solo añaden filas a
`configuracion`, y el código las lee con `$this->config("clave", POR_DEFECTO)`, así
que sin la migración funciona igual y lo único que pierdes es poder calibrar el
número sin tocar código. Las obligatorias cambian **la forma de la tabla** —un
valor de enum, una columna—, y para eso no hay valor por defecto posible: el PHP no
puede inventarse una columna que no está.

> ⚠️ **`019` sin aplicar NO da un error limpio, y por eso hay una red en el
> código.** MariaDB aquí no es estricta: guardar un valor que el enum no conoce
> **trunca a cadena vacía con un simple warning** que PDO no convierte en
> excepción. `resolverDuelo()` relee el estado y deshace la transacción si no
> cuadra, devolviendo un error que dice qué migración falta. Sin esa red, el duelo
> quedaría cobrado a los dos y sin poder cerrarse nunca. Ver la primera trampa del
> §8.

```
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root tcg < db/migraciones/019_partido_decide.sql
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root tcg < db/migraciones/021_resuelto_por_tanda.sql
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root tcg < db/migraciones/022_presupuesto_marcador.sql
```

### 5.3 ⚠️ TRAMPA DE CODIFICACIÓN — léela antes de aplicar nada

**El flag `--default-character-set=utf8mb4` NO es opcional.** Sin él, en Windows
`mysql.exe < fichero.sql` lee el .sql con la codepage de consola (CP850) en vez
de UTF-8, y las tildes y eñes entran corrompidas: "Montaña" se guarda como
"Monta├▒a". **Pasó de verdad, dos veces**, con `002` y con `003`.

Cómo detectarlo (compara contra un dato preexistente correcto):
```sql
SELECT HEX(nombre) FROM rasgos WHERE clave='montana';   -- correcto: ...c3b1...
SELECT HEX(nombre) FROM afinidad WHERE id=1;            -- referencia sana
```
Si ves `e2949c` (carácter de dibujo de caja) en vez de `c3b1`, está corrupto.

Cómo arreglarlo: `db/migraciones/004_reparar_codificacion.php` revierte la
transformación exacta (UTF-8 → CP850 devuelve los bytes originales). Es
idempotente y hace dry-run si lo lanzas sin `--aplicar`. Ojo: `002` siembra con
`INSERT IGNORE`, así que **reaplicarla NO arregla el texto ya corrupto** — hay
que usar el reparador.

### 5.4 Parámetros de balance (`configuracion`)

Todo número de balance vive aquí, nunca como constante en el código. Se lee con
`$db->config($clave, $porDefecto)`.

| Clave | Valor | Qué es |
|---|---|---|
| `duelo_k` | 400 | K de la curva Elo |
| `duelo_p_min` / `duelo_p_max` | 0.05 / 0.95 | probabilidad acotada, nunca 0 ni 1 |
| `duelo_plazo_aumento` | 30 | segundos para elegir aumento |
| `duelo_latido_max` | 45 | segundos antes de dar una sala por abandonada |
| `line_cap` | 20 | tope % del bonus de compos sobre UNA línea |
| `compo_pesos_dr` | 1.0,0.7,0.45,0.25 | rendimientos decrecientes por línea |
| `ciclo_contra_afinidad_bonus` | 5.5 | % al total por ventaja de afinidad |
| `coherencia_umbral_libre` | 2.5 | rareza media sin exigencia de compos |
| `coherencia_malus_rate` | 3.0 | cuánta coherencia se exige por punto de rareza |
| `coherencia_malus_tope` | 18 | tope % del malus |
| `tension_tiers_0..3` | 60,30,10 … 43,36,21 | probabilidades Plata/Oro/Prisma |
| `partido_duracion_seg` | 45 | duración real del partido narrado, **sin contar las pausas** (§15.3) |
| `partido_espera_seg` | 15 | cuánto se espera a que aparezcan los dos antes de arrancar igual |
| `partido_latido_max` | 12 | segundos sin latido para dar a alguien por ausente |
| `partido_minijuegos_max` | 2 | decisiones por jugador y partido. **Cuidado al subirlo:** el reloj se para para los DOS en cada una, así que 3 son seis pausas y el partido se hace eterno (§15.5) |
| `partido_minijuegos_sin_impacto_max` | 1 | cuántas de esas decisiones pueden ser de impacto `"ninguno"` (árbitro y defensivas sin gol que mover). **No sube el total ni las pausas**, solo acota cuántas pueden ser irrelevantes para el marcador. Migración `017`; sin él, el 13,94 % de los jugadores gastaba las dos en decisiones que no cambian nada (§15.5) |
| `partido_minijuego_prob_gol` | 0.70 | probabilidad de que un ACIERTO acabe moviendo el marcador. Antes era siempre 1: leer bien la jugada equivalia a marcar. Fallar sigue sin castigar. Migracion `020` (§15.4f) |
| `partido_presupuesto_marcador` | 1 | goles que puede mover cada jugador con sus minijuegos en un partido. **Sustituyó a la §1.3 como límite** y ya no es una restricción de coherencia sino un tope de diseño: subirlo hace que pesen más los minijuegos y menos la fuerza del mazo, bajarlo a 0 los deja en pura actuación. Migración `022` (§15.10) |
| `partido_abandono_seg` | 3600 | tras esto, un partido `en_juego` que no arranca o que se quedó parado se cierra solo. **No es un adorno:** hasta que alguien liquide, el dinero de los dos está retenido. Holgado a propósito, para que quien llegue tarde pueda jugar su partido entero. Migración `022` (§15.10) |
| `tanda_plazo_seg` | 12 | segundos para elegir hueco en un penalti antes de que decida el sistema. Más largo que el plazo de un minijuego (9 s) a propósito: aquí no lees una jugada, intentas adivinar a una persona. Migración `024` (§15.11) |

---

## 6. Tono y voz — límite estricto

Editorial y serio, como una ficha oficial de competición. Sin argot gamer, sin
superlativos vacíos, sin bromear con nombres de jugadores reales.

El humor se limita a **exactamente dos guiños**: "Superruina Frontier" (solo
contextos secundarios, nunca en el lockup del logo) y "a ese Gonzalo le gano
fácil" (cita de folclore, en `partials/footer.php`). No inventes chistes nuevos.

> El `<h1>` de la portada lo edita Alejandro a mano. Respétalo.

---

## 7. Accesibilidad — no negociable (WCAG 2.2)

- Contraste ≥4.5:1 en texto normal, ≥3:1 en grande.
- Foco visible en todo elemento interactivo, nunca tapado.
- Objetivo táctil mínimo 24×24px, estándar interno 44×44px. En el mazo, el
  retrato puede ser menor si el **botón completo** llega a 44px.
- Todo operable por teclado. El deck builder nunca ha usado arrastre, solo
  "elegir hueco → elegir jugador", así que cumple SC 2.5.7 por diseño.
- `prefers-reduced-motion` cubre las ceremonias. **Regla que salió de un fallo
  real: reduce el MOVIMIENTO, nunca el JUEGO.** El partido de cadenas sí se
  salta con esta preferencia (allí es decoración: el marcador ya está decidido
  y no hay nada que decidir), pero el **partido PvP no se salta jamás** — se ve
  entero y se juega entero, solo sin animaciones. Durante un tiempo sí se
  saltaba, y eso significaba que quien tuviera la preferencia puesta no jugaba
  ninguno de sus minijuegos, nunca paraba un gol, y encima su rival esperaba
  15 s a alguien que no iba a aparecer: una desventaja competitiva atada a un
  ajuste de accesibilidad. Ver §15.7.
- `aria-live` para resultados de sobre y duelo. El reloj del aumento usa
  `role="timer" aria-live="off"` a propósito.
- Un solo `<h1>` por página. En `duelo.php` el veredicto ES el `<h1>`, con
  `tabindex="-1"` para que la simulación le pase el foco al terminar.

**Errores ya corregidos — no los reintroduzcas:**
- Texto blanco sobre el holográfico SRF caía a 1,9:1 → placa oscura debajo.
- Enlaces del pie a 20px de alto → `padding-block`.
- Hamburguesa comprimida a 20px por el flex → `flex-shrink: 0`.
- `duelo.php` resultado sin `<h1>` → el veredicto pasó a serlo.
- `.partido-nombre` y `.aumento-destape-lado` desbordaban con nombres largos
  sin espacios (permitidos hasta 50 caracteres) → truncan con elipsis.

---

## 8. Trampas conocidas

### ⚠️ La base de datos NO es estricta: guarda mal y no avisa

**Esta es la trampa más peligrosa del proyecto, porque falla en silencio y con
dinero dentro.** `sql_mode` aquí es
`NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION` — **sin
`STRICT_TRANS_TABLES`**. Consecuencia medida:

```sql
-- columna: ENUM('creado','resuelto')
UPDATE prueba SET estado = 'en_juego';
-- NO da error. Guarda '' (cadena vacía) y deja un Warning 1265,
-- "Data truncated for column 'estado'", que PDO NO convierte en excepción.
```

Así que **un valor de ENUM que no existe se traga sin protestar**. Y lo mismo
pasa con un `VARCHAR` demasiado corto (trunca), o un número fuera de rango (lo
acota). Solo las columnas que **no existen** dan error de verdad
(`Unknown column`), y esas sí deshacen la transacción solas.

Por qué importa tanto aquí: si alguien lleva el código a una copia sin la
migración `019`, `resolverDuelo()` dejaría un duelo **con lo apostado ya retenido
a los dos**, en un estado que no existe, que `liquidarPartido()` nunca podría
cerrar porque exige `en_juego`. **El bote no volvería a nadie y nada avisaría.**

**Cómo se protege, y cómo protegerlo si añades algo parecido:** después de
escribir un valor del que depende el flujo, **reléelo y compáralo**. Está hecho en
`resolverDuelo()` (§15.10) y devuelve un error que dice *qué migración falta*, con
prueba propia en una copia a la que se le quita el valor del enum a propósito.
**No basta con `try/catch`:** un warning no lanza excepción.

### Entorno

- **No hay Python real**: `python`/`python3`/`py` son el stub de Microsoft
  Store. No hay `pandoc`. No hay `node` (no puedes usar `node -c` para validar JS).
- **El navegador cachea CSS y JS con fuerza.** Ya no debería morder: todos los
  `<link>`/`<script>` del sitio pasan por `assetUrl()`/`assetScript()`
  (`partials/assets.php`), que les añade `?v=filemtime`. **Si añades un asset
  nuevo, úsalo también** — el fallo es muy engañoso, porque el PHP no se
  cachea nunca y por tanto el HTML nuevo llega bien pero contra un .css/.js
  viejo: parece "he cambiado el código y no pasa nada". Ya pasó con `ui.js`,
  que se cargaba desde `footer.php` sin versión.
- **El panel del navegador de pruebas no compone fotogramas si está en segundo
  plano**: `requestAnimationFrame` se pausa y `document.hidden` es `true`. Las
  animaciones no se pueden cronometrar ahí; hay que validar la lógica por
  partes (estado final, algoritmos aislados) y decir honestamente que el ritmo
  visual no se ha visto.
- PHP CLI en `C:\xampp\php\php.exe`. **`extension=gd` ya está activa** tanto en
  CLI (`php -m` la lista) como en el PHP que carga Apache (verificado v6,
  2026-08-07: `panel/plantillas.php` no da `Fatal error: Call to undefined
  function imagecreatetruecolor()`). Si en otra copia vuelve a faltar,
  descoméntala en `C:\xampp\php\php.ini` y **reinicia Apache a mano** (XAMPP
  Control Panel: Stop → Start, o `services.msc` → Apache2.4 → Reiniciar):
  corre como servicio de Windows y una sesión sin privilegios de administrador
  no puede reiniciarlo (`Restart-Service` da "Acceso denegado").
- Apache y MariaDB a veces están parados. Si XAMPP los deja a medias, mata los
  procesos `httpd`/`mysqld` y relanza desde `C:\xampp\`.
- **Si MariaDB no arranca y `mysql\data\mysql_error.log` dice `Aria engine: log
  data error` / `log initialization failed`**, el log de recuperación de Aria
  quedó corrupto (típico de un apagado no limpio de XAMPP, ya pasó el
  2026-08-07). Se arregla **moviendo** (no borrando)
  `mysql\data\aria_log.00000001` y `mysql\data\aria_log_control` fuera de
  `mysql\data\` — MariaDB los regenera solos al siguiente arranque. No toca
  `ibdata1` ni las tablas InnoDB, que son la mayoría de este proyecto.

- **El panel del navegador de pruebas no tiene el FOCO** (`document.hasFocus()`
  es `false`), además de no componer fotogramas. Eso rompe cosas que en un
  navegador de verdad funcionan: `navigator.clipboard` rechaza sin foco, y
  `document.execCommand('copy')` también. Si algo relacionado con el
  portapapeles "falla", comprueba `document.hasFocus()` antes de tocar el
  código — probablemente no está roto, es que no se puede probar desde aquí.
- **Para probar animaciones con GSAP se puede bombear el ticker a mano**:
  `gsap.ticker.tick()` en un bucle avanza las timelines aunque
  `requestAnimationFrame` esté pausado por tener el panel oculto. Es la única
  forma de verificar una secuencia animada en este arnés.
- **Para probar cambios que escriben en la BD, usa una copia desechable** en vez
  de tocar datos reales:
  ```
  mysqldump -u root tcg | mysql -u root tcg_prueba
  ```
  Se hizo así para probar `resolverDuelo()` y el antiabuso de los minijuegos.

### Verificar pantallas con sesión

Lo más cómodo es **iniciar sesión de verdad** con la cuenta `Claude` desde el
navegador de pruebas:
```js
await fetch('/tcg_srf/login.php', {method:'POST',
  body: new URLSearchParams({nombre:'Claude', password:'123456'})});
```
Para render en CLI sin navegador, inyecta la sesión:
```php
session_start(); $_SESSION['id_usuario'] = 9;
$sid = session_id(); session_write_close();
$_COOKIE[session_name()] = $sid;
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start(); include 'coleccion.php'; $html = ob_get_clean();
```
Ojo: si incluyes dos páginas en el mismo proceso saldrá un aviso de
`session_start()` duplicado — es artefacto del arnés, no un fallo de la página.

Usuarios: **id 9 `Claude`/`123456`** (la de pruebas, úsala), id 2 `LuluLulez`
(~200 cartas, mazo titular "a"), id 1 `FranDictador` (1 solo cromo jugador, no
puede alinear), id 8 `GonzaloEse`, id 7 `Prueba3` (sin cartas).

### Código

- **`bloqueada` en BD = "protegida de la venta"**, no "no la tienes". En el
  componente son dos opciones distintas: `protegida` y `poseida`.
- El mercado devuelve el nombre como `carta`, no `nombre`; `mercado.php` lo adapta.
- `listarColeccionUsuario()` devuelve todas las copias; `contarColeccionUsuario()`
  cuenta distintas. No es un bug.
- La ceremonia recibe la carta ya renderizada en servidor (`carta_html`). No la
  reimplementes en JS — sigue aplicando en §14.
- `rareza-clases.php` solo lo usa `panel/cromos.php`. Se retira en Fase 3.
- `panel/` es autónomo, con Bootstrap Icons y su propio `admin.css`. Fase 3.
- `body` usa `overflow-x: clip`, **no `hidden`** (rompería el `sticky` de la nav).
- **`Tcg::HUECOS`** es `["POR","DF","DF","DF","DF","MC","MC","MC","MC","DC","DC"]`
  y el índice ES el número de hueco. El CSS `.hueco:nth-child` depende de ese
  orden: si lo cambias, actualiza también el CSS.
- **`rowCount()` en MySQL cuenta filas modificadas, no coincidentes.** Ya mordió
  en `latirDuelo()`: dos latidos en el mismo segundo escriben el mismo `NOW()`
  y daba 0 con la sala viva. Se confirma con una lectura aparte.
- **Los navegadores estrangulan `setInterval` en pestañas de fondo.**
  `duelo.js` late también en `visibilitychange`, no solo por temporizador.
- **La alineación y las compos de un duelo se CONGELAN** al comprometerse.
  Editar el mazo o reasignar un rasgo después no cambia un duelo en curso —
  verificado con una prueba de "trampa".
- **No hay cron.** Todo lo que necesita "pasar el tiempo" (vencer el plazo de
  aumento, abandonar una sala, **avanzar el minuto del partido y vencer el
  plazo de un minijuego**) se evalúa de forma perezosa en cada carga o sondeo.
  **Misiones debe seguir el mismo patrón.**
- **Un `<button>` no puede contener otro `<button>`** — el navegador cierra el
  exterior de golpe al primero interior. Por eso las cajas abribles en
  `sobres.php` son `<div role="button" tabindex="0">`, no `<button>`: su
  interior lleva hasta 50 `<button>` de sobre individual (§14).
- **⚠️ Nada de `height` ni `aspect-ratio` fijos en un contenedor con texto de
  opciones dentro.** Las pistas de los minijuegos son frases enteras y las
  columnas miden un tercio del panel, así que el número de líneas cambia con el
  ancho de la pantalla. Ya mordió dos veces el mismo día, en las dos primitivas
  nuevas:
  - `.sim-mj-pista` (medidor) tenía `height: 62px` **más `overflow: hidden`** —el
    recorte hace falta para que la aguja no se salga— y cortaba la última línea
    de cada zona. Medido: 63 px necesarios de 60, ya en escritorio; en móvil la
    pista pasa a tres líneas y desaparece media frase. Lo reportó Alejandro
    jugando. Arreglado con `min-height`.
  - `.sim-mj-lienzo` (clic-en-zona) tenía `aspect-ratio`, que **calcula la altura
    a partir del ancho**, así que a 375 px las celdas pedían 105 px y tenían 84.
    Arreglado con `grid-template-rows: minmax(<mín>, auto)`, que conserva la forma
    en pantalla ancha y crece cuando el texto lo pide.
  - **Cómo comprobarlo sin jugar:** `elemento.scrollHeight > elemento.clientHeight`
    a 375 px y en escritorio. Mirarlo a ojo en escritorio no basta: ahí sobraba
    por 3 px y se veía casi bien.
- **⚠️ Una regla de `display` propia le gana SIEMPRE al atributo `hidden`.**
  `[hidden]` es una regla del navegador, y cualquier `display: flex/grid/block`
  que escribas tiene más prioridad, sin importar la especificidad. El síntoma
  engaña muchísimo: el JavaScript pone `elemento.hidden = true` correctamente,
  pero el elemento **se sigue viendo**, así que parece que el JS no se ejecuta.
  Ya ha mordido en **cinco** sitios (`.submenu-tipos`, `.cer-escena`,
  `.cer-walkout`, `.cer-aviso-motion`, `.ceremonia-mesa`). **Si escribes una
  regla de `display` sobre algo que el JS oculta con `hidden`, cuélgala de
  `:not([hidden])`.**
- **Nunca uses `mt_rand()` en código que se ejecute al pintar una pantalla.**
  El motor de eventos y los minijuegos usan un generador propio sembrado
  (`Tcg::azarSembrado`) por dos motivos: tocar el estado global del generador
  desde una lectura contaminaría el sorteo de cualquier duelo o sobre que se
  resolviera después en la misma petición, y además hace falta que el servidor
  pueda **recalcular** el mismo resultado para validar lo que manda el cliente.
- **⚠️ `azarSembrado()` es un generador, NO una función hash. No uses su primer
  valor para "hashear" una semilla.** Es la trampa que más se ha notado jugando
  de todo el §15, y el síntoma era claro: *"solo me sale el mismo minijuego"*.
  El patrón culpable era sembrar con `semilla * K + id_evento * c` y coger el
  primer valor. El primer valor de un LCG es **casi lineal** en su estado
  inicial, así que con el sorteo del duelo fijo y el id avanzando a pasos
  constantes, el valor avanzaba también a pasos constantes y el resultado caía
  en **ciclos cortos**: en el duelo 1859 los eventos 5 y 17 daban 0.073069 y
  0.074081 —semillas muy distintas, valor casi idéntico— y por tanto el mismo
  minijuego dos veces.
  - **La firma para reconocerlo:** con 5 candidatas, la colisión entre eventos
    consecutivos era del **0,0 %**. No "poca": imposible. Un reparto sano da
    1/n. Si mides una colisión de exactamente 0 %, tienes un patrón fijo, no
    azar.
  - **Ojo con la métrica equivocada**, que ya despistó una vez: contar *cuántos
    valores distintos* salen en un partido daba 4 de 4 y parecía sano. Lo que
    hay que medir es la **repetición**, no la variedad.
  - Lo correcto está en `Tcg::azarDeJugada()`: sembrar una vez con el sorteo del
    duelo y **avanzar el generador** hasta el turno de ese evento. Un LCG en
    secuencia sí está bien distribuido (medido: 25,1 / 20,1 / 33,4 % para 4 / 5 /
    3 candidatas). `generarEventosPartido()` no se tocó porque ya lo usaba bien:
    saca muchos valores seguidos de una sola siembra.

---

## 9. Convenciones

- **El briefing manda en diseño y marca. El código existente manda en
  convenciones técnicas.**
- Nombres de clases CSS y de funciones **en español**. Comentarios en español,
  explicando **por qué**, no qué.
- **Sin dependencias de npm ni build step.** GSAP es la única excepción
  autorizada y ya está vendorizada (`assets/js/vendor/gsap/`); Three.js sigue
  autorizado para §14 pero no se ha necesitado.
- JS sin framework, en IIFE, con `'use strict'`. API pública en `window.SRF`:
  `abrirModal`, `cerrarModal`, `toast`, `confirmar`, `ceremonia`, `copiar`.
- Escapar siempre con `htmlspecialchars()`. PDO preparado siempre.
- **Toda la capa de datos vive en la clase `Tcg`** (`db/consultas.php`, ~5480
  líneas), agrupada por comentarios de sección (`MAZOS`, `DUELOS`, `AUMENTO
  PRE-PARTIDO`, `CAPA 2 — COMPOS`, `MOTOR DE EVENTOS`, `MINIJUEGOS`, `PARTIDO
  EN VIVO`, `CONFIGURACIÓN`). No se crean clases nuevas.
  - **Excepción a "todo en Tcg": los DATOS puros van en su propio fichero**
    (`db/plantillas_narracion.php`, `db/minijuegos.php`). Son arrays que se
    editan a mano y crecen mucho; meterlos en la clase la haría ilegible sin
    aportar nada. La lógica que los consume sí vive en `Tcg`.
- **Patrón "sala en vivo" sin websockets**: latido periódico + sondeo +
  `navigator.sendBeacon` en `pagehide`. Ver `duelo.js` + `assets/ajax/duelo_estado.php`.
  **El partido en vivo del §15 usa exactamente el mismo patrón**, no otro.

### Decisiones ya tomadas — no volver a abrirlas

- La nav es `sticky`, no fija superpuesta.
- Nada de desplegables falsos en la navegación.
- La ceremonia recibe la carta renderizada en servidor.
- Geist va autoalojada, no por CDN.
- GSAP y Three.js autorizados **solo** para §14, vendorizados sin npm. GSAP ya
  está vendorizado y en uso (`assets/js/vendor/gsap/`); Three.js sigue
  autorizado pero no se ha necesitado.
- Los ficheros retirados se mueven a `_legacy/`, no se borran.
- El `<h1>` de la portada lo controla Alejandro.
- Confirmación explícita en modal propio para toda acción con consecuencia
  económica. Nunca `confirm()` del navegador.
- **Se duela siempre con el mazo titular**, nunca eligiendo mazo por partida.
- **Un mismo cromo no puede repetirse en una alineación**, aunque tengas copias.
- **Cualquier carta puede ir en cualquier hueco.** No hay reglas de posición;
  lo que cambia es con qué estadística puntúa. Decisión explícita de Alejandro
  ("poder quien quieras donde quieras"). Cualquier bloqueo de posición sería
  revertirla.
- **En la apuesta de carta, la carta concreta la elige quien puja**, no el sistema.
- **El fallback del aumento es aleatorio entre las 3 opciones**, no la más baja
  (que era la especificación original). Desviación documentada, decidida por
  Alejandro para no castigar tan duro a quien no llega a tiempo.
- **Los aumentos de ambos se destapan a la vez, solo con el duelo ya resuelto.**
  Regla anti-abuso: verlos antes daría ventaja a quien elige segundo.
- **Tensión no da fuerza**, mejora las probabilidades de tier del Aumento (§10.2).

---

## 10. Capa 2 — Compos (CONSTRUIDA)

Implementa `branding/Superliga_Frontier_TCG_Sistema_Compos_Balance.md`, §3 a §6.

### 10.1 Modelo

Un **rasgo** es una etiqueta que, con suficientes copias en el once, activa un
bonus sobre una o dos líneas. **Umbrales 2 / 5 / 11 copias** para todos.

| Rasgo | Tipo | Línea(s) | N1 | N2 | N3 |
|---|---|---|---|---|---|
| Fuego | afinidad | Ataque | 2,70 % | 6,30 % | 12,59 % |
| Bosque | afinidad | Medio | 1,70 % | 3,97 % | 7,94 % |
| Viento | afinidad | Defensa | 2,23 % | 5,19 % | 10,36 % |
| Montaña | afinidad | Portería | 3,88 % | 9,06 % | 18,13 % |
| Contraataque | configuración | Ataque | 2,70 % | 6,30 % | 12,59 % |
| Vínculo | configuración | Medio | 1,70 % | 3,97 % | 7,94 % |
| Justicia | configuración | Ataque + Defensa | 0,82 % | 1,83 % | 3,65 % |
| Brecha | configuración | Ataque + Portería | 1,06 % | 2,39 % | 4,77 % |
| Tensión | derivado | — (mejora el Aumento) | 3 rasgos | 5 rasgos | 7 rasgos |

Los porcentajes **no son uniformes a propósito**: con el peso ponderado por
línea (`Tcg::PESOS_LINEA`, ver §Formaciones/PvE), Portería pesa ~15,45 % del
total y Medio ~35,27 %, así que Montaña sigue necesitando un % mayor para
tener el mismo impacto real, solo que ya no tan extremo como antes. Están
calibrados para equivaler, no para parecer iguales: los seis rasgos de una
sola línea maximizados dan exactamente 2,80 % de la fuerza total cada uno;
Justicia y Brecha (dos líneas) dan 1,80 % cada uno.

**Recalibrados en `009_recalibrar_compos.sql`** cuando el deck builder pasó de
puntuar cada línea con una sola estadística a las tres ponderadas (decisión de
Alejandro sobre el cálculo de fuerza). Antes del cambio, Portería pesaba solo
~8,9 % del total y Medio ~37,4 %; con la fórmula nueva Portería casi se
duplica, así que el +31,5 % original de Montaña habría pasado a valer más del
doble que un Vínculo maximizado (una inversión real, no solo un desajuste
cosmético). Si `Tcg::PESOS_LINEA` vuelve a cambiar, esta tabla hay que
recalcularla otra vez con el mismo método: reparto real por línea (catálogo
completo, formación 1-4-4-2, cartas en su posición natural) → factor de
reescalado por línea → aplicar a cada % existente.

**La afinidad NO se duplica en `cromo_rasgos`**: vive solo en
`cromos.id_afinidad`, para que no haya dos fuentes de la misma verdad. Solo los
rasgos de configuración se guardan en `cromo_rasgos`.

### 10.2 Cómo se deriva el rasgo de configuración

Alejandro eligió **derivación automática** en vez de curación a mano.

**Se descartó derivar de las estadísticas** (`ataque`/`defensa`/`tecnica`), que
es lo primero que uno intentaría: esas columnas se sembraron con una fórmula de
base-por-rareza + ajuste-por-posición, así que **no contienen información
independiente** (hay cartas con estadísticas idénticas: Vozinha y Strem Goozer,
40/74/67). Derivar de ahí daría un rasgo que es un calco de la posición y —lo
grave— **correlacionado con la rareza**, lo cual anularía el malus de coherencia,
cuyo propósito exacto es que la rareza alta no venga con compos gratis.

**Regla usada:** `rasgo = (línea_del_puesto − línea_de_la_afinidad) mod 4`, con
POR/DF/MC/DC = 0..3 y Montaña/Viento/Bosque/Fuego = 0..3, mapeando
`0→Contraataque, 1→Justicia, 2→Vínculo, 3→Brecha`. Como ambas se mueven en la
misma escala de 4 líneas, sale un cuadrado latino: cada rasgo cae en las 4
posiciones **y** en las 4 afinidades.

Verificado sobre las 38 cartas: reparto **Vínculo 12, Contraataque 10, Justicia
8, Brecha 8**; rareza media por rasgo 2,38–3,33 frente al 2,84 del catálogo, es
decir **sin correlación con la rareza**.

`Tcg::derivarRasgosConfiguracion()` la aplica. **Nunca pisa una fila con
`manual = 1`**, así que curar a mano desde el panel (Fase 3) será compatible sin
rediseñar nada. Se dispara sola al crear o editar un cromo desde
`panel/cromos.php`.

### 10.3 Motor

Todo en `db/consultas.php`, sección `CAPA 2 — COMPOS`:

- `calcularCompos(array $cartas)` — el núcleo. Devuelve `activos`,
  `bonos_linea` (ya con rendimientos decrecientes y tope), `tension_nivel`,
  `afinidad_dom`, `compo_index`, `rareza_index`, `malus`.
- `bonoCicloAfinidad($mio, $suyo)` — ciclo canon **Fuego>Bosque>Viento>Montaña>Fuego**
  (Fūrinkazan, no se toca nunca). Neutro ni gana ni pierde.
- `congelarCompos()` / `listarComposDuelo()` — congelado por duelo.
- `probabilidadesTier($tensionNivel)` — tabla de Tensión → Aumento.
- `derivarRasgosConfiguracion()` — §10.2.

**Orden de aplicación en la fórmula maestra** (importante, no lo cambies):
1. Bonos de **categoría** por línea = compos (con rendimientos decrecientes
   `[1.0, 0.7, 0.45, 0.25]` y tope de línea 20 %) **+** Aumento.
   El tope acota **solo las compos**; el Aumento se suma por encima.
2. Se suman las líneas ya ajustadas.
3. Bonos de **total** sobre esa suma: ciclo de contra-afinidad (+) y malus de
   coherencia (−). Nunca encadenados sobre un valor ya multiplicado.

**Dónde se engancha:** `aceptarDuelo()` congela compos y genera los Aumentos con
el nivel de Tensión de cada jugador; `resolverDuelo()` recalcula desde la
alineación congelada y aplica todo.

### 10.4 Interfaz

- `mazos.php` — panel "Compos activas" con rasgos, nivel en puntos
  llenos/vacíos, afinidad dominante, Tensión, rareza media y malus. Se
  recalcula al guardar, **no en vivo con JS**: el cálculo vive en servidor y
  duplicarlo en JavaScript sería tener dos fuentes de la misma verdad.
- `duelo.php` — dos paneles enfrentados en el resultado, leyendo las compos
  **congeladas**, más el ciclo y el malus de cada uno.

### 10.5 Verificación hecha

| Comprobación | Documento | Implementación |
|---|---|---|
| Fuego vence a Bosque (ciclo 5,5 %) | 57,78 % | **57,75 %** |
| 4 rasgos apilados en Ataque, bruto (Fuego+Contraataque+Brecha+Justicia, N3) | «supera el 30 %» | 33,60 % |
| …con rendimientos decrecientes | — | 24,46 % |
| …con tope de línea | 20 % | **20,00 %** |
| Probabilidades de tier por Tensión | 60/30/10 → 43/36/21 | idénticas |

> Remedido tras `009_recalibrar_compos.sql` (antes: bruto 37,44 % / con
> decrecientes 27,33 %). El tope de línea sigue absorbiendo la diferencia en
> ambos casos, así que el comportamiento final no cambia.

### 10.6 Tres hallazgos abiertos (decisiones para Alejandro)

1. **El malus de coherencia muerde menos de lo que dice el §6.3.** Su escenario
   de "rareza alta con 0 compos" **no existe con solo 4 afinidades**: por
   principio del palomar, un once de 11 siempre repite un elemento 3 veces y
   activa Nivel 1. En una prueba el equipo "desordenado" acabó con más
   `compo_index` (4) que el monotype (3). No es un fallo de implementación —la
   fórmula está literal— sino que su simulación asumía un espacio de rasgos
   mayor. Se corregirá solo según crezca el catálogo.
2. **§3.3 y §3.5 se contradicen para Montaña.** Su Nivel 3 son +31,5 %, pero el
   tope de línea es 20 %: cuando el catálogo permita un monotype Montaña puro,
   el tope lo recortará a 20 %, deshaciendo la calibración que hacía a Montaña
   equivalente. Hoy da igual (solo hay 9 cartas Montaña, hacen falta 11). Es el
   único rasgo que toca el tope.
3. **Nivel 3 casi inalcanzable.** Requiere 11 copias distintas del mismo rasgo.
   Disponibles hoy: Viento 10, Bosque 10, Montaña 9, Fuego 9, Vínculo 12,
   Contraataque 10, Justicia 8, Brecha 8. **Solo Vínculo llega.** Los umbrales
   son absolutos por diseño (§16 del documento) para que el catálogo crezca sin
   retocar parámetros; es techo aspiracional, no un error.

### 10.7 Qué del documento de balance sigue sin construir

Ya construidos después: PvE Cadenas de Partido (§7 del balance) y formaciones
alternativas (§8). **Siguen sin construir:** **anti-tilt de sesión** (§9),
**matchmaking anti-repetición PvP** (§10), **validador de balance del panel**
(§12) y el **pity del Aumento** (§5.3). Ninguno bloquea nada.

---

## 11. Misiones (CONSTRUIDO)

`misiones.php` + sección `MISIONES` en `db/consultas.php`
(`listarMisionesConProgreso`, `reclamarMision`). Enlace en el clúster "Jugar".

**El progreso NO se guarda en un contador**: `misiones_progreso` solo registra
el reclamo (`fecha_reclamada`), y el avance se **deriva** en cada carga de
consultas que ya existían — `contarColeccionUsuario()`,
`contarExpansionesCompletas()`, `listarMazosUsuario()`, y conteos sobre
`duelos`. Es deliberado: un contador duplicado se desincroniza.

Reclamo **manual** (el jugador pulsa "Reclamar"), **una sola vez por misión**,
en transacción que revalida el objetivo antes de pagar. Las 8 misiones
sembradas cubren colección (10/25/40 distintas, 100 copias), mazos, duelos
jugados, duelos ganados y expansiones completas.

`005_ciclo_misiones.sql` añadió el soporte de ciclo; si hicieran falta
misiones diarias o semanales, ese es el sitio.

---

## 11b. Cadenas de Partido — PvE (CONSTRUIDO)

Implementa §7 del documento de balance. `cadenas.php` (listado) y `cadena.php`
(mapa + partido), con ~1.200 líneas nuevas en `db/consultas.php`.

**Migraciones:** `007_pve_motor.sql`, `008_dificultad_pesos.sql`,
`010_cadenas.sql`, `011_recompensas.sql`, `012_contenido_real.sql`.

**13 tablas** `cadena_*`: `cadenas`, `cadena_nodos`, `cadena_aristas`,
`cadena_requisitos`, `cadena_rivales`, `cadena_rival_estilos`,
`cadena_rival_cartas`, `cadena_progreso`, `cadena_loot`, `cadena_drops`,
`cadena_cofres`, `cadena_numeracion`.

**Cómo funciona:**
- Mapa de **nodos no lineal** con aristas: hay rutas alternativas ("Vía alta" /
  "Vía baja") que confluyen. Nodos de tipo **partido** y de tipo **cofre**.
- **5 dificultades** (`Tcg::DIFICULTADES`): facil, medio, dificil, muy_dificil,
  extremo. Elegibles partido a partido. Cada una tiene multiplicador de fuerza,
  sesgo de tier del Aumento del rival y tope de rareza, **todo en
  `configuracion`** (`pve_mult_*`, `pve_tiers_*`, `pve_rareza_max_*`).
- **Rangos S / A / B** por resultado (`rangoPartido()`), que multiplican la
  recompensa (`pve_recompensa_mult_rango_*`).
- **Recompensa decreciente por repetición**, solo en fácil y medio
  (`DIFICULTADES_CON_DECRECIMIENTO`), con suelo y tasa configurables.
- **Cadenas encadenadas por requisitos**: "Descenso de Frontier" exige haber
  cerrado "Ruta de ascenso".
- Los cofres finales **desbloquean formaciones** (`formaciones_usuario`).
- El rival es el usuario **`CPU` (id 10)**, `Tcg::USUARIO_BOT`. Usa el mismo
  motor de resolución que el PvP (alineación + compos + aumento + curva Elo);
  lo que cambia son sus multiplicadores. En PvE **sí puede tener combinaciones
  de compos imposibles para un jugador real**: es intencionado, es la
  herramienta para crear dificultad sin salir del sistema base.

**Contenido sembrado hoy:** 2 cadenas, 18 nodos, 6 rivales, 12 estilos,
132 cartas de rival.

**Formaciones** (`Tcg::FORMACIONES`, `006_formaciones.sql`): 442, 433, 352,
532, 451, 343, 541, 361. Todas reparten los mismos 11 huecos de otra manera —
elegir formación es una decisión táctica sobre a qué compos apostar, nunca una
ventaja de poder.
## 12. Pendientes, en orden aproximado

1. ~~Subir arte real de cajas/sobres del Base Set~~ — **hecho el 2026-08-07**:
   `caja_expansion_3`, `caja_sobre_2` y `sobre_2` (expansión id 3 "Base Set -
   T2", sobre id 2 "Sobre Básico") ya tienen fila en `plantillas_3d` — se
   llamó a `Tcg::subirPlantilla()` por CLI reutilizando el `original.png` que
   ya estaba en `assets/img/plantillas/{tipo}_{id}/` (el mismo flujo que hace
   `panel/plantillas.php`, sin pasar por el navegador). `sobres.php` ya sirve
   las texturas reales (`front.png`, `side.png`, `top.png`, `lid.png`,
   `interior.png`, `frente.png`, `reverso.png`, todo 200) en vez del
   degradado por defecto. **Sigue pendiente el resto del catálogo**: los
   otros dos sobres del Base Set (`id_sobre` 1 "Sobre Doble" y 3 "sobre
   prueba") y cualquier expansión futura no tienen plantilla — mismo proceso,
   arte nuevo primero.
2. **Fase 3** — panel de administración al sistema nuevo (hoy sigue con
   Bootstrap Icons y su propio `admin.css`, salvo `plantillas.php` que ya usa
   el sistema nuevo), motion unificado de las ceremonias, y documentar cómo
   añadir una expansión de temporada.
3. **Panel para curar rasgos a mano** (§10.2). La tabla ya soporta `manual = 1`
   y la derivación nunca lo pisa; falta solo la UI. Va con la Fase 3.
4. **Lo que queda del documento de balance** (§10.7): anti-tilt de sesión,
   pity del Aumento, matchmaking anti-repetición PvP, validador de balance.
5. **Más contenido de Cadenas**: hoy hay 2 cadenas y 18 nodos. El motor
   aguanta más sin tocar código; es trabajo de datos.
6. **`_legacy/` se puede borrar** cuando Alejandro confirme.

**Pendientes que nacieron con el §15:**

7. **Más minijuegos — pero ya no es trabajo de catálogo.** **65 de las 100
   entradas con mecánica propia de la Biblia**, construidas con **75 entradas de
   catálogo** (las otras 10 son variantes propias sin nombre en la Biblia: Mano
   Cambiada, Lectura de Cadera, El Último Palmo, El Regate Previo, Desde la
   Frontal, A Primer Toque, La Barrera, La Pizarra, El Córner, Segunda Jugada).
   No confundas los dos números. Y
   **los 11 huecos que el motor puede producir están cubiertos** (§15.4). Añadir
   variantes a los que ya existen sigue siendo barato (escribir un array y pasar
   el verificador), pero lo que queda de la Biblia **no está bloqueado por el
   catálogo sino por sistemas que no existen**, y escribirlas como arrays no las
   haría jugables:
   - ~~Dos primitivas sin construir~~ — **las cuatro están hechas** (§15.4b).
     Ya no queda nada bloqueado por falta de primitiva.
   - ~~El penalti~~ — **hecho** (§15.4c), con El Momento de la Verdad y Leer la
     Mente. Quedan como eventos sin emitir el **saque de banda**, el **saque de
     puerta** y el **centro alto al área**, que valen 3 entradas de poco peso
     (El Misil de Banda, El Centro Cargado, ¿Corto o al Bombardero?): son
     jugadas de relleno y cada tipo nuevo diluye el relato, así que no compensan
     salvo que Alejandro las quiera.
   - ~~Tres que cabían sin nada nuevo~~ — **hechas**: El Pase de la Prudencia, El
     Capricho del Árbitro y La Mano que Nadie Vio, que **solo cabía desde que
     existe el penalti**: es el VAR invertido sobre una pena máxima en contra, y
     un acierto ahí anula el gol, que es literalmente lo que la Biblia le pide a
     Ojo de Halcón.
   - **Supertécnicas como datos**: no existen. Bloquea Golpe de Autor y El Combo
     Prohibido. *(El Momento de la Verdad y Leer la Mente ya están construidos
     sin ellas — ver las desviaciones documentadas en sus entradas.)*
   - **Sin banquillo, sustituciones, lesiones ni cansancio**: bloquea Emergencia
     en la Enfermería, La Revolución del Banquillo, Exprimir al Límite, Cazar al
     Cansado, El Novato Congelado…
   - ~~Sin prórroga ni tanda de penaltis~~ — **la tanda existe y SE JUEGA**
     (§15.11): portería de cuatro huecos, coincidís y es parada. Lo que queda de
     ese bloque de la Biblia es **decorarla**, no construirla: El Orden del Destino
     (elegir el orden de los lanzadores) y Guerra Psicológica (intentar leer al
     rival) piden datos de plantilla que hoy no existen; El Gol que lo Cambia Todo
     ya está, de hecho, en cada tiro. **Tiempo Extra** (prórroga antes de la tanda)
     sigue sin construir y es lo único de ahí que necesitaría motor nuevo.
   - ~~`impacto: "partido"` sigue sin decidir~~ — **decidido y construido**
     (§15.4d): 13 de las 17 entradas están dentro. Las 4 que no caben, y por qué,
     al final de este apartado.
     **Hay análisis escrito: `branding/impacto-partido-analisis.md`**, con el
     inventario de qué se rompe (con `fichero:línea`), cuatro caminos y una
     recomendación. Dos cosas de ahí que conviene saber sin abrirlo, **las dos ya
     resueltas en §15.10**:
     - **`valor_sorteo` hacía dos trabajos**: decidir el ganador y sembrar la
       narración. Ya solo hace el segundo — en PvP el ganador lo decide el
       marcador—, así que **no hubo que separarlo en dos números** y la garantía
       de que relato y resultado no se contradigan sigue saliendo gratis.
     - **La parte difícil no era código, era diseño**: si el partido decide,
       abandonar pasa a ser una jugada. Se resolvió sin inventar ninguna regla,
       porque **lo apostado ya estaba retenido de los dos** desde que entraron:
       irse no devuelve nada, y las dos ramas de abandono de
       `cerrarPartidoSiToca()` garantizan que el bote acabe entregándose.
     - **DECIDIDO Y CONSTRUIDO** (§15.4d): se acotó `partido` al presupuesto y al
       ritmo, nunca al ganador. Van **13 entradas**. Las **4 que no caben** y por
       qué, para no volver a intentarlo:
       - *El Baile Provocador* y *La Fiesta Peligrosa* — sus dos ramas son "neutra
         o peor", y `resolverMinijuego()` no castiga elegir mal a propósito. Toda
         la familia de Decisiones Negativas choca con esa regla.
       - *El Golpe de Timón* — cambiar de formación exige recalcular la fuerza a
         mitad de partido.
       - *Salir a Matar o Caminar* — necesita cansancio, que no existe.
   - **Decisiones fuera del partido** (pre-partido o entre nodos de cadena):
     El Informe Secreto, ¿Arriesgo o Protejo?, La Mirada Desafiante…
   - **Un hueco más de partido**: dar `familia_def` al evento de `falta`
     abriría defender un balón parado, donde encaja El Muro de Piedra. Es
     decisión de diseño, no ampliación de catálogo.
8. **El desequilibrio de compos** que §15.8 deja medido: hoy mezclar afinidades
   rinde más que enfocarlas, así que construir bien un equipo casi no importa.
   Es lo que más afecta a la sensación de juego de todo lo pendiente. Decisión
   de balance, no de código.
9. **Llevar el partido narrado a las cadenas** — **es lo siguiente que toca, y ya
   está todo decidido: §15.12.** Cinco piezas, con las cuatro decisiones de
   Alejandro dentro (el partido decide en PvE, los minijuegos influyen en las
   recompensas del partido, el cofre mantiene contenido fijo, y premio extra si
   todos los partidos previos están en **S en Extremo**). Se jubila
   `marcadorCadena()`, que da 5,65 goles por partido y no se puede narrar. **Ojo:
   el rango S quedará inalcanzable hasta que Alejandro calibre, y eso es esperado,
   no un fallo** — el bonus del cofre también.
10. **El resto del escalado de dificultad** (Biblia §3). Hoy salen por
    dificultad el plazo y el ritmo de aparición; faltan el tamaño de la zona de
    acierto, la fiabilidad de la pista y el coste del fallo.
11. **Calibrar `duelo_k`** con duelos reales — §15.8 sugiere que es la palanca
    real del equilibrio, no los rangos de estadísticas.
12. **Resolver los hallazgos abiertos de §10.6** (relacionado con el 8).

**Ya no están pendientes** (estaban en la v6):
- ~~Algoritmo definitivo del marcador PvP~~ — hecho: nace de la simulación
  (§15.1) y `marcadorDuelo()` se retiró.
- ~~Minijuegos aplazados~~ — empezados (§15.4).

---

## 13. Comprobaciones antes de dar algo por terminado

```bash
for f in *.php partials/*.php components/*.php db/*.php db/pruebas/*.php assets/ajax/*.php panel/*.php; do
  C:/xampp/php/php.exe -l "$f"
done
```

**Y si has tocado el partido, los duelos o los minijuegos, esto no es opcional:**

```
C:\xampp\php\php.exe db/pruebas/correr_todas.php
C:\xampp\php\php.exe db/verificar_minijuegos.php
```

`correr_todas.php` monta y borra `tcg_prueba` él solo, **nunca toca la base real**
(§8), y sale con código 1 si algo falla. Las cinco suites que lanza y qué cubre
cada una están en su cabecera. La grande, `db/pruebas/probar_300.php` (300 duelos
de punta a punta, ~7 min, hay que montar `tcg_prueba` antes), se lanza aparte
cuando el cambio toca el motor.

> **Estas suites han cazado cinco bugs que el razonamiento no cazó**, tres de
> ellos con dinero de por medio. No las trates como decoración: si añades una fase
> al partido, **añádela también al guion**, o los duelos que pasen por ella
> aparecerán como colgados y parecerá un fallo del motor (pasó tres veces).

En navegador, a 375×812 y en escritorio:

- Sin scroll horizontal de página (`document.documentElement.scrollWidth`).
- Tabulador: foco siempre visible y nunca tapado.
- Modales: se abren, atrapan el foco, cierran con Esc, devuelven el foco.
- Con "reducir movimiento": sin volteos ni destellos, y la SRF sigue reconocible.
- Objetivos táctiles ≥24×24 (44×44 en acciones primarias).
- Un solo `<h1>`, `main#contenido`, `.skip-link` y aviso legal en cada página.
- **Prueba con nombres largos**: un nombre de invocador de 50 caracteres sin
  espacios ya rompió el marcador y el destape de aumentos. Es un caso real.

Si tocas Duelos, además:
- Crear sala, dejarla más de `duelo_latido_max` segundos, comprobar que se
  cancela y devuelve lo apostado.
- Comprobar en `duelo_aumentos` que las opciones de un jugador **nunca**
  aparecen en la respuesta que recibe el otro.
- Comprobar que editar el mazo tras comprometerse no cambia el resultado.
- **Abrir el mismo duelo desde las DOS cuentas y comprobar que ven el mismo
  marcador.** Ya falló una vez (4-2 contra 4-3, ver §15.7) y no se detecta
  jugando con una sola cuenta.
- Si tocas el partido narrado, la lista completa está en **§15.9**.

Si tocas la Capa 2, además:
- `SELECT HEX(nombre) FROM rasgos WHERE clave='montana';` → debe contener `c3b1`.
- Reejecutar `derivarRasgosConfiguracion()` no debe cambiar el reparto
  (12/10/8/8) ni pisar filas con `manual = 1`.
- El ciclo Fuego→Bosque debe seguir dando ~57,8 % con onces equivalentes.

---

## 14. Cajas y sobres en pseudo-3D (CONSTRUIDO, distinto del plan original)

**Estado: construido y funcionando.** Hay un primer intento de arte real para
`caja_expansion_3`, `caja_sobre_2` y `sobre_2` (recortes ya en disco), pero
sigue cayendo al degradado por defecto porque la fila correspondiente en
`plantillas_3d` no se llegó a grabar — ver la nota de v6 al principio del
documento y §5.2/§12. El resto de expansiones/sobres sigue sin arte subido.
Especificado en un prompt aparte
(`prompt-claude-code-sobres-3d.md` — **no vive en el repo**, solo se cita en
comentarios de código; si lo necesitas entero, pídeselo a Alejandro) que
describe un sistema **más simple** que el plan que tenía este documento en su
v4: nada de vitrina con scroll horizontal, nada de reveal por aura anticipatoria
ni de secuencia FUT independiente. Lo construido es:

- Cada expansión se pinta como una **caja 3D** (`components/caja3d.php`,
  función `pack3d_caja_html()` — nombres "pack3d", no "caja3d": el componente
  se reescribió por completo en un rediseño posterior, ver nota al final de
  este apartado) hecha enteramente con **CSS falso 3D**: `perspective` +
  `transform-style: preserve-3d` + `rotate`/`translateZ` sobre capas planas.
  **Nunca WebGL ni modelos reales** — Three.js sigue sin vendorizar y sin usar.
- Las texturas de cada cara (`front`, `top`, `side`, `lid`, `interior`) salen
  de `Tcg::rutasPlantilla($tipo, $id)`; si el admin no subió plantilla, las
  claves faltan en el array y el CSS cae solo al degradado por defecto — el
  render **nunca se rompe** por falta de assets.
- Clicar una caja la abre en un "portal" (`.pack3d-portal`): la tapa gira más
  allá de 90° (`rotateX` hasta ~-170) desvaneciéndose a la vez, dejando ver el
  interior con hasta `Tcg::SOBRES_POR_CAJA` (50) sobres individuales
  (`pack3d_sobre_html()`), dentro del **mismo árbol `preserve-3d`** que la
  caja — nunca en un contenedor hermano, o se pierde la percepción de "sacar
  el sobre de dentro".
- Si una expansión tiene varios tipos de sobre, la caja grande abre a un
  **submenú** de cajas pequeñas por tipo (`sobres.php`, sección "Fase 2" en el
  propio fichero) en vez de meter todos los sobres en la caja de expansión.
- Clicar un sobre individual dispara la compra + apertura real
  (`abrirSobre()`) y reutiliza el modal/reveal de `partials/ceremonia.php` +
  `assets/js/ceremonia.js`. §14 solo cambia cómo se llega hasta ahí, no lo que
  pasa al abrir un sobre. (La ceremonia SÍ se reescribió después — ver §14.2 —,
  pero por su cuenta, no por §14.)
- **GSAP sí se usa aquí** (`assets/js/vendor/gsap/gsap.min.js`,
  `gsap.quickTo()` para el tilt al cursor de cada caja, `gsap.timeline()` para
  abrir/cerrar la tapa). Es el primer uso real de GSAP en el proyecto.

**Rediseño posterior (mismo día):** todo `components/caja3d.php`,
`assets/js/sobres.js` y el bloque `pack3d` de `components.css` se reescribieron
de cero a petición de Alejandro, contra un prompt de 5 fases más estricto
(idle + tilt / submenú con callback `onEnvelopeTypeSelected` / apertura con
stagger / hover+selección / plantillas). El resultado es funcionalmente
equivalente al descrito arriba (misma técnica, mismas zonas de plantilla), con
las clases renombradas de `caja3d`/`sobre3d-mini` a `pack3d`/`pack3d-sobre`, y
**tres bugs reales corregidos** en esa reescritura que probablemente seguían
latentes en cualquier copia previa:
1. Clicar un sobre individual burbujeaba hasta el `.js-tipo-sobre` que lo
   envuelve (mismo árbol DOM, requisito técnico de §14) y volvía a disparar la
   apertura de la caja, que resetea la selección — falta un
   `e.stopPropagation()` en el listener de `.js-sobre-individual`.
2. El `gsap.fromTo()` del stagger de los 50 sobres dejaba `opacity`/`transform`
   como estilo en línea (más específico que cualquier clase CSS) sin limpiarlo
   nunca — bloqueaba para siempre la regla `.con-seleccion` que debe atenuar
   al resto de sobres al elegir uno. Se arregla con
   `onComplete: () => gsap.set(sobres, { clearProps: 'all' })`.
3. La rama `prefers-reduced-motion` escribía el mismo `opacity`/`transform`
   inline "por si acaso", sin necesidad (el estado de reposo por defecto ya es
   ese) y con el mismo efecto bloqueante que el bug anterior — se eliminó, esa
   rama ahora no toca los sobres en absoluto.

### 14.0b Geometría 3D — cómo se arma la caja (no lo "simplifiques")

Cada cara se **centra** primero en el volumen con `translate(-50%,-50%)` y luego
se empuja a su sitio con `translateX/Y/Z` + `rotate`:

| Cara | Tamaño | Transform tras centrar |
|---|---|---|
| `front` | w × h | `translateZ(+d/2)` |
| `top` | w × d | `translateY(-h/2) rotateX(90deg)` |
| `side` | d × h | `translateX(+w/2) rotateY(90deg)` |
| `interior` (suelo) | w × d | `translateY(+h/2) rotateX(90deg)` |

**No plegar las caras desde su arista con `transform-origin`.** Es lo que hacía
la primera versión y las dejaba casi de canto: la caja se veía como un
rectángulo plano, sin volumen. El volumen se orienta con
`rotateX(-18deg) rotateY(-32deg)` (así quedan visibles frente + tapa + lateral
derecho: las normales de ambas acaban con z positiva).

La **tapa** cuelga de `.pack3d-bisagra`, un div de altura 0 colocado en la
arista trasera-superior; con `transform-origin: 50% 0` girarla es literalmente
abrir una bisagra: `rotateX(90deg)` cerrada (tumbada tapando la boca) →
`rotateX(205deg)` abierta (de pie, inclinada hacia atrás).

Los **sobres van de pie** dentro, escalonados en profundidad con
`translateZ` a partir de `--i` (índice) y `--n` (total), que `pack3d_sobre_html()`
escribe en el atributo `style`. Tres trampas que ya mordieron:

1. **Nada de `overflow` en el contenedor de los sobres.** Cualquier
   `overflow≠visible` crea un contexto de recorte que **aplana en 2D a todos
   sus descendientes** y destruye el `preserve-3d` (además de pintar barras de
   scroll sueltas flotando en la escena).
2. **GSAP no puede animar `transform` sobre `.pack3d-sobre`**: ese transform
   lleva su `translateZ(var(--z))` de colocación y un transform en línea lo
   sustituiría entero, apilando los 50 sobres en el mismo punto. Se anima la
   variable `--alza`. Por lo mismo, **nunca `clearProps: 'all'`** sobre un
   sobre: borraría `--i`/`--n`.
3. **Un ancestro con `transform` es el bloque contenedor de sus descendientes
   `position: fixed`.** GSAP dejaba un transform inline en `.submenu-tipo`
   (ancestro de `.pack3d-portal`) y el overlay a pantalla completa se encogía
   al tamaño de la cajita del submenú. Toda animación sobre un ancestro del
   portal debe terminar con `clearProps: 'transform'`.

### 14.1 Sistema de plantillas (arte de cajas y sobres)

Cada caja/sobre necesita un **único PNG plano** con zonas fijas predefinidas en
`Tcg::ZONAS_CAJA` / `Tcg::ZONAS_SOBRE` (nunca configurables por el admin: si se
movieran, el render 3D quedaría desalineado con lo ya subido):

- **Caja** (`caja_expansion` y `caja_sobre` comparten geometría, solo cambia la
  escala CSS): lienzo `1024×1024` — `front` 400×500, `side` 260×500,
  `top` 400×260, `lid` 400×260, `interior` 400×260.
- **Sobre**: lienzo `1024×720`, `frente` y `reverso` de 400×570.

> **Las proporciones de cada zona son las de la cara que pintan**, derivadas de
> `--pack3d-w/h/d` (200:250:130) y `--pack3d-sobre-w/h` (165:235). Antes eran
> todas cuadradas (512×512) o mitades (512×256), así que el arte se estiraba al
> pintarse y **la plantilla no correspondía con el resultado**. Si tocas esas
> variables CSS, hay que recalcular las zonas con la misma regla de tres.
> Cambiar las zonas **invalida el arte ya subido**: hay que volver a descargar
> la guía y resubir. El lienzo del sobre cambió de tamaño, así que las
> plantillas de sobre antiguas las rechaza `subirPlantilla()` con su mensaje de
> medidas — es la validación funcionando, no un fallo.

Flujo en `panel/plantillas.php` (solo `dictador=1`):
1. **Descargar la guía** (`Tcg::generarGuiaPlantilla()`, dibujada con GD — no
   hay Node/sharp en esta máquina, ver §8): PNG del tamaño exacto con las
   zonas marcadas, etiquetadas, y un aviso "LEEME" en el hueco libre del
   lienzo. `imagestring()` de GD solo entiende ISO-8859-1, así que el texto se
   pasa por `iconv(..., 'ISO-8859-1//TRANSLIT', ...)` — es la misma trampa de
   codificación de §5.3, aquí aplicada al render de la guía, no a MySQL.
2. El admin sustituye el arte **sin mover ni redimensionar las zonas** en
   Photoshop y exporta un PNG plano del mismo tamaño exacto.
3. **Subir** (`Tcg::subirPlantilla()`): valida PNG + dimensiones exactas,
   recorta cada zona con GD (`imagecopy` con alpha preservado), guarda en
   `assets/img/plantillas/{tipo}_{id}/` con nombre versionado por
   `time()`, y escribe las rutas como JSON en `plantillas_3d.rutas_recortadas`.
4. La **vista previa reutiliza el componente real** (`pack3d_caja_html()` /
   `pack3d_sobre_html()`), incluida la apertura de tapa con 6 sobres de
   muestra — el admin valida contra el motor de producción, no una maqueta.

### 14.2 La ceremonia de apertura (reescrita)

`partials/ceremonia.php` + `assets/js/ceremonia.js`. **Tres escenas**:

1. **El sobre se abre por arriba** (`#ceremoniaEscena`) usando la textura de SU
   plantilla (`sobre.frente`, que `sobres.php` pasa por `data-frente`): entra
   girando, le barre un reflejo, tiembla y se le **arranca una tira superior**
   que sale volando, dejando ver la boca oscura. **El sobre NO desaparece** —
   se queda abierto en pantalla porque las cartas tienen que salir de él.
   La tira lleva la misma textura encuadrada en su franja de arriba
   (`background-size: 100% calc(100%/0.14)`), así que encaja con el cuerpo en
   vez de parecer un rectángulo pegado encima.
2. **Carta a carta, saliendo del sobre**: cada carta arranca con `y` positiva y
   `z-index: 2` (**detrás** del cuerpo del sobre, o sea dentro), sube, y a
   mitad de recorrido pasa a `z-index: 6`. Ese cambio es lo que vende que sale
   de dentro. Ya fuera, espera **el clic** del jugador para voltearse (es un
   `<button>` real, así que funciona con Enter/Espacio). Un segundo clic la
   aparta y saca la siguiente.
   - Rareza **≥ 5** (legendaria y SRF) no se voltea sin más: dispara antes un
     **walkout** — se oscurece todo, giran rayos cónicos, aparece el nombre de
     la rareza con latido, y la carta se destapa con destello y temblor.
   - `Saltar carta` resuelve la actual al instante; `Saltar todo` va al resumen.
3. **Resumen** (`#ceremoniaMesa`): todas las cartas ya reveladas, con el
   anuncio por `aria-live`.

La apertura es **inmersiva**: mientras dura, `#modalSobre` lleva la clase
`.es-inmersiva`, que le quita caja, borde, cabecera, pie y la mesa, y deja solo
el fondo negro con el sobre. El único control visible es `.cer-saltar`, arriba
a la derecha. Al llegar al resumen se quita la clase y vuelve el modal normal.

El sobre se construye con la pinta de uno real: proporción **1 : 1.9**, dos
**bandas termoselladas** (`.cer-sobre-tira` arriba, `.cer-sobre-sellado` abajo)
con estriado vertical fino y filo dentado, abombamiento lateral y reflejo de
film. La banda de arriba es la que se rasga y sale volando.

> ⚠️ **GSAP es el dueño ÚNICO del `transform` de `.cer-carta` y `.cer-sobre`.**
> Ninguno de los dos lleva `transform` en el CSS. Dos consecuencias que ya han
> mordido, una por elemento:
> - **El volteo va con `rotationY` desde JS, nunca con una clase CSS.** GSAP
>   deja el transform en línea y un estilo en línea gana siempre a una regla de
>   clase: la carta se quedaba con la clase `esta-volteada` puesta pero **sin
>   girar** (`matrix(1,0,0,1,0,0)`).
> - **El centrado va con `xPercent/yPercent` de GSAP, no con
>   `translate(-50%,-50%)` en CSS**, que el primer tween machacaría. Al sobre
>   le pasó exactamente eso: perdía el centrado en cuanto se le animaba nada.
>
> **Al verificar un volteo o un centrado hay que mirar la matriz de
> `transform`, no la clase ni el atributo** — comprobar la clase es lo que hizo
> dar el fallo del volteo por arreglado dos veces sin estarlo.

### 14.2c ⚠️ Movimiento reducido: la trampa que se come TODAS las ceremonias

**Si alguien reporta "he cambiado la animación y no pasa nada", mira esto
ANTES que el código.** En Windows basta con apagar *Configuración →
Accesibilidad → Efectos visuales → Efectos de animación* (muchísima gente lo
hace **por rendimiento**, no por sensibilidad al movimiento) para que Chrome
reporte `prefers-reduced-motion: reduce`, y entonces la ceremonia salta
directa al resumen. El síntoma engaña: parece que el código nuevo no se ha
desplegado, cuando en realidad se está ejecutando su rama correcta.

Comprobarlo desde PowerShell:
```powershell
$m = (Get-ItemProperty 'HKCU:\Control Panel\Desktop' -Name UserPreferencesMask).UserPreferencesMask
[bool]($m[0] -band 0x02)   # False = animaciones DESACTIVADAS
```

**La preferencia del sistema se sigue respetando por defecto** (WCAG 2.2, §7),
pero se puede sobrescribir **solo para esta web**:

- **`SRF.movimientoReducido()` es la ÚNICA fuente de verdad**, y se define
  **inline en `partials/head.php`**, no en un `.js` aparte: un fichero externo
  puede servirse cacheado y dejar la página en el modo equivocado (pasó con
  `ui.js`), y además así se aplica antes del primer pintado. Manda
  `localStorage['srf-animaciones']`: `'si'` fuerza animaciones, `'no'` las
  quita, ausente = automático. Nadie más debe llamar a
  `matchMedia('(prefers-reduced-motion)')` — la única excepción legítima es
  `configuracion.js`, que necesita saber qué dice **el sistema** para
  explicárselo al jugador.
- Se consulta **en cada uso**, nunca cacheada en una variable al cargar el
  script: así cambiarla surte efecto sin recargar.
- **El CSS NO usa `@media (prefers-reduced-motion: reduce)`.** Todas esas
  reglas cuelgan de `:root[data-motion="reduce"]`, y el atributo lo pone el
  bootstrap de `head.php`. **Es obligatorio seguir así**: una media query la
  decide el sistema y **JavaScript no puede sobrescribirla**, así que con ella
  activar las animaciones en la web dejaba la ceremonia en `display: none` —
  el JS se ejecutaba pero la pantalla estaba muerta y no se podía ni hacer
  clic. Si añades una regla de movimiento reducido, cuélgala del atributo.
- `ui.js` **amplía** `window.SRF` en vez de reasignarlo (`window.SRF = {...}`
  borraría lo que dejó el bootstrap inline).
- Se elige en `configuracion.php` → «Animaciones», que además **explica el
  estado real** ("tu sistema pide reducir el movimiento, así que ahora mismo
  NO verás animaciones").
- Y como nadie va a buscar eso en los ajustes, la ceremonia **ofrece activarlo
  donde se nota**: si se saltó por la preferencia del sistema y el jugador no
  ha elegido todavía, el resumen muestra un aviso con «Activar animaciones
  aquí» que repite la apertura al instante. Quien pulse «No, gracias» guarda
  `'no'` y no lo vuelve a ver.

### 14.2b Qué NO se construyó

La **vitrina 3D con scroll horizontal** entre expansiones (el plan de la v4 de
este documento) nunca se hizo: las expansiones van en una rejilla normal.
Tampoco hay aura anticipatoria *antes* de ver el dorso — el aura se enciende al
pedir el volteo, no antes.

### 14.3 Comprobaciones

Además de §13, si tocas la ceremonia de cajas/sobres:
- `assets/img/plantillas/{tipo}_{id}/` se crea sola al subir la primera
  plantilla de ese elemento; sin ninguna subida, todo debe seguir mostrando el
  degradado por defecto sin errores.
- Subir un PNG de tamaño incorrecto debe rechazarse con el mensaje exacto de
  medidas esperadas, nunca recortar "lo que se pueda".
- `panel/plantillas.php` deniega el acceso a quien no tenga `dictador=1`,
  igual que el resto de `panel/`.
- Con `prefers-reduced-motion`: revisar que el tilt (`gsap.quickTo` en
  `mousemove`) no sea la única forma de interactuar con la caja — hoy el clic
  para abrir funciona igual sin tilt, pero si se añade motion nuevo aquí debe
  respetar la preferencia como el resto del sitio (§7).

---

## 15. El partido narrado en vivo (CONSTRUIDO, lo más nuevo del proyecto)

Implementa la **Biblia** (`branding/Biblia/`), sobre todo su §1 (motor de
eventos), §2 (minijuegos) y el veredicto de priorización de su §13.3, que fue el
que marcó el orden: *"el motor de eventos es la pieza fundacional de la que
dependen literalmente todas las demás"*.

**Todas las referencias tipo "§1.5 regla 6" o "§4.6" de esta sección apuntan a
la Biblia, no a este documento.**

El problema que venía a resolver (Biblia §0.2): un duelo era *un botón que se
pulsa y un resultado que aparece*. Todo el trabajo de las tres capas no se veía
nunca desplegarse. Hoy un duelo PvP se juega.

### 15.1 El motor de eventos

`Tcg::generarEventosPartido()`, en `db/consultas.php`. Es una capa que va
**encima** del resultado, nunca dentro (§1.1): quién gana lo siguen decidiendo
las tres capas cerradas y la curva Elo.

- La **posesión** se reparte ponderada por la línea de **MEDIO**, no por el
  total (§1.2). El **Ataque** contra `Defensa + Portería` decide qué tramos
  acaban en ocasión.
- **No guarda nada.** La narración se regenera de forma determinista desde el
  `valor_sorteo` que el duelo ya tiene almacenado. Un mismo duelo se narra igual
  siempre (necesario para que el veredicto compartible siga cuadrando) y dos
  intentos distintos del mismo nodo se narran distinto solos, porque cada duelo
  tiene su propio sorteo. Eso es la regla 6 de §1.5 sin pagar una columna JSON
  por duelo.
- **Momentum** (§1.4): media móvil de quién ha generado las ocasiones recientes,
  en −100..100. No toca ningún cálculo, pero **varios minijuegos del catálogo no
  hacen otra cosa que moverlo**, así que tenía que existir antes que ellos.
- Garantiza un mínimo de interacción: si el azar deja un hueco largo sin nada que
  leer, fuerza un evento de contexto (§1.5 regla 8).

**El contrato de un evento se rompe caro** — es la superficie a la que se van a
enganchar las ~90 entradas del catálogo:

```
id · minuto · tipo · lado · texto · marcador · momentum
familia · familia_def · interactivo · protagonistas   (solo en ocasiones)
```

- `familia` es la del que **ataca**; `familia_def`, la del que **defiende**. La
  misma jugada es `disparo` para quien remata y `porteria` para quien la para.
  Sin esa distinción, Muralla Humana no aparecía nunca.
- `protagonistas` viaja ya resuelto. Si el minijuego volviera a elegir jugador
  por su cuenta, contradiría el texto que el jugador acaba de leer.
- Los `id` se renumeran **después** de ordenar por minuto. El descuento y el
  descanso se añaden fuera del recorrido de tramos, así que el orden de creación
  no es el cronológico y los id salían saltados.

### 15.2 Las frases

`db/plantillas_narracion.php` — un array, sin lógica. La regla 6 de §1.5 (la que
la Biblia llama *"la corrección más importante de toda la sesión"*) exige que
repetir un nodo se lea distinto, y eso no se consigue con probabilidad sino con
**volumen**: el motor consume las frases **sin reemplazo** dentro de un mismo
partido, así que el número de variantes de cada bloque es literalmente el techo
de variedad de ese partido.

**Añade variantes solo al FINAL de cada array**: la elección es determinista a
partir del sorteo, así que insertar en medio reescribe la narración de los
duelos ya jugados.

### 15.3 El partido en vivo (migración `016`)

Antes cada jugador reproducía el partido en SU reloj, al cargar la página con
`?nuevo=1`. Con dos personas en el mismo duelo eso no se sostiene: veían minutos
distintos y la pausa de uno no existía para el otro.

Ahora **el minuto lo manda el servidor**, derivado del reloj de pared en cada
sondeo (no hay cron):

```
minuto = (NOW() − partido_inicio − partido_pausa_seg) × ritmo
```

- `assets/ajax/duelo_narracion.php` es el sondeo (1/s). `Tcg::estadoPartido()`
  hace todo en diferido: arrancar el reloj, pausar al llegar a un minijuego,
  aplicar el fallback de quien no contesta y reanudar.
- Cuando toca un minijuego, `partido_pausado_en` detiene el reloj **para los
  dos**. Al otro se le dice *"el rival está decidiendo"*, en vez de dejarle el
  reloj congelado sin explicación.
- **Regla acordada con Alejandro: si no estás atento, te lo pierdes.** El partido
  arranca cuando los dos han latido o al vencer `partido_espera_seg`, y no espera
  indefinidamente a nadie.
- El cliente **no tiene reloj propio** en modo narrado: solo pinta lo que dice el
  sondeo.

### 15.4 Los minijuegos

`db/minijuegos.php` es el catálogo. El motor no sabe jugar a ninguno: lee de ahí
qué ofrecer. **Añadir el minijuego 40 es añadir un array, no tocar el motor.**

Cada entrada declara su `impacto`, y eso lo decidió Alejandro explícitamente —
**lo declara el minijuego, no el tipo de duelo**:

- `ninguno` — solo suma a la puntuación de actuación.
- `jugada` — puede cambiar el desenlace de ESA jugada (un gol pasa a parada o al
  revés). Cambia el marcador, **nunca el ganador**.
- `partido` — reservado, sin usar. Exige mover la resolución del duelo a después
  del partido, y eso está sin decidir.

**Por qué `jugada` es seguro en PvP, verificado en el código y no supuesto:** el
reparto de la apuesta usa solo `id_ganador`, las misiones cuentan duelos y
victorias (nunca goles), y el `rango` se calcula únicamente en PvE. **En un duelo
PvP el marcador no lo lee nadie más que la pantalla que lo pinta.** Si algún día
pasa a valer para algo, hay que releer esto.

Construidos **43** de ~90, repartidos por **hueco** —que es la unidad que de
verdad importa: `(lado, familia, tipo de evento)`, no `(familia, lado)`—. Los
diez huecos que el motor puede producir están cubiertos, ninguno vacío:

| Hueco | Apariciones / 600 partidos | Dato oculto | Entradas |
|---|---|---|---|
| defiendes · `porteria` · **gol** | 1527 | remate | `muralla_humana`, `mano_cambiada`, `lectura_de_cadera`, `el_ultimo_palmo`, `el_paso_adelante`※ |
| defiendes · `porteria` · **parada** | 1488 | remate | `agarrar_o_golpear`, `el_farol`, `cerrar_el_angulo` |
| atacas · `disparo` · **parada** | 1488 | estilo_portero | `elige_tu_veneno`, `el_regate_previo`, `cara_a_cara`, `salto_depredador`, `golpe_de_primeras`※ |
| atacas · `disparo` · **tiro_fuera** | 1173 | estilo_portero | `desde_la_frontal`, `primer_toque`, `golpe_de_fe`, `efecto_imposible` |
| atacas · `balon_parado` · **falta** | 1056 | colocacion_defensa | `la_barrera`, `la_pizarra`, `bombardeo_aereo`, `doble_engano`, `francotirador`※ |
| defiendes · `defensa` · **despeje** | 983 | remate | `entrada_al_limite`, `susto_propia_puerta`※, `el_sacrificio_final`, `pedir_ayuda` |
| atacas · `disparo` · **despeje** | 983 | colocacion_defensa | `cara_o_cruz`, `la_humillacion`, `dentro_o_fuera`, `control_magico`, `el_latigazo`※, `escudo_humano`※ |
| defiendes · `balon_parado` · **corner** | 703 | remate | `hombre_o_zona`, `salir_o_quedarse`, `vigilancia_aerea` |
| atacas · `balon_parado` · **corner** | 703 | colocacion_defensa | `el_corner`, `segunda_jugada`, `corner_de_bolsillo`, `jugada_laboratorio` |
| atacas · `arbitro` · **tarjeta** | 304 | reaccion_rival | `perder_los_papeles`, `el_motin`, `el_ultimo_aviso`, `ojo_de_halcon` |

※ = primitiva `medidor`. Las demás son `eleccion`.

Los de `porteria` y los de `disparo` sobre el mismo dato oculto son **espejo**
unos de otros: sus opciones encajan una a una, así que lo que aprendes parando
te sirve para rematar.

**Las entradas defensivas sobre `parada`, `despeje` y `corner`, y todas las de
`arbitro`, son de impacto `"ninguno"`** y no por prudencia: esas jugadas ya
acabaron sin gol, así que no hay ningún gol que quitar. Suman a la puntuación de
actuación y ahí se quedan. Por eso existe `partido_minijuegos_sin_impacto_max`
(§15.5).

**Dos cosas del motor cambiaron para que esto quepa** (v7.1, 2026-08-07):

1. **`minijuegoDeEvento()` ya no devuelve la primera entrada que case.** Junta
   todas las candidatas y elige de forma **determinista** con la semilla del
   duelo. Antes había un techo real de **una entrada por (familia, lado)**: la
   segunda que escribieras era código muerto, y ese —no el motor de eventos—
   era el límite para crecer. De regalo sirve a §1.5 regla 6: repetir un nodo
   ya no solo se lee distinto, se **juega** distinto.
2. **Clave `tipos` opcional** en una entrada, para acotarla a ciertos tipos de
   evento. Hace falta porque `balon_parado` mezcla córners y faltas, y
   *"¿dónde pones el córner?"* sobre una falta se lee como un fallo.

**Tercer dato oculto: `colocacion_defensa`** (`{salta, aguanta, sale}`), que se
lee del **defensa** rival y no del portero. No es una preferencia estética: en
una **falta** el motor no reparte portero —sus protagonistas son solo `jugador`
y `defensa`—, así que un minijuego de balón parado que leyera al portero
devolvería siempre su valor por defecto y dejaría **una opción ganando el 100 %
de las faltas**. El defensa sí está en las dos jugadas de la familia.

**Cuarto dato oculto: `reaccion_rival`** (`{protesta, teatro, sigue}`), el que
abre la familia `arbitro`. Se lee del **rival que sufre la falta**, que está en
la alineación **contraria a la del evento** — y eso es lo que obligó a crear un
dato nuevo en vez de reutilizar uno: en un evento de tarjeta el `lado` es el
equipo **sancionado**, así que los tres datos anteriores (que leen a quien
defiende la jugada) habrían buscado la carta en la alineación equivocada, no la
habrían encontrado nunca y habrían dejado **una opción ganando el 100 % de las
tarjetas**. Tampoco se lee tu propio amonestado a propósito: tu alineación la ves
entera, así que adivinar algo de tus cartas no sería adivinar nada.

> ⚠️ **Lo que decía la v7.1 sobre huecos inalcanzables ERA CIERTO, pero por una
> sola línea de código — y en la v7.3 esa línea cambió.** El filtro
> `$tieneSentido` de `narracionDuelo()` exigía `tipo === "gol"` para defender, y
> como un gol siempre es `familia_def` `porteria`, las familias `defensa` y
> `balon_parado` defensivas eran imposibles **por construcción**, no por falta de
> contenido. Ahora defender solo exige que la jugada del rival traiga
> `familia_def`:
>
> ```php
> $tieneSentido = $defiendo ? !empty($e["familia_def"]) : ($e["tipo"] !== "gol");
> ```
>
> Eso abrió **tres huecos nuevos con 3.174 apariciones** de cada 600 partidos
> (parada 1488, despeje 983, córner 703) y con ellos la familia `defensa`, que el
> documento daba por inalcanzable. Los que siguen sin llegar se excluyen solos,
> sin necesitar el filtro: `tiro_fuera` trae `familia_def` en `null` y `falta` no
> la trae, así que `minijuegoDeEvento()` no encuentra familia y devuelve `null`.
>
> El de `arbitro` tampoco era inservible de raíz: el motor **ya calculaba**
> `$jugador` y `$defensa` en la rama de la falta y los tiraba solo al emitir la
> tarjeta. Adjuntarlos fue una línea.
>
> **Sigue valiendo la regla: antes de escribir una entrada para un hueco nuevo,
> comprueba que llega.** Lo que ya no vale es la conclusión de que solo hay tres.
> Para abrir un hueco más habría que dar `familia_def` a la falta (abriría
> defender un balón parado, que es donde encaja *El Muro de Piedra* de la
> Biblia), y eso sí es decisión de diseño.

**Tres reglas que costaron sangre:**

1. **Ciclo cerrado obligatorio.** Cada opción para exactamente un tipo, y cada
   tipo lo para exactamente una opción. La primera versión hacía que cada opción
   ganase a una y perdiera contra otra, y entonces el remate `potente` no lo
   paraba **ninguna**: un tercio de las jugadas estaban decididas antes de elegir.
2. **El dato oculto NO viaja al cliente.** Solo va una pista sobre la tendencia
   de la carta rival. Si viajara, bastaría con mirar la respuesta de red para
   acertar siempre.
3. **El desvío se mide contra la media de los ONCE que están en el campo**, no
   contra cero. Contra cero, el sesgo del catálogo (más Técnica que Ataque)
   empujaba siempre al mismo tipo de remate y dejaba una **opción dominante**
   (+27 de balance), que es justo lo que prohíbe §1.5 regla 2. Centrado, las tres
   opciones quedan a ~33 % y solo leer la pista sube al 37 %.

### 15.4f Un acierto SUBE la probabilidad de gol, ya no lo regala (`020`)

Decisión de Alejandro: *"si ganas un minijuego en un punto decisivo que sea
ocasión de gol"*. Hasta la migración `020` un acierto movía el marcador **siempre**
(100 %), así que leer bien la jugada equivalía a marcar. Ahora entra con
`partido_minijuego_prob_gol` (0,70): puedes adivinarle la intención y que se
estrelle en el palo.

**Lo que NO cambia: fallar sigue sin castigar.** El minijuego solo puede mejorar tu
partido, nunca empeorarlo (ver `resolverMinijuego`), así que un fallo deja la
jugada exactamente como estaba. La probabilidad se aplica **solo al acierto**.

**El sorteo es determinista por (duelo, evento)**, con sal propia (8663). No es
opcional: el sondeo repite y la resolución se puede reintentar, así que con azar
real el mismo acierto entraría una vez y no la siguiente, y los dos jugadores
verían desenlaces distintos. Medido: reparto al 70,0 %, determinista, y sin
correlación con la elección de minijuego (50,8 % contra el 50 % esperado).

> ⚠️ **Esto obligó a un tercer desenlace en el cliente.** Antes había dos —acertaste
> o falleste— y ahora hay tres, porque un acierto puede no acabar en gol. Sin
> cubrirlo, el jugador acertaba, no pasaba nada y **no había nada en pantalla que
> se lo explicara**: parecía que el minijuego no servía. La respuesta lleva
> `podia_mover` para distinguirlo de una decisión que nunca iba a tocar el marcador
> (impacto `ninguno`, o una defensa sobre una jugada que ya acabó sin gol), donde
> no hay nada que justificar.
>
> Si añades un desenlace nuevo, mira las tres ramas de `duelo.js`, no dos.

### 15.4e ⚠️ El bucle del §1.3 aplanaba TODOS los partidos

Salió midiendo otra cosa, y afectaba a cada duelo que se jugara. El bucle que
corrige el marcador para que no contradiga al ganador sorteado
([consultas.php:3013](db/consultas.php:3013)) paraba **en cuanto el ganador se
ponía por delante**, y el efecto medido era brutal:

| margen final | natural de la simulación | tras el bucle (antes) | tras el arreglo |
|---|---|---|---|
| 0 | 36,0 % | — | — |
| **1** | 44,8 % | **88,8 %** | **80,5 %** |
| 2 | 15,1 % | 9,5 % | **15,0 %** |
| 3 | 3,5 % | 1,2 % | **3,8 %** |
| goles/partido | 1,53 | 2,47 | 2,46 |

**El reparto natural de la simulación era sano** —tiene forma de fútbol— y el
bucle se lo comía entero: casi ningún partido era una goleada ni un partido
cómodo, todos se parecían. De paso **inflaba el marcador un 60 %** (1,53 → 2,47
goles) ascendiendo ocasiones falladas hasta poner al ganador delante.

**El arreglo:** el bucle ya no busca "que gane por uno" sino **que gane por el
margen que tenía el partido natural**. Si la simulación iba a dar un 3-1, acaba
3-1 aunque haya que dárselo al otro. Solo los empates naturales se rompen a
margen 1, que es el mínimo destrozo de verdad.

> **El 80,5 % restante es un suelo, no un fallo:** el 36 % de partidos que la
> simulación deja empatados **tiene** que romperse, y siempre a margen 1. Bajar de
> ahí exigiría o menos empates naturales (más goles) o dejar que el partido
> decida, que está descartado (`branding/impacto-partido-analisis.md`).
>
> Y subir la conversión de gol **no lo arregla**: medido a `gol_base` 0,18 el
> margen 1 seguía en el 84 %, porque el problema era dónde paraba el bucle.

### 15.4d `impacto: "partido"` — lo que arrastra al resto del encuentro

La tercera clase de impacto, y la que la Biblia pide para sus entradas de ritmo y
moral. **Decisión de Alejandro tras medirlo** (todo el razonamiento y los números
están en `branding/impacto-partido-analisis.md`): **el efecto NO mueve la
resolución del duelo.** El ganador lo sigue decidiendo la curva Elo y
`resolverDuelo()` no se ha tocado.

Lo que hace un `partido` es **ampliar el presupuesto** con el que las jugadas
siguientes pueden mover el marcador, o conceder una decisión más. Cada entrada
declara `efecto`, y el verificador lo exige:

| `efecto` | qué concede |
|---|---|
| `presupuesto_gol` | una ocasión propia más podrá acabar en gol |
| `presupuesto_parada` | un gol del rival más podrá pararse |
| `decision` | una decisión más en el partido (tope +1, por el coste de pausas) |

**Sigue teniendo sentido ahora que el partido decide el duelo, y de hecho más.**
El presupuesto pasó de ser *el margen que dejaba libre el ganador pre-sorteado* a
ser un **tope de diseño** (`partido_presupuesto_marcador`, §15.10), así que
ampliarlo es una recompensa clara y acotada: un gol más de los que puedes mover.
Antes era una concesión dentro de una restricción de coherencia; ahora es
directamente lo que dice que es.

El efecto **se reconstruye** de las filas de `duelo_minijuegos` en cada sondeo
(`minijuegosResueltos()`), así que los dos jugadores ven lo mismo, sobrevive a
recargar la página y no hace falta guardar estado nuevo en ninguna parte.

> ⚠️ **Un `partido` solo puede CONCEDER, y no es una elección de diseño.**
> `resolverMinijuego()` no castiga elegir mal a propósito: *"el minijuego solo
> puede mejorar tu partido, nunca empeorarlo, así que ofrecerlo jamás es una
> trampa"*. Por eso la **familia de Decisiones Negativas de la Biblia sigue siendo
> inexpresable** —El Baile Provocador, La Fiesta Peligrosa, donde una rama "solo
> puede salir peor"—. No es un olvido del catálogo: es esa regla, y cambiarla es
> otra decisión.

> **Lo que quedó en suspenso y ya está resuelto:** la **tanda de penaltis** se
> había decidido para romper empates, pero mientras el §1.3 forzaba un ganador
> **no había empates posibles**. Con el partido decidiendo (§15.10) los empates
> existen —**el 32 % de los partidos entre iguales**— y la tanda ya se juega en
> servidor. Sus 4 entradas interactivas siguen pendientes.

### 15.4c El penalti (migración `018`)

**No es un tipo de evento nuevo, y ahí está toda la gracia.** El motor coge una
ocasión **ya resuelta** y le pone el traje de pena máxima: la emite con los tipos
`gol` / `parada` / `tiro_fuera` de siempre y lo único propio es la **familia**
(`penalti`) y las frases. Así el marcador sigue naciendo del sorteo, el
presupuesto de §15.5 cuenta igual, `$tieneSentido` no se toca y los minijuegos se
enganchan por familia como cualquier otro. Es el mismo truco que ya usaba
`gol_asistido`: **la clave de la frase y el tipo del evento no tienen por qué
coincidir.**

Se señala en un evento aparte de la ejecución (`penalti_senalado`) para que el
relato tenga el latido real: primero la pena máxima, luego el disparo.

**Es el único hueco donde la misma jugada da decisión a los dos.** Si el penalti
entra, quien defiende puede sacarlo (*Leer la Mente*); si se falla, quien ataca
puede meterlo (*El Momento de la Verdad*, que la Biblia llama el minijuego
insignia de todo el catálogo).

> ⚠️ **El sesgo hacia las ocasiones que ya eran gol no es un detalle, y esconde un
> trato que es de Alejandro.** Sin sesgo salía marcado solo el **29 %** de los
> penaltis —en el fútbol real es el ~78 %— y la pena máxima se leía como una
> moneda al aire que casi siempre falla. Pero un penalti marcado le da la decisión
> al que DEFIENDE y uno fallado al que ATACA, así que **cuanto más realista es el
> acierto, menos aparece el insignia.** De ahí que las dos probabilidades vivan en
> `configuracion` (§5.4) y no en el código.
>
> Con los valores sembrados (`0.12` / `0.018`), medido sobre 600 partidos:
>
> | | valor |
> |---|---|
> | penaltis por partido | 0,47 *(fútbol real ~0,25)* |
> | marcados | **76,1 %** *(fútbol real ~78 %)* |
> | `leer_la_mente` se ofrece | 1 partido de cada 2,4 |
> | el insignia se ofrece | 1 partido de cada 9 |
>
> Subir `prob_gol` da más penaltis y más realismo pero esconde el insignia; subir
> `prob_fallo` hace lo contrario. Los dos llamantes de `generarEventosPartido()`
> tienen que pasar **los mismos valores** (`opcionesPenalti()`): uno narra el
> partido y el otro lo resuelve, y si difirieran el marcador guardado no cuadraría
> con el relato.

### 15.4b Las cuatro primitivas de interfaz (Biblia §2.1 y §2.2)

**Las cuatro están construidas.** La clave que las hace baratas es la misma en
todas: **las zonas, los sectores y los tramos del medidor SON las opciones**, así
que el ciclo cerrado, la pista, la opción segura y el verificador siguen valiendo
sin tocar nada del servidor. Cambia el mando, no la decisión — y una entrada pasa
de una primitiva a otra cambiando una clave.

| Primitiva | Entradas | Qué añade | Claves propias |
|---|---|---|---|
| `eleccion` | 44 | tres botones. La más usada del catálogo | — |
| `medidor` | 6 | una capa de **ejecución**: hay que cazar la aguja | `velocidad` |
| `zona` | 7 | **leer la posición** de un vistazo, sobre un mapa | `lienzo` + `zona` por opción |
| `arrastre` | 5 | el **gesto** del control de DS (Biblia §2.2) | `sector` por opción |

Ninguna toca el equilibrio: las tres zonas de un medidor son de ancho igual, los
tres sectores de un arrastre son de 60°, y en `zona` no hay puntería que fallar.
A ciegas siguen valiendo 1/3, que es lo que mide el verificador.

**`zona` (clic-en-zona).** Las opciones van sobre un mapa —`porteria` (el marco
de frente), `area` (el área desde arriba) o `campo` (el último tercio)— en el
sitio que les toca. El vocabulario de huecos de cada lienzo vive en
**`Tcg::LIENZOS_ZONA`**, y es fuente única de verdad para tres sitios que tienen
que coincidir: el catálogo, el verificador y las `grid-template-areas` de
`layout.css`. El cliente pone `grid-area` con el nombre tal cual, así que **un
hueco que el CSS no conozca se auto-coloca y descuadra el mapa sin dar ningún
error** — de ahí que el verificador lo compruebe. No necesita degradarse: son
`<button>` de verdad y no hay nada animado.

**`arrastre` (Familia DS).** Se arrastra desde el balón y el ángulo cae en uno de
tres sectores de 60° (`izquierda` −90..−30, `centro` −30..+30, `derecha`
+30..+90, medidos desde la vertical). Dos detalles que no son opcionales:
- **⚠️ Los botones siguen visibles con esta primitiva, por WCAG 2.2 SC 2.5.7
  (Dragging Movements):** toda función de arrastre necesita alternativa de un
  solo puntero. No son redundancia, son parte de la primitiva — sin ellos queda
  inoperable con teclado y fuera del §7.
- **Mínimo de 24 px de recorrido.** Sin él, un simple toque en la lona resolvía
  la jugada con el ángulo que saliera: un accidente esperando a pasar.
- La lona lleva `touch-action: none`, o el navegador se lleva el gesto al scroll.

Lo que **no** se construyó de la Familia DS: *La Conducción Serpenteante* pide un
trazo **prolongado** puntuado por fidelidad al camino, y eso no cabe en un
contrato que resuelve una opción contra un dato oculto. Está como elección de
ruta con el mismo gesto, y la desviación queda anotada en su entrada.

#### El `medidor` en detalle (Biblia §2.1, segunda primitiva)

Una aguja recorre las tres zonas en bucle y el jugador la detiene con un botón.
**La decisión de diseño que lo hace baratísimo: las ZONAS son las opciones.** El
ciclo cerrado, la pista, la opción segura y el verificador siguen valiendo sin
tocar nada del servidor — cambia *cómo* eliges, no *qué* se decide. Una entrada
puede pasar de `eleccion` a `medidor` cambiando una clave.

Dos claves nuevas en el catálogo: `primitiva` (`"eleccion"` | `"medidor"`) y
`velocidad`, en milisegundos de ida y vuelta de la aguja, con las mismas seis
claves que `plazo`. La velocidad es su palanca de dificultad (§3.2).

**Dos reglas propias, y las dos las comprueba el verificador:**

1. **La opción `segura` va EN MEDIO del array `opciones`.** La aguja cruza la
   zona central dos veces por ciclo, así que es la más fácil de acertar: fallar
   el pulso tiene que dejarte en lo conservador y nunca en lo de más premio. Es
   §1.5 regla 4 llevada de la decisión a la ejecución.
2. **`velocidad` completa en las seis dificultades**, o el medidor caería a un
   valor por defecto sin que nadie se enterase.

**No toca el equilibrio.** Las tres zonas son de ancho igual, así que a ciegas
siguen valiendo 1/3 cada una y el verificador mide exactamente lo mismo que
antes. Lo que añade es una capa de **ejecución** encima de la de lectura.

**Con movimiento reducido no se ofrece:** `duelo.js` cae a los tres botones de
siempre. Cazar una aguja *es* movimiento, y sin ella no hay medidor que jugar —
§7: se reduce el movimiento, nunca el juego.

> ⚠️ **La aguja arranca en el centro de la zona segura, no en el extremo.** Esto
> salió de una prueba real, no de la teoría: `requestAnimationFrame` se pausa en
> una pestaña en segundo plano (§8), y con la aguja arrancando en 0 la posición
> se quedaba ahí, así que pulsar "Parar" resolvía con la **primera** opción de la
> lista — que no es la conservadora. Un fallo de fotogramas se convertía en una
> decisión arriesgada tomada sin querer. Arrancando en la segura, lo peor que
> puede pasar coincide con lo que ya hace el servidor al vencer el plazo.
>
> Por eso el payload del sondeo incluye `segura` en cada opción. **No filtra
> nada**: dice cuál es la conservadora, no contra qué valor gana, y el jugador la
> conoce igual porque es la que se aplica sola al agotarse el plazo.

### 15.5 Cuántas decisiones, y por qué tan pocas

`partido_minijuegos_max` = **2 por jugador**. El número no vale por jugador sino
**por partido**: el reloj se para para los dos en cada decisión, así que dos
jugadores a tres son **seis pausas**, y el partido se hacía eterno (medido:
2 min 20 s). Hoy son ~62 s con 4 pausas.

Una decisión se ofrece si la jugada tiene sentido para ese lado, hasta el techo.
**Que pueda cambiar el marcador NO decide si se ofrece**: antes sí, y el
resultado medido era que **quien perdía por un gol se quedaba con cero
decisiones** — con margen mínimo no cabe mover nada sin contradecir el sorteo, y
justo el partido en el que más quieres pelear era el que no te dejaba tocar nada.
Cuando no cabe, la jugada sigue contando para la actuación (§4.6), así que nunca
es un "continuar" disfrazado.

#### Y CUÁNDO llegan: repartidas, no las primeras que valgan

`Tcg::repartirDecisiones()`. Hasta la v7.3 se ofrecían las **primeras** jugadas
que valían, y con los huecos defensivos abiertos eso se volvió un defecto
medible: las candidatas tempranas se multiplicaron y el techo se gastaba
enseguida, dejando el resto del partido plano y repitiendo siempre el tipo de
jugada más frecuente al principio.

| | antes | ahora |
|---|---|---|
| minuto mediano de una decisión | 10' | **46'** |
| minuto de la última del partido | 17' | **68'** |
| decisiones en los primeros 30' | 88 % | **33 %** |
| **mismo minijuego dos veces en un partido** | frecuente | **0,75 %** |
| decisiones por jugador | 2,00 | 1,99 |

Cómo funciona: la lista de eventos ya está completa cuando se llega ahí, así que
divide el partido en tantas ventanas iguales como decisiones quepan y coge una de
cada ventana. Dentro de una ventana prefiere, por orden: (1) las que pueden mover
el marcador, (2) las que **no repiten** un minijuego ya visto en ese partido. Las
ventanas vacías se rellenan al final para no perder una decisión por un reparto
desafortunado de los eventos.

**Es determinista de principio a fin**, y no es opcional:
`resolverMinijuegoDuelo()` vuelve a llamar a `narracionDuelo()` para recalcular
qué se jugó, así que con azar real aquí el servidor podría elegir una jugada
distinta de la que el jugador tenía delante.

#### El tope de las decisiones que no mueven marcador (`017`, v7.3)

`partido_minijuegos_sin_impacto_max` = **1**. Es el segundo techo, y hace falta
porque al abrir los huecos defensivos y la familia árbitro las entradas de
impacto `"ninguno"` pasaron a ser mayoría de las candidatas: los tres huecos
defensivos nuevos juntos (3.174 apariciones) superan al del gol (1527).

**Medido sobre 800 partidos, 1600 jugadores:**

| | sin tope | con tope = 1 |
|---|---|---|
| decisiones por jugador | 2,00 | 1,99 |
| de ellas sin impacto en el marcador | 35,3 % | 28,5 % |
| **jugadores sin NINGUNA que mueva el marcador** | **13,94 %** | **0,81 %** |

Sin el tope, **1 de cada 7 jugadores** gastaba sus dos decisiones en cosas
incapaces de tocar el resultado: exactamente el mismo problema que este apartado
arregló en su día por el otro lado, reintroducido por la puerta de atrás. Con el
tope baja 17 veces y las decisiones por jugador **no bajan** (2,00 → 1,99), así
que la variedad entra sin coste.

**Ojo con lo que el tope NO hace:** no sube el número de pausas, que es el coste
de ritmo real. El total sigue siendo `partido_minijuegos_max`; esto solo acota
cuántas de ellas pueden ser irrelevantes para el marcador.

**Y ojo con `impacto`, que hasta la v7.3 era decorativo:** la clave estaba
declarada en el catálogo desde el principio pero **el motor no la leía en ningún
sitio** —las tres menciones en `db/consultas.php` eran comentarios—, así que
`resolverMinijuegoDuelo()` movía el marcador solo por `lado` + `acierto`. Una
entrada con `impacto: "ninguno"` habría sumado un gol de la nada. Ahora la leen
los dos sitios que importan: `resolverMinijuegoDuelo()` para aplicar el gol y
`narracionDuelo()` para gastar presupuesto.

Quien decide de verdad si el marcador se mueve es **la base de datos**:
`descontarGolRival()` y `sumarGolPropio()` llevan sus condiciones dentro del
`UPDATE`, no comprobadas antes en PHP. Comprobar y luego actualizar deja una
ventana por la que dos peticiones a la vez aplicarían el cambio dos veces.
Verificado martilleando 25 peticiones seguidas: solo se aplica una.

> ⚠️ **Lo que iba en ese `UPDATE` y ya NO va:** la condición de §1.3, "el ganador
> sorteado sigue ganando después de mover el gol". Se retiró en §15.10 junto con
> `cabeCambioMarcador()`. Si la ves en un comentario viejo, el comentario está
> desfasado: **ahora el marcador ES el resultado**, y lo único que queda dentro
> del `UPDATE` es que nada baje de cero y que el duelo siga `en_juego`.

### 15.6 Veredicto y actuación

- **Actuación** (§4.6, §6.4): aciertos sobre decisiones jugadas. Se deriva de
  `duelo_minijuegos`, no se acumula aparte. Es lo que da sentido a una jugada
  cuando el marcador ya no puede moverse.
- **Veredicto** (§1.5 regla 7): cada partido cierra con un dato concreto, también
  al perder. `Tcg::veredictoDuelo()` busca hechos reales del encuentro y elige el
  más memorable — una parada tuya se cuenta antes que la posesión. Se calcula en
  servidor para que **las dos cuentas lean el mismo texto**.
  - Ojo al tono: *"Aguantaste hasta el X"* solo sale si X ≥ 70'. Sin ese corte
    salía "Aguantaste hasta el 15'", que promete épica y la desmiente con el
    número: se leía como una burla involuntaria.
- **Copiar al portapapeles** (`SRF.copiar`, en `ui.js`): del mecanismo 4 de
  Copero (§6.1), el resumen tiene que poder pegarse en Discord tal cual.
  `navigator.clipboard` **rechaza si el documento no tiene el foco**, no solo si
  el sitio va por `http`; por eso el método viejo con textarea no es el respaldo
  para `http` sino el respaldo para cuando la API moderna dice que no.

### 15.7 Duelos y cadenas están SEPARADOS a propósito

`duelo.php` decide el modo con `$esCadena ? 'clasico' : 'narrado'` y lo publica en
`data-modo`. `duelo.js` se ramifica ahí.

| | Duelos PvP | Cadenas PvE |
|---|---|---|
| Modo | `narrado` | `clasico` — **intacto** |
| Reloj | servidor | local (rAF) |
| Minijuegos | sí | no |
| Marcador | nace de la simulación, **y decide** (§15.10) | `marcadorCadena()`, sin tocar |
| Estado tras montarse | `en_juego`, sin ganador | `resuelto` de una vez |
| Cuándo se paga | al terminar el partido | en el acto |
| Botón "Ver resultado" | **no** | sí |
| Movimiento reducido | se juega igual, sin animación | se salta |

**El botón de saltar se quitó del PvP** porque saltártelo no detiene al rival,
que puede seguir parando goles después de que hayas salido: una cuenta acababa
viendo 4-2 y la otra 4-3. Cerrar sigue siendo posible con Esc o el aspa (§13
exige que un modal se pueda cerrar), pero el aspa dice *"Salir del partido"*, no
*"Ver resultado"*, porque lo segundo sería mentir.

Ese mismo bug tenía **dos causas encadenadas**, las dos corregidas: el cliente
solo actualizaba el marcador final cuando el gol lo parabas tú, y el servidor
informaba del marcador **original** en vez del actual (la reconstrucción que hace
`narracionDuelo()` para generar el relato sobrescribía los goles del duelo). Si
vuelve a bailar un marcador, mira esos dos sitios.

**Llevar el partido narrado a las cadenas está sin hacer, pero ya está DISEÑADO y
DECIDIDO** — plan completo, decisiones y medidas en **§15.12**.

### 15.8 ⚠️ El equilibrio está medido y NO cumple lo que se buscaba

Alejandro pidió que un equipo bien pensado pudiera ganar a uno de todo SRF. Se
midió pasando equipos completos por el motor real. **No se cumple, y la causa no
son las estadísticas de las cartas:**

```
Raro bien pensado  vs SRF surtido   11,2 %
Raro MAL pensado   vs SRF surtido   11,0 %
```

Pensar bien el equipo aporta **dos décimas**. El motivo: un equipo **surtido
activa 8 rasgos** de compos y un monotipo solo 4. **El sistema premia mezclar
afinidades, no enfocarlas** — es el hallazgo 1 del §10.6 de este documento, ahora
con número.

Ensanchar los rangos de estadísticas casi no lo mueve (de ±7 a ±25 lleva al Raro
del 11 % al 23 %, y con ±25 la rareza ya no significa nada). **La palanca es
`duelo_k`**: subirla de 400 a 1000 lleva al Raro al 30 % y al Épico al 39 %.

Los rangos para crear cartas nuevas están en
`branding/Rangos_estadisticas_SRF.xlsx` / `.csv`, ajustados por mínimos cuadrados
sobre las 38 cartas reales (desviación máxima 5,6 puntos) y con solape
deliberado entre rarezas contiguas. Las 38 cartas actuales encajan dentro de sus
rangos.

**Nada de esto se ha tocado: es balance, y el balance lo decide Alejandro.**

### 15.9 Comprobaciones si tocas el partido

Además de §13:

- El marcador narrado debe cuadrar con el guardado en **todos** los duelos
  resueltos, no en una muestra.
- **La invariante de §1.3 ya NO se comprueba porque ya no existe** (§15.10). Lo
  que hay que comprobar en su lugar es que el `id_ganador` de un duelo `resuelto`
  cuadre con su marcador, o que `resuelto_por_tanda` explique por qué no.
- **Un duelo no puede quedarse en `en_juego` para siempre**: ahí el dinero de los
  dos está retenido. Lo garantizan las **tres** ramas de abandono de
  `cerrarPartidoSiToca()` —partido que no arranca, partido parado en una decisión y
  **tanda a medias** (§15.11)—, y las tres tienen prueba.
- **Si añades una FASE al partido, el guion de prueba tiene que jugarla.** Una fase
  nueva sin cubrir no se ve como un error: se ve como **duelos colgados**, y hay que
  saber distinguir eso de un fallo del motor. Pasó al añadir la tanda: 73 de 300.
- **Pasa el verificador**, que cubre esto y seis cosas más de una vez:
  ```
  C:\xampp\php\php.exe db/verificar_minijuegos.php
  ```
  No toca la base de datos y sale con código 1 si algo falla. **Ejecútalo
  siempre que añadas una entrada a `db/minijuegos.php`.** Comprueba el ciclo
  cerrado, la opción segura única, que la clave de una opción no delate su
  valor, los plazos, el reparto del dato oculto, que ninguna entrada sea código
  muerto, el determinismo, cuánto vale leer la pista, que la primitiva sea
  conocida y —si es `medidor`— que traiga `velocidad` completa y la opción
  segura **en el centro** (§15.4b). Hoy: **75 entradas, 159 comprobaciones, 0 fallos**.
- **Su `recorrer()` tiene que replicar LITERALMENTE el `$tieneSentido` de
  `narracionDuelo()`.** Si se queda con una condición vieja, las entradas de los
  huecos nuevos salen marcadas como código muerto aunque en un partido real se
  ofrezcan — pasó al relajar el filtro en la v7.3.
- **El payload que viaja al navegador no puede llevar la clave `gana`** de
  ninguna opción, ni `oculto`, ni las listas `remates`/`estilos` del catálogo.
  Es el dato oculto: con verlo en la respuesta de red bastaría para acertar
  siempre. `segura` sí puede viajar (§15.4b explica por qué no filtra nada).
- **Ni el título ni el enunciado pueden llegar con marcadores `{}` sin
  sustituir.** El título no pasaba por `strtr()` hasta la v7.3, así que una
  entrada con un nombre ahí enseñaba `{defensa}` en pantalla.
- Ninguna opción de un minijuego puede tener ventaja eligiéndola siempre a ciegas
  (las tres a ~33 %), y leer la pista debe quedar por encima **sin resolverlo
  sola**. Medido hoy: a ciegas 33,2-33,9 %; leyendo, 37,2 % (`estilo_portero`),
  44,2 % (`remate`) y 46,9 % (`colocacion_defensa`).
- El dato oculto (`remate`, `estilo_portero`, `colocacion_defensa`) **no puede
  aparecer** en el JSON que se manda al cliente antes de decidir.
- Resolver dos veces la misma jugada debe devolver *"Esa jugada ya estaba
  resuelta"* y no volver a mover el marcador.
- Con movimiento reducido, un duelo PvP debe **abrir el modal y ofrecer sus
  minijuegos** igual; lo único que cambia es que nada se anima.

### 15.10 EL PARTIDO DECIDE EL DUELO (migraciones `019`–`022`)

**Es el cambio más grande del motor de duelos, y viene de una crítica de Alejandro
que era certera:** *"pero entonces no tendrían sentido los minijuegos, porque el
resultado viene dado ya de antes"*. Tenía razón, y se pudo medir: en PvP los goles
se leían en 4 sitios y la actuación en 2, **todos de pantalla**. Nada mecánico
dependía de ellos. Un minijuego podía cambiar el relato, nunca el resultado.

Lo que pidió, textual: *"que si ganas un duelo tengas más posibilidades en esa
ocasión de marcar un gol, no crees el resultado al iniciar el partido, que se vaya
decidiendo según se resuelvan los minijuegos, y si se queda en empate a penaltis y
gg"*.

#### Cómo funciona ahora

| Antes | Ahora |
|---|---|
| `resolverDuelo()` sorteaba el ganador y **pagaba** antes del minuto 1 | Deja el duelo en **`en_juego`**, sin ganador y sin pagar |
| La simulación recibía `gana` y tenía prohibido contradecir el sorteo | **Modo natural**: el marcador sale como salga, empates incluidos |
| Un empate era imposible | **32 % de los partidos entre iguales** acaban empatados |
| El minijuego movía el marcador dentro del margen que dejaba el ganador | El minijuego mueve el marcador, y **el marcador es el resultado** |
| — | Un empate se rompe en la **tanda de penaltis**, que se JUEGA (§15.11) |
| — | **`liquidarPartido()`** escribe el ganador y entrega el bote al terminar |

El pago **no cambió, y esto sorprendió al investigarlo**: ya funcionaba como hacía
falta. Cada uno deja lo apostado al entrar (`crearDuelo`, `aceptarDuelo`), así que
Paso 3 se redujo a **mover el momento de la entrega**, no a rehacer el flujo. Es
también lo que cierra la pregunta del abandono sin inventar ninguna regla nueva.

> ⚠️ **Cómo se retiene una CARTA apostada, que no es lo que parece.** Durante un
> rato este documento afirmó que se marca `bloqueada`. **Es falso, y no había ni una
> línea de código que lo hiciera.** `bloqueada` es el candado manual del jugador
> contra la venta (§9) y el duelo no lo toca.
>
> Lo que retiene la copia es que las consultas de *"¿puedo apostar o vender esta
> carta?"* excluyen las que tienen fila en `duelo_apuestas` con el duelo en
> **`estado NOT IN ('resuelto','cancelado')`** — tres sitios:
> `listarCopiasApostables()`, `publicarAnuncio()` y el selector de apuesta.
>
> **Que sea un `NOT IN` y no una lista positiva es lo que salvó este cambio**: el
> estado nuevo `en_juego` cae dentro sin tocar nada. Si hubiera estado escrito como
> `estado IN ('creado','aceptado',…)`, la carta se habría liberado a mitad de
> partido y se habría podido vender la que estabas jugando. Verificado sobre 25
> duelos de carta: la copia sigue retenida mientras el partido se juega.

#### Las tres piezas nuevas

- **`liquidarPartido($id_duelo)`** — decide con el marcador, rompe el empate en la
  tanda, entrega el bote, pasa a `resuelto`.
  > ⚠️ **La llaman los DOS jugadores en cada sondeo.** Que el bote se entregue una
  > sola vez **no lo garantiza el PHP**: lo garantiza `WHERE id_duelo = :d AND
  > estado = 'en_juego'` **dentro del `UPDATE`**, con `rowCount() === 0` →
  > `rollBack()` y no se paga. Comprobar y luego pagar deja una ventana por la que
  > dos sondeos simultáneos pagarían dos veces. Probado con cinco llamadas
  > seguidas: **una liquida, el bote se entrega una vez.**
- **La tanda** — se construyó primero automática (5 tiros de Ataque contra Portería,
  deterministas desde `valor_sorteo`) y **se retiró al hacerla jugable**: ver §15.11.
  Si buscas `tandaDePenaltis()`, ya no existe.
- **`cerrarPartidoSiToca()`** — el enganche perezoso (§8, no hay cron). Lo llaman
  el sondeo, `duelo.php` y `duelos.php`.

#### ⚠️ Las dos ramas de abandono, y por qué son obligatorias

Desde que el duelo se decide en el campo, **un partido a medias ya no es un
partido perdido: es un bote que no vuelve a nadie.** Y hay dos formas de que el
reloj no llegue nunca al final:

1. **El partido no arranca**, porque arrancarlo es cosa del sondeo y nadie volvió
   a abrir la pantalla (o volvió sin JavaScript).
2. **El partido se queda parado** en una decisión, porque el plazo solo lo aplica
   el sondeo de alguien presente.

Las dos se cierran pasado `partido_abandono_seg` (3600 s, holgado a propósito:
quien llega tarde todavía puede jugar su partido entero). Es §15.3 llevada a su
conclusión — *te pierdes el partido, no la apuesta*.

> **Lo primero que escribí en la rama 2 estaba mal y lo tumbó la prueba:**
> reanudaba el reloj y dejaba que siguiera su curso. Pero reanudar suma el tiempo
> parado a `partido_pausa_seg` —correcto, ese rato no era partido—, así que el
> encuentro vuelve al minuto en el que se detuvo y **todavía le faltan segundos**:
> hacía falta una segunda visita para cerrarlo. Un partido congelado una hora no
> necesita que le contemos los minutos que le quedaban.

#### Lo que se retiró, y que no vuelva

- **`cabeCambioMarcador()` se borró.** Era la §1.3 aplicada a los minijuegos.
  No se dejó como función muerta a propósito: dejarla invitaría a volver a
  llamarla, y volver a llamarla reimplantaría la §1.3 a medias.
- **La condición de §1.3 salió del `UPDATE`** de `descontarGolRival()` y
  `sumarGolPropio()`. **Si se hubiera dejado, con `id_ganador` en `NULL` durante
  `en_juego` daría siempre falso y ninguna parada contaría** — un fallo que se ve
  como "los minijuegos no hacen nada".
- **El presupuesto de marcador ya no es un margen, es un tope de diseño:**
  `partido_presupuesto_marcador` (1 gol por jugador). Se dejó en 1 porque es el
  margen que la §1.3 autorizaba en la práctica, para no meter en el mismo cambio
  *"los minijuegos deciden"* y *"los minijuegos deciden el doble"*. Subirlo hace
  que pesen más los minijuegos y menos la fuerza del mazo; bajarlo a 0 los deja en
  pura actuación.

#### Medido sobre 300 duelos jugados enteros por el camino real

No simulados aparte: **creados, aceptados, con aumentos cerrados, sondeados y con
cada decisión respondida**, 25 de ellos apostando CARTA. Es la comprobación de
referencia si vuelves a tocar el partido.

| contabilidad — lo que no puede fallar | |
|---|---|
| duelos liquidados | **300 / 300** |
| duelos que se quedaron en `en_juego` | **0** |
| **total de monedas del sistema** | **NO cambia** (cada uno pone, el ganador cobra) |
| **total de copias de carta** | **NO cambia**, solo cambian de dueño |
| ganador que no cuadra con el marcador (ni lo explica la tanda) | **0** |
| copias traspasadas y desbloqueadas | **25 / 25** |

| la forma del partido | |
|---|---|
| goles por partido | **2,42** |
| margen 0 / 1 / 2 / 3 / 4+ | **27,7 % / 36,3 % / 21,3 % / 11,3 % / 3,3 %** |
| empates en el campo → tanda | **27,7 %**, todos y solo ellos |
| decisiones jugadas | 1.446 (**4,82 por duelo**) |
| aciertos | 32,8 % (contestando a ciegas y rotando opción) |
| aciertos que MOVIERON el marcador | **311** |
| duelos que acabaron con un marcador distinto al simulado | **58,3 %** |

**Ese 58,3 % es la respuesta a la queja que originó todo esto.** Antes era 0 %: el
resultado venía dado. La distribución de márgenes también es sana —compárala con el
**88,8 % en margen 1** que el bucle del §1.3 producía (§15.4e)—.

> **El 27,7 % de tandas** significa que más de uno de cada cuatro duelos se decide
> en los penaltis. Cuando esto se midió la tanda todavía no se jugaba, y fue el
> argumento que llevó a hacerla interactiva: **ya se juega** (§15.11).

#### El coste aceptado, con número

En los 300 duelos con mazos REALES el reparto fue **72 % / 28 %** — el mazo fuerte
gana, pero el flojo gana más de uno de cada cuatro. La cifra que sigue en pie como
aviso es la del caso extremo sintético: con 240 contra 100,
**el favorito pasa del 69,1 % al 91,0 %** de victorias.
Está medido y **Alejandro lo aceptó a cambio de que los minijuegos cuenten**. No
hay forma de tener a la vez el equilibrio de antes, marcadores con forma de fútbol
y que el partido decida: aplanar la conversión de goles para que cuadre con el Elo
da 52 % de empates y 0,84 goles por partido, que no es fútbol. **Las palancas para
recalibrarlo son `duelo_k` y, ahora, `partido_presupuesto_marcador`.**

#### El cliente tuvo que cambiar, y aquí está la trampa

La pantalla de resultado la **renderiza el servidor**, y con el duelo en `en_juego`
se renderiza **sin ganador**. Destaparla al terminar enseñaría *"Partido en
juego"* donde debería decir Victoria o Derrota. Solución: al llegar a `fase:
final`, el cliente **recarga a `duelo.php?id=X&revelar=1`**, que trae la pantalla
de verdad ya destapada, con su animación y el foco en el veredicto (lo que hace
que un lector de pantalla lo anuncie).

- `data-decidido` dice si la pantalla de debajo sirve; `d.decidido` del sondeo
  dice si el servidor ya escribió el ganador. **Se exigen las dos** para no poder
  entrar en un bucle de recargas si la liquidación no llegó a completarse.
- **Recargar a mitad de partido te reincorpora al partido**, no te deja fuera: el
  minuto lo manda el servidor, así que `$ceremonia = $enJuego || ?nuevo`.
- En `duelo.php` hay que distinguir **`$jugado`** (el partido existe: alineaciones
  y compos congeladas, hay marcador) de **`$resuelto`** (hay ganador y el bote ya
  se entregó). Confundirlos es el error fácil de esa pantalla.
- El sondeo de la fase de aumento comprobaba `estado === 'resuelto'` para pasar al
  partido. Con el estado nuevo **dejaba a los dos jugadores esperando** hasta que
  el reloj llegase a cero, con el partido ya montado al otro lado.
- Un empate con la palabra "Victoria" al lado **se lee como un error del juego**,
  así que la tanda se dice en el titular (`.partido-tanda`), en el veredicto, en el
  resumen compartible (`(pen.)`) y en el listado de duelos.

#### Qué NO cambió

**Las cadenas (PvE) están intactas**, y tienen prueba propia para demostrarlo
porque el cambio pasa justo por dentro de `resolverDuelo()`: no tienen minijuegos,
así que no hay nada que esperar y se siguen resolviendo de una vez, con su rango y
su botín en el acto.

### 15.11 LA TANDA DE PENALTIS SE JUEGA (migración `024`)

**La regla entera, tal como la pidió Alejandro:** la portería se divide en **cuatro
huecos**, tirador y portero eligen uno cada uno, y **si coinciden es parada; si no,
gol**. Ni Ataque ni Portería entran en la cuenta.

Vino de un número: el §15.10 midió que **el 27,7 % de los duelos acaba empatado**,
o sea que más de uno de cada cuatro se decidía en algo que el jugador no llegaba a
ver. Era el agujero más grande que quedaba en el partido.

Dos propiedades bonitas que salen gratis de esa regla:

- **Con los dos eligiendo a ciegas, entra el 75 % de los penaltis** (1 de cada 4
  coincide). Es casi exactamente el porcentaje del fútbol real, sin haber ajustado
  ningún número.
- **Es el único sitio del juego donde el mazo no importa.** Un mazo flojo que
  empata tiene la tanda al 50 %, así que compensa en parte el 91 % del favorito que
  el §15.10 dejó como coste aceptado.

#### ⚠️ ES LA PRIMERA INTERACCIÓN SIMULTÁNEA DEL JUEGO

Y eso rompe el supuesto sobre el que está construido todo lo demás. En un minijuego
el dato oculto **sale de las cartas**: el servidor lo recalcula cuando quiere, y por
eso la narración entera es función de `valor_sorteo` y no hace falta guardar nada.

Aquí el dato oculto es **lo que el otro jugador está eligiendo en este momento**, que
no se deriva de nada. De ahí la tabla `duelo_penaltis`: hay que guardarlo porque no
se puede reconstruir. Si añades algo parecido, esa es la pregunta que decide si
necesitas tabla o no.

Tres reglas que hay que respetar al tocar esto:

| | |
|---|---|
| **La elección del rival NO viaja al cliente** hasta que el tiro se resuelve (§6.3) | Del tiro en curso, `tandaParaCliente()` solo dice si **yo** ya elegí. Verla sería ganar siempre. Hay prueba específica de que no se filtra, ni en el payload ni en el sondeo |
| **La idempotencia va en el SQL, no en PHP** | La PK `(id_duelo, ronda, turno)` y el `zona_X IS NULL` dentro del UPDATE. Los dos jugadores sondean a la vez |
| **El plazo tiene que resolver solo** | Si alguien se va a mitad de tanda, el bote de los dos está retenido. `tanda_plazo_seg` (12 s) y auto-tiro determinista |

> ⚠️ **El endpoint tampoco puede contestar con la zona del rival**, ni cuando el
> tiro se resuelve en esa misma petición. Si lo hiciera, el que eligiera SEGUNDO se
> enteraría antes que el otro — y en una tanda simultánea eso es toda la ventaja del
> mundo. El resultado se lee del sondeo, igual para los dos.

#### Cómo está montado

- **`tandaEstado()`** — deriva TODO de las filas guardadas: marcador, de quién es el
  turno, si ya hay ganador. No guarda marcador propio, así que dos sondeos
  simultáneos no pueden discrepar y recargar la página no pierde nada.
- **`tandaAvanzar($id, $forzar)`** — el motor perezoso: abre el tiro que toca, aplica
  el plazo y resuelve. Con `$forzar` decide la tanda entera sin esperar, que es lo
  que usa el cierre por abandono.
- **`tirarPenalti()`** — registra una elección. `resolverTiro()` cierra el tiro con
  la regla dentro del UPDATE: `gol = IF(zona_tirador = zona_portero, 0, 1)`.
- **`tandaParaCliente()`** — el payload, con el filtro de §6.3.
- **Quién tira primero sale del sorteo**, no es siempre el creador: tirar primero es
  una ventaja real en una tanda.
- **Corte anticipado**: en cuanto uno no puede alcanzar al otro ni marcando todo lo
  que le queda, se acabó. Sin eso un 3-0 seguiría lanzando hasta el quinto.
- **Muerte súbita** desde el tiro 6, y solo decide con la ronda **completa**.
- **Tope de 25 rondas** por bando: sin él una muerte súbita podría no acabar nunca y
  el duelo se quedaría con el bote dentro.

#### Lo que se retiró

**`tandaDePenaltis()` (la automática) se borró**, mismo criterio que
`cabeCambioMarcador()`: si siguiera ahí, alguien volvería a llamarla y el duelo se
decidiría sin que el jugador tocase nada, que es exactamente lo que este trabajo vino
a quitar. Su papel de red lo hace ahora el auto-tiro por plazo, que es **una sola
mecánica** en vez de dos que pueden discrepar.

#### La forma nueva de dejar un duelo colgado

Al hacer la tanda jugable apareció una: **partido empatado + tanda a medias + nadie
que vuelva**. La cubre `cerrarConTandaSiHace()`, que fuerza la tanda antes de
liquidar. Es la tercera rama de abandono, y por el mismo motivo que las dos del
§15.10: con el duelo decidiéndose en el campo, un partido a medias es un bote que no
vuelve a nadie.

> **Esto lo cazó la prueba, no el razonamiento.** Al añadir la tanda, los 300 duelos
> pasaron a dejar **73 colgados** — exactamente los que acababan en empate— porque el
> guion de prueba no sabía jugar la tanda. La cuenta cuadró al céntimo (69 duelos de
> monedas × 20 = 1.380 retenidos), y eso confirmó que el motor hacía lo correcto y
> que era la prueba la que se había quedado corta. **Si vuelves a tocar el partido,
> comprueba que el guion cubre TODAS las fases**: una fase nueva sin cubrir se ve
> como duelos colgados, no como un error.

### 15.12 LLEVAR EL PARTIDO A LAS CADENAS — diseñado y decidido, SIN CONSTRUIR

Decisiones de Alejandro del **2026-08-11**. Está todo decidido y medido; lo que
falta es escribirlo. **Léelo entero antes de tocar cadenas.**

#### Las cuatro decisiones

1. **El partido decide también en PvE, y los minijuegos influyen en las
   recompensas.** Es el objetivo, no un efecto colateral: ahí vive el problema
   original de la Biblia (§0.2, *"simular hasta ganar"*).
2. **Solo en las recompensas DE ESE PARTIDO.** Textual: *"quiero que influya solo a
   las recompensas de ese partido, no del cofre"*.
3. **El contenido de los cofres es FIJO.** *"Los cofres vendrán marcados el
   contenido que tiene."* Esto **ya es verdad y no hay que tocar nada**:
   `reclamarCofre()` llama a `otorgarLootNodo()` con `$rango = null`, así que solo
   entran las filas de `cadena_loot` **sin** `rango_minimo`. Los cofres nunca han
   puntuado.
4. **Bonus del cofre por camino perfecto:** *"si tienes todos los partidos previos
   al cofre en S, una mejor recompensa"*, y la S tiene que ser **en la dificultad
   más alta**, o sea **`extremo`**.
5. **El rango NO se recalibra ahora.** *"Cuando se tengan las cartas definitivas y
   se creen las cadenas, se testeará que sea posible manualmente partido a partido
   y que sea balanceado."* Se construye el mecanismo, se dejan los diales
   (`pve_rango_s_goles`, `pve_rango_a_margen`) y se reporta la distribución medida.
   **No inventar una fórmula de rango nueva.**

#### El bonus del cofre sale sin tocar el esquema, y esto es lo bonito

`cadena_progreso.mejor_rango` ya se guarda **por (usuario, nodo, dificultad)**, y
`cadena_loot` ya tiene `rango_minimo`. Así que:

- se calcula si **existe un camino** de raíz al cofre en el que todos los nodos
  `partido` tienen `mejor_rango = 'S'` **en `extremo`** para ese usuario;
- si lo hay, `reclamarCofre()` pasa `$rango = 'S'` en vez de `null`;
- el premio extra se declara en `cadena_loot` con `rango_minimo = 'S'`, **como
  cualquier otro loot**.

Ni tabla nueva ni mecanismo nuevo. Una función y un parámetro.

> **Por qué "existe un camino" y no "todos los nodos anteriores":** `mapaCadena()`
> da un nodo por disponible si **CUALQUIERA** de sus predecesores está superado
> (`break` en el primero, [consultas.php:6654](db/consultas.php:6654)). O sea que las
> cadenas se recorren **eligiendo camino**, no completándolo todo. Exigir todos los
> ancestros obligaría a jugar las dos ramas de una bifurcación, que no es cómo se
> llega al cofre.

> ⚠️ **Por qué la S tiene que ser en `extremo`, y no "en cualquier dificultad".**
> `mejor_rango` es por dificultad y **las cinco dificultades están siempre
> disponibles** (`cadena.php` las ofrece todas, sin desbloqueo). Con "cualquier
> dificultad" se podría **granjear la S entera en Fácil** y reclamar el cofre con el
> premio bueno. Decisión de Alejandro: `extremo`. Déjalo en una función suya para
> que cambiarlo siga siendo una línea.

#### Lo que hay que construir, en orden

| | qué | por qué |
|---|---|---|
| **1** | `resolverDuelo()` deja el PvE en `en_juego`, simulación **natural**, sin rango ni botín. Se jubila **`marcadorCadena()`** | el rango tiene que salir del partido jugado |
| **2** | progreso de nodo, monedas y loot **del partido** se mueven a `liquidarPartido()` | es lo que hace que los minijuegos cuenten |
| **3** | el bonus del cofre (arriba) | decisión 4 |
| **4** | `duelo.php` pasa de `'clasico'` a `'narrado'` en cadenas | ver los tres avisos de abajo |
| **5** | la tanda contra el CPU: auto-tiro **inmediato** en vez de a los 12 s | decisión 1 aplicada al empate |

#### Tres cosas que muerden en la pieza 4, y no son obvias

- **El CPU no debe recibir decisiones.** `duenosDeMinijuego()` mira el duelo desde
  los DOS lados. En PvE eso le daría minijuegos al bot que nadie va a jugar, y cada
  uno **pausaría tu partido 9 segundos** hasta que venciera el plazo.
- **El reloj no arrancaría.** `arrancarPartidoSiToca()` espera a que **los dos** hayan
  latido, y **el CPU no late nunca**, así que solo arrancaría por `partido_espera_seg`
  — 15 segundos de espera antes de cada partido de cadena.
- **El botón "Ver resultado"** existe en PvE porque ahí el resultado ya estaba
  decidido. Si el partido decide, saltarlo deja de tener sentido, igual que pasó en
  PvP (§15.7).

#### Medido antes de empezar, para no suponerlo

| | cadenas hoy | duelos PvP |
|---|---|---|
| goles por partido | **5,65** | 2,10 |
| portería a cero | 17,2 % | — |
| marcadores típicos | 4-3, 5-3, 5-4 | 1-0, 2-1 |

`marcadorCadena()` **no se puede narrar**: un 5-4 minuto a minuto son nueve goles sin
una sola parada.

> ⚠️ **CONSECUENCIA QUE HAY QUE SABER ANTES DE PROBARLO.** Con `pve_rango_s_goles = 5`
> y `pve_rango_a_margen = 3`, al bajar de 5,65 a ~2,1 goles **el rango S deja de ser
> alcanzable en la práctica**. Y como el bonus del cofre exige S en todos los
> partidos, **el bonus también será inalcanzable hasta que se calibre**. No está
> roto: está sin calibrar, y calibrarlo es la decisión 5.

#### Dos cosas que NO hacen falta, y conviene saberlo antes de presupuestar

- **No hay que darle inteligencia al CPU para los minijuegos.** El dato oculto sale
  de las **CARTAS**, no de lo que elija el rival, así que el jugador lee el perfil
  del delantero del CPU igual que leería el de una persona.
- **La tanda contra el CPU ya está construida**: es el auto-tiro por plazo de
  §15.11, determinista desde `valor_sorteo` (que no sale del servidor, así que no se
  puede adivinar). Aplicándolo al instante en vez de a los 12 s ya hay penaltis
  contra la máquina — y sesgarlo hacia donde tira el jugador es una palanca de
  dificultad gratis (Biblia §3).

#### Y una corrección a un supuesto de partida

Hoy el CPU **no tiene cartas imposibles**. Sus 132 huecos apuntan a **38 cromos
normales** del catálogo; lo que tiene son **multiplicadores** (`pve_mult_*`,
`pve_compos_mult_*`: en Extremo ×1,063 a la fuerza y ×1,339 a las compos).

Alejandro quiere darle rasgos que un jugador no podría tener (*"tierra y montaña al
3"*), y eso hay que **MEDIRLO** al hacerlo: los minijuegos leen el perfil
**RELATIVO** de la carta para elegir el dato oculto, así que con cartas muy extremas
la pista puede pasar a resolver el minijuego sola. El verificador ya mide ese número
(hoy: a ciegas 33 %, leyendo 44 %) — **ejecútalo después de meter esas cartas.**

---

## 16. Importador de datos oficiales (diseño aprobado, sin construir)

> Fase 3, trabajo independiente y en paralelo a lo que se construye en otras
> sesiones — no lo toca. Diseño y plan aprobados por Alejandro el 2026-08-07.

### 15.0 Objetivo

Alejandro sube manualmente el `datos_oficiales.json` que exporta la web de la
Superliga Frontier desde el panel de administración. El importador crea, en
una expansión que él elige, las cartas de **todos los jugadores actualmente
en plantilla de algún equipo**, más las cartas de escudo/entrenador/gerente de
esos equipos. Nunca se ejecuta solo: siempre es una acción manual, con
previsualización antes de escribir nada en la base de datos.

**Fuera de alcance:** agentes libres del JSON (`agentes_libres`), `PRESIDENTE`
(el JSON no trae ese dato), edición masiva de cartas ya creadas, y cualquier
otro trabajo en curso en paralelo.

### 15.1 Dónde vive

- **Página nueva:** `panel/importar.php`, mismo patrón que `panel/cromos.php` /
  `panel/expansiones.php` (guard de `$_SESSION['dictador']`, `admin.css`,
  Bootstrap Icons, reutiliza `.field`, `.admin-table`/`.admin-table-wrap`,
  `.alert-*`, `.modal-footer` ya existentes — sin CSS nuevo). Entrada nueva en
  `panel/navbar.php`.
- **Lógica de datos:** todo en `Tcg` (`db/consultas.php`), sección nueva
  `IMPORTACIÓN DATOS OFICIALES`. No se crean clases nuevas.
- **Self-check aislado:** `db/test_importar_datos_oficiales.php`, un script de
  CLI con `assert`-a-mano sobre las funciones puras (mapeo de posición y
  afinidad, emparejamiento de equipos, ranking de rareza, stats), sin arrancar
  el panel ni depender de datos concretos en la BD.

### 15.2 Flujo (wizard de dos pasos, con `$_SESSION`)

**Paso 1 — Subir y previsualizar** (sin escribir en BD): formulario con
`<input type="file">` + selector de expansión destino
(`listarExpansiones()`; si la expansión que quiere no existe, la crea antes
desde `expansiones.php`, este importador no crea expansiones). Al subir se
decodifica el JSON, se guarda en `$_SESSION['import_datos']` /
`$_SESSION['import_id_expansion']` para el paso 2, y se muestra sin tocar la
BD:
- Nº de jugadores a crear / omitidos (ya existentes en esa expansión).
- Equipos: cuántos matchean exacto, cuántos no tienen ningún parecido (se
  crearán automáticamente), y una tabla de **equipos ambiguos** —similitud de
  texto alta pero no exacta, ej. "Instituto Kirkwood" del JSON vs "Instituto
  Kikrwood" de la BD— con un selector por fila: usar nombre del JSON / usar
  nombre de la BD / escribir uno distinto.
- Jugadores con afinidad no reconocida (nulos, texto suelto, URLs por error
  de datos en el JSON) → cuenta, irán como "no-afi".
- Nº de cartas de escudo/entrenador/gerente a crear.

**Paso 2 — Confirmar y crear**: resuelve los equipos ambiguos con lo elegido
en el paso 1, crea los equipos nuevos que hagan falta, crea las cartas de
jugador (con foto descargada y optimizada) y las de escudo/entrenador/gerente,
llama a `derivarRasgosConfiguracion()` una sola vez al final (no por carta,
igual que hace `panel/cromos.php`), limpia la sesión y muestra el resumen
(creadas / omitidas / equipos nuevos / fotos que fallaron al descargar). Si
falla la descarga de una foto puntual, la carta se crea igual con `imagen`
vacío y se lista como aviso — no bloquea el resto del lote.

### 15.3 Mapeo de datos

| JSON | Columna en `cromos` | Regla |
|---|---|---|
| `nombre` (jugador) | `nombre` | tal cual |
| `posicion` | `posicion` | `POR→POR`, `DEF→DF`, `MED→MC`, `DEL→DC` |
| `afinidad` | `id_afinidad` | `Fuego→2`, `Bosque→4`, `Montaña`/`montaña`→1, **`Aire→3` (Viento)**; cualquier otro valor (nulo, texto no reconocido, URL filtrada por error del JSON) → `5` (no-afi) |
| equipo del jugador | `id_equipo` | exacto (normalizado) → usa el existente; similar → lo elegido en el paso 1; sin parecido → crea fila nueva en `equipos` |
| `foto` (URL cloudfront) | `imagen` | se descarga con GD, se convierte a WebP, se guarda en `assets/img/Cromos/Importados/<slug-equipo>/<slug-jugador>.webp`; si falla, `imagen = ''` |
| — | `descripcion` | `''` |
| — | `id_expansion` | la elegida en el paso 1 |
| — | `cupo_numerado` | `NULL` |

Cartas de equipo (una por campo no vacío, por cada equipo con jugadores
importados):

| Tipo | `posicion` | `nombre` | `imagen` | `id_rareza` | `id_afinidad` |
|---|---|---|---|---|---|
| Escudo | `ESCUDO` | `"Escudo {equipo}"` | `''` | 5 (Legendario) | 5 (no-afi) |
| Entrenador | `ENT` | valor de `entrenador` | `''` | 5 (Legendario) | 5 (no-afi) |
| Gerente | `GER` | valor de `gerente` | `''` | 5 (Legendario) | 5 (no-afi) |

### 15.4 Equipos: coincidencia difusa

Para cada equipo del JSON con jugadores a importar, comparar su nombre
(normalizado: minúsculas, con las vocales acentuadas y la "ñ" sustituidas a
mano antes de pasar por `iconv(...TRANSLIT...)` —el `iconv` de PHP en Windows
no translitera bien en todos los builds—, y con cualquier carácter que no sea
alfanumérico o espacio eliminado al final, trim) contra los nombres
existentes en `equipos`:
- **Coincidencia exacta tras normalizar** → usa ese `id_equipo`, sin
  preguntar.
- **Similar pero no exacta** (`similar_text()` ≥ 90%) → se lista en el paso 1
  para que confirmes cuál de los dos nombres es el correcto (o escribas uno
  distinto). El umbral subió de 75% a 90% tras un falso positivo real:
  "Instituto Occult" y "Instituto Otaku" —dos equipos DISTINTOS del
  catálogo— daban 77,4% solo por compartir el prefijo "Instituto ", mientras
  que los typos genuinos ("Instituto Kirkwood"/"Kikrwood", "Inazuma Kids
  FC"/"CF") dan 93-94%. Caso cubierto en el self-check.
- **Sin ningún parecido** → se crea automáticamente un `equipo` nuevo con el
  nombre del JSON, sin preguntar.

### 15.5 Rareza

**Base:** `titular === true` → Poco común (`id_rareza=2`); resto → Común
(`id_rareza=1`).

**Promoción** (después de la base; el valor más alto gana si un jugador cae en
varias listas): tres rankings independientes, top 1-3 → Épico (`id_rareza=4`),
top 4-10 → Raro (`id_rareza=3`). En cada ranking solo entran jugadores con más
de 0 puntos (goles, o goles+asistencias en el caso del "mejor jugador de cada
equipo") — sin este filtro, una lista con pocos candidatos promocionaría por
pura posición a alguien sin mérito real. (Ajuste hecho durante la Tarea 5, al
escribir el self-check, sobre el diseño original de este apartado.)
1. Goleadores de la temporada anterior cerrada (`historial_temporadas`, la
   entrada `"Temporada " . (temporada_actual - 1)`), solo si el jugador sigue
   en la plantilla actual de algún equipo.
2. Goleadores actuales (`goles` de la temporada en curso).
3. Mejor jugador de cada equipo actual (mayor `goles + asistencias` de su
   plantilla), todos los "mejores" rankeados juntos.

### 15.6 Stats de combate

Hoy el panel deja `ataque`/`defensa`/`tecnica` a 0 (el formulario de
`cromos.php` no las expone). Para que las cartas importadas pesen en combate:

```
BASE_TOTAL = ['comun' => 165, 'poco_comun' => 190, 'raro' => 215, 'epico' => 240]
// (la promoción de jugador nunca pasa de Épico; Legendario es solo para
// escudo/entrenador/gerente, que van con 0/0/0 y no entran aquí)
// jitter aleatorio ±8% sobre el total antes de repartir

SPLIT_POR_POSICION = [ // fracciones de BASE_TOTAL, suman 1.0
  'POR' => ['ataque' => 0.20, 'defensa' => 0.45, 'tecnica' => 0.35],
  'DF'  => ['ataque' => 0.25, 'defensa' => 0.45, 'tecnica' => 0.30],
  'MC'  => ['ataque' => 0.33, 'defensa' => 0.30, 'tecnica' => 0.37],
  'DC'  => ['ataque' => 0.45, 'defensa' => 0.25, 'tecnica' => 0.30],
]

stat = round(BASE_TOTAL[rareza] * SPLIT_POR_POSICION[posicion][stat] * jitter)
clamp cada stat entre 1 y 99
```

`BASE_TOTAL` sale de promediar las stats reales ya existentes en `cromos` por
rareza (común ~165-170, poco común ~190, raro ~210-215, épico ~235-245 de
suma; consulta hecha sobre la BD real el 2026-08-07). El reparto por posición
sigue el mismo sesgo que ya muestran los datos reales (portero fuerte en
defensa, delantero fuerte en ataque).

`ponytail:` heurística sin playtesting dedicado, aproximando lo que ya hay en
el catálogo — no es un sistema derivado del documento de balance. Retocar
`BASE_TOTAL`/`SPLIT_POR_POSICION` si el balance no cuadra al jugar.

### 15.7 Reimportar / idempotencia

Antes de crear una carta (de jugador o de equipo) se comprueba si ya existe
una fila en `cromos` con el mismo `nombre` + `id_equipo` + `id_expansion`
elegida (`existeCromoImportado()`). Si existe, se omite. Así se puede volver a
subir el archivo (tras una jornada nueva, por ejemplo) sin duplicar todo el
catálogo, mientras se elija la misma expansión.

### 15.8 Errores y casos límite

- **Fotos que no se pueden descargar**: no bloquean el lote; la carta se crea
  con `imagen=''` y se lista como aviso en el resumen final.
- **JSON mal formado o sin la clave `equipos`**: el paso 1 falla con un
  mensaje claro, no se guarda nada en sesión.
- **`entrenador`/`gerente` vacío**: no se crea esa carta para ese equipo.
- **Equipo con 0 jugadores en el JSON** (hoy: Raimon, Oscuridad Ancestral,
  Ragnah): no se crea ni el equipo ni sus cartas de
  escudo/entrenador/gerente.
- **Tamaño de subida**: el JSON pesa ~1.1 MB; si `upload_max_filesize` /
  `post_max_size` de XAMPP lo bloquean, hay que subirlos a mano en `php.ini`
  (no se toca automáticamente).
- **Descarga de 500+ fotos**: puede tardar; el paso 2 corre con
  `set_time_limit(0)`.

### 15.9 Plan de implementación

> REQUIRED SUB-SKILL para ejecutar: `superpowers:subagent-driven-development`
> (recomendado) o `superpowers:executing-plans`. Pasos con checkbox `- [ ]`
> para seguimiento.

**Restricciones globales:** sin dependencias nuevas de npm (no aplica, es
PHP puro); sin clases nuevas, todo en `Tcg`; sin CSS nuevo en `admin.css`,
reutilizar lo existente; nombres de funciones y comentarios en español; PDO
preparado siempre; `htmlspecialchars()` en toda salida a HTML.

---

#### Task 1 — Extender `crearCromo()` con stats de combate

**Archivos:** Modifica `db/consultas.php:220-237` (`crearCromo`).

**Interfaces:**
- Produce: `crearCromo($nombre, $posicion, $descripcion, $imagen, $id_expansion, $id_equipo, $id_rareza, $id_afinidad, $ataque = 0, $defensa = 0, $tecnica = 0)` — compatible con la llamada actual de `panel/cromos.php` (no le pasa esos tres últimos, quedan a 0).

- [ ] **Paso 1:** Editar la firma y el `INSERT` para incluir `ataque`, `defensa`, `tecnica` con default `0`:

```php
public function crearCromo($nombre, $posicion, $descripcion, $imagen, $id_expansion, $id_equipo, $id_rareza, $id_afinidad, $ataque = 0, $defensa = 0, $tecnica = 0) {
    $sql = "
        INSERT INTO cromos (nombre, posicion, descripcion, imagen, id_expansion, id_equipo, id_rareza, id_afinidad, ataque, defensa, tecnica)
        VALUES (:nombre, :posicion, :descripcion, :imagen, :id_expansion, :id_equipo, :id_rareza, :id_afinidad, :ataque, :defensa, :tecnica)
    ";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
        ":nombre" => $nombre,
        ":posicion" => $posicion,
        ":descripcion" => $descripcion,
        ":imagen" => $imagen,
        ":id_expansion" => $id_expansion,
        ":id_equipo" => $id_equipo,
        ":id_rareza" => $id_rareza,
        ":id_afinidad" => $id_afinidad,
        ":ataque" => $ataque,
        ":defensa" => $defensa,
        ":tecnica" => $tecnica,
    ]);
    return $this->pdo->lastInsertId();
}
```

- [ ] **Paso 2:** Verificar que `panel/cromos.php` sigue creando cromos sin
  error (`C:/xampp/php/php.exe -l db/consultas.php panel/cromos.php`, y crear
  un cromo de prueba desde el panel con la cuenta `Claude`).
- [ ] **Paso 3:** Commit.

```bash
git add db/consultas.php
git commit -m "Añade stats de combate opcionales a crearCromo()"
```

---

#### Task 2 — Helpers puros de mapeo (posición, afinidad, texto)

**Archivos:** Modifica `db/consultas.php`, nueva sección al final de la
clase (antes del `}` que cierra `Tcg`, hoy línea 3971).

**Interfaces:**
- Produce: `normalizarTexto(string $s): string`, `mapearPosicionJugador(string $pos): ?string`, `mapearAfinidadJugador(?string $nombre): int`, `slugImportado(string $texto): string`.

- [ ] **Paso 1:** Añadir la sección con las constantes y las cuatro funciones:

```php
	// ==========================================================
	// IMPORTACIÓN DATOS OFICIALES
	// ==========================================================

	private const IMPORT_POSICIONES = ['POR' => 'POR', 'DEF' => 'DF', 'MED' => 'MC', 'DEL' => 'DC'];
	private const IMPORT_AFINIDADES = ['fuego' => 2, 'bosque' => 4, 'aire' => 3, 'viento' => 3, 'montana' => 1];

	// Minúsculas, sin tildes, sin espacios repetidos — para comparar nombres
	// de equipo y claves de afinidad sin que un acento o una mayúscula rompa
	// el match (el JSON oficial mezcla "Aire"/"aire", "Montaña"/"montaña"...).
	// (Versión revisada en la Tarea 5: el iconv de PHP en Windows no
	// transliteraba de forma fiable en todos los builds, así que las vocales
	// acentuadas y la "ñ" se sustituyen a mano antes del iconv, y un
	// preg_replace final limpia cualquier carácter que no sea alfanumérico o
	// espacio, por si el iconv deja algo suelto.)
	public function normalizarTexto(string $s): string {
		$s = trim(mb_strtolower($s, 'UTF-8'));
		$s = str_replace(['ñ', 'á', 'é', 'í', 'ó', 'ú', 'à', 'è', 'ì', 'ò', 'ù'],
		                   ['n', 'a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u'], $s);
		$translit = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
		if ($translit !== false) { $s = $translit; }
		$s = preg_replace('/[^a-z0-9\s]/i', '', $s);
		return preg_replace('/\s+/', ' ', $s);
	}

	public function mapearPosicionJugador(string $pos): ?string {
		$pos = strtoupper(trim($pos));
		return self::IMPORT_POSICIONES[$pos] ?? null;
	}

	public function mapearAfinidadJugador(?string $nombre): int {
		if ($nombre === null || trim($nombre) === '') { return 5; } // no-afi
		return self::IMPORT_AFINIDADES[$this->normalizarTexto($nombre)] ?? 5;
	}

	public function slugImportado(string $texto): string {
		$slug = preg_replace('/[^a-z0-9]+/', '-', $this->normalizarTexto($texto));
		$slug = trim($slug, '-');
		return $slug !== '' ? $slug : 'x';
	}
```

- [ ] **Paso 2:** `C:/xampp/php/php.exe -l db/consultas.php`.
- [ ] **Paso 3:** Commit.

```bash
git add db/consultas.php
git commit -m "Añade helpers puros de mapeo para el importador de datos oficiales"
```

---

#### Task 3 — Emparejamiento y resolución de equipos

**Archivos:** Modifica `db/consultas.php`, dentro de la sección de la Tarea 2.

**Interfaces:**
- Consume: `normalizarTexto()` (Tarea 2).
- Produce: `emparejarEquipo(string $nombreJson, array $equiposExistentes): array` (`$equiposExistentes` = salida de `listarEquipos()`), `resolverEquipos(array $equiposJson, array $equiposExistentes, array $decisiones): array` (devuelve `[id_json_equipo => id_equipo]`).

- [ ] **Paso 1:** Añadir ambas funciones:

```php
	public function emparejarEquipo(string $nombreJson, array $equiposExistentes): array {
		$normJson = $this->normalizarTexto($nombreJson);
		$mejor = null;
		$mejorPct = 0.0;
		foreach ($equiposExistentes as $eq) {
			if ($this->normalizarTexto($eq['nombre']) === $normJson) {
				return ['estado' => 'exacto', 'id_equipo' => (int) $eq['id_equipo'], 'nombre' => $eq['nombre']];
			}
			similar_text($normJson, $this->normalizarTexto($eq['nombre']), $pct);
			if ($pct > $mejorPct) { $mejorPct = $pct; $mejor = $eq; }
		}
		// Umbral revisado tras un caso real: "Instituto Occult" (nuevo) y
		// "Instituto Otaku" (existente) son equipos distintos pero daban 77,4%
		// solo por compartir "Instituto "; los typos genuinos dan 93-94%.
		if ($mejor !== null && $mejorPct >= 90.0) {
			return [
				'estado' => 'ambiguo',
				'nombre_json' => $nombreJson,
				'candidato_db' => ['id_equipo' => (int) $mejor['id_equipo'], 'nombre' => $mejor['nombre']],
				'porcentaje' => round($mejorPct, 1),
			];
		}
		return ['estado' => 'nuevo', 'nombre' => $nombreJson];
	}

	// $decisiones: [id_json_equipo => ['eleccion' => 'json'|'db'|'otro', 'texto' => string|null]],
	// solo hace falta para los equipos que salieron 'ambiguo' en emparejarEquipo().
	public function resolverEquipos(array $equiposJson, array $equiposExistentes, array $decisiones): array {
		$mapa = [];
		$cacheNombreAId = [];
		foreach ($equiposExistentes as $eq) {
			$cacheNombreAId[$this->normalizarTexto($eq['nombre'])] = (int) $eq['id_equipo'];
		}

		foreach ($equiposJson as $equipo) {
			$match = $this->emparejarEquipo($equipo['nombre'], $equiposExistentes);
			$idExistente = null;
			$nombreFinal = null;

			if ($match['estado'] === 'exacto') {
				$idExistente = $match['id_equipo'];
			} elseif ($match['estado'] === 'ambiguo') {
				$decision = $decisiones[$equipo['id']] ?? ['eleccion' => 'db', 'texto' => null];
				if ($decision['eleccion'] === 'db') {
					$idExistente = $match['candidato_db']['id_equipo'];
				} elseif ($decision['eleccion'] === 'json') {
					$nombreFinal = $match['nombre_json'];
				} else {
					$nombreFinal = trim((string) $decision['texto']) !== '' ? $decision['texto'] : $match['nombre_json'];
				}
			} else {
				$nombreFinal = $match['nombre'];
			}

			if ($idExistente !== null) {
				$mapa[$equipo['id']] = $idExistente;
				continue;
			}

			$clave = $this->normalizarTexto($nombreFinal);
			if (isset($cacheNombreAId[$clave])) {
				$mapa[$equipo['id']] = $cacheNombreAId[$clave];
				continue;
			}

			$stmt = $this->pdo->prepare("INSERT INTO equipos (nombre) VALUES (:nombre)");
			$stmt->execute([':nombre' => $nombreFinal]);
			$nuevoId = (int) $this->pdo->lastInsertId();
			$cacheNombreAId[$clave] = $nuevoId;
			$mapa[$equipo['id']] = $nuevoId;
		}

		return $mapa;
	}
```

- [ ] **Paso 2:** `C:/xampp/php/php.exe -l db/consultas.php`.
- [ ] **Paso 3:** Commit.

```bash
git add db/consultas.php
git commit -m "Añade emparejamiento y resolución de equipos al importador"
```

---

#### Task 4 — Ranking de rarezas y fórmula de stats

**Archivos:** Modifica `db/consultas.php`, misma sección.

**Interfaces:**
- Produce: `rankearRarezasImportacion(array $datosJson): array` (devuelve `['id_json_equipo|nombre_jugador' => id_rareza]`, solo para promocionados), `statsBaseImportacion(string $posicion, int $idRareza): array` (devuelve `['ataque'=>int,'defensa'=>int,'tecnica'=>int]`).

- [ ] **Paso 1:** Añadir ambas funciones:

```php
	private const IMPORT_BASE_TOTAL = ['comun' => 165, 'poco_comun' => 190, 'raro' => 215, 'epico' => 240];
	private const IMPORT_RAREZA_CLAVE = [1 => 'comun', 2 => 'poco_comun', 3 => 'raro', 4 => 'epico'];
	private const IMPORT_SPLIT_POSICION = [
		'POR' => ['ataque' => 0.20, 'defensa' => 0.45, 'tecnica' => 0.35],
		'DF'  => ['ataque' => 0.25, 'defensa' => 0.45, 'tecnica' => 0.30],
		'MC'  => ['ataque' => 0.33, 'defensa' => 0.30, 'tecnica' => 0.37],
		'DC'  => ['ataque' => 0.45, 'defensa' => 0.25, 'tecnica' => 0.30],
	];

	// Tres rankings independientes (goleadores temporada anterior, goleadores
	// actuales, mejor jugador por equipo); top 1-3 -> Épico, top 4-10 -> Raro.
	// Si un jugador cae en varias listas, se queda con la rareza más alta.
	public function rankearRarezasImportacion(array $datosJson): array {
		$resultado = [];

		$ubicacionActual = [];
		foreach ($datosJson['equipos'] as $equipo) {
			foreach ($equipo['jugadores'] ?? [] as $jugador) {
				$ubicacionActual[$jugador['nombre']] = $equipo['id'];
			}
		}

		$aplicarRanking = function (array $lista) use (&$resultado, $ubicacionActual) {
			usort($lista, fn($a, $b) => $b['puntos'] <=> $a['puntos']);
			foreach ($lista as $i => $item) {
				if (!isset($ubicacionActual[$item['nombre']])) { continue; } // ya no juega
				$idRareza = $i < 3 ? 4 : ($i < 10 ? 3 : null);
				if ($idRareza === null) { continue; }
				$clave = $ubicacionActual[$item['nombre']] . '|' . $item['nombre'];
				if ($idRareza > ($resultado[$clave] ?? 0)) { $resultado[$clave] = $idRareza; }
			}
		};

		$actuales = [];
		foreach ($datosJson['equipos'] as $equipo) {
			foreach ($equipo['jugadores'] ?? [] as $jugador) {
				$actuales[] = ['nombre' => $jugador['nombre'], 'puntos' => (int) ($jugador['goles'] ?? 0)];
			}
		}
		$aplicarRanking($actuales);

		$numeroActual = (int) ($datosJson['config']['temporada'] ?? 0);
		$etiquetaAnterior = 'Temporada ' . ($numeroActual - 1);
		foreach ($datosJson['historial_temporadas'] ?? [] as $temporada) {
			if (($temporada['nombre'] ?? '') !== $etiquetaAnterior) { continue; }
			$anteriores = [];
			foreach ($temporada['equipos'] ?? [] as $equipo) {
				foreach ($equipo['jugadores'] ?? [] as $jugador) {
					$anteriores[] = ['nombre' => $jugador['nombre'], 'puntos' => (int) ($jugador['goles'] ?? 0)];
				}
			}
			$aplicarRanking($anteriores);
			break;
		}

		$mejoresPorEquipo = [];
		foreach ($datosJson['equipos'] as $equipo) {
			$mejor = null;
			foreach ($equipo['jugadores'] ?? [] as $jugador) {
				$puntos = (int) ($jugador['goles'] ?? 0) + (int) ($jugador['asistencias'] ?? 0);
				if ($mejor === null || $puntos > $mejor['puntos']) {
					$mejor = ['nombre' => $jugador['nombre'], 'puntos' => $puntos];
				}
			}
			if ($mejor !== null) { $mejoresPorEquipo[] = $mejor; }
		}
		$aplicarRanking($mejoresPorEquipo);

		return $resultado;
	}

	public function statsBaseImportacion(string $posicion, int $idRareza): array {
		if (!isset(self::IMPORT_SPLIT_POSICION[$posicion])) {
			return ['ataque' => 0, 'defensa' => 0, 'tecnica' => 0]; // ENT/GER/ESCUDO
		}
		$clave = self::IMPORT_RAREZA_CLAVE[$idRareza] ?? 'comun';
		$total = self::IMPORT_BASE_TOTAL[$clave] * (mt_rand(92, 108) / 100);
		$split = self::IMPORT_SPLIT_POSICION[$posicion];
		return [
			'ataque'  => max(1, min(99, (int) round($total * $split['ataque']))),
			'defensa' => max(1, min(99, (int) round($total * $split['defensa']))),
			'tecnica' => max(1, min(99, (int) round($total * $split['tecnica']))),
		];
	}
```

- [ ] **Paso 2:** `C:/xampp/php/php.exe -l db/consultas.php`.
- [ ] **Paso 3:** Commit.

```bash
git add db/consultas.php
git commit -m "Añade ranking de rarezas y fórmula de stats al importador"
```

---

#### Task 5 — Self-check de las funciones puras

**Archivos:** Crea `db/test_importar_datos_oficiales.php`.

**Interfaces:**
- Consume: todas las funciones de las Tareas 2-4 (`mapearPosicionJugador`, `mapearAfinidadJugador`, `emparejarEquipo`, `rankearRarezasImportacion`, `statsBaseImportacion`).

- [ ] **Paso 1:** Crear el script:

```php
<?php
require_once __DIR__ . '/conexion.php';

function afirmar($cond, $mensaje) {
    if (!$cond) { fwrite(STDERR, "FALLO: {$mensaje}\n"); exit(1); }
    echo "OK: {$mensaje}\n";
}

afirmar($db->mapearPosicionJugador('DEL') === 'DC', 'DEL mapea a DC');
afirmar($db->mapearPosicionJugador('DEF') === 'DF', 'DEF mapea a DF');
afirmar($db->mapearPosicionJugador('MED') === 'MC', 'MED mapea a MC');
afirmar($db->mapearPosicionJugador('POR') === 'POR', 'POR mapea a POR');
afirmar($db->mapearPosicionJugador('inventado') === null, 'posición desconocida devuelve null');

afirmar($db->mapearAfinidadJugador('Fuego') === 2, 'Fuego -> 2');
afirmar($db->mapearAfinidadJugador('Aire') === 3, 'Aire -> Viento (3)');
afirmar($db->mapearAfinidadJugador('Montaña') === 1, 'Montaña -> 1');
afirmar($db->mapearAfinidadJugador('Bosque') === 4, 'Bosque -> 4');
afirmar($db->mapearAfinidadJugador(null) === 5, 'nulo -> no-afi (5)');
afirmar($db->mapearAfinidadJugador('Forest') === 5, 'valor no reconocido -> no-afi (5)');

$existentes = [
    ['id_equipo' => 13, 'nombre' => 'Instituto Zeus'],
    ['id_equipo' => 99, 'nombre' => 'Instituto Kikrwood'],
];
$exacto = $db->emparejarEquipo('Instituto Zeus', $existentes);
afirmar($exacto['estado'] === 'exacto' && $exacto['id_equipo'] === 13, 'match exacto encuentra Instituto Zeus');

$ambiguo = $db->emparejarEquipo('Instituto Kirkwood', $existentes);
afirmar($ambiguo['estado'] === 'ambiguo' && $ambiguo['candidato_db']['id_equipo'] === 99, 'Kirkwood/Kikrwood se detecta como ambiguo');

$nuevo = $db->emparejarEquipo('Equipo Totalmente Distinto FC', $existentes);
afirmar($nuevo['estado'] === 'nuevo', 'nombre sin parecido se marca como nuevo');

$fixture = [
    'config' => ['temporada' => '3'],
    'equipos' => [
        ['id' => 'eqA', 'nombre' => 'Equipo A', 'jugadores' => [
            ['nombre' => 'Goleador Top', 'goles' => 20, 'asistencias' => 1],
            ['nombre' => 'Suplente', 'goles' => 0, 'asistencias' => 0],
        ]],
        ['id' => 'eqB', 'nombre' => 'Equipo B', 'jugadores' => [
            ['nombre' => 'Jugador Medio', 'goles' => 5, 'asistencias' => 5],
        ]],
    ],
    'historial_temporadas' => [],
];
$rareza = $db->rankearRarezasImportacion($fixture);
afirmar(($rareza['eqA|Goleador Top'] ?? null) === 4, 'máximo goleador actual sube a épico');
afirmar(!isset($rareza['eqA|Suplente']), 'jugador sin goles no se promociona');

$statsEnt = $db->statsBaseImportacion('ENT', 5);
afirmar($statsEnt === ['ataque' => 0, 'defensa' => 0, 'tecnica' => 0], 'entrenador siempre 0/0/0');
$statsDC = $db->statsBaseImportacion('DC', 4);
afirmar($statsDC['ataque'] >= 50 && $statsDC['ataque'] <= 99, 'delantero épico tiene ataque alto');

echo "\nTodas las comprobaciones pasaron.\n";
```

- [ ] **Paso 2:** Ejecutar y comprobar que todo pasa.

```bash
C:/xampp/php/php.exe db/test_importar_datos_oficiales.php
```

Esperado: una línea `OK:` por cada `afirmar()` y el mensaje final, sin
`FALLO:`.

- [ ] **Paso 3:** Commit.

```bash
git add db/test_importar_datos_oficiales.php
git commit -m "Añade self-check de las funciones puras del importador"
```

---

#### Task 6 — Descarga de fotos, dedupe y orquestadores

**Archivos:** Modifica `db/consultas.php`, misma sección.

**Interfaces:**
- Consume: `slugImportado()` (Tarea 2), `emparejarEquipo()`/`resolverEquipos()` (Tarea 3), `rankearRarezasImportacion()`/`statsBaseImportacion()` (Tarea 4), `crearCromo()` (Tarea 1), `listarEquipos()`/`derivarRasgosConfiguracion()` (ya existentes).
- Produce: `guardarFotoImportada(string $url, string $equipoSlug, string $jugadorSlug): string`, `existeCromoImportado(string $nombre, int $id_equipo, int $id_expansion): bool`, `previsualizarImportacion(array $datosJson, int $id_expansion): array`, `ejecutarImportacion(array $datosJson, int $id_expansion, array $decisiones): array`.

- [ ] **Paso 1:** Añadir las cuatro funciones:

```php
	public function guardarFotoImportada(string $url, string $equipoSlug, string $jugadorSlug): string {
		$contenido = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 8]]));
		if ($contenido === false) { return ''; }

		$imagen = @imagecreatefromstring($contenido);
		if ($imagen === false) { return ''; }

		$carpeta = __DIR__ . "/../assets/img/Cromos/Importados/{$equipoSlug}";
		if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true) && !is_dir($carpeta)) {
			imagedestroy($imagen);
			return '';
		}

		$rutaDisco = "{$carpeta}/{$jugadorSlug}.webp";
		$ok = imagewebp($imagen, $rutaDisco, 85);
		imagedestroy($imagen);

		return $ok ? "./assets/img/Cromos/Importados/{$equipoSlug}/{$jugadorSlug}.webp" : '';
	}

	public function existeCromoImportado(string $nombre, int $id_equipo, int $id_expansion): bool {
		$stmt = $this->pdo->prepare("
			SELECT 1 FROM cromos
			WHERE nombre = :nombre AND id_equipo = :id_equipo AND id_expansion = :id_expansion
			LIMIT 1
		");
		$stmt->execute([':nombre' => $nombre, ':id_equipo' => $id_equipo, ':id_expansion' => $id_expansion]);
		return (bool) $stmt->fetchColumn();
	}

	public function previsualizarImportacion(array $datosJson, int $id_expansion): array {
		$equiposExistentes = $this->listarEquipos();
		$equiposConJugadores = array_values(array_filter($datosJson['equipos'] ?? [], fn($eq) => !empty($eq['jugadores'])));

		$exactos = 0; $nuevos = []; $ambiguos = [];
		$jugadoresACrear = 0; $jugadoresOmitidos = 0; $afinidadesDesconocidas = 0; $cartasEquipo = 0;

		foreach ($equiposConJugadores as $equipo) {
			$match = $this->emparejarEquipo($equipo['nombre'], $equiposExistentes);
			$idEquipo = null;
			if ($match['estado'] === 'exacto') { $exactos++; $idEquipo = $match['id_equipo']; }
			elseif ($match['estado'] === 'ambiguo') { $ambiguos[] = ['id' => $equipo['id']] + $match; }
			else { $nuevos[] = $equipo['nombre']; }

			foreach ($equipo['jugadores'] as $jugador) {
				if ($idEquipo !== null && $this->existeCromoImportado($jugador['nombre'], $idEquipo, $id_expansion)) {
					$jugadoresOmitidos++;
					continue;
				}
				$jugadoresACrear++;
				if ($this->mapearAfinidadJugador($jugador['afinidad'] ?? null) === 5) { $afinidadesDesconocidas++; }
			}

			foreach (['escudo', 'entrenador', 'gerente'] as $campo) {
				if (trim((string) ($equipo[$campo] ?? '')) !== '') { $cartasEquipo++; }
			}
		}

		return [
			'equipos_exactos' => $exactos,
			'equipos_nuevos' => $nuevos,
			'equipos_ambiguos' => $ambiguos,
			'jugadores_a_crear' => $jugadoresACrear,
			'jugadores_omitidos' => $jugadoresOmitidos,
			'afinidades_desconocidas' => $afinidadesDesconocidas,
			'cartas_equipo_a_crear' => $cartasEquipo,
		];
	}

	public function ejecutarImportacion(array $datosJson, int $id_expansion, array $decisiones): array {
		set_time_limit(0);

		$equiposExistentes = $this->listarEquipos();
		$idsEquiposPrevios = array_column($equiposExistentes, 'id_equipo');
		$equiposConJugadores = array_values(array_filter($datosJson['equipos'] ?? [], fn($eq) => !empty($eq['jugadores'])));
		$mapaEquipos = $this->resolverEquipos($equiposConJugadores, $equiposExistentes, $decisiones);
		$rareza = $this->rankearRarezasImportacion($datosJson);

		$creados = 0; $omitidos = 0; $fotosFallidas = []; $equiposCreados = 0;

		foreach ($equiposConJugadores as $equipo) {
			$idEquipo = $mapaEquipos[$equipo['id']];
			if (!in_array($idEquipo, $idsEquiposPrevios, true)) { $equiposCreados++; }
			$equipoSlug = $this->slugImportado($equipo['nombre']);

			foreach ($equipo['jugadores'] as $jugador) {
				if ($this->existeCromoImportado($jugador['nombre'], $idEquipo, $id_expansion)) { $omitidos++; continue; }

				$posicion = $this->mapearPosicionJugador($jugador['posicion'] ?? '');
				if ($posicion === null) { $omitidos++; continue; }

				$idRareza = $rareza["{$equipo['id']}|{$jugador['nombre']}"] ?? (($jugador['titular'] ?? false) ? 2 : 1);
				$idAfinidad = $this->mapearAfinidadJugador($jugador['afinidad'] ?? null);
				$stats = $this->statsBaseImportacion($posicion, $idRareza);

				$imagen = '';
				if (!empty($jugador['foto'])) {
					$imagen = $this->guardarFotoImportada($jugador['foto'], $equipoSlug, $this->slugImportado($jugador['nombre']));
					if ($imagen === '') { $fotosFallidas[] = $jugador['nombre']; }
				}

				$this->crearCromo($jugador['nombre'], $posicion, '', $imagen, $id_expansion, $idEquipo, $idRareza, $idAfinidad, $stats['ataque'], $stats['defensa'], $stats['tecnica']);
				$creados++;
			}

			if (trim((string) ($equipo['escudo'] ?? '')) !== '' && !$this->existeCromoImportado('Escudo ' . $equipo['nombre'], $idEquipo, $id_expansion)) {
				$this->crearCromo('Escudo ' . $equipo['nombre'], 'ESCUDO', '', '', $id_expansion, $idEquipo, 5, 5, 0, 0, 0);
				$creados++;
			}
			if (trim((string) ($equipo['entrenador'] ?? '')) !== '' && !$this->existeCromoImportado($equipo['entrenador'], $idEquipo, $id_expansion)) {
				$this->crearCromo($equipo['entrenador'], 'ENT', '', '', $id_expansion, $idEquipo, 5, 5, 0, 0, 0);
				$creados++;
			}
			if (trim((string) ($equipo['gerente'] ?? '')) !== '' && !$this->existeCromoImportado($equipo['gerente'], $idEquipo, $id_expansion)) {
				$this->crearCromo($equipo['gerente'], 'GER', '', '', $id_expansion, $idEquipo, 5, 5, 0, 0, 0);
				$creados++;
			}
		}

		$this->derivarRasgosConfiguracion();

		return ['creados' => $creados, 'omitidos' => $omitidos, 'equipos_creados' => $equiposCreados, 'fotos_fallidas' => $fotosFallidas];
	}
```

- [ ] **Paso 2:** `C:/xampp/php/php.exe -l db/consultas.php`.
- [ ] **Paso 3:** Commit.

```bash
git add db/consultas.php
git commit -m "Añade orquestadores de previsualización y ejecución del importador"
```

---

#### Task 7 — Página del panel (`panel/importar.php`) y navegación

**Archivos:** Crea `panel/importar.php`. Modifica `panel/navbar.php`.

**Interfaces:**
- Consume: `listarExpansiones()`, `previsualizarImportacion()`, `ejecutarImportacion()` (Tarea 6).

- [ ] **Paso 1:** Añadir la entrada de navegación en `panel/navbar.php`, justo
  después del enlace a "Cromos":

```php
    <a href="importar.php" class="<?= $activeAdmin === 'importar' ? 'active' : '' ?>">
      <span class="nav-ico"><i class="bi bi-cloud-upload"></i></span> Importar datos
    </a>
```

- [ ] **Paso 2:** Crear `panel/importar.php`:

```php
<?php
session_start();
require_once __DIR__ . '/../db/conexion.php';

if (isset($_SESSION['dictador'])) {
    if ($_SESSION['dictador'] != 1) { header("Location: ../landing.php"); exit; }
} else {
    header("Location: ../landing.php"); exit;
}

$error = '';
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar']) && isset($_SESSION['import_datos'])) {
    $decisiones = [];
    foreach ($_POST['equipo_eleccion'] ?? [] as $idEquipoJson => $eleccion) {
        $decisiones[$idEquipoJson] = ['eleccion' => $eleccion, 'texto' => $_POST['equipo_texto'][$idEquipoJson] ?? ''];
    }
    $resultado = $db->ejecutarImportacion($_SESSION['import_datos'], (int) $_SESSION['import_id_expansion'], $decisiones);
    unset($_SESSION['import_datos'], $_SESSION['import_id_expansion']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar'])) {
    unset($_SESSION['import_datos'], $_SESSION['import_id_expansion']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['json_datos'])) {
    $contenido = file_get_contents($_FILES['json_datos']['tmp_name']);
    $datos = json_decode($contenido, true);

    if (!is_array($datos) || !isset($datos['equipos']) || !is_array($datos['equipos'])) {
        $error = 'El archivo no parece un datos_oficiales.json válido: falta la clave "equipos".';
    } else {
        $_SESSION['import_datos'] = $datos;
        $_SESSION['import_id_expansion'] = (int) ($_POST['id_expansion'] ?? 0);
    }
}

$previsualizacion = isset($_SESSION['import_datos'])
    ? $db->previsualizarImportacion($_SESSION['import_datos'], (int) $_SESSION['import_id_expansion'])
    : null;

$expansiones = $db->listarExpansiones();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Importar datos oficiales — Panel de control</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Chakra+Petch:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="./assets/css/admin.css">
<link rel="icon" type="image/png" href="../assets/img/iconos/favicon.ico">
</head>
<body>

<div class="admin-shell">
  <?php $activeAdmin = 'importar'; include __DIR__ . '/navbar.php'; ?>

  <main class="admin-main">
    <div class="admin-head">
      <div>
        <h1>Importar datos oficiales</h1>
        <p>Crea cartas de jugadores, escudos, entrenadores y gerentes a partir del datos_oficiales.json de la Superliga Frontier.</p>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($resultado): ?>
      <div class="field-full">
        <h2><?= $resultado['creados'] ?> cartas creadas</h2>
        <ul>
          <li><?= $resultado['omitidos'] ?> omitidas (ya existían)</li>
          <li><?= $resultado['equipos_creados'] ?> equipos nuevos creados</li>
        </ul>
        <?php if (!empty($resultado['fotos_fallidas'])): ?>
        <div class="alert alert-warning">No se pudo descargar la foto de: <?= htmlspecialchars(implode(', ', $resultado['fotos_fallidas'])) ?>. Esas cartas se crearon sin imagen.</div>
        <?php endif; ?>
      </div>

    <?php elseif ($previsualizacion): ?>
      <form method="POST">
        <h2>Previsualización</h2>
        <ul>
          <li><?= $previsualizacion['jugadores_a_crear'] ?> jugadores a crear</li>
          <li><?= $previsualizacion['jugadores_omitidos'] ?> jugadores omitidos (ya existen en esta expansión)</li>
          <li><?= $previsualizacion['equipos_exactos'] ?> equipos ya reconocidos</li>
          <li><?= count($previsualizacion['equipos_nuevos']) ?> equipos nuevos: <?= htmlspecialchars(implode(', ', $previsualizacion['equipos_nuevos'])) ?></li>
          <li><?= $previsualizacion['afinidades_desconocidas'] ?> jugadores con afinidad no reconocida (irán como "no-afi")</li>
          <li><?= $previsualizacion['cartas_equipo_a_crear'] ?> cartas de escudo/entrenador/gerente a crear</li>
        </ul>

        <?php if (!empty($previsualizacion['equipos_ambiguos'])): ?>
        <h3>Equipos que necesitan tu confirmación</h3>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Del JSON</th><th>¿Con cuál equipo es?</th></tr></thead>
            <tbody>
            <?php foreach ($previsualizacion['equipos_ambiguos'] as $amb): ?>
            <tr>
              <td><?= htmlspecialchars($amb['nombre_json']) ?> <small>(<?= $amb['porcentaje'] ?>% parecido)</small></td>
              <td>
                <label><input type="radio" name="equipo_eleccion[<?= htmlspecialchars($amb['id']) ?>]" value="db" checked> Es "<?= htmlspecialchars($amb['candidato_db']['nombre']) ?>" (ya existe)</label><br>
                <label><input type="radio" name="equipo_eleccion[<?= htmlspecialchars($amb['id']) ?>]" value="json"> Es un equipo nuevo, llámalo "<?= htmlspecialchars($amb['nombre_json']) ?>"</label><br>
                <label><input type="radio" name="equipo_eleccion[<?= htmlspecialchars($amb['id']) ?>]" value="otro"> Otro nombre:
                  <input type="text" name="equipo_texto[<?= htmlspecialchars($amb['id']) ?>]" placeholder="Nombre correcto"></label>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <div class="modal-footer">
          <button type="submit" name="cancelar" value="1" class="btn btn-ghost">Cancelar</button>
          <button type="submit" name="confirmar" value="1" class="btn btn-primary">Crear cartas</button>
        </div>
      </form>

    <?php else: ?>
      <form method="POST" enctype="multipart/form-data">
        <div class="field field-full">
          <label>Archivo datos_oficiales.json</label>
          <input type="file" name="json_datos" accept=".json,application/json" required>
        </div>
        <div class="field field-full">
          <label>Expansión destino</label>
          <select name="id_expansion" required>
            <?php foreach ($expansiones as $ex): ?>
            <option value="<?= $ex['id_expansion'] ?>"><?= htmlspecialchars($ex['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Previsualizar</button>
        </div>
      </form>
    <?php endif; ?>
  </main>
</div>

</body>
</html>
```

- [ ] **Paso 3:** `C:/xampp/php/php.exe -l panel/importar.php panel/navbar.php`.
- [ ] **Paso 4:** Verificar en navegador con la cuenta `Claude` (`dictador=1`)
  que `panel/importar.php` carga, el formulario del paso 1 aparece, y que
  redirige a `landing.php` si no hay sesión de dictador.
- [ ] **Paso 5:** Commit.

```bash
git add panel/importar.php panel/navbar.php
git commit -m "Añade la página del panel para importar datos oficiales"
```

---

#### Task 8 — Prueba manual con el archivo real y verificación final

- [ ] **Paso 1:** Con sesión de `Claude` en el navegador, ir a
  `panel/importar.php`, subir el `datos_oficiales.json` real y una expansión
  de prueba, y comprobar que el resumen del paso 1 cuadra con lo esperado
  (43 equipos en el JSON, 21 ya en BD, ~22 nuevos, ~133 agentes libres
  excluidos del conteo).
- [ ] **Paso 2:** Resolver los equipos ambiguos (ej. "Instituto Kirkwood" vs
  "Instituto Kikrwood") y confirmar. Comprobar en `panel/cromos.php` que las
  cartas aparecen con equipo/posición/afinidad/foto correctos.
- [ ] **Paso 3:** Comprobar que `derivarRasgosConfiguracion()` no rompió el
  reparto existente (`12/10/8/8`, según §13 del CLAUDE.md).
- [ ] **Paso 4:** Volver a subir el mismo archivo con la misma expansión y
  comprobar que el resumen da 0 jugadores a crear (todos omitidos).
- [ ] **Paso 5:** `for f in *.php partials/*.php components/*.php db/*.php assets/ajax/*.php panel/*.php; do C:/xampp/php/php.exe -l "$f"; done` sin
  errores (§13).

## 16.10 Refinamiento tras la implementación: stats reales y borrado de importados

Dos cambios pedidos por Alejandro después de probar el importador con el
archivo real, sobre la base ya construida en §16.1-§16.9.

### Stats de combate: tabla real en vez de la heurística

Alejandro entregó `Rangos_estadisticas_SRF.csv` (mín/máx de ataque, defensa y
técnica por rareza × posición, 24 filas: rarezas 1-6 × POR/DF/MC/DC). Sustituye
por completo la fórmula heurística de §16.6 (`IMPORT_BASE_TOTAL` /
`IMPORT_SPLIT_POSICION`, con su jitter ±8%): `statsBaseImportacion()` pasa a
buscar el rango exacto `[min, max]` de cada estadística en una tabla fija
(`IMPORT_RANGOS_STATS[$idRareza][$posicion]`) y sortear con `mt_rand(min,
max)` de forma independiente para ataque/defensa/técnica. Sin tope 1-99
manual: los rangos del CSV ya respetan ese límite. Para posiciones sin
entrada (`ENT`/`GER`/`ESCUDO`) sigue devolviendo `0/0/0`, igual que antes.

La tabla completa (transcrita del CSV):

| Rareza | Posición | Ataque | Defensa | Técnica |
|---|---|---|---|---|
| 1 Común | POR | 23-37 | 62-76 | 52-66 |
| 1 Común | DF | 37-51 | 57-71 | 47-61 |
| 1 Común | MC | 48-62 | 49-63 | 56-70 |
| 1 Común | DC | 63-77 | 37-51 | 50-64 |
| 2 Poco común | POR | 31-45 | 68-82 | 59-73 |
| 2 Poco común | DF | 43-57 | 65-79 | 53-67 |
| 2 Poco común | MC | 56-70 | 57-71 | 65-79 |
| 2 Poco común | DC | 69-83 | 45-59 | 58-72 |
| 3 Raro | POR | 39-53 | 74-88 | 65-79 |
| 3 Raro | DF | 50-64 | 72-86 | 60-74 |
| 3 Raro | MC | 64-78 | 65-79 | 73-87 |
| 3 Raro | DC | 76-90 | 53-67 | 66-80 |
| 4 Épico | POR | 47-61 | 80-94 | 72-86 |
| 4 Épico | DF | 56-70 | 79-93 | 66-80 |
| 4 Épico | MC | 72-86 | 73-87 | 81-95 |
| 4 Épico | DC | 82-96 | 60-74 | 74-88 |
| 5 Legendario | POR | 55-69 | 86-99 | 79-93 |
| 5 Legendario | DF | 63-77 | 86-99 | 73-87 |
| 5 Legendario | MC | 80-94 | 81-95 | 90-99 |
| 5 Legendario | DC | 89-99 | 68-82 | 83-97 |
| 6 SRF | POR | 63-77 | 92-99 | 86-99 |
| 6 SRF | DF | 69-83 | 92-99 | 79-93 |
| 6 SRF | MC | 88-99 | 89-99 | 92-99 |
| 6 SRF | DC | 92-99 | 76-90 | 91-99 |

Las filas 5 (Legendario) y 6 (SRF) no las usa hoy el importador de jugadores
(la promoción de rareza nunca pasa de Épico, §16.5), pero se incluyen
completas por si `statsBaseImportacion()` se reutiliza en otro contexto
(cartas Legendario/SRF de escudo/entrenador/gerente siguen yendo a `0/0/0`
porque su posición no tiene entrada en la tabla, no por la rareza).

### Botón para borrar las cartas importadas

Nuevo botón en `panel/importar.php`, siguiendo el patrón ya existente en el
panel (`confirmarBorrado()` con `confirm()` nativo del navegador + enlace
`?accion=valor` — el panel es "el sistema viejo", §12, no usa el modal
`SRF.confirmar` del sitio principal).

**Marcado:** nueva columna `cromos.origen_importacion` (`TINYINT(1) NOT NULL
DEFAULT 0`), migración `db/migraciones/014_importador_origen.sql` (013 ya
estaba ocupado por `013_plantillas_3d.sql`)
(`ALTER TABLE ... ADD COLUMN IF NOT EXISTS`, aditiva y re-ejecutable). `crearCromo()`
gana un parámetro opcional más al final (`$origen_importacion = 0`,
manteniendo compatibilidad con las llamadas existentes). `ejecutarImportacion()`
lo pasa a `1` en **todas** sus llamadas a `crearCromo()` (jugadores y cartas
de equipo) — así "cartas importadas" significa exactamente "creadas por este
importador", sin importar en qué expansión ni cuándo.

**Borrado seguro — nunca borra cartas ya en manos de un jugador:**
`borrarCartasImportadas()` primero reúne los `id_cromo` con
`origen_importacion = 1`, luego comprueba cuáles aparecen en `coleccion`
(alguien la tiene) o en `duelo_alineaciones` (se usó en un duelo, aunque sea
histórico) — esas se **excluyen** del borrado y se cuentan aparte como
"retenidas". Solo se borran las que no están en uso. `cromo_rasgos` se
limpia solo (`FOREIGN KEY ... ON DELETE CASCADE`, ya existe en el esquema);
`coleccion` y `duelo_alineaciones` NO tienen cascada (son `RESTRICT` por
defecto), así que intentar borrar una carta en uso fallaría de golpe si no
se filtrara antes — de ahí el filtro previo en vez de un `DELETE` directo.

El botón muestra cuántas cartas importadas hay ahora mismo
(`contarCartasImportadas()`), pide confirmación con el texto exacto de
cuántas se van a borrar, y tras borrar reporta cuántas se borraron y cuántas
se retuvieron por estar en uso (si las hay).

## 16.11 Barra de progreso durante la importación

`ejecutarImportacion()` puede tardar varios minutos con el archivo real
(descarga de cientos de fotos). Alejandro pidió una barra que se vaya
llenando para saber por dónde va, no un número exacto — aproximado es
suficiente.

**Por qué no basta con la petición POST normal:** el paso 2 hoy es un
`<form method="POST">` que bloquea la navegación del navegador hasta que
`panel/importar.php` termina de responder — durante ese bloqueo no corre
ningún JS de la página, así que no se puede animar nada. Hace falta que el
paso 2 se dispare por `fetch()` (JS), para poder sondear el progreso en
paralelo mientras esa petición sigue en vuelo — mismo patrón de "sala en
vivo sin websockets" que ya usa `duelo.js` (latido + sondeo, §9 del
CLAUDE.md), aplicado aquí a un progreso en vez de a un estado de partida.

**Dos endpoints nuevos en `assets/ajax/`** (nunca mezclado con el render,
§2):
- `importacion_ejecutar.php` — recibe el POST del paso 2 (las decisiones de
  equipos ambiguos), llama a `ejecutarImportacion()` y devuelve el resultado
  en JSON. Sustituye por completo la rama `isset($_POST['confirmar'])` que
  hoy vive en `panel/importar.php` — se mueve entera aquí, no se duplica.
- `importacion_progreso.php` — lee el fichero de progreso y devuelve
  `{"actual": N, "total": M}` en JSON. Sondeado por JS cada ~800 ms mientras
  dura la importación.

**Guardar el progreso sin BD ni sesión compartida:** `ejecutarImportacion()`
gana un parámetro opcional `?string $idSesionProgreso = null`. Si se pasa,
calcula el total al principio (recorriendo `$equiposConJugadores` una vez:
jugadores + campos de escudo/entrenador/gerente no vacíos) y va escribiendo
`{"actual": N, "total": M}` en un fichero
`sys_get_temp_dir() . '/tcg_importacion_progreso_' . $idSesionProgreso .
'.json'` cada vez que procesa un jugador o una carta de equipo (se cree,
se omita o falle da igual — lo que importa para la barra es "cuánto se ha
recorrido", no "cuánto se ha creado"). Borra el fichero al terminar.

**Trampa de PHP a evitar — el lock de sesión:** `session_start()` bloquea
por fichero mientras la sesión esté abierta. Si `importacion_ejecutar.php`
mantiene la sesión abierta durante los varios minutos que tarda la
importación, **todas** las peticiones de `importacion_progreso.php` con la
misma sesión (la del propio Alejandro, sondeando desde la misma pestaña) se
quedarían colgadas esperando el lock — el sondeo dejaría de servir para
nada. Por eso `importacion_ejecutar.php` lee lo que necesita de `$_SESSION`
(los datos del JSON, la expansión) y llama a `session_write_close()` **antes**
de invocar `ejecutarImportacion()`, para soltar el lock cuanto antes. El
`session_id()` (que no cambia al cerrar la sesión) es lo que se le pasa como
`$idSesionProgreso`, para que el fichero de progreso sea único por sesión de
admin sin depender de la sesión seguir abierta.

**Frontend** (`panel/assets/js/scriptImportar.js`, nuevo, sigue el patrón IIFE
+ `'use strict'` del resto de JS del proyecto): el botón "Crear cartas" pasa
de `type="submit"` a `type="button"`; su `onclick` oculta el formulario,
muestra una barra `<progress>` nativa de HTML (sin CSS nuevo, el navegador ya
la estiliza) con un texto "X de Y", lanza el `fetch()` a
`importacion_ejecutar.php` con los datos del formulario, y en paralelo un
`setInterval` que sondea `importacion_progreso.php` y actualiza `value`/`max`
de la barra y el texto. Al resolver el `fetch()`, para el `setInterval` y
pinta el resumen final (creados/omitidos/equipos nuevos/fotos fallidas/
posiciones desconocidas) con el mismo marcado que ya usa la vista de
resultado. El botón "Cancelar" no cambia: sigue siendo un `<button
type="submit">` normal, no necesita JS porque no tarda nada.

## 16.12 Borrado de cartas importadas por expansión

El botón de §16.10 borraba TODAS las cartas importadas de golpe, sin
importar la expansión. Alejandro pidió poder borrar solo las de una
expansión concreta (para poder probar una importación en una expansión de
pruebas y limpiarla sin arriesgar cartas importadas de otra expansión que sí
esté en uso).

**`Tcg`, cambios:**
- `borrarCartasImportadas(?int $id_expansion = null)`: gana un parámetro
  opcional. Si se pasa, el `SELECT id_cromo FROM cromos WHERE
  origen_importacion = 1` añade `AND id_expansion = :id_expansion`. El resto
  de la lógica (excluir las que están en `coleccion`/`duelo_alineaciones`)
  no cambia. `null` mantiene el comportamiento global de antes, por si algo
  más lo llama así en el futuro.
- `contarCartasImportadas()` deja de ser lo que decide si se muestra el
  botón — pasa a usarse `listarExpansionesConCartasImportadas(): array`,
  nueva, que devuelve una fila por cada expansión con al menos una carta
  importada: `[['id_expansion'=>int, 'nombre'=>string, 'total'=>int], ...]`
  (`SELECT c.id_expansion, e.nombre, COUNT(*) AS total FROM cromos c JOIN
  expansiones e ON e.id_expansion = c.id_expansion WHERE
  c.origen_importacion = 1 GROUP BY c.id_expansion, e.nombre ORDER BY
  e.nombre`).

**`panel/importar.php`:** el bloque único "N cartas importadas actualmente
[Borrar cartas importadas]" de la pantalla del paso 1 se sustituye por una
tabla (mismo patrón `admin-table`/`admin-table-wrap` que usa el resto del
panel, sin CSS nuevo) con una fila por expansión que tiene cartas
importadas: nombre de la expansión, cuántas, y un botón "Borrar" por fila
que apunta a `importar.php?borrar_importadas=1&id_expansion=ID`, con el
mismo `confirm()` nativo de antes pero mencionando la expansión y la cifra
concretas en el mensaje. Si no hay ninguna expansión con cartas importadas,
no se muestra nada (igual que antes con el botón único).

El manejador del `GET` (`isset($_GET['borrar_importadas'])`, al principio
del fichero) lee también `$_GET['id_expansion']` y se lo pasa a
`borrarCartasImportadas()`.

