<?php
/**
 * Tarjeta de noticia para listados.
 *
 * Variables: $n (fila del listado), $prioridad (bool, primera imagen visible de la página),
 *            $destacada (bool, tarjeta ancha de portada).
 *
 * Accesibilidad: la imagen de la tarjeta es redundante con el título (que ya es un
 * enlace a la misma noticia), por eso lleva alt="" y su enlace queda fuera del
 * orden de tabulación: el lector de pantalla anuncia un solo enlace por noticia.
 * La versión con descripción (alt) se muestra en la página completa de la noticia.
 *
 * Eficiencia: miniatura WebP + srcset, width/height para reservar espacio,
 * carga diferida (lazy) salvo la primera imagen, que se pide con prioridad (LCP).
 */
declare(strict_types=1);

use Quipu\Support\Formateador;
use Quipu\Support\SubidaImagen;
use Quipu\Support\TiempoLectura;

$prioridad = $prioridad ?? false;
$destacada = $destacada ?? false;
$enlace = url('noticia.php?slug=' . rawurlencode((string) $n['slug']));
?>
<article class="tarjeta<?= $destacada ? ' tarjeta--destacada' : '' ?>">
    <?php if (!empty($n['imagen'])):
        $miniatura = SubidaImagen::miniatura((string) $n['imagen']);
        [$anchoMini, $altoMini] = SubidaImagen::dimensionesMiniatura((int) $n['imagen_ancho'], (int) $n['imagen_alto']);
        $srcset = url_subida($miniatura) . ' ' . $anchoMini . 'w';
        if ((int) $n['imagen_ancho'] > $anchoMini) {
            $srcset .= ', ' . url_subida((string) $n['imagen']) . ' ' . (int) $n['imagen_ancho'] . 'w';
        }
        ?>
        <a class="tarjeta__enlace-imagen" href="<?= e($enlace) ?>" tabindex="-1" aria-hidden="true">
            <img class="tarjeta__imagen"
                 src="<?= e(url_subida($miniatura)) ?>"
                 srcset="<?= e($srcset) ?>"
                 sizes="(min-width: 62em) 22rem, (min-width: 48em) 45vw, 100vw"
                 width="<?= $anchoMini ?>" height="<?= $altoMini ?>"
                 alt=""
                 <?= $prioridad ? 'fetchpriority="high"' : 'loading="lazy"' ?>
                 decoding="async">
        </a>
    <?php endif; ?>
    <div class="tarjeta__cuerpo">
        <p class="sin-margen">
            <a class="etiqueta" href="<?= e(url('index.php?categoria=' . rawurlencode((string) $n['categoria_slug']))) ?>"><?= e($n['categoria_nombre']) ?></a>
            <?php if (($n['estado'] ?? 'publicada') === 'borrador'): ?>
                <span class="estado estado--borrador">Borrador</span>
            <?php endif; ?>
        </p>
        <h2 class="tarjeta__titulo"><a href="<?= e($enlace) ?>"><?= e($n['titulo']) ?></a></h2>
        <p class="tarjeta__resumen"><?= e($n['resumen']) ?></p>
        <p class="tarjeta__meta">
            Por <?= e($n['autor_nombre']) ?> ·
            <time datetime="<?= e(Formateador::fechaIso($n['publicado_en'] ?? $n['creado_en'])) ?>"><?= e(Formateador::fecha($n['publicado_en'] ?? $n['creado_en'])) ?></time> ·
            <?= e(TiempoLectura::etiqueta((int) $n['minutos_lectura'])) ?>
        </p>
    </div>
</article>
