<?php
/**
 * Panel (solo administrador): moderación de comentarios.
 * Ocultar (reversible) o eliminar (definitivo).
 */
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

use Quipu\Core\Auth;
use Quipu\Core\Csrf;
use Quipu\Core\Database;
use Quipu\Repository\ComentarioRepository;
use Quipu\Support\Formateador;
use Quipu\Support\Paginador;

$usuario = Auth::requerirRol(['admin']);
$repo = new ComentarioRepository(Database::conexion());

if (es_post()) {
    Csrf::exigir();
    $id = entero_param('id', 'POST');
    $accion = valor('accion');
    $pagina = entero_param('pagina', 'POST');

    if ($id > 0 && in_array($accion, ['ocultar', 'mostrar', 'eliminar'], true)) {
        if ($accion === 'eliminar') {
            $repo->eliminar($id);
            mensaje_flash('exito', 'El comentario fue eliminado.');
        } else {
            $repo->cambiarEstado($id, $accion === 'ocultar' ? 'oculto' : 'visible');
            mensaje_flash('exito', $accion === 'ocultar' ? 'El comentario quedó oculto para el público.' : 'El comentario volvió a mostrarse.');
        }
    }
    redirigir(url('admin/comentarios.php') . ($pagina > 1 ? '?pagina=' . $pagina : ''));
}

$total = $repo->contarTodos();
$pag = new Paginador($total, 10, entero_param('pagina'));
$lista = $repo->listarAdmin($pag->porPagina, $pag->offset);

$tituloPagina = 'Panel · Comentarios';
$seccion = 'panel';
$seccionAdmin = 'comentarios';
require QUIPU_ROOT . '/templates/cabecera.php';
?>
<div class="panel">
    <?php require QUIPU_ROOT . '/templates/admin_nav.php'; ?>

    <div class="panel__contenido">
        <h1>Moderación de comentarios</h1>

        <div class="tabla-contenedor" role="region" aria-labelledby="cap-comentarios" tabindex="0">
            <table class="tabla">
                <caption id="cap-comentarios">Comentarios, del más reciente al más antiguo (<?= $total ?>)</caption>
                <thead>
                    <tr>
                        <th scope="col">Comentario</th>
                        <th scope="col">Autor</th>
                        <th scope="col">Noticia</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($lista === []): ?>
                    <tr><td colspan="5">Todavía no hay comentarios.</td></tr>
                <?php endif; ?>
                <?php foreach ($lista as $c): ?>
                    <tr>
                        <th scope="row">
                            <?= e(mb_strimwidth((string) $c['contenido'], 0, 140, '…')) ?><br>
                            <span class="texto-suave"><?= e(Formateador::fecha($c['creado_en'])) ?></span>
                        </th>
                        <td><?= e($c['autor_nombre']) ?></td>
                        <td><a href="<?= e(url('noticia.php?slug=' . rawurlencode((string) $c['noticia_slug'])) . '#comentarios') ?>"><?= e($c['noticia_titulo']) ?></a></td>
                        <td><span class="estado <?= $c['estado'] === 'oculto' ? 'estado--oculto' : '' ?>"><?= $c['estado'] === 'oculto' ? 'Oculto' : 'Visible' ?></span></td>
                        <td>
                            <div class="acciones">
                                <form method="post" action="<?= e(url('admin/comentarios.php')) ?>">
                                    <?= Csrf::campo() ?>
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <input type="hidden" name="pagina" value="<?= $pag->pagina ?>">
                                    <input type="hidden" name="accion" value="<?= $c['estado'] === 'oculto' ? 'mostrar' : 'ocultar' ?>">
                                    <button class="boton boton--secundario boton--chico" type="submit"
                                            aria-label="<?= $c['estado'] === 'oculto' ? 'Mostrar' : 'Ocultar' ?> el comentario de <?= e($c['autor_nombre']) ?>"><?= $c['estado'] === 'oculto' ? 'Mostrar' : 'Ocultar' ?></button>
                                </form>
                                <form method="post" action="<?= e(url('admin/comentarios.php')) ?>"
                                      data-confirmar="¿Eliminar definitivamente este comentario?">
                                    <?= Csrf::campo() ?>
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <input type="hidden" name="pagina" value="<?= $pag->pagina ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <button class="boton boton--peligro boton--chico" type="submit"
                                            aria-label="Eliminar el comentario de <?= e($c['autor_nombre']) ?>">Eliminar</button>
                                </form>
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
