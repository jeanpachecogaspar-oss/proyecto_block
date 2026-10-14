<?php
declare(strict_types=1);

namespace Quipu\Tests\Integration;

use PDOException;
use Quipu\Repository\NoticiaRepository;

final class NoticiaRepositoryTest extends BaseDatosTestCase
{
    private NoticiaRepository $repo;
    private int $autor;
    private int $categoria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new NoticiaRepository($this->pdo);
        $this->autor = $this->crearUsuario();
        $this->categoria = $this->crearCategoria();
    }

    public function testCrearCalculaSlugTiempoDeLecturaYFechaDePublicacion(): void
    {
        $id = $this->crearNoticia($this->autor, $this->categoria, 'Feria del Tejido en Ankarí');
        $n = $this->repo->porId($id);

        $this->assertSame('feria-del-tejido-en-ankari', $n['slug']);
        $this->assertSame(1, (int) $n['minutos_lectura']);
        $this->assertNotNull($n['publicado_en']);
        $this->assertSame(0, (int) $n['vistas']);
    }

    public function testBorradorNoTieneFechaDePublicacionNiApareceEnPortada(): void
    {
        $this->crearNoticia($this->autor, $this->categoria, 'Noticia publicada visible', 'publicada');
        $id = $this->crearNoticia($this->autor, $this->categoria, 'Noticia en borrador oculta', 'borrador');

        $this->assertNull($this->repo->porId($id)['publicado_en']);
        $this->assertSame(1, $this->repo->contarPublicadas());
        $titulos = array_column($this->repo->listarPublicadas(10, 0), 'titulo');
        $this->assertSame(['Noticia publicada visible'], $titulos);
        $this->assertNull($this->repo->porSlug('noticia-en-borrador-oculta'));
        $this->assertNotNull($this->repo->porSlug('noticia-en-borrador-oculta', false));
    }

    public function testSlugRepetidoRecibeSufijoNumerico(): void
    {
        $a = $this->crearNoticia($this->autor, $this->categoria, 'Titulo repetido de prueba');
        $b = $this->crearNoticia($this->autor, $this->categoria, 'Titulo repetido de prueba');
        $c = $this->crearNoticia($this->autor, $this->categoria, 'Titulo repetido de prueba');
        $this->assertSame('titulo-repetido-de-prueba', $this->repo->porId($a)['slug']);
        $this->assertSame('titulo-repetido-de-prueba-2', $this->repo->porId($b)['slug']);
        $this->assertSame('titulo-repetido-de-prueba-3', $this->repo->porId($c)['slug']);
    }

    public function testPaginacionConLimiteYOffset(): void
    {
        for ($i = 1; $i <= 13; $i++) {
            $this->crearNoticia($this->autor, $this->categoria, sprintf('Noticia numero %02d de la serie', $i));
        }
        $this->assertSame(13, $this->repo->contarPublicadas());
        $this->assertCount(6, $this->repo->listarPublicadas(6, 0));
        $this->assertCount(6, $this->repo->listarPublicadas(6, 6));
        $this->assertCount(1, $this->repo->listarPublicadas(6, 12));
        $this->assertCount(0, $this->repo->listarPublicadas(6, 18));

        $ids = array_merge(
            array_column($this->repo->listarPublicadas(6, 0), 'id'),
            array_column($this->repo->listarPublicadas(6, 6), 'id'),
            array_column($this->repo->listarPublicadas(6, 12), 'id')
        );
        $this->assertCount(13, array_unique($ids), 'ninguna noticia se repite entre páginas');
    }

    public function testFiltroPorCategoria(): void
    {
        $otra = $this->crearCategoria('Deporte');
        $this->crearNoticia($this->autor, $this->categoria, 'Noticia de cultura uno');
        $this->crearNoticia($this->autor, $otra, 'Noticia de deporte uno');
        $this->crearNoticia($this->autor, $otra, 'Noticia de deporte dos');

        $this->assertSame(2, $this->repo->contarPublicadas($otra));
        $this->assertCount(1, $this->repo->listarPublicadas(10, 0, $this->categoria));
    }

    public function testElListadoNoTraeElContenidoCompleto(): void
    {
        $this->crearNoticia($this->autor, $this->categoria, 'Noticia sin cuerpo en listado');
        $fila = $this->repo->listarPublicadas(1, 0)[0];
        $this->assertArrayNotHasKey('contenido', $fila);
        $this->assertArrayHasKey('categoria_nombre', $fila, 'la categoría llega con JOIN (sin N+1)');
        $this->assertArrayHasKey('autor_nombre', $fila);
    }

    public function testBusquedaPorPalabrasEnTituloResumenYContenido(): void
    {
        $this->crearNoticia($this->autor, $this->categoria, 'Cosecha de quinua récord');
        $this->crearNoticia($this->autor, $this->categoria, 'Taller de lectura', 'publicada', ['contenido' => 'Se leerán leyendas del valle y cuentos de quinua para todos los asistentes.']);
        $this->crearNoticia($this->autor, $this->categoria, 'Liga de vóley', 'publicada', ['resumen' => 'Final reñida entre dos barrios del distrito.']);
        $this->crearNoticia($this->autor, $this->categoria, 'Quinua en borrador secreta', 'borrador');

        $this->assertSame(2, $this->repo->contarBusqueda('quinua'), 'título y contenido; el borrador no cuenta');
        $this->assertSame(1, $this->repo->contarBusqueda('reñida barrios'), 'todas las palabras deben aparecer');
        $this->assertSame(0, $this->repo->contarBusqueda('quinua vóley'));
        $this->assertSame(1, $this->repo->contarBusqueda('QUINUA récord'), 'sin distinguir mayúsculas');
        $this->assertCount(2, $this->repo->buscar('quinua', 10, 0));
    }

    public function testLosComodinesDeLikeSeBuscanComoTextoLiteral(): void
    {
        $this->crearNoticia($this->autor, $this->categoria, 'Descuento del 50% en ferias');
        $this->crearNoticia($this->autor, $this->categoria, 'Una noticia cualquiera aquí');

        $this->assertSame(1, $this->repo->contarBusqueda('50%'));
        $this->assertSame(0, $this->repo->contarBusqueda('%%'), '"%%" no debe devolver todo el catálogo');
        $this->assertSame(0, $this->repo->contarBusqueda('__'));
        $this->assertSame('a!%b!_c!!d', NoticiaRepository::escaparLike('a%b_c!d'));
    }

    public function testBusquedaConInyeccionSqlNoDevuelveNiRompeNada(): void
    {
        $this->crearNoticia($this->autor, $this->categoria, 'Noticia normal de prueba');
        $this->assertSame(0, $this->repo->contarBusqueda("' OR 1=1 --"));
        $this->assertSame(0, $this->repo->contarBusqueda("'; DROP TABLE noticias; --"));
        $this->assertSame(1, $this->repo->contarPublicadas(), 'la tabla sigue intacta');
    }

    public function testActualizarConservaSlugYFijaFechaAlPublicar(): void
    {
        $id = $this->crearNoticia($this->autor, $this->categoria, 'Titulo original de la nota', 'borrador');
        $this->repo->actualizar($id, [
            'categoria_id' => $this->categoria,
            'titulo' => 'Titulo completamente nuevo',
            'resumen' => 'Resumen actualizado de la noticia',
            'contenido' => str_repeat('palabra ', 450),
            'estado' => 'publicada',
        ]);
        $n = $this->repo->porId($id);
        $this->assertSame('titulo-original-de-la-nota', $n['slug'], 'el slug no cambia: los enlaces compartidos siguen vivos');
        $this->assertSame('Titulo completamente nuevo', $n['titulo']);
        $this->assertNotNull($n['publicado_en']);
        $this->assertSame(3, (int) $n['minutos_lectura'], '450 palabras = 3 min');
    }

    public function testDespublicarBorraLaFechaDePublicacion(): void
    {
        $id = $this->crearNoticia($this->autor, $this->categoria, 'Noticia que se despublica');
        $this->repo->actualizar($id, [
            'categoria_id' => $this->categoria, 'titulo' => 'Noticia que se despublica',
            'resumen' => 'Resumen', 'contenido' => 'Contenido suficiente para la prueba de despublicación.', 'estado' => 'borrador',
        ]);
        $this->assertNull($this->repo->porId($id)['publicado_en']);
        $this->assertSame(0, $this->repo->contarPublicadas());
    }

    public function testVistasMasLeidasYRelacionadas(): void
    {
        $a = $this->crearNoticia($this->autor, $this->categoria, 'Noticia poco leída de prueba');
        $b = $this->crearNoticia($this->autor, $this->categoria, 'Noticia muy leída de prueba');
        $this->crearNoticia($this->autor, $this->categoria, 'Otra noticia de la categoría');
        $this->repo->incrementarVistas($b);
        $this->repo->incrementarVistas($b);
        $this->repo->incrementarVistas($a);

        $top = $this->repo->masLeidas(2);
        $this->assertSame($b, (int) $top[0]['id']);
        $this->assertSame(2, (int) $top[0]['vistas']);

        $rel = $this->repo->relacionadas($this->categoria, $b, 5);
        $this->assertCount(2, $rel);
        $this->assertNotContains($b, array_map('intval', array_column($rel, 'id')));
    }

    public function testEstadisticasGeneralesYPorAutor(): void
    {
        $otroAutor = $this->crearUsuario('Beto Ríos', 'beto@ejemplo.pe');
        $this->crearNoticia($this->autor, $this->categoria, 'Publicada de Ana en prueba');
        $this->crearNoticia($this->autor, $this->categoria, 'Borrador de Ana en prueba', 'borrador');
        $this->crearNoticia($otroAutor, $this->categoria, 'Publicada de Beto en prueba');

        $this->assertSame(['publicada' => 2, 'borrador' => 1, 'vistas' => 0], $this->repo->estadisticas());
        $this->assertSame(['publicada' => 1, 'borrador' => 1, 'vistas' => 0], $this->repo->estadisticas($this->autor));
        $this->assertSame(2, $this->repo->contarAdmin($this->autor));
        $this->assertSame(3, $this->repo->contarAdmin(null));
    }

    public function testEliminarNoticiaBorraSusComentariosEnCascada(): void
    {
        $id = $this->crearNoticia($this->autor, $this->categoria, 'Noticia con comentarios en prueba');
        (new \Quipu\Repository\ComentarioRepository($this->pdo))->crear($id, $this->autor, 'Un comentario');
        $this->repo->eliminar($id);
        $this->assertNull($this->repo->porId($id));
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM comentarios')->fetchColumn());
    }

    public function testNoSePuedeEliminarUnaCategoriaConNoticias(): void
    {
        $this->crearNoticia($this->autor, $this->categoria, 'Noticia que ancla la categoría');
        $this->expectException(PDOException::class);
        (new \Quipu\Repository\CategoriaRepository($this->pdo))->eliminar($this->categoria);
    }
}
