<?php
/**
 * Detalle de una noticia + comentarios (lectura y publicación de comentarios).
 */
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Quipu\Core\Auth;
use Quipu\Core\Csrf;
use Quipu\Core\Database;
use Quipu\Core\Permisos;
use Quipu\Repository\ComentarioRepository;
use Quipu\Repository\NoticiaRepository;
use Quipu\Support\Formateador;
use Quipu\Support\SubidaImagen;
use Quipu\Support\TiempoLectura;
use Quipu\Support\Validador;

$pdo = Database::conexion();
$noticias = new NoticiaRepository($pdo);
$comentarios = new ComentarioRepository($pdo);
$usuario = usuario_actual();

$slug = texto_get('slug');
$noticia = $slug !== '' ? $noticias->porSlug($slug, false) : null;
if ($noticia === null) {
    abortar(404, 'Noticia no encontrada', 'La noticia que buscas no existe, cambió de dirección o todavía no fue publicada.');
}

$esBorrador = $noticia['estado'] !== 'publicada';
// Un borrador solo lo ve quien puede editarlo; para el resto "no existe" (404, no 403).
if ($esBorrador && !Permisos::puedeEditarNoticia($usuario, $noticia)) {
    abortar(404, 'Noticia no encontrada', 'La noticia que buscas no existe, cambió de dirección o todavía no fue publicada.');
}

$idNoticia = (int) $noticia['id'];
$urlNoticia = url('noticia.php?slug=' . rawurlencode((string) $noticia['slug']));

// ------------------------------------------------------------------ publicar comentario
$errores = [];
if (es_post()) {
    Csrf::exigir();
    if ($usuario === null) {
        Auth::requerirLogin();
    }
    if (!Permisos::puedeComentar($usuario) || $esBorrador) {
        abortar(403, 'No puedes comentar', 'Esta noticia no admite comentarios con tu cuenta.');
    }

    $texto = valor('comentario');
    $error = Validador::comentario($texto);
    if ($error === null) {
        $espera = $comentarios->segundosDeEspera((int) $usuario['id']);
        if ($espera > 0) {
            $error = sprintf('Espera %d segundos antes de publicar otro comentario.', $espera);
        }
    }
    if ($error !== null) {
        $errores['comentario'] = $error;
        http_response_code(422);
    } else {
        $comentarios->crear($idNoticia, (int) $usuario['id'], $texto);
        mensaje_flash('exito', 'Tu comentario fue publicado.');
        redirigir($urlNoticia . '#comentarios');
    }
}

// ------------------------------------------------------------------ contar lectura (una vez por sesión)
if (!es_post() && !$esBorrador && empty($_SESSION['vistas'][$idNoticia])) {
    $noticias->incrementarVistas($idNoticia);
    $_SESSION['vistas'][$idNoticia] = true;
}

$listaComentarios = $comentarios->visiblesDeNoticia($idNoticia);
$relacionadas = $esBorrador ? [] : $noticias->relacionadas((int) $noticia['categoria_id'], $idNoticia, 3);

$tituloPagina = (string) $noticia['titulo'];
$descripcionPagina = (string) $noticia['resumen'];
$seccion = 'inicio';
$retorno = ruta_interna_segura((string) ($_SERVER['REQUEST_URI'] ?? ''));
$sufijoRetorno = $retorno !== null ? '?next=' . rawurlencode($retorno) : '';

require QUIPU_ROOT . '/templates/cabecera.php';
?>
<article class="articulo" aria-labelledby="titulo-noticia">
    <?php if ($esBorrador): ?>
        <p class="aviso aviso--info" role="status"><strong>Vista previa:</strong> esta noticia es un borrador. Solo quienes pueden editarla la ven.</p>
    <?php endif; ?>

    <header>
        <p class="sin-margen">
            <a class="etiqueta" href="<?= e(url('index.php?categoria=' . rawurlencode((string) $noticia['categoria_slug']))) ?>"><?= e($noticia['categoria_nombre']) ?></a>
        </p>
        <h1 id="titulo-noticia"><?= e($noticia['titulo']) ?></h1>
        <p class="articulo__resumen"><?= e($noticia['resumen']) ?></p>
        <p class="articulo__meta">
            <span>Por <strong><?= e($noticia['autor_nombre']) ?></strong></span>
            <?php if (!empty($noticia['publicado_en'])): ?>
                <span>Publicado el <time datetime="<?= e(Formateador::fechaIso($noticia['publicado_en'])) ?>"><?= e(Formateador::fecha($noticia['publicado_en'])) ?></time></span>
            <?php endif; ?>
            <span><?= e(TiempoLectura::etiqueta((int) $noticia['minutos_lectura'])) ?></span>
        </p>
    </header>

    <?php if (!empty($noticia['imagen'])):
        $principal = (string) $noticia['imagen'];
        $anchoPrincipal = (int) $noticia['imagen_ancho'];
        [$anchoMini] = SubidaImagen::dimensionesMiniatura($anchoPrincipal, (int) $noticia['imagen_alto']);
        $srcset = url_subida(SubidaImagen::miniatura($principal)) . ' ' . $anchoMini . 'w';
        if ($anchoPrincipal > $anchoMini) {
            $srcset .= ', ' . url_subida($principal) . ' ' . $anchoPrincipal . 'w';
        }
        ?>
        <figure class="articulo__figura">
            <img src="<?= e(url_subida($principal)) ?>"
                 srcset="<?= e($srcset) ?>"
                 sizes="(min-width: 48em) 44rem, 100vw"
                 width="<?= $anchoPrincipal ?>" height="<?= (int) $noticia['imagen_alto'] ?>"
                 alt="<?= e($noticia['imagen_alt']) ?>"
                 fetchpriority="high" decoding="async">
        </figure>
    <?php endif; ?>

    <div class="articulo__cuerpo">
        <?= Formateador::cuerpo((string) $noticia['contenido']) /* ya escapado por Formateador */ ?>
    </div>
</article>

<section id="comentarios" class="comentarios" aria-labelledby="titulo-comentarios" tabindex="-1">
    <h2 id="titulo-comentarios">Comentarios (<?= count($listaComentarios) ?>)</h2>

    <?php if ($listaComentarios === []): ?>
        <p class="texto-suave">Todavía no hay comentarios. Puedes escribir el primero.</p>
    <?php else: ?>
        <ol class="comentarios__lista">
            <?php foreach ($listaComentarios as $c): ?>
                <li class="comentario">
                    <p class="comentario__cabecera">
                        <span class="comentario__autor"><?= e($c['autor_nombre']) ?></span>
                        <time datetime="<?= e(Formateador::fechaIso($c['creado_en'])) ?>"><?= e(Formateador::fecha($c['creado_en'])) ?></time>
                    </p>
                    <p class="comentario__texto"><?= e($c['contenido']) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <?php if ($esBorrador): ?>
        <p class="aviso aviso--info"><strong>Aviso:</strong> los comentarios se habilitan cuando la noticia se publica.</p>
    <?php elseif (Permisos::puedeComentar($usuario)): ?>
        <h3 id="titulo-formulario-comentario">Deja tu comentario</h3>
        <form class="formulario" method="post" action="<?= e($urlNoticia) ?>#comentarios" aria-labelledby="titulo-formulario-comentario">
            <?= Csrf::campo() ?>
            <?php if ($errores !== []): ?>
                <div class="aviso aviso--error" role="alert" tabindex="-1" data-resumen-errores>
                    <strong>Error:</strong> no se pudo publicar el comentario.
                    <ul><li><a href="#comentario"><?= e($errores['comentario']) ?></a></li></ul>
                </div>
            <?php endif; ?>
            <div class="campo">
                <label class="campo__etiqueta" for="comentario">Tu comentario <span class="texto-suave">(obligatorio)</span></label>
                <p class="campo__ayuda" id="comentario-ayuda">Sé respetuoso. No publiques datos personales de otras personas.</p>
                <textarea class="entrada" id="comentario" name="comentario" rows="5" required
                          maxlength="<?= Validador::COMENTARIO_MAX ?>" data-maximo="<?= Validador::COMENTARIO_MAX ?>"
                          <?= isset($errores['comentario']) ? 'aria-invalid="true"' : '' ?>
                          aria-describedby="comentario-ayuda comentario-contador<?= isset($errores['comentario']) ? ' comentario-error' : '' ?>"><?= e(valor('comentario')) ?></textarea>
                <p class="campo__contador" id="comentario-contador">Máximo <?= Validador::COMENTARIO_MAX ?> caracteres.</p>
                <p class="solo-lector" id="comentario-aviso" role="status" aria-live="polite"></p>
                <?php if (isset($errores['comentario'])): ?>
                    <p class="campo__error" id="comentario-error"><strong>Error:</strong> <?= e($errores['comentario']) ?></p>
                <?php endif; ?>
            </div>
            <button class="boton" type="submit">Publicar comentario</button>
        </form>
    <?php else: ?>
        <p class="aviso aviso--info">
            <strong>Aviso:</strong>
            <a href="<?= e(url('login.php') . $sufijoRetorno) ?>">Inicia sesión</a> o
            <a href="<?= e(url('registro.php')) ?>">crea una cuenta</a> para comentar.
        </p>
    <?php endif; ?>
</section>

<?php if ($relacionadas !== []): ?>
    <section class="relacionadas" aria-labelledby="titulo-relacionadas">
        <h2 id="titulo-relacionadas">Más en <?= e($noticia['categoria_nombre']) ?></h2>
        <ul class="rejilla">
            <?php foreach ($relacionadas as $n):
                $prioridad = false;
                $destacada = false;
                ?>
                <li><?php require QUIPU_ROOT . '/templates/parciales/tarjeta_noticia.php'; ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
<?php require QUIPU_ROOT . '/templates/pie.php'; ?>
