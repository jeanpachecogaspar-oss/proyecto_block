<?php
/**
 * Panel: listado de noticias (todas para el admin, las propias para el autor) y eliminación.
 */
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

use Quipu\Core\Auth;
use Quipu\Core\Csrf;
use Quipu\Core\Database;
use Quipu\Core\Permisos;
use Quipu\Repository\NoticiaRepository;
use Quipu\Support\Formateador;
use Quipu\Support\Paginador;
use Quipu\Support\SubidaImagen;

$usuario = Auth::requerirRol(['admin', 'autor']);
$noticias = new NoticiaRepository(Database::conexion());
$esAdmin = Permisos::esAdmin($usuario);
$filtroAutor = $esAdmin ? null : (int) $usuario['id'];

// ------------------------------------------------------------------ eliminar
if (es_post()) {
    Csrf::exigir();
    if (valor('accion') === 'eliminar') {
        $noticia = $noticias->porId(entero_param('id', 'POST'));
        if ($noticia === null) {
            mensaje_flash('error', 'La noticia ya no existe.');
        } elseif (!Permisos::puedeEditarNoticia($usuario, $noticia)) {
            abortar(403, 'Acceso restringido', 'Solo puedes eliminar tus propias noticias.');
        } else {
            $noticias->eliminar((int) $noticia['id']);
            (new SubidaImagen(QUIPU_ROOT . '/public/uploads'))->eliminar($noticia['imagen']);
            mensaje_flash('exito', 'La noticia «' . $noticia['titulo'] . '» fue eliminada.');
        }
    }
    redirigir(url('admin/noticias.php'));
}

$total = $noticias->contarAdmin($filtroAutor);
$pag = new Paginador($total, 10, entero_param('pagina'));
$lista = $noticias->listarAdmin($filtroAutor, $pag->porPagina, $pag->offset);

$tituloPagina = 'Panel · Noticias';
$seccion = 'panel';
$seccionAdmin = 'noticias';
require QUIPU_ROOT . '/templates/cabecera.php';
?>
<div class="panel">
    <?php require QUIPU_ROOT . '/templates/admin_nav.php'; ?>

    <div class="panel__contenido">
        <h1>Noticias</h1>
        <p class="acciones"><a class="boton" href="<?= e(url('admin/noticia_form.php')) ?>">Nueva noticia</a></p>

        <div class="tabla-contenedor" role="region" aria-labelledby="cap-noticias" tabindex="0">
            <table class="tabla">
                <caption id="cap-noticias"><?= $esAdmin ? 'Todas las noticias' : 'Tus noticias' ?> (<?= $total ?>)</caption>
                <thead>
                    <tr>
                        <th scope="col">Título</th>
                        <th scope="col">Categoría</th>
                        <?php if ($esAdmin): ?><th scope="col">Autor</th><?php endif; ?>
                        <th scope="col">Estado</th>
                        <th scope="col">Fecha</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($lista === []): ?>
                    <tr><td colspan="<?= $esAdmin ? 6 : 5 ?>">No hay noticias para mostrar.</td></tr>
                <?php endif; ?>
                <?php foreach ($lista as $n):
                    $puede = Permisos::puedeEditarNoticia($usuario, $n);
                    ?>
                    <tr>
                        <th scope="row"><?= e($n['titulo']) ?></th>
                        <td><?= e($n['categoria_nombre']) ?></td>
                        <?php if ($esAdmin): ?><td><?= e($n['autor_nombre']) ?></td><?php endif; ?>
                        <td><span class="estado estado--<?= e($n['estado']) ?>"><?= $n['estado'] === 'publicada' ? 'Publicada' : 'Borrador' ?></span></td>
                        <td><?= e(Formateador::fecha($n['publicado_en'] ?? $n['creado_en'])) ?></td>
                        <td>
                            <div class="acciones">
                                <a class="boton boton--secundario boton--chico" href="<?= e(url('noticia.php?slug=' . rawurlencode((string) $n['slug']))) ?>"
                                   aria-label="Ver la noticia: <?= e($n['titulo']) ?>">Ver</a>
                                <?php if ($puede): ?>
                                    <a class="boton boton--secundario boton--chico" href="<?= e(url('admin/noticia_form.php?id=' . (int) $n['id'])) ?>"
                                       aria-label="Editar la noticia: <?= e($n['titulo']) ?>">Editar</a>
                                    <form method="post" action="<?= e(url('admin/noticias.php')) ?>"
                                          data-confirmar="¿Eliminar definitivamente esta noticia y sus comentarios?">
                                        <?= Csrf::campo() ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                                        <button class="boton boton--peligro boton--chico" type="submit"
                                                aria-label="Eliminar la noticia: <?= e($n['titulo']) ?>">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require QUIPU_ROOT . '/templates/parciales/paginacion.php'; ?>
    </div>
</div>
<?php require QUIPU_ROOT . '/templates/pie.php'; ?>
