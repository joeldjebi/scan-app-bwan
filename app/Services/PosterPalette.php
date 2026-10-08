<?php

namespace App\Services;

use Imagick;
use ImagickException;
use ImagickPixel;

/**
 * Extrait les couleurs dominantes d'une affiche pour habiller le formulaire d'enregistrement.
 */
class PosterPalette
{
    /**
     * @return array{primary: string, secondary: string}|null
     */
    public function extract(string $path): ?array
    {
        try {
            $image = new Imagick($path);
            $image->setIteratorIndex(0);
            $image->thumbnailImage(96, 96, true);
            $image->quantizeImage(12, Imagick::COLORSPACE_SRGB, 0, false, false);
            $histogram = $image->getImageHistogram();
        } catch (ImagickException) {
            return null;
        }

        $total = array_sum(array_map(fn (ImagickPixel $pixel) => $pixel->getColorCount(), $histogram)) ?: 1;

        $candidates = collect($histogram)
            ->map(function (ImagickPixel $pixel) use ($total) {
                $rgb = $pixel->getColor();
                ['h' => $h, 's' => $s, 'l' => $l] = self::toHsl($rgb['r'], $rgb['g'], $rgb['b']);
                $share = $pixel->getColorCount() / $total;

                // Couleurs vives et assez présentes ; quasi blanc / noir / gris pénalisés.
                $vividness = $s * (1 - abs($l - 0.5) * 1.6);

                return [
                    'hex' => self::toHex($rgb['r'], $rgb['g'], $rgb['b']),
                    'h' => $h, 's' => $s, 'l' => $l,
                    'score' => sqrt($share) * (0.15 + max($vividness, 0)),
                ];
            })
            ->sortByDesc('score')
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        $primary = $candidates->first();

        // Couleur d'accent : la meilleure teinte suffisamment différente de la principale.
        $secondary = $candidates->first(fn (array $color) => self::hueDistance($color['h'], $primary['h']) >= 35 && $color['s'] >= 0.25)
            ?? ['hex' => self::shade($primary['hex'], $primary['l'] > 0.5 ? -0.35 : 0.35)];

        return ['primary' => $primary['hex'], 'secondary' => $secondary['hex']];
    }

    /**
     * Couleur de texte lisible (blanc ou presque noir) sur un fond donné.
     */
    public static function readableTextOn(string $hex): string
    {
        [$r, $g, $b] = array_map(fn ($c) => hexdec($c) / 255, str_split(ltrim($hex, '#'), 2));
        $linear = fn (float $c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $luminance = 0.2126 * $linear($r) + 0.7152 * $linear($g) + 0.0722 * $linear($b);

        return $luminance > 0.45 ? '#0f172a' : '#ffffff';
    }

    /**
     * Éclaircit (amount > 0) ou assombrit (amount < 0) une couleur.
     */
    public static function shade(string $hex, float $amount): string
    {
        $channels = array_map(fn ($c) => hexdec($c), str_split(ltrim($hex, '#'), 2));
        $channels = array_map(fn ($c) => (int) round($amount >= 0 ? $c + (255 - $c) * $amount : $c * (1 + $amount)), $channels);

        return self::toHex(...$channels);
    }

    private static function toHex(int|float $r, int|float $g, int|float $b): string
    {
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * @return array{h: float, s: float, l: float}
     */
    private static function toHsl(int|float $r, int|float $g, int|float $b): array
    {
        [$r, $g, $b] = [$r / 255, $g / 255, $b / 255];
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return ['h' => 0.0, 's' => 0.0, 'l' => $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match ($max) {
            $r => (($g - $b) / $d + ($g < $b ? 6 : 0)),
            $g => (($b - $r) / $d + 2),
            default => (($r - $g) / $d + 4),
        } * 60;

        return ['h' => $h, 's' => $s, 'l' => $l];
    }

    private static function hueDistance(float $a, float $b): float
    {
        $distance = abs($a - $b);

        return min($distance, 360 - $distance);
    }
}
