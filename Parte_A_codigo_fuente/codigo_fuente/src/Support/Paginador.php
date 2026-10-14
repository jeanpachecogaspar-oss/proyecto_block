<?php
declare(strict_types=1);

namespace Quipu\Support;

/**
 * Cálculo de paginación. Normaliza cualquier página solicitada:
 * valores menores que 1 pasan a 1 y mayores que la última pasan a la última.
 */
final class Paginador
{
    public readonly int $total;
    public readonly int $porPagina;
    public readonly int $pagina;
    public readonly int $totalPaginas;
    public readonly int $offset;

    public function __construct(int $total, int $porPagina, int $paginaSolicitada)
    {
        $this->total = max(0, $total);
        $this->porPagina = max(1, $porPagina);
        $this->totalPaginas = max(1, (int) ceil($this->total / $this->porPagina));
        $this->pagina = min(max(1, $paginaSolicitada), $this->totalPaginas);
        $this->offset = ($this->pagina - 1) * $this->porPagina;
    }

    public function desde(): int
    {
        return $this->total === 0 ? 0 : $this->offset + 1;
    }

    public function hasta(): int
    {
        return min($this->total, $this->offset + $this->porPagina);
    }

    public function hayAnterior(): bool
    {
        return $this->pagina > 1;
    }

    public function haySiguiente(): bool
    {
        return $this->pagina < $this->totalPaginas;
    }

    /**
     * Números de página a mostrar alrededor de la actual.
     *
     * @return list<int>
     */
    public function ventana(int $radio = 2): array
    {
        $inicio = max(1, $this->pagina - $radio);
        $fin = min($this->totalPaginas, $this->pagina + $radio);
        return range($inicio, $fin);
    }
}
