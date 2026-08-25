# SEO — Superliga Frontier TCG

> Auditoría del **2026-08-25** contra producción (`tcgfrontier.es`) y contra el
> código, no una lista genérica de buenas prácticas. Repasada después con el
> plugin `claude-seo` v2.2.4, que añadió lo del punto 2.f y confirmó el resto.
>
> **Health Score: 43/100.** Technical 40 · Contenido 45 · On-Page 60 ·
> Schema **0** · Rendimiento 65 · AI Search 20 · Imágenes 70.
>
> ⚠️ **NADA DE ESTO ESTÁ IMPLEMENTADO. Es el plan, no el estado.**
>
> Documento hermano de `branding/CLAUDE.md`, que es el contexto general del
> proyecto. Aquí solo SEO. Si tocas algo de esto, actualiza este fichero — y si
> lo implementas, muévelo de «pendiente» a «hecho» con la fecha.

---

## 0. El techo, para no vender humo

Esto es un TCG fan-made de una comunidad concreta. **El SEO no va a traer
tráfico masivo haga lo que haga.** El objetivo realista es doble y modesto:
que quien busque la marca o el nombre de una carta encuentre el sitio, y que
un enlace compartido por Discord o X se vea decente. Con ese listón puesto,
el orden de abajo es por retorno real, no por lo que dicta un checklist.

## 1. ⚠️ La superficie indexable son DOS páginas

Lo primero que hay que entender antes de tocar nada. Todas las pantallas
exigen sesión y redirigen a login; lo único público es:

- `/` → `landing.php` (la portada, que redirige a `hoy.php` SI hay sesión)
- `plantilla.php` (el catálogo, que funciona sin sesión como escaparate)

Y ya. `acceso.php` y `404.php` son públicas pero no interesa indexarlas.

**Eso está BIEN por diseño** —un TCG no expone el mercado ni los duelos— pero
significa que hoy Google tiene dos páginas que indexar de un catálogo de
**592 cartas y 36 equipos**. Cualquier conversación sobre SEO en este proyecto
empieza aquí.

## 2. Prioridad 1 — lo barato que está sin hacer

**a) Cuatro URLs sirven la misma portada con código 200.** Comprobado con
`curl` contra producción:

```
http://tcgfrontier.es/        200   ← ni siquiera redirige a https
https://www.tcgfrontier.es/   200
https://tcgfrontier.es/       200
/landing.php → 301 → /landing       (ver el punto 4: esa regla NO está en el repo)
```

No hay `<link rel="canonical">` en ninguna parte de `partials/head.php`, así
que nada le dice a Google cuál es la buena y reparte señales entre variantes.
Se arregla con 301 a una sola forma más el canonical en `head.php`.

**b) No hay Open Graph ni Twitter Card.** Probablemente **lo de más retorno
inmediato de toda la lista**, aunque no sea SEO puro: esta comunidad se mueve
por Discord y por X, y hoy compartir el enlace produce una línea de texto pelada
sin imagen ni descripción. Con `og:image` apuntando a arte de carta, sale una
tarjeta. Eso se nota en clics el mismo día, no en tres meses.

**c) `robots.txt` y `sitemap.xml` no existen** — los dos dan 404 en producción.

**d) Falta `noindex`** en `acceso.php`, `styleguide.php` y `404.php`. La guía de
estilo sobre todo: es una página interna de trabajo y hoy es indexable.

**e) El meta description miente.** `landing.php` dice «470 fichas» y hay **592**.
Conviene sacarlo de un `COUNT(*)` en vez de dejarlo escrito a mano, que es lo
que hace que se quede viejo.

**f) ⚠️ CADENAS DE CINCO REDIRECCIONES HASTA EL LOGIN.** Lo más serio que salió
al pasar la herramienta, y no lo había visto a ojo. Trazado salto a salto sobre
`mercado.php`:

| # | URL | Respuesta |
|---|-----|-----------|
| 1 | `/mercado.php` | 301 → `/mercado` |
| 2 | `/mercado` | 302 → `login.php` |
| 3 | `/login.php` | 301 → `/login` |
| 4 | `/login` | 301 → `acceso.php?modo=entrar` |
| 5 | `/acceso.php?modo=entrar` | 301 → `/acceso?modo=entrar` |
| 6 | | **200** |

**Causa raíz:** el servidor reescribe TODO `.php` a URL sin extensión (punto 4)
y el código PHP redirige siempre a `.php`. Cada `header('Location')` cuesta
**dos** saltos en vez de uno. Google recomienda no pasar de 3; a partir de 5
puede dejar de seguir. Afecta a `mercado`, `sobres`, `perfil`, `duelos`,
`cadenas` y `misiones`.

**Arreglo:** que los `header('Location: …')` apunten a URL sin extensión, o
quitar la reescritura. Cualquiera de los dos parte la cadena por la mitad.

**g) Cero cabeceras de seguridad.** Ni `Strict-Transport-Security`, ni
`X-Content-Type-Options`, ni `X-Frame-Options`, ni `Referrer-Policy`, ni CSP.
Con `http://` sirviendo 200 (punto a), la falta de HSTS es la que más pesa.

**h) Ninguna imagen declara `width`/`height`: 0 de 417.** Sin dimensiones el
navegador no reserva el hueco y el contenido salta al cargar. **CLS es una de
las tres Core Web Vitals**, y esta es la corrección de rendimiento más barata
que queda por hacer.

**i) Detalles menores:** `/favicon.ico` en la raíz da 404 (el declarado en
`head.php` sí responde 200, pero los rastreadores piden el de la raíz por
convención); 19 rutas de imagen llevan espacios sin codificar
(`Cromos/ALL STARS/…`); y la portada sale con `Cache-Control: no-store` +
`Vary: Cookie` porque `landing.php` llama a `session_start()`, así que ningún
CDN la puede guardar.

## 3. Prioridad 2 — la única medida que puede traer tráfico nuevo

**Dar URL propia a cada carta**: `/carta/mark-evans`, `/carta/shawn-froste`…

Hoy las cartas se abren en un **modal**, con `<button data-ficha-carta>` en
`components/carta.php`. No hay `<a href>` en ninguna parte, así que **para
Google no existen**. Se pasaría de 2 páginas indexables a ~600, y son
exactamente lo que la gente busca (el nombre de un jugador).

Casi todo el trabajo ya está hecho: la ficha existe
(`partials/ficha_carta.php`), los datos están en la base, y los `alt` de las
imágenes ya son descriptivos (`Ilustración de {nombre}`). Falta la ruta, el
render sin modal y un `JSON-LD` por carta.

Medido en producción, para dimensionar el problema: `/plantilla?ver=todas`
sirve **470 cartas de una vez**, **641 KB de HTML** sin comprimir (33 KB
comprimido), **cero enlaces de paginación** y las 470 como
`<button data-ficha-carta>`. Paginar resuelve el peso Y da las URL, así que
son la misma tarea.

⚠️ **HAZ ESTO PRIMERO O ROMPERÁS TODAS LAS IMÁGENES.** El arte se sirve con
rutas **relativas**: `src="./assets/img/Cromos/…"`. En `/plantilla` resuelve
bien, pero en una URL con más profundidad como `/carta/mark-evans` resolvería
a `/carta/assets/img/…` y **no cargaría ni una sola imagen**. Hay que pasarlas
a absolutas (`/assets/…`) ANTES de crear las rutas de carta, no después.

Y el schema de ficha va **`Game` o `CreativeWork`, NUNCA `Product`**: las
cartas no se venden por dinero real, y declarar `offers` que no existen son
datos estructurados engañosos — eso sí se sanciona.

**El resto de la lista solo evita perder lo que ya llega. Esta es la única que
suma.**

## 4. ⚠️ HALLAZGO COLATERAL: producción NO tiene el `.htaccess` del repo

Salió auditando el SEO y no es un asunto de SEO: **producción hace 301 de
`.php` a URLs sin extensión** (`/plantilla.php` → `/plantilla`), y esa regla
**no está en el `.htaccess` del repositorio**. Alguien la puso a mano en el
servidor en algún momento.

**Si algún día se sube el `.htaccess` del repo encima, esas URLs se rompen.**
Antes de desplegar ese fichero hay que bajarse el de producción y fundirlos.

Y tiene una consecuencia que sí es de SEO: **todos los enlaces internos apuntan
a `.php`** (`href="plantilla.php"` en `navbar.php` y en todas las pantallas),
así que en producción **cada navegación se come un 301**. Desperdicia crawl
budget y añade un viaje de ida y vuelta a cada clic.

## 5. Lo que YA está bien — no lo toques creyendo que lo mejoras

El rendimiento, que sí pesa en posicionamiento, está resuelto desde antes:
compresión (gzip + brotli), caché de un año con `?v=filemtime` en la URL, WebP,
fuentes autoalojadas y subseteadas, iconos recortados de 679 KB a 17,9 KB.
Además `lang="es"`, un solo `<h1>` por página, jerarquía de encabezados
correcta, `viewport` bien puesto y `alt` descriptivos en las cartas.

## 6. Una decisión que solo puede tomar Alejandro

El título y la descripción no nombran **Inazuma Eleven: Victory Road**, que es
lo que mucha gente escribiría al buscar. Mencionarlo ayudaría a que te
encuentren; no mencionarlo mantiene el proyecto a más distancia de la marca
original. **Es una decisión suya, no técnica**, y por eso está aquí y no en la
lista de tareas.
