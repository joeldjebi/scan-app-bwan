<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCode
{
    /** Marge autour du QR code, en modules (zone de silence). */
    private const MARGIN = 2;

    /**
     * QR code vectoriel : idéal pour l'intégration dans le gabarit d'impression.
     */
    public static function svg(string $content, int $size = 512): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, self::MARGIN), new SvgImageBackEnd));

        return $writer->writeString($content, Encoder::DEFAULT_BYTE_MODE_ENCODING, self::errorCorrection());
    }

    /**
     * QR code PNG d'environ $size pixels, dessiné module par module avec GD : des dizaines
     * de fois plus rapide que le rendu vectoriel d'Imagick, pour une image identique.
     */
    public static function png(string $content, int $size = 1024): string
    {
        $matrix = Encoder::encode($content, self::errorCorrection(), Encoder::DEFAULT_BYTE_MODE_ENCODING)->getMatrix();
        $modules = $matrix->getWidth();
        $scale = max(1, intdiv($size, $modules + 2 * self::MARGIN));
        $pixels = $scale * ($modules + 2 * self::MARGIN);

        $image = imagecreate($pixels, $pixels);
        imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $left = ($x + self::MARGIN) * $scale;
                    $top = ($y + self::MARGIN) * $scale;
                    imagefilledrectangle($image, $left, $top, $left + $scale - 1, $top + $scale - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($image, null, 6);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /**
     * Niveau Q (25 %) : reste lisible même si le support imprimé est abîmé.
     */
    private static function errorCorrection(): ErrorCorrectionLevel
    {
        return ErrorCorrectionLevel::Q();
    }
}
