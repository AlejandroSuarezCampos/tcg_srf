# Despliegue del trabajo de SEO — tcgfrontier.es

Guía de UNA subida concreta: la de las sesiones del 26 de agosto de 2026, que
incluye los arreglos de funcionalidad, la auditoría SEO aplicada y las fichas de
carta. Para el despliegue general, ver `LEEME-DESPLIEGUE-IONOS.md`.

> **La carpeta `ionos/` ya no existe.** Se archivó en `_archivo/ionos/` porque
> estaba ~60 ficheros por detrás de la raíz. Ahora **se despliega desde la raíz
> del proyecto**, con la lista de exclusiones de más abajo. El `LEEME` viejo
> todavía habla de subir `ionos/`: esa parte está obsoleta.

---

## 0. Antes de tocar nada

**Copia de seguridad de las dos cosas**, porque hay cambios de ficheros Y de datos:

- **Base de datos**: phpMyAdmin → Exportar → toda la base, formato SQL.
- **Ficheros**: descarga al menos `.htaccess`, `partials/`, `components/`,
  `db/consultas.php` y las páginas de la raíz. Si el FTP permite bajar todo,
  mejor.

Sin esto no hay marcha atrás, y hay un cambio —el `.htaccess`— que puede tumbar
el sitio entero si el servidor no se lleva bien con alguna directiva.

---

## 1. Lo que NO se sube. Nunca.

| Qué | Por qué |
|---|---|
| **`db/conexion.local.php`** | ⚠️ **El más peligroso de la lista.** Lleva las credenciales de XAMPP y `db/conexion.php` le da prioridad: si sube, producción intenta conectar a `127.0.0.1` con el usuario `root` y **el sitio entero deja de funcionar**. |
| `_archivo/` | 86 MB de copias viejas, con un repositorio git dentro. |
| `tcgfrontier.es-audit/` | 6,9 MB de informes de la auditoría. |
| `node_modules/`, `package*.json` | Solo sirven para Playwright en local. |
| `branding/`, `design-system/`, `referencias-ux/` | Documentación interna. El `.htaccess` ya los bloquearía, pero mejor que ni estén. |

En FileZilla se hace con **Filtros de archivo** (Ver → Filtros de archivos →
Editar reglas): añade esos nombres y no se suben ni por error.

---

## 2. Subir los ficheros, y en este orden

El orden importa por un motivo concreto: **`partials/head.php` ahora hace
`require_once partials/seo.php`**. Si head.php llega antes que seo.php, todas las
páginas dan error fatal durante esos segundos.

### 2a. Primero los ficheros NUEVOS (nadie los usa todavía)

```
partials/seo.php                    ← el que head.php necesitará
assets/css/ficha.css
assets/img/og-portada.png

carta.php
como-se-juega.php
preguntas-frecuentes.php
quienes-somos.php
legal.php
privacidad.php

robots.txt
llms.txt
favicon.ico

db/.htaccess
db/migraciones/052_escudo_no_es_universo.sql
db/herramientas/generar_og.php
db/herramientas/generar_sitemap.php
db/herramientas/generar_variantes.php
db/pruebas/probar_rutas.php
db/pruebas/probar_agrupado_por_cromo.php
db/pruebas/probar_fichas_carta.php
```

Y las **126 variantes de imagen** (2,0 MB): todos los ficheros
`assets/img/**/*-256w.webp` y `*-512w.webp`. Se generan del arte, no de la base
de datos, así que las de local valen tal cual en producción.

### 2b. Después los MODIFICADOS

```
partials/head.php        partials/footer.php
components/carta.php     db/consultas.php
landing.php              plantilla.php            acceso.php
mazos.php                mercado.php              duelos.php
cadena.php               navbar.php
assets/css/components.css   assets/css/plantilla.css   assets/css/iconos.css
assets/async/js/scriptsAsync.js
assets/ajax/monedas.php
```

### 2c. El `.htaccess`, EL ÚLTIMO

Va solo y va al final porque es el único que puede tumbar el sitio de golpe. En
cuanto lo subas, **abre el dominio inmediatamente**. Si ves un error 500,
sustitúyelo por el que descargaste en el paso 0 y avisa: se mira con calma.

---

## 3. La base de datos

Una sola migración, y es un arreglo de datos:

```sql
-- db/migraciones/052_escudo_no_es_universo.sql
UPDATE equipos
   SET escudo = NULL
 WHERE escudo IS NOT NULL AND escudo <> '' AND escudo NOT LIKE '%/%';
```

Pégalo en phpMyAdmin → SQL. Es **idempotente**: si lo lanzas dos veces, la
segunda no toca nada.

Arregla dos equipos (`RaptorXz` y `Nosfanáticos`) que tenían el universo
—`srf` / `ie`— guardado en la columna del escudo, y por eso salían con la imagen
rota en el panel.

---

## 4. Comprobar, en este orden

### 4a. Que el sitio sigue en pie

```bash
curl -sI https://tcgfrontier.es/ | head -1
```

### 4b. Que las redirecciones nuevas funcionan

```bash
curl -sI http://tcgfrontier.es/            # → 301 a https
curl -sI https://www.tcgfrontier.es/       # → 301 a sin-www
curl -sI https://tcgfrontier.es/perfil.php # → 301 a /perfil
```

### 4c. Que las páginas nuevas responden

```bash
for u in robots.txt llms.txt favicon.ico como-se-juega preguntas-frecuentes \
         quienes-somos legal privacidad carta/torch carta/tom-skipper; do
  echo -n "$u "; curl -s -o /dev/null -w "%{http_code}\n" "https://tcgfrontier.es/$u"
done
```

Todo debe dar `200`.

### 4d. Que el enlace ya no sale vacío en Discord

Pega `https://tcgfrontier.es/` en cualquier canal. Debe salir la imagen con las
tres cartas, el título y la descripción. Si sale sin imagen, comprueba que
`assets/img/og-portada.png` subió y responde 200.

### 4e. Que las cabeceras de seguridad llegan

```bash
curl -sI https://tcgfrontier.es/ | grep -iE "x-content-type|x-frame|referrer|permissions|content-security"
```

Deben salir cinco. Si no sale ninguna, el hosting no tiene `mod_headers` y hay
que decirlo.

---

## 5. Lo que NO se puede hacer en producción, y qué hacer al respecto

Los planes básicos de IONOS Hosting Web **no dan SSH ni PHP por línea de
comandos**, y `db/` está cerrado por web (`db/.htaccess`). Así que
`generar_sitemap.php` y `generar_og.php` **no se pueden ejecutar allí**.

Consecuencia concreta: el `sitemap.xml` y la imagen social que subes están
generados con los datos de la copia local. Hoy local tiene **470** cartas
públicas y producción **472**, así que el sitemap se dejaría **dos fichas**
fuera y la imagen diría «470 fichas».

Tres formas de resolverlo, de menos a más trabajo:

1. **Vivir con ello por ahora.** Dos fichas de 460 y un número que se queda
   corto en una imagen. Las fichas que faltan siguen respondiendo 200 y se
   alcanzan desde el catálogo; solo no están anunciadas en el sitemap.
2. **Sincronizar la base local con producción** (exportar de producción,
   importar en local), regenerar los tres ficheros y subirlos. Es lo que hay que
   hacer de todas formas cada vez que entren cartas nuevas.
3. **Una página en el panel que lance los tres generadores**, protegida por el
   mismo `dictador` que el resto del panel. Es la única que hace esto
   sostenible el día que se añadan cartas: se pulsa un botón y se regeneran
   sitemap, imagen social y variantes en el propio servidor. No está hecha.

---

## 6. Después de desplegar, y no antes

### HSTS

En el `.htaccess`, al final del bloque de cabeceras, hay una línea comentada:

```apache
# Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

**Descoméntala cuando la redirección a HTTPS lleve unos días funcionando sin
sorpresas**, no el mismo día. Es la única cabecera que no se puede deshacer: el
navegador se la guarda durante meses y a partir de ahí se niega a hablar con el
dominio por http, aunque el certificado caduque.

### Cookie de sesión solo por HTTPS

Ahora que HTTPS es obligatorio, añade en `.user.ini`:

```ini
session.cookie_secure = 1
```

Estaba pendiente desde el propio `LEEME-DESPLIEGUE-IONOS.md`, que lo dejaba sin
poner precisamente porque forzarlo sin HTTPS habría roto el login.

### Search Console

Es el paso que convierte todo esto en algo medible:

1. Da de alta la propiedad en <https://search.google.com/search-console>.
2. Envía `https://tcgfrontier.es/sitemap.xml`.
3. Pide la indexación de la portada y de `/plantilla` a mano, para acelerar.

Y de paso, gratis y en la misma hora: **Bing Webmaster Tools**, **PageSpeed
Insights / CrUX** y **GA4**. Sin ellos, media auditoría seguirá siendo estimación
en vez de dato.

### Volver a medir

```
Lighthouse sobre https://tcgfrontier.es/plantilla en móvil
```

Antes daba **46/100**, con LCP de 5,4 s y 6.310 ms de bloqueo. Con
`content-visibility` y el arreglo del LCP desplegados debería subir bastante.
Conviene confirmarlo con el número, no darlo por hecho.

---

## 7. Si algo sale mal

| Síntoma | Causa más probable | Arreglo |
|---|---|---|
| **500 en todo el sitio** | El `.htaccess` nuevo | Sube el de la copia de seguridad |
| **500 en todas las páginas, con .htaccess viejo** | `partials/seo.php` no subió y `head.php` sí | Sube `partials/seo.php` |
| **Bucle de redirecciones** | TLS terminado en un balanceador | La regla ya contempla `X-Forwarded-Proto`; si aun así pasa, comenta el bloque de HTTPS y avisa |
| **La web conecta a la base equivocada** | Subió `db/conexion.local.php` | **Bórralo del servidor ya** |
| **`/carta/loquesea` da 404 en todas** | El `.htaccess` subió pero `carta.php` no | Sube `carta.php` |
| **Las fichas se ven sin estilos** | Falta `assets/css/ficha.css` | Súbelo |
| **Los CSS llegan sin comprimir** | El hosting no trae `mod_deflate` | El `.user.ini` ya comprime el HTML; para CSS y JS hace falta el módulo |
