# Superliga Frontier TCG — contexto de trabajo

> Documento de traspaso, versión 4 (2026-08-06).
> Léelo entero antes de tocar código. Si trabajas desde otro equipo con **la
> misma copia del proyecto** (mismos ficheros, misma base de datos `tcg`), este
> fichero es todo el contexto necesario: no hace falta la conversación anterior.
>
> **Sustituye a las versiones anteriores.** Desde la v3 se han construido, en
> los commits `6ce78d3` y `93642b2`: **Misiones**, **Cadenas de Partido (PvE)**,
> **formaciones alternativas**, **recompensas/loot**, y una **recalibración del
> modelo de combate** (las líneas pasaron a ponderar las tres estadísticas).
> La Fase 2 está **cerrada**.
>
> Lo siguiente ya no es Misiones: ver §12.

---

## Cómo arrancar en un chat nuevo

Estás recogiendo un proyecto con **Fases 0, 1 y 2 cerradas**. Está construido y
funcionando contra la base de datos real: Deck Builder, Duelos PvP completos
(Capas 1, 2 y 3), simulación de partido, Misiones, formaciones alternativas y
**Cadenas de Partido (PvE)**. No lo rehagas ni lo revises salvo que Alejandro
lo pida.

**Lo primero que tienes que hacer, en este orden:**

1. Leer este documento entero.
2. Comprobar el entorno: `Get-Process httpd`, `Get-Process mysqld`. Si algo está
   parado, lánzalo desde `C:\xampp\`. Luego abre
   `http://localhost/tcg_srf/styleguide.php` para ver el sistema de diseño.
3. Verificar que el repo está intacto:
   ```
   for f in *.php partials/*.php components/*.php db/*.php assets/ajax/*.php panel/*.php; do C:/xampp/php/php.exe -l "$f"; done
   ```
4. `git status` y `git log --oneline -5`. El proyecto **está en git** (rama
   `master`, remoto `origin`).
5. Comprobar que la BD está al día (todas las migraciones hasta la `012`):
   ```
   C:\xampp\mysql\bin\mysql.exe -u root tcg -e "SELECT COUNT(*) FROM rasgos;"        -- 9
   C:\xampp\mysql\bin\mysql.exe -u root tcg -e "SELECT COUNT(*) FROM cromo_rasgos;"  -- 38
   C:\xampp\mysql\bin\mysql.exe -u root tcg -e "SELECT COUNT(*) FROM cadena_nodos;"  -- 18
   ```
   Si alguna da 0 o la tabla no existe, aplica §5.2.
6. Auditar la codificación (§5.3), que ya ha mordido dos veces:
   `C:\xampp\php\php.exe db/migraciones/004_reparar_codificacion.php`

**Antes de escribir código nuevo, presenta un plan corto y espera el visto
bueno.** Es la forma de trabajar acordada: plan → aprobación → implementación →
resumen de cierre. Si algo tiene dos lecturas razonables que llevarían a trabajo
distinto, pregúntalo con opciones concretas en vez de decidir por tu cuenta.

**Si Alejandro no dice por dónde seguir**, pregunta entre: la **ceremonia 3D de
sobres** (§14, autorizada y sin empezar), la **Fase 3** (panel de administración
al sistema nuevo, §12), el **importador de datos oficiales** (§15, diseño
aprobado, sin construir), o los **mecanismos de sesión que quedan del documento
de balance** (anti-tilt, pity del Aumento, matchmaking anti-repetición,
validador de balance — §10.7). Son trabajos independientes entre sí.

---

## 0. Qué es esto

TCG coleccionable fan-made de la **Superliga Frontier**, la liga de Inazuma
Eleven: Victory Road. Las cartas representan jugadores, presidentes,
entrenadores y escudos reales de una comunidad activa, no personajes de ficción.

- **Stack:** PHP 8 + MariaDB sobre XAMPP. Sin framework, sin build, sin npm
  (con la excepción de §9 para GSAP/Three.js, reservada a §14 y aún sin usar).
- **Raíz:** `C:\xampp\htdocs\tcg_srf` (servido en `http://localhost/tcg_srf/`).
- **Control de versiones:** git, rama `master`, remoto `origin`
  (`github.com/AlejandroSuarezCampos/tcg_srf`). Nunca `--force`, nunca
  reescribir historial sin que lo pida explícitamente.
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
| **§14 — Ceremonia 3D de sobres** | Vitrina 3D, reveal secuencial, secuencia FUT | ⬜ Sin empezar |
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
│   ├── ceremonia.php     ← modal de apertura de sobres
│   └── confirmar.php     ← modal de confirmación compartido (SRF.confirmar)
├── components/
│   └── carta.php         ← EL componente de tarjeta (render_carta, carta_html)
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
│   │   └── vendor/       ← reservado para §14 (GSAP/Three.js), vacío aún
│   ├── fonts/            ← Geist autoalojada (4 .woff2)
│   ├── ajax/             ← canjear_codigo.php, monedas.php, duelo_estado.php
│   └── img/
│       ├── Cromos/...    ← arte optimizado a WebP
│       └── _originales_sin_optimizar/  ← PNG originales, no borrados
├── db/
│   ├── conexion.php      ← instancia $db (sin tocar)
│   ├── consultas.php     ← clase Tcg, ~2630 líneas. TODA la lógica vive aquí.
│   ├── migraciones/
│   │   ├── 002_duelos_misiones_mazos.sql   Fase 2
│   │   ├── 003_capa2_compos.sql            Capa 2
│   │   └── 004_reparar_codificacion.php    utilidad (§5.3)
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
- PHP CLI en `C:\xampp\php\php.exe`. `extension=gd` ya está descomentada.
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
- GSAP y Three.js autorizados **solo** para §14, vendorizados sin npm.
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

1. **§14, ceremonia 3D de sobres** — autorizada explícitamente, sin empezar.
2. **Fase 3** — panel de administración al sistema nuevo (hoy sigue con
   Bootstrap Icons y su propio `admin.css`), motion unificado de las
   ceremonias, y documentar cómo añadir una expansión de temporada.
3. **§15, importador de datos oficiales** — diseño aprobado con plan de
   implementación completo, sin construir. Va con la Fase 3.
4. **Panel para curar rasgos a mano** (§10.2). La tabla ya soporta `manual = 1`
   y la derivación nunca lo pisa; falta solo la UI. Va con la Fase 3.
5. **Lo que queda del documento de balance** (§10.7): anti-tilt de sesión,
   pity del Aumento, matchmaking anti-repetición PvP, validador de balance.
6. **Más contenido de Cadenas**: hoy hay 2 cadenas y 18 nodos. El motor
   aguanta más sin tocar código; es trabajo de datos.
7. **Minijuegos** — ni contenido ni pantalla, aplazados por Alejandro.
8. **Resolver los hallazgos abiertos de §10.6.**
9. **Calibrar `duelo_k`/`duelo_p_min`/`duelo_p_max`** con duelos reales.
10. **Algoritmo definitivo del marcador de goles PvP**, hoy placeholder
    funcional (`marcadorDuelo()`): nunca contradice al ganador ya sorteado,
    pero no se considera el diseño final. El PvE ya usa su propia fórmula
    (`pve_goles_*`).
11. **`_legacy/` se puede borrar** cuando Alejandro confirme.

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

## 14. Ceremonia de sobres 3D y reveal secuencial

**Estado: sin empezar.** Extensión explícita y autorizada de la ceremonia ya
cerrada en la Fase 1: no se reabre esa fase, se construye **encima**, en los
mismos ficheros (`sobres.php`, `partials/ceremonia.php`, `assets/js/sobres.js`,
`assets/js/ceremonia.js`, `assets/css/components.css`).

### 14.0 Tecnología permitida

- **GSAP está permitido y se debe usar** como orquestador de las timelines
  (`gsap.timeline()`, `gsap.to()`, `gsap.quickTo()`, `ScrollTrigger`).
  Vendorizar en `assets/js/vendor/gsap/` (build UMD, sin npm, sin bundler).
- **Three.js también**, para lo que CSS 3D no cubra por rendimiento.
  Vendorizar en `assets/js/vendor/three/` (build ESM de un fichero).
  - Sigue **prohibido importar modelos 3D** (`.glb`/`.gltf`/`.obj`/`.fbx`/
    `.blend`). Las geometrías se generan por código.
- Motivo: el desarrollador anterior no evitó GSAP/Three.js por decisión de
  producto sino porque no sabía usarlos. Alejandro confirmó que sí los quiere.

### 14.1 Vitrina de expansiones (cajas tipo blaster)

Cada expansión como caja: imagen, logo del set, nombre, precio, disponibilidad,
con los datos que ya devuelve `consultas.php`. Dispuestas en fila con
perspectiva 3D (`perspective` en el contenedor, `transform-style: preserve-3d`):
la central de frente y ampliada, las laterales con `rotateY` y `translateZ`
negativo, `scale` y opacidad reducidos. Scroll horizontal (rueda, drag, flechas,
swipe) animando esos valores según la distancia al centro. Contener el wrapper
con `overflow: hidden` **solo en ese contenedor**, sin tocar el `overflow-x: clip`
global del body.

### 14.2 Interior de la caja: sobres al cursor

Sobres en abanico con profundidad escalonada (`translateZ` por índice). Con
`mousemove`, los cercanos al cursor se levantan, se acercan y rotan hacia el
puntero (tilt), con `gsap.quickTo()` por eje. Los lejanos vuelven a reposo con
`ease: power2.out`. En touch, sustituir por el sobre centrado en un swipe.

### 14.3 Selección y confirmación

El sobre elegido se centra y amplía; el resto se desenfoca (`filter: blur()`).
Modal **usando el patrón existente** (`partials/confirmar.php` / `SRF.confirmar`),
no uno nuevo: título "¿Quieres abrir este sobre?", coste y saldo actual, botón
deshabilitado si no llega el saldo, "Abrir sobre" / "Cancelar" (cancelar
revierte la animación).

### 14.4 Apertura y aura anticipatoria de rareza

Cierre rápido del modal, "rasgado" en 3D (dos mitades con `rotateX`
divergentes), flash y partículas. **Esto es lo que hoy no existe:** justo antes
de que cada carta se voltee, el fondo debe emitir un aura pulsante
(`radial-gradient` animado + `box-shadow`/`drop-shadow` apilados) cuya
intensidad y color dependan de **la rareza real de esa carta**, de forma que el
aura **anticipe** la rareza antes de verla. Para legendaria y SRF, añadir un
holográfico que reaccione al `pointermove`.

### 14.5 Reveal secuencial con skip

Las cartas se muestran **de una en una**: aparece de espaldas (`ease: back.out`)
→ aura anticipatoria → flip 3D (`rotateY` 180°→0°, `backface-visibility: hidden`)
→ se mantiene unos segundos → se desliza a un mini-stack lateral. Cada carta es
un paso de una `gsap.timeline()` única. **Botón "Saltar animación"** (salta la
carta actual) y **"Saltar todo"** (resuelve la timeline entera), implementados
fijando el estado final con `gsap.set()` y luego `timeline.kill()`, no dejando
`progress(1)` a medias con listeners vivos. Con `prefers-reduced-motion` no hay
timeline: se pinta el estado final.

### 14.6 Secuencia especial estilo FUT

Solo para legendaria y SRF, como timeline **separada y reutilizable**: fondo se
oscurece, rayos de luz (gradientes cónicos rotando), partículas 2D, nombre y
rareza con la escala `display` y `text-shadow` pulsante. Preámbulo de 2-3 s,
saltable con los mismos botones. Después, el flip amplificado: aura mayor,
*screen shake* leve (`gsap.to` con traslaciones pequeñas y `yoyo: true`), y un
hook `onExclusiveReveal()` preparado para audio futuro. Dispararla leyendo el
campo de rareza ya devuelto por `consultas.php`; si el campo que necesitas no
existe, **dilo antes de improvisar un nombre de columna**.

### 14.7 Entregables y comprobaciones

Vitrina 3D · vista interior · modal reutilizando el patrón · apertura + aura por
rareza · reveal secuencial con skips · secuencia FUT independiente · GSAP (y
Three.js si hace falta) en `assets/js/vendor/` colgando de
`window.SRF.ceremonia3D` · variables CSS centralizadas para perspectiva, aura y
duración, coherentes con los tokens de motion.

Además de §13, verifica:
- `assets/js/vendor/…` existe y se carga con `<script>` normal, **sin**
  `package.json`, `node_modules/` ni paso de build.
- Ningún `.glb`/`.gltf`/`.obj`/`.fbx`/`.blend` añadido al repo.
- Con `prefers-reduced-motion`: la vitrina no rota sola, no hay tilt, el reveal
  salta al estado final y la secuencia FUT se sustituye por el aura estática.
- Los botones de skip dejan el DOM en el mismo estado final que la animación
  completa, sin timelines colgadas ni listeners duplicados tras varios usos.

---

## 15. Importador de datos oficiales (diseño aprobado, sin construir)

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
- **Similar pero no exacta** (`similar_text()` ≥ 75%) → se lista en el paso 1
  como ambiguo, con tres opciones: nombre del JSON, nombre de la BD, o texto
  libre.
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
		if ($mejor !== null && $mejorPct >= 75.0) {
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
