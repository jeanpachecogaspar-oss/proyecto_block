<?php
declare(strict_types=1);

namespace Quipu\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Quipu\Support\SubidaImagen;

final class SubidaImagenTest extends TestCase
{
    private string $carpeta;

    protected function setUp(): void
    {
        $this->carpeta = sys_get_temp_dir() . '/quipu-test-' . bin2hex(random_bytes(4));
        mkdir($this->carpeta);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->carpeta . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->carpeta);
    }

    private function crearPng(int $ancho, int $alto): string
    {
        $img = imagecreatetruecolor($ancho, $alto);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 10, 90, 140));
        $ruta = $this->carpeta . '/origen-' . $ancho . 'x' . $alto . '.png';
        imagepng($img, $ruta);
        imagedestroy($img);
        return $ruta;
    }

    /** @return array{error:int,tmp_name:string} */
    private function archivo(string $ruta): array
    {
        return ['error' => UPLOAD_ERR_OK, 'tmp_name' => $ruta];
    }

    public function testProcesaUnaImagenGrandeYGeneraPrincipalYMiniatura(): void
    {
        $resultado = (new SubidaImagen($this->carpeta))->procesar($this->crearPng(2400, 1200));

        $this->assertSame(1200, $resultado['ancho'], 'se reduce al ancho máximo');
        $this->assertSame(600, $resultado['alto'], 'se conserva la proporción');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{20}\.(webp|jpg)$/', $resultado['archivo'], 'nombre aleatorio');
        $this->assertFileExists($this->carpeta . '/' . $resultado['archivo']);

        $mini = $this->carpeta . '/' . SubidaImagen::miniatura($resultado['archivo']);
        $this->assertFileExists($mini);
        $this->assertSame(480, getimagesize($mini)[0]);
        $this->assertLessThan(filesize($this->carpeta . '/' . $resultado['archivo']), filesize($mini) + 1);
    }

    public function testNoAgrandaImagenesPequenas(): void
    {
        $r = (new SubidaImagen($this->carpeta))->procesar($this->crearPng(300, 200));
        $this->assertSame(300, $r['ancho']);
        $this->assertSame(200, $r['alto']);
    }

    public function testValidaPorContenidoNoPorExtension(): void
    {
        $php = $this->carpeta . '/shell.png';
        file_put_contents($php, '<?php echo "pwn"; ?>');
        $this->assertSame('Formato no permitido. Sube una imagen JPG, PNG o WebP.', SubidaImagen::validar($this->archivo($php), false));

        $gif = $this->carpeta . '/animada.png';
        $g = imagecreatetruecolor(10, 10);
        imagegif($g, $gif);
        $this->assertNotNull(SubidaImagen::validar($this->archivo($gif), false), 'GIF disfrazado de PNG se rechaza');

        $this->assertNull(SubidaImagen::validar($this->archivo($this->crearPng(50, 50)), false));
    }

    public function testRechazaArchivosDemasiadoPesadosYErroresDeSubida(): void
    {
        $grande = $this->carpeta . '/grande.png';
        file_put_contents($grande, str_repeat('0', SubidaImagen::MAX_BYTES + 1));
        $this->assertStringContainsString('2 MB', (string) SubidaImagen::validar($this->archivo($grande), false));

        $this->assertStringContainsString('2 MB', (string) SubidaImagen::validar(['error' => UPLOAD_ERR_INI_SIZE], false));
        $this->assertNotNull(SubidaImagen::validar(['error' => UPLOAD_ERR_PARTIAL], false));
        $this->assertFalse(SubidaImagen::hayArchivo(['error' => UPLOAD_ERR_NO_FILE]));
        $this->assertFalse(SubidaImagen::hayArchivo(null));
        $this->assertTrue(SubidaImagen::hayArchivo(['error' => UPLOAD_ERR_OK]));
    }

    public function testElArchivoDeFormularioSinSubidaHttpSeRechaza(): void
    {
        // is_uploaded_file() es falso para archivos que no vienen de una subida real.
        $this->assertSame('El archivo recibido no es válido.', SubidaImagen::validar($this->archivo($this->crearPng(50, 50)), true));
    }

    public function testEliminarBorraAmbasVersionesYIgnoraNombresPeligrosos(): void
    {
        $subidor = new SubidaImagen($this->carpeta);
        $r = $subidor->procesar($this->crearPng(800, 400));
        $subidor->eliminar($r['archivo']);
        $this->assertFileDoesNotExist($this->carpeta . '/' . $r['archivo']);
        $this->assertFileDoesNotExist($this->carpeta . '/' . SubidaImagen::miniatura($r['archivo']));

        $fuera = sys_get_temp_dir() . '/quipu-fuera.webp';
        file_put_contents($fuera, 'x');
        $subidor->eliminar('../quipu-fuera.webp');
        $this->assertFileExists($fuera, 'no se puede salir de la carpeta con ../');
        @unlink($fuera);
        $subidor->eliminar(null);
    }

    public function testDimensionesDeMiniatura(): void
    {
        $this->assertSame([480, 252], SubidaImagen::dimensionesMiniatura(1200, 630));
        $this->assertSame([300, 200], SubidaImagen::dimensionesMiniatura(300, 200));
        $this->assertSame([480, 252], SubidaImagen::dimensionesMiniatura(0, 0), 'valores por defecto si faltan datos');
    }
}
