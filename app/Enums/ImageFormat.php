<?php

namespace App\Enums;

enum ImageFormat: string
{
    case AUTO = 'auto';
    case PNG_8BIT_GRAYSCALE = 'png_8bit_grayscale';
    case BMP3_1BIT_SRGB = 'bmp3_1bit_srgb';
    case PNG_8BIT_256C = 'png_8bit_256c';

    public function label(): string
    {
        return match ($this) {
            self::AUTO => 'Auto',
            self::PNG_8BIT_GRAYSCALE => 'PNG 8-bit Grayscale Gray 2c',
            self::BMP3_1BIT_SRGB => 'BMP3 1-bit sRGB 2c',
            self::PNG_8BIT_256C => 'PNG 8-bit Grayscale Gray 256c',
        };
    }
}
