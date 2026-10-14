<?php
/**
 * Panel (solo administrador): gestión de usuarios (rol y activación).
 *
 * Reglas de seguridad:
 *  - Nadie puede cambiar su propio rol ni desactivarse (evita dejar el sitio sin administrador).
 *  - Siempre debe quedar al menos un administrador activo.
 *  - El rol se valida contra la lista permitida (no se acepta cualquier texto).
 */
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

use Quipu\Core\Auth;
use Quipu\Core\Csrf;
use Quipu\Core\Database;
use Quipu\Core\Permisos;
use Quipu\Repository\UsuarioRepository;
use Quipu\Support\Formateador;

$usuario = Auth::requerirRol(['admin']);
$repo = new UsuarioRepository(Database::conexion());

if (es_post()) {
    Csrf::exigir();
    $id = entero_param('id', 'POST');
    $accion = valor('accion');
    $objetivo = $id > 0 ? $repo->porId($id) : null;

    if ($objetivo === null) {
        mensaje_flash('error', 'El usuario no existe.');
    } elseif ((int) $objetivo['id'] === (int) $usuario['id']) {
        mensaje_flash('error', 'No puedes cambiar tu propio rol ni desactivar tu propia cuenta.');
    } elseif ($accion === 'rol') {
        $rol = valor('rol');
        if (!Permisos::rolValido($rol)) {
            mensaje_flash('error', 'El rol elegido no es válido.');
        } elseif ($objetivo['rol'] === 'admin' && $rol !== 'admin' && (int) $objetivo['activo'] === 1 && $repo->contarActivosPorRol('admin') <= 1) {
            mensaje_flash('error', 'Debe quedar al menos un administrador activo.');
        } else {
            $repo->cambiarRol($id, $rol);
            mensaje_flash('exito', 'El rol de ' . $objetivo['nombre'] . ' ahora es «' . $rol . '».');
        }
    } elseif ($accion === 'activar' || $accion === 'desactivar') {
        if ($accion === 'desactivar' && $objetivo['rol'] === 'admin' && $repo->contarActivosPorRol('admin') <= 1) {
            mensaje_flash('error', 'Debe quedar al menos un administrador activo.');
        } else {
            $repo->cambiarActivo($id, $accion === 'activar');
            mensaje_flash('exito', 'La cuenta de ' . $objetivo['nombre'] . ($accion === 'activar' ? ' fue activada.' : ' fue desactivada.'));
        }
    }
    redirigir(url('admin/usuarios.php'));
}

$lista = $repo->listar();
$tituloPagina = 'Panel · Usuarios';
$seccion = 'panel';
$seccionAdmin = 'usuarios';
require QUIPU_ROOT . '/templates/cabecera.php';
?>
<div class="panel">
    <?php require QUIPU_ROOT . '/templates/admin_nav.php'; ?>

    <div class="panel__contenido">
        <h1>Usuarios</h1>
        <p class="texto-suave">Las cuentas nuevas son «lector». Sube a «autor» a quien deba publicar noticias.</p>

        <div class="tabla-contenedor" role="region" aria-labelledby="cap-usuarios" tabindex="0">
            <table class="tabla">
                <caption id="cap-usuarios">Cuentas registradas (<?= count($lista) ?>)</caption>
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Correo</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Último acceso</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lista as $u):
                    $esYo = (int) $u['id'] === (int) $usuario['id'];
                    ?>
                    <tr>
                        <th scope="row"><?= e($u['nombre']) ?><?= $esYo ? ' (tú)' : '' ?></th>
                        <td><?= e($u['email']) ?></td>
                        <td>
                            <?php if ($esYo): ?>
                                <?= e($u['rol']) ?>
                            <?php else: ?>
                                <form method="post" action="<?= e(url('admin/usuarios.php')) ?>" class="acciones">
                                    <?= Csrf::campo() ?>
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <input type="hidden" name="accion" value="rol">
                                    <label class="solo-lector" for="rol-<?= (int) $u['id'] ?>">Rol de <?= e($u['nombre']) ?></label>
                                    <select class="entrada" id="rol-<?= (int) $u['id'] ?>" name="rol">
                                        <?php foreach (Permisos::ROLES as $rol): ?>
                                            <option value="<?= e($rol) ?>"<?= $u['rol'] === $rol ? ' selected' : '' ?>><?= e($rol) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="boton boton--secundario boton--chico" type="submit"
                                            aria-label="Guardar rol de <?= e($u['nombre']) ?>">Guardar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td><span class="estado <?= (int) $u['activo'] === 1 ? '' : 'estado--borrador' ?>"><?= (int) $u['activo'] === 1 ? 'Activa' : 'Desactivada' ?></span></td>
                        <td><?= $u['ultimo_acceso'] !== null ? e(Formateador::fecha($u['ultimo_acceso'])) : 'Nunca' ?></td>
                        <td>
                            <?php if (!$esYo): ?>
                                <form method="post" action="<?= e(url('admin/usuarios.php')) ?>"
                                      <?= (int) $u['activo'] === 1 ? 'data-confirmar="¿Desactivar esta cuenta? La persona no podrá iniciar sesión."' : '' ?>>
                                    <?= Csrf::campo() ?>
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <input type="hidden" name="accion" value="<?= (int) $u['activo'] === 1 ? 'desactivar' : 'activar' ?>">
                                    <button class="boton <?= (int) $u['activo'] === 1 ? 'boton--peligro' : 'boton--secundario' ?> boton--chico" type="submit"
                                            aria-label="<?= (int) $u['activo'] === 1 ? 'Desactivar' : 'Activar' ?> la cuenta de <?= e($u['nombre']) ?>"><?= (int) $u['activo'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                            <?php else: ?>
                                <span class="texto-suave">Sin acciones</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require QUIPU_ROOT . '/templates/pie.php'; ?>
