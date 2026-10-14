<?php
/**
 * Autocarga mínima (PSR-4) para el espacio de nombres Quipu\.
 *
 * La aplicación funciona sin Composer. Composer solo es necesario para
 * instalar PHPUnit (composer install) y ejecutar las pruebas.
 */
declare(strict_types=1);

if (!defined('QUIPU_ROOT')) {
    define('QUIPU_ROOT', dirname(__DIR__));
}

spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Quipu\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }
    $relativa = substr($clase, strlen($prefijo));
    $archivo = QUIPU_ROOT . '/src/' . str_replace('\\', '/', $relativa) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});

require_once __DIR__ . '/helpers.php';
