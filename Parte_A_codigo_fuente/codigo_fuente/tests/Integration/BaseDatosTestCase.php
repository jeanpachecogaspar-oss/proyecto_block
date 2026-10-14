<?php
declare(strict_types=1);

namespace Quipu\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base de las pruebas de integración: SQLite en memoria con el esquema real
 * del proyecto (database/esquema_sqlite.sql). Cada prueba parte de una base limpia.
 */
abstract class BaseDatosTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/esquema_sqlite.sql'));
    }

    protected function crearUsuario(string $nombre = 'Ana Torres', string $email = 'ana@ejemplo.pe', string $rol = 'autor'): int
    {
        return (new \Quipu\Repository\UsuarioRepository($this->pdo))
            ->crear($nombre, $email, password_hash('Clave-segura-1', PASSWORD_DEFAULT), $rol);
    }

    protected function crearCategoria(string $nombre = 'Cultura'): int
    {
        return (new \Quipu\Repository\CategoriaRepository($this->pdo))->crear($nombre, 'Descripción de ' . $nombre);
    }

    /** @param array<string,mixed> $extra */
    protected function crearNoticia(int $autor, int $categoria, string $titulo, string $estado = 'publicada', array $extra = []): int
    {
        return (new \Quipu\Repository\NoticiaRepository($this->pdo))->crear($extra + [
            'autor_id' => $autor,
            'categoria_id' => $categoria,
            'titulo' => $titulo,
            'resumen' => 'Resumen de la noticia ' . $titulo,
            'contenido' => 'Contenido de ejemplo suficientemente largo para la noticia ' . $titulo . ' en las pruebas.',
            'estado' => $estado,
        ]);
    }
}
