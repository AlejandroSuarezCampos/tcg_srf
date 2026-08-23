# Despliegue en IONOS — Superliga Frontier TCG

Esta carpeta es una copia completa del proyecto con los ajustes necesarios para
un hosting compartido de IONOS (PHP + MySQL vía FastCGI/PHP-FPM, sin acceso a
`php.ini` ni, normalmente, a SSH). No se ha quitado ni reescrito ninguna
funcionalidad: solo configuración de entorno.

> ## ⚠️ REFRESCADA EL 2026-08-23 — Y CÓMO REFRESCARLA LA PRÓXIMA VEZ
>
> Esta carpeta se había quedado congelada en el **2026-08-21** mientras la raíz
> seguía avanzando: le faltaban ~3.000 líneas de `db/consultas.php` y **todo lo
> de las migraciones `029` a `044`** (equipos, tutorial, universo en la carta,
> rareza Numerada, sobre inicial, apuesta con varias cartas, dificultad por
> nodo, calibración PvE…). O sea que **la web estaba sirviendo código de la
> v7.7 contra una base de datos que ya tenía el esquema nuevo.** Ya está
> sincronizada.
>
> **Hoy `ionos/` es idéntica a la raíz salvo UNA línea** (el `charset=utf8mb4`
> del DSN, punto 2 de abajo). Todo lo demás coincide byte a byte, así que
> refrescarla es una copia con cuatro exclusiones:
>
> ```
> robocopy C:\xampp\htdocs\tcg_srf C:\xampp\htdocs\tcg_srf\ionos /E ^
>   /XD C:\xampp\htdocs\tcg_srf\ionos C:\xampp\htdocs\tcg_srf\branding C:\xampp\htdocs\tcg_srf\.claude ^
>   /XF conexion.php conexion.local.php
> ```
>
> Y **después vuelve a poner a mano el `charset=utf8mb4`** en
> `ionos/db/consultas.php` (el propio fichero lleva un aviso en esa línea).
>
> ⚠️ **`db/conexion.local.php` NO puede viajar NUNCA.** Es la configuración de
> XAMPP (localhost/root/sin contraseña) y `db/conexion.php` la prefiere si
> existe: subirla dejaría la web intentando conectar a la base local del
> servidor y el sitio entero caído. Por eso está en la lista de exclusión.
>
> **Qué NO se copia y por qué:** `branding/` (documentación interna, no hace
> falta servirla), `.claude/`, `db/conexion.php` (lleva credenciales; el
> servidor ya tiene la suya y no debe pisarse) y `db/conexion.local.php`.

## Qué se ha adaptado respecto a la copia local (XAMPP)

1. **`db/conexion.php`** — ya no tiene `localhost` / `root` / contraseña vacía
   a fuego. Lee `TCG_DB_HOST`/`TCG_DB_PORT`/`TCG_DB_NAME`/`TCG_DB_USER`/
   `TCG_DB_PASS` de variables de entorno si tu plan de IONOS las soporta
   (Hosting Web IONOS → tu paquete → "PHP" → variables de entorno); si no,
   edita directamente los 5 valores por defecto del propio fichero con los
   datos que te da IONOS al crear la base de datos MySQL (panel → Bases de
   datos → MySQL). **El host casi nunca es `localhost` en IONOS** — suele ser
   algo como `dbXXXXXXXXX.hosting-data.io`.

   ⚠️ **Este fichero, con la contraseña real ya rellenada, NUNCA se sube a
   git** (está en `.gitignore`). En el repositorio solo se versiona
   `db/conexion.php.example`, con placeholders. Cópialo a `conexion.php` (sin
   `.example`) y rellénalo — es el paso que ya hiciste al desplegar, esto es
   solo para que quede documentado por qué el fichero real no aparece en git.
2. **`db/consultas.php`** — la conexión PDO ahora fija `charset=utf8mb4`
   explícitamente en el DSN. Sin esto, la codificación de la conexión depende
   del valor por defecto del servidor MySQL de IONOS, que no está garantizado
   que sea utf8mb4 — este proyecto ya sufrió corrupción de codificación por
   este motivo exacto en local (§5.3 de `branding/CLAUDE.md`), así que se fija
   en origen para no repetirlo en el hosting.
3. ~~**`.htaccess`** — `ErrorDocument 404` con ruta relativa.~~
   **DESFASADO, y estaba mal.** Apache **no admite ruta relativa** en
   `ErrorDocument`: sin la barra inicial la toma como texto literal y se la
   manda tal cual al navegador. El `.htaccess` de la raíz ya trae la versión
   correcta (`ErrorDocument 404 /404.php`) más el arreglo del *"Forbidden"* que
   daba la portada de **tcgfrontier.es** (`DirectoryIndex landing.php …` y la
   `RewriteRule ^$ landing.php`). Desde el 2026-08-23 **el `.htaccess` de esta
   carpeta es exactamente el de la raíz**: ya no hay nada que adaptar aquí.
4. **`.user.ini` (nuevo)** — IONOS sirve PHP vía PHP-FPM, no como módulo de
   Apache, así que las directivas `php_flag`/`php_value` del `.htaccess` NO
   surten efecto ahí (se han dejado igualmente, comentadas explicando por
   qué, por si tu plan usara mod_php). `.user.ini` es lo que PHP-FPM sí lee
   sin necesitar acceso a `php.ini`: sube `upload_max_filesize`/`post_max_size`
   (para la subida de imágenes de cromos/escudos, §7.7 de `branding/CLAUDE.md`)
   y `max_execution_time` (para el importador de datos oficiales, que puede
   tardar varios minutos descargando fotos). PHP-FPM lo relee solo, sin
   reiniciar nada, aunque puede tardar hasta 5 minutos en recogerlo
   (`user_ini.cache_ttl`).

## Pasos para subir el proyecto

1. **Crear la base de datos MySQL en IONOS** (panel → Bases de datos → MySQL).
   Anota host, nombre de BD, usuario y contraseña.
2. **Importar `db/tcg.sql`** en esa base — es un volcado completo y actualizado
   con TODAS las migraciones ya aplicadas (**hasta la `045`**, revolcado el
   2026-08-23), así que no hace falta encadenar migraciones a mano. Desde
   phpMyAdmin del panel IONOS:
   Importar → selecciona `db/tcg.sql` → charset `utf8mb4`. Si el fichero pesa
   más de lo que permite la subida de phpMyAdmin, comprímelo a `.sql.gz`
   primero (phpMyAdmin de IONOS acepta `.sql.gz`).
3. **Editar `db/conexion.php`** con las credenciales del paso 1 (o configurar
   las variables de entorno, si tu plan las soporta).
4. **Subir todos los ficheros de esta carpeta** (`ionos/`) por FTP/SFTP a la
   raíz de tu hosting (o a la subcarpeta que corresponda), **el contenido de
   `ionos/`, no la carpeta `ionos/` en sí**.
5. **Comprobar permisos de escritura** en las carpetas donde el código escribe
   archivos — IONOS suele dar 755 por defecto, que ya es suficiente para que
   PHP cree subcarpetas nuevas dentro de ellas:
   - `assets/img/Cromos/` (subida directa de imágenes de cromos, por
     expansión — la carpeta de cada expansión se crea sola)
   - `assets/img/Escudos/` (escudos de rivales de cadena)
   - `assets/img/plantillas/` (plantillas de cajas/sobres 3D del panel)
6. **PHP 8.x** en el panel de IONOS (Hosting → tu paquete → PHP → versión).
   El proyecto usa PHP 8 sin framework y necesita la extensión **GD** activa
   (para las imágenes y el generador de guías de plantilla) — en IONOS suele
   venir activada por defecto; compruébalo en el panel si algo de imágenes
   falla.
7. **Verificar que la web responde**: abre `styleguide.php` en tu dominio. Si
   ves el sistema de diseño completo, la base de datos conecta y las rutas de
   assets son correctas.
8. **Sesión "Claude" de pruebas** (usuario `Claude`/`123456`, ver
   `branding/CLAUDE.md`) sigue viva en el volcado — bórrala o cámbiale la
   contraseña antes de dar el sitio por público de verdad, según decidas con
   la limpieza previa a producción que ya describe el propio documento
   ("Limpieza previa a producción — el orden importa", en `branding/CLAUDE.md`).

## Actualizar un despliegue QUE YA ESTÁ EN MARCHA

Los pasos de arriba son para montar el sitio desde cero. Si la web ya está
funcionando —que es el caso de tcgfrontier.es— **no importes `db/tcg.sql`**:
te llevarías por delante los usuarios, las colecciones y el progreso reales.
Lo que hay que hacer es:

1. **Aplicar solo las migraciones que falten**, por orden, desde phpMyAdmin
   (pestaña SQL → pegar el contenido del `.sql` → Continuar). Todas son
   **aditivas e idempotentes**: volver a lanzar una ya aplicada no rompe nada
   ni duplica datos, así que ante la duda, lánzala.

   Al 2026-08-23 la base de producción tenía hasta la `044`, así que lo único
   pendiente es:

   - `db/migraciones/045_nodo_bloqueo.sql` — el nodo de bloqueo del mapa de
     cadenas (§ v7.9 de `branding/CLAUDE.md`). Añade el tipo `bloqueo`, la
     tabla `cadena_nodo_requisitos` y dos columnas a `cadena_progreso`.

   Para saber qué falta sin adivinar, esta consulta lo dice de un vistazo:
   ```sql
   SELECT
     (SELECT COUNT(*) FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadena_nodo_requisitos') AS tiene_045,
     (SELECT COUNT(*) FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadena_progreso'
         AND COLUMN_NAME = 'mas_goles') AS tiene_goles;
   ```
   Los dos a `1` = la `045` está puesta.

2. **Subir los ficheros por FTP**, el contenido de `ionos/` como siempre.
   **No subas `db/conexion.php`** (no está en esta carpeta justamente por eso):
   el servidor ya tiene la suya con las credenciales buenas y pisarla dejaría
   el sitio sin base de datos.

3. **Orden recomendado: primero la migración, después los ficheros.** Las
   migraciones son aditivas, así que el código viejo sigue funcionando con el
   esquema nuevo; al revés no — el código nuevo contra el esquema viejo
   revienta con errores de columna inexistente. Es exactamente lo que pasó en
   local y costó una sesión entera de diagnóstico (v7.8 de
   `branding/CLAUDE.md`).

## Lo que NO va a funcionar igual en un hosting compartido típico de IONOS

- **`db/pruebas/*.php` y los scripts de `db/migraciones/*.php`** son de
  ejecución por **línea de comandos** (`PHP_SAPI === 'cli'`, exigido desde la
  v7.7 de `branding/CLAUDE.md`) y montan/borran una base `tcg_prueba` propia.
  Sin acceso SSH con PHP CLI (los planes básicos de IONOS Hosting Web no lo
  dan; sí algunos planes Cloud/VPS), no se pueden lanzar desde este hosting.
  No pasa nada si no los lanzas nunca en producción: son herramientas de
  desarrollo, no parte del sitio servido.
- **No hay cron en este proyecto** (por diseño, ver §8 de `branding/CLAUDE.md`
  — todo lo que "necesita que pase el tiempo" se evalúa de forma perezosa en
  cada carga o sondeo), así que no hace falta configurar ninguna tarea
  programada en IONOS para que el partido, los abandonos o las cadenas
  funcionen.
- **HTTPS**: IONOS da certificado SSL gratuito por dominio. Una vez esté
  activo, añade en `.user.ini`:
  ```
  session.cookie_secure = 1
  ```
  para que la cookie de sesión solo viaje por HTTPS. No se ha añadido por
  defecto en esta copia porque forzarlo antes de tener el dominio con SSL
  activo dejaría el login roto.

## Qué NO se ha tocado

Todo el resto del proyecto — lógica de `db/consultas.php`, las ~90 páginas y
componentes, `assets/`, `panel/`, `_legacy/` — se ha copiado tal cual. Esta
carpeta es una copia de despliegue, no una reescritura.

Tras la sincronización del 2026-08-23, **la única diferencia con la raíz es el
`charset=utf8mb4` del DSN** (punto 2). Se puede comprobar en cualquier momento,
y debería devolver solo esa línea:

```
diff -r --brief C:\xampp\htdocs\tcg_srf\db C:\xampp\htdocs\tcg_srf\ionos\db
```

⚠️ **`branding/` NO se despliega** (nunca ha estado en esta carpeta, aunque una
versión anterior de este documento decía que sí). Es documentación interna: la
Biblia de diseño, el briefing de marca y `CLAUDE.md`. No hace falta servirla, y
`.htaccess` la bloquearía igualmente con `Options -Indexes`.
