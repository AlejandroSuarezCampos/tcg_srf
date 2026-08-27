<?php
session_start();
$_SESSION = [];
session_destroy();
header('Location: acceso.php?modo=entrar');
exit;
