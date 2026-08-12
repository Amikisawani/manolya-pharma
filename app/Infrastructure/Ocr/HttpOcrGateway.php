<?php

namespace App\Infrastructure\Ocr;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Optional HTTP OCR microservice.
 * POST multipart file to OCR_HTTP_URL, expects JSON { "text": "..." }.
 */
final class HttpOcrGateway implements OcrGateway
{
    public function __construct(
        private readonly LocalExtractOcrGateway $fallback,
    ) {}

    public function extract(array $document): OcrResult
    {
        $url = config('services.ocr.url');
        if (! is_string($url) || $url === '') {
            return $this->fallback->extract($document);
        }

        $path = $document['path'] ?? '';
        if (! Storage::disk('local')->exists($path)) {
            return $this->fallback->extract($document);
        }

        try {
            $response = Http::timeout((int) config('services.ocr.timeout', 60))
                ->attach('file', Storage::disk('local')->get($path), basename($path))
                ->post($url, [
                    'mime' => $document['mime'] ?? null,
                    'title' => $document['title'] ?? null,
                ]);

            if (! $response->successful()) {
                return new OcrResult(
                    text: $this->fallback->extract($document)->text,
                    status: 'failed',
                    engine: 'http',
                    error: 'HTTP '.$response->status(),
                );
            }

            $text = (string) ($response->json('text') ?? '');

            return new OcrResult(
                text: $text !== '' ? $text : $this->fallback->extract($document)->text,
                engine: 'http',
            );
        } catch (\Throwable $e) {
            $fallback = $this->fallback->extract($document);

            return new OcrResult(
                text: $fallback->text,
                status: 'failed',
                engine: 'http',
                error: $e->getMessage(),
            );
        }
    }
}
