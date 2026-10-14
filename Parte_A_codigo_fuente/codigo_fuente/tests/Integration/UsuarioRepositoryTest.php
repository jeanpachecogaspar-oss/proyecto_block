<?php
declare(strict_types=1);

namespace Quipu\Tests\Integration;

use PDOException;
use Quipu\Repository\UsuarioRepository;

final class UsuarioRepositoryTest extends BaseDatosTestCase
{
    public function testCrearYBuscarPorEmailSinDistinguirMayusculas(): void
    {
        $repo = new UsuarioRepository($this->pdo);
        $id = $repo->crear('Ana Torres', '  ANA@Ejemplo.PE ', password_hash('Clave-segura-1', PASSWORD_DEFAULT));

        $u = $repo->porEmail('ana@ejemplo.pe');
        $this->assertNotNull($u);
        $this->assertSame($id, (int) $u['id']);
        $this->assertSame('ana@ejemplo.pe', $u['email']);
        $this->assertSame('lector', $u['rol'], 'el rol por defecto es lector');
        $this->assertSame(1, (int) $u['activo']);
    }

    public function testLaContrasenaSeGuardaComoHashNoEnClaro(): void
    {
        $repo = new UsuarioRepository($this->pdo);
        $repo->crear('Ana Torres', 'ana@ejemplo.pe', password_hash('Clave-segura-1', PASSWORD_DEFAULT));
        $hash = (string) $repo->porEmail('ana@ejemplo.pe')['password_hash'];

        $this->assertNotSame('Clave-segura-1', $hash);
        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertTrue(password_verify('Clave-segura-1', $hash));
        $this->assertFalse(password_verify('otra', $hash));
    }

    public function testCorreoDuplicadoLoImpideLaBaseDeDatos(): void
    {
        $repo = new UsuarioRepository($this->pdo);
        $repo->crear('Ana', 'ana@ejemplo.pe', 'hash');
        $this->assertTrue($repo->existeEmail('ANA@ejemplo.pe'));

        $this->expectException(PDOException::class);
        $repo->crear('Otra Ana', 'ana@ejemplo.pe', 'hash');
    }

    public function testInyeccionSqlEnElCorreoNoDevuelveUsuarios(): void
    {
        $repo = new UsuarioRepository($this->pdo);
        $repo->crear('Ana', 'ana@ejemplo.pe', 'hash');
        $this->assertNull($repo->porEmail("' OR '1'='1"));
        $this->assertNull($repo->porEmail("x'; DROP TABLE usuarios; --"));
        $this->assertNotNull($repo->porEmail('ana@ejemplo.pe'), 'la tabla sigue existiendo');
    }

    public function testCambiarRolYActivo(): void
    {
        $repo = new UsuarioRepository($this->pdo);
        $id = $repo->crear('Ana', 'ana@ejemplo.pe', 'hash');
        $repo->cambiarRol($id, 'autor');
        $repo->cambiarActivo($id, false);
        $u = $repo->porId($id);
        $this->assertSame('autor', $u['rol']);
        $this->assertSame(0, (int) $u['activo']);
        $this->assertSame(0, $repo->contarActivosPorRol('autor'));
    }

    public function testElMotorRechazaUnRolInexistente(): void
    {
        $repo = new UsuarioRepository($this->pdo);
        $id = $repo->crear('Ana', 'ana@ejemplo.pe', 'hash');
        $this->expectException(PDOException::class);
        $repo->cambiarRol($id, 'superadmin');
    }

    public function testContarPorRol(): void
    {
        $repo = new UsuarioRepository($this->pdo);
        $repo->crear('A', 'a@x.pe', 'h', 'admin');
        $repo->crear('B', 'b@x.pe', 'h', 'autor');
        $repo->crear('C', 'c@x.pe', 'h');
        $repo->crear('D', 'd@x.pe', 'h');
        $this->assertSame(['admin' => 1, 'autor' => 1, 'lector' => 2], $repo->contarPorRol());
    }
}
