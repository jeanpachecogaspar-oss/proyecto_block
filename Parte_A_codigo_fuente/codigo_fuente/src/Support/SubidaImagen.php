<?php
declare(strict_types=1);

namespace Quipu\Support;

use RuntimeException;

/**
 * Subida segura y optimizada de imágenes.
 *
 * Seguridad
 *  - El tipo se determina por el CONTENIDO (finfo + getimagesize), nunca por la
 *    extensión ni por el tipo que declara el navegador.
 *  - Se limita el peso (2 MB) y las dimensiones (anti "bomba de descompresión").
 *  - La imagen se vuelve a codificar con GD: se descarta cualquier metadato o
 *    código oculto dentro del archivo original.
 *  - El nombre final es aleatorio; el nombre original del usuario no se usa.
 *
 * Eficiencia
 *  - Se guardan dos versiones en WebP: principal (máx. 1200 px) y miniatura
 *    (máx. 480 px) para las tarjetas del listado.
 */
final class SubidaImagen
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MAX_LADO = 6000;
    public const ANCHO_PRINCIPAL = 1200;
    public const ANCHO_MINIATURA = 480;
    public const TIPOS_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private readonly string $directorio)
    {
    }

    /** ¿El usuario eligió realmente un archivo en el formulario? */
    public static function hayArchivo(?array $archivo): bool
    {
        return $archivo !== null && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /** Devuelve el mensaje de error o null si el archivo es aceptable. */
    public static function validar(array $archivo, bool $exigirSubidaHttp = true): ?string
    {
        $error = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return 'La imagen supera el tamaño máximo permitido (2 MB).';
        }
        if ($error !== UPLOAD_ERR_OK) {
            return 'No se pudo recibir la imagen. Inténtalo de nuevo.';
        }
        $tmp = (string) ($archivo['tmp_name'] ?? '');
        if ($exigirSubidaHttp && !is_uploaded_file($tmp)) {
            return 'El archivo recibido no es válido.';
        }
        if (!is_file($tmp) || filesize($tmp) === false || filesize($tmp) > self::MAX_BYTES) {
            return 'La imagen supera el tamaño máximo permitido (2 MB).';
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        if (!in_array($mime, self::TIPOS_PERMITIDOS, true)) {
            return 'Formato no permitido. Sube una imagen JPG, PNG o WebP.';
        }
        $info = @getimagesize($tmp);
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            return 'El archivo no es una imagen válida.';
        }
        if ($info[0] > self::MAX_LADO || $info[1] > self::MAX_LADO) {
            return sprintf('La imagen es demasiado grande (máximo %d px por lado).', self::MAX_LADO);
        }
        return null;
    }

    /**
     * Procesa una ruta ya validada y guarda las dos versiones.
     *
     * @return array{archivo:string,ancho:int,alto:int}
     */
    public function procesar(string $rutaTemporal): array
    {
        if (!is_dir($this->directorio) && !mkdir($this->directorio, 0755, true) && !is_dir($this->directorio)) {
            throw new RuntimeException('No se pudo crear la carpeta de imágenes.');
        }
        if (!is_writable($this->directorio)) {
            throw new RuntimeException('La carpeta de imágenes no tiene permiso de escritura.');
        }

        $contenido = file_get_contents($rutaTemporal);
        $origen = $contenido === false ? false : @imagecreatefromstring($contenido);
        if ($origen === false) {
            throw new RuntimeException('No se pudo leer la imagen.');
        }

        $usaWebp = function_exists('imagewebp');
        $extension = $usaWebp ? 'webp' : 'jpg';
        $base = bin2hex(random_bytes(10));
        $archivo = $base . '.' . $extension;

        [$principal, $ancho, $alto] = $this->redimensionar($origen, self::ANCHO_PRINCIPAL, !$usaWebp);
        [$miniatura] = $this->redimensionar($origen, self::ANCHO_MINIATURA, !$usaWebp);
        imagedestroy($origen);

        $this->escribir($principal, $this->directorio . '/' . $archivo, $usaWebp, 82);
        $this->escribir($miniatura, $this->directorio . '/' . self::miniatura($archivo), $usaWebp, 78);
        imagedestroy($principal);
        imagedestroy($miniatura);

        return ['archivo' => $archivo, 'ancho' => $ancho, 'alto' => $alto];
    }

    /** Validación + procesamiento de un archivo de $_FILES. */
    public function guardar(array $archivo): array
    {
        $error = self::validar($archivo);
        if ($error !== null) {
            throw new RuntimeException($error);
        }
        return $this->procesar((string) $archivo['tmp_name']);
    }

    /** Borra ambas versiones. El nombre se valida para impedir recorrer carpetas. */
    public function eliminar(?string $archivo): void
    {
        if ($archivo === null || preg_match('/^[a-f0-9]{20}\.(webp|jpg)$/', $archivo) !== 1) {
            return;
        }
        foreach ([$archivo, self::miniatura($archivo)] as $nombre) {
            $ruta = $this->directorio . '/' . $nombre;
            if (is_file($ruta)) {
                @unlink($ruta);
            }
        }
    }

    /**
     * Dimensiones de la miniatura a partir de las de la imagen principal.
     * Se usan en los atributos width/height para reservar el espacio.
     *
     * @return array{0:int,1:int}
     */
    public static function dimensionesMiniatura(int $ancho, int $alto): array
    {
        if ($ancho < 1 || $alto < 1) {
            return [self::ANCHO_MINIATURA, (int) round(self::ANCHO_MINIATURA * 630 / 1200)];
        }
        $anchoMini = min(self::ANCHO_MINIATURA, $ancho);
        return [$anchoMini, max(1, (int) round($alto * ($anchoMini / $ancho)))];
    }

    /** "abc.webp" → "abc_m.webp" */
    public static function miniatura(string $archivo): string
    {
        return (string) preg_replace('/\.(webp|jpg)$/', '_m.$1', $archivo);
    }

    /**
     * @param \GdImage $origen
     * @return array{0:\GdImage,1:int,2:int}
     */
    private function redimensionar(\GdImage $origen, int $anchoMax, bool $fondoBlanco): array
    {
        $anchoOrigen = imagesx($origen);
        $altoOrigen = imagesy($origen);
        $ancho = min($anchoMax, $anchoOrigen); // nunca se agranda
        $alto = max(1, (int) round($altoOrigen * ($ancho / $anchoOrigen)));

        $destino = imagecreatetruecolor($ancho, $alto);
        if ($fondoBlanco) {
            imagefill($destino, 0, 0, (int) imagecolorallocate($destino, 255, 255, 255));
        } else {
            imagealphablending($destino, false);
            imagesavealpha($destino, true);
            imagefill($destino, 0, 0, (int) imagecolorallocatealpha($destino, 0, 0, 0, 127));
            imagealphablending($destino, true);
        }
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $ancho, $alto, $anchoOrigen, $altoOrigen);
        return [$destino, $ancho, $alto];
    }

    private function escribir(\GdImage $imagen, string $ruta, bool $webp, int $calidad): void
    {
        $ok = $webp ? imagewebp($imagen, $ruta, $calidad) : imagejpeg($imagen, $ruta, $calidad);
        if (!$ok) {
            throw new RuntimeException('No se pudo guardar la imagen.');
        }
        @chmod($ruta, 0644);
    }
}
