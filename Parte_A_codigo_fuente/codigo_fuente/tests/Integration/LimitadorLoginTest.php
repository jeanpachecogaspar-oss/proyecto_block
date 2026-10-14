<?php
declare(strict_types=1);

namespace Quipu\Tests\Integration;

use Quipu\Repository\LimitadorLogin;

final class LimitadorLoginTest extends BaseDatosTestCase
{
    public function testSeBloqueaAlQuintoFalloYSeLiberaConElTiempo(): void
    {
        $ahora = 1_800_000_000;
        $limitador = new LimitadorLogin($this->pdo, static function () use (&$ahora): int {
            return $ahora;
        });

        for ($i = 1; $i <= LimitadorLogin::MAX_INTENTOS - 1; $i++) {
            $limitador->registrarFallo('ana@ejemplo.pe', '10.0.0.1');
            $this->assertFalse($limitador->bloqueado('ana@ejemplo.pe', '10.0.0.1'), "con $i fallos aún no se bloquea");
        }
        $limitador->registrarFallo('ana@ejemplo.pe', '10.0.0.1');
        $this->assertTrue($limitador->bloqueado('ana@ejemplo.pe', '10.0.0.1'), 'el 5.º fallo bloquea (valor límite)');

        $ahora += LimitadorLogin::VENTANA_SEGUNDOS + 1;
        $this->assertFalse($limitador->bloqueado('ana@ejemplo.pe', '10.0.0.1'), 'pasada la ventana se libera');
    }

    public function testElBloqueoEsPorCorreoEIp(): void
    {
        $limitador = new LimitadorLogin($this->pdo);
        for ($i = 0; $i < LimitadorLogin::MAX_INTENTOS; $i++) {
            $limitador->registrarFallo('ana@ejemplo.pe', '10.0.0.1');
        }
        $this->assertTrue($limitador->bloqueado('ANA@ejemplo.pe', '10.0.0.1'), 'el correo se normaliza');
        $this->assertFalse($limitador->bloqueado('ana@ejemplo.pe', '10.0.0.2'), 'otra IP no queda bloqueada');
        $this->assertFalse($limitador->bloqueado('beto@ejemplo.pe', '10.0.0.1'), 'otro correo no queda bloqueado');
    }

    public function testLimpiarTrasUnLoginCorrectoYPurgar(): void
    {
        $limitador = new LimitadorLogin($this->pdo);
        for ($i = 0; $i < 3; $i++) {
            $limitador->registrarFallo('ana@ejemplo.pe', '10.0.0.1');
        }
        $this->assertSame(3, $limitador->intentosRecientes('ana@ejemplo.pe', '10.0.0.1'));
        $limitador->limpiar('ana@ejemplo.pe', '10.0.0.1');
        $this->assertSame(0, $limitador->intentosRecientes('ana@ejemplo.pe', '10.0.0.1'));

        $this->pdo->exec("INSERT INTO intentos_login (email, ip, creado_en) VALUES ('viejo@x.pe', '1.1.1.1', '2020-01-01 00:00:00')");
        $limitador->purgar();
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM intentos_login')->fetchColumn());
    }
}
