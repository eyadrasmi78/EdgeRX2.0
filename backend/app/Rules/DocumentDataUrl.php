<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * BE-38 fix: validate that an uploaded base64 data URL is actually a
 * PDF or image (PNG / JPEG / GIF / WebP) by sniffing its magic bytes.
 *
 * Without this rule, a user could upload a renamed `.exe` as their trade
 * license and the system would accept it as long as it stayed under the
 * 10MB size cap. We render these documents as PDFs/images downstream;
 * non-matching content would silently fail to render — but worse, an
 * attacker-controlled blob would be persisted in our DB.
 *
 * Usage:
 *   'tradeLicenseDataUrl' => ['nullable', 'string', 'max:10000000', new DocumentDataUrl(['pdf'])],
 *   'productImage'        => ['nullable', 'string', 'max:10000000', new DocumentDataUrl(['image'])],
 *   'anyDoc'              => ['nullable', 'string', 'max:10000000', new DocumentDataUrl(['pdf', 'image'])],
 */
class DocumentDataUrl implements ValidationRule
{
    /** @param array<string> $accept any of: 'pdf', 'image' */
    public function __construct(private array $accept = ['pdf', 'image']) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || $value === '') return;

        // Expect "data:<mime>;base64,<payload>"
        if (!preg_match('#^data:([^;]+);base64,(.+)$#', $value, $m)) {
            $fail('The :attribute must be a base64 data URL (data:...;base64,...).');
            return;
        }

        $declaredMime = strtolower($m[1]);
        $payload = substr($m[2], 0, 64); // first 64 chars is enough for magic bytes

        // Decode just the prefix to check magic bytes
        $bin = base64_decode($payload, true);
        if ($bin === false || strlen($bin) < 4) {
            $fail('The :attribute base64 payload is invalid.');
            return;
        }

        $isPdf   = str_starts_with($bin, '%PDF-');
        $isPng   = str_starts_with($bin, "\x89PNG\r\n\x1A\n");
        $isJpg   = str_starts_with($bin, "\xFF\xD8\xFF");
        $isGif   = str_starts_with($bin, "GIF87a") || str_starts_with($bin, "GIF89a");
        $isWebp  = strlen($bin) >= 12 && substr($bin, 0, 4) === 'RIFF' && substr($bin, 8, 4) === 'WEBP';
        $isImage = $isPng || $isJpg || $isGif || $isWebp;

        $accepted = false;
        if (in_array('pdf', $this->accept, true) && $isPdf)     $accepted = true;
        if (in_array('image', $this->accept, true) && $isImage) $accepted = true;

        if (!$accepted) {
            $allowed = implode(' or ', $this->accept);
            $fail("The :attribute must be a valid {$allowed} file (magic-byte check failed).");
            return;
        }

        // Sanity: declared MIME should roughly match the detected type
        if ($isPdf && !str_contains($declaredMime, 'pdf')) {
            $fail('The :attribute declared MIME does not match its content (expected pdf).');
        }
        if ($isImage && !str_contains($declaredMime, 'image') && !str_contains($declaredMime, 'octet-stream')) {
            $fail('The :attribute declared MIME does not match its content (expected image).');
        }
    }
}
