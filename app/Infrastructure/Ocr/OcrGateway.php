<?php

namespace App\Infrastructure\Ocr;

interface OcrGateway
{
    /**
     * @param  array{path: string, mime?: string|null, title?: string|null, absolute_path?: string|null}  $document
     */
    public function extract(array $document): OcrResult;
}
