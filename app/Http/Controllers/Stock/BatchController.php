<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BatchController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('batches.view'), 403);

        $batches = Batch::query()
            ->with(['product:id,commercial_name,sku', 'warehouse:id,name,code'])
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->string('q')->toString(), function ($query, string $q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('lot_number', 'like', "%{$q}%")
                        ->orWhereHas('product', function ($p) use ($q): void {
                            $p->where('commercial_name', 'like', "%{$q}%")
                                ->orWhere('sku', 'like', "%{$q}%");
                        });
                });
            })
            ->when($request->boolean('expiring'), function ($query): void {
                $query->whereBetween('expires_at', [now()->toDateString(), now()->addDays(30)->toDateString()]);
            })
            ->when($request->boolean('expired'), function ($query): void {
                $query->where('expires_at', '<', now()->toDateString());
            })
            ->orderBy('expires_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Stock/Batches/Index', [
            'batches' => $batches,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->string('status')->toString(),
                'expiring' => $request->boolean('expiring'),
                'expired' => $request->boolean('expired'),
            ],
        ]);
    }
}
