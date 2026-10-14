<?php
declare(strict_types=1);

namespace Quipu\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Quipu\Support\Slug;

final class SlugTest extends TestCase
{
    /** @return array<string,array{string,string}> */
    public static function casos(): array
    {
        return [
            'tildes y ñ' => ['La feria del Tejido en Ankarí: ¡Año nuevo!', 'la-feria-del-tejido-en-ankari-ano-nuevo'],
            'espacios múltiples' => ['  Hola     mundo  ', 'hola-mundo'],
            'símbolos' => ['100% natural & local', '100-natural-local'],
            'mayúsculas' => ['QUIPU NOTICIAS', 'quipu-noticias'],
            'solo símbolos usa valor por defecto' => ['¡¿!?', 'noticia'],
            'vacío usa valor por defecto' => ['', 'noticia'],
            'HTML no sobrevive' => ['<script>alert(1)</script>', 'script-alert-1-script'],
        ];
    }

    #[DataProvider('casos')]
    public function testSlug(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, Slug::de($entrada));
    }

    public function testLargoMaximoSinGuionFinal(): void
    {
        $slug = Slug::de(str_repeat('palabra ', 30));
        $this->assertLessThanOrEqual(80, strlen($slug));
        $this->assertStringEndsNotWith('-', $slug);
    }
}
