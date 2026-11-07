<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class Qr
{
    /**
     * A PNG data URI, which dompdf renders reliably (unlike SVG).
     */
    public static function pngDataUri(string $content, int $scale = 6, int $ecc = EccLevel::M): string
    {
        return (new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => true,
            'eccLevel' => $ecc,
            'scale' => $scale,
            'quietzoneSize' => 2,
        ])))->render($content);
    }

    /**
     * The EPC/SEPA payment QR ("scan to pay" in Belgian banking apps).
     */
    public static function epcPayload(string $name, string $iban, ?string $bic, float $amount, string $reference): string
    {
        $structured = StructuredReference::isValid($reference) ? preg_replace('/\D/', '', $reference) : null;

        return implode("\n", [
            'BCD', '002', '1', 'SCT',
            (string) $bic,
            mb_substr($name, 0, 70),
            preg_replace('/\s+/', '', $iban),
            'EUR'.number_format($amount, 2, '.', ''),
            '',
            $structured ?? '',
            $structured ? '' : mb_substr($reference, 0, 140),
        ]);
    }
}
