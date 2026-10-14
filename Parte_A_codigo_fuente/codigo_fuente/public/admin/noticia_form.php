<?php
/**
 * Panel: crear o editar una noticia (con imagen y categoría).
 *
 * - Crear: sin parámetro.  Editar: ?id=NN
 * - El autor solo edita lo suyo; el administrador edita todo (Permisos).
 * - La imagen se valida por contenido, se recodifica y se guarda en WebP.
 * - La descripción alternativa de la imagen es OBLIGATORIA (accesibilidad).
 */
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

use Quipu\Core\Auth;
use Quipu\Core\Csrf;
use Quipu\Core\Database;
use Quipu\Core\Permisos;
use Quipu\Repository\CategoriaRepository;
use Quipu\Repository\NoticiaRepository;
use Quipu\Support\SubidaImagen;
use Quipu\Support\Validador;

$usuario = Auth::requerirRol(['admin', 'autor']);
$pdo = Database::conexion();
$noticias = new NoticiaRepository($pdo);
$categorias = (new CategoriaRepository($pdo))->todas();

$id = entero_param('id');
$noticia = null;
if ($id > 0) {
    $noticia = $noticias->porId($id);
    if ($noticia === null) {
        abortar(404, 'Noticia no encontrada', 'La noticia que intentas editar no existe.');
    }
    if (!Permisos::puedeEditarNoticia($usuario, $noticia)) {
        abortar(403, 'Acceso restringido', 'Solo puedes editar tus propias noticias.');
    }
}
$esEdicion = $noticia !== null;

// Valores iniciales del formulario (de la base de datos o de lo enviado).
$datos = [
    'titulo' => $noticia['titulo'] ?? '',
    'resumen' => $noticia['resumen'] ?? '',
    'contenido' => $noticia['contenido'] ?? '',
    'categoria_id' => (string) ($noticia['categoria_id'] ?? ''),
    'estado' => $noticia['estado'] ?? 'borrador',
    'imagen_alt' => $noticia['imagen_alt'] ?? '',
];
$errores = [];

// Si el archivo supera post_max_size, PHP descarta todo el formulario ($_POST vacío).
if (es_post() && $_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    mensaje_flash('error', 'El archivo enviado supera el tamaño máximo permitido por el servidor. Sube una imagen de hasta 2 MB.');
    redirigir(url('admin/noticia_form.php') . ($id > 0 ? '?id=' . $id : ''));
}

if (es_post()) {
    Csrf::exigir();

    foreach (['titulo', 'resumen', 'contenido', 'categoria_id', 'estado', 'imagen_alt'] as $campo) {
        $datos[$campo] = valor($campo);
    }
    $datos['titulo'] = trim($datos['titulo']);
    $datos['resumen'] = trim($datos['resumen']);
    $datos['contenido'] = trim($datos['contenido']);
    $datos['imagen_alt'] = trim($datos['imagen_alt']);
    $quitarImagen = valor('quitar_imagen') === '1';

    if (($e = Validador::titulo($datos['titulo'])) !== null) {
        $errores['titulo'] = $e;
    }
    if (($e = Validador::resumen($datos['resumen'])) !== null) {
        $errores['resumen'] = $e;
    }
    if (($e = Validador::contenido($datos['contenido'])) !== null) {
        $errores['contenido'] = $e;
    }
    $idsCategoria = array_map(static fn (array $c): string => (string) $c['id'], $categorias);
    if (!in_array($datos['categoria_id'], $idsCategoria, true)) {
        $errores['categoria_id'] = 'Elige una categoría de la lista.';
    }
    if (!in_array($datos['estado'], ['borrador', 'publicada'], true)) {
        $errores['estado'] = 'Elige si la noticia es un borrador o está publicada.';
    }

    // Imagen: nueva, conservada o quitada.
    $archivo = $_FILES['imagen'] ?? null;
    $hayNueva = SubidaImagen::hayArchivo($archivo);
    if ($hayNueva && ($e = SubidaImagen::validar($archivo)) !== null) {
        $errores['imagen'] = $e;
    }
    $tendraImagen = $hayNueva || ($esEdicion && !empty($noticia['imagen']) && !$quitarImagen);
    if ($tendraImagen && ($e = Validador::textoAlternativo($datos['imagen_alt'])) !== null) {
        $errores['imagen_alt'] = $e;
    }

    if ($errores === []) {
        $subidor = new SubidaImagen(QUIPU_ROOT . '/public/uploads');
        $imagen = $esEdicion ? $noticia['imagen'] : null;
        $ancho = $esEdicion ? $noticia['imagen_ancho'] : null;
        $alto = $esEdicion ? $noticia['imagen_alto'] : null;
        $imagenAnterior = null;

        if ($quitarImagen && $imagen !== null && !$hayNueva) {
            $imagenAnterior = $imagen;
            $imagen = $ancho = $alto = null;
        }
        if ($hayNueva) {
            try {
                $resultado = $subidor->guardar($archivo);
                $imagenAnterior = $imagen;
                $imagen = $resultado['archivo'];
                $ancho = $resultado['ancho'];
                $alto = $resultado['alto'];
            } catch (RuntimeException $ex) {
                $errores['imagen'] = $ex->getMessage();
            }
        }
    }

    if ($errores === []) {
        $campos = [
            'categoria_id' => (int) $datos['categoria_id'],
            'titulo' => $datos['titulo'],
            'resumen' => $datos['resumen'],
            'contenido' => $datos['contenido'],
            'estado' => $datos['estado'],
            'imagen' => $imagen,
            'imagen_alt' => $imagen !== null ? $datos['imagen_alt'] : null,
            'imagen_ancho' => $ancho !== null ? (int) $ancho : null,
            'imagen_alto' => $alto !== null ? (int) $alto : null,
        ];
        if ($esEdicion) {
            $noticias->actualizar((int) $noticia['id'], $campos);
        } else {
            $noticias->crear($campos + ['autor_id' => (int) $usuario['id']]);
        }
        // La imagen vieja se borra DESPUÉS de guardar en la base: si algo falla, no se pierde.
        $subidor->eliminar($imagenAnterior);

        mensaje_flash('exito', $esEdicion ? 'Los cambios de la noticia se guardaron.' : 'La noticia se creó correctamente.');
        redirigir(url('admin/noticias.php'));
    }
    http_response_code(422);
}

$tituloPagina = $esEdicion ? 'Panel · Editar noticia' : 'Panel · Nueva noticia';
$seccion = 'panel';
$seccionAdmin = $esEdicion ? 'noticias' : 'nueva';
$accion = url('admin/noticia_form.php') . ($esEdicion ? '?id=' . (int) $noticia['id'] : '');
require QUIPU_ROOT . '/templates/cabecera.php';
?>
<div class="panel">
    <?php require QUIPU_ROOT . '/templates/admin_nav.php'; ?>

    <div class="panel__contenido">
        <h1><?= $esEdicion ? 'Editar noticia' : 'Nueva noticia' ?></h1>
        <p class="texto-suave">Los campos marcados como obligatorios deben completarse para guardar.</p>

        <form class="formulario" method="post" action="<?= e($accion) ?>" enctype="multipart/form-data"
              aria-label="<?= $esEdicion ? 'Formulario de edición de noticia' : 'Formulario de nueva noticia' ?>">
            <?= Csrf::campo() ?>
            <?php $general = null; require QUIPU_ROOT . '/templates/parciales/resumen_errores.php'; ?>

            <div class="campo">
                <label class="campo__etiqueta" for="titulo">Título <span class="texto-suave">(obligatorio)</span></label>
                <p class="campo__ayuda" id="titulo-ayuda">Entre <?= Validador::TITULO_MIN ?> y <?= Validador::TITULO_MAX ?> caracteres. Que se entienda sin leer el resto.</p>
                <input class="entrada" type="text" id="titulo" name="titulo" value="<?= e($datos['titulo']) ?>" required
                       maxlength="<?= Validador::TITULO_MAX ?>" <?= attr_campo('titulo', $errores, 'titulo-ayuda') ?>>
                <?= error_campo('titulo', $errores) ?>
            </div>

            <div class="campo">
                <label class="campo__etiqueta" for="categoria_id">Categoría <span class="texto-suave">(obligatorio)</span></label>
                <select class="entrada" id="categoria_id" name="categoria_id" required <?= attr_campo('categoria_id', $errores) ?>>
                    <option value="">Selecciona una categoría</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"<?= $datos['categoria_id'] === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= error_campo('categoria_id', $errores) ?>
            </div>

            <div class="campo">
                <label class="campo__etiqueta" for="resumen">Resumen <span class="texto-suave">(obligatorio)</span></label>
                <p class="campo__ayuda" id="resumen-ayuda">Una o dos frases para el listado y los buscadores. Entre <?= Validador::RESUMEN_MIN ?> y <?= Validador::RESUMEN_MAX ?> caracteres.</p>
                <textarea class="entrada" id="resumen" name="resumen" rows="3" required maxlength="<?= Validador::RESUMEN_MAX ?>"
                          <?= attr_campo('resumen', $errores, 'resumen-ayuda') ?>><?= e($datos['resumen']) ?></textarea>
                <?= error_campo('resumen', $errores) ?>
            </div>

            <div class="campo">
                <label class="campo__etiqueta" for="contenido">Contenido <span class="texto-suave">(obligatorio)</span></label>
                <p class="campo__ayuda" id="contenido-ayuda">Separa los párrafos con una línea en blanco. Empieza una línea con «## » para un subtítulo y con «- » para una lista. No se admite HTML. Entre <?= Validador::CONTENIDO_MIN ?> y <?= Validador::CONTENIDO_MAX ?> caracteres.</p>
                <textarea class="entrada entrada--larga" id="contenido" name="contenido" rows="14" required
                          maxlength="<?= Validador::CONTENIDO_MAX ?>"
                          <?= attr_campo('contenido', $errores, 'contenido-ayuda') ?>><?= e($datos['contenido']) ?></textarea>
                <?= error_campo('contenido', $errores) ?>
            </div>

            <fieldset class="grupo">
                <legend>Imagen de portada (opcional)</legend>
                <?php if ($esEdicion && !empty($noticia['imagen'])): ?>
                    <figure>
                        <img src="<?= e(url_subida(SubidaImagen::miniatura((string) $noticia['imagen']))) ?>"
                             width="<?= (int) SubidaImagen::dimensionesMiniatura((int) $noticia['imagen_ancho'], (int) $noticia['imagen_alto'])[0] ?>"
                             height="<?= (int) SubidaImagen::dimensionesMiniatura((int) $noticia['imagen_ancho'], (int) $noticia['imagen_alto'])[1] ?>"
                             alt="Imagen actual: <?= e($noticia['imagen_alt']) ?>" loading="lazy">
                        <figcaption class="campo__ayuda">Imagen actual. Sube otra para reemplazarla.</figcaption>
                    </figure>
                    <div class="casilla">
                        <input type="checkbox" id="quitar_imagen" name="quitar_imagen" value="1"<?= valor('quitar_imagen') === '1' ? ' checked' : '' ?>>
                        <label for="quitar_imagen">Quitar la imagen actual</label>
                    </div>
                <?php endif; ?>

                <div class="campo">
                    <label class="campo__etiqueta" for="imagen">Archivo de imagen</label>
                    <p class="campo__ayuda" id="imagen-ayuda">JPG, PNG o WebP de hasta 2 MB. Se optimiza automáticamente.</p>
                    <input class="entrada" type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp"
                           <?= attr_campo('imagen', $errores, 'imagen-ayuda') ?>>
                    <?= error_campo('imagen', $errores) ?>
                </div>

                <div class="campo">
                    <label class="campo__etiqueta" for="imagen_alt">Descripción de la imagen <span class="texto-suave">(obligatoria si hay imagen)</span></label>
                    <p class="campo__ayuda" id="imagen_alt-ayuda">Describe qué se ve, para quienes no pueden verla (lector de pantalla). Entre <?= Validador::ALT_MIN ?> y <?= Validador::ALT_MAX ?> caracteres.</p>
                    <input class="entrada" type="text" id="imagen_alt" name="imagen_alt" value="<?= e($datos['imagen_alt']) ?>"
                           maxlength="<?= Validador::ALT_MAX ?>" <?= attr_campo('imagen_alt', $errores, 'imagen_alt-ayuda') ?>>
                    <?= error_campo('imagen_alt', $errores) ?>
                </div>
            </fieldset>

            <fieldset class="grupo">
                <legend>Estado de publicación <span class="texto-suave">(obligatorio)</span></legend>
                <div class="casilla">
                    <input type="radio" id="estado" name="estado" value="borrador"<?= $datos['estado'] === 'borrador' ? ' checked' : '' ?><?= attr_campo('estado', $errores) ?>>
                    <label for="estado">Borrador (solo visible para quien edita)</label>
                </div>
                <div class="casilla">
                    <input type="radio" id="estado-publicada" name="estado" value="publicada"<?= $datos['estado'] === 'publicada' ? ' checked' : '' ?>>
                    <label for="estado-publicada">Publicada (visible para todo el público)</label>
                </div>
                <?= error_campo('estado', $errores) ?>
            </fieldset>

            <div class="acciones">
                <button class="boton" type="submit"><?= $esEdicion ? 'Guardar cambios' : 'Crear noticia' ?></button>
                <a class="boton boton--secundario" href="<?= e(url('admin/noticias.php')) ?>">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php require QUIPU_ROOT . '/templates/pie.php'; ?>
