<?php
declare(strict_types=1);

namespace Quipu\Tests\Integration;

use Quipu\Repository\ComentarioRepository;

final class ComentarioRepositoryTest extends BaseDatosTestCase
{
    public function testCrearYListarSoloVisibles(): void
    {
        $autor = $this->crearUsuario();
        $lector = $this->crearUsuario('Diego Ñahui', 'diego@ejemplo.pe', 'lector');
        $noticia = $this->crearNoticia($autor, $this->crearCategoria(), 'Noticia para comentar en prueba');
        $repo = new ComentarioRepository($this->pdo);

        $c1 = $repo->crear($noticia, $lector, '  Primer comentario  ');
        $c2 = $repo->crear($noticia, $autor, 'Segundo comentario');

        $visibles = $repo->visiblesDeNoticia($noticia);
        $this->assertCount(2, $visibles);
        $this->assertSame('Primer comentario', $visibles[0]['contenido'], 'se recorta el espacio sobrante');
        $this->assertSame('Diego Ñahui', $visibles[0]['autor_nombre']);

        $repo->cambiarEstado($c1, 'oculto');
        $this->assertCount(1, $repo->visiblesDeNoticia($noticia));
        $this->assertSame(2, $repo->contarTodos(), 'ocultar no borra');
        $this->assertSame(1, $repo->contarVisiblesDeNoticia($noticia));

        $repo->eliminar($c2);
        $this->assertSame(1, $repo->contarTodos());
    }

    public function testEstadoInvalidoSeIgnora(): void
    {
        $u = $this->crearUsuario();
        $n = $this->crearNoticia($u, $this->crearCategoria(), 'Noticia estado invalido prueba');
        $repo = new ComentarioRepository($this->pdo);
        $id = $repo->crear($n, $u, 'Comentario');
        $repo->cambiarEstado($id, 'borrado-raro');
        $this->assertCount(1, $repo->visiblesDeNoticia($n));
    }

    public function testEsperaEntreComentarios(): void
    {
        $u = $this->crearUsuario();
        $n = $this->crearNoticia($u, $this->crearCategoria(), 'Noticia anti spam de prueba');
        $repo = new ComentarioRepository($this->pdo);

        $this->assertSame(0, $repo->segundosDeEspera($u), 'sin comentarios previos no hay espera');
        $repo->crear($n, $u, 'Primero');
        $ahora = time();
        $this->assertGreaterThan(0, $repo->segundosDeEspera($u, $ahora));
        $this->assertLessThanOrEqual(ComentarioRepository::ESPERA_SEGUNDOS, $repo->segundosDeEspera($u, $ahora));
        $this->assertSame(0, $repo->segundosDeEspera($u, $ahora + ComentarioRepository::ESPERA_SEGUNDOS + 1));
    }

    public function testListadoAdminPaginado(): void
    {
        $u = $this->crearUsuario();
        $n = $this->crearNoticia($u, $this->crearCategoria(), 'Noticia con muchos comentarios');
        $repo = new ComentarioRepository($this->pdo);
        for ($i = 1; $i <= 12; $i++) {
            $repo->crear($n, $u, "Comentario $i");
        }
        $this->assertCount(10, $repo->listarAdmin(10, 0));
        $this->assertCount(2, $repo->listarAdmin(10, 10));
        $this->assertArrayHasKey('noticia_titulo', $repo->listarAdmin(1, 0)[0]);
    }
}
