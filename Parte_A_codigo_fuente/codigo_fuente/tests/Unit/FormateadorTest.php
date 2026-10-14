<?php
declare(strict_types=1);

namespace Quipu\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quipu\Support\Formateador;

final class FormateadorTest extends TestCase
{
    public function testEscapaHtmlYScripts(): void
    {
        $html = Formateador::cuerpo("<script>alert('xss')</script>\n\n<img src=x onerror=alert(1)>");
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testParrafosSubtitulosYListas(): void
    {
        $html = Formateador::cuerpo("Primer párrafo.\n\n## Subtítulo\n\n- uno\n- dos\n\nSegunda línea\ncon salto");
        $this->assertStringContainsString('<p>Primer párrafo.</p>', $html);
        $this->assertStringContainsString('<h2>Subtítulo</h2>', $html);
        $this->assertStringContainsString('<ul><li>uno</li><li>dos</li></ul>', $html);
        $this->assertStringContainsString('<p>Segunda línea<br>con salto</p>', $html);
    }

    public function testSubtituloTambienSeEscapa(): void
    {
        $this->assertSame('<h2>&lt;b&gt;x&lt;/b&gt;</h2>', Formateador::cuerpo('## <b>x</b>'));
    }

    public function testFechaEnEspanol(): void
    {
        $this->assertSame('13 de octubre de 2026', Formateador::fecha('2026-10-13 18:30:00'));
        $this->assertSame('1 de enero de 2027', Formateador::fecha('2027-01-01 00:00:00'));
        $this->assertSame('', Formateador::fecha(null));
        $this->assertSame('', Formateador::fecha('no es una fecha'));
        $this->assertSame('2026-10-13T18:30', Formateador::fechaIso('2026-10-13 18:30:00'));
    }
}
