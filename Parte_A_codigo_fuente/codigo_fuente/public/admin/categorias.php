<?php
/**
 * Panel (solo administrador): crear, editar y eliminar categorías.
 * Una categoría con noticias no se puede eliminar (se protege la integridad de los datos).
 */
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

use Quipu\Core\Auth;
use Quipu\Core\Csrf;
use Quipu\Core\Database;
use Quipu\Repository\CategoriaRepository;
use Quipu\Support\Validador;

$usuario = Auth::requerirRol(['admin']);
$repo = new CategoriaRepository(Database::conexion());

$errores = [];
$editarId = entero_param('editar');
$enEdicion = $editarId > 0 ? $repo->porId($editarId) : null;
if ($editarId > 0 && $enEdicion === null) {
    abortar(404, 'Categoría no encontrada', 'La categoría que intentas editar no existe.');
}
$datos = [
    'nombre' => $enEdicion['nombre'] ?? '',
    'descripcion' => $enEdicion['descripcion'] ?? '',
];

if (es_post()) {
    Csrf::exigir();
    $accion = valor('accion');
    $idPost = entero_param('id', 'POST');

    if ($accion === 'eliminar') {
        $cat = $repo->porId($idPost);
        if ($cat === null) {
            mensaje_flash('error', 'La categoría ya no existe.');
        } elseif ($repo->contarNoticias($idPost) > 0) {
            mensaje_flash('error', 'No se puede eliminar «' . $cat['nombre'] . '» porque tiene noticias. Mueve las noticias a otra categoría primero.');
        } else {
            $repo->eliminar($idPost);
            mensaje_flash('exito', 'La categoría «' . $cat['nombre'] . '» fue eliminada.');
        }
        redirigir(url('admin/categorias.php'));
    }

    if ($accion === 'crear' || $accion === 'actualizar') {
        $datos['nombre'] = trim(valor('nombre'));
        $datos['descripcion'] = trim(valor('descripcion'));
        $ignorar = $accion === 'actualizar' ? $idPost : null;

        if (($e = Validador::nombreCategoria($datos['nombre'])) !== null) {
            $errores['nombre'] = $e;
        } elseif ($repo->existeNombre($datos['nombre'], $ignorar)) {
            $errores['nombre'] = 'Ya existe una categoría con ese nombre.';
        }
        if (mb_strlen($datos['descripcion']) > 200) {
            $errores['descripcion'] = 'La descripción admite hasta 200 caracteres.';
        }
        if ($accion === 'actualizar' && $repo->porId($idPost) === null) {
            abortar(404, 'Categoría no encontrada', 'La categoría que intentas editar no existe.');
        }

        if ($errores === []) {
            if ($accion === 'crear') {
                $repo->crear($datos['nombre'], $datos['descripcion']);
                mensaje_flash('exito', 'La categoría «' . $datos['nombre'] . '» fue creada.');
            } else {
                $repo->actualizar($idPost, $datos['nombre'], $datos['descripcion']);
                mensaje_flash('exito', 'La categoría fue actualizada.');
            }
            redirigir(url('admin/categorias.php'));
        }
        $enEdicion = $accion === 'actualizar' ? $repo->porId($idPost) : null;
        http_response_code(422);
    }
}

$lista = $repo->conConteo();
$tituloPagina = 'Panel · Categorías';
$seccion = 'panel';
$seccionAdmin = 'categorias';
require QUIPU_ROOT . '/templates/cabecera.php';
?>
<div class="panel">
    <?php require QUIPU_ROOT . '/templates/admin_nav.php'; ?>

    <div class="panel__contenido">
        <h1>Categorías</h1>

        <div class="tabla-contenedor" role="region" aria-labelledby="cap-categorias" tabindex="0">
            <table class="tabla">
                <caption id="cap-categorias">Categorías del sitio (<?= count($lista) ?>)</caption>
                <thead>
                    <tr><th scope="col">Nombre</th><th scope="col">Descripción</th><th scope="col">Noticias publicadas</th><th scope="col">Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($lista as $c): ?>
                    <tr>
                        <th scope="row"><?= e($c['nombre']) ?></th>
                        <td><?= e($c['descripcion']) ?></td>
                        <td><?= (int) $c['total'] ?></td>
                        <td>
                            <div class="acciones">
                                <a class="boton boton--secundario boton--chico" href="<?= e(url('admin/categorias.php?editar=' . (int) $c['id'])) ?>#nombre"
                                   aria-label="Editar la categoría: <?= e($c['nombre']) ?>">Editar</a>
                                <form method="post" action="<?= e(url('admin/categorias.php')) ?>"
                                      data-confirmar="¿Eliminar la categoría «<?= e($c['nombre']) ?>»?">
                                    <?= Csrf::campo() ?>
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button class="boton boton--peligro boton--chico" type="submit"
                                            aria-label="Eliminar la categoría: <?= e($c['nombre']) ?>">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 id="titulo-form-categoria"><?= $enEdicion !== null ? 'Editar categoría' : 'Nueva categoría' ?></h2>
        <form class="formulario" method="post" action="<?= e(url('admin/categorias.php') . ($enEdicion !== null ? '?editar=' . (int) $enEdicion['id'] : '')) ?>"
              aria-labelledby="titulo-form-categoria">
            <?= Csrf::campo() ?>
            <input type="hidden" name="accion" value="<?= $enEdicion !== null ? 'actualizar' : 'crear' ?>">
            <?php if ($enEdicion !== null): ?>
                <input type="hidden" name="id" value="<?= (int) $enEdicion['id'] ?>">
            <?php endif; ?>
            <?php $general = null; require QUIPU_ROOT . '/templates/parciales/resumen_errores.php'; ?>

            <div class="campo">
                <label class="campo__etiqueta" for="nombre">Nombre <span class="texto-suave">(obligatorio)</span></label>
                <p class="campo__ayuda" id="nombre-ayuda">Entre <?= Validador::CATEGORIA_MIN ?> y <?= Validador::CATEGORIA_MAX ?> caracteres.</p>
                <input class="entrada" type="text" id="nombre" name="nombre" value="<?= e($datos['nombre']) ?>" required
                       maxlength="<?= Validador::CATEGORIA_MAX ?>" <?= attr_campo('nombre', $errores, 'nombre-ayuda') ?>>
                <?= error_campo('nombre', $errores) ?>
            </div>
            <div class="campo">
                <label class="campo__etiqueta" for="descripcion">Descripción (opcional)</label>
                <input class="entrada" type="text" id="descripcion" name="descripcion" value="<?= e($datos['descripcion']) ?>"
                       maxlength="200" <?= attr_campo('descripcion', $errores) ?>>
                <?= error_campo('descripcion', $errores) ?>
            </div>
            <div class="acciones">
                <button class="boton" type="submit"><?= $enEdicion !== null ? 'Guardar cambios' : 'Crear categoría' ?></button>
                <?php if ($enEdicion !== null): ?>
                    <a class="boton boton--secundario" href="<?= e(url('admin/categorias.php')) ?>">Cancelar edición</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?php require QUIPU_ROOT . '/templates/pie.php'; ?>
