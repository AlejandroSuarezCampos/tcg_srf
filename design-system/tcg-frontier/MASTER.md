# TCG FRONTIER — SISTEMA DE DISEÑO "ASCUA"

> Fuente única de verdad. Ningún componente define un color, un tamaño ni una
> duración a mano.
> Versión 1.1 · Fase 2 en curso.

## Estado de la implementación

| Bloque | Estado | Ficheros |
|---|---|---|
| **1 · Tokens y motor de movimiento** | ✅ hecho | `assets/css/tokens.css` (reescrito), `assets/css/ascua.css` (nuevo), `assets/css/puente.css` (temporal), `assets/js/motion.js` (nuevo), bloque inline de `partials/head.php`, `assets/fonts/` |
| **2 · Navegación de 5** | ✅ hecho | `navbar.php` (reescrito), §2 de `assets/css/ascua.css`, `iniciarHojaJugar()` en `assets/js/ui.js`, paso «navegacion» del tutorial en `db/consultas.php` |
| **3 · `hoy.php`** | ✅ hecho | `hoy.php` (nuevo), `partials/hoy_prioridad.php` (nuevo), `assets/css/hoy.css` (nuevo, primera hoja por pantalla), `landing.php`/`login.php`/`registro.php` redirigen, `listarMercadoActivo()` acepta `limite` |
| **4 · `plantilla.php`** | ✅ hecho | `plantilla.php` (nuevo), `partials/plantilla_filtro.php` (nuevo), `assets/css/plantilla.css` (nuevo), `coleccion.php` y `album.php` → redirección 301, hoja genérica `iniciarHojas()` en `assets/js/ui.js`, `listarColeccionCompleta()` devuelve `id_equipo` |
| **5 · `jugar.php`** | ✅ hecho | `jugar.php` (nuevo), `partials/jugar_disposicion.php` (nuevo), `assets/css/jugar.css` (nuevo), hoja desplegable retirada de `navbar.php` y su CSS muerto borrado de `ascua.css` |
| **6 · `landing.php`** | ✅ hecho | `landing.php` (reescrita entera), `assets/css/landing.css` (nuevo), `estadisticasPublicas()` y `listarDestacados()` corregido en `db/consultas.php` |
| **7a · Las dos fusiones** | ✅ hecho | `acceso.php` (nuevo), `assets/css/acceso.css` + `assets/js/acceso.js` (nuevos), `perfil.php` absorbe `configuracion.php`, enlace profundo a pestañas en `ui.js`, `login`/`registro`/`configuracion` → redirección 301 |
| **7b · Pantallas simples** | ✅ hecho | `partials/cabecera.php` (nuevo, compartido) + su CSS en `ascua.css`; migradas `sobres`, `misiones`, `cadenas`, `mercado`, `descartar`, `duelos`, `usuario` y `perfil` |
| **7c · Botones, modal y borrado de `puente.css`** | ✅ hecho | §5 y §6 de `ascua.css` (botones y modal premium), renombrado de tokens en 14 hojas + 20 PHP + 5 JS, **`puente.css` borrado** |
| **7d · Partir los monolitos y la hoja de Jugar** | ✅ hecho | `ceremonia.css`, `cadena.css`, `partido.css`, `misiones.css` extraídas; navegación vieja borrada de `layout.css`; hoja de «Jugar» restaurada para móvil; `jugar.php` con filas en móvil |
| **Ronda de correcciones 1 — QA visual** | ✅ hecho | Sesión de logout perdida, `.rot` sin definir fuera de `hoy.css`, `cadenas.php` sin `.cadena-tarjeta`, letras del "11/11" muy separadas, rejilla de rarezas desbordada, fondo negro de la ficha en modal/popup — ver §10 |
| **Ronda de correcciones 2 — `duelo.php` y minijuegos** | ✅ hecho | `duelo.php` sin `cadena.css`, título de la modal en juego sin centrar, cola de la 7d con 5 componentes globales atrapados en `partido.css` (footer sitewide incluido) — ver §10 |

**Deuda del bloque 1 — SALDADA.** `puente.css` está borrado. Y no hizo falta
reescribir las 6.785 líneas: el puente era un mapa **1 a 1** de nombres, así que
bastó renombrar los tokens en su sitio (1.991 sustituciones en 14 hojas, 20 PHP
y 5 JS). De los 69 alias, solo uno no lo era —`--radius-md`, que me inventé en
el bloque 1— y ahora vive como `--r-alerta`.

**Bloque 2 — deuda SALDADA, y con una corrección.** La hoja desplegable se
justificó diciendo que llevaba a cualquier modo «en **un** toque frente a los
dos de una pantalla intermedia». **Era falso**: abrir la hoja ya era el primer
toque y pulsar el destino el segundo. Costaban dos las dos. Con el mismo coste,
el hub enseña estado y la hoja no enseñaba nada, así que en el bloque 5 la hoja
se retiró y «Jugar» pasó a ser un enlace normal a `jugar.php`.

El mecanismo genérico de hoja inferior (`.hoja`, `iniciarHojas()` en `ui.js`)
sigue vivo: lo usan los filtros de `plantilla.php`. Lo que se borró fue el
marcado de la hoja de Jugar y su CSS muerto (`.hoja-lista`, `.hoja-opcion*`,
`.hoja--nav`).

Los cinco destinos apuntan a su pantalla definitiva.

**Bloque 3 — deuda RESUELTA.** Se levantó una base local de pruebas
(`tcg_prueba_temporal`, volcado de `db/tcg.sql`: 15 usuarios, 484 cromos, 2.980
filas de colección, 9 anuncios, 130 duelos) y se midió sobre datos reales:

| Pantalla | Respuesta (media de 5) |
|---|---|
| `misiones.php` | 78 ms |
| **`hoy.php`** | **88 ms** |
| `mercado.php` | 122 ms |
| `coleccion.php` | 171 ms |
| `duelos.php` | 240 ms |

Las ocho consultas de `hoy.php` cuestan ~10 ms por encima de `misiones.php` sola:
`listarMisionesConProgreso()` no es el problema que se temía, y la pantalla es
más barata que tres de las cuatro que ya existían. No hay que cachear nada.

`render_carta()` se ciñe al ancho de su contenedor (176 × 358 dentro de un `li`
de 176): la tira no desborda y no saca scroll lateral a la página.

**Bloque 4 — corrección del plan.** §6.3 decía fusionar también
`descartar.php` como «modo Cortar» dentro de la plantilla. **No se ha hecho, y
el plan estaba mal.** Mirando el código en vez del nombre: el descarte no es una
selección múltiple sobre la misma rejilla, es otra pantalla con otros datos
—`repetidasDescartables()` devuelve copias sobrantes, precio por unidad y un
tope por tanda— y con un contador en vivo de lo que vas a cobrar, que es
justo el dato que decide si merece la pena. Además es irreversible en lote.
El comentario que ya había en `descartar.php` lo argumentaba y tenía razón:
mezclarlo con la pantalla donde se navega y se filtra es cómo alguien vacía
media colección con un toque mal dado. Sigue siendo pantalla propia, enlazada
desde la barra superior de la plantilla.

**Balance real de la fusión: 17 pantallas → 12**, no 11.

**El bloque 7 no era un bloque.** Al medirlo: 4.984 líneas de PHP sin migrar y
6.785 de CSS antiguo. Se partió en tres (7a, 7b, 7c) en vez de fingir que cabía
de una vez. **7a está hecho**; 7b y 7c siguen pendientes, y es 7c el que
devuelve los 280 KB al borrar `puente.css`.

**Bloque 7a — las dos fusiones.**

`acceso.php` funde login y registro. Las pestañas son ENLACES de verdad: sin
JavaScript navegan y la pantalla funciona igual que antes; con JavaScript el
cambio es instantáneo y la URL se actualiza sin recargar, así que el botón de
atrás deshace el cambio de pestaña. Nada depende de que el script llegue.
No se tocó nada de la seguridad —freno por intentos fallidos,
`session_regenerate_id(true)`, y el guardia de la carrera del registro que
aborta si alguien coge el nombre entre la comprobación y el INSERT— y se
verificó una por una contra la base.

`perfil.php` absorbe `configuracion.php` como pestaña «Ajustes». **El bloque
«Canjear un código» estaba duplicado palabra por palabra en las dos pantallas**;
ahora vive una sola vez, en la pestaña «Códigos». De paso, `iniciarTabs()` en
`ui.js` aprende enlaces profundos (`#panel-x` y `data-ir-a-tab`): sin eso, un
enlace a una pestaña oculta salta a un elemento con `hidden` y parece que la
página está rota.

**Bloque 5 — fallo del sistema encontrado.** `obtenerMazoTitular()` devuelve
`null` en **dos** situaciones opuestas: cuando no hay ningún mazo marcado como
titular, y cuando lo hay pero no llega a las once cartas (última línea de esa
función). Para quien juega no es lo mismo: en la primera hay que elegir una, en
la segunda ya está elegida y falta rellenarla. `jugar.php` distingue las dos
—por eso `jugar_disposicion()` recibe también la lista de mazos— y usa
`obtenerMazoTitular()` solo como señal de «listo», que es la puerta que abren de
verdad `duelos.php` y `cadenas.php`.

La primera versión de la prueba NO cazó esto: le pasaba un `$titular` no nulo a
un caso en el que la realidad siempre manda `null`. Codificaba mi suposición, no
el comportamiento del sistema. Está corregida y ahora cubre la forma real.

**Bloque 6 — dos arreglos de paso.**

`listarDestacados()` hacía `ORDER BY c.id_cromo LIMIT 5`: no devolvía las
destacadas, devolvía **las cinco primeras que se metieron en la base**. Un
escaparate que enseña cinco comunes al azar no vende un juego de coleccionismo.
Ahora ordena por rareza descendente y se ciñe a expansiones activas.

Colisión de cascada en la portada: `.pt-display` usaba el atajo `margin`, que
escribe también el eje horizontal, y como `landing.css` se carga después de
`ascua.css` se comía el `margin-inline: -6vw` de `.a-sangre`. **La tipografía a
sangre no sangraba y no fallaba nada** — simplemente se quedaba dentro del
margen. Arreglado con `margin-block`. Es el riesgo del atajo `margin` sobre
cualquier elemento que también lleve un dispositivo del sistema.

Objetivos táctiles: se subieron a 44 el «Entrar» de la barra y los enlaces de
columna del pie. **No** se tocó el enlace suelto dentro de un párrafo («Discord
de la liga»): WCAG 2.5.8 exime los enlaces en línea dentro de una frase, y
estirarlo partiría el renglón.

**Bloque 7b — una cabecera, no seis.** Las seis pantallas repetían a mano el
mismo `<header class="cabecera">` con su propia mezcla de `linea-campo`,
`cabecera-datos` y `fila fila-entre`: cada retoque había que darlo seis veces y
siempre se quedaba alguna fuera. Ahora es `partials/cabecera.php`, con ranuras
opcionales para rótulo, texto, cifras, acción, avatar y pastilla. Su CSS vive en
`ascua.css` y no en una hoja por pantalla, precisamente porque la comparten.

Se migraron **ocho** en vez de seis: entraron también `duelos.php` (fichero
largo pero cabecera simple) y `perfil.php`, que se había quedado con la vieja en
7a. Quedan tres con cabecera antigua, todas de 7c: `cadena`, `mazos` y
`styleguide`.

Renombrado aplicado a los títulos de pestaña: Misiones → **Objetivos**, Cadenas
de Partido → **Cadenas**, Mercado → **Mercado de fichajes**, Descartar repetidas
→ **Cortar del equipo**.

**Aviso que salió de paso (no es del rediseño):** `db/tcg.sql` es anterior a la
migración `047_afinidad_webp.sql`. Quien reconstruya una base desde ese volcado
se lleva de vuelta el fallo que 047 arregla —las cuatro rutas de afinidad
apuntando a `.png` cuando en disco son `.webp`—, y el síntoma es que **el
hexágono de afinidad desaparece de todas las cartas**, porque `ui.js` esconde el
hexágono entero cuando su imagen falla. Conviene regenerar el volcado.

Y una trampa de despliegue confirmada en carne propia: aplicar una migración con
`mysql < fichero.sql` **sin `--default-character-set=utf8mb4`** destroza los
caracteres no ASCII. Al importar 047 así, `montaña.webp` se guardó como
`monta├▒a.webp` y las rutas volvieron a dar 404. Existe ya una migración
`004_reparar_codificacion.php`, así que no es la primera vez.

**Bloque 7c — botones y ficha de jugador, y el puente fuera.**

Los botones pasan a píldora con relleno de degradado, caja alta en display y un
**filo claro arriba**: la luz viene de arriba en todo el sistema (ver `--elev-*`)
y ese único píxel es lo que hace que un botón se lea como un objeto y no como
una mancha de color. El hover ya no cambia de tono —eso delata un botón
barato—: levanta 2 px y enciende el halo.

La ficha de jugador es la pantalla que más veces se mira del juego, y ahora lo
parece: fondo con su propio rescoldo detrás del arte, filo de brasa en el panel,
cifras en display a 28 px con `tabular-nums` y barras finas. Se escribió en
`ascua.css` sin tocar el marcado, así que las pantallas sin migrar lo estrenan
igual.

⚠️ **FALLO GRAVE QUE COMETÍ Y CORREGÍ.** El script de renombrado construía el
mapa con `re.findall(...var\((--[a-z0-9-]+)\))`, que captura `--negro` **sin**
el `var(...)` que lo envuelve. Resultado: las 1.991 sustituciones dejaron
`background: --negro` en vez de `background: var(--negro)`. Sintaxis válida para
el parser, valor inválido: el sitio entero se quedó sin fondo, sin color de
texto y sin tipografía. Se detectó midiendo `getComputedStyle(document.body)` —
`rgba(0,0,0,0)` donde tenía que haber `#08070A`— y se reparó con una segunda
pasada que devolvió los 1.991 envoltorios. **La lección: una sustitución masiva
no está hecha hasta que se comprueba un valor computado, no hasta que el fichero
parsea.**

**LOS KB TODAVÍA NO ESTÁN.** Borrar `puente.css` no los devuelve: el CSS
bloqueante ha pasado de 280 KB a **318 KB**, porque `ascua.css` (40 KB) se suma
y `components.css` + `layout.css` se siguen sirviendo enteros en cada página. El
ahorro sale de partir esos dos por pantalla y cargarlos con `$cssExtra`, que es
el bloque **7d**. Hasta entonces, esa cifra del diagnóstico sigue sin resolverse.

**Bloque 7d — la hoja vuelve, y los KB por fin bajan.**

**Me equivoqué al quitar la hoja de «Jugar» en el bloque 5.** El argumento era
que los toques eran los mismos que con una pantalla intermedia, y el recuento
era correcto — pero no venía al caso: en móvil una hoja **no recarga** y cae
donde está el dedo, y eso se nota aunque sean dos toques igual. Descarté una
preferencia real con una cuenta que no medía lo que importaba.

Ahora «Jugar» se comporta según el tamaño: **móvil abre la hoja** (sin recargar,
bajo el pulgar) y **escritorio navega a `jugar.php`**. El enlace apunta al hub de
verdad, así que sin JavaScript navega igual: la hoja es una mejora encima, no un
requisito. Y trae lo que le faltaba: **cifras por modo** (`$navEstado`), que solo
ponen las pantallas que ya tienen los datos — ninguna consulta extra.

`jugar.php` adopta el mismo lenguaje: en móvil, filas anchas de 76–120 px con
placa de icono y descripción; en escritorio vuelve el bento de cuatro.

**CSS bloqueante, medido por pantalla:**

| | Antes | Ahora |
|---|---|---|
| mercado, duelos, cadenas, perfil, mazos, usuario, descartar | 280 KB | **219 KB** (−61) |
| misiones · acceso · plantilla · landing | 280 KB | 222–229 KB |
| hoy · jugar | 280 KB | 232 KB (−48) |
| sobres | 280 KB | 268 KB (−12) |
| **duelo** | 280 KB | **296 KB (+16)** |
| **cadena** | 280 KB | **314 KB (+34)** |

Salió de dos cortes: extraer a hojas propias la ceremonia (48 KB, solo la usan
sobres/duelo/cadena), el mapa de cadena, el modo narrado y los objetivos; y
**borrar la navegación vieja de `layout.css`** (303 líneas), rescatando las
reglas de la hamburguesa a `admin.css`, que es quien de verdad las usa.

**Duelo y cadena siguen por encima del original** y lo estarán mientras
`components.css` (85 KB) y `layout.css` (53 KB) se sirvan enteros. Queda chrome
muerto medido y localizado: `.cabecera*`, `.hero*`, `.auth*`, `.expansion-tarjeta`
y `.seccion-head` ya no aparecen en ningún marcado. Ese es el siguiente corte.

Pruebas:
```
node db/pruebas/probar_motor_movimiento.mjs
php  db/pruebas/probar_hoy_prioridad.php
php  db/pruebas/probar_plantilla_filtro.php
php  db/pruebas/probar_jugar_disposicion.php
```

---

## 0. DIAGNÓSTICO — QUÉ FALLA HOY

Cinco puntos, todos verificados sobre el código, no impresiones.

**1. El jugador con sesión iniciada aterriza en una página de marketing.**
`landing.php` es idéntica con y sin sesión salvo un botón: hero → cartas destacadas → expansiones → pie. Un jugador que vuelve el martes por la tarde no ve si tiene sobres pendientes, si le queda una misión a un paso, ni si alguien le ha lanzado un duelo. Tiene que adivinar y navegar. Es el fallo más caro del producto.

**2. La navegación móvil esconde once destinos detrás de una hamburguesa.**
`navbar.php` agrupa 11 enlaces (Sobres, Mazos, Duelos, Misiones, Cadenas, Colección, Álbum, Mercado, Perfil, Panel, Salir) en un panel desplegable. En un público mayoritariamente móvil, eso son 2 toques y una lectura de lista para cualquier acción. No hay barra inferior. No hay jerarquía: "Cadenas" pesa visualmente lo mismo que "Sobres".

**3. `album.php` y `coleccion.php` son la misma pantalla dos veces.**
Misma rejilla, mismo componente `render_carta()`, misma estética. La diferencia real es un filtro: todas las cartas del juego vs. las tuyas. Están en grupos distintos del menú ("Coleccionar" contiene las dos) y nada en la interfaz explica cuál abrir. Lo mismo pasa entre `perfil.php` y `configuracion.php`: los dos son "tu cuenta" y los dos tienen un bloque "Canjear un código" duplicado literalmente.

**4. 280 KB de CSS bloqueante en cada carga, en todas las páginas.**
`tokens + base + components + layout + iconos` = 286.743 bytes sin comprimir, cuatro `<link>` en el `<head>`, servidos enteros tanto en `login.php` como en `duelo.php`. `components.css` (132 KB) y `layout.css` (112 KB) llevan las reglas de las 17 pantallas juntas. En IONOS y en un móvil de gama baja eso es exactamente el problema de rendimiento que describes: no es el JavaScript, es el primer pintado.

**5. Todas las pantallas usan la misma plantilla: `section.seccion.wrap` + `h1` + `div.panel` apilados.**
`mazos`, `duelos`, `misiones`, `perfil`, `configuracion`, `mercado` comparten estructura, ritmo y proporciones. Un duelo en curso —lo más tenso del juego— se lee igual que la pantalla de cambiar la contraseña. No hay ninguna señal visual de "esto importa más". La emoción de un TCG está en la jerarquía, y aquí es plana.

---

## 1. DIRECCIÓN — "ASCUA"

Extraída de las 8 referencias de `referencias-ux/`: negro casi puro, naranja incandescente con degradado que quema hacia el rojo, tipografía de caja alta desproporcionada, rejillas bento con esquinas muy redondeadas, etiquetas micro en versalitas con tracking abierto y numeración `01 /`.

**Concepto:** la brasa. Un estadio de noche, el foco sobre el césped, el resto en negro. No es "gaming neón" (cian/magenta, saturado, infantil) ni el ámbar de trofeo sobrio del sistema anterior. Es calor, presión y foco.

**Voz de marca — "GRADA"** *(reemplaza al tono editorial-museístico actual: "El registro coleccionable de…")*

| Regla | Sí | No |
|---|---|---|
| Imperativo, verbo primero | «Abre el sobre» | «Puedes abrir un sobre» |
| Títulos ≤ 6 palabras | «TU PLANTILLA» | «Aquí tienes tu colección de cartas» |
| Vocabulario de fútbol, no de app | ficha, titular, banquillo, cantera, traspaso, fichaje | gestiona, explora, descubre, elemento |
| Cifras desnudas | «312 fichas · 47 %» | «Has conseguido 312 de 664 cartas» |
| Errores sin culpa | «No ha entrado. Vuelve a intentarlo.» | «Error: la operación ha fallado» |
| Mayúsculas solo en display y etiquetas | nunca en párrafos | |

**Renombrado de secciones (parte del rediseño, no cosmética):**

| Antes | Después |
|---|---|
| Colección | **Plantilla** |
| Álbum | *(fusionado en Plantilla → pestaña "Todas")* |
| Mazos | **Alineaciones** |
| Mercado | **Mercado de fichajes** |
| Misiones | **Objetivos** |
| Descartar repetidas | **Cortar del equipo** *(modo dentro de Plantilla)* |
| Cadenas de Partido | **Cadenas** *(nombre intacto: referencia a las cadenas de partidos de Inazuma Eleven)* |
| Configuración | *(pestaña dentro de Perfil)* |

---

## 2. PALETA

> **Nota de procedencia:** la base de datos de la skill no tiene ninguna paleta oscura + naranja (sus resultados fueron verde casino, azul marino institucional y gris fotográfico). Estos valores están **derivados a mano de las referencias** y verificados con la fórmula de contraste WCAG 2.2. Los ratios de abajo son cálculos reales, no estimaciones.

### Base y superficies

| Token | Hex | Uso |
|---|---|---|
| `--negro` | `#08070A` | Fondo de página. Único. |
| `--carbon` | `#121114` | Superficie: tarjetas, paneles, celdas. |
| `--carbon-alto` | `#1A181D` | Superficie sobre superficie: modal, popover, menú. |
| `--pozo` | `#050406` | Hundido: inputs, tablas, tracks de progreso. |
| `--linea` | `rgba(255,255,255,.07)` | Borde por defecto. |
| `--linea-viva` | `rgba(255,255,255,.16)` | Borde en hover / elemento seleccionado. |

### Texto

| Token | Hex | Contraste sobre `--negro` | Uso |
|---|---|---|---|
| `--hueso` | `#F5F3F0` | **18,2 : 1** | Texto principal, cifras. |
| `--ceniza` | `#A09CA6` | **7,5 : 1** | Texto secundario, metadatos, ayuda. |
| `--humo` | `rgba(255,255,255,.34)` | — | Solo decorativo. **Nunca texto.** |

### Acento — la brasa

| Token | Hex | Contraste sobre `--negro` | Uso |
|---|---|---|---|
| `--brasa` | `#FF5C1A` | **6,5 : 1** | Acento único de firma: CTA primario, foco, activo. |
| `--brasa-alta` | `#FF8A3D` | **8,6 : 1** | Hover, texto de enlace sobre negro, extremo claro del degradado. |
| `--brasa-honda` | `#C42D00` | — | Estado `:active`, extremo oscuro del degradado. |
| `--brasa-tinta` | `#140600` | **6,1 : 1** *(sobre `--brasa`)* | Texto sobre fondo brasa. |
| `--brasa-velo` | `rgba(255,92,26,.10)` | — | Fondo de chip / fila activa. |
| `--brasa-borde` | `rgba(255,92,26,.38)` | — | Borde de elemento acentuado. |

```css
--fuego: linear-gradient(147deg, #FF8A3D 0%, #FF5C1A 46%, #C42D00 100%);
--rescoldo: radial-gradient(120% 90% at 50% 0%, rgba(255,92,26,.28), transparent 62%);
```

`--fuego` es el relleno de todo CTA primario y de la superficie destacada de un bento.
`--rescoldo` es el halo de fondo del hero y de las ceremonias. Uno por pantalla, máximo.

**Por qué naranja incandescente y no el ámbar anterior:** el público son hombres de 18–30 acostumbrados a estética de esports (las propias referencias que has elegido son cartelería de CS2). El `#E8752A` actual es un ámbar sobrio de trofeo — correcto pero apagado; leído en un móvil a 400 nits al sol se desatura hasta parecer marrón. `#FF5C1A` mantiene saturación bajo brillo alto y aguanta el degradado hacia rojo sin ensuciarse.

### Semánticos — los colores del reglamento

Se conserva **el concepto** (el color viene del propio fútbol), con valores nuevos:

| Token | Hex | Texto legible | Significado |
|---|---|---|---|
| `--cesped` | `#2FD97E` | `--cesped-txt` `#87F7C0` | Éxito, confirmado, ganado. |
| `--amarilla` | `#FFC53D` | `--amarilla-txt` `#FFE0A0` | Aviso, acción reversible, "estás a un paso". |
| `--roja` | `#FF4438` | `--roja-txt` `#FFA69E` | Error, destructivo, derrota. |
| `--var` | `#4D9BFF` | `--var-txt` `#B3D2FF` | Informativo, neutro, tutorial. |

Cada uno con `-velo` (`rgba(…,.10)`) y `-borde` (`rgba(…,.36)`).
**El color nunca va solo:** siempre icono + etiqueta. Nadie tiene que distinguir verde de rojo para entender un estado.

### Estadísticas del jugador

`--ata` → `--cesped` · `--def` → `--roja` · `--tec` → `--brasa-alta`
Alias, no colores nuevos. Se declaran aparte porque el significado no es el arbitral (una defensa alta es buena) y para que retocarlos no arrastre a las alertas.

### Rarezas — 7 niveles

| Nivel | Token | Hex | Acabado |
|---|---|---|---|
| 1 Común | `--rz1` | `#8E92A0` | plano |
| 2 Poco común | `--rz2` | `#2FD97E` | plano |
| 3 Rara | `--rz3` | `#4D9BFF` | plano |
| 4 Épica | `--rz4` | `#A06BFF` | halo suave |
| 5 Legendaria | `--rz5` | `#FF8A3D` | degradado `--fuego` |
| 6 SRF | `--rz6` | `#FFFFFF` | degradado iris |
| 7 Numerada | `--rz7` | `#B8C4D9` | metal pulido, sin animación |

```css
--rz6-grad: linear-gradient(100deg,#FF5C1A,#FFC53D,#2FD97E,#4D9BFF,#A06BFF,#FF5C1A);
--rz7-grad: linear-gradient(115deg,#5F6A7D,#DDE5F2 42%,#828FA5 58%,#C7D2E4);
```

La 7 es platino frío a propósito: una numerada no es "más rara" que una SRF, es **otra cosa**. Si compartiera paleta con las cumbre se leería como un peldaño más de la misma escalera.

---

## 3. TIPOGRAFÍA

**Dos familias. Ni una más.** El presupuesto de fuentes en IONOS + gama baja no da para tres, y las etiquetas micro se resuelven con caja alta + tracking en lugar de una mono dedicada. Es una renuncia consciente frente a las referencias, que sí usan mono.

| Rol | Familia | Peso | Autoalojada |
|---|---|---|---|
| Display / títulos / etiquetas / botones | **Space Grotesk** | 500, 700 | `woff2` subset latino |
| Cuerpo / UI / cifras | **Inter** *(variable)* | 400, 500, 600 | `woff2` variable, ya presente |

Space Grotesk 700 en caja alta es exactamente la voz de las referencias: geométrica, ancha, agresiva sin ser condensada. Inter aguanta 14 px en una pantalla mala mejor que ninguna otra y trae `tabular-nums`, imprescindible para estadísticas alineadas.

### Escala — móvil primero (360 px) → escritorio (≥1024 px)

| Token | Móvil | Escritorio | Interlínea | Tracking | Peso | Caja |
|---|---|---|---|---|---|---|
| `--fs-display` | 44 px | 96 px | 0.92 | −0.035em | 700 | ALTA |
| `--fs-h1` | 32 px | 56 px | 1.00 | −0.03em | 700 | ALTA |
| `--fs-h2` | 24 px | 34 px | 1.08 | −0.02em | 700 | ALTA |
| `--fs-h3` | 19 px | 21 px | 1.2 | −0.01em | 600 | normal |
| `--fs-body` | 16 px | 16 px | 1.55 | 0 | 400 | normal |
| `--fs-body-sm` | 14.5 px | 14.5 px | 1.5 | 0 | 400 | normal |
| `--fs-label` | 12 px | 12 px | 1.2 | **+0.14em** | 500 | ALTA |
| `--fs-micro` | 11 px | 11 px | 1.3 | +0.1em | 500 | ALTA |

```css
--fs-display: clamp(2.75rem, 1.2rem + 7vw, 6rem);
--fs-h1:      clamp(2rem,   1.3rem + 3.2vw, 3.5rem);
--fs-h2:      clamp(1.5rem, 1.3rem + .9vw,  2.125rem);
--fs-h3:      clamp(1.1875rem, 1.15rem + .18vw, 1.3125rem);
```

**Reglas duras**
- 16 px es el mínimo del cuerpo. Nunca por debajo.
- Cifras siempre `font-variant-numeric: tabular-nums`.
- Ancho de párrafo tope `64ch`.
- `--fs-display` solo aparece **una vez por pantalla**. Si hay dos, una está mal.
- La caja alta nunca se aplica a un párrafo: solo a display, H1, H2, etiquetas y botones.

---

## 4. ESPACIADO, RADIOS, ELEVACIÓN

### Espaciado — base 4

```
--e-1: 4px   --e-2: 8px   --e-3: 12px  --e-4: 16px  --e-5: 24px
--e-6: 32px  --e-7: 48px  --e-8: 64px  --e-9: 96px  --e-10: 128px
```
1–4 dentro de componentes · 5–7 entre bloques · 8–10 entre secciones de página.

### Radios

```
--r-input: 6px    inputs, selects, checkbox
--r-chip:  10px   chips, insignias, botones pequeños
--r-carta: 18px   tarjeta de carta, celda de bento
--r-panel: 28px   panel, modal, bloque de hero
--r-pill:  999px  botones, filtros, avatar
```

### Elevación — 3 niveles + 1 halo

```css
--elev-1: inset 0 1px 0 rgba(255,255,255,.05), 0 2px 8px -4px rgba(0,0,0,.8);
--elev-2: inset 0 1px 0 rgba(255,255,255,.05), 0 16px 40px -20px rgba(0,0,0,.9);
--elev-3: 0 40px 90px -30px rgba(0,0,0,.95);
--halo:   0 0 0 1px rgba(255,92,26,.35), 0 18px 50px -22px rgba(255,92,26,.5);
```
`--elev-3` se reserva a lo que flota sobre el flujo: modal, carta en hover, ceremonia.
`--halo` marca **una sola cosa por pantalla**: la acción primaria o la carta recién obtenida.

### Puntos de corte — exactos

| Nombre | Ancho | Rejilla | Contenido | Canal |
|---|---|---|---|---|
| `xs` | 360 px | 4 col | 100 % | 16 px |
| `sm` | 480 px | 4 col | 100 % | 20 px |
| `md` | 768 px | 8 col | 100 % | 28 px |
| `lg` | 1024 px | 12 col | 1200 px | 32 px |
| `xl` | 1440 px | 12 col | 1360 px | 40 px |
| `2xl` | 1920 px | 12 col | 1560 px | 48 px |

Diseño móvil primero: todo se escribe para 360 px y se **añade** en `min-width`. Ninguna regla en `max-width`.

---

## 4B. LENGUAJE VISUAL — los ocho dispositivos

Sin esto, la paleta y la tipografía por sí solas dan una interfaz oscura correcta pero **plana**. Esto es lo que hace que se parezca a las referencias. Cada dispositivo tiene una regla de uso: si se usan todos a la vez, el resultado es ruido.

**1 · Tipografía a sangre (`.tipo-sangre`)**
La palabra display se sale del contenedor y la corta el borde del viewport. `overflow:hidden` en el padre, `margin-inline: -8vw` en el texto. En las referencias es la firma más reconocible: `STUDIOS`, `BRANDING`, `GANDER` cortadas por el canto.
*Regla:* una por pantalla, siempre en el hero o en el cierre. Nunca en medio del contenido.

**2 · Tipo detrás del sujeto (`.tipo-fondo`)**
La carta o el escudo van **delante** de la palabra gigante. Dos capas: `.tipo-fondo` con `color: transparent; -webkit-text-stroke: 1px var(--linea-viva)` o relleno `--carbon`, y encima la imagen. Da profundidad sin coste: son dos elementos apilados, no un 3D.
*Regla:* solo con arte recortado (carta, escudo, jugador sin fondo).

**3 · Rescoldo dominante (`.rescoldo`)**
El halo naranja no es sutil: en las referencias ocupa entre un tercio y la mitad del lienzo y quema desde detrás del sujeto. Dos capas superpuestas, una amplia y difusa y otra pequeña e intensa.
```css
.rescoldo::before{ background: radial-gradient(60% 55% at 50% 18%, rgba(255,92,26,.42), transparent 70%); }
.rescoldo::after { background: radial-gradient(28% 22% at 50% 12%, rgba(255,170,90,.55), transparent 72%); }
```
*Regla:* uno por pantalla. Si hay dos, la pantalla se convierte en una mancha.

**4 · Superficie invertida (`.celda--hueso`)**
Fondo `--hueso`, texto `--negro`. En las referencias, entre las tarjetas oscuras y las naranjas siempre hay **una blanca** — es lo que impide que el bento se lea como un bloque uniforme.
*Regla:* como mucho una por rejilla, y nunca la que lleva la acción primaria (esa es `--fuego`).

**5 · Rejilla técnica (`.trama`)**
Retícula de 1 px al 4 % de opacidad sobre el fondo, con desvanecido en los bordes. Coste cero: dos `linear-gradient` repetidos.
```css
.trama{ background-image:
  linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px),
  linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px);
  background-size: 56px 56px;
  mask-image: radial-gradient(85% 70% at 50% 30%, #000, transparent); }
```
*Regla:* solo en hero, hub y pantallas de duelo. Nunca bajo una rejilla de cartas: compite con el arte.

**6 · Filo brasa (`.filo`)**
Línea de 2 px con degradado `--fuego` en el borde superior de un panel destacado, desvanecida en los extremos. Marca importancia sin gritar.
*Regla:* solo en el panel «Lo siguiente» y en el bloque activo de un bento.

**7 · Metadatos de esquina (`.esquina`)**
Etiqueta micro en la esquina del panel: expansión, fecha, número de serie, `#04`. Las referencias de esports lo usan constantemente (`IEM KATOWICE`, `GROUP STAGE`, `2025`) y es lo que da el aire de ficha técnica.
*Regla:* solo cuando el dato es **real**. Un `#04` decorativo es exactamente el tipo de numeración vacía que hay que evitar.

**8 · Tira en marcha (`.tira`)**
Cinta horizontal en bucle con estado vivo: `TEMPORADA 03 · 3 SOBRES ESPERANDO · DUELO PENDIENTE · 47 % DE PLANTILLA ·`. Un `transform: translateX` infinito sobre contenido duplicado. Es de los efectos más baratos que existen y de los que más “producto vivo” aportan.
*Regla:* una por pantalla, y **solo con datos reales del jugador**. Una tira que repite eslóganes es decoración; una que dice cuántos sobres te esperan es navegación.

---

## 5. MOVIMIENTO — MOTION GRAPHICS

```css
--curva:      cubic-bezier(.2,.8,.2,1);    /* casi todo */
--curva-sale: cubic-bezier(.4,0,1,1);      /* salidas: más rápidas que entradas */
--curva-brio: cubic-bezier(.34,1.4,.5,1);  /* rebote: solo recompensa */
--curva-masa: cubic-bezier(.16,1,.3,1);    /* revelados largos, entrada con peso */

--t-toque:  140ms;   /* hover, focus, active */
--t-estado: 240ms;   /* cambio de estado, filtro, pestaña */
--t-panel:  320ms;   /* modal, hoja inferior, menú */
--t-revelado: 620ms; /* entrada de bloque al hacer scroll */
--t-fiesta: 900ms;   /* SOLO apertura de sobre y presentación de duelo */

--escalon: 70ms;     /* retardo entre hermanos en una entrada escalonada */
```

### 5.1 Tres niveles de motor — la respuesta honesta a «más animación en gama baja»

Más movimiento y móviles lentos son objetivos en conflicto. No se resuelve animando menos: se resuelve **animando distinto según el aparato**. El interruptor `data-motion` que ya existe pasa de dos estados a cuatro, y el nivel se decide una vez al cargar:

```js
// 'full' | 'lite' | 'reduce'  — la preferencia guardada del jugador siempre manda
const nucleos = navigator.hardwareConcurrency || 4;
const memoria = navigator.deviceMemory || 4;
const flojo   = nucleos <= 4 || memoria <= 4;
document.documentElement.dataset.motion =
  guardada ?? (reduceSistema ? 'reduce' : flojo ? 'lite' : 'full');
```

| Nivel | Qué corre | Quién lo ve |
|---|---|---|
| **`full`** | Todo: revelados escalonados, inclinación 3D, rescoldo latiendo, cifras rodando, tira en marcha, ceremonias GSAP | Escritorio y móvil moderno |
| **`lite`** | Solo opacidad y desplazamientos cortos. Sin 3D, sin latido, sin blur animado. Ceremonias reducidas a un fundido con escala | Gama baja detectada |
| **`reduce`** | Nada salvo fundidos de 120 ms. La tira se congela pero **sigue legible** | `prefers-reduced-motion` o elección del jugador |

En Ajustes hay un selector de tres opciones, no una casilla: **Completo · Ligero · Mínimo**, con el nivel detectado marcado como «recomendado para tu dispositivo». El jugador de gama baja que quiera el espectáculo puede pedirlo.

### 5.2 Entrada de página — la coreografía

Es lo que más separa «web» de «producto». Ocurre una vez, al cargar, y dura menos de un segundo.

| Orden | Elemento | Movimiento | Retardo |
|---|---|---|---|
| 1 | Rescoldo | `opacity 0→1` + `scale(1.15)→1` | 0 ms |
| 2 | Etiqueta micro | `opacity 0→1` + `translateY(8px)→0` | 90 ms |
| 3 | Display, **palabra a palabra** | cada palabra sube desde una máscara: `translateY(105%)→0` | 160 ms + 70 ms por palabra |
| 4 | Subtítulo | `opacity` + `translateY(14px)` | 380 ms |
| 5 | CTA | `opacity` + `scale(.96)→1` con `--curva-brio` | 470 ms |
| 6 | Bento | escalonado de celdas, `translateY(20px)` | 540 ms + 70 ms cada una |

El revelado de display se hace **por palabras, no por caracteres**: partir por letra multiplica los nodos del DOM por veinte y en un móvil de gama baja se nota. Se parte con `String.split(' ')` propio, no con SplitText (que además es de pago).

```css
.palabra{ display:inline-block; overflow:hidden; vertical-align:bottom }
.palabra > span{ display:inline-block; transform:translateY(105%);
  transition: transform var(--t-revelado) var(--curva-masa) }
.revelado .palabra > span{ transform:none }
```

### 5.3 Catálogo de motion graphics

| Efecto | Dónde | Cómo, exactamente | Nivel |
|---|---|---|---|
| **Revelado por palabras** | Todo H1 y display | máscara + `translateY(105%)→0`, escalón 70 ms | full · lite |
| **Escalonado al hacer scroll** | Rejillas, bentos, listas | `IntersectionObserver` (nunca un listener de scroll), `translateY(20px)` + opacidad, escalón 70 ms, se dispara **una vez** | full · lite |
| **Rescoldo latiendo** | Hero, ceremonias | `transform: scale(1 → 1.06)` y `opacity(.85 → 1)`, 6 s, `alternate`. Se anima la **transformación de una capa ya pintada**, jamás el gradiente ni un `filter: blur` | full |
| **Tira en marcha** | Hoy, hub | `translateX(0 → -50%)` sobre contenido duplicado, 28 s lineal. Pausa en `:hover` y en `:focus-within` | full · lite |
| **Cifras rodando** | Marcador, progreso, monedas | contador con `requestAnimationFrame`, 900 ms, `--curva-masa`, `tabular-nums` para que no baile el ancho | full |
| **Carta inclinada** | Rejilla, ficha, ceremonia | `rotateX/rotateY` máx. 8°, `perspective:900px`, más un brillo que recorre con `background-position`. Solo `@media (hover:hover) and (pointer:fine)` | full |
| **Brillo holográfico** | Rareza 5, 6 y 7 | degradado cónico desplazado por el puntero; en táctil, por el **giroscopio** con permiso, y si se deniega queda estático | full |
| **Transición entre pantallas** | Toda la navegación | `View Transitions API` nativa: la carta pulsada se convierte en la cabecera de su ficha (`view-transition-name`). Sin librería, coste casi nulo, y donde no exista simplemente no pasa nada | full · lite |
| **FLIP a la alineación** | Alineaciones | la ficha vuela al hueco midiendo primero y último rectángulo. `transform` puro | full · lite |
| **Barra que se llena** | Progreso de plantilla | `scaleX` sobre un pseudo-elemento, nunca `width` | full · lite |
| **Ceremonia del sobre** | Sobres | GSAP, ya existente, recalibrada a 900 ms. **Cargado bajo demanda**, fuera del paquete base | full · lite reducida |
| **Presentación de duelo** | Duelo | Los dos escudos entran desde lados opuestos, chocan, el rescoldo destella una vez | full · lite reducida |

### 5.4 Reglas de rendimiento — no negociables

- Solo `transform` y `opacity`. **Jamás** `width`, `height`, `top`, `left`, `box-shadow`, `filter` ni `background-position` en bucle animado.
- `will-change` se pone **al empezar** la animación y se quita al terminar. Nunca fijo en CSS: un `will-change` permanente en cincuenta cartas reserva cincuenta capas de GPU y es exactamente cómo se tumba un móvil de gama baja.
- Ninguna animación de scroll usa un listener de `scroll`. `IntersectionObserver` o `animation-timeline: view()` donde exista.
- El presupuesto de la entrada de página es **1 segundo**. Pasado ese punto la interfaz está quieta y esperando al jugador.
- Nada anima por encima de 60 elementos a la vez. Las rejillas largas escalonan solo las dos primeras filas; el resto aparece ya colocado.
- GSAP no entra en el paquete base. Solo lo cargan `sobres.php` y `duelo.php`, bajo demanda.

### 5.5 Microinteracciones con propósito

| Momento | Qué pasa | Duración |
|---|---|---|
| Sobre abierto, carta revelada | escala 0.92 → 1 con `--curva-brio`, `--halo` que decae y el rescoldo destellando una vez | 900 ms |
| Rareza 5+ obtenida | el brillo holográfico recorre la carta una vez, de canto a canto | 900 ms |
| Carta añadida a alineación | la ficha vuela al hueco (FLIP), el hueco se ilumina | 320 ms |
| Duelo: gol | vibración de 8 px en el marcador, la cifra rueda con `--curva-brio` | 240 ms |
| Guardado correcto | el botón se convierte en ✓ **en su sitio**, sin toast | 240 ms |
| Validación fallida | el campo se desplaza 4 px lateral ×2, borde `--roja`, texto bajo el campo | 240 ms |
| Carga de datos | esqueleto con la **forma exacta** del contenido final, con un barrido de brillo (nunca un spinner centrado) | — |
| Insignia de pendiente | aparece con `scale(0)→1` y rebote, una sola vez por sesión | 240 ms |

### Estados obligatorios — los ocho

Todo control interactivo define los ocho. Sin excepción.

| Estado | Especificación |
|---|---|
| `default` | según variante |
| `hover` | `translateY(-2px)`, borde → `--linea-viva`, 140 ms. **Solo en `@media (hover:hover)`** |
| `active` | `translateY(0) scale(.98)`, fondo → `--brasa-honda`, 100 ms |
| `focus-visible` | `outline: 2px solid var(--brasa-alta); outline-offset: 3px` — anillo idéntico en todo el sitio |
| `disabled` | `opacity:.4`, `cursor:not-allowed`, sin hover, **atributo `disabled` real** |
| `loading` | ancho congelado, texto → esqueleto pulsante, `aria-busy="true"`, control bloqueado |
| `error` | borde `--roja`, mensaje **debajo del propio campo**, `aria-describedby`, `aria-invalid` |
| `vacío` | icono trazo 1,5 px + título + una frase + **un botón que resuelve el vacío** |

**Objetivo táctil mínimo 44 × 44 px** con 8 px de separación. Si el icono mide 24, el área de toque se extiende con pseudo-elemento, no agrandando el icono.

---

## 6. MAPA DE SECCIONES

### 6.1 Pantallas de hoy → mañana

| Hoy | Mañana | Decisión |
|---|---|---|
| `landing.php` | `landing.php` **solo sin sesión** | Reescrita entera: pasa de escaparate a captación |
| — | **`hoy.php` ← NUEVA** | Portada del jugador con sesión. Lo más importante del rediseño |
| `album.php` | *(desaparece)* | **Fusionada** en Plantilla, pestaña "Todas" |
| `coleccion.php` | `plantilla.php` | Absorbe álbum y descarte |
| `descartar.php` | *(desaparece)* | **Fusionada**: modo "Cortar" dentro de Plantilla |
| `sobres.php` | `sobres.php` | Se mantiene. Es la ceremonia, merece pantalla propia |
| `mazos.php` | `alineaciones.php` | Se mantiene, reordenada |
| `duelos.php` + `duelo.php` | `duelos.php` + `duelo.php` | Se mantienen |
| `misiones.php` | **`jugar.php` ← NUEVA (hub)** | Fusionada como sección |
| `cadenas.php` + `cadena.php` | `jugar.php` + `cadena.php` | Índice fusionado en el hub; el nodo mantiene pantalla |
| `perfil.php` | `perfil.php` con pestañas | Absorbe configuración |
| `configuracion.php` | *(desaparece)* | **Fusionada**: pestaña "Ajustes" |
| `usuario.php` | `usuario.php` | Se mantiene (perfil público de otro) |
| `login.php` + `registro.php` | `acceso.php` | **Fusionadas** en una con dos pestañas |
| `styleguide.php`, `diagnostico.php`, `info.php`, `_tmp_sesion.php` | *(fuera del producto)* | A `_dev/`, con `.htaccess` que las cierra |

**Balance: 17 pantallas → 11.** Seis fusiones o eliminaciones, dos creaciones.

### 6.2 Navegación — barra inferior de 5 en móvil

Sustituye a la hamburguesa de once. Cinco destinos, ni uno más (regla dura del sistema):

```
 HOY      JUGAR      PLANTILLA      MERCADO      PERFIL
```

- **Jugar** es un hub, no un enlace: abre `jugar.php` con Sobres, Duelos, Objetivos y Cadenas como cuatro bloques bento.
- Sobre el icono va una **insignia numérica** cuando hay algo pendiente (sobres sin abrir, duelo esperándote, objetivo completable). Ese es el motor de retorno que hoy no existe.
- En escritorio (≥1024 px) la misma estructura pasa a barra superior, con el saldo de monedas y el avatar a la derecha.
- Se conservan: `skip-link`, `aria-current="page"`, el foco visible.

### 6.3 Orden de secciones por pantalla

#### `landing.php` — solo sin sesión · objetivo: registro
Antes: hero → destacadas → expansiones → pie.
**Ahora:**
1. **Hero** — display a sangre sobre `--rescoldo`, una carta legendaria en 3D suave, un CTA: «Abre tu primer sobre gratis».
2. **Qué es esto** ← *nueva* — tres frases. Un visitante de fuera de la Superliga no sabe qué está viendo. Hoy no se lo cuenta nadie.
3. **Cómo se juega en 3 pasos** ← *nueva* — Abre · Alinea · Compite. Bento de 3.
4. **Las 7 rarezas** ← *nueva* — la escalera de rareza es el gancho de un TCG y hoy está totalmente escondida.
5. **Cartas destacadas** — *se mantiene*, pero después de explicar qué son.
6. **Prueba social** ← *nueva* — jugadores registrados, cartas repartidas, duelos jugados, enlace a Discord.
7. **CTA de cierre** + pie.

*Por qué este orden:* hoy la landing enseña producto antes de explicar el producto. Un usuario de fuera del nicho ve una rejilla de caras que no conoce y se va. Explicar → demostrar → probar → convertir.

#### `hoy.php` — NUEVA · portada con sesión · objetivo: una acción clara en 3 segundos
1. **Marcador personal** — nombre, monedas, racha, % de plantilla. Una línea, cifras grandes.
2. **Lo siguiente** — **una** tarjeta a ancho completo con `--halo`: la acción más valiosa ahora mismo (sobre disponible > duelo esperando > objetivo a un paso > misión diaria).
3. **Bento de 4** — Sobres · Duelos · Objetivos · Cadenas, cada uno con su cifra pendiente.
4. **Últimas fichas** — carrusel horizontal de lo obtenido recientemente.
5. **Movimiento en la liga** ← *nueva* — qué han sacado otros, qué se vende en el mercado. Es la prueba social que retiene.

#### `plantilla.php` — Colección + Álbum + Descarte fusionadas
1. **Cabecera de progreso** — «312 / 664 · 47 %» con barra. La cifra es el motor del coleccionismo y hoy está enterrada.
2. **Conmutador Mías / Todas** — resuelve la confusión Álbum-vs-Colección de un toque.
3. **Filtros en hoja inferior** en móvil, barra lateral pegajosa en escritorio. Nunca una fila de selects que empuja la rejilla.
4. **Rejilla** — 2 col en 360 px, 3 en 480, 4 en 768, 6 en 1024.
5. **Modo Cortar** — botón que activa selección múltiple sobre la misma rejilla. Deja de ser una pantalla aparte.

#### `jugar.php` — NUEVA · hub
Bento asimétrico: **Sobres** ocupa 2×2 (es lo que más engancha), Duelos 2×1, Objetivos 1×1, Cadenas 1×1. Cada bloque enseña su estado real, no solo su nombre.

#### `perfil.php` — Perfil + Configuración fusionadas
Cabecera con avatar y cifras → pestañas **Resumen · Ajustes · Cuenta**. El bloque «Canjear un código», hoy duplicado en las dos pantallas, existe una sola vez, en Resumen.

#### `acceso.php` — Login + Registro fusionadas
Una pantalla, dos pestañas, sin recarga. Hoy son dos páginas casi idénticas y el enlace entre ellas obliga a un viaje al servidor.

### 6.4 Los tres puntos de fricción y su solución

| # | Fricción | Solución |
|---|---|---|
| 1 | **Once destinos en una hamburguesa** en un producto mayoritariamente móvil | Barra inferior de 5 + hub `jugar.php` + insignias de pendiente |
| 2 | **Álbum vs Colección**: dos pantallas gemelas, ninguna pista de cuál abrir | Fusión en `plantilla.php` con conmutador Mías/Todas |
| 3 | **El jugador con sesión aterriza en marketing** y no sabe qué hacer | `hoy.php` con una única tarjeta «Lo siguiente» destacada con `--halo` |

*(El cuarto, los 280 KB de CSS, se resuelve en la fase de implementación: crítico en línea + hoja por pantalla.)*

### 6.5 Camino feliz y camino de recuperación

**Camino feliz (jugador nuevo):**
`landing` → «Abre tu primer sobre gratis» → `acceso` (registro, 3 campos) → **onboarding de 3 pasos** ← *nuevo* → ceremonia del primer sobre → «Tienes 5 fichas. Móntate una alineación» → `alineaciones` → primer duelo PvE guiado → `hoy` con la insignia del siguiente objetivo.

**Camino de recuperación:**

| Fallo | Qué ve el jugador |
|---|---|
| Abandona el registro a medias | El correo queda guardado en `localStorage`; al volver, «Casi lo tenías» y el campo relleno |
| Se corta la conexión en un duelo | Banner pegajoso `--amarilla`: «Se ha perdido el partido. Reconectando…» + reintento automático 3× + botón manual. El duelo **nunca** se pierde en silencio |
| Sobre que no llega a abrirse | La ceremonia se salta y las cartas aparecen en una lista con «El sobre se abrió. Aquí lo que salió.» Nunca una pantalla en blanco |
| Rejilla vacía por filtros | Estado vacío con **el filtro culpable nombrado** y un botón «Quitar el filtro de rareza» |
| Plantilla vacía (usuario nuevo) | Ilustración + «Tu plantilla está vacía» + «Abrir un sobre» |
| Error 500 / caída | `--roja`, «Se nos ha ido el balón fuera», enlace a Discord, botón de reintento |

---

## 7. ACCESIBILIDAD — NO NEGOCIABLE

- Contraste **AA verificado** en la tabla de §2. Cuerpo ≥ 4,5:1, display ≥ 3:1.
- **Foco:** `outline: 2px solid var(--brasa-alta); outline-offset: 3px`, idéntico en todos los controles. Nunca `outline:none` sin sustituto.
- `scroll-padding-top: var(--nav-h)` para que el foco de teclado no quede bajo la barra (WCAG 2.2 SC 2.4.11).
- Toda modal atrapa el foco, cierra con `Esc` y **devuelve el foco al disparador**.
- Cambios asíncronos anunciados por `aria-live="polite"`; los errores por `aria-live="assertive"`.
- El color **jamás** transmite significado solo: siempre icono + texto.
- Iconos SVG o fuente subseteada. **Cero emojis como iconos.**
- Toque mínimo 44 × 44 px, 8 px de separación.
- `hover` siempre dentro de `@media (hover:hover)`: en táctil, un hover pegado es un bug.
- Zoom nunca bloqueado (`maximum-scale` prohibido).
- `prefers-reduced-motion` respetado vía el mecanismo `data-motion` existente.

## 8. RENDIMIENTO — restricciones IONOS + gama baja

- **CSS crítico en línea** (< 6 KB) + hoja por pantalla cargada por `$cssExtra`. Se acaban los 280 KB en todas las páginas.
- Solo `transform` y `opacity` en animación. `content-visibility: auto` en las filas de rejilla fuera de pantalla.
- Fuentes autoalojadas con subset y `font-display: swap`. Dos familias, no tres.
- Imágenes de carta en WebP con `width`/`height` explícitos → CLS < 0.1.
- Rejillas largas: `loading="lazy"` a partir de la fila 2, `fetchpriority="high"` en la primera.
- GSAP solo donde ya está (ceremonias y duelo), cargado bajo demanda. **No entra en el CSS base.**

---

## 9. QUÉ SE CONSERVA DEL SISTEMA ANTERIOR — y por qué

Tres piezas, todas justificadas, ninguna cosmética:

1. **El mecanismo `data-motion`** de `partials/head.php`. Está bien resuelto y una `@media (prefers-reduced-motion)` no se puede sobrescribir desde JS.
2. **Los iconos subseteados** de `assets/css/iconos.css` (17,9 KB frente a 679 KB de unpkg). Se re-genera con los iconos nuevos, no se descarta.
3. **El concepto** de semánticos tomados del reglamento de fútbol. Los valores hexadecimales son todos nuevos.

Todo lo demás —`tokens.css`, `base.css`, `components.css`, `layout.css`— se reemplaza.

---

## 10. RONDAS DE CORRECCIONES POST-BLOQUE 7d

La partición de los tres monolitos (bloque 7d) cortó por líneas contiguas, no
por componente. Dos rondas de QA visual encontraron el mismo patrón de fallo
repetido: una pantalla que perdió su hoja de estilos al mudarse, o una hoja
que se llevó de más algo que no le tocaba.

**Ronda 1** (capturas en `cosas_ux_mejorar/`): sesión sin botón de cerrar en
`navbar.php` (regresión funcional, no cosmética — restaurado con `logout.php`
simplificado); `.rot` solo vivía en `hoy.css` y `partials/cabecera.php` lo usa
en 8 pantallas más → promovido a `ascua.css`; `cadenas.php` sin
`.cadena-tarjeta` (la 7d la dejó en `cadena.css`, pensada para el mapa, sin
dársela a la lista) → añadida a `$cssExtra`; `.pastilla .mono` heredaba
`letter-spacing` y el «11/11» salía separado → override con `normal`; rejilla
de rarezas con columnas fijas desbordando un panel de 300px →
`auto-fit`/`minmax`; fondo negro en `.ficha-arte` y `.hueco-ficha` por un
glow radial demasiado débil sobre `--pozo` → reforzado en los dos sitios.

**Ronda 2** (`duelo.php` + minijuegos, captura de 4 pantallas): el marcador
final salía como texto corrido porque `duelo.php` nunca cargó `cadena.css` —
esa hoja no es solo el mapa de cadena, también pinta `.partido-lineas` y
`.partido-alineaciones` → añadida a `$cssExtra`. El título de la modal en
juego se veía a la izquierda porque `.modal-head` es una fila
`space-between` pensada para título + botón de cerrar, y esta modal
**deliberadamente no tiene botón de cerrar** (`data-sin-cerrar`), así que el
único hijo heredaba `justify-content` de una fila diseñada para dos → fix con
`.modal-head > :only-child { width:100%; text-align:center }`, verificado
contra las ~18 modales del sitio (todas con 2 hijos, ninguna rota).

El hallazgo mayor: la cola de `partido.css` post-7d escondía **cinco**
componentes sin relación con partidos — arrastrados por venir justo después
de un bloque de "PERFIL" en el monolito original. `.perfil-cabecera` y
`.campo-inline` eran código muerto (confirmado por grep exacto de `class="`,
no solo de nombre) y se borraron. `.ajustes-grid`/`.ajustes-foto`
(pestaña Ajustes de `perfil.php`), `.barra-filtros`/`.barra-filtros-acciones`
(`mercado.php` + 4 pantallas del panel) y **el pie de página entero**
(`.pie`, `.pie-grid`, `.pie-col`, `.pie-redes`, `.pie-legal`) se movieron a
`ascua.css`. Ese último era el motivo real de la queja «no deja margen debajo
de Payo Aguao»: no era un bug de `duelo.php`, era el footer sin estilo en
**toda página que no cargase `partido.css`** — es decir, casi todo el sitio.

**Lo que NO hacía falta reescribir:** el propio `partido.css` (una vez
separado de los cinco polizones) ya estaba bien resuelto para móvil —
`container-type: inline-size` en vez de `auto-fit` para evitar el 2+1 en el
minijuego de opciones, `minmax()` sin `aspect-ratio` fija en el medidor y en
la portería de la tanda (evita el recorte de texto documentado en el propio
CSS como «la trampa del §8»), `touch-action: none` en el lienzo de arrastre,
y la alternativa de botones visible junto al arrastre por WCAG 2.2 SC 2.5.7.
Verificado con inyección de DOM sintético a 375px: opciones (1 columna,
70-89px de alto), medidor (3 zonas ~105px, pista de 62px, botón de 44px),
zonas de gol (6 botones 99-105×96px), tanda (grid 2×2, 158×84px por hueco) y
arrastre (lienzo 343×197px, sin desbordar). Ninguno necesitó tocarse.

**Lección para el resto del sitio:** cualquier partición de monolito debe
revisarse por *componente*, no por *bloque contiguo* — el riesgo de que un
corte por líneas se lleve algo de más (o dé algo de menos a la pantalla que
lo necesitaba) es sistemático, no un accidente puntual del bloque 7d.
