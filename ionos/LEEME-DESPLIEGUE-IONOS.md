# Despliegue en IONOS — Superliga Frontier TCG

Esta carpeta es una copia completa del proyecto (`branding/CLAUDE.md` incluido,
léelo para el contexto funcional) con los ajustes necesarios para un hosting
compartido de IONOS (PHP + MySQL vía FastCGI/PHP-FPM, sin acceso a `php.ini`
ni, normalmente, a SSH). No se ha quitado ni reescrito ninguna funcionalidad:
solo configuración de entorno.

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
3. **`.htaccess`** — el `ErrorDocument 404` pasó de ruta absoluta
   (`/tcg_srf/404.php`) a relativa (`404.php`), para que funcione igual si
   subes el proyecto a la raíz del dominio o a una subcarpeta.
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
   con TODAS las migraciones ya aplicadas (hasta la `028`), así que no hace
   falta encadenar migraciones a mano. Desde phpMyAdmin del panel IONOS:
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
componentes, `assets/`, `branding/` (incluida la Biblia de diseño y este mismo
`CLAUDE.md`), `panel/`, `_legacy/` — se ha copiado tal cual. Esta carpeta es
una copia de despliegue, no una reescritura: los cambios están limitados a los
cinco ficheros listados arriba.
