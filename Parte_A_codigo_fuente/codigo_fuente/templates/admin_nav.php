<?php
/**
 * Navegación lateral del panel. Variables: $usuario (usuario actual), $seccionAdmin.
 * Los enlaces solo de administrador no se muestran a los autores
 * (y aunque se escribiera la URL, cada página vuelve a comprobar el rol).
 */
declare(strict_types=1);

use Quipu\Core\Permisos;

$seccionAdmin = $seccionAdmin ?? '';
$marca = static fn (string $nombre): string => $seccionAdmin === $nombre ? ' aria-current="page"' : '';
?>
<nav class="panel__nav" aria-label="Panel de administración">
    <ul>
        <li><a class="panel__enlace" href="<?= e(url('admin/index.php')) ?>"<?= $marca('resumen') ?>>Resumen</a></li>
        <li><a class="panel__enlace" href="<?= e(url('admin/noticias.php')) ?>"<?= $marca('noticias') ?>>Noticias</a></li>
        <li><a class="panel__enlace" href="<?= e(url('admin/noticia_form.php')) ?>"<?= $marca('nueva') ?>>Nueva noticia</a></li>
        <?php if (Permisos::esAdmin($usuario)): ?>
            <li><a class="panel__enlace" href="<?= e(url('admin/categorias.php')) ?>"<?= $marca('categorias') ?>>Categorías</a></li>
            <li><a class="panel__enlace" href="<?= e(url('admin/comentarios.php')) ?>"<?= $marca('comentarios') ?>>Comentarios</a></li>
            <li><a class="panel__enlace" href="<?= e(url('admin/usuarios.php')) ?>"<?= $marca('usuarios') ?>>Usuarios</a></li>
        <?php endif; ?>
    </ul>
</nav>
