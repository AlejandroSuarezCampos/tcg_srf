# Superliga Frontier TCG — contexto de trabajo

> Documento de traspaso. Léelo entero antes de tocar código.
> Si trabajas desde otro equipo, este fichero más el repo son todo el contexto
> necesario: no hace falta la conversación anterior.

---

## Cómo arrancar en un chat nuevo

Estás recogiendo un proyecto con las **Fases 0 y 1 terminadas y aprobadas**.
No las rehagas ni las revises salvo que Alejandro lo pida.

**Lo primero que tienes que hacer, en este orden:**

1. Leer este documento entero.
2. Comprobar que el entorno responde: Apache levantado (`Get-Process httpd`) y
   `http://localhost/tcg_srf/styleguide.php` carga. Ahí está el sistema de
   diseño completo y funcionando; míralo antes de escribir nada.
3. Verificar que el repo está intacto:
   `for f in *.php partials/*.php components/*.php; do C:/xampp/php/php.exe -l "$f"; done`
4. **Antes de escribir código de la Fase 2, presentar un plan corto y esperar
   el visto bueno.** Es la forma de trabajar acordada con Alejandro: plan →
   aprobación → implementación → resumen de cierre.

**Orden de las fases restantes:** Fase 2 (§10) y después Fase 3 (§11). No se
mezclan. Cada una se cierra por completo y deja el sitio desplegable antes de
abrir la siguiente.

**Si Alejandro no dice por dónde empezar**, empieza por la Fase 2 y, dentro de
ella, por la migración de base de datos, porque todo lo demás depende de ella.

---

## 0. Qué es esto

TCG coleccionable fan-made de la **Superliga Frontier**, la liga de Inazuma
Eleven: Victory Road. Las cartas representan jugadores, presidentes,
entrenadores y escudos reales de una comunidad activa, no personajes de ficción.

- **Stack:** PHP 8 + MariaDB sobre XAMPP. Sin framework, sin build, sin npm.
- **Raíz del proyecto:** `C:\xampp\htdocs\tcg_srf` (servido en `http://localhost/tcg_srf/`).
- **Ejecutado por una sola persona** (Alejandro), sin fecha de lanzamiento fija.
- **Gratuito**, sin monetización, exclusivo para participantes de la liga.
- **Legal:** proyecto fan-made sin ánimo de lucro. Inazuma Eleven es propiedad
  de Level-5. Sin afiliación. Este aviso debe estar visible en **todas** las
  páginas (lo pone `partials/footer.php`, incluido en todas).

El documento maestro de marca es
`branding/Brand-Identity-Briefing-Superliga-Frontier-TCG.docx` (38 secciones).
Si algo de diseño no está claro aquí, la respuesta está ahí.

---

## 1. Estado del trabajo

| Fase | Contenido | Estado |
|---|---|---|
| **Fase 0 — Fundamentos** | Tokens de diseño, componente de tarjeta de carta construido en aislamiento, guía de estilo | ✅ Terminada y aprobada |
| **Fase 1 — Núcleo** | Las 9 pantallas existentes migradas al sistema nuevo, aviso legal en todas | ✅ Terminada y aprobada |
| **Fase 2 — Sistemas nuevos** | Deck Builder, Duelos, Misiones, minijuegos (§10) | ⬜ **Siguiente** |
| **Fase 3 — Pulido y escala** | Rediseño de `panel/`, motion final, doc de expansiones (§11) | ⬜ Pendiente |

**Regla de trabajo:** una fase se cierra por completo y deja el sitio
desplegable antes de abrir la siguiente. Nunca se mezclan. Al terminar cada
fase, resumir qué cambió y por qué antes de continuar.

### Qué cubrió cada fase ya cerrada

**Fase 0** — No se tocó ninguna pantalla hasta tener el sistema debajo:
`tokens.css` con todas las variables, Geist autoalojada, `components.css` con
los 16 componentes del briefing, el componente de tarjeta con todos sus
estados, y `styleguide.php` como página de validación. Alejandro aprobó el
marco de carta antes de pasar a la Fase 1.

**Fase 1** — Migración pantalla a pantalla, sin dejar ninguna a medias:
`navbar` → `landing` → `login`/`registro` → `sobres` (con la ceremonia nueva)
→ `coleccion` → `album` → `mercado` → `perfil`/`configuracion`. Se añadió el
aviso legal a todas mediante `partials/footer.php` y se retiró el CSS y el JS
antiguos a `_legacy/`. Después de la aprobación hubo una ronda de correcciones
sobre problemas reales de uso (placa de sobres, filtros pegajosos,
desbordamiento en móvil, ceremonia que no cabía, selector de venta).

### Lo que ya está hecho y no hay que rehacer

- Sistema de tokens completo (color, tipografía, espaciado, radio, sombra,
  motion, breakpoints).
- Geist Sans + Geist Mono **autoalojadas** en `assets/fonts/` (variables,
  100–900, 4 ficheros `.woff2`). Fallback a Inter.
- Componente único de tarjeta de carta, con todos sus estados.
- Las 9 pantallas del núcleo migradas: `landing`, `login`, `registro`,
  `sobres`, `coleccion`, `album`, `mercado`, `perfil`, `configuracion`.
- Navegación reagrupada en clústeres, lista para recibir Duelos y Misiones.
- Ceremonia de apertura de sobres escalada por rareza.
- Guía de estilo viva en `styleguide.php`.

---

## 2. Arquitectura de ficheros

```
tcg_srf/
├── partials/
│   ├── head.php          ← abre el documento: fuentes, CSS, <body>, skip-link
│   ├── footer.php        ← pie + AVISO LEGAL + carga de ui.js
│   └── ceremonia.php     ← marcado del modal de apertura de sobres
├── components/
│   └── carta.php         ← EL componente de tarjeta (render_carta, carta_html)
├── navbar.php            ← navegación, incluida por todas las páginas
├── assets/
│   ├── css/
│   │   ├── tokens.css      (235 l) variables + @font-face. Fuente de verdad.
│   │   ├── base.css        (214 l) reset, tipografía, foco, layout de página
│   │   ├── components.css (1225 l) los 16 componentes + ceremonia
│   │   ├── layout.css      (555 l) nav, hero, cabecera, filtros, pie
│   │   └── styleguide.css   (75 l) solo para styleguide.php
│   ├── js/
│   │   ├── ui.js         (300 l) modales, toasts, tabs, nav, plegables, reveal
│   │   ├── ceremonia.js  (144 l) SRF.ceremonia(cartas)
│   │   ├── sobres.js      (65 l) compra → llama a la ceremonia
│   │   ├── mercado.js    (149 l) confirmación + selector visual de venta
│   │   ├── album.js       (69 l) filtrado en cliente
│   │   ├── perfil.js      (58 l) canje de códigos
│   │   └── configuracion.js (32 l) vista previa de foto
│   ├── fonts/            ← Geist autoalojada (4 .woff2)
│   ├── ajax/             ← canjear_codigo.php, monedas.php (sin tocar)
│   └── async/js/scriptsAsync.js ← actualizarMonedasNav() (sin tocar)
├── db/
│   ├── conexion.php      ← instancia $db (sin tocar)
│   ├── consultas.php     ← clase Tcg, 1055 l, capa PDO (sin tocar)
│   └── tcg.sql
├── panel/                ← admin, TODAVÍA CON EL SISTEMA VIEJO (Fase 3)
├── _legacy/              ← CSS y JS retirados. Nadie los referencia.
└── styleguide.php        ← guía de estilo viva
```

### Esqueleto de una página

```php
<?php
session_start();
require_once __DIR__ . '/db/conexion.php';
require_once __DIR__ . '/components/carta.php';

// ... lógica ...

$paginaTitulo = 'Mercado';
$paginaDesc   = 'Descripción para el <meta>.';
include __DIR__ . '/partials/head.php';

$activePage = 'mercado';
include __DIR__ . '/navbar.php';
?>

<header class="cabecera"> ... </header>
<main id="contenido" class="seccion wrap"> ... </main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
```

`head.php` acepta `$base` (prefijo relativo: `''` en la raíz, `'../'` en
`panel/`) y `$cssExtra` (array de hojas adicionales).

---

## 3. El componente de tarjeta

Vive en `components/carta.php`. **Nunca se copia su marcado con variaciones.**
Si una pantalla necesita algo distinto, se añade una opción al componente.

```php
render_carta($cromo, $opts);   // imprime
carta_html($cromo, $opts);     // devuelve string (lo usa la ceremonia por AJAX)
render_rareza($idRareza, $nombreRareza);  // etiqueta suelta
```

`$cromo` usa las claves que ya devuelven las consultas existentes: `nombre`,
`imagen`, `posicion`, `equipo`, `id_rareza`, `rareza`, `afinidad`,
`afinidad_imagen`.

Opciones (`$opts`):

| Clave | Efecto |
|---|---|
| `tamano` | `'sm'` / `'md'` (defecto) / `'lg'` |
| `href` | la carta pasa a ser un enlace interactivo |
| `poseida` | `false` ⇒ silueta apagada con candado (álbum) |
| `protegida` | `true` ⇒ insignia de bloqueada para venta |
| `precio` | int ⇒ insignia de precio (mercado) |
| `seleccionada` | `true` ⇒ anillo ámbar (deck builder) |
| `stats` | `['ATA'=>88,'DEF'=>72,'TÉC'=>91]`, hasta 3 |
| `acciones` | HTML flotante sobre la carta |
| `pie` | HTML al final del marco |
| `datos` | `['equipo'=>'x']` ⇒ atributos `data-*` para filtros de cliente |
| `clase`, `lazy` | clases extra / carga diferida de la imagen |

### Tres reglas que el componente garantiza

1. **El arte se muestra siempre completo.** `object-fit: contain`, nunca
   `cover`. La imagen va **posicionada en absoluto** contra la placa: con
   `height:100%` a secas el porcentaje no resuelve (la altura de la placa
   viene de `aspect-ratio`) y un arte muy alto desbordaba y se recortaba.
   Esto ya se rompió una vez; no lo cambies sin volver a medirlo.
2. **La rareza lleva marca no cromática** además del color: 0/1/2/3 chevrones
   dibujados en CSS, corona para legendaria, destello para SRF.
3. **Todo arte lleva texto alternativo.**

---

## 4. Sistema de diseño

### Color (`tokens.css`)

```
--void #0B0C10   --panel #16181D   --frost #EDEEF1   --frost-dim #93959F
--amber #E8752A  --amber-light #FFB168  --amber-ink #2B1204
--success #3DDC9B  --warning #F2B134  --danger #F0554A  --info #5B96F2
```

Los semánticos son literalmente los colores de las tarjetas arbitrales del
fútbol (amarilla = aviso, roja = falta). Es una decisión deliberada, no un
sistema genérico.

### Rarezas

| id | Nombre | Prob. | Marca no cromática |
|---|---|---|---|
| 1 | Común | 60 % | sin adorno |
| 2 | Poco común | 25 % | 1 chevrón |
| 3 | Raro | 10 % | 2 chevrones |
| 4 | Épico | 3,5 % | 3 chevrones |
| 5 | Legendario | 1 % | corona + borde metálico |
| 6 | SRF | 0,5 % | borde arcoíris animado + aura + barrido |

La **SRF tiene que ganar visualmente a la legendaria sin discusión**: es el
momento más espectacular del producto y la única rareza con animación por
defecto. Lleva cuatro señales que ninguna otra usa (borde arcoíris en
movimiento, aura exterior que late, tinte multitono sobre el arte y barrido
diagonal de luz).

### Tipografía

Geist Sans para UI. **Geist Mono solo para datos**: monedas, estadísticas,
contadores, marcas de tiempo. Nunca para texto editorial.

Escala: display 56–96/700 (solo hero y revelado SRF) · H1 40–48/700 ·
H2 28–32/700 · H3 20–22/600 · body 15–16/400–500 · caption 12–13/600
mayúsculas tracking +8 %.

### Espaciado, radio, motion

- Espaciado: `--space-1..8` = 4·8·12·16·24·32·48·64px.
- Radio: 8 (controles) / 12 / 16 / 22px (carta y modales).
- Motion: una sola curva `--ease`. `--t-micro` 160ms (hover/focus),
  `--t-media` 380ms (estados), `--t-ceremonia` 700ms **solo para sobre y duelo**.

### Iconografía

Phosphor Icons por CDN, pinneado a `@2.1.1` (pesos regular, bold y fill).
Regular para navegación y estados neutros; **bold/fill solo en celebración**
(duelo ganado, logro, carta SRF).

Cuidado: no dependas de un glifo de Phosphor para información crítica. El
check del selector de venta y los chevrones de rareza van dibujados en CSS
precisamente por eso.

---

## 5. Principios que gobiernan cada decisión

1. **El arte manda.** La interfaz sirve a la ilustración, nunca compite.
2. **Un acento, cero ruido.** El ámbar solo en lo que importa de verdad.
3. **La rareza se lee sin color.** Redundancia no cromática siempre.
4. **Ceremonia con propósito.** Motion largo solo en sobre y duelo.
5. **Denso pero nunca abarrotado.**
6. **Un solo chiste, dos veces** (ver tono).
7. **Se adapta, no se recorta.**

---

## 6. Tono y voz — límite estricto

Editorial y serio, como una ficha oficial de competición. Sin argot gamer, sin
superlativos vacíos ("¡increíble!"), sin bromear con nombres de jugadores
reales.

El humor se limita a **exactamente dos guiños, ninguno más**:

1. **"Superruina Frontier"** — solo en contextos secundarios o informales
   (loading states, redes, easter eggs). **Nunca en el lockup principal del
   logo**, que siempre es "Superliga Frontier · TCG".
2. **"a ese Gonzalo le gano fácil"** — tratada como cita histórica de folclore
   de liga, en espacios no críticos (pie de página, estados vacíos, 404).
   Está en `partials/footer.php`.

Si generas microcopy nuevo, contrástalo con esta regla antes de darlo por
bueno. No inventes nombres, eslóganes ni chistes nuevos.

> Nota: el `<h1>` de la portada lo editó Alejandro a mano después de la
> migración. Respeta lo que haya ahí; no lo revierta.

---

## 7. Accesibilidad — no negociable (WCAG 2.2)

- Contraste ≥4.5:1 en texto normal, ≥3:1 en texto grande. **Los 14 pares del
  sistema están medidos y pasan.** El peor es tinta ámbar sobre botón primario
  (5,89:1).
- Foco visible en todo elemento interactivo, nunca tapado.
- Objetivo táctil mínimo 24×24px, estándar interno 44×44px.
- Todo operable por teclado: filtros, modales (foco atrapado + Esc), ceremonia
  de sobre (saltable), futuras salas de duelo.
- Si el deck builder usa arrastrar y soltar, **debe tener alternativa por
  tap/clic desde el primer lanzamiento** (SC 2.5.7).
- `prefers-reduced-motion` cubre también las ceremonias.
- Regiones `aria-live` para resultados de sobre y duelo.

Errores de accesibilidad ya encontrados y corregidos (no los reintroduzcas):

- Texto blanco sobre el holográfico SRF caía a 1,9:1 → lleva placa oscura
  debajo, y el barrido de la etiqueta va **por debajo** del texto (`z-index:0`).
- Enlaces del pie a 20px de alto → llevan `padding-block`.
- Botón de hamburguesa comprimido a 20px por el flex → `flex-shrink: 0`.

---

## 8. Trampas conocidas del entorno y del código

### Entorno

- **No hay Python real** en el equipo original: `python`/`python3`/`py` son el
  alias stub de Microsoft Store y fallan. No hay `pandoc`.
- Para leer el `.docx` de marca: suele estar **abierto en Word y bloqueado**.
  Cópialo primero y extrae `word/document.xml` con
  `System.IO.Compression.ZipFile` desde PowerShell.
- **El navegador cachea CSS y JS con fuerza.** Tras editar, recarga con
  Ctrl+F5 o busca cambios que "no se aplican" cuando sí están en disco.
- Apache a veces está parado. Comprobar con `Get-Process httpd`.
- PHP CLI está en `C:\xampp\php\php.exe` (útil para `php -l`).

### Verificar pantallas con sesión sin iniciar sesión

Las páginas internas redirigen a `login.php`. Para comprobarlas se usa un
arnés en CLI que inyecta la sesión:

```php
session_start();
$_SESSION['id_usuario'] = 2;   // usuario con 209 cartas
$sid = session_id();
session_write_close();
$_COOKIE[session_name()] = $sid;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['QUERY_STRING'] = '';
ob_start(); include 'coleccion.php'; $html = ob_get_clean();
```

Usuarios de prueba en la BD: id 2 (`LuluLulez`, 209 cartas, admin),
id 1 (`FranDictador`), id 7 (`Prueba3`, sin cartas).

### Código

- **`assets/img/` NO EXISTE** en la copia de trabajo original. Falta todo el
  arte de cartas, iconos de afinidad, fotos de perfil y favicon. La BD apunta
  a rutas que no están en disco. `ui.js` degrada con elegancia (marcador de
  posición en la placa, el hexágono de afinidad desaparece), pero **la
  validación estética del marco con arte real está pendiente**.
- **`bloqueada` en BD = "protegida de la venta"**, no "no la tienes". En la
  interfaz se llama *protegida*. En el componente son dos opciones distintas:
  `protegida` y `poseida`. No las mezcles.
- El mercado devuelve el nombre de la carta como `carta`, no como `nombre`;
  `mercado.php` lo adapta antes de pasarlo al componente.
- `listarColeccionUsuario()` devuelve **todas las copias**, no cartas
  distintas; `contarColeccionUsuario()` cuenta distintas. No es un bug.
- La ceremonia recibe **la carta ya renderizada en servidor** (`carta_html`).
  Es lo que hace cierto que haya un solo componente. No la reimplementes en JS.
- `assets/async/js/scriptsAsync.js` expone `actualizarMonedasNav()`. Usa una
  ruta relativa (`ajax/monedas.php`), así que solo funciona desde la raíz.
- `rareza-clases.php` **solo lo usa ya `panel/cromos.php`**. Se retira en Fase 3.
- `panel/` es autónomo: tiene su propio `admin.css` y usa **Bootstrap Icons**,
  no Phosphor. Se unifica en Fase 3.
- `body` usa `overflow-x: clip`, **no `hidden`**: `hidden` convierte el body en
  contenedor de scroll y rompe el `position:sticky` de la nav.

---

## 9. Convenciones

- **El briefing manda en diseño y marca. El código existente manda en
  convenciones técnicas.** Si chocan, adapta la implementación.
- Nombres de clases CSS y de funciones **en español**, como el resto del
  proyecto (`.carta-placa`, `render_carta`, `$cromos`).
- Comentarios en español, explicando **por qué**, no qué.
- Sin dependencias nuevas de npm ni build step. CSS y JS a mano.
- JS sin framework, en IIFE, con `'use strict'`. La API pública compartida
  cuelga de `window.SRF` (`abrirModal`, `cerrarModal`, `toast`, `ceremonia`).
- Escapar siempre con `htmlspecialchars()`. Consultas con PDO preparado.

### Decisiones ya tomadas — no volver a abrirlas

Estas se discutieron y se cerraron con Alejandro. Si crees que alguna está mal,
dilo, pero no las cambies por iniciativa propia:

- **La nav es `sticky`, no fija superpuesta.** Al ocupar sitio en el flujo se
  elimina de raíz que tape contenido con el foco puesto.
- **Nada de desplegables falsos en la navegación.** Los clústeres se leen por
  separadores en escritorio y por títulos en el panel móvil. Un menú
  desplegable para un solo destino es teatro.
- **La ceremonia recibe la carta renderizada en servidor.** Es lo que hace
  cierto que exista un solo componente de tarjeta.
- **Geist va autoalojada**, no por CDN de Google Fonts.
- **Los ficheros retirados se mueven a `_legacy/`, no se borran**, porque el
  proyecto no está bajo control de versiones.
- **El texto del `<h1>` de la portada lo controla Alejandro.** Lo ha editado a
  mano; no lo toques.
- **Confirmación explícita en modal propio** para toda acción con consecuencia
  económica. Nada de `confirm()` del navegador.
- **La SRF tiene que ganar visualmente a la legendaria.** Se pidió
  expresamente reforzarla.

---

## 10. Fase 2 — lo que viene

Requiere **migraciones aditivas y explícitas** en un fichero nuevo
(`db/migraciones/002_duelos_misiones_mazos.sql`). **No modificar ni borrar
datos existentes** de `usuarios`, `cromos`, `coleccion` ni `mercado` sin
decirlo con claridad.

Tablas previstas: `mazos`, `mazo_cartas`, `duelos`, `duelo_apuestas`,
`misiones`, `misiones_progreso`, `minijuegos_partidas`.

### Deck Builder
Estilo Adrenalyn XL / Panini. Si usa arrastrar y soltar, alternativa por
tap/clic desde el día uno.

### Duelos 1v1
- Apuesta de **monedas** (ambos la misma cantidad) o de **carta** (ambos de la
  misma rareza, elegida por quien crea la sala).
- Ceremonia: cuenta atrás → revelado simultáneo → resultado.
- **Evitar estética de casino**: nada de tragaperras ni ruleta. Es un reto
  deportivo, no un juego de azar.
- Ninguna acción con consecuencia económica se ejecuta sin confirmación
  explícita (el patrón ya existe en `mercado.js` + modal `modalConfirmar`).
- El bloque de estadísticas de combate de la carta **ya tiene su hueco** en el
  componente (`$opts['stats']`); falta definir qué estadísticas son.

### Misiones y minijuegos
Reutilizan los componentes de la Fase 1. No crean variantes propias.

### Navegación
Añadir Duelos y Misiones es añadir líneas al array `$navGrupos` de
`navbar.php`. Los clústeres ya están montados para recibirlos.

---

## 11. Fase 3 — pulido y escala

Se abre **solo cuando la Fase 2 esté cerrada y estable**. Tres bloques.

### 11.1 Rediseñar el panel de administración

`panel/` es hoy la única parte del producto que sigue con el sistema viejo.
Está aislado: no comparte CSS ni JS con la raíz, así que se puede migrar sin
riesgo para el resto.

Estado actual:

- 5 vistas: `index.php`, `cromos.php`, `expansiones.php`, `sobres.php`,
  `usuarios.php`, más `panel/navbar.php` (barra lateral).
- CSS propio en `panel/assets/css/admin.css`, que **duplica** clases del
  sistema (`.rarity`, `.r-comun`… hasta `.r-srf`) y usa la tipografía antigua
  (`Chakra Petch`).
- Usa **Bootstrap Icons** por CDN, no Phosphor.
- `panel/cromos.php` es el último fichero que depende de `rareza-clases.php`.

Qué hacer:

1. Migrar las 5 vistas a `partials/head.php` con `$base = '../'` y a los
   componentes del sistema (`.panel`, `.tabla`, `.campo`, `.btn`, `.modal`,
   `.pastilla`).
2. Sustituir las etiquetas de rareza ad-hoc por `render_rareza()`, y las
   miniaturas de cromo por `render_carta()` en tamaño `sm`.
3. Cambiar Bootstrap Icons por Phosphor y **eliminar el CDN de Bootstrap**.
4. Rehacer `panel/navbar.php` como barra lateral con los tokens del sistema.
5. Borrar `panel/assets/css/admin.css` y `rareza-clases.php` cuando no queden
   referencias (`grep -rn "rareza-clases\|admin.css" --include=*.php .`).
6. El panel es un dashboard: usar la escala de espaciado densa (1–4) y
   `Geist Mono` para todas las cifras.

Ojo: el panel gestiona datos reales. No cambies la lógica de los formularios ni
las llamadas a `db/consultas.php`; esto es una migración **visual**.

### 11.2 Refinar el motion de marca

Con sobre y duelo ya construidos y usados de verdad, revisar las dos ceremonias
juntas para que se sientan de la misma familia:

- Una sola curva de easing en ambas (`--ease`).
- Duraciones coherentes: la del duelo no debería ser más larga que la de una
  SRF, que es el clímax del producto.
- Verificar que `prefers-reduced-motion` deja las dos utilizables y que la
  rareza sigue siendo legible sin movimiento.
- Comprobar rendimiento con varias cartas SRF a la vez en pantalla (la aura y
  el barrido animan `background-position` y `opacity`, que son baratos, pero
  conviene medirlo con una rejilla llena).

### 11.3 Documentar cómo añadir una expansión de temporada

Entregable: un documento (por ejemplo `branding/EXPANSIONES.md`) que explique
el proceso completo de sacar una temporada nueva **dentro del mismo sistema**,
no como una skin aparte. Debe cubrir:

- Alta de la expansión y de sus cromos desde `panel/`.
- Convención de rutas y nombres del arte en `assets/img/`.
- Cómo se crean los sobres de esa expansión y cómo afectan a las
  probabilidades de rareza.
- Qué **no** hay que tocar: los tokens, el marco de carta y la paleta se
  mantienen entre temporadas. Una expansión aporta contenido, no un estilo
  propio.

---

## 12. Pendientes menores (no bloquean la Fase 2)

- `coleccion.php` pinta las 209+ cartas de golpe, sin paginar. El componente
  `.paginacion` existe en el sistema y está sin conectar.
- `_legacy/` se puede borrar cuando Alejandro confirme que no echa nada en
  falta. Se movió en vez de borrarse porque **el proyecto no está bajo control
  de versiones**.
- Falta validar el marco de carta con arte real (ver `assets/img/`).

---

## 13. Comprobaciones antes de dar algo por terminado

```bash
# sintaxis de todas las páginas
for f in *.php partials/*.php components/*.php; do C:/xampp/php/php.exe -l "$f"; done
```

En navegador, a 375×812 y en escritorio:

- Sin scroll horizontal de página (`document.documentElement.scrollWidth`).
- Recorre con el tabulador: el foco siempre visible y nunca tapado.
- Modales: se abren, atrapan el foco, cierran con Esc y devuelven el foco.
- Con "reducir movimiento" activo: sin volteos ni destellos, y la SRF sigue
  siendo reconocible.
- Objetivos táctiles ≥24×24 (44×44 en acciones primarias).
- Un solo `<h1>`, `main#contenido`, `.skip-link` y aviso legal en cada página.
