<?php
/**
 * Cabecera común (HTML semántico, enlace de salto, navegación, búsqueda y modo oscuro).
 *
 * Variables que puede definir la página antes de incluir esta plantilla:
 *   $tituloPagina       Título corto de la página (obligatorio).
 *   $descripcionPagina  Meta descripción (opcional).
 *   $seccion            'inicio' | 'buscar' | 'login' | 'registro' | 'panel' (para aria-current).
 */
declare(strict_types=1);

use Quipu\Core\Csrf;
use Quipu\Core\Permisos;

$usuarioNav = usuario_actual();
$tituloPagina = $tituloPagina ?? '';
$descripcionPagina = $descripcionPagina ?? 'Quipu Noticias: crónica de los valles y la sierra, contada con calma y con datos verificables.';
$seccion = $seccion ?? '';
$tituloCompleto = $tituloPagina !== '' ? $tituloPagina . ' · Quipu Noticias' : 'Quipu Noticias';
$actual = static fn (string $nombre): string => $seccion === $nombre ? ' aria-current="page"' : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= e($tituloCompleto) ?></title>
    <meta name="description" content="<?= e($descripcionPagina) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/estilo.css')) ?>">
    <script src="<?= e(asset('js/tema.js')) ?>"></script>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<a class="salto" href="#contenido">Saltar al contenido principal</a>

<header class="cabecera">
    <div class="contenedor cabecera__interior">
        <a class="marca" href="<?= e(url('index.php')) ?>">
            <svg class="marca__logo" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
                <path d="M10 14h44" stroke="currentColor" stroke-width="5" stroke-linecap="round" fill="none"/>
                <path d="M20 14v36M32 14v28M44 14v40" stroke="currentColor" stroke-width="4" stroke-linecap="round" fill="none"/>
                <circle cx="20" cy="26" r="5" fill="currentColor"/><circle cx="20" cy="38" r="5" fill="currentColor"/>
                <circle cx="32" cy="30" r="5" fill="currentColor"/>
                <circle cx="44" cy="24" r="5" fill="currentColor"/><circle cx="44" cy="36" r="5" fill="currentColor"/><circle cx="44" cy="48" r="5" fill="currentColor"/>
            </svg>
            <span>Quipu Noticias<span class="marca__sub">Crónica de los valles y la sierra</span></span>
        </a>

        <form class="buscador" role="search" action="<?= e(url('buscar.php')) ?>" method="get">
            <label class="solo-lector" for="q-cabecera">Buscar noticias</label>
            <input class="entrada" type="search" id="q-cabecera" name="q" maxlength="60" placeholder="Buscar noticias" autocomplete="off">
            <button class="boton" type="submit">Buscar</button>
        </form>

        <nav class="nav" aria-label="Principal">
            <ul class="nav__lista">
                <li><a class="nav__enlace" href="<?= e(url('index.php')) ?>"<?= $actual('inicio') ?>>Inicio</a></li>
                <?php if ($usuarioNav === null): ?>
                    <li><a class="nav__enlace" href="<?= e(url('login.php')) ?>"<?= $actual('login') ?>>Ingresar</a></li>
                    <li><a class="nav__enlace" href="<?= e(url('registro.php')) ?>"<?= $actual('registro') ?>>Crear cuenta</a></li>
                <?php else: ?>
                    <?php if (Permisos::puedeEntrarAlPanel($usuarioNav)): ?>
                        <li><a class="nav__enlace" href="<?= e(url('admin/index.php')) ?>"<?= $actual('panel') ?>>Panel</a></li>
                    <?php endif; ?>
                    <li class="nav__usuario">Sesión: <?= e($usuarioNav['nombre']) ?> (<?= e($usuarioNav['rol']) ?>)</li>
                    <li>
                        <form action="<?= e(url('logout.php')) ?>" method="post">
                            <?= Csrf::campo() ?>
                            <button class="boton boton--secundario boton--chico" type="submit">Cerrar sesión</button>
                        </form>
                    </li>
                <?php endif; ?>
                <li>
                    <button class="boton boton--secundario boton--chico interruptor" type="button" data-tema-interruptor aria-pressed="false" hidden>Modo oscuro</button>
                </li>
            </ul>
        </nav>
    </div>
</header>

<main id="contenido" class="contenedor principal" tabindex="-1">
<?php foreach (mensajes_flash() as $mensaje):
    $rol = $mensaje['tipo'] === 'error' ? 'alert' : 'status';
    $prefijo = match ($mensaje['tipo']) {
        'error' => 'Error:',
        'exito' => 'Listo:',
        default => 'Aviso:',
    };
    ?>
    <p class="aviso aviso--<?= e($mensaje['tipo']) ?>" role="<?= $rol ?>"><strong><?= e($prefijo) ?></strong> <?= e($mensaje['texto']) ?></p>
<?php endforeach; ?>
