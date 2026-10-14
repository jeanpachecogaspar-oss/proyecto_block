<?php
declare(strict_types=1);

namespace Quipu\Repository;

use PDO;
use Quipu\Support\Slug;

final class CategoriaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function todas(): array
    {
        return $this->pdo->query('SELECT id, nombre, slug, descripcion FROM categorias ORDER BY nombre')->fetchAll();
    }

    /**
     * Categorías con la cantidad de noticias PUBLICADAS (una sola consulta con
     * LEFT JOIN y GROUP BY, sin consultas repetidas por categoría).
     *
     * @return list<array<string,mixed>>
     */
    public function conConteo(): array
    {
        $sql = "SELECT c.id, c.nombre, c.slug, c.descripcion, COUNT(n.id) AS total
                FROM categorias c
                LEFT JOIN noticias n ON n.categoria_id = c.id AND n.estado = 'publicada'
                GROUP BY c.id, c.nombre, c.slug, c.descripcion
                ORDER BY c.nombre";
        return $this->pdo->query($sql)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function porId(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT id, nombre, slug, descripcion FROM categorias WHERE id = :id');
        $st->execute([':id' => $id]);
        $f = $st->fetch();
        return $f === false ? null : $f;
    }

    /** @return array<string,mixed>|null */
    public function porSlug(string $slug): ?array
    {
        $st = $this->pdo->prepare('SELECT id, nombre, slug, descripcion FROM categorias WHERE slug = :s');
        $st->execute([':s' => $slug]);
        $f = $st->fetch();
        return $f === false ? null : $f;
    }

    public function existeNombre(string $nombre, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT 1 FROM categorias WHERE (LOWER(nombre) = LOWER(:n) OR slug = :s)';
        $params = [':n' => trim($nombre), ':s' => Slug::de($nombre, 'categoria')];
        if ($ignorarId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $ignorarId;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn() !== false;
    }

    public function crear(string $nombre, string $descripcion): int
    {
        $this->pdo->prepare('INSERT INTO categorias (nombre, slug, descripcion) VALUES (:n, :s, :d)')
            ->execute([':n' => trim($nombre), ':s' => Slug::de($nombre, 'categoria'), ':d' => trim($descripcion)]);
        return (int) $this->pdo->lastInsertId();
    }

    public function actualizar(int $id, string $nombre, string $descripcion): void
    {
        $this->pdo->prepare('UPDATE categorias SET nombre = :n, slug = :s, descripcion = :d WHERE id = :id')
            ->execute([':n' => trim($nombre), ':s' => Slug::de($nombre, 'categoria'), ':d' => trim($descripcion), ':id' => $id]);
    }

    public function contarNoticias(int $id): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM noticias WHERE categoria_id = :id');
        $st->execute([':id' => $id]);
        return (int) $st->fetchColumn();
    }

    public function eliminar(int $id): void
    {
        $this->pdo->prepare('DELETE FROM categorias WHERE id = :id')->execute([':id' => $id]);
    }
}
