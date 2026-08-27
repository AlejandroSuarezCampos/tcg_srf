<?php
/**
 * PROTECCIÓN CSRF — helper compartido (auditoría de seguridad).
 *
 * Un único token por sesión, guardado en $_SESSION['csrf']. Se expone:
 *   · csrfToken()  -> el valor, para meterlo en un <meta> o generarlo si no existe.
 *   · csrfCampo()  -> el <input type="hidden"> listo para formularios normales.
 *   · csrfValido() -> compara con hash_equals() (a tiempo constante).
 *
 * Los endpoints AJAX (fetch/sendBeacon) no mandan formulario: leen el token
 * del <meta name="csrf-token"> que pinta partials/head.php y lo añaden ellos
 * mismos al payload — ver assets/js/*.js.
 */

if (!function_exists('csrfToken')) {
    function csrfToken(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    function csrfCampo(): string {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrfToken()) . '">';
    }

    function csrfValido(?string $tokenRecibido): bool {
        return $tokenRecibido !== null
            && $tokenRecibido !== ''
            && !empty($_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $tokenRecibido);
    }
}
