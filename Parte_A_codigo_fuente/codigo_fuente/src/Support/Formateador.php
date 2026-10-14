<?php
declare(strict_types=1);

namespace Quipu\Support;

use DateTimeImmutable;

/**
 * Presentación segura del contenido.
 *
 * Las noticias se guardan como texto plano con una sintaxis mínima:
 *   - Una línea en blanco separa párrafos.
 *   - "## Texto" crea un subtítulo (h2).
 *   - Líneas que empiezan con "- " forman una lista.
 *
 * El texto se ESCAPA antes de añadir cualquier etiqueta, de modo que el autor
 * nunca puede inyectar HTML ni JavaScript (anti-XSS por diseño).
 */
final class Formateador
{
    private const MESES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    public static function cuerpo(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", trim($texto));
        $bloques = preg_split("/\n{2,}/", $texto) ?: [];
        $html = [];

        foreach ($bloques as $bloque) {
            $bloque = trim($bloque);
            if ($bloque === '') {
                continue;
            }
            if (str_starts_with($bloque, '## ')) {
                $html[] = '<h2>' . self::esc(substr($bloque, 3)) . '</h2>';
                continue;
            }
            $lineas = explode("\n", $bloque);
            $todasSonItems = count(array_filter($lineas, static fn (string $l): bool => str_starts_with(trim($l), '- '))) === count($lineas);
            if ($todasSonItems) {
                $items = array_map(
                    static fn (string $l): string => '<li>' . self::esc(substr(trim($l), 2)) . '</li>',
                    $lineas
                );
                $html[] = '<ul>' . implode('', $items) . '</ul>';
                continue;
            }
            $html[] = '<p>' . implode('<br>', array_map(self::esc(...), $lineas)) . '</p>';
        }
        return implode("\n", $html);
    }

    /** "2026-10-13 18:30:00" → "13 de octubre de 2026". */
    public static function fecha(?string $fechaHora): string
    {
        if ($fechaHora === null || $fechaHora === '') {
            return '';
        }
        try {
            $f = new DateTimeImmutable($fechaHora);
        } catch (\Exception) {
            return '';
        }
        return sprintf('%d de %s de %d', (int) $f->format('j'), self::MESES[(int) $f->format('n')], (int) $f->format('Y'));
    }

    /** Valor para el atributo datetime de <time>. */
    public static function fechaIso(?string $fechaHora): string
    {
        if ($fechaHora === null || $fechaHora === '') {
            return '';
        }
        try {
            return (new DateTimeImmutable($fechaHora))->format('Y-m-d\TH:i');
        } catch (\Exception) {
            return '';
        }
    }

    private static function esc(string $t): string
    {
        return htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
