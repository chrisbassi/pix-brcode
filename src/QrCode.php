<?php

declare(strict_types=1);

namespace ChrisBassi\PixBrCode;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Desenha o payload do Pix como QR Code.
 */
final class QrCode
{
    public function __construct(private readonly string $payload)
    {
    }

    public function svg(int $size = 320, int $margin = 2): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, $margin), new SvgImageBackEnd());

        return (new Writer($renderer))->writeString($this->payload, 'UTF-8', ErrorCorrectionLevel::M());
    }

    /**
     * Requer a extensão GD.
     */
    public function png(int $size = 320, int $margin = 2): string
    {
        $renderer = new GDLibRenderer($size, $margin);

        return (new Writer($renderer))->writeString($this->payload, 'UTF-8', ErrorCorrectionLevel::M());
    }

    public function dataUri(int $size = 320, int $margin = 2): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode($this->svg($size, $margin));
    }
}
