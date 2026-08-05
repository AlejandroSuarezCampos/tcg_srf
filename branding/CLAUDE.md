# Superliga Frontier TCG — contexto de trabajo

> Documento de traspaso, versión ampliada (2026-08-05). Léelo entero antes de
> tocar código. Si trabajas desde otro equipo con **la misma copia del
> proyecto** (mismos ficheros, misma base de datos `tcg`), este fichero es
> todo el contexto necesario: no hace falta la conversación anterior.
>
> **Sustituye a la versión anterior de este mismo documento.** La Fase 2 ha
> avanzado mucho desde la última vez que se escribió: Mazos y Duelos están
> construidos y probados, no son ya "lo que viene". Si tienes por ahí una
> copia vieja de este fichero, tírala y usa esta.

---

## Cómo arrancar en un chat nuevo

Estás recogiendo un proyecto con las **Fases 0 y 1 terminadas y aprobadas**,
y la **Fase 2 en curso y avanzada**: el Deck Builder y los Duelos (con su
capa de aumento pre-partido) están construidos, probados en navegador contra
la base de datos real, y comprometidos en git. No los rehagas ni los revises
salvo que Alejandro lo pida explícitamente.

**Lo primero que tienes que hacer, en este orden:**

1. Leer este documento entero.
2. Comprobar que el entorno responde: Apache y MariaDB levantados
   (`Get-Process httpd`, `Get-Process mysqld`) y
   `http://localhost/tcg_srf/styleguide.php` carga. Ahí está el sistema de
   diseño completo y funcionando.
3. Verificar que el repo está intacto:
   `for f in *.php partials/*.php components/*.php db/*.php assets/ajax/*.php; do C:/xampp/php/php.exe -l "$f"; done`
4. Comprobar el estado de git (`git status`, `git log --oneline -5`): el
   proyecto **sí está bajo control de versiones** desde antes de que existiera
   este documento — corrige cualquier nota antigua que diga lo contrario.
5. Mirar qué falta exactamente de la Fase 2 en la tabla de §1 antes de asumir
   nada: no está todo hecho, pero lo que está hecho es real y probado, no un
   plan.

**Antes de escribir código nuevo, presentar un plan corto y esperar el visto
bueno.** Es la forma de trabajar acordada con Alejandro: plan → aprobación →
implementación → resumen de cierre. Dentro de eso, si algo tiene dos lecturas
razonables y llevarían a trabajo distinto, pregúntalo con opciones concretas
en vez de decidir por iniciativa propia — así se ha trabajado toda la Fase 2
(ver §10 para ejemplos reales de esto).

**Excepción autorizada por Alejandro, fuera del orden de fases:** el rediseño
3D de la ceremonia de sobres descrito en **§14**. Sigue sin empezar. Si
Alejandro no dice por dónde seguir y §14 sigue sin tocar, pregunta primero si
quiere continuar con §14, con lo que queda de Fase 2 (ver tabla de §1), o con
las decisiones de contenido de la Capa 2 de combate (§10.4) — son trabajos
independientes entre sí.

---

## 0. Qué es esto

TCG coleccionable fan-made de la **Superliga Frontier**, la liga de Inazuma
Eleven: Victory Road. Las cartas representan jugadores, presidentes,
entrenadores y escudos reales de una comunidad activa, no personajes de ficción.

- **Stack:** PHP 8 + MariaDB sobre XAMPP. Sin framework, sin build, sin npm
  (con la excepción puntual de §9 para GSAP/Three.js, vendored sin bundler,
  reservada a §14 y todavía sin usar).
- **Raíz del proyecto:** `C:\xampp\htdocs\tcg_srf` (servido en `http://localhost/tcg_srf/`).
- **Control de versiones:** el proyecto **está en git** (rama `master`,
  remoto `origin`). Los commits de Fase 2 ya están hechos por Alejandro.
  Sigue las reglas normales de git del entorno (nunca `--force`, nunca
  reescribir historial sin que lo pida explícitamente).
- **Ejecutado por una sola persona** (Alejandro), sin fecha de lanzamiento fija.
- **Gratuito**, sin monetización, exclusivo para participantes de la liga.
- **Legal:** proyecto fan-made sin ánimo de lucro. Inazuma Eleven es propiedad
  de Level-5. Sin afiliación. Este aviso debe estar visible en **todas** las
  páginas (lo pone `partials/footer.php`, incluido en todas).

El documento maestro de marca es
`branding/Brand-Identity-Briefing-Superliga-Frontier-TCG.docx` (38 secciones).
Si algo de diseño no está claro aquí, la respuesta está ahí.

**Cuenta de pruebas dedicada:** usuario `Claude` (id 9), contraseña `123456`,
`dictador=1` (admin), 1.000.000 de monedas, con un mazo titular ya armado
("Once titular", 11 huecos cubiertos). La creó Alejandro específicamente para
que la IA pruebe cosas en navegador real. **Úsala siempre** en vez de
improvisar sesiones con las cuentas de otros usuarios o pedir credenciales.

---

## 1. Estado del trabajo

| Fase / bloque | Contenido | Estado |
|---|---|---|
| **Fase 0 — Fundamentos** | Tokens de diseño, componente de tarjeta, guía de estilo | ✅ Terminada y aprobada |
| **Fase 1 — Núcleo** | Las 9 pantallas del núcleo migradas, aviso legal en todas | ✅ Terminada y aprobada |
| **§14 — Ceremonia de sobres 3D** | Vitrina 3D, sobres al cursor, reveal secuencial, secuencia FUT | ⬜ Sin empezar |
| **Fase 2 — Migración de BD** | 002_duelos_misiones_mazos.sql, 9 tablas nuevas, stats de combate | ✅ Aplicada y probada |
| **Fase 2 — Deck Builder** | `mazos.php`, alineación en formación real sobre un campo | ✅ Construido y probado |
| **Fase 2 — Duelos, Capa 1** | Fuerza de mazo + curva de resolución (Elo) + sala en vivo | ✅ Construido y probado |
| **Fase 2 — Duelos, Capa 3** | Aumento pre-partido (3 opciones, tiers, plazo, fallback) | ✅ Construido y probado |
| **Fase 2 — Duelos, Capa 2** | Rasgos/sinergias entre cartas | ⏸️ **Aplazada — falta decidir contenido, ver §10.4** |
| **Fase 2 — Misiones** | 8 misiones sembradas en BD | ⬜ **Sin pantalla ni lógica de progreso** |
| **Fase 2 — Minijuegos** | Solo la tabla `minijuegos_partidas` | ⬜ Aplazados por decisión de Alejandro, sin contenido definido |
| **Fase 3 — Pulido y escala** | Panel admin, motion unificado, doc de expansiones | ⬜ Pendiente |

**Regla de trabajo:** una fase (o §14, o cada capa de combate) se cierra por
completo y deja el sitio desplegable antes de abrir la siguiente. Al terminar
cada bloque, resumir qué cambió y por qué antes de continuar.

### Qué cubrió cada fase ya cerrada

**Fase 0** — No se tocó ninguna pantalla hasta tener el sistema debajo:
`tokens.css` con todas las variables, Geist autoalojada, `components.css` con
los 16 componentes del briefing, el componente de tarjeta con todos sus
estados, y `styleguide.php` como página de validación.

**Fase 1** — Migración pantalla a pantalla: `navbar` → `landing` →
`login`/`registro` → `sobres` (con la ceremonia plana) → `coleccion` →
`album` → `mercado` → `perfil`/`configuracion`. Aviso legal vía
`partials/footer.php`. CSS/JS antiguos retirados a `_legacy/`.

> **Nota de honestidad sobre la ceremonia de sobres:** la ceremonia cerrada en
> la Fase 1 es **plana** (modal, reveal de las cartas del sobre, escalado
> visual por rareza en el propio marco). **No incluye** vitrina 3D, cursor con
> tilt, flip de reverso a frente ni secuencia FUT. Todo eso sigue en §14, sin
> empezar. El §4 describe el aspecto SRF como objetivo de marca, no como
> inventario de lo construido.

**Fase 2, lo construido hasta ahora** — ver §10 para el detalle técnico
completo. En resumen: base de datos migrada de forma aditiva y repetible,
constructor de mazos con formación real sobre un campo, y duelos jugables de
principio a fin (crear sala → esperar rival con latido → aumento pre-partido
→ resolución con curva de probabilidad → resultado con desglose por líneas).

**Bugs de Fase 1 corregidos durante el trabajo de Fase 2** (efecto
secundario, no planeado, pero real y verificado):

- Colección: copias repetidas del mismo cromo se agrupaban visualmente con
  insignia "×N" en vez de renderizar una tarjeta por copia (189 tarjetas →
  22 en la cuenta de prueba). Filtros de colección aplicándose solos al
  cambiar cualquier campo, sin botón "Aplicar".
- Un bug real de CSS en los filtros plegables (`.stack` con `display:flex`
  ganaba al `display:none` nativo de un `<details>` cerrado, por ser de
  origen "autor" y no "user-agent") hacía que el panel de filtros se
  renderizara superpuesto al resto de la página en móvil. Corregido con
  `.filtros:not([open]) .filtros-cuerpo { display: none; }`.
- Ceremonia de sobres con muchas cartas: `.modal-caja` fuerza `overflow:
  hidden` (para que sobres pequeños "se miren, no se naveguen"), pero con un
  sobre de cientos de cartas eso dejaba la mayoría inalcanzables y los
  botones enterrados entre cartas. Corregido dándole scroll propio a
  `.ceremonia-mesa`, dejando cabecera y botones siempre fijos alrededor.
- Peso del arte de cartas: 16 PNG sumaban 45,7 MB (hasta 1130×2005 px para un
  hueco de 204×315). Convertidos a WebP a 800 px de alto: **1,79 MB en
  total**. Originales conservados en `assets/img/_originales_sin_optimizar/`
  (no se borran, el proyecto ya está en git pero por si acaso). Requirió
  descomentar `extension=gd` en `php.ini` (ver §8).
- Auditoría de la probabilidad de rareza en sobres (Alejandro reportó que en
  otra máquina la SRF salía "demasiado" a menudo): simulación de 200.000
  tiradas con el algoritmo real dio SRF al 0,481 % contra el 0,5 % esperado.
  **La lógica y los datos de esta base de datos son correctos.** Si se
  reproduce en otra máquina, lo primero a mirar es `SELECT * FROM rarezas;`
  ahí, no el código.

---

## 2. Arquitectura de ficheros

```
tcg_srf/
├── partials/
│   ├── head.php          ← abre el documento: fuentes, CSS, <body>, skip-link
│   ├── footer.php        ← pie + AVISO LEGAL + carga de ui.js
│   ├── ceremonia.php     ← marcado del modal de apertura de sobres
│   └── confirmar.php     ← NUEVO: modal de confirmación compartido
│                            (mercado.js lo usaba solo; ahora SRF.confirmar()
│                            en ui.js lo expone a cualquier pantalla)
├── components/
│   └── carta.php         ← EL componente de tarjeta (render_carta, carta_html)
│                            + opción 'cantidad' NUEVA para insignia "×N"
├── navbar.php            ← navegación; clúster "Jugar" con Sobres/Mazos/Duelos
├── mazos.php             ← NUEVO: constructor de mazos (ver §10.2)
├── duelos.php            ← NUEVO: lobby de duelos (ver §10.3)
├── duelo.php             ← NUEVO: sala en vivo + partido (ver §10.3)
├── assets/
│   ├── css/
│   │   ├── tokens.css      variables + @font-face. Fuente de verdad.
│   │   ├── base.css        reset, tipografía, foco, layout de página
│   │   ├── components.css  los 16 componentes + ceremonia
│   │   ├── layout.css      nav, hero, cabecera, filtros, pie, MAZOS, DUELOS
│   │   └── styleguide.css  solo para styleguide.php
│   ├── js/
│   │   ├── ui.js         modales, toasts, tabs, nav, plegables, reveal,
│   │   │                  SRF.confirmar() NUEVO
│   │   ├── ceremonia.js  SRF.ceremonia(cartas)
│   │   ├── sobres.js     compra → llama a la ceremonia
│   │   ├── mercado.js    confirmación + selector visual de venta
│   │   ├── album.js      filtrado en cliente
│   │   ├── coleccion.js  NUEVO: auto-submit de filtros
│   │   ├── perfil.js     canje de códigos
│   │   ├── configuracion.js
│   │   ├── mazos.js      NUEVO: asignación hueco→jugador (ver §10.2)
│   │   ├── duelos.js     NUEVO: lobby, tipo de apuesta, confirmación
│   │   ├── duelo.js      NUEVO: latido, sondeo, cuenta atrás de aumento
│   │   └── vendor/       ← reservado para §14 (GSAP/Three.js), vacío aún
│   ├── fonts/            ← Geist autoalojada (4 .woff2)
│   ├── ajax/
│   │   ├── canjear_codigo.php
│   │   ├── monedas.php
│   │   └── duelo_estado.php  ← NUEVO: latido + sondeo de la sala de duelo
│   ├── async/js/scriptsAsync.js ← actualizarMonedasNav() (sin tocar)
│   └── img/
│       ├── Cromos/...           ← arte optimizado a WebP (ver §8)
│       └── _originales_sin_optimizar/  ← PNG originales, no borrados
├── db/
│   ├── conexion.php      ← instancia $db (sin tocar)
│   ├── consultas.php     ← clase Tcg, ~2200 l. TODA la Fase 2 vive aquí como
│   │                        métodos nuevos de la misma clase (ver §10.1)
│   ├── migraciones/
│   │   └── 002_duelos_misiones_mazos.sql  ← toda la migración de Fase 2,
│   │                                          aditiva y re-ejecutable
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

Los patrones nuevos de Fase 2 (todos siguen esqueletos parecidos, no
inventan nada nuevo):

- **Acción POST → redirección**: todas las páginas de Fase 2 procesan
  `$_POST['accion']` al principio del fichero y hacen `header('Location:
  ...'); exit;`, nunca renderizan tras un POST.
- **AJAX aparte en `assets/ajax/`**: cuando una pantalla necesita sondeo
  periódico (la sala de duelo), el endpoint vive en su propio fichero, nunca
  mezclado con la lógica de render de la página.

---

## 3. El componente de tarjeta

Vive en `components/carta.php`. **Nunca se copia su marcado con variaciones.**
Si una pantalla necesita algo distinto, se añade una opción al componente —
así se hizo con `cantidad` esta fase, no creando una tarjeta aparte.

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
| `cantidad` | **NUEVO** int ⇒ insignia "×N" junto al hexágono de afinidad, para copias repetidas (colección, selector de mazos) |
| `stats` | `['ATA'=>88,'DEF'=>72,'TÉC'=>91]`, hasta 3 |
| `acciones` | HTML flotante sobre la carta |
| `pie` | HTML al final del marco |
| `datos` | `['equipo'=>'x']` ⇒ atributos `data-*` para filtros de cliente |
| `clase`, `lazy` | clases extra / carga diferida de la imagen |

### Tres reglas que el componente garantiza

1. **El arte se muestra siempre completo.** `object-fit: contain`, nunca
   `cover`. La imagen va **posicionada en absoluto** contra la placa: con
   `height:100%` a secas el porcentaje no resuelve, y un arte muy alto
   desbordaba y se recortaba. Validado con arte real: cero desbordes, ratios
   de 0,45 a 1,53.
2. **La rareza lleva marca no cromática** además del color: 0/1/2/3 chevrones
   dibujados en CSS, corona para legendaria, destello para SRF.
3. **Todo arte lleva texto alternativo.**

### El borde real de una carta (importante si tocas cualquier vista nueva)

El borde de rareza **no es un `border` CSS**. Es la técnica
`padding + background`: `.carta` tiene `padding: 1px; background:
var(--rz-borde);` y el marco interior (`.carta-marco`) pinta encima, dejando
ver solo 1px del fondo — ESO es el borde. `--rz-borde` es:

```
rareza 1 (Común)      var(--line-strong)                          (gris neutro)
rareza 2 (Poco común)  color-mix(in srgb, var(--rz2) 34%, transparent)
rareza 3 (Raro)        color-mix(in srgb, var(--rz3) 38%, transparent)
rareza 4 (Épico)       color-mix(in srgb, var(--rz4) 42%, transparent)
rareza 5 (Legendario)  linear-gradient(140deg, #FFE0AE, #E8752A 30%, #7A3A12 52%, #F2B134 72%, #FFE0AE)
rareza 6 (SRF)         var(--rz6-grad), animado con @keyframes holoDeriva
```

**Nunca uses el tono puro de `--rzN` como borde sólido en una vista nueva** —
es un error que ya se cometió una vez en el chip del mazo (ver §10.2) y se
corrigió reutilizando exactamente esta técnica, incluida la mezcla de
transparencia y el tratamiento especial de legendaria/SRF.

---

## 4. Sistema de diseño

### Color (`tokens.css`)

```
--void #0B0C10   --panel #16181D   --frost #EDEEF1   --frost-dim #93959F
--amber #E8752A  --amber-light #FFB168  --amber-ink #2B1204
--success #3DDC9B  --warning #F2B134  --danger #F0554A  --info #5B96F2
```

Los semánticos son literalmente los colores de las tarjetas arbitrales del
fútbol. Es una decisión deliberada, no un sistema genérico.

### Rarezas

| id | Nombre | Prob. | Marca no cromática |
|---|---|---|---|
| 1 | Común | 60 % | sin adorno |
| 2 | Poco común | 25 % | 1 chevrón |
| 3 | Raro | 10 % | 2 chevrones |
| 4 | Épico | 3,5 % | 3 chevrones |
| 5 | Legendario | 1 % | corona + borde metálico |
| 6 | SRF | 0,5 % | borde arcoíris animado + aura + barrido |

**Estas probabilidades están auditadas y confirmadas correctas** (ver §1,
bugs corregidos): coinciden entre el código, la base de datos y el seed SQL.
La lógica de sorteo de sobres (`elegirCartasSobre()` en `db/consultas.php`)
verificada con 200.000 muestras simuladas.

La **SRF tiene que ganar visualmente a la legendaria sin discusión**. Sigue
siendo aspiración de §14, no algo ya construido (ver nota de honestidad en §1).

### Tipografía

Geist Sans para UI. **Geist Mono solo para datos**: monedas, estadísticas,
contadores, marcas de tiempo, y ahora también los totales de fuerza de un
mazo y el marcador de un duelo.

### Espaciado, radio, motion

- Espaciado: `--space-1..8` = 4·8·12·16·24·32·48·64px.
- Radio: 8 (controles) / 12 / 16 / 22px (carta y modales).
- Motion: una sola curva `--ease`. `--t-micro` 160ms, `--t-media` 380ms,
  `--t-ceremonia` 700ms **para sobre y duelo** (la entrada del marcador de
  partido ya usa `--t-ceremonia`, ver §10.3).

### Iconografía

Phosphor Icons por CDN, pinneado a `@2.1.1`. No dependas de un glifo para
información crítica (los chevrones de rareza y el check del selector van
dibujados en CSS por eso).

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
superlativos vacíos, sin bromear con nombres de jugadores reales.

El humor se limita a **exactamente dos guiños, ninguno más**: "Superruina
Frontier" (solo contextos secundarios) y "a ese Gonzalo le gano fácil" (cita
de folclore de liga, en `partials/footer.php`). No inventes chistes nuevos.

> Nota: el `<h1>` de la portada lo editó Alejandro a mano. Respétalo.

---

## 7. Accesibilidad — no negociable (WCAG 2.2)

- Contraste ≥4.5:1 en texto normal, ≥3:1 en texto grande.
- Foco visible en todo elemento interactivo, nunca tapado.
- Objetivo táctil mínimo 24×24px, estándar interno 44×44px. **En el mazo, el
  retrato visual de un jugador puede ser menor (41px en móvil) siempre que el
  área de clic del botón completo (retrato + nombre) siga midiendo ≥44px** —
  verificado así en `mazos.php`/`layout.css`.
- Todo operable por teclado: filtros, modales, ceremonia de sobre, **huecos
  del mazo y opciones de aumento** (son `<button>`, no hace falta arrastrar).
- Si algo usa arrastrar y soltar, alternativa por tap/clic desde el primer
  lanzamiento (SC 2.5.7). El deck builder cumple esto por diseño: nunca ha
  tenido arrastre, solo "elegir hueco → elegir jugador".
- `prefers-reduced-motion` cubre las ceremonias. La entrada del marcador de
  partido (`duelo.js`) comprueba esta preferencia y no anima si está activa.
- Regiones `aria-live` para resultados de sobre y duelo. El reloj de la fase
  de aumento usa `role="timer" aria-live="off"` a propósito (no se lee cada
  segundo).
- Un solo `<h1>` por página — **se rompió una vez en `duelo.php`** (la vista
  de resultado no tenía ninguno) y se corrigió haciendo que el veredicto
  ("Victoria"/"Derrota") sea el propio `<h1>`, con contexto para lector de
  pantalla en un `<span class="sr-only">`.

Errores de accesibilidad ya encontrados y corregidos (no los reintroduzcas):

- Texto blanco sobre el holográfico SRF caía a 1,9:1 → placa oscura debajo.
- Enlaces del pie a 20px de alto → `padding-block`.
- Botón de hamburguesa comprimido a 20px por el flex → `flex-shrink: 0`.
- `duelo.php`, vista de resultado, sin `<h1>` → corregido (ver arriba).

---

## 8. Trampas conocidas del entorno y del código

### Entorno

- **No hay Python real**: `python`/`python3`/`py` son el stub de Microsoft
  Store. No hay `pandoc`.
- **El navegador cachea CSS y JS con fuerza.** Tras editar, o bien recarga
  con Ctrl+F5, o si estás automatizando pruebas, sustituye el `<link>`/
  `<script>` con un `?bust=timestamp` y espera a `onload` antes de medir —
  es el patrón usado durante toda la Fase 2 para verificar cambios de CSS/JS
  sin depender de que el navegador respete la caché.
- Apache y MariaDB a veces están parados. Comprobar con `Get-Process httpd`
  y `Get-Process mysqld`. Si XAMPP los deja a medio arrancar/parar (pasó una
  vez durante la Fase 2), mata los procesos `httpd`/`mysqld` colgados y
  vuelve a lanzar `apache_start.bat`/`mysql_start.bat` desde `C:\xampp\`.
- PHP CLI está en `C:\xampp\php\php.exe`.
- **`extension=gd` estaba comentada en `php.ini`** (necesaria para convertir
  imágenes). Ya se descomentó y Apache se reinició para aplicar el cambio.
  Si `gd_info()` falla en otra máquina, es esto.

### Verificar pantallas con sesión sin iniciar sesión

```php
session_start();
$_SESSION['id_usuario'] = 2;   // usuario con cartas reales
$sid = session_id();
session_write_close();
$_COOKIE[session_name()] = $sid;
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start(); include 'coleccion.php'; $html = ob_get_clean();
```

O, para pruebas en navegador de verdad (recomendado para Fase 2, que tiene
mucha interacción con JS): fija una sesión con `session_id()` explícito desde
PHP CLI y pon esa cookie en el navegador con
`document.cookie = "PHPSESSID=...; path=/"`.

Usuarios de prueba en la BD: id 2 (`LuluLulez`, ~200 cartas, admin), id 1
(`FranDictador`, **solo 1 cromo jugador distinto — no puede formar
alineación**), id 7 (`Prueba3`, sin cartas), id 8 (`Payo Water`, colección
variada), **id 9 (`Claude` / `123456`, la cuenta dedicada a pruebas de IA —
úsala siempre que puedas)**.

### Código — general

- **`bloqueada` en BD = "protegida de la venta"**, no "no la tienes". En el
  componente son dos opciones distintas: `protegida` y `poseida`.
- El mercado devuelve el nombre de la carta como `carta`, no `nombre`;
  `mercado.php` lo adapta.
- `listarColeccionUsuario()` devuelve todas las copias; `contarColeccionUsuario()`
  cuenta distintas. No es un bug.
- La ceremonia recibe la carta ya renderizada en servidor (`carta_html`). No
  la reimplementes en JS — sigue aplicando en §14.
- `rareza-clases.php` solo lo usa `panel/cromos.php`. Se retira en Fase 3.
- `panel/` es autónomo, con Bootstrap Icons, no Phosphor. Se unifica en Fase 3.
- `body` usa `overflow-x: clip`, no `hidden` (rompería el `sticky` de la nav).

### Código — arte de cartas

- `assets/img/` existe, con 35 ilustraciones de cromo (28 de 44 cromos con
  arte), ya **optimizadas a WebP** (1,79 MB en total, antes 46,1 MB en PNG).
  Originales sin optimizar en `assets/img/_originales_sin_optimizar/`, no
  borrados.
- Rutas con espacios y una `ñ` (`montaña.png`); Apache las sirve bien
  percent-encodeadas.
- `usuarios.foto` del id 2 apunta a un fichero que ya no existe; degrada a
  iniciales sin romper nada (`is_file()` comprobado en `navbar.php`,
  `perfil.php`, `configuracion.php`).

### Código — Fase 2 (nuevo)

- **`Tcg::HUECOS`** (en `db/consultas.php`) es el array `["POR","DF","DF",
  "DF","DF","MC","MC","MC","MC","DC","DC"]` — el índice ES el número de
  hueco (0–10). El orden del DOM en `mazos.php` sigue este array
  exactamente, y el CSS de la disposición sobre el campo (`.hueco:nth-child`)
  depende de que ese orden nunca cambie sin actualizar también el CSS.
- **`mt_rand()`/`rowCount()` con MySQL**: `rowCount()` cuenta filas
  *modificadas*, no *coincidentes*. `latirDuelo()` lo pisó una vez: dos
  latidos en el mismo segundo escriben el mismo `NOW()`, así que
  `rowCount()` daba 0 con la sala perfectamente viva. Se corrigió
  confirmando con una lectura aparte en vez de fiarse del recuento del
  `UPDATE`.
- **Los navegadores estrangulan `setInterval` en pestañas de fondo.** El
  margen de abandono de una sala de duelo (`duelo_latido_max`) se subió de
  15 a 45 segundos tras comprobar que cambiar de pestaña un instante ya
  cancelaba la sala por error. `duelo.js` además late inmediatamente al
  volver a hacerse visible la pestaña (`visibilitychange`), no solo por el
  temporizador.
- **`fuerzaAlineacion()` es el contrato de la Capa 1** de combate. No se
  modifica ni se envuelve dentro de sí misma: toda lógica nueva (aumento,
  futuros rasgos) se calcula alrededor de su resultado, en
  `calcularTotalFinal()`.
- **La alineación de un duelo se congela** (`duelo_alineaciones`) en el
  momento de comprometerse, copiando las cifras, no una referencia. Editar
  el mazo después de crear/aceptar una sala no cambia ya nada de ese duelo —
  verificado explícitamente con una prueba de "trampa" (mejorar el mazo tras
  abrir la sala no sube la fuerza usada al resolver).
- **No hay cron en este proyecto.** Todo lo que necesita "pasar el tiempo"
  (vencer el plazo de aumento, dar una sala por abandonada) se evalúa de
  forma perezosa: cada vez que alguien carga una pantalla o el sondeo AJAX
  llama al servidor, se comprueba si algo venció y se actúa ahí mismo.

---

## 9. Convenciones

- **El briefing manda en diseño y marca. El código existente manda en
  convenciones técnicas.**
- Nombres de clases CSS y de funciones **en español**.
- Comentarios en español, explicando **por qué**, no qué.
- **Sin dependencias nuevas de npm ni build step.** GSAP/Three.js siguen
  siendo la única excepción autorizada, reservada a §14, y **todavía no se
  han vendorizado** (`assets/js/vendor/` sigue vacío).
- JS sin framework, en IIFE, con `'use strict'`. La API pública compartida
  cuelga de `window.SRF`: `abrirModal`, `cerrarModal`, `toast`,
  `ceremonia`, y **`confirmar(texto, alAceptar)` NUEVO** — envuelve
  `partials/confirmar.php` para que cualquier pantalla pida confirmación sin
  montar su propio modal. `mercado.js` sigue gestionando ese mismo modal por
  su cuenta (además envía por AJAX); `SRF.confirmar` se aparta si detecta que
  ya hay una petición de `mercado.js` en curso.
- Escapar siempre con `htmlspecialchars()`. Consultas con PDO preparado.
- **Toda la capa de acceso a datos vive en la clase `Tcg`** (`db/consultas.php`).
  La Fase 2 no creó clases nuevas: añadió ~1150 líneas de métodos nuevos a
  esta misma clase, agrupados por comentarios de sección (`MAZOS`, `DUELOS`,
  `AUMENTO PRE-PARTIDO`, `CONFIGURACIÓN`).
- **Parámetros de balance en BD, no en código.** La tabla `configuracion`
  (clave/valor) guarda `duelo_k`, `duelo_p_min`, `duelo_p_max`,
  `duelo_plazo_aumento`, `duelo_latido_max`. Se leen con `$db->config($clave,
  $porDefecto)`. Cualquier parámetro de balance nuevo va aquí, nunca como
  constante embebida.
- **Patrón "sala en vivo" sin websockets**: latido periódico (cliente →
  servidor cada pocos segundos) + sondeo (la misma respuesta dice si ya se
  puede avanzar) + `navigator.sendBeacon` en `pagehide` para avisar al
  servidor si se cierra la pestaña. Es el patrón de `duelo.js` +
  `assets/ajax/duelo_estado.php`; reutilízalo si algo más necesita "estar en
  una sala esperando a otro jugador".

### Decisiones ya tomadas — no volver a abrirlas

- **La nav es `sticky`, no fija superpuesta.**
- **Nada de desplegables falsos en la navegación.**
- **La ceremonia recibe la carta renderizada en servidor.**
- **Geist va autoalojada**, no por CDN.
- **GSAP y Three.js están autorizados y vendorizados sin npm**, exclusivamente
  para §14.
- **Los ficheros retirados se mueven a `_legacy/`, no se borran.**
- **El texto del `<h1>` de la portada lo controla Alejandro.**
- **Confirmación explícita en modal propio** para toda acción con
  consecuencia económica. Nunca `confirm()` del navegador.
- **La SRF tiene que ganar visualmente a la legendaria.**
- **Se duela siempre con el mazo titular**, nunca eligiendo mazo por partida.
  Si no hay titular con los 11 huecos cubiertos, no se puede ni crear ni
  aceptar un duelo.
- **Un mismo cromo no puede repetirse en una alineación**, aunque haya varias
  copias suyas en la colección. Es legal tenerlas, no alinearlas dos veces.
- **Cualquier carta puede ir en cualquier hueco del mazo.** No hay reglas de
  posición al armar; lo que cambia es con qué estadística puntúa ahí. Es
  decisión explícita de Alejandro ("poder quien quieras donde quieras"), no
  se vuelve a discutir aunque permita alineaciones "raras" a propósito.
  Cualquier bloqueo de posición sería revertir esta decisión.
- **En la apuesta de carta de un duelo, la carta concreta la elige quien
  puja**, no el sistema automáticamente "la más valiosa".
- **El fallback del aumento pre-partido es aleatorio entre las 3 opciones**,
  no la de porcentaje más bajo (que era la especificación técnica original).
  Alejandro lo cambió explícitamente para no penalizar tan duro a quien no
  llega a tiempo. Es una **desviación documentada**, no un olvido — si se
  quiere volver a la versión determinista, es una decisión de Alejandro.
- **Los aumentos de ambos jugadores se destapan a la vez, solo cuando el
  duelo ya está resuelto.** Nunca antes, ni parcialmente: es la regla
  anti-abuso de la especificación de combate (evita que quien elige segundo
  tenga ventaja).

---

## 10. Fase 2 — detalle técnico de lo construido

### 10.1 Migración de base de datos

`db/migraciones/002_duelos_misiones_mazos.sql`. Aditiva, y **verificada
re-ejecutable sin duplicar nada** (se ha corrido varias veces durante el
desarrollo). Aplicar con:

```bash
C:\xampp\mysql\bin\mysql.exe -u root tcg < db/migraciones/002_duelos_misiones_mazos.sql
```

Tablas nuevas: `mazos`, `mazo_cartas`, `duelos`, `duelo_apuestas`,
`duelo_alineaciones`, `duelo_aumentos`, `configuracion`, `misiones`,
`misiones_progreso`, `minijuegos_partidas`.

Columnas nuevas en tablas existentes:

- **`cromos`**: `ataque`, `defensa`, `tecnica` (TINYINT UNSIGNED). Sembradas
  para las 38 cartas de posición jugadora (POR/DF/MC/DC); las 6 cartas no
  jugadoras (escudos, entrenadores, gerentes) se quedan a 0 a propósito.
  Fórmula: base por rareza (60→94) + ajuste por posición + variación
  determinista por `id_cromo`.
- **`duelos`**: `estado` amplíado de 3 a 6 valores (`creado`, `aceptado`,
  `aumento_pendiente`, `listo_para_resolver`, `resuelto`, `cancelado`);
  columnas de trazabilidad completa de la resolución (`total_bruto_*`,
  `total_final_*`, `probabilidad_victoria_creador`, `valor_sorteo`,
  `k_utilizado`, `aumento_vence`, `ultimo_latido`).

Valores de arranque en `configuracion` (todos **provisionales**, sin
calibrar con datos de duelos reales):

| Clave | Valor | Qué es |
|---|---|---|
| `duelo_k` | 400 | K de la curva Elo de resolución |
| `duelo_p_min` | 0.05 | probabilidad mínima de ganar, nunca 0 |
| `duelo_p_max` | 0.95 | probabilidad máxima de ganar, nunca 1 |
| `duelo_plazo_aumento` | 30 (segundos) | tiempo para elegir aumento |
| `duelo_latido_max` | 45 (segundos) | margen antes de dar una sala por abandonada |

### 10.2 Constructor de mazos (`mazos.php`, `assets/js/mazos.js`)

- 11 huecos fijos: 1 POR + 4 DF + 4 MC + 2 DC (`Tcg::HUECOS`). Cualquier
  carta en cualquier hueco; puntúa con `defensa` (POR/DF), `tecnica` (MC) o
  `ataque` (DC) según el hueco, no según su posición natural
  (`Tcg::ESTADISTICA_LINEA`).
- **La alineación se dibuja como un campo de fútbol real**, no como una
  rejilla plana: `.alineacion` es un contenedor con fondo de líneas de campo
  (SVG inline en el CSS: banda, círculo central, ambas áreas) y los 11
  huecos se posicionan de forma absoluta con `:nth-child` siguiendo
  exactamente el orden de `Tcg::HUECOS` (portero abajo, después DF, después
  MC, delanteros arriba).
- Cada hueco muestra un **retrato circular compacto** (no la tarjeta
  completa: 11 tarjetas completas en un campo no serían legibles), con el
  mismo lenguaje de borde de rareza que el resto del sitio — ver la técnica
  exacta en §3. La tarjeta completa, con estadísticas y rareza en texto, se
  ve en el selector de jugadores de abajo, que es donde hace falta el
  detalle para elegir.
- **El selector de jugadores agrupa copias repetidas** del mismo cromo con
  la insignia "×N" (mismo criterio y misma opción del componente que ya usa
  `coleccion.php`) — todas las copias de un jugador son intercambiables
  (mismas estadísticas) y solo una puede alinearse a la vez, así que no
  tiene sentido listar 200 veces la misma carta común.
- Interacción: **elegir hueco → elegir jugador**, con `<button>` normales
  (tap, clic, teclado — nunca arrastre). Avanza sola al siguiente hueco
  libre tras cada asignación.
- **Un mismo cromo no puede repetirse en el once**, validado en servidor
  (`guardarCartasMazo()`), no solo en la pantalla.
- Guardarraíles verificados con pruebas reales: una carta en un mazo no se
  puede vender; no se puede guardar un mazo ajeno, de tamaño incorrecto, con
  un hueco repetido o con el mismo jugador dos veces.

### 10.3 Duelos (`duelos.php`, `duelo.php`, `assets/js/duelos.js`, `duelo.js`, `assets/ajax/duelo_estado.php`)

**Máquina de estados:**

```
creado → aumento_pendiente → listo_para_resolver → resuelto
   ↓ (nadie entra, o el creador abandona)
cancelado
```

**Ciclo completo:**

1. El creador abre una sala con su mazo titular y una apuesta (monedas,
   retenidas al momento; o una carta concreta de una rareza, bloqueada al
   momento). Se queda "dentro" de la sala latiendo cada 3s
   (`assets/ajax/duelo_estado.php`); si deja de latir más de
   `duelo_latido_max` segundos, la sala se cancela sola y se devuelve lo
   apostado.
2. Un rival entra desde el lobby, con su propio mazo titular y su apuesta.
   En ese mismo POST se congela la alineación de AMBOS
   (`duelo_alineaciones`) y el duelo pasa a `aumento_pendiente`.
3. Ambos jugadores llegan a la pantalla de partido y ven **sus propias** 3
   opciones de aumento (generadas una sola vez, nunca las del rival — ni
   siquiera se consultan del lado servidor durante esta fase). Tienen
   `duelo_plazo_aumento` segundos para elegir; si no eligen, se les asigna
   una al azar entre las 3.
4. En cuanto ambos tienen aumento elegido, el duelo se resuelve: fuerza bruta
   de cada uno (`fuerzaAlineacion()`) + bono del aumento
   (`calcularTotalFinal()`) → probabilidad vía curva Elo, acotada entre
   `duelo_p_min` y `duelo_p_max` → sorteo real → ganador → apuestas movidas
   → resultado guardado con trazabilidad completa (probabilidad, sorteo, K
   usado).
5. Los dos aumentos se destapan a la vez en la pantalla de resultado, junto
   con el desglose de fuerza por las 4 líneas y las 11 cartas de cada
   alineación.

**Verificado con pruebas reales, no solo escrito:** ciclo completo con
apuesta de monedas (retención + bote al ganador), ciclo completo con apuesta
de carta (transferencia de propiedad), congelación de alineación resistiendo
un intento de "trampa" (mejorar el mazo tras abrir la sala), curva de
probabilidad acotada incluso con diferencias de fuerza extremas, tiers de
aumento sobre 3.000 muestras (60,47/29,83/9,70 % contra 60/30/10 esperado),
orden de aplicación de la fórmula maestra (categoría antes que total, nunca
encadenado), latido y cancelación automática de sala abandonada con
devolución exacta de lo apostado, sondeo detectando en vivo que el rival
completó su elección sin recargar a mano.

**Lo que es explícitamente provisional:**

- El marcador de goles (`marcadorDuelo()`) es un placeholder funcional: nunca
  contradice al ganador ya sorteado, pero el algoritmo exacto no se considera
  el diseño final.
- `duelo_k`, `duelo_p_min`, `duelo_p_max` no están calibrados con datos de
  duelos reales.

### 10.4 Capa 2 de combate — rasgos/sinergias (APLAZADA, falta contenido)

Diseño de motor ya cerrado (no discutirlo salvo problema real encontrado); lo
que falta es **contenido de equipo**, y es el motivo por el que esta capa no
se ha construido:

- Un rasgo es una etiqueta asignada a mano a cartas concretas. Con
  suficientes cartas del mismo rasgo en la alineación titular (11 cartas, no
  toda la colección), se activa un nivel de bono. Solo cuenta el nivel más
  alto por rasgo; entre rasgos distintos, los bonos se suman agrupados por a
  qué afectan (una línea concreta, o el total general).
- El motor de cálculo (`calcularTotalFinal()`) **ya tiene el hueco
  preparado**: aplica bonos de categoría junto con el aumento, y un bono de
  total al final, sobre la suma ya ajustada. Hoy esos bonos de rasgo valen
  siempre 0. Construir esta capa no obliga a tocar el deck builder, la curva
  de resolución ni ninguna pantalla ya hecha — solo añadir un paso más al
  cálculo que ya existe.
- **No existe ninguna tabla de rasgos todavía** (a diferencia de Misiones,
  que sí tiene esquema aunque no pantalla). Cero implementación.

**Decisiones de contenido pendientes**, con datos reales del catálogo para
decidir con criterio:

Cartas jugadoras por equipo (solo 2 de 6 equipos tienen catálogo suficiente
para un rasgo con varios niveles):

| Equipo | Cartas jugadoras |
|---|---|
| Instituto Zeus | 17 |
| Academia Plenilunio | 16 |
| Alpino | 2 |
| Triple C | 1 |
| Zanark Domain | 1 |
| Inazuma Kids CF | 1 |

Afinidades disponibles: Montaña, Fuego, Viento, Bosque, sin afinidad.
Distribución de rareza entre las 38 jugadoras: Común 11, Poco común 8, Raro
6, Épico 7, Legendario 1, SRF 5.

Preguntas abiertas que hay que responder antes de construir: ¿el criterio de
rasgo es por equipo, por afinidad, por otra cosa, o varios combinados?; qué
cartas concretas lleva cada rasgo; cuántos niveles y cuántas copias exige
cada uno; qué bono da cada nivel y a qué afecta.

---

## 11. Fase 3 — pulido y escala

Sin cambios respecto a antes: se abre solo cuando la Fase 2 esté cerrada y
estable de verdad (con Misiones construidas y la Capa 2 resuelta, no solo con
Mazos y Duelos). Tres bloques: rediseñar `panel/` al sistema nuevo, unificar
el motion de las ceremonias de sobre y duelo, y documentar cómo añadir una
expansión de temporada.

---

## 12. Pendientes, en orden aproximado de lo que probablemente toca primero

1. **Pantalla de Misiones.** El esquema y las 8 misiones de arranque ya están
   en BD (`misiones`, `misiones_progreso`); falta la pantalla y la lógica que
   calcule el progreso (derivado de consultas ya existentes — cartas
   distintas, duelos ganados, mazos creados — nunca un contador duplicado).
   No depende de la Capa 2 ni de nada pendiente; se puede hacer en cualquier
   momento.
2. **Decidir contenido de la Capa 2** (§10.4) y construirla.
3. **Minijuegos** — ni contenido ni pantalla; aplazados explícitamente por
   Alejandro, sin fecha.
4. **Calibrar `duelo_k`/`duelo_p_min`/`duelo_p_max`** con datos de duelos
   jugados de verdad, una vez haya volumen real de partidas.
5. **Diseñar el algoritmo definitivo del marcador de goles**, hoy
   placeholder.
6. **`_legacy/` se puede borrar** cuando Alejandro confirme que no echa nada
   en falta (ya no es tan urgente conservarlo como antes de tener git, pero
   sigue sin haber orden de borrarlo).
7. **§14, ceremonia 3D de sobres** — sigue sin empezar, es la extensión
   autorizada aparte de todo este orden.

---

## 13. Comprobaciones antes de dar algo por terminado

```bash
for f in *.php partials/*.php components/*.php db/*.php assets/ajax/*.php; do
  C:/xampp/php/php.exe -l "$f"
done
```

En navegador, a 375×812 y en escritorio:

- Sin scroll horizontal de página.
- Recorre con el tabulador: foco siempre visible y nunca tapado.
- Modales: se abren, atrapan el foco, cierran con Esc y devuelven el foco.
- Con "reducir movimiento" activo: sin volteos ni destellos.
- Objetivos táctiles ≥24×24 (44×44 en acciones primarias; en el mazo, el
  botón completo cuenta, no solo el retrato visual).
- Un solo `<h1>`, `main#contenido`, `.skip-link` y aviso legal en cada página.

Si tocas Duelos, añade además:

- Crear una sala, dejarla sola más de `duelo_latido_max` segundos, y
  comprobar que se cancela y devuelve lo apostado.
- Comprobar en `duelo_aumentos` que las opciones de un jugador nunca
  aparecen en la respuesta que recibe el otro.
- Comprobar que editar el mazo después de comprometerse a un duelo no
  cambia el resultado (fuerza usada = la congelada, no la del mazo vivo).

Si trabajas en §14, sigue las comprobaciones específicas que ya tenía este
documento (vendorizado sin npm, sin modelos 3D en el repo, `prefers-reduced-motion`
cubierto, botones de skip sin dejar timelines colgadas) — sin cambios.

---

## 14. Ceremonia de sobres 3D y reveal secuencial de cartas

**Estado: sin empezar.** Sin cambios de fondo respecto a como se dejó
especificado. Es una extensión explícita y autorizada de la ceremonia de
sobres ya cerrada en la Fase 1: no se reabre esa fase entera, se construye
esto **encima**, en los mismos ficheros (`sobres.php`,
`partials/ceremonia.php`, `assets/js/sobres.js`, `assets/js/ceremonia.js`,
`assets/css/components.css`) más los nuevos módulos que se describen abajo.

### 14.0 Tecnología permitida

- **GSAP está permitido y se debe usar** como orquestador de todas las
  timelines de esta sección (`gsap.timeline()`, `gsap.to()`,
  `gsap.quickTo()`, y `ScrollTrigger` si la vitrina usa scroll). Vendorizar en
  `assets/js/vendor/gsap/` (build UMD standalone, sin npm, sin bundler).
- **Three.js también está permitido**, para lo que CSS 3D transforms no cubra
  bien por rendimiento (partículas del walkout de §14.5, o un
  `CSS3DRenderer` sobre el propio DOM de la vitrina si el CSS puro no aguanta
  bien). Vendorizar en `assets/js/vendor/three/` (build ESM de un solo
  fichero, sin npm).
  - Lo único que sigue prohibido es **importar modelos 3D como asset**
    (`.glb`/`.gltf`/`.obj`/`.fbx`/`.blend`). Si se usa Three.js, las
    geometrías se generan por código (planos, sprites, partículas), nunca se
    cargan desde un fichero de modelo externo.
- Motivo de la corrección respecto a versiones antiguas de este documento: el
  desarrollador que dejó esto sin hacer no evitó GSAP/Three.js por una
  decisión de producto, sino porque no sabía usarlos. Alejandro ha confirmado
  que sí quiere que se usen aquí.

### 14.1 Vitrina de expansiones (cajas tipo blaster)

Sustituye o extiende la pantalla `sobres.php` actual (comprobar primero qué
hay: puede que hoy solo liste sobres en una rejilla plana, sin cajas por
expansión).

- Cada expansión se representa como una caja tipo blaster: imagen de caja +
  logo del set + nombre + precio en monedas + stock/disponibilidad, usando los
  datos que ya devuelve `db/consultas.php` para expansiones y sobres (revisar
  qué consulta existe antes de inventar una nueva).
- Las cajas se disponen en fila con perspectiva 3D (`perspective` en el
  contenedor, `transform-style: preserve-3d` en cada caja): la caja central se
  ve de frente y ampliada; las laterales rotan en Y (`rotateY`) y se alejan en
  Z (`translateZ` negativo), con `scale` y opacidad reducidos.
- El scroll horizontal (rueda, drag, flechas, y swipe en móvil) anima
  `rotateY`/`translateZ`/`scale` de cada caja según su distancia al centro,
  con `gsap.timeline()` + `ScrollTrigger` o un listener de `wheel`/`touch`
  atado a `gsap.to()`.
- Contener bien el wrapper (`overflow: hidden` solo en ese contenedor, sin
  tocar la regla global de `overflow-x: clip` del body) para no introducir
  scroll horizontal de página.

### 14.2 Interior de la caja: sobres interactivos al cursor

Al seleccionar una caja, se muestran los sobres de esa expansión en abanico o
cuadrícula con profundidad escalonada (`translateZ` por índice).

- Con `mousemove` sobre el contenedor, los sobres cercanos al cursor se
  levantan (`translateY` negativo), se acercan (`translateZ` positivo) y
  rotan hacia el puntero (efecto tilt), usando `gsap.quickTo()` por eje para
  no penalizar rendimiento. Los sobres lejanos vuelven a su reposo con
  `ease: power2.out`.
- En touch, sustituir el `mousemove` por el sobre actualmente centrado en un
  scroll/swipe horizontal; no hay cursor que simular.

### 14.3 Selección y modal de confirmación

Al hacer clic en un sobre:

- El sobre seleccionado se centra y amplía (`translateZ` máximo); el resto se
  desenfoca (`filter: blur()`) y se oscurece con overlay.
- Se abre el modal de confirmación **usando el patrón ya existente**
  (`partials/confirmar.php` / `SRF.confirmar()`, ver §9), no un modal nuevo:
  - Título: "¿Quieres abrir este sobre?"
  - Texto dinámico: coste (`{precio}` monedas) y saldo actual
    (`Tienes {saldoActual} monedas`), leído del mismo sitio que ya usa
    `actualizarMonedasNav()`.
  - Botón de confirmar deshabilitado si el saldo es insuficiente, con aviso
    visual.
  - "Abrir sobre" / "Cancelar" (cancelar revierte la animación de selección).

### 14.4 Apertura del sobre y aura anticipatoria de rareza

- Cierre rápido del modal (fade), animación de "rasgado" del sobre en 3D (dos
  mitades con `rotateX` divergentes) y flash/partículas CSS antes de pasar al
  reveal.
- **Esto es lo que hoy no existe y que §4 da por hecho:** justo antes de que
  cada carta se voltee, el fondo detrás de ella debe emitir un aura pulsante
  (radial-gradient animado + `box-shadow`/`filter: drop-shadow` apilados en
  `@keyframes`) cuya intensidad/color depende de la rareza real de esa carta
  concreta (plata tenue → oro intenso → SRF multicolor/holográfico), de forma
  que el aura **anticipe** la rareza antes de ver la carta.
- Para legendaria/SRF, añadir además un efecto holográfico sobre la carta ya
  revelada que reaccione al `pointermove` (gradiente que cambia con el ángulo
  del cursor, al estilo de los efectos CSS-only que recrean el holográfico de
  cartas físicas de coleccionable).

### 14.5 Reveal secuencial de cartas, con skip

- Las cartas del sobre se muestran **de una en una**, no todas a la vez:
  1. Aparece de espaldas en el centro (`ease: back.out`).
  2. Tras el aura anticipatoria de §14.4, flip 3D de reverso a frente
     (`rotateY` 180°→0°, contenedor con `transform-style: preserve-3d`, caras
     `.front`/`.back` con `backface-visibility: hidden`).
  3. Se mantiene visible unos segundos y se desliza a un mini-stack lateral de
     "ya reveladas" antes de dar paso a la siguiente.
- Cada carta es un paso de una `gsap.timeline()` única para todo el sobre.
- **Botón "Saltar animación"** (salta solo la carta actual a su estado final)
  y **botón "Saltar todo"** (resuelve toda la timeline al instante, todas las
  cartas visibles ya en cuadrícula, cara arriba). Implementar limpiando la
  timeline (`timeline.kill()` tras fijar el estado final con
  `gsap.set()`), no dejando el `timeline.progress(1)` a medio camino con
  listeners aún vivos.
- Respeta `prefers-reduced-motion`: si está activo, no hay timeline que saltar
  porque no se anima nada; se pinta el estado final directamente.

### 14.6 Secuencia especial estilo FIFA Ultimate Team para rarezas exclusivas

Solo para legendaria y SRF (las dos rarezas top de la tabla de §4), y como
función/timeline **separada y reutilizable**, no mezclada con el flip
estándar de §14.5:

- Antes del flip de esa carta: fondo se oscurece del todo, rayos de luz
  animados (gradientes cónicos o lineales rotando en CSS), partículas 2D
  (divs o canvas 2D — o Three.js con geometría generada por código, ver
  §14.0 — nunca WebGL con modelos) cayendo o flotando, y el nombre/rareza
  apareciendo con tipografía grande (usar la escala `display` de §4) y
  `text-shadow` pulsante.
- Preámbulo de 2–3 segundos, saltable con los mismos botones de §14.5.
- Tras el preámbulo, el flip normal pero amplificado: aura más grande que la
  de §14.4, ligero *screen shake* (`gsap.to` con pequeñas traslaciones
  aleatorias y `yoyo: true`), y un hook `onExclusiveReveal()` preparado para
  disparar audio en el futuro (la implementación de audio en sí es opcional
  por ahora).
- Disparar esta secuencia leyendo el campo de rareza/`esExclusiva` de la carta
  ya devuelta por `consultas.php`; si ese campo no existe todavía, decirlo
  explícitamente antes de improvisarlo, no inventar un nombre de columna.

### 14.7 Entregables de §14

1. Vitrina 3D de cajas por expansión con scroll (§14.1).
2. Vista interior de caja con sobres interactivos al cursor/touch (§14.2).
3. Modal de confirmación reutilizando el patrón existente (§14.3).
4. Animación de apertura del sobre + sistema de aura anticipatoria por rareza
   (§14.4).
5. Reveal secuencial con flip, mini-stack de reveladas, y botones de skip
   individual/global (§14.5).
6. Secuencia especial estilo FUT para legendaria/SRF, como timeline
   independiente (§14.6).
7. GSAP (y Three.js si hace falta) vendorizados en `assets/js/vendor/`, sin
   npm ni build step, colgando de `window.SRF.ceremonia3D`.
8. Variables CSS centralizadas para intensidad de perspectiva, aura y
   duración, coherentes con los tokens de motion de §4.
9. Resumen de cierre explicando qué de lo descrito en §4 sobre la SRF ha
   quedado realmente implementado tras §14, para que ese apartado deje de ser
   aspiracional.

Además de las comprobaciones generales de §13, si trabajas en §14 verifica:

- `assets/js/vendor/gsap/` y (si se usa) `assets/js/vendor/three/` existen en
  disco y se cargan con `<script>`/`<script type="module">` normal, sin
  ningún `package.json`, `node_modules/` ni paso de build nuevo en el repo.
- No hay ningún fichero `.glb`/`.gltf`/`.obj`/`.fbx`/`.blend` añadido al repo.
- Con `prefers-reduced-motion` activo, la vitrina no rota sola, el tilt al
  cursor no se aplica, el reveal salta directo al estado final de cada carta,
  y la secuencia estilo FUT se sustituye por el aura final estática sin rayos
  ni partículas.
- Los botones "saltar animación" (por carta) y "saltar todo" dejan el DOM en
  el mismo estado final que si se hubiera visto la animación completa, sin
  timelines de GSAP colgadas ni listeners duplicados tras usarlos varias
  veces seguidas.
