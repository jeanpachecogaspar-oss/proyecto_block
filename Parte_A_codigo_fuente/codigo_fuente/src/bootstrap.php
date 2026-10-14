<?php
/**
 * Arranque común de todas las páginas web (no se usa en las pruebas CLI).
 */
declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use Quipu\Core\Config;
use Quipu\Core\Session;

Config::cargar(QUIPU_ROOT);
date_default_timezone_set(Config::get('APP_TZ', 'America/Lima') ?: 'America/Lima');
mb_internal_encoding('UTF-8');

// ---------------------------------------------------------------------------
// Errores: en producción nunca se muestran detalles técnicos al visitante.
// ---------------------------------------------------------------------------
ini_set('display_errors', Config::esProduccion() ? '0' : '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e): void {
    error_log('[Quipu] ' . get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    if (headers_sent()) {
        return;
    }
    $detalle = Config::esProduccion()
        ? 'Ocurrió un problema inesperado. Inténtalo de nuevo en unos minutos.'
        : get_class($e) . ': ' . $e->getMessage();
    abortar(500, 'Algo salió mal', $detalle);
});

// ---------------------------------------------------------------------------
// Cabeceras de seguridad. La política CSP prohíbe scripts y estilos en línea,
// por eso todo el JS y CSS vive en archivos propios (public/assets).
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') {
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; script-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header('Cache-Control: no-cache, private');
    header_remove('X-Powered-By');
    if (Session::esHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

Session::iniciar();
