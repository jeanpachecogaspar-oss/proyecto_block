<?php
declare(strict_types=1);

namespace Quipu\Tests\Integration;

/** Comprueba que los datos de demostración del script SQL cargan y cumplen las reglas. */
final class EsquemaYDatosDemoTest extends BaseDatosTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database/quipu_noticias.sql');
        $marca = '-- ==== DATOS DE DEMOSTRACIÓN ====';
        $this->pdo->exec(substr($sql, (int) strpos($sql, $marca) + strlen($marca)));
    }

    public function testCantidadesDeDatosDemo(): void
    {
        $conteo = fn (string $t): int => (int) $this->pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        $this->assertSame(3, $conteo('usuarios'));
        $this->assertSame(5, $conteo('categorias'));
        $this->assertSame(9, $conteo('noticias'));
        $this->assertSame(6, $conteo('comentarios'));
    }

    public function testLasCuentasDemoTienenLosTresRolesYHashBcrypt(): void
    {
        $roles = $this->pdo->query('SELECT rol FROM usuarios ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['admin', 'autor', 'lector'], $roles);
        $hash = (string) $this->pdo->query("SELECT password_hash FROM usuarios WHERE rol = 'admin'")->fetchColumn();
        $this->assertTrue(password_verify('Admin#Quipu2026', $hash));
    }

    public function testTodaNoticiaConImagenTieneTextoAlternativo(): void
    {
        $sinAlt = (int) $this->pdo->query("SELECT COUNT(*) FROM noticias WHERE imagen IS NOT NULL AND (imagen_alt IS NULL OR imagen_alt = '')")->fetchColumn();
        $this->assertSame(0, $sinAlt);
    }

    public function testLosTamanosDelContenidoRespetanLosLimitesDelValidador(): void
    {
        foreach ($this->pdo->query('SELECT titulo, resumen, contenido FROM noticias')->fetchAll() as $n) {
            $this->assertNull(\Quipu\Support\Validador::titulo($n['titulo']), $n['titulo']);
            $this->assertNull(\Quipu\Support\Validador::resumen($n['resumen']), $n['titulo']);
            $this->assertNull(\Quipu\Support\Validador::contenido($n['contenido']), $n['titulo']);
        }
    }

    public function testElMinutoDeLecturaGuardadoCoincideConElCalculo(): void
    {
        foreach ($this->pdo->query('SELECT contenido, minutos_lectura FROM noticias')->fetchAll() as $n) {
            $this->assertSame(\Quipu\Support\TiempoLectura::minutos($n['contenido']), (int) $n['minutos_lectura']);
        }
    }
}
