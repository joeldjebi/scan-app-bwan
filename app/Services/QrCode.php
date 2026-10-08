<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\ImageBackEndInterface;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCode
{
    /**
     * QR code vectoriel : idéal pour l'intégration dans le gabarit d'impression.
     */
    public static function svg(string $content, int $size = 512): string
    {
        return self::write($content, $size, new SvgImageBackEnd);
    }

    public static function png(string $content, int $size = 1024): string
    {
        return self::write($content, $size, new ImagickImageBackEnd);
    }

    private static function write(string $content, int $size, ImageBackEndInterface $backEnd): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 2), $backEnd));

        // Niveau Q (25 %) : reste lisible même si le support imprimé est abîmé.
        return $writer->writeString($content, Encoder::DEFAULT_BYTE_MODE_ENCODING, ErrorCorrectionLevel::Q());
    }
}
