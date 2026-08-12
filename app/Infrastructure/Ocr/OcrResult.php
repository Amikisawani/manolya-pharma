<?php

namespace App\Infrastructure\Ocr;

final readonly class OcrResult
{
    public function __construct(
        public string $text,
        public string $status = 'completed', // completed|failed|skipped
        public string $engine = 'local',
        public ?string $error = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->status === 'completed';
    }
}
