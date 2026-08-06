# Superliga Frontier TCG — contexto de trabajo

> Documento de traspaso, versión 5 (2026-08-06).
> Léelo entero antes de tocar código. Si trabajas desde otro equipo con **la
> misma copia del proyecto** (mismos ficheros, misma base de datos `tcg`), este
> fichero es todo el contexto necesario: no hace falta la conversación anterior.
>
> **Sustituye a las versiones anteriores.** Desde la v4 se ha construido la
> **ceremonia de cajas/sobres en pseudo-3D** (migración `013`, componente
> `components/caja3d.php`, panel `panel/plantillas.php`) — ver §14, reescrito
> por completo porque lo entregado **no** es el plan original de vitrina con
> scroll y secuencia FUT: es más sencillo y ya está en producción. Además,
> **el repo ya no tiene `.git`** — ver la nota de git más abajo, es importante.
>
> Lo siguiente ya no es la ceremonia de sobres: ver §12.

---

## Cómo arrancar en un chat nuevo

Estás recogiendo un proyecto con **Fases 0, 1 y 2 cerradas**, más la ceremonia
de sobres en pseudo-3D (§14) ya construida encima. Está construido y
funcionando contra la base de datos real: Deck Builder, Duelos PvP completos
(Capas 1, 2 y 3), simulación de partido, Misiones, formaciones alternativas,
**Cadenas de Partido (PvE)** y la **apertura de sobres con cajas 3D CSS**. No
lo rehagas ni lo revises salvo que Alejandro lo pida.

**Lo primero que tienes que hacer, en este orden:**

1. Leer este documento entero.
2. Comprobar el entorno: `Get-Process httpd`, `Get-Process mysqld`. Si algo está
   parado, lánzalo desde `C:\xampp\`. Luego abre
   `http://localhost/tcg_srf/styleguide.php` para ver el sistema de diseño.
3. Verificar que el repo está intacto:
   ```
   for f in *.php partials/*.php components/*.php db/*.php assets/ajax/*.php panel/*.php; do C:/xampp/php/php.exe -l "$f"; done
   ```
4. **Ya hay `.git`** (se inicializó como red de seguridad antes de reescribir
   §14 — ver la nota de "Control de versiones" más abajo), pero **sin
   remoto**: `git log` no cuenta la historia completa del proyecto, solo lo
   que ha pasado desde ese checkpoint. Si en tu copia NO hay `.git` (por
   ejemplo, si Alejandro volvió a descomprimir un ZIP), sigue aplicando el
   aviso antiguo: no asumas commits/ramas/remoto, y considera `git init` antes
   de cambios grandes, avisando primero.
5. Comprobar que la BD está al día (todas las migraciones hasta la `013`):
   ```
   C:\xampp\mysql\bin\mysql.exe -u root tcg -e "SELECT COUNT(*) FROM rasgos;"        -- 9
   C:\xampp\mysql\bin\mysql.exe -u root tcg -e "SELECT COUNT(*) FROM cromo_rasgos;"  -- 38
   C:\xampp\mysql\bin\mysql.exe -u root tcg -e "SELECT COUNT(*) FROM cadena_nodos;"  -- 18
   C:\xampp\mysql\bin\mysql.exe -u root tcg -e "SELECT COUNT(*) FROM plantillas_3d;" -- 0 si nadie ha subido arte aún
   ```
   Si alguna de las tres primeras da 0 o la tabla no existe, aplica §5.2.
6. Auditar la codificación (§5.3), que ya ha mordido dos veces:
   `C:\xampp\php\php.exe db/migraciones/004_reparar_codificacion.php`

**Antes de escribir código nuevo, presenta un plan corto y espera el visto
bueno.** Es la forma de trabajar acordada: plan → aprobación → implementación →
resumen de cierre. Si algo tiene dos lecturas razonables que llevarían a trabajo
distinto, pregúntalo con opciones concretas en vez de decidir por tu cuenta.

**Si Alejandro no dice por dónde seguir**, pregunta entre: subir arte real a
**panel/plantillas.php** para que las cajas dejen de mostrar el degradado por
defecto, la **Fase 3** (panel de administración al sistema nuevo, §12), o los
**mecanismos de sesión que quedan del documento de balance** (anti-tilt, pity
del Aumento, matchmaking anti-repetición, validador de balance — §10.7). Son
trabajos independientes entre sí.

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
- **Control de versiones:** **ahora sí hay `.git`** — se creó como red de
  seguridad antes de una reescritura destructiva del sistema de cajas/sobres
  (§14). Es un repo **local, sin remoto configurado** (no
  `github.com/AlejandroSuarezCampos/tcg_srf` ni ningún otro); si retomas ese
  remoto, añádelo explícitamente, nunca asumas que ya existe. El primer commit
  (`"Checkpoint antes de reescribir..."`) es el estado previo a esa
  reescritura — útil si algo hay que revertir. Nunca `--force`, nunca
  reescribir historial sin que lo pida explícitamente Alejandro.
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
| **Fase 2 — Simulación de partido** | Modal de 7 s con reloj y goles antes del resultado | ✅ Construido |
| **Fase 2 — Misiones** | `misiones.php`, progreso derivado, reclamo | ✅ **Construido (§11)** |
| **Fase 2 — Formaciones** | 8 formaciones, desbloqueables por cofre de cadena | ✅ Construido |
| **Fase 2 — Cadenas de Partido (PvE)** | Mapa de nodos, 5 dificultades, rangos, loot | ✅ **Construido (§11b)** |
| **Fase 2 — Minijuegos** | Solo la tabla `minijuegos_partidas` | ⬜ Aplazados por Alejandro |
| **§14 — Cajas y sobres en pseudo-3D** | `caja3d.php`, `panel/plantillas.php`, migración `013` | ✅ **Construido, sin arte real subido aún** |
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
probabilidad → simulación de partido → resultado con desglose completo).

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
│   │   ├── ui.js         modales, toasts, tabs, nav, plegables, SRF.confirmar
│   │   ├── ceremonia.js  SRF.ceremonia(cartas)
│   │   ├── sobres.js · mercado.js · album.js · coleccion.js
│   │   ├── perfil.js · configuracion.js
│   │   ├── mazos.js      asignación hueco→jugador
│   │   ├── duelos.js     lobby, tipo de apuesta, confirmación
│   │   ├── duelo.js      latido, sondeo, cuenta atrás, SIMULACIÓN DE PARTIDO
│   │   ├── sobres.js     apertura de cajas/sobres 3D + tilt (§14, usa GSAP)
│   │   └── vendor/
│   │       └── gsap/gsap.min.js  ← único vendor real hoy. Three.js sigue sin usarse.
│   ├── fonts/            ← Geist autoalojada (4 .woff2)
│   ├── ajax/             ← canjear_codigo.php, monedas.php, duelo_estado.php
│   └── img/
│       ├── Cromos/...    ← arte optimizado a WebP
│       ├── plantillas/   ← creada al vuelo por subirPlantilla(); vacía si
│       │                    nadie ha subido arte de caja/sobre todavía
│       └── _originales_sin_optimizar/  ← PNG originales, no borrados
├── db/
│   ├── conexion.php      ← instancia $db (sin tocar)
│   ├── consultas.php     ← clase Tcg, ~3970 líneas. TODA la lógica vive aquí.
│   ├── migraciones/
│   │   ├── 002_duelos_misiones_mazos.sql   Fase 2
│   │   ├── 003_capa2_compos.sql            Capa 2
│   │   ├── 004_reparar_codificacion.php    utilidad (§5.3)
│   │   ├── 005 a 012                       Misiones, formaciones, PvE (§11, §11b)
│   │   └── 013_plantillas_3d.sql           tabla plantillas_3d (§14)
│   └── tcg.sql
├── panel/                ← admin, TODAVÍA CON EL SISTEMA VIEJO salvo
│                            plantillas.php (Fase 3, ver §12)
│   └── plantillas.php    ← sube/recorta/previsualiza el arte de cajas y
│                            sobres (§14), con el motor 3D real
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

De la Capa 2 (`003`): `rasgos`, `cromo_rasgos`, `duelo_compos`.

De Misiones/Formaciones/PvE (`005`-`012`): ver §11 y §11b.

De la ceremonia 3D (`013`): `plantillas_3d` — una fila por caja/sobre con
plantilla subida (§14).

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
- `prefers-reduced-motion` cubre las ceremonias. La simulación de partido ni
  siquiera abre su modal si está activo: va directo al resultado.
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

### Entorno

- **No hay Python real**: `python`/`python3`/`py` son el stub de Microsoft
  Store. No hay `pandoc`. No hay `node` (no puedes usar `node -c` para validar JS).
- **El navegador cachea CSS y JS con fuerza.** Tras editar, recarga con Ctrl+F5,
  o si automatizas pruebas, sustituye el `<link>`/`<script>` con
  `?cb=timestamp` y espera a `onload` antes de medir. Es el patrón usado en
  todas las verificaciones de este proyecto.
- **El panel del navegador de pruebas no compone fotogramas si está en segundo
  plano**: `requestAnimationFrame` se pausa y `document.hidden` es `true`. Las
  animaciones no se pueden cronometrar ahí; hay que validar la lógica por
  partes (estado final, algoritmos aislados) y decir honestamente que el ritmo
  visual no se ha visto.
- PHP CLI en `C:\xampp\php\php.exe`. **`extension=gd` estaba comentada tanto
  para CLI como para el PHP que carga Apache** (contradice lo que decía esta
  misma línea en versiones anteriores del documento — verificado con
  `php -m`, ninguno de los dos la traía activa). Se descomentó en
  `C:\xampp\php\php.ini`, pero **Apache corre como servicio de Windows
  (`Apache2.4`) y una sesión sin privilegios de administrador no puede
  reiniciarlo** (`Restart-Service` da "Acceso denegado"). Si `panel/plantillas.php`
  da `Fatal error: Call to undefined function imagecreatetruecolor()`, ese es
  el motivo: reinicia Apache a mano (XAMPP Control Panel: Stop → Start, o
  `services.msc` → Apache2.4 → Reiniciar) para que recoja el cambio.
- Apache y MariaDB a veces están parados. Si XAMPP los deja a medias, mata los
  procesos `httpd`/`mysqld` y relanza desde `C:\xampp\`.

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
  aumento, abandonar una sala) se evalúa de forma perezosa en cada carga o
  sondeo. **Misiones debe seguir el mismo patrón.**
- **Un `<button>` no puede contener otro `<button>`** — el navegador cierra el
  exterior de golpe al primero interior. Por eso las cajas abribles en
  `sobres.php` son `<div role="button" tabindex="0">`, no `<button>`: su
  interior lleva hasta 50 `<button>` de sobre individual (§14).

---

## 9. Convenciones

- **El briefing manda en diseño y marca. El código existente manda en
  convenciones técnicas.**
- Nombres de clases CSS y de funciones **en español**. Comentarios en español,
  explicando **por qué**, no qué.
- **Sin dependencias de npm ni build step.** GSAP/Three.js son la única
  excepción autorizada, reservada a §14, y **aún no vendorizados**.
- JS sin framework, en IIFE, con `'use strict'`. API pública en `window.SRF`:
  `abrirModal`, `cerrarModal`, `toast`, `confirmar`, `ceremonia`.
- Escapar siempre con `htmlspecialchars()`. PDO preparado siempre.
- **Toda la capa de datos vive en la clase `Tcg`** (`db/consultas.php`, ~2630
  líneas), agrupada por comentarios de sección (`MAZOS`, `DUELOS`, `AUMENTO
  PRE-PARTIDO`, `CAPA 2 — COMPOS`, `CONFIGURACIÓN`). No se crean clases nuevas.
- **Patrón "sala en vivo" sin websockets**: latido periódico + sondeo +
  `navigator.sendBeacon` en `pagehide`. Ver `duelo.js` + `assets/ajax/duelo_estado.php`.

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

1. **Subir arte real de cajas/sobres** en `panel/plantillas.php` (§14) — el
   motor está construido y probado, pero sin plantillas subidas todo cae al
   degradado por defecto. Es trabajo de contenido, no de código.
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
6. **Minijuegos** — ni contenido ni pantalla, aplazados por Alejandro.
7. **Resolver los hallazgos abiertos de §10.6.**
8. **Calibrar `duelo_k`/`duelo_p_min`/`duelo_p_max`** con duelos reales.
9. **Algoritmo definitivo del marcador de goles PvP**, hoy placeholder
   funcional (`marcadorDuelo()`): nunca contradice al ganador ya sorteado,
   pero no se considera el diseño final. El PvE ya usa su propia fórmula
   (`pve_goles_*`).
10. **`_legacy/` se puede borrar** cuando Alejandro confirme.

---

## 13. Comprobaciones antes de dar algo por terminado

```bash
for f in *.php partials/*.php components/*.php db/*.php assets/ajax/*.php panel/*.php; do
  C:/xampp/php/php.exe -l "$f"
done
```

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

Si tocas la Capa 2, además:
- `SELECT HEX(nombre) FROM rasgos WHERE clave='montana';` → debe contener `c3b1`.
- Reejecutar `derivarRasgosConfiguracion()` no debe cambiar el reparto
  (12/10/8/8) ni pisar filas con `manual = 1`.
- El ciclo Fuego→Bosque debe seguir dando ~57,8 % con onces equivalentes.

---

## 14. Cajas y sobres en pseudo-3D (CONSTRUIDO, distinto del plan original)

**Estado: construido y funcionando**, con `admin` aún sin subir arte real (cae
al degradado por defecto). Especificado en un prompt aparte
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
  (`abrirSobre()`) y reutiliza el modal/reveal **ya existente** de
  `partials/ceremonia.php` + `assets/js/ceremonia.js` (sin cambios: el reveal
  de cartas por rareza es el mismo desde la Fase 1). §14 solo cambia cómo se
  llega hasta ahí, no lo que pasa al abrir un sobre.
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

1. **El sobre se rasga** (`#ceremoniaApertura`) usando la textura de SU
   plantilla (`sobre.frente`, que `sobres.php` pasa por `data-frente`). Las dos
   mitades comparten la misma imagen de fondo al doble de alto, desplazada
   `-100%` en la de abajo, para que la costura caiga por el centro del arte.
2. **Carta a carta** (`#ceremoniaFoco`): cada carta aparece **boca abajo y
   espera el clic** del jugador para voltearse (es un `<button>` real, así que
   funciona con Enter/Espacio). Un segundo clic pasa a la siguiente.
   - Rareza **≥ 5** (legendaria y SRF) no se voltea sin más: dispara antes un
     **walkout** — se oscurece todo, giran rayos cónicos, aparece el nombre de
     la rareza con latido, y la carta se destapa con destello y temblor.
   - `Saltar carta` resuelve la actual al instante; `Saltar todo` va al resumen.
3. **Resumen** (`#ceremoniaMesa`): todas las cartas ya reveladas, con el
   anuncio por `aria-live`.

`prefers-reduced-motion` se consulta **en cada apertura**, no al cargar el
script, para que cambiar la preferencia del sistema surta efecto sin recargar;
con la preferencia activa se va directo al resumen.

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
