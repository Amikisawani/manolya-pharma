<?php

namespace App\Domain\Sales\Receipts;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Throwable;

final class ReceiptQrCodeGenerator
{
    public function svg(string $payload, int $size = 108): ?string
    {
        $payload = trim($payload);
        if ($payload === '') {
            return null;
        }

        try {
            $renderer = new ImageRenderer(
                new RendererStyle($size, 0),
                new SvgImageBackEnd
            );

            $svg = (new Writer($renderer))->writeString($payload);

            return $this->compactSvg($svg);
        } catch (Throwable) {
            return null;
        }
    }

    private function compactSvg(string $svg): string
    {
        $svg = preg_replace('/<\?xml[^>]*>/', '', $svg) ?? $svg;

        return trim($svg);
    }
}
