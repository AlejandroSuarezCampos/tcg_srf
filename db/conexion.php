<?php

// Carga la clase Tcg desde consultas.php
require_once __DIR__ . '/consultas.php';

/*
|--------------------------------------------------------------------------
| CONEXIÓN A LA BASE DE DATOS
|--------------------------------------------------------------------------
|
| Los datos de PRODUCCIÓN (IONOS) están más abajo. En una máquina de
| desarrollo no sirven —el host de IONOS no se resuelve desde fuera de su
| hosting— así que este archivo mira primero si existe `conexion.local.php`
| y, si lo encuentra, usa ese y no sigue leyendo.
|
| Así el MISMO archivo vale para las dos máquinas y no hay que acordarse de
| cambiarlo antes de cada subida, que es como se acaba subiendo el localhost
| a producción o al revés.
|
| En producción `conexion.local.php` sencillamente no existe y todo sigue
| como estaba.
|
*/
if (is_file(__DIR__ . '/conexion.local.php')) {
    require __DIR__ . '/conexion.local.php';
    return;
}

/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN BASE DE DATOS IONOS
|--------------------------------------------------------------------------
|
| ⚠️ ESTA CONTRASEÑA ESTÁ ESCRITA EN UN ARCHIVO DEL PROYECTO.
|    Quien tenga acceso al código tiene acceso a la base de datos de
|    producción: una copia del repositorio, un backup, o el propio archivo si
|    algún día el servidor deja de interpretar PHP, la enseñan entera.
|    Lo correcto es sacarla a una variable de entorno del hosting y leerla con
|    `getenv()`. Mientras siga aquí, conviene al menos que este archivo NO
|    viaje a ningún sitio público.
|
| Rellena cada campo con los datos que aparecen en IONOS.
|
*/

// 1. SERVIDOR / HOST DE LA BASE DE DATOS
// Ejemplo: db5021242091.hosting-data.io
$host = "db5021242091.hosting-data.io";

// 2. PUERTO MYSQL
// Normalmente es 3306
$puerto = 3306;

// 3. NOMBRE DE LA BASE DE DATOS
// OJO: NO es necesariamente el nombre de usuario.
// Pon aquí el "Nombre de la base de datos" que aparece en IONOS.
$baseDatos = "dbs16038604";

// 4. USUARIO DE LA BASE DE DATOS
// Pon aquí el "Usuario" que aparece en IONOS.
$usuario = "dbu5176074";

// 5. CONTRASEÑA DE LA BASE DE DATOS
// Se lee de la variable de entorno TCG_DB_PASS del hosting. Configúrala en
// el panel de IONOS si todavía no existe.
$password = getenv('TCG_DB_PASS');

/*
|--------------------------------------------------------------------------
| CONEXIÓN
|--------------------------------------------------------------------------
*/

$db = new Tcg(
    $host,
    $puerto,
    $baseDatos,
    $usuario,
    $password
);
