# Importador de `datos_oficiales.json` desde el panel de administración

> Spec, 2026-08-07. Fase 3 (panel de administración), trabajo independiente y en
> paralelo a la Fase 2 que se está construyendo en otra sesión — no la toca.

## 1. Objetivo

Alejandro sube manualmente el `datos_oficiales.json` que exporta la web de la
Superliga Frontier desde el panel de administración. El script crea, en una
expansión que él elige, las cartas de **todos los jugadores actualmente en
plantilla de algún equipo**, más las cartas de escudo/entrenador/gerente de
esos equipos. Nunca se ejecuta solo: siempre es una acción manual, con
previsualización antes de escribir nada en la base de datos.

## 2. Fuera de alcance

- Agentes libres (`agentes_libres` en el JSON): no se crean cartas para ellos
  en esta importación.
- `PRESIDENTE`: el JSON no trae ese dato, no se crea.
- Cualquier trabajo de la Fase 2 en curso en paralelo.
- Edición masiva de cartas ya creadas, gestión de mercado, sobres, etc.

## 3. Dónde vive

- **Página nueva:** `panel/importar.php`, mismo patrón que `panel/cromos.php` /
  `panel/expansiones.php` (guard de `$_SESSION['dictador']`, `admin.css`,
  Bootstrap Icons). Entrada nueva en `panel/navbar.php`.
- **Lógica de datos:** todo en `Tcg` (`db/consultas.php`), sección nueva
  `IMPORTACIÓN DATOS OFICIALES`. No se crean clases nuevas.
- **Helpers puros** (mapeo de posición/afinidad, similitud de nombres de
  equipo, cálculo de rankings de rareza, fórmula de stats): funciones
  aisladas y testeables en la misma sección de `Tcg`, para poder darles un
  self-check sin arrancar el panel entero.

## 4. Flujo (wizard de dos pasos, con `$_SESSION`)

**Paso 1 — Subir y previsualizar** (`POST` a `importar.php`, sin escribir en
BD):

1. Formulario: `<input type="file" accept=".json">` + selector de expansión
   destino (`listarExpansiones()`; si la que quiere no existe, la crea antes
   desde `expansiones.php`, este importador no crea expansiones).
2. Se decodifica el JSON, se guarda en `$_SESSION['import_json']` (y la
   expansión elegida en `$_SESSION['import_id_expansion']`) para el paso 2.
3. Se calcula y se muestra, sin tocar la BD:
   - Nº de jugadores a crear / nº omitidos (ya existentes en esa expansión).
   - Equipos: cuántos matchean exacto contra `equipos`, cuántos no tienen
     ningún parecido (se crearán automáticamente), y una **tabla de equipos
     ambiguos** — similitud de texto alta pero no exacta (ej. "Instituto
     Kirkwood" del JSON vs "Instituto Kikrwood" de la BD) — con un selector
     por fila: usar nombre del JSON / usar nombre de la BD / escribir uno
     distinto.
   - Afinidades no reconocidas (valores nulos, texto suelto, URLs por error
     de datos en el JSON) y cuántos jugadores caerán en "no-afi" por eso.
   - Nº de cartas de escudo/entrenador/gerente a crear.

**Paso 2 — Confirmar y crear** (`POST` a `importar.php`, con las decisiones
del paso 1 más un campo de confirmación):

1. Resuelve los equipos ambiguos con lo elegido en el paso 1.
2. Crea los equipos nuevos que hagan falta (`INSERT INTO equipos (nombre)`).
3. Para cada jugador a importar: descarga su foto, la optimiza a WebP, crea
   el cromo.
4. Para cada equipo con jugadores importados: crea sus cartas de escudo /
   entrenador / gerente (las que tengan el campo relleno en el JSON).
5. Llama a `derivarRasgosConfiguracion()` una sola vez al final (no por
   carta), igual que hace `panel/cromos.php` tras crear/editar.
6. Limpia `$_SESSION['import_json']` y muestra un resumen final (creados /
   omitidos / equipos nuevos / fotos que fallaron al descargar).

Si falla la descarga de una foto puntual, esa carta se crea igualmente con
`imagen` vacío y se lista en el resumen final como aviso — no bloquea el
resto del lote.

## 5. Mapeo de datos

| JSON | Columna en `cromos` | Regla |
|---|---|---|
| `nombre` (jugador) | `nombre` | tal cual |
| `posicion` | `posicion` | `POR→POR`, `DEF→DF`, `MED→MC`, `DEL→DC` |
| `afinidad` | `id_afinidad` | `Fuego→2`, `Bosque→4`, `Montaña`/`montaña`→1, **`Aire→3` (Viento)**; cualquier otro valor (nulo, texto no reconocido, URL filtrada por error del JSON) → `5` (no-afi), y se cuenta como aviso |
| equipo del jugador | `id_equipo` | exacto (case-insensitive, trim) → usa el existente; similar (ver §6) → lo que se elija en el paso 1; sin parecido → crea fila nueva en `equipos` |
| `foto` (URL cloudfront) | `imagen` | se descarga con GD, se convierte a WebP, se guarda en `assets/img/Cromos/Importados/<slug-equipo>/<slug-jugador>.webp`; si falla, `imagen = ''` |
| — | `descripcion` | `''` |
| — | `id_expansion` | la elegida en el paso 1 |
| — | `cupo_numerado` | `NULL` (no aplica a esta importación) |

Cartas de equipo (una por campo no vacío, por cada equipo con jugadores
importados):

| Tipo | `posicion` | `nombre` | `imagen` | `id_rareza` | `id_afinidad` |
|---|---|---|---|---|---|
| Escudo | `ESCUDO` | `"Escudo {equipo}"` | `''` | 5 (Legendario) | 5 (no-afi) |
| Entrenador | `ENT` | valor de `entrenador` | `''` | 5 (Legendario) | 5 (no-afi) |
| Gerente | `GER` | valor de `gerente` | `''` | 5 (Legendario) | 5 (no-afi) |

## 6. Equipos: coincidencia difusa

Para cada equipo del JSON con jugadores a importar, comparar su nombre
(normalizado: minúsculas, sin tildes, trim) contra los nombres existentes en
`equipos`:

- **Coincidencia exacta tras normalizar** → usa ese `id_equipo`, sin
  preguntar.
- **Similar pero no exacta** (`similar_text()` ≥ ~75% o distancia de
  Levenshtein baja) → se lista en el paso 1 como ambiguo, con tres opciones:
  nombre del JSON, nombre de la BD, o texto libre. El paso 2 usa lo elegido:
  si coincide con uno existente, reutiliza su id; si no, crea uno nuevo con
  ese nombre.
- **Sin ningún parecido** → se crea automáticamente un `equipo` nuevo con el
  nombre del JSON, sin preguntar.

## 7. Rareza

**Base:** `titular === true` → Poco común (`id_rareza=2`); resto → Común
(`id_rareza=1`).

**Promoción** (se aplica después, el valor más alto gana si un jugador cae en
varias listas):

1. **Goleadores de la temporada anterior**: la entrada más reciente de
   `historial_temporadas` distinta de la temporada actual (`config.temporada`
   del JSON), rankeando su campo `goles` de esa temporada. Solo cuenta si el
   jugador sigue en la plantilla actual de algún equipo (si ya no juega, no se
   le crea carta y no cuenta).
2. **Goleadores actuales**: campo `goles` (temporada en curso) de
   `equipos[].jugadores[]`, rankeado de mayor a menor.
3. **Mejor jugador de cada equipo**: por equipo, el jugador con mayor
   `goles + asistencias` de su plantilla actual (un valor por equipo); todos
   esos "mejores" se rankean juntos.

En cada lista: puestos 1-3 → Épico (`id_rareza=4`); puestos 4-10 → Raro
(`id_rareza=3`). Empates se resuelven por orden de aparición en el JSON (no
es crítico acertar el desempate, es cosmético).

## 8. Stats de combate (`ataque` / `defensa` / `tecnica`)

Hoy el panel las deja a 0 (el formulario de `cromos.php` no las expone). Para
que las cartas importadas pesen en combate, se genera una estimación:

```
BASE_TOTAL = ['comun' => 165, 'poco_comun' => 190, 'raro' => 215, 'epico' => 240]
// jitter aleatorio ±8% sobre el total antes de repartir
// (la promoción de rareza de jugador nunca pasa de Épico, ver §7 — Legendario
// solo lo usan las cartas de equipo, que van con 0/0/0 y no entran aquí)

SPLIT_POR_POSICION = [ // fracciones de BASE_TOTAL, suman 1.0
  'POR' => ['ataque' => 0.20, 'defensa' => 0.45, 'tecnica' => 0.35],
  'DF'  => ['ataque' => 0.25, 'defensa' => 0.45, 'tecnica' => 0.30],
  'MC'  => ['ataque' => 0.33, 'defensa' => 0.30, 'tecnica' => 0.37],
  'DC'  => ['ataque' => 0.45, 'defensa' => 0.25, 'tecnica' => 0.30],
]
// ENT/GER/ESCUDO: 0/0/0, igual que las cartas de equipo existentes en el catálogo.

stat = round(BASE_TOTAL[rareza] * SPLIT_POR_POSICION[posicion][stat] * jitter)
clamp cada stat entre 1 y 99
```

`BASE_TOTAL` sale de promediar las stats reales ya existentes en `cromos` por
rareza (consulta hecha sobre la BD actual: común ~165-170, poco común ~190,
raro ~210-215, épico ~235-245 de suma). El reparto por posición sigue el
mismo sesgo que ya muestran los datos reales (portero fuerte en defensa,
delantero fuerte en ataque).

`ponytail:` heurística sin playtesting dedicado — es una aproximación a lo
que ya hay en el catálogo, no un sistema derivado del documento de balance.
Retocar `BASE_TOTAL`/`SPLIT_POR_POSICION` si el balance no cuadra al jugar.

## 9. Reimportar / idempotencia

Antes de crear una carta (de jugador o de equipo) se comprueba si ya existe
una fila en `cromos` con el mismo `nombre` + `id_equipo` + `id_expansion`
elegida. Si existe, se omite y se cuenta en el resumen. Así se puede volver a
subir el archivo (por ejemplo tras una jornada nueva) sin duplicar todo el
catálogo — mientras se elija la misma expansión.

## 10. Errores y casos límite

- **Fotos que no se pueden descargar** (URL caída, timeout, formato no
  soportado por GD): no bloquean el lote; la carta se crea con `imagen=''` y
  se lista como aviso en el resumen final.
- **JSON mal formado o sin las claves esperadas** (`equipos`, `jugadores`):
  el paso 1 falla con un mensaje claro, no se guarda nada en sesión.
- **Campo `entrenador`/`gerente` vacío**: no se crea esa carta para ese
  equipo.
- **Equipo con 0 jugadores en el JSON** (ocurre hoy: Raimon, Oscuridad
  Ancestral, Ragnah): no se crea ni el equipo ni sus cartas de
  escudo/entrenador/gerente, porque no hay jugadores que los enganchen a una
  expansión.
- **Tamaño de subida**: el JSON pesa ~1.1 MB; si `upload_max_filesize` /
  `post_max_size` de XAMPP lo bloquean, se avisa con un mensaje explícito en
  vez de fallar en silencio (no se toca `php.ini` como parte de esta spec;
  si hace falta, se señala en el resumen de implementación para que Alejandro
  lo suba a mano).
- **Descarga de 500+ fotos**: puede tardar; el paso 2 corre con
  `set_time_limit(0)` y muestra progreso o al menos no expira a medio
  proceso.

## 11. Verificación

- `C:/xampp/php/php.exe -l` sobre todos los ficheros tocados, como manda §13
  del CLAUDE.md.
- Self-check aislado (sin BD ni panel) de las funciones puras: mapeo de
  posición/afinidad, similitud de nombres de equipo, ranking de rareza y
  fórmula de stats — con una fixture pequeña (3-4 equipos, unos pocos
  jugadores) y `assert`, ejecutable por CLI.
- Prueba manual con el `datos_oficiales.json` real: subir → revisar que el
  resumen del paso 1 cuadra con lo explorado (43 equipos en el JSON, 21 ya en
  BD, ~22 nuevos, ~133 agentes libres excluidos) → confirmar → comprobar en
  `panel/cromos.php` que las cartas aparecen con equipo/posición/afinidad
  correctos y que `derivarRasgosConfiguracion()` no rompe el reparto existente
  (12/10/8/8 sigue igual, según §13 del CLAUDE.md).
- Volver a subir el mismo archivo y comprobar que la segunda vez no duplica
  nada (resumen en 0 creados, todo omitido).
