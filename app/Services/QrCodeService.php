<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use InvalidArgumentException;

/**
 * QR Code generation service using bacon/bacon-qr-code
 */
class QrCodeService
{
    protected ?string $format = null;

    protected ?int $size = null;

    protected ?string $errorCorrection = null;

    /**
     * Set the output format
     *
     * @param  string  $format  The format (currently only 'svg' is supported)
     * @return $this
     */
    public function format(string $format): self
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Set the size of the QR code
     *
     * @param  int  $size  The size in pixels
     * @return $this
     */
    public function size(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    /**
     * Set the error correction level
     *
     * @param  string  $level  Error correction level: 'l', 'm', 'q', 'h'
     * @return $this
     */
    public function errorCorrection(string $level): self
    {
        $this->errorCorrection = $level;

        return $this;
    }

    /**
     * Generate the QR code
     *
     * @param  string  $text  The text to encode
     * @return string The generated QR code (SVG string)
     */
    public function generate(string $text): string
    {
        // Ensure format is set (default to SVG)
        $format = $this->format ?? 'svg';

        if ($format !== 'svg') {
            throw new InvalidArgumentException("Format '{$format}' is not supported. Only 'svg' is currently supported.");
        }

        // Calculate size and margin
        // If size is not set, calculate from module size (default module size is 11)
        if ($this->size === null) {
            $moduleSize = 11;
            $this->size = 29 * $moduleSize;
        }

        // Calculate margin: 4 modules on each side
        // Module size = size / 29, so margin = (size / 29) * 4
        $moduleSize = $this->size / 29;
        $margin = (int) ($moduleSize * 4);

        // Map error correction level
        $errorCorrectionLevel = ErrorCorrectionLevel::valueOf('M'); // default
        if ($this->errorCorrection !== null) {
            $errorCorrectionLevel = match (mb_strtoupper($this->errorCorrection)) {
                'L' => ErrorCorrectionLevel::valueOf('L'),
                'M' => ErrorCorrectionLevel::valueOf('M'),
                'Q' => ErrorCorrectionLevel::valueOf('Q'),
                'H' => ErrorCorrectionLevel::valueOf('H'),
                default => ErrorCorrectionLevel::valueOf('M'),
            };
        }

        // Create renderer style with size and margin
        $rendererStyle = new RendererStyle($this->size, $margin);

        // Create SVG renderer
        $renderer = new ImageRenderer(
            $rendererStyle,
            new SvgImageBackEnd()
        );

        // Create writer
        $writer = new Writer($renderer);

        // Generate SVG
        $svg = $writer->writeString($text, 'ISO-8859-1', $errorCorrectionLevel);

        // Add class="qr-code" to the SVG element
        $svg = $this->addQrCodeClass($svg);

        return $svg;
    }

    /**
     * Add the 'qr-code' class to the SVG element
     *
     * @param  string  $svg  The SVG string
     * @return string The SVG string with the class added
     */
    protected function addQrCodeClass(string $svg): string
    {
        // Match <svg followed by whitespace or attributes, and insert class before the first attribute or closing >
        if (preg_match('/<svg\s+([^>]*)>/', $svg, $matches)) {
            $attributes = $matches[1];
            // Check if class already exists
            if (mb_strpos($attributes, 'class=') === false) {
                $svg = preg_replace('/<svg\s+([^>]*)>/', '<svg class="qr-code" $1>', $svg, 1);
            } else {
                // If class exists, add qr-code to it
                $svg = preg_replace('/(<svg\s+[^>]*class=["\'])([^"\']*)(["\'][^>]*>)/', '$1$2 qr-code$3', $svg, 1);
            }
        } else {
            // Fallback: simple replacement if no attributes
            $svg = preg_replace('/<svg>/', '<svg class="qr-code">', $svg, 1);
        }

        return $svg;
    }
}
