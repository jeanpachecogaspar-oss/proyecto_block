<?php
declare(strict_types=1);

namespace Quipu\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Quipu\Support\Paginador;

final class PaginadorTest extends TestCase
{
    /** @return array<string,array{int,int,int,int,int,int}> total, porPágina, solicitada, página, totalPáginas, offset */
    public static function casos(): array
    {
        return [
            'sin resultados' => [0, 6, 1, 1, 1, 0],
            'una página exacta (6 de 6)' => [6, 6, 1, 1, 1, 0],
            'límite: 7 resultados abren página 2' => [7, 6, 2, 2, 2, 6],
            'página 0 se corrige a 1' => [20, 6, 0, 1, 4, 0],
            'página negativa se corrige a 1' => [20, 6, -5, 1, 4, 0],
            'página mayor a la última se corrige' => [20, 6, 99, 4, 4, 18],
            'última página' => [20, 6, 4, 4, 4, 18],
        ];
    }

    #[DataProvider('casos')]
    public function testCalculo(int $total, int $porPagina, int $solicitada, int $pagina, int $totalPaginas, int $offset): void
    {
        $p = new Paginador($total, $porPagina, $solicitada);
        $this->assertSame($pagina, $p->pagina);
        $this->assertSame($totalPaginas, $p->totalPaginas);
        $this->assertSame($offset, $p->offset);
    }

    public function testDesdeHastaYVentana(): void
    {
        $p = new Paginador(20, 6, 4);
        $this->assertSame(19, $p->desde());
        $this->assertSame(20, $p->hasta());
        $this->assertTrue($p->hayAnterior());
        $this->assertFalse($p->haySiguiente());
        $this->assertSame([2, 3, 4], $p->ventana());
        $this->assertSame(0, (new Paginador(0, 6, 1))->desde());
    }
}
