<?php
/**
 * Portada: últimas noticias con filtro por categoría y paginación.
 */
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Quipu\Core\Config;
use Quipu\Core\Database;
use Quipu\Repository\CategoriaRepository;
use Quipu\Repository\NoticiaRepository;
use Quipu\Support\Paginador;

$pdo = Database::conexion();
$noticias = new NoticiaRepository($pdo);
$categorias = new CategoriaRepository($pdo);

$categoriaActual = null;
$slug = texto_get('categoria');
if ($slug !== '') {
    $categoriaActual = $categorias->porSlug($slug);
    if ($categoriaActual === null) {
        abortar(404, 'Categoría no encontrada', 'Esa categoría no existe o fue eliminada.');
    }
}
$categoriaId = $categoriaActual !== null ? (int) $categoriaActual['id'] : null;

$total = $noticias->contarPublicadas($categoriaId);
$pag = new Paginador($total, Config::entero('PER_PAGE', 6), entero_param('pagina'));
$lista = $noticias->listarPublicadas($pag->porPagina, $pag->offset, $categoriaId);
$masLeidas = $noticias->masLeidas(5);
$listaCategorias = $categorias->conConteo();

$tituloPagina = $categoriaActual !== null ? 'Categoría: ' . $categoriaActual['nombre'] : 'Últimas noticias';
if ($pag->pagina > 1) {
    $tituloPagina .= ' (página ' . $pag->pagina . ')';
}
$descripcionPagina = $categoriaActual !== null && $categoriaActual['descripcion'] !== ''
    ? (string) $categoriaActual['descripcion']
    : 'Las últimas noticias de Quipu Noticias: comunidad, economía, cultura, deporte y ambiente en los valles y la sierra.';
$seccion = 'inicio';

require QUIPU_ROOT . '/templates/cabecera.php';
?>
<div class="encabezado-pagina">
    <h1><?= $categoriaActual !== null ? e($categoriaActual['nombre']) : 'Últimas noticias' ?></h1>
    <?php if ($categoriaActual !== null && $categoriaActual['descripcion'] !== ''): ?>
        <p><?= e($categoriaActual['descripcion']) ?></p>
    <?php else: ?>
        <p>Crónica de los valles y la sierra, con fuentes a la vista y sin titulares exagerados.</p>
    <?php endif; ?>
</div>

<nav class="filtros" aria-label="Categorías">
    <ul class="filtros__lista">
        <li><a class="filtro" href="<?= e(url('index.php')) ?>"<?= $categoriaActual === null ? ' aria-current="page"' : '' ?>>Todas</a></li>
        <?php foreach ($listaCategorias as $c): ?>
            <li>
                <a class="filtro" href="<?= e(url('index.php?categoria=' . rawurlencode((string) $c['slug']))) ?>"<?= (int) ($categoriaActual['id'] ?? 0) === (int) $c['id'] ? ' aria-current="page"' : '' ?>>
                    <?= e($c['nombre']) ?> <span class="solo-lector">(<?= (int) $c['total'] ?> noticias)</span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<div class="disposicion disposicion--con-lateral">
    <section aria-labelledby="titulo-listado">
        <h2 id="titulo-listado" class="solo-lector">Listado de noticias</h2>

        <?php if ($lista === []): ?>
            <p class="aviso aviso--info" role="status"><strong>Aviso:</strong> Todavía no hay noticias publicadas en esta sección.</p>
        <?php else: ?>
            <ul class="rejilla">
                <?php foreach ($lista as $i => $n):
                    $prioridad = $i === 0;
                    $destacada = $i === 0 && $pag->pagina === 1;
                    ?>
                    <li<?= $destacada ? ' class="rejilla__ancho"' : '' ?>><?php require QUIPU_ROOT . '/templates/parciales/tarjeta_noticia.php'; ?></li>
                <?php endforeach; ?>
            </ul>
            <?php require QUIPU_ROOT . '/templates/parciales/paginacion.php'; ?>
        <?php endif; ?>
    </section>

    <aside class="lateral" aria-label="Contenido complementario">
        <section class="lateral__bloque" aria-labelledby="titulo-mas-leidas">
            <h2 id="titulo-mas-leidas">Lo más leído</h2>
            <?php if ($masLeidas === []): ?>
                <p class="texto-suave sin-margen">Aún no hay lecturas registradas.</p>
            <?php else: ?>
                <ol class="lateral__lista">
                    <?php foreach ($masLeidas as $m): ?>
                        <li><a href="<?= e(url('noticia.php?slug=' . rawurlencode((string) $m['slug']))) ?>"><?= e($m['titulo']) ?></a>
                            <span class="texto-suave">(<?= (int) $m['vistas'] ?> lecturas)</span></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </aside>
</div>
<?php require QUIPU_ROOT . '/templates/pie.php'; ?>
