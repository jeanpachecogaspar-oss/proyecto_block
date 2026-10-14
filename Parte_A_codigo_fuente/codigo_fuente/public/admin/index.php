<?php
/**
 * Panel: resumen. Un administrador ve cifras de todo el sitio; un autor solo las suyas.
 */
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

use Quipu\Core\Auth;
use Quipu\Core\Database;
use Quipu\Core\Permisos;
use Quipu\Repository\ComentarioRepository;
use Quipu\Repository\NoticiaRepository;
use Quipu\Repository\UsuarioRepository;
use Quipu\Support\Formateador;

$usuario = Auth::requerirRol(['admin', 'autor']);
$pdo = Database::conexion();
$noticias = new NoticiaRepository($pdo);

$esAdmin = Permisos::esAdmin($usuario);
$filtroAutor = $esAdmin ? null : (int) $usuario['id'];

$stats = $noticias->estadisticas($filtroAutor);
$recientes = $noticias->listarAdmin($filtroAutor, 5, 0);
$masLeidas = $noticias->masLeidas(5);
$usuariosPorRol = $esAdmin ? (new UsuarioRepository($pdo))->contarPorRol() : [];
$totalComentarios = $esAdmin ? (new ComentarioRepository($pdo))->contarTodos() : 0;

$tituloPagina = 'Panel · Resumen';
$seccion = 'panel';
$seccionAdmin = 'resumen';
require QUIPU_ROOT . '/templates/cabecera.php';
?>
<div class="panel">
    <?php require QUIPU_ROOT . '/templates/admin_nav.php'; ?>

    <div class="panel__contenido">
        <h1>Resumen del panel</h1>
        <p class="texto-suave"><?= $esAdmin ? 'Cifras de todo el sitio.' : 'Cifras de tus noticias.' ?></p>

        <dl class="datos">
            <div class="dato"><dt>Noticias publicadas</dt><dd><?= $stats['publicada'] ?></dd></div>
            <div class="dato"><dt>Borradores</dt><dd><?= $stats['borrador'] ?></dd></div>
            <div class="dato"><dt>Lecturas totales</dt><dd><?= $stats['vistas'] ?></dd></div>
            <?php if ($esAdmin): ?>
                <div class="dato"><dt>Comentarios</dt><dd><?= $totalComentarios ?></dd></div>
                <div class="dato"><dt>Administradores</dt><dd><?= $usuariosPorRol['admin'] ?></dd></div>
                <div class="dato"><dt>Autores</dt><dd><?= $usuariosPorRol['autor'] ?></dd></div>
                <div class="dato"><dt>Lectores</dt><dd><?= $usuariosPorRol['lector'] ?></dd></div>
            <?php endif; ?>
        </dl>

        <div class="tabla-contenedor" role="region" aria-labelledby="cap-recientes" tabindex="0">
            <table class="tabla">
                <caption id="cap-recientes"><?= $esAdmin ? 'Últimas noticias creadas' : 'Tus últimas noticias' ?></caption>
                <thead>
                    <tr><th scope="col">Título</th><th scope="col">Estado</th><th scope="col">Fecha de creación</th></tr>
                </thead>
                <tbody>
                <?php if ($recientes === []): ?>
                    <tr><td colspan="3">Aún no hay noticias. <a href="<?= e(url('admin/noticia_form.php')) ?>">Crea la primera</a>.</td></tr>
                <?php endif; ?>
                <?php foreach ($recientes as $n): ?>
                    <tr>
                        <th scope="row"><a href="<?= e(url('admin/noticia_form.php?id=' . (int) $n['id'])) ?>"><?= e($n['titulo']) ?></a></th>
                        <td><span class="estado estado--<?= e($n['estado']) ?>"><?= $n['estado'] === 'publicada' ? 'Publicada' : 'Borrador' ?></span></td>
                        <td><?= e(Formateador::fecha($n['creado_en'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tabla-contenedor" role="region" aria-labelledby="cap-leidas" tabindex="0">
            <table class="tabla">
                <caption id="cap-leidas">Noticias más leídas del sitio</caption>
                <thead>
                    <tr><th scope="col">Título</th><th scope="col">Lecturas</th></tr>
                </thead>
                <tbody>
                <?php if ($masLeidas === []): ?>
                    <tr><td colspan="2">Todavía no hay lecturas registradas.</td></tr>
                <?php endif; ?>
                <?php foreach ($masLeidas as $m): ?>
                    <tr>
                        <th scope="row"><a href="<?= e(url('noticia.php?slug=' . rawurlencode((string) $m['slug']))) ?>"><?= e($m['titulo']) ?></a></th>
                        <td><?= (int) $m['vistas'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require QUIPU_ROOT . '/templates/pie.php'; ?>
