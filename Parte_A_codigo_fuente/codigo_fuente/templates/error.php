<?php
/**
 * Página de error accesible. La usa la función abortar().
 * Variables: $codigoError, $tituloPagina, $mensajeError.
 */
declare(strict_types=1);

$seccion = '';
require __DIR__ . '/cabecera.php';
?>
<section class="encabezado-pagina" aria-labelledby="titulo-error">
    <p class="texto-suave sin-margen">Error <?= (int) $codigoError ?></p>
    <h1 id="titulo-error"><?= e($tituloPagina) ?></h1>
    <p><?= e($mensajeError) ?></p>
    <p class="acciones">
        <a class="boton" href="<?= e(url('index.php')) ?>">Ir a la portada</a>
        <a class="boton boton--secundario" href="<?= e(url('buscar.php')) ?>">Buscar noticias</a>
    </p>
</section>
<?php require __DIR__ . '/pie.php'; ?>
