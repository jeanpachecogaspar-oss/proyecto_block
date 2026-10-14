<?php
declare(strict_types=1);

namespace Quipu\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Quipu\Core\Config;

/** Prueba de regresión: la redirección tras el login solo puede ser interna. */
final class RutaSeguraTest extends TestCase
{
    protected function setUp(): void
    {
        Config::fijar('APP_BASE', '');
    }

    /** @return array<string,array{?string,?string}> */
    public static function casos(): array
    {
        return [
            'ruta interna' => ['/admin/noticias.php?pagina=2', '/admin/noticias.php?pagina=2'],
            'raíz' => ['/', '/'],
            'URL relativa al protocolo //host' => ['//evil.example/x', null],
            'URL absoluta' => ['https://evil.example', null],
            'esquema javascript' => ['/x:javascript', null],
            'barra invertida' => ['/\\evil.example', null],
            'salto de línea (inyección de cabecera)' => ["/ok\r\nSet-Cookie: a=b", null],
            'sin barra inicial' => ['admin/index.php', null],
            'vacío' => ['', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('casos')]
    public function testRutaInternaSegura(?string $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, ruta_interna_segura($entrada));
    }
}
