<?php
declare(strict_types=1);

namespace Quipu\Repository;

use PDO;

/**
 * Acceso a datos de usuarios. Todas las consultas usan parámetros enlazados.
 */
final class UsuarioRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function crear(string $nombre, string $email, string $passwordHash, string $rol = 'lector'): int
    {
        $sql = 'INSERT INTO usuarios (nombre, email, password_hash, rol, activo, creado_en)
                VALUES (:nombre, :email, :hash, :rol, 1, :creado)';
        $this->pdo->prepare($sql)->execute([
            ':nombre' => trim($nombre),
            ':email' => self::normalizarEmail($email),
            ':hash' => $passwordHash,
            ':rol' => $rol,
            ':creado' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function porEmail(string $email): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM usuarios WHERE email = :email');
        $st->execute([':email' => self::normalizarEmail($email)]);
        $fila = $st->fetch();
        return $fila === false ? null : $fila;
    }

    /** @return array<string,mixed>|null */
    public function porId(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM usuarios WHERE id = :id');
        $st->execute([':id' => $id]);
        $fila = $st->fetch();
        return $fila === false ? null : $fila;
    }

    public function existeEmail(string $email): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM usuarios WHERE email = :email');
        $st->execute([':email' => self::normalizarEmail($email)]);
        return $st->fetchColumn() !== false;
    }

    /** @return list<array<string,mixed>> */
    public function listar(): array
    {
        return $this->pdo->query(
            'SELECT id, nombre, email, rol, activo, creado_en, ultimo_acceso FROM usuarios ORDER BY id'
        )->fetchAll();
    }

    public function cambiarRol(int $id, string $rol): void
    {
        $this->pdo->prepare('UPDATE usuarios SET rol = :rol WHERE id = :id')
            ->execute([':rol' => $rol, ':id' => $id]);
    }

    public function cambiarActivo(int $id, bool $activo): void
    {
        $this->pdo->prepare('UPDATE usuarios SET activo = :a WHERE id = :id')
            ->execute([':a' => $activo ? 1 : 0, ':id' => $id]);
    }

    public function registrarAcceso(int $id): void
    {
        $this->pdo->prepare('UPDATE usuarios SET ultimo_acceso = :f WHERE id = :id')
            ->execute([':f' => date('Y-m-d H:i:s'), ':id' => $id]);
    }

    public function actualizarHash(int $id, string $hash): void
    {
        $this->pdo->prepare('UPDATE usuarios SET password_hash = :h WHERE id = :id')
            ->execute([':h' => $hash, ':id' => $id]);
    }

    public function contarActivosPorRol(string $rol): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE rol = :r AND activo = 1');
        $st->execute([':r' => $rol]);
        return (int) $st->fetchColumn();
    }

    /** @return array<string,int> rol => cantidad */
    public function contarPorRol(): array
    {
        $filas = $this->pdo->query('SELECT rol, COUNT(*) AS n FROM usuarios GROUP BY rol')->fetchAll();
        $salida = ['admin' => 0, 'autor' => 0, 'lector' => 0];
        foreach ($filas as $f) {
            $salida[(string) $f['rol']] = (int) $f['n'];
        }
        return $salida;
    }

    public static function normalizarEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
