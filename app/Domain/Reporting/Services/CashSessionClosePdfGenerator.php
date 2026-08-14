<?php

namespace App\Domain\Reporting\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

final class CashSessionClosePdfGenerator
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function store(array $payload, string $relativePath): string
    {
        Storage::disk('local')->put($relativePath, $this->raw($payload));

        return $relativePath;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function raw(array $payload): string
    {
        return Pdf::loadView('reports.cash-session-close', ['report' => $payload])
            ->setPaper('a4')
            ->output();
    }
}
