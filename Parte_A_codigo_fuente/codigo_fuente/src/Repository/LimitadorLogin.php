<?php
declare(strict_types=1);

namespace Quipu\Repository;

use PDO;

/**
 * Freno a la fuerza bruta: tras 5 intentos fallidos en 15 minutos para la misma
 * combinación correo + IP, el login se bloquea temporalmente.
 *
 * Recibe un "reloj" opcional para poder simular el paso del tiempo en las pruebas.
 */
final class LimitadorLogin
{
    public const MAX_INTENTOS = 5;
    public const VENTANA_SEGUNDOS = 900;

    /** @var callable():int */
    private $reloj;

    public function __construct(private readonly PDO $pdo, ?callable $reloj = null)
    {
        $this->reloj = $reloj ?? static fn (): int => time();
    }

    public function bloqueado(string $email, string $ip): bool
    {
        return $this->intentosRecientes($email, $ip) >= self::MAX_INTENTOS;
    }

    public function registrarFallo(string $email, string $ip): void
    {
        $this->pdo->prepare('INSERT INTO intentos_login (email, ip, creado_en) VALUES (:e, :i, :f)')
            ->execute([
                ':e' => UsuarioRepository::normalizarEmail($email),
                ':i' => substr($ip, 0, 45),
                ':f' => date('Y-m-d H:i:s', ($this->reloj)()),
            ]);
    }

    public function limpiar(string $email, string $ip): void
    {
        $this->pdo->prepare('DELETE FROM intentos_login WHERE email = :e AND ip = :i')
            ->execute([':e' => UsuarioRepository::normalizarEmail($email), ':i' => substr($ip, 0, 45)]);
    }

    public function intentosRecientes(string $email, string $ip): int
    {
        $desde = date('Y-m-d H:i:s', ($this->reloj)() - self::VENTANA_SEGUNDOS);
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM intentos_login WHERE email = :e AND ip = :i AND creado_en >= :d');
        $st->execute([':e' => UsuarioRepository::normalizarEmail($email), ':i' => substr($ip, 0, 45), ':d' => $desde]);
        return (int) $st->fetchColumn();
    }

    /** Elimina registros viejos para que la tabla no crezca sin límite. */
    public function purgar(): void
    {
        $limite = date('Y-m-d H:i:s', ($this->reloj)() - 86400);
        $this->pdo->prepare('DELETE FROM intentos_login WHERE creado_en < :l')->execute([':l' => $limite]);
    }
}
