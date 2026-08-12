<?php

namespace App\Http\Controllers\Alert;

use App\Http\Controllers\Controller;
use App\Models\Alert as AlertModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('alerts.view'), 403);

        $alerts = AlertModel::query()
            ->with('acknowledger:id,name')
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->string('severity')->toString(), fn ($q, $sev) => $q->where('severity', $sev))
            ->orderByDesc('raised_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Alerts/Index', [
            'alerts' => $alerts,
            'filters' => [
                'status' => $request->string('status')->toString(),
                'severity' => $request->string('severity')->toString(),
            ],
        ]);
    }

    public function acknowledge(Request $request, AlertModel $alert): RedirectResponse
    {
        abort_unless($request->user()?->can('alerts.ack'), 403);

        $alert->update([
            'status' => 'acked',
            'acked_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Alerte acquittée.');
    }
}
