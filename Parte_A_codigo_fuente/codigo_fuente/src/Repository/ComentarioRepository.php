<?php
declare(strict_types=1);

namespace Quipu\Repository;

use PDO;

final class ComentarioRepository
{
    /** Segundos mínimos entre dos comentarios del mismo usuario (anti-spam). */
    public const ESPERA_SEGUNDOS = 20;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function crear(int $noticiaId, int $usuarioId, string $contenido): int
    {
        $this->pdo->prepare(
            "INSERT INTO comentarios (noticia_id, usuario_id, contenido, estado, creado_en)
             VALUES (:n, :u, :c, 'visible', :f)"
        )->execute([
            ':n' => $noticiaId,
            ':u' => $usuarioId,
            ':c' => trim($contenido),
            ':f' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string,mixed>> */
    public function visiblesDeNoticia(int $noticiaId): array
    {
        $st = $this->pdo->prepare(
            "SELECT c.id, c.contenido, c.creado_en, u.nombre AS autor_nombre
             FROM comentarios c INNER JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.noticia_id = :n AND c.estado = 'visible'
             ORDER BY c.creado_en ASC, c.id ASC"
        );
        $st->execute([':n' => $noticiaId]);
        return $st->fetchAll();
    }

    /** Segundos que faltan para que el usuario pueda volver a comentar (0 = ya puede). */
    public function segundosDeEspera(int $usuarioId, ?int $ahora = null): int
    {
        $st = $this->pdo->prepare('SELECT MAX(creado_en) FROM comentarios WHERE usuario_id = :u');
        $st->execute([':u' => $usuarioId]);
        $ultimo = $st->fetchColumn();
        if (!is_string($ultimo) || $ultimo === '') {
            return 0;
        }
        $transcurrido = ($ahora ?? time()) - (int) strtotime($ultimo);
        return max(0, self::ESPERA_SEGUNDOS - $transcurrido);
    }

    /** @return list<array<string,mixed>> */
    public function listarAdmin(int $limite, int $offset): array
    {
        $st = $this->pdo->prepare(
            'SELECT c.id, c.contenido, c.estado, c.creado_en, u.nombre AS autor_nombre,
                    n.titulo AS noticia_titulo, n.slug AS noticia_slug
             FROM comentarios c
             INNER JOIN usuarios u ON u.id = c.usuario_id
             INNER JOIN noticias n ON n.id = c.noticia_id
             ORDER BY c.creado_en DESC, c.id DESC LIMIT :limite OFFSET :offset'
        );
        $st->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $st->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    public function contarTodos(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM comentarios')->fetchColumn();
    }

    public function contarVisiblesDeNoticia(int $noticiaId): int
    {
        $st = $this->pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE noticia_id = :n AND estado = 'visible'");
        $st->execute([':n' => $noticiaId]);
        return (int) $st->fetchColumn();
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        if (!in_array($estado, ['visible', 'oculto'], true)) {
            return;
        }
        $this->pdo->prepare('UPDATE comentarios SET estado = :e WHERE id = :id')
            ->execute([':e' => $estado, ':id' => $id]);
    }

    public function eliminar(int $id): void
    {
        $this->pdo->prepare('DELETE FROM comentarios WHERE id = :id')->execute([':id' => $id]);
    }
}
