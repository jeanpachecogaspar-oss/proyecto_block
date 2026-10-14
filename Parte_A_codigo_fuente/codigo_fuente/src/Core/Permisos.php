<?php
declare(strict_types=1);

namespace Quipu\Core;

/**
 * Matriz de permisos por rol (control de acceso).
 *
 *  Acción                         lector  autor   admin
 *  Leer noticias publicadas         sí      sí      sí
 *  Comentar                         sí      sí      sí
 *  Entrar al panel                  no      sí      sí
 *  Crear noticias                   no      sí      sí
 *  Editar/eliminar noticia propia   no      sí      sí
 *  Editar/eliminar noticia ajena    no      no      sí
 *  Categorías, usuarios, moderación no      no      sí
 *
 * Son funciones puras (sin base de datos), por eso se prueban fácilmente.
 */
final class Permisos
{
    public const ROLES = ['admin', 'autor', 'lector'];

    /** @param array<string,mixed>|null $usuario */
    public static function esAdmin(?array $usuario): bool
    {
        return $usuario !== null && ($usuario['rol'] ?? null) === 'admin';
    }

    /** @param array<string,mixed>|null $usuario */
    public static function puedeEntrarAlPanel(?array $usuario): bool
    {
        return $usuario !== null && in_array($usuario['rol'] ?? null, ['admin', 'autor'], true);
    }

    /** @param array<string,mixed>|null $usuario */
    public static function puedeComentar(?array $usuario): bool
    {
        return $usuario !== null
            && in_array($usuario['rol'] ?? null, self::ROLES, true)
            && (int) ($usuario['activo'] ?? 1) === 1;
    }

    /**
     * @param array<string,mixed>|null $usuario
     * @param array<string,mixed> $noticia
     */
    public static function puedeEditarNoticia(?array $usuario, array $noticia): bool
    {
        if (!self::puedeEntrarAlPanel($usuario)) {
            return false;
        }
        if (self::esAdmin($usuario)) {
            return true;
        }
        return (int) ($noticia['autor_id'] ?? 0) === (int) ($usuario['id'] ?? -1);
    }

    /** @param array<string,mixed>|null $usuario */
    public static function puedeModerar(?array $usuario): bool
    {
        return self::esAdmin($usuario);
    }

    public static function rolValido(string $rol): bool
    {
        return in_array($rol, self::ROLES, true);
    }
}
