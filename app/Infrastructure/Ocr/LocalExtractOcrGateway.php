<?php

namespace App\Infrastructure\Ocr;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Best-effort local extraction (text files + heuristic PDF strings).
 * Swap for HttpOcrGateway / cloud when a real OCR engine is available.
 */
final class LocalExtractOcrGateway implements OcrGateway
{
    public function extract(array $document): OcrResult
    {
        $path = $document['path'] ?? '';
        $mime = strtolower((string) ($document['mime'] ?? ''));
        $title = (string) ($document['title'] ?? '');
        $absolute = $document['absolute_path']
            ?? (Storage::disk('local')->exists($path) ? Storage::disk('local')->path($path) : null);

        if (! $absolute || ! is_readable($absolute)) {
            return new OcrResult(
                text: $this->fallbackText($title, $path, $mime),
                status: 'completed',
                engine: 'local-fallback',
            );
        }

        try {
            if ($this->isPlainText($mime, $absolute)) {
                $raw = file_get_contents($absolute) ?: '';
                $text = $this->normalize($title."\n".$raw);

                return new OcrResult(text: $text, engine: 'local-text');
            }

            if (str_contains($mime, 'pdf') || Str::endsWith(strtolower($absolute), '.pdf')) {
                $raw = file_get_contents($absolute) ?: '';
                $extracted = $this->extractPdfStrings($raw);
                $text = $this->normalize(trim($title."\n".$extracted));

                return new OcrResult(
                    text: $text !== '' ? $text : $this->fallbackText($title, $path, $mime),
                    engine: 'local-pdf',
                );
            }

            // Images / Office : pas d’OCR natif — index métadonnées + marqueur
            $hint = $this->fallbackText($title, $path, $mime);
            $hint .= "\n[ocr:pending-external] Fichier binaire — brancher un moteur OCR (Tesseract/API) pour le texte image.";

            return new OcrResult(text: $this->normalize($hint), status: 'completed', engine: 'local-metadata');
        } catch (\Throwable $e) {
            return new OcrResult(
                text: $this->fallbackText($title, $path, $mime),
                status: 'failed',
                engine: 'local',
                error: $e->getMessage(),
            );
        }
    }

    private function isPlainText(string $mime, string $absolute): bool
    {
        if (str_starts_with($mime, 'text/') || in_array($mime, ['application/json', 'application/csv', 'text/csv'], true)) {
            return true;
        }

        $ext = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));

        return in_array($ext, ['txt', 'csv', 'md', 'json', 'log'], true);
    }

    private function extractPdfStrings(string $binary): string
    {
        $chunks = [];

        if (preg_match_all('/\((?:\\\\.|[^\\\\)]){2,}\)/s', $binary, $matches)) {
            foreach ($matches[0] as $match) {
                $inner = substr($match, 1, -1);
                $inner = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'], ["\n", "\r", "\t", '(', ')', '\\'], $inner);
                $inner = preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/', ' ', $inner) ?? $inner;
                if (preg_match('/[A-Za-zÀ-ÿ0-9]{3,}/u', $inner)) {
                    $chunks[] = trim($inner);
                }
            }
        }

        // Stream text operators Tj / TJ (best-effort)
        if (preg_match_all('/\\((.*?)\\)\\s*Tj/s', $binary, $tj)) {
            foreach ($tj[1] as $part) {
                $part = trim(str_replace(['\\(', '\\)'], ['(', ')'], $part));
                if ($part !== '') {
                    $chunks[] = $part;
                }
            }
        }

        return implode(' ', array_unique($chunks));
    }

    private function fallbackText(string $title, string $path, string $mime): string
    {
        return $this->normalize(implode("\n", array_filter([
            $title,
            basename($path),
            $mime,
        ])));
    }

    private function normalize(string $text): string
    {
        $text = preg_replace('/[ \\t]+/', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim(Str::limit($text, 50000, ''));
    }
}
