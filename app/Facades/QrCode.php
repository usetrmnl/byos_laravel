<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Services\QrCodeService format(string $format)
 * @method static \App\Services\QrCodeService size(int $size)
 * @method static \App\Services\QrCodeService errorCorrection(string $level)
 * @method static string generate(string $text)
 *
 * @see \App\Services\QrCodeService
 */
class QrCode extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'qr-code';
    }
}
