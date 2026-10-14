<?php
/**
 * Registro de lectores.
 *
 * Toda cuenta nueva nace con rol "lector". Solo un administrador puede subirla a
 * "autor" o "admin" desde el panel: nadie puede auto-asignarse privilegios
 * (el campo "rol" nunca se lee del formulario).
 */
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Quipu\Core\Auth;
use Quipu\Core\Csrf;
use Quipu\Core\Database;
use Quipu\Repository\UsuarioRepository;
use Quipu\Support\Validador;

if (usuario_actual() !== null) {
    redirigir(url('index.php'));
}

$errores = [];
$nombre = '';
$email = '';

if (es_post()) {
    Csrf::exigir();
    $usuarios = new UsuarioRepository(Database::conexion());

    $nombre = trim(valor('nombre'));
    $email = trim(valor('email'));
    $password = valor('password');
    $confirmacion = valor('password2');

    if (($e = Validador::nombre($nombre)) !== null) {
        $errores['nombre'] = $e;
    }
    if (($e = Validador::email($email)) !== null) {
        $errores['email'] = $e;
    } elseif ($usuarios->existeEmail($email)) {
        $errores['email'] = 'Ya existe una cuenta con ese correo. Inicia sesión o usa otro correo.';
    }
    if (($e = Validador::password($password)) !== null) {
        $errores['password'] = $e;
    }
    if (!isset($errores['password']) && ($e = Validador::confirmacionPassword($password, $confirmacion)) !== null) {
        $errores['password2'] = $e;
    }

    if ($errores === []) {
        try {
            $id = $usuarios->crear($nombre, $email, password_hash($password, PASSWORD_DEFAULT), 'lector');
        } catch (PDOException $ex) {
            // Carrera entre dos registros simultáneos: la restricción UNIQUE del correo lo detiene.
            $id = 0;
            $errores['email'] = 'Ya existe una cuenta con ese correo. Inicia sesión o usa otro correo.';
        }
        if ($id > 0) {
            Auth::iniciar($id);
            mensaje_flash('exito', 'Tu cuenta fue creada. ¡Bienvenido(a), ' . $nombre . '!');
            redirigir(url('index.php'));
        }
    }
    http_response_code(422);
}

$tituloPagina = 'Crear cuenta';
$descripcionPagina = 'Crea tu cuenta en Quipu Noticias para comentar las noticias.';
$seccion = 'registro';
require QUIPU_ROOT . '/templates/cabecera.php';
?>
<div class="encabezado-pagina">
    <h1>Crear cuenta</h1>
    <p>Con una cuenta puedes comentar las noticias. ¿Ya tienes una? <a href="<?= e(url('login.php')) ?>">Ingresa aquí</a>.</p>
</div>

<form class="formulario" method="post" action="<?= e(url('registro.php')) ?>" aria-label="Formulario de registro">
    <?= Csrf::campo() ?>
    <?php $general = null; require QUIPU_ROOT . '/templates/parciales/resumen_errores.php'; ?>

    <div class="campo">
        <label class="campo__etiqueta" for="nombre">Nombre <span class="texto-suave">(obligatorio)</span></label>
        <p class="campo__ayuda" id="nombre-ayuda">Se muestra junto a tus comentarios. Entre <?= Validador::NOMBRE_MIN ?> y <?= Validador::NOMBRE_MAX ?> caracteres.</p>
        <input class="entrada" type="text" id="nombre" name="nombre" value="<?= e($nombre) ?>" required
               maxlength="<?= Validador::NOMBRE_MAX ?>" autocomplete="name"
               <?= attr_campo('nombre', $errores, 'nombre-ayuda') ?>>
        <?= error_campo('nombre', $errores) ?>
    </div>

    <div class="campo">
        <label class="campo__etiqueta" for="email">Correo electrónico <span class="texto-suave">(obligatorio)</span></label>
        <input class="entrada" type="email" id="email" name="email" value="<?= e($email) ?>" required
               maxlength="<?= Validador::EMAIL_MAX ?>" autocomplete="email" inputmode="email"
               <?= attr_campo('email', $errores) ?>>
        <?= error_campo('email', $errores) ?>
    </div>

    <div class="campo">
        <label class="campo__etiqueta" for="password">Contraseña <span class="texto-suave">(obligatorio)</span></label>
        <p class="campo__ayuda" id="password-ayuda">Mínimo <?= Validador::PASSWORD_MIN ?> caracteres, con al menos una letra y un número. Puedes usar una frase fácil de recordar.</p>
        <input class="entrada" type="password" id="password" name="password" required
               minlength="<?= Validador::PASSWORD_MIN ?>" autocomplete="new-password"
               <?= attr_campo('password', $errores, 'password-ayuda') ?>>
        <?= error_campo('password', $errores) ?>
    </div>

    <div class="campo">
        <label class="campo__etiqueta" for="password2">Repite la contraseña <span class="texto-suave">(obligatorio)</span></label>
        <input class="entrada" type="password" id="password2" name="password2" required
               autocomplete="new-password" <?= attr_campo('password2', $errores) ?>>
        <?= error_campo('password2', $errores) ?>
        <div class="casilla" data-mostrar-password-caja hidden>
            <input type="checkbox" id="mostrar-password" data-mostrar-password="password password2">
            <label for="mostrar-password">Mostrar contraseñas</label>
        </div>
    </div>

    <button class="boton" type="submit">Crear cuenta</button>
</form>
<?php require QUIPU_ROOT . '/templates/pie.php'; ?>
