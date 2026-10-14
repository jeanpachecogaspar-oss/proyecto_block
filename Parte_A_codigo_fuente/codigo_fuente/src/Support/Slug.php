<?php
declare(strict_types=1);

namespace Quipu\Support;

/**
 * Convierte un título en un identificador legible para la URL
 * ("Feria del tejido en Ankarí" → "feria-del-tejido-en-ankari").
 * No depende de la extensión intl.
 */
final class Slug
{
    private const LARGO_MAX = 80;

    private const TRANSLITERACION = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ];

    public static function de(string $texto, string $porDefecto = 'noticia'): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, self::TRANSLITERACION);
        $texto = (string) preg_replace('/[^a-z0-9]+/', '-', $texto);
        $texto = trim($texto, '-');
        if (strlen($texto) > self::LARGO_MAX) {
            $texto = rtrim(substr($texto, 0, self::LARGO_MAX), '-');
        }
        return $texto !== '' ? $texto : $porDefecto;
    }
}
