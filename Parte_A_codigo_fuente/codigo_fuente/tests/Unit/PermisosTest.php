<?php
declare(strict_types=1);

namespace Quipu\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Quipu\Core\Permisos;

/** Matriz de control de acceso por rol. */
final class PermisosTest extends TestCase
{
    /** @return array<string,array{?array<string,mixed>,bool,bool,bool,bool}> usuario, panel, comentar, moderar, editarAjena */
    public static function matriz(): array
    {
        return [
            'visitante' => [null, false, false, false, false],
            'lector' => [['id' => 3, 'rol' => 'lector', 'activo' => 1], false, true, false, false],
            'lector desactivado' => [['id' => 3, 'rol' => 'lector', 'activo' => 0], false, false, false, false],
            'autor' => [['id' => 2, 'rol' => 'autor', 'activo' => 1], true, true, false, false],
            'admin' => [['id' => 1, 'rol' => 'admin', 'activo' => 1], true, true, true, true],
            'rol desconocido' => [['id' => 9, 'rol' => 'invitado', 'activo' => 1], false, false, false, false],
        ];
    }

    /** @param array<string,mixed>|null $usuario */
    #[DataProvider('matriz')]
    public function testMatriz(?array $usuario, bool $panel, bool $comentar, bool $moderar, bool $editarAjena): void
    {
        $this->assertSame($panel, Permisos::puedeEntrarAlPanel($usuario));
        $this->assertSame($comentar, Permisos::puedeComentar($usuario));
        $this->assertSame($moderar, Permisos::puedeModerar($usuario));
        $this->assertSame($editarAjena, Permisos::puedeEditarNoticia($usuario, ['autor_id' => 777]));
    }

    public function testAutorEditaSoloLoPropio(): void
    {
        $autor = ['id' => 2, 'rol' => 'autor', 'activo' => 1];
        $this->assertTrue(Permisos::puedeEditarNoticia($autor, ['autor_id' => 2]));
        $this->assertTrue(Permisos::puedeEditarNoticia($autor, ['autor_id' => '2']));
        $this->assertFalse(Permisos::puedeEditarNoticia($autor, ['autor_id' => 3]));
    }

    public function testRolesValidos(): void
    {
        $this->assertTrue(Permisos::rolValido('admin'));
        $this->assertTrue(Permisos::rolValido('autor'));
        $this->assertTrue(Permisos::rolValido('lector'));
        $this->assertFalse(Permisos::rolValido('superadmin'));
        $this->assertFalse(Permisos::rolValido(''));
        $this->assertFalse(Permisos::rolValido('ADMIN'));
    }
}
