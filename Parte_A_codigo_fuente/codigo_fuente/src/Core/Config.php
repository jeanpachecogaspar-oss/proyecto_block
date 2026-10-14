<?php
declare(strict_types=1);

namespace Quipu\Core;

/**
 * Configuración de la aplicación.
 *
 * Orden de prioridad: variables de entorno reales > archivo .env > valor por defecto.
 * Así las claves de la base de datos nunca quedan escritas en el código fuente.
 */
final class Config
{
    /** @var array<string,string> */
    private static array $valores = [];
    private static bool $cargada = false;

    public static function cargar(string $raiz): void
    {
        self::$valores = [];
        $archivo = rtrim($raiz, '/\\') . '/.env';
        if (is_file($archivo) && is_readable($archivo)) {
            $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lineas as $linea) {
                $linea = trim($linea);
                if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
                    continue;
                }
                [$clave, $valor] = explode('=', $linea, 2);
                $valor = trim($valor);
                // Quita comillas simples o dobles que envuelven el valor completo.
                if (strlen($valor) >= 2 && ($valor[0] === '"' || $valor[0] === "'") && $valor[0] === substr($valor, -1)) {
                    $valor = substr($valor, 1, -1);
                }
                self::$valores[trim($clave)] = $valor;
            }
        }
        self::$cargada = true;
    }

    public static function get(string $clave, ?string $porDefecto = null): ?string
    {
        $entorno = getenv($clave);
        if ($entorno !== false && $entorno !== '') {
            return $entorno;
        }
        return self::$valores[$clave] ?? $porDefecto;
    }

    public static function entero(string $clave, int $porDefecto): int
    {
        $valor = self::get($clave);
        return ($valor !== null && ctype_digit($valor)) ? (int) $valor : $porDefecto;
    }

    public static function esProduccion(): bool
    {
        return self::get('APP_ENV', 'dev') === 'prod';
    }

    public static function fijar(string $clave, string $valor): void
    {
        self::$valores[$clave] = $valor;
    }

    public static function estaCargada(): bool
    {
        return self::$cargada;
    }
}
