<?php
/**
 * Cierre de sesión. Solo por POST con token CSRF (un enlace GET podría ser
 * activado por una imagen o un sitio externo para cerrarte la sesión).
 */
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Quipu\Core\Auth;
use Quipu\Core\Csrf;
use Quipu\Core\Session;

if (!es_post()) {
    redirigir(url('index.php'));
}

Csrf::exigir();
Auth::cerrar();

// La sesión anterior se destruyó: se abre una nueva solo para mostrar el aviso.
Session::iniciar();
Csrf::renovar();
mensaje_flash('info', 'Cerraste sesión correctamente.');
redirigir(url('index.php'));
