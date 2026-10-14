<?php
declare(strict_types=1);

namespace Quipu\Core;

/**
 * Inicio de sesión endurecido.
 *
 * - Cookie HttpOnly (JavaScript no puede leerla) y SameSite=Lax.
 * - Cookie "Secure" automática cuando el sitio se sirve por HTTPS.
 * - use_strict_mode: PHP rechaza identificadores de sesión que no creó.
 * - Caducidad por inactividad.
 */
final class Session
{
    public const INACTIVIDAD_MAXIMA = 7200; // 2 horas

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_name('quipu_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => self::esHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $ahora = time();
        if (isset($_SESSION['_ultima_actividad']) && ($ahora - (int) $_SESSION['_ultima_actividad']) > self::INACTIVIDAD_MAXIMA) {
            self::destruir();
            session_start();
        }
        $_SESSION['_ultima_actividad'] = $ahora;
    }

    /** Cambia el identificador de sesión (evita la fijación de sesión al iniciar sesión). */
    public static function regenerar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destruir(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires' => time() - 3600,
                    'path' => $p['path'],
                    'domain' => $p['domain'],
                    'secure' => $p['secure'],
                    'httponly' => $p['httponly'],
                    'samesite' => 'Lax',
                ]);
            }
            session_destroy();
        }
    }

    public static function esHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    }
}
