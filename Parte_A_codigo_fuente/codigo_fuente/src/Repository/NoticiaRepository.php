<?php
declare(strict_types=1);

namespace Quipu\Repository;

use PDO;
use PDOStatement;
use Quipu\Support\Slug;
use Quipu\Support\TiempoLectura;

/**
 * Acceso a datos de noticias.
 *
 * Criterios de eficiencia aplicados:
 *  - Los listados NO traen la columna "contenido" (puede pesar miles de caracteres).
 *  - Cada listado pagina con LIMIT/OFFSET y se apoya en índices compuestos
 *    (estado, publicado_en) y (categoria_id, estado, publicado_en).
 *  - Autor y categoría se obtienen con JOIN en la misma consulta (sin consultas N+1).
 */
final class NoticiaRepository
{
    private const COLUMNAS_LISTADO = 'n.id, n.autor_id, n.categoria_id, n.titulo, n.slug, n.resumen,
        n.imagen, n.imagen_alt, n.imagen_ancho, n.imagen_alto, n.estado, n.minutos_lectura, n.vistas,
        n.publicado_en, n.creado_en, c.nombre AS categoria_nombre, c.slug AS categoria_slug,
        u.nombre AS autor_nombre';

    private const UNIONES = 'FROM noticias n
        INNER JOIN categorias c ON c.id = n.categoria_id
        INNER JOIN usuarios u ON u.id = n.autor_id';

    private const MAX_PALABRAS_BUSQUEDA = 5;

    public function __construct(private readonly PDO $pdo)
    {
    }

    // ------------------------------------------------------------------ lectura pública

    /** @return list<array<string,mixed>> */
    public function listarPublicadas(int $limite, int $offset, ?int $categoriaId = null): array
    {
        $sql = 'SELECT ' . self::COLUMNAS_LISTADO . ' ' . self::UNIONES . " WHERE n.estado = 'publicada'";
        $params = [];
        if ($categoriaId !== null) {
            $sql .= ' AND n.categoria_id = :cat';
            $params[':cat'] = $categoriaId;
        }
        $sql .= ' ORDER BY n.publicado_en DESC, n.id DESC LIMIT :limite OFFSET :offset';
        return $this->ejecutar($sql, $params, $limite, $offset)->fetchAll();
    }

    public function contarPublicadas(?int $categoriaId = null): int
    {
        $sql = "SELECT COUNT(*) FROM noticias n WHERE n.estado = 'publicada'";
        $params = [];
        if ($categoriaId !== null) {
            $sql .= ' AND n.categoria_id = :cat';
            $params[':cat'] = $categoriaId;
        }
        return (int) $this->ejecutar($sql, $params)->fetchColumn();
    }

    /** @return array<string,mixed>|null */
    public function porSlug(string $slug, bool $soloPublicadas = true): ?array
    {
        $sql = 'SELECT n.*, c.nombre AS categoria_nombre, c.slug AS categoria_slug, u.nombre AS autor_nombre
                ' . self::UNIONES . ' WHERE n.slug = :slug';
        if ($soloPublicadas) {
            $sql .= " AND n.estado = 'publicada'";
        }
        $fila = $this->ejecutar($sql, [':slug' => $slug])->fetch();
        return $fila === false ? null : $fila;
    }

    /** @return array<string,mixed>|null */
    public function porId(int $id): ?array
    {
        $sql = 'SELECT n.*, c.nombre AS categoria_nombre, c.slug AS categoria_slug, u.nombre AS autor_nombre
                ' . self::UNIONES . ' WHERE n.id = :id';
        $fila = $this->ejecutar($sql, [':id' => $id])->fetch();
        return $fila === false ? null : $fila;
    }

    /** @return list<array<string,mixed>> */
    public function masLeidas(int $limite = 5): array
    {
        $sql = "SELECT n.id, n.titulo, n.slug, n.vistas FROM noticias n
                WHERE n.estado = 'publicada' ORDER BY n.vistas DESC, n.publicado_en DESC LIMIT :limite OFFSET :offset";
        return $this->ejecutar($sql, [], $limite, 0)->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function relacionadas(int $categoriaId, int $excluirId, int $limite = 3): array
    {
        $sql = 'SELECT ' . self::COLUMNAS_LISTADO . ' ' . self::UNIONES . "
                WHERE n.estado = 'publicada' AND n.categoria_id = :cat AND n.id <> :excluir
                ORDER BY n.publicado_en DESC, n.id DESC LIMIT :limite OFFSET :offset";
        return $this->ejecutar($sql, [':cat' => $categoriaId, ':excluir' => $excluirId], $limite, 0)->fetchAll();
    }

    public function incrementarVistas(int $id): void
    {
        $this->pdo->prepare('UPDATE noticias SET vistas = vistas + 1 WHERE id = :id')->execute([':id' => $id]);
    }

    // ------------------------------------------------------------------ búsqueda

    /** @return list<array<string,mixed>> */
    public function buscar(string $consulta, int $limite, int $offset): array
    {
        [$condicion, $params] = $this->condicionBusqueda($consulta);
        if ($condicion === '') {
            return [];
        }
        $sql = 'SELECT ' . self::COLUMNAS_LISTADO . ' ' . self::UNIONES . "
                WHERE n.estado = 'publicada' AND $condicion
                ORDER BY n.publicado_en DESC, n.id DESC LIMIT :limite OFFSET :offset";
        return $this->ejecutar($sql, $params, $limite, $offset)->fetchAll();
    }

    public function contarBusqueda(string $consulta): int
    {
        [$condicion, $params] = $this->condicionBusqueda($consulta);
        if ($condicion === '') {
            return 0;
        }
        $sql = "SELECT COUNT(*) FROM noticias n WHERE n.estado = 'publicada' AND $condicion";
        return (int) $this->ejecutar($sql, $params)->fetchColumn();
    }

    /**
     * Cada palabra debe aparecer en título, resumen o contenido (AND entre palabras).
     * Los comodines % y _ escritos por el usuario se escapan con "!" para que se
     * busquen como texto literal. Los marcadores son distintos por columna porque
     * las sentencias preparadas nativas no permiten repetir un mismo nombre.
     *
     * @return array{0:string,1:array<string,string>}
     */
    private function condicionBusqueda(string $consulta): array
    {
        $palabras = preg_split('/\s+/u', trim($consulta), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $palabras = array_slice($palabras, 0, self::MAX_PALABRAS_BUSQUEDA);
        $partes = [];
        $params = [];
        foreach ($palabras as $i => $palabra) {
            $patron = '%' . self::escaparLike($palabra) . '%';
            $partes[] = "(n.titulo LIKE :b{$i}t ESCAPE '!' OR n.resumen LIKE :b{$i}r ESCAPE '!' OR n.contenido LIKE :b{$i}c ESCAPE '!')";
            $params[":b{$i}t"] = $patron;
            $params[":b{$i}r"] = $patron;
            $params[":b{$i}c"] = $patron;
        }
        return [implode(' AND ', $partes), $params];
    }

    public static function escaparLike(string $texto): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $texto);
    }

    // ------------------------------------------------------------------ panel

    /** @return list<array<string,mixed>> */
    public function listarAdmin(?int $autorId, int $limite, int $offset): array
    {
        $sql = 'SELECT ' . self::COLUMNAS_LISTADO . ' ' . self::UNIONES;
        $params = [];
        if ($autorId !== null) {
            $sql .= ' WHERE n.autor_id = :autor';
            $params[':autor'] = $autorId;
        }
        $sql .= ' ORDER BY n.creado_en DESC, n.id DESC LIMIT :limite OFFSET :offset';
        return $this->ejecutar($sql, $params, $limite, $offset)->fetchAll();
    }

    public function contarAdmin(?int $autorId): int
    {
        $sql = 'SELECT COUNT(*) FROM noticias n';
        $params = [];
        if ($autorId !== null) {
            $sql .= ' WHERE n.autor_id = :autor';
            $params[':autor'] = $autorId;
        }
        return (int) $this->ejecutar($sql, $params)->fetchColumn();
    }

    /** @return array{publicada:int,borrador:int,vistas:int} */
    public function estadisticas(?int $autorId = null): array
    {
        $donde = $autorId !== null ? ' WHERE autor_id = :autor' : '';
        $params = $autorId !== null ? [':autor' => $autorId] : [];

        $salida = ['publicada' => 0, 'borrador' => 0, 'vistas' => 0];
        $filas = $this->ejecutar("SELECT estado, COUNT(*) AS n FROM noticias$donde GROUP BY estado", $params)->fetchAll();
        foreach ($filas as $f) {
            $salida[(string) $f['estado']] = (int) $f['n'];
        }
        $salida['vistas'] = (int) $this->ejecutar("SELECT COALESCE(SUM(vistas), 0) FROM noticias$donde", $params)->fetchColumn();
        return $salida;
    }

    // ------------------------------------------------------------------ escritura

    /**
     * @param array{autor_id:int,categoria_id:int,titulo:string,resumen:string,contenido:string,estado:string,
     *              imagen?:?string,imagen_alt?:?string,imagen_ancho?:?int,imagen_alto?:?int} $d
     */
    public function crear(array $d): int
    {
        $ahora = date('Y-m-d H:i:s');
        $sql = 'INSERT INTO noticias
                (autor_id, categoria_id, titulo, slug, resumen, contenido, imagen, imagen_alt, imagen_ancho, imagen_alto,
                 estado, minutos_lectura, vistas, publicado_en, creado_en, actualizado_en)
                VALUES (:autor, :cat, :titulo, :slug, :resumen, :contenido, :imagen, :alt, :ancho, :alto,
                        :estado, :minutos, 0, :publicado, :creado, :actualizado)';
        $this->ejecutar($sql, [
            ':autor' => $d['autor_id'],
            ':cat' => $d['categoria_id'],
            ':titulo' => trim($d['titulo']),
            ':slug' => $this->slugUnico($d['titulo']),
            ':resumen' => trim($d['resumen']),
            ':contenido' => trim($d['contenido']),
            ':imagen' => $d['imagen'] ?? null,
            ':alt' => $d['imagen_alt'] ?? null,
            ':ancho' => $d['imagen_ancho'] ?? null,
            ':alto' => $d['imagen_alto'] ?? null,
            ':estado' => $d['estado'],
            ':minutos' => TiempoLectura::minutos($d['contenido']),
            ':publicado' => $d['estado'] === 'publicada' ? $ahora : null,
            ':creado' => $ahora,
            ':actualizado' => $ahora,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * El slug NO cambia al editar (los enlaces ya compartidos siguen funcionando).
     * publicado_en se fija la primera vez que la noticia pasa a "publicada".
     *
     * @param array<string,mixed> $d
     */
    public function actualizar(int $id, array $d): void
    {
        $actual = $this->porId($id);
        if ($actual === null) {
            return;
        }
        $ahora = date('Y-m-d H:i:s');
        $publicado = $actual['publicado_en'];
        if ($d['estado'] === 'publicada' && $publicado === null) {
            $publicado = $ahora;
        } elseif ($d['estado'] === 'borrador') {
            $publicado = null;
        }

        $sql = 'UPDATE noticias SET categoria_id = :cat, titulo = :titulo, resumen = :resumen, contenido = :contenido,
                imagen = :imagen, imagen_alt = :alt, imagen_ancho = :ancho, imagen_alto = :alto, estado = :estado,
                minutos_lectura = :minutos, publicado_en = :publicado, actualizado_en = :actualizado WHERE id = :id';
        $this->ejecutar($sql, [
            ':cat' => $d['categoria_id'],
            ':titulo' => trim((string) $d['titulo']),
            ':resumen' => trim((string) $d['resumen']),
            ':contenido' => trim((string) $d['contenido']),
            ':imagen' => $d['imagen'] ?? null,
            ':alt' => $d['imagen_alt'] ?? null,
            ':ancho' => $d['imagen_ancho'] ?? null,
            ':alto' => $d['imagen_alto'] ?? null,
            ':estado' => $d['estado'],
            ':minutos' => TiempoLectura::minutos((string) $d['contenido']),
            ':publicado' => $publicado,
            ':actualizado' => $ahora,
            ':id' => $id,
        ]);
    }

    public function eliminar(int $id): void
    {
        $this->ejecutar('DELETE FROM noticias WHERE id = :id', [':id' => $id]);
    }

    public function slugUnico(string $titulo, ?int $ignorarId = null): string
    {
        $base = Slug::de($titulo);
        $candidato = $base;
        for ($n = 2; $n < 200; $n++) {
            if (!$this->existeSlug($candidato, $ignorarId)) {
                return $candidato;
            }
            $candidato = $base . '-' . $n;
        }
        return $base . '-' . bin2hex(random_bytes(3));
    }

    private function existeSlug(string $slug, ?int $ignorarId): bool
    {
        $sql = 'SELECT 1 FROM noticias WHERE slug = :slug';
        $params = [':slug' => $slug];
        if ($ignorarId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $ignorarId;
        }
        return $this->ejecutar($sql, $params)->fetchColumn() !== false;
    }

    // ------------------------------------------------------------------ utilidades

    /**
     * Prepara y ejecuta enlazando cada valor con su tipo. LIMIT y OFFSET deben
     * enlazarse como enteros cuando las sentencias preparadas son nativas.
     *
     * @param array<string,mixed> $params
     */
    private function ejecutar(string $sql, array $params, ?int $limite = null, ?int $offset = null): PDOStatement
    {
        $st = $this->pdo->prepare($sql);
        foreach ($params as $nombre => $valor) {
            $tipo = match (true) {
                is_int($valor) => PDO::PARAM_INT,
                $valor === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $st->bindValue($nombre, $valor, $tipo);
        }
        if ($limite !== null) {
            $st->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        }
        if ($offset !== null) {
            $st->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        }
        $st->execute();
        return $st;
    }
}
