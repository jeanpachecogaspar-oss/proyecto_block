<?php
declare(strict_types=1);

namespace Quipu\Tests\Integration;

use Quipu\Repository\CategoriaRepository;

final class CategoriaRepositoryTest extends BaseDatosTestCase
{
    public function testCrearGeneraSlugYDetectaDuplicados(): void
    {
        $repo = new CategoriaRepository($this->pdo);
        $id = $repo->crear('Economía y mercados', 'Producción y precios');

        $this->assertSame('economia-y-mercados', $repo->porId($id)['slug']);
        $this->assertSame($id, (int) $repo->porSlug('economia-y-mercados')['id']);
        $this->assertTrue($repo->existeNombre('ECONOMÍA Y MERCADOS'));
        $this->assertTrue($repo->existeNombre('Economia y mercados'), 'mismo slug = duplicado');
        $this->assertFalse($repo->existeNombre('Economía y mercados', $id), 'al editar se ignora a sí misma');
        $this->assertFalse($repo->existeNombre('Deporte'));
    }

    public function testConteoSoloCuentaNoticiasPublicadas(): void
    {
        $autor = $this->crearUsuario();
        $cat = $this->crearCategoria('Ambiente');
        $this->crearCategoria('Vacía');
        $this->crearNoticia($autor, $cat, 'Publicada de ambiente uno');
        $this->crearNoticia($autor, $cat, 'Borrador de ambiente dos', 'borrador');

        $porNombre = array_column((new CategoriaRepository($this->pdo))->conConteo(), 'total', 'nombre');
        $this->assertSame(1, (int) $porNombre['Ambiente']);
        $this->assertSame(0, (int) $porNombre['Vacía']);
    }

    public function testActualizarYEliminarSinNoticias(): void
    {
        $repo = new CategoriaRepository($this->pdo);
        $id = $repo->crear('Cultura', '');
        $repo->actualizar($id, 'Cultura y tradiciones', 'Ferias y talleres');
        $this->assertSame('cultura-y-tradiciones', $repo->porId($id)['slug']);
        $this->assertSame(0, $repo->contarNoticias($id));
        $repo->eliminar($id);
        $this->assertNull($repo->porId($id));
    }
}
