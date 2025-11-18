<?php

namespace App\Enums;

use Bnussbau\TrmnlPipeline\Data\RgbColor;
use InvalidArgumentException;

enum PaletteName: string
{
    case SPECTRA_6 = 'spectra_6';

    /**
     * @return RgbColor[]
     */
    public static function getPalette(PaletteName $palette): array
    {
        switch ($palette) {
            case PaletteName::SPECTRA_6:
                return [
                    RgbColor::fromComponents(0, 0, 0),
                    RgbColor::fromComponents(255, 255, 255),
                    RgbColor::fromComponents(255, 255, 0),
                    RgbColor::fromComponents(255, 0, 0),
                    RgbColor::fromComponents(0, 255, 0),
                    RgbColor::fromComponents(0, 0, 255),
                ];
            default:
                throw new InvalidArgumentException('Invalid palette name');
        }
    }
}
