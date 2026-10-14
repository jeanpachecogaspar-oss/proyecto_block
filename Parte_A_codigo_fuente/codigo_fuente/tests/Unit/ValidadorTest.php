<?php
declare(strict_types=1);

namespace Quipu\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Quipu\Support\Validador;

/**
 * Técnicas: partición de equivalencia y análisis de valores límite
 * (mínimo-1, mínimo, máximo, máximo+1) sobre los campos de texto.
 */
final class ValidadorTest extends TestCase
{
    // ---------------------------------------------------------------- nombre
    /** @return array<string,array{string,bool}> */
    public static function nombres(): array
    {
        return [
            'límite: 1 carácter (inválido)' => ['A', false],
            'límite: 2 caracteres (mínimo)' => ['An', true],
            'válido con tilde y ñ' => ['Ñusta Quispe', true],
            'válido con apóstrofo y guion' => ["D'Angelo Pérez-Ruiz", true],
            'límite: 60 caracteres (máximo)' => [str_repeat('a', 60), true],
            'límite: 61 caracteres (inválido)' => [str_repeat('a', 61), false],
            'vacío' => ['', false],
            'solo espacios' => ['     ', false],
            'con dígitos' => ['Ana123', false],
            'con etiqueta HTML' => ['<b>Ana</b>', false],
        ];
    }

    #[DataProvider('nombres')]
    public function testNombre(string $valor, bool $valido): void
    {
        $this->assertSame($valido, Validador::nombre($valor) === null);
    }

    // ---------------------------------------------------------------- email
    /** @return array<string,array{string,bool}> */
    public static function correos(): array
    {
        return [
            'válido simple' => ['ana@ejemplo.pe', true],
            'válido con subdominio' => ['ana.torres@mail.medio.com.pe', true],
            'sin arroba' => ['anaejemplo.pe', false],
            'sin dominio' => ['ana@', false],
            'con espacios internos' => ['ana torres@ejemplo.pe', false],
            'vacío' => ['', false],
            'más de 120 caracteres' => [str_repeat('a', 115) . '@e.pe', false],
        ];
    }

    #[DataProvider('correos')]
    public function testEmail(string $valor, bool $valido): void
    {
        $this->assertSame($valido, Validador::email($valor) === null);
    }

    // ---------------------------------------------------------------- password
    /** @return array<string,array{string,bool}> */
    public static function claves(): array
    {
        return [
            'límite: 9 caracteres (inválido)' => ['Abcdefgh1', false],
            'límite: 10 caracteres (mínimo)' => ['Abcdefghi1', true],
            'límite: 72 bytes (máximo)' => [str_repeat('a', 71) . '1', true],
            'límite: 73 bytes (inválido)' => [str_repeat('a', 72) . '1', false],
            'solo letras' => ['abcdefghijkl', false],
            'solo números' => ['12345678901', false],
            'frase con espacios' => ['mi frase 2026 larga', true],
            'común rechazada' => ['password123', false],
            'vacía' => ['', false],
        ];
    }

    #[DataProvider('claves')]
    public function testPassword(string $valor, bool $valido): void
    {
        $this->assertSame($valido, Validador::password($valor) === null);
    }

    public function testConfirmacionPassword(): void
    {
        $this->assertNull(Validador::confirmacionPassword('Clave-123456', 'Clave-123456'));
        $this->assertNotNull(Validador::confirmacionPassword('Clave-123456', 'clave-123456'));
    }

    // ---------------------------------------------------------------- textos con rango
    /** @return array<string,array{string,bool}> */
    public static function titulos(): array
    {
        return [
            'límite: 9 (inválido)' => [str_repeat('t', Validador::TITULO_MIN - 1), false],
            'límite: 10 (mínimo)' => [str_repeat('t', Validador::TITULO_MIN), true],
            'límite: 150 (máximo)' => [str_repeat('t', Validador::TITULO_MAX), true],
            'límite: 151 (inválido)' => [str_repeat('t', Validador::TITULO_MAX + 1), false],
            'vacío' => ['', false],
        ];
    }

    #[DataProvider('titulos')]
    public function testTitulo(string $valor, bool $valido): void
    {
        $this->assertSame($valido, Validador::titulo($valor) === null);
    }

    /** @return array<string,array{string,bool}> */
    public static function comentarios(): array
    {
        return [
            'límite: 1 (inválido)' => ['a', false],
            'límite: 2 (mínimo)' => ['ok', true],
            'límite: 1000 (máximo)' => [str_repeat('c', Validador::COMENTARIO_MAX), true],
            'límite: 1001 (inválido)' => [str_repeat('c', Validador::COMENTARIO_MAX + 1), false],
            'solo espacios' => ['      ', false],
            'con HTML (se acepta; se escapa al mostrar)' => ['<b>hola</b>', true],
        ];
    }

    #[DataProvider('comentarios')]
    public function testComentario(string $valor, bool $valido): void
    {
        $this->assertSame($valido, Validador::comentario($valor) === null);
    }

    public function testResumenYContenidoLimites(): void
    {
        $this->assertNotNull(Validador::resumen(str_repeat('r', Validador::RESUMEN_MIN - 1)));
        $this->assertNull(Validador::resumen(str_repeat('r', Validador::RESUMEN_MIN)));
        $this->assertNull(Validador::resumen(str_repeat('r', Validador::RESUMEN_MAX)));
        $this->assertNotNull(Validador::resumen(str_repeat('r', Validador::RESUMEN_MAX + 1)));
        $this->assertNotNull(Validador::contenido(str_repeat('c', Validador::CONTENIDO_MIN - 1)));
        $this->assertNull(Validador::contenido(str_repeat('c', Validador::CONTENIDO_MIN)));
        $this->assertNull(Validador::contenido(str_repeat('c', Validador::CONTENIDO_MAX)));
        $this->assertNotNull(Validador::contenido(str_repeat('c', Validador::CONTENIDO_MAX + 1)));
    }

    public function testTextoAlternativoEsObligatorioParaImagenes(): void
    {
        $this->assertNotNull(Validador::textoAlternativo(''));
        $this->assertNotNull(Validador::textoAlternativo('foto'));
        $this->assertNull(Validador::textoAlternativo('Mercado de trueque en la plaza'));
    }

    // ---------------------------------------------------------------- búsqueda
    /** @return array<string,array{string,bool}> */
    public static function busquedas(): array
    {
        return [
            'vacía (no es error)' => ['', true],
            'límite: 1 carácter (inválido)' => ['a', false],
            'límite: 2 caracteres (mínimo)' => ['ab', true],
            'límite: 60 caracteres (máximo)' => [str_repeat('b', 60), true],
            'límite: 61 caracteres (inválido)' => [str_repeat('b', 61), false],
        ];
    }

    #[DataProvider('busquedas')]
    public function testBusqueda(string $valor, bool $valido): void
    {
        $this->assertSame($valido, Validador::busqueda($valor) === null);
    }
}
