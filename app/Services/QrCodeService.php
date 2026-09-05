<?php

namespace App\Services;

use App\Models\Allocation;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * SRS FR-080 - generates the QR code carried by allocation letters and cards.
 *
 * The encoded value is the public verification URL, which is keyed by the
 * allocation's non-guessable verification token (SRS 7.2).
 */
class QrCodeService
{
    /** The public URL a scanner is sent to (FR-080). */
    public function verificationUrl(Allocation $allocation): string
    {
        return route('verify.show', ['token' => $allocation->verification_token]);
    }

    /** QR code as an SVG string, for on-screen rendering. */
    public function svg(string $value, int $size = 200): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd,
        ));

        // Level M (~15% correction) keeps a printed code readable after wear.
        return $writer->writeString($value, Encoder::DEFAULT_BYTE_MODE_ENCODING, ErrorCorrectionLevel::M());
    }

    /**
     * QR code as a base64 data URI. Dompdf cannot fetch remote images, so
     * generated PDFs embed the code inline this way.
     */
    public function dataUri(string $value, int $size = 200): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($value, $size));
    }

    public function forAllocation(Allocation $allocation, int $size = 200): string
    {
        return $this->dataUri($this->verificationUrl($allocation), $size);
    }
}
