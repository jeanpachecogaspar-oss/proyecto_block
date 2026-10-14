<?php
declare(strict_types=1);

// Arranque de PHPUnit: autocarga propia + funciones auxiliares. No inicia sesión ni envía cabeceras.
require_once dirname(__DIR__) . '/src/autoload.php';

if (is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// Las pruebas usan las constantes y el entorno por defecto, no el .env real.
\Quipu\Core\Config::cargar(sys_get_temp_dir() . '/quipu-sin-env');
date_default_timezone_set('America/Lima');
