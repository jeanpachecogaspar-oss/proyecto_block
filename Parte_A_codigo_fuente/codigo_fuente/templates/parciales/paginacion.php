<?php
/**
 * Navegación de páginas accesible. Variable: $pag (Quipu\Support\Paginador).
 * Usa <nav> con nombre, lista, aria-current="page" y estados deshabilitados
 * indicados con texto (no solo con color).
 */
declare(strict_types=1);

if ($pag->totalPaginas <= 1) {
    return;
}
?>
<nav class="paginacion" aria-label="Paginación">
    <p class="paginacion__estado">Mostrando <?= $pag->desde() ?> a <?= $pag->hasta() ?> de <?= $pag->total ?> resultados · Página <?= $pag->pagina ?> de <?= $pag->totalPaginas ?></p>
    <ul class="paginacion__lista">
        <li>
            <?php if ($pag->hayAnterior()): ?>
                <a class="paginacion__enlace" rel="prev" href="<?= e(url_pagina($pag->pagina - 1)) ?>">Anterior</a>
            <?php else: ?>
                <span class="paginacion__enlace" aria-disabled="true">Anterior</span>
            <?php endif; ?>
        </li>
        <?php foreach ($pag->ventana() as $numero): ?>
            <li>
                <a class="paginacion__enlace" href="<?= e(url_pagina($numero)) ?>"
                   <?= $numero === $pag->pagina ? 'aria-current="page"' : '' ?>
                   aria-label="Página <?= $numero ?>"><?= $numero ?></a>
            </li>
        <?php endforeach; ?>
        <li>
            <?php if ($pag->haySiguiente()): ?>
                <a class="paginacion__enlace" rel="next" href="<?= e(url_pagina($pag->pagina + 1)) ?>">Siguiente</a>
            <?php else: ?>
                <span class="paginacion__enlace" aria-disabled="true">Siguiente</span>
            <?php endif; ?>
        </li>
    </ul>
</nav>
