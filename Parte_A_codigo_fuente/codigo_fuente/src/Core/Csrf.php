<?php
declare(strict_types=1);

namespace Quipu\Core;

/**
 * Protección CSRF con token sincronizado.
 *
 * Cada formulario POST incluye un campo oculto "_csrf". El servidor lo compara
 * (en tiempo constante) con el token guardado en la sesión del usuario.
 */
final class Csrf
{
    public const CAMPO = '_csrf';

    public static function token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /** Campo oculto listo para pegar dentro de un <form>. */
    public static function campo(): string
    {
        return '<input type="hidden" name="' . self::CAMPO . '" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function valido(?string $recibido): bool
    {
        $esperado = $_SESSION['_csrf'] ?? null;
        if (!is_string($esperado) || $esperado === '' || $recibido === null || $recibido === '') {
            return false;
        }
        return hash_equals($esperado, $recibido);
    }

    /** Debe llamarse al inicio de todo manejador POST. Corta con 419 si el token falla. */
    public static function exigir(): void
    {
        $recibido = $_POST[self::CAMPO] ?? null;
        if (!self::valido(is_string($recibido) ? $recibido : null)) {
            abortar(
                419,
                'La sesión del formulario caducó',
                'No pudimos verificar que la solicitud viniera de este sitio. Vuelve a la página anterior, recárgala e inténtalo de nuevo.'
            );
        }
    }

    /** Rota el token (se usa tras iniciar o cerrar sesión). */
    public static function renovar(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
}
