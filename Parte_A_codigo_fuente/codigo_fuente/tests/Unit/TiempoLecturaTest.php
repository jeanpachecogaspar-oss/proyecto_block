<?php
declare(strict_types=1);

namespace Quipu\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Quipu\Support\TiempoLectura;

/** Función extra N.º 1: valores límite alrededor de 200 palabras por minuto. */
final class TiempoLecturaTest extends TestCase
{
    /** @return array<string,array{int,int}> */
    public static function limites(): array
    {
        return [
            '0 palabras -> 1 minuto (mínimo)' => [0, 1],
            '1 palabra -> 1 minuto' => [1, 1],
            '199 palabras -> 1 minuto' => [199, 1],
            '200 palabras -> 1 minuto' => [200, 1],
            '201 palabras -> 2 minutos' => [201, 2],
            '400 palabras -> 2 minutos' => [400, 2],
            '401 palabras -> 3 minutos' => [401, 3],
        ];
    }

    #[DataProvider('limites')]
    public function testMinutos(int $palabras, int $esperado): void
    {
        $texto = $palabras === 0 ? '' : implode(' ', array_fill(0, $palabras, 'palabra'));
        $this->assertSame($esperado, TiempoLectura::minutos($texto));
    }

    public function testCuentaPalabrasConTildesPuntuacionYSaltos(): void
    {
        $this->assertSame(7, TiempoLectura::palabras("Año nuevo, vida nueva...\n\n¡Ñandú! y café"));
    }

    public function testEtiquetaSingularYPlural(): void
    {
        $this->assertSame('1 min de lectura', TiempoLectura::etiqueta(1));
        $this->assertSame('4 min de lectura', TiempoLectura::etiqueta(4));
    }
}
