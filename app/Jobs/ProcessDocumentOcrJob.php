<?php

namespace App\Jobs;

use App\Infrastructure\Ocr\OcrGateway;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessDocumentOcrJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $documentVersionId) {}

    public function handle(OcrGateway $ocr): void
    {
        $version = DocumentVersion::query()->find($this->documentVersionId);
        if ($version === null) {
            return;
        }

        $document = Document::query()->find($version->document_id);
        if ($document === null) {
            return;
        }

        app()->instance('current_tenant_id', (string) $document->tenant_id);

        $version->update([
            'ocr_status' => 'processing',
            'ocr_error' => null,
        ]);

        try {
            $result = $ocr->extract([
                'path' => (string) $version->disk_path,
                'mime' => $version->mime,
                'title' => $document->title,
                'absolute_path' => Storage::disk('local')->exists($version->disk_path)
                    ? Storage::disk('local')->path($version->disk_path)
                    : null,
            ]);

            $search = trim(implode("\n", array_filter([
                $document->title,
                $document->type,
                $result->text,
            ])));

            $version->update([
                'ocr_status' => $result->succeeded() ? 'completed' : 'failed',
                'ocr_text' => $result->text,
                'ocr_engine' => $result->engine,
                'ocr_error' => $result->error,
                'ocr_processed_at' => now(),
            ]);

            $document->update([
                'search_text' => $search,
            ]);
        } catch (Throwable $e) {
            Log::error('OCR job failed', [
                'document_version_id' => $this->documentVersionId,
                'message' => $e->getMessage(),
            ]);

            $version->update([
                'ocr_status' => 'failed',
                'ocr_error' => $e->getMessage(),
                'ocr_processed_at' => now(),
            ]);

            throw $e;
        }
    }
}
