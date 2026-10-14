<?php
declare(strict_types=1);

namespace Quipu\Support;

/**
 * Reglas de validación del lado del servidor (la validación del navegador es solo ayuda).
 *
 * Cada método devuelve null si el dato es válido o el mensaje de error en español.
 * Los límites son constantes públicas: el Plan de Pruebas los usa como valores
 * límite (mínimo, mínimo-1, máximo, máximo+1) y las pruebas PHPUnit los reutilizan.
 */
final class Validador
{
    public const NOMBRE_MIN = 2;
    public const NOMBRE_MAX = 60;
    public const EMAIL_MAX = 120;
    public const PASSWORD_MIN = 10;
    public const PASSWORD_MAX_BYTES = 72;   // límite real de bcrypt
    public const TITULO_MIN = 10;
    public const TITULO_MAX = 150;
    public const RESUMEN_MIN = 20;
    public const RESUMEN_MAX = 300;
    public const CONTENIDO_MIN = 50;
    public const CONTENIDO_MAX = 20000;
    public const COMENTARIO_MIN = 2;
    public const COMENTARIO_MAX = 1000;
    public const BUSQUEDA_MIN = 2;
    public const BUSQUEDA_MAX = 60;
    public const CATEGORIA_MIN = 3;
    public const CATEGORIA_MAX = 50;
    public const ALT_MIN = 5;
    public const ALT_MAX = 160;

    /** Contraseñas demasiado comunes que se rechazan aunque cumplan el largo mínimo. */
    private const CLAVES_COMUNES = [
        '1234567890', 'contrasena1', 'contraseña1', 'password123', 'password12',
        'qwertyuiop', 'abcdefghij1', '0123456789', 'iloveyou123', 'admin12345',
    ];

    public static function nombre(string $valor): ?string
    {
        $largo = mb_strlen(trim($valor));
        if ($largo === 0) {
            return 'Escribe tu nombre.';
        }
        if ($largo < self::NOMBRE_MIN || $largo > self::NOMBRE_MAX) {
            return sprintf('El nombre debe tener entre %d y %d caracteres.', self::NOMBRE_MIN, self::NOMBRE_MAX);
        }
        // Letras (con tildes), espacios, apóstrofo, punto y guion.
        if (preg_match("/^[\\p{L}][\\p{L}\\p{M} .'\\-]*$/u", trim($valor)) !== 1) {
            return 'El nombre solo puede contener letras, espacios, punto, apóstrofo y guion.';
        }
        return null;
    }

    public static function email(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return 'Escribe tu correo electrónico.';
        }
        if (mb_strlen($valor) > self::EMAIL_MAX || filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
            return 'Escribe un correo válido, por ejemplo nombre@dominio.pe.';
        }
        return null;
    }

    public static function password(string $valor): ?string
    {
        if ($valor === '') {
            return 'Escribe una contraseña.';
        }
        if (mb_strlen($valor) < self::PASSWORD_MIN) {
            return sprintf('La contraseña debe tener al menos %d caracteres.', self::PASSWORD_MIN);
        }
        if (strlen($valor) > self::PASSWORD_MAX_BYTES) {
            return sprintf('La contraseña no puede superar %d bytes (unos %d caracteres).', self::PASSWORD_MAX_BYTES, self::PASSWORD_MAX_BYTES);
        }
        if (preg_match('/\p{L}/u', $valor) !== 1 || preg_match('/\d/', $valor) !== 1) {
            return 'La contraseña debe combinar al menos una letra y un número.';
        }
        if (in_array(mb_strtolower($valor), self::CLAVES_COMUNES, true)) {
            return 'Esa contraseña es demasiado común. Elige otra.';
        }
        return null;
    }

    public static function confirmacionPassword(string $password, string $confirmacion): ?string
    {
        return hash_equals($password, $confirmacion) ? null : 'Las contraseñas no coinciden.';
    }

    public static function titulo(string $valor): ?string
    {
        return self::rango(trim($valor), self::TITULO_MIN, self::TITULO_MAX, 'El título', 'Escribe un título.');
    }

    public static function resumen(string $valor): ?string
    {
        return self::rango(trim($valor), self::RESUMEN_MIN, self::RESUMEN_MAX, 'El resumen', 'Escribe un resumen.');
    }

    public static function contenido(string $valor): ?string
    {
        return self::rango(trim($valor), self::CONTENIDO_MIN, self::CONTENIDO_MAX, 'El contenido', 'Escribe el contenido de la noticia.');
    }

    public static function comentario(string $valor): ?string
    {
        return self::rango(trim($valor), self::COMENTARIO_MIN, self::COMENTARIO_MAX, 'El comentario', 'Escribe tu comentario.');
    }

    public static function nombreCategoria(string $valor): ?string
    {
        return self::rango(trim($valor), self::CATEGORIA_MIN, self::CATEGORIA_MAX, 'El nombre de la categoría', 'Escribe el nombre de la categoría.');
    }

    public static function textoAlternativo(string $valor): ?string
    {
        return self::rango(trim($valor), self::ALT_MIN, self::ALT_MAX, 'La descripción de la imagen', 'Describe la imagen para quienes no pueden verla.');
    }

    /** La búsqueda vacía no es un error: simplemente no se ejecuta. */
    public static function busqueda(string $valor): ?string
    {
        $largo = mb_strlen(trim($valor));
        if ($largo === 0) {
            return null;
        }
        if ($largo < self::BUSQUEDA_MIN) {
            return sprintf('Escribe al menos %d caracteres para buscar.', self::BUSQUEDA_MIN);
        }
        if ($largo > self::BUSQUEDA_MAX) {
            return sprintf('La búsqueda admite hasta %d caracteres.', self::BUSQUEDA_MAX);
        }
        return null;
    }

    private static function rango(string $valor, int $min, int $max, string $campo, string $mensajeVacio): ?string
    {
        $largo = mb_strlen($valor);
        if ($largo === 0) {
            return $mensajeVacio;
        }
        if ($largo < $min || $largo > $max) {
            return sprintf('%s debe tener entre %d y %d caracteres (ahora tiene %d).', $campo, $min, $max, $largo);
        }
        return null;
    }
}
