<?php
/**
 * Búsqueda de noticias por palabras (título, resumen o contenido) con paginación.
 */
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Quipu\Core\Config;
use Quipu\Core\Database;
use Quipu\Repository\NoticiaRepository;
use Quipu\Support\Paginador;
use Quipu\Support\Validador;

$noticias = new NoticiaRepository(Database::conexion());

$consulta = texto_get('q');
$consulta = mb_substr($consulta, 0, 200); // recorte defensivo antes de validar
$error = Validador::busqueda($consulta);
$hayBusqueda = $consulta !== '' && $error === null;

$lista = [];
$pag = new Paginador(0, Config::entero('PER_PAGE', 6), 1);
if ($hayBusqueda) {
    $total = $noticias->contarBusqueda($consulta);
    $pag = new Paginador($total, Config::entero('PER_PAGE', 6), entero_param('pagina'));
    $lista = $noticias->buscar($consulta, $pag->porPagina, $pag->offset);
}

$tituloPagina = $hayBusqueda ? 'Resultados para «' . $consulta . '»' : 'Buscar noticias';
$descripcionPagina = 'Busca noticias publicadas en Quipu Noticias por palabras del título, el resumen o el contenido.';
$seccion = 'buscar';

require QUIPU_ROOT . '/templates/cabecera.php';
?>
<div class="encabezado-pagina">
    <h1>Buscar noticias</h1>
    <p>Escribe una o varias palabras. Mostramos las noticias que contienen todas las palabras indicadas.</p>
</div>

<form class="formulario" role="search" action="<?= e(url('buscar.php')) ?>" method="get" aria-label="Búsqueda en el sitio">
    <div class="campo">
        <label class="campo__etiqueta" for="q">Palabras a buscar</label>
        <p class="campo__ayuda" id="q-ayuda">Entre <?= Validador::BUSQUEDA_MIN ?> y <?= Validador::BUSQUEDA_MAX ?> caracteres. Ejemplo: feria tejido.</p>
        <input class="entrada" type="search" id="q" name="q" value="<?= e($consulta) ?>"
               maxlength="<?= Validador::BUSQUEDA_MAX ?>" autocomplete="off"
               aria-describedby="q-ayuda<?= $error !== null ? ' q-error' : '' ?>"
               <?= $error !== null ? 'aria-invalid="true"' : '' ?>>
        <?php if ($error !== null): ?>
            <p class="campo__error" id="q-error"><strong>Error:</strong> <?= e($error) ?></p>
        <?php endif; ?>
    </div>
    <button class="boton" type="submit">Buscar</button>
</form>

<section aria-labelledby="titulo-resultados" class="resultados">
    <h2 id="titulo-resultados">Resultados</h2>
    <?php if (!$hayBusqueda): ?>
        <p class="texto-suave" role="status">Escribe algo en el cuadro de búsqueda para ver resultados.</p>
    <?php elseif ($lista === []): ?>
        <p class="aviso aviso--info" role="status">
            <strong>Aviso:</strong> no encontramos noticias para «<?= e($consulta) ?>». Prueba con otras palabras o revisa la ortografía.
        </p>
    <?php else: ?>
        <p role="status"><?= $pag->total ?> <?= $pag->total === 1 ? 'resultado' : 'resultados' ?> para «<?= e($consulta) ?>».</p>
        <ul class="rejilla">
            <?php foreach ($lista as $i => $n):
                $prioridad = $i === 0;
                $destacada = false;
                ?>
                <li><?php require QUIPU_ROOT . '/templates/parciales/tarjeta_noticia.php'; ?></li>
            <?php endforeach; ?>
        </ul>
        <?php require QUIPU_ROOT . '/templates/parciales/paginacion.php'; ?>
    <?php endif; ?>
</section>
<?php require QUIPU_ROOT . '/templates/pie.php'; ?>
