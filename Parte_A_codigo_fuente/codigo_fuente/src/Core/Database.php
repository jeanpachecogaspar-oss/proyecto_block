<?php
declare(strict_types=1);

namespace Quipu\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Conexión PDO única por petición.
 *
 * - Modo de errores por excepciones.
 * - Sentencias preparadas NATIVAS (EMULATE_PREPARES = false): el motor separa
 *   siempre código SQL y datos, lo que bloquea la inyección SQL.
 * - Driver "mysql" (producción) o "sqlite" (desarrollo rápido y pruebas).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function conexion(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::crear();
        }
        return self::$pdo;
    }

    /** Permite inyectar una conexión (por ejemplo, SQLite en memoria para las pruebas). */
    public static function usar(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    private static function crear(): PDO
    {
        $driver = Config::get('DB_DRIVER', 'mysql');

        try {
            if ($driver === 'sqlite') {
                $ruta = (string) Config::get('DB_SQLITE_PATH', 'database/quipu_dev.sqlite');
                if ($ruta !== ':memory:' && !str_starts_with($ruta, '/')) {
                    $ruta = QUIPU_ROOT . '/' . $ruta;
                }
                $pdo = new PDO('sqlite:' . $ruta);
                $pdo->exec('PRAGMA foreign_keys = ON');
            } elseif ($driver === 'mysql') {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    Config::get('DB_HOST', '127.0.0.1'),
                    Config::entero('DB_PORT', 3306),
                    Config::get('DB_NAME', 'quipu_noticias')
                );
                $pdo = new PDO($dsn, (string) Config::get('DB_USER', 'root'), (string) Config::get('DB_PASS', ''));
            } else {
                throw new RuntimeException('DB_DRIVER no soportado: ' . $driver);
            }
        } catch (PDOException $e) {
            // Nunca se propaga el mensaje original: podría incluir usuario o servidor.
            error_log('[Quipu] Error de conexión a la base de datos: ' . $e->getMessage());
            throw new RuntimeException('No se pudo conectar con la base de datos.');
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        return $pdo;
    }
}
