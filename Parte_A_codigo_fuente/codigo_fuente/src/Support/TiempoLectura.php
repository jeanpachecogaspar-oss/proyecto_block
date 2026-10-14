<?php
declare(strict_types=1);

namespace Quipu\Support;

/**
 * Función extra propia N.º 1: tiempo de lectura estimado.
 *
 * Se cuentan las palabras reales (secuencias de letras o dígitos) y se divide
 * entre 200 palabras por minuto, que es una velocidad media de lectura en
 * pantalla para texto en español. Siempre se informa al menos 1 minuto.
 */
final class TiempoLectura
{
    public const PALABRAS_POR_MINUTO = 200;

    public static function palabras(string $texto): int
    {
        return (int) preg_match_all('/[\p{L}\p{N}]+/u', $texto);
    }

    public static function minutos(string $texto, int $palabrasPorMinuto = self::PALABRAS_POR_MINUTO): int
    {
        $ppm = max(1, $palabrasPorMinuto);
        return max(1, (int) ceil(self::palabras($texto) / $ppm));
    }

    public static function etiqueta(int $minutos): string
    {
        return $minutos <= 1 ? '1 min de lectura' : $minutos . ' min de lectura';
    }
}
