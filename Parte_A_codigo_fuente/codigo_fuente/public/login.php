<?php
/**
 * Inicio de sesión.
 *
 * Seguridad: CSRF, consulta preparada, password_verify, mensaje genérico (no revela
 * si el correo existe), tiempo de respuesta parecido exista o no el usuario,
 * límite de 5 intentos fallidos / 15 min, nuevo ID de sesión al entrar,
 * redirección posterior solo a rutas internas.
 */
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Quipu\Core\Auth;
use Quipu\Core\Csrf;
use Quipu\Core\Database;
use Quipu\Repository\LimitadorLogin;
use Quipu\Repository\UsuarioRepository;
use Quipu\Support\Validador;

if (usuario_actual() !== null) {
    redirigir(url('index.php'));
}

$errores = [];
$general = null;
$next = es_post() ? valor('next') : texto_get('next');
$email = '';

if (es_post()) {
    Csrf::exigir();
    $pdo = Database::conexion();
    $usuarios = new UsuarioRepository($pdo);
    $limitador = new LimitadorLogin($pdo);

    $email = trim(valor('email'));
    $password = valor('password');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    if ($limitador->bloqueado($email, $ip)) {
        http_response_code(429);
        $general = 'Hubo demasiados intentos fallidos. Espera 15 minutos antes de volver a intentarlo.';
    } else {
        if (($e = Validador::email($email)) !== null) {
            $errores['email'] = $e;
        }
        if ($password === '') {
            $errores['password'] = 'Escribe tu contraseña.';
        }

        if ($errores === []) {
            $usuario = $usuarios->porEmail($email);
            // Si el correo no existe se verifica contra un hash falso para que la
            // respuesta tarde lo mismo y no se pueda averiguar qué correos existen.
            $hash = $usuario['password_hash'] ?? password_hash('relleno-de-tiempo', PASSWORD_DEFAULT);
            $correcto = password_verify($password, (string) $hash);

            if ($usuario === null || !$correcto || (int) $usuario['activo'] !== 1) {
                $limitador->registrarFallo($email, $ip);
                http_response_code(401);
                $general = 'El correo o la contraseña no son correctos.';
            } else {
                $limitador->limpiar($email, $ip);
                if (password_needs_rehash((string) $usuario['password_hash'], PASSWORD_DEFAULT)) {
                    $usuarios->actualizarHash((int) $usuario['id'], password_hash($password, PASSWORD_DEFAULT));
                }
                $usuarios->registrarAcceso((int) $usuario['id']);
                Auth::iniciar((int) $usuario['id']);
                mensaje_flash('exito', 'Bienvenido(a), ' . $usuario['nombre'] . '.');
                redirigir(ruta_interna_segura($next) ?? url('index.php'));
            }
        } else {
            http_response_code(422);
        }
    }
}

$tituloPagina = 'Ingresar';
$descripcionPagina = 'Inicia sesión en Quipu Noticias para comentar y, si eres autor, publicar noticias.';
$seccion = 'login';
require QUIPU_ROOT . '/templates/cabecera.php';
?>
<div class="encabezado-pagina">
    <h1>Ingresar</h1>
    <p>Accede con tu correo y contraseña. ¿Aún no tienes cuenta? <a href="<?= e(url('registro.php')) ?>">Crea una gratis</a>.</p>
</div>

<form class="formulario" method="post" action="<?= e(url('login.php')) ?>" aria-label="Formulario de inicio de sesión">
    <?= Csrf::campo() ?>
    <input type="hidden" name="next" value="<?= e((string) $next) ?>">
    <?php require QUIPU_ROOT . '/templates/parciales/resumen_errores.php'; ?>

    <div class="campo">
        <label class="campo__etiqueta" for="email">Correo electrónico <span class="texto-suave">(obligatorio)</span></label>
        <input class="entrada" type="email" id="email" name="email" value="<?= e($email) ?>" required
               maxlength="<?= Validador::EMAIL_MAX ?>" autocomplete="username" inputmode="email"
               <?= attr_campo('email', $errores) ?>>
        <?= error_campo('email', $errores) ?>
    </div>

    <div class="campo">
        <label class="campo__etiqueta" for="password">Contraseña <span class="texto-suave">(obligatorio)</span></label>
        <input class="entrada" type="password" id="password" name="password" required
               autocomplete="current-password" <?= attr_campo('password', $errores) ?>>
        <?= error_campo('password', $errores) ?>
        <div class="casilla" data-mostrar-password-caja hidden>
            <input type="checkbox" id="mostrar-password" data-mostrar-password="password">
            <label for="mostrar-password">Mostrar contraseña</label>
        </div>
    </div>

    <button class="boton" type="submit">Ingresar</button>
</form>
<?php require QUIPU_ROOT . '/templates/pie.php'; ?>
