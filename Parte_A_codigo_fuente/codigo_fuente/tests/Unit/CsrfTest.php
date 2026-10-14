<?php
declare(strict_types=1);

namespace Quipu\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quipu\Core\Csrf;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testElTokenEsLargoYEstable(): void
    {
        $t = Csrf::token();
        $this->assertSame(64, strlen($t));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $t);
        $this->assertSame($t, Csrf::token(), 'dentro de una sesión el token no cambia');
    }

    public function testValidaSoloElTokenCorrecto(): void
    {
        $t = Csrf::token();
        $this->assertTrue(Csrf::valido($t));
        $this->assertFalse(Csrf::valido($t . 'x'));
        $this->assertFalse(Csrf::valido(strrev($t)));
        $this->assertFalse(Csrf::valido(''));
        $this->assertFalse(Csrf::valido(null));
    }

    public function testSinTokenEnSesionNadaEsValido(): void
    {
        $this->assertFalse(Csrf::valido('cualquiera'));
    }

    public function testRenovarInvalidaElTokenAnterior(): void
    {
        $viejo = Csrf::token();
        Csrf::renovar();
        $this->assertFalse(Csrf::valido($viejo));
        $this->assertTrue(Csrf::valido(Csrf::token()));
    }

    public function testCampoOcultoContieneElToken(): void
    {
        $campo = Csrf::campo();
        $this->assertStringContainsString('type="hidden"', $campo);
        $this->assertStringContainsString('name="_csrf"', $campo);
        $this->assertStringContainsString(Csrf::token(), $campo);
    }
}
