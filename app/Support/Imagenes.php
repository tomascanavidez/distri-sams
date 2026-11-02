<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\ErrorNegocio;

/**
 * Subida de imágenes: valida que sea realmente una imagen, la reduce y la comprime
 * (JPEG) para que las pantallas carguen rápido en conexiones móviles.
 */
final class Imagenes
{
    /** Guarda la foto del producto (600 px) y una miniatura (160 px). Devuelve el nombre del archivo. */
    public static function guardarProducto(array $archivo, int $productoId): string
    {
        $dir = PUBLIC_PATH . '/uploads/productos';
        $nombre = 'p' . $productoId . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.jpg';
        $origen = self::cargar($archivo);
        self::redimensionar($origen, 600, $dir . '/' . $nombre);
        self::redimensionar($origen, 160, $dir . '/t_' . $nombre);
        imagedestroy($origen);
        return $nombre;
    }

    /** Logo de la empresa (PNG para conservar transparencia). */
    public static function guardarLogo(array $archivo): string
    {
        $origen = self::cargar($archivo);
        $nombre = 'logo_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.png';
        $w = imagesx($origen);
        $h = imagesy($origen);
        $escala = min(1, 400 / max($w, $h));
        $nw = (int) max(1, round($w * $escala));
        $nh = (int) max(1, round($h * $escala));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $origen, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagepng($dst, PUBLIC_PATH . '/uploads/empresa/' . $nombre, 8);
        imagedestroy($dst);
        imagedestroy($origen);
        return $nombre;
    }

    private static function cargar(array $archivo): \GdImage
    {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ErrorNegocio('No se pudo subir la imagen.');
        }
        if ($archivo['size'] > 8 * 1024 * 1024) {
            throw new ErrorNegocio('La imagen supera los 8 MB.');
        }
        $info = @getimagesize($archivo['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            throw new ErrorNegocio('El archivo no es una imagen válida (JPG, PNG, WEBP o GIF).');
        }
        if (!function_exists('imagecreatetruecolor')) {
            throw new ErrorNegocio('El servidor no tiene la extensión GD para procesar imágenes.');
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($archivo['tmp_name']),
            IMAGETYPE_PNG => @imagecreatefrompng($archivo['tmp_name']),
            IMAGETYPE_WEBP => @imagecreatefromwebp($archivo['tmp_name']),
            default => @imagecreatefromgif($archivo['tmp_name']),
        };
        if (!$img) {
            throw new ErrorNegocio('No se pudo leer la imagen.');
        }
        return $img;
    }

    private static function redimensionar(\GdImage $origen, int $max, string $destino): void
    {
        $w = imagesx($origen);
        $h = imagesy($origen);
        $escala = min(1, $max / max($w, $h));
        $nw = (int) max(1, round($w * $escala));
        $nh = (int) max(1, round($h * $escala));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $origen, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imageinterlace($dst, true);
        imagejpeg($dst, $destino, 78);
        imagedestroy($dst);
    }
}
