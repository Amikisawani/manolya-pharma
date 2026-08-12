<?php

namespace App\Domain\Reporting\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

final class OwnerReportPdfGenerator
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function store(array $payload, string $relativePath): string
    {
        $view = ($payload['type'] ?? 'daily') === 'monthly'
            ? 'reports.monthly'
            : 'reports.daily';

        $pdf = Pdf::loadView($view, ['report' => $payload])
            ->setPaper('a4');

        Storage::disk('local')->put($relativePath, $pdf->output());

        return $relativePath;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function raw(array $payload): string
    {
        $view = ($payload['type'] ?? 'daily') === 'monthly'
            ? 'reports.monthly'
            : 'reports.daily';

        return Pdf::loadView($view, ['report' => $payload])
            ->setPaper('a4')
            ->output();
    }
}
