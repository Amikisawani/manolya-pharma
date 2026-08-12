<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\AuditRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AuditRecord::class);

        $records = AuditRecord::query()
            ->with('user:id,name,email')
            ->when($request->string('action')->toString(), fn ($q, $action) => $q->where('action', 'like', "%{$action}%"))
            ->when($request->string('user_id')->toString(), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->date('from'), fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->date('to'), fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->paginate(40)
            ->withQueryString();

        return Inertia::render('Audit/Index', [
            'records' => $records,
            'filters' => [
                'action' => $request->string('action')->toString(),
                'user_id' => $request->string('user_id')->toString(),
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
            ],
        ]);
    }
}
