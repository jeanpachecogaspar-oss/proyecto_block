<?php
/**
 * Funciones auxiliares globales usadas por las plantillas y las páginas.
 */
declare(strict_types=1);

use Quipu\Core\Config;

/**
 * Escapa texto para imprimirlo dentro de HTML (defensa principal contra XSS).
 * Se usa en TODA salida que provenga de la base de datos o del usuario.
 */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Construye una URL interna respetando APP_BASE (por ejemplo /quipu/public). */
function url(string $ruta = ''): string
{
    $base = rtrim((string) Config::get('APP_BASE', ''), '/');
    return $base . '/' . ltrim($ruta, '/');
}

/** URL de un recurso estático con versión (fecha de modificación) para cachear sin riesgo. */
function asset(string $ruta): string
{
    $archivo = QUIPU_ROOT . '/public/assets/' . ltrim($ruta, '/');
    $version = is_file($archivo) ? (string) filemtime($archivo) : '1';
    return url('assets/' . ltrim($ruta, '/')) . '?v=' . $version;
}

/** URL pública de una imagen subida. */
function url_subida(string $archivo): string
{
    return url('uploads/' . rawurlencode($archivo));
}

/**
 * Usuario con sesión iniciada o null. Si la base de datos falla (por ejemplo,
 * mientras se muestra la página de error) devuelve null en lugar de lanzar otra excepción.
 *
 * @return array<string,mixed>|null
 */
function usuario_actual(): ?array
{
    try {
        return \Quipu\Core\Auth::usuario();
    } catch (\Throwable) {
        return null;
    }
}

function redirigir(string $ruta): never
{
    header('Location: ' . $ruta, true, 303);
    exit;
}

/**
 * Valida que una ruta de retorno ("next") sea interna al sitio.
 * Evita el ataque de redirección abierta (open redirect) tras el login.
 */
function ruta_interna_segura(?string $ruta): ?string
{
    if ($ruta === null || $ruta === '') {
        return null;
    }
    $base = rtrim((string) Config::get('APP_BASE', ''), '/') . '/';
    if (!str_starts_with($ruta, $base)) {
        return null;
    }
    // Rechaza "//host" (URL relativa al protocolo), esquemas (":"), barras
    // invertidas y caracteres de control, que algunos navegadores normalizan a "//".
    if (str_starts_with($ruta, '//') || preg_match('#\\\\|[\x00-\x1F]|:#', $ruta) === 1) {
        return null;
    }
    return $ruta;
}

function es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Agrega un mensaje que se mostrará una sola vez en la siguiente página. */
function mensaje_flash(string $tipo, string $texto): void
{
    $_SESSION['_flash'][] = ['tipo' => $tipo, 'texto' => $texto];
}

/** @return list<array{tipo:string,texto:string}> */
function mensajes_flash(): array
{
    $mensajes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $mensajes;
}

/** Valor de un campo enviado por POST, o el valor por defecto si no existe. */
function valor(string $campo, string $porDefecto = ''): string
{
    $v = $_POST[$campo] ?? null;
    return is_string($v) ? $v : $porDefecto;
}

/** Entero positivo recibido por GET o POST (0 si no es válido). */
function entero_param(string $campo, string $origen = 'GET'): int
{
    $fuente = $origen === 'POST' ? $_POST : $_GET;
    $v = $fuente[$campo] ?? '';
    return (is_string($v) && ctype_digit($v) && strlen($v) <= 10) ? (int) $v : 0;
}

/** Texto de un parámetro GET (recortado). */
function texto_get(string $campo): string
{
    $v = $_GET[$campo] ?? '';
    return is_string($v) ? trim($v) : '';
}

/**
 * Corta la ejecución y muestra una página de error accesible.
 */
function abortar(int $codigo, string $titulo, string $mensaje): never
{
    http_response_code($codigo);
    $tituloPagina = $titulo;
    $codigoError = $codigo;
    $mensajeError = $mensaje;
    require QUIPU_ROOT . '/templates/error.php';
    exit;
}

/** Construye un enlace de paginación conservando los demás parámetros de la URL. */
function url_pagina(int $pagina, string $parametro = 'pagina'): string
{
    $parametros = $_GET;
    $parametros[$parametro] = $pagina;
    if ($pagina <= 1) {
        unset($parametros[$parametro]);
    }
    $consulta = http_build_query($parametros);
    $script = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '';
    return $script . ($consulta !== '' ? '?' . $consulta : '');
}

/**
 * Atributos ARIA de un campo de formulario: marca aria-invalid cuando tiene error y
 * enlaza el campo con su texto de ayuda y con su mensaje de error (aria-describedby).
 *
 * @param array<string,string> $errores
 */
function attr_campo(string $campo, array $errores, string ...$ayudas): string
{
    $ids = $ayudas;
    $atributos = '';
    if (isset($errores[$campo])) {
        $ids[] = $campo . '-error';
        $atributos .= ' aria-invalid="true"';
    }
    if ($ids !== []) {
        $atributos .= ' aria-describedby="' . e(implode(' ', $ids)) . '"';
    }
    return $atributos;
}

/**
 * Mensaje de error junto al campo (texto con prefijo "Error:", no solo color).
 *
 * @param array<string,string> $errores
 */
function error_campo(string $campo, array $errores): string
{
    if (!isset($errores[$campo])) {
        return '';
    }
    return '<p class="campo__error" id="' . e($campo) . '-error"><strong>Error:</strong> ' . e($errores[$campo]) . '</p>';
}
