<?php
/**
 * Resumen de errores al inicio de un formulario.
 * Variables: $errores (campo => mensaje), $general (mensaje opcional).
 * Recibe el foco al cargar la página (app.js) y cada error enlaza a su campo.
 */
declare(strict_types=1);

$errores = $errores ?? [];
$general = $general ?? null;
if ($errores === [] && $general === null) {
    return;
}
$cantidad = count($errores);
?>
<div class="aviso aviso--error" role="alert" tabindex="-1" data-resumen-errores>
    <p class="sin-margen"><strong>Error:</strong>
        <?= e($general ?? ($cantidad === 1 ? 'Hay 1 dato por corregir.' : 'Hay ' . $cantidad . ' datos por corregir.')) ?></p>
    <?php if ($errores !== []): ?>
        <ul>
            <?php foreach ($errores as $campo => $mensaje): ?>
                <li><a href="#<?= e((string) $campo) ?>"><?= e($mensaje) ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
