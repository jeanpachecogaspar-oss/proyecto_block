<?php
declare(strict_types=1);

namespace Quipu\Core;

use Quipu\Repository\UsuarioRepository;

/**
 * Autenticación basada en sesión.
 *
 * Solo se guarda el id del usuario en la sesión. En cada petición se vuelve a
 * leer el usuario desde la base de datos: si un administrador lo desactiva o le
 * cambia el rol, el efecto es inmediato (no hay "roles viejos" en la sesión).
 */
final class Auth
{
    /** @var array<string,mixed>|null|false  false = aún no consultado */
    private static array|null|false $actual = false;

    /** @return array<string,mixed>|null */
    public static function usuario(): ?array
    {
        if (self::$actual !== false) {
            return self::$actual;
        }
        $id = $_SESSION['uid'] ?? null;
        if (!is_int($id)) {
            return self::$actual = null;
        }
        $usuario = (new UsuarioRepository(Database::conexion()))->porId($id);
        if ($usuario === null || (int) $usuario['activo'] !== 1) {
            unset($_SESSION['uid']);
            return self::$actual = null;
        }
        unset($usuario['password_hash']); // el hash nunca viaja a las plantillas
        return self::$actual = $usuario;
    }

    public static function iniciar(int $idUsuario): void
    {
        Session::regenerar();
        Csrf::renovar();
        $_SESSION['uid'] = $idUsuario;
        self::$actual = false;
    }

    public static function cerrar(): void
    {
        self::$actual = false;
        Session::destruir();
    }

    public static function reiniciarCache(): void
    {
        self::$actual = false;
    }

    /** Exige sesión iniciada; si no, envía al login recordando a dónde volver. */
    public static function requerirLogin(): array
    {
        $usuario = self::usuario();
        if ($usuario === null) {
            $destino = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $parametro = ruta_interna_segura($destino) !== null ? '?next=' . rawurlencode($destino) : '';
            mensaje_flash('info', 'Inicia sesión para continuar.');
            redirigir(url('login.php') . $parametro);
        }
        return $usuario;
    }

    /**
     * Exige uno de los roles indicados. Sin sesión → login; con sesión pero sin
     * permiso → 403 (el usuario no puede deducir qué páginas existen).
     *
     * @param list<string> $roles
     * @return array<string,mixed>
     */
    public static function requerirRol(array $roles): array
    {
        $usuario = self::requerirLogin();
        if (!in_array($usuario['rol'], $roles, true)) {
            abortar(403, 'Acceso restringido', 'Tu cuenta no tiene permiso para ver esta sección.');
        }
        return $usuario;
    }
}
