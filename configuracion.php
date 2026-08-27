<?php
/**
 * Fusionada en `perfil.php`, pestaña «Ajustes» (bloque 7a del rediseño).
 *
 * Eran dos pantallas que decían las dos «tu cuenta», y las dos tenían el mismo
 * bloque «Canjear un código» repetido palabra por palabra. Se conserva como
 * redirección porque hay enlaces y marcadores que apuntan aquí.
 */
header('Location: perfil.php#panel-ajustes', true, 301);
exit;
