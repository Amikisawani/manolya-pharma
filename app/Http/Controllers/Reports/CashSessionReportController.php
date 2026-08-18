<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Reporting\Services\CashSessionReportQuery;
use App\Domain\Sales\Services\CashRegisterSessionService;
use App\Http\Controllers\Controller;
use App\Jobs\SendCashSessionClosedReportJob;
use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CashSessionReportController extends Controller
{
    public function index(Request $request, CashSessionReportQuery $query): Response
    {
        abort_unless($request->user()?->canReviewCashSessions(), 403);

        $filters = $query->filters($request);

        return Inertia::render('Reports/CashSessions/Index', [
            'sessions' => $query->paginate($filters),
            'filters' => $filters,
            'canApprove' => $request->user()?->canApproveCashSessions() ?? false,
        ]);
    }

    public function show(Request $request, CashRegisterSession $session, CashSessionReportQuery $query): Response
    {
        abort_unless($request->user()?->canReviewCashSessions(), 403);

        return Inertia::render('Reports/CashSessions/Show', [
            ...$query->detail($session, [
                'q' => $request->string('q')->toString(),
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
            ]),
            'canApprove' => $request->user()?->canApproveCashSessions() ?? false,
        ]);
    }

    public function confirm(
        Request $request,
        CashRegisterSession $session,
        CashRegisterSessionService $service,
    ): RedirectResponse {
        abort_unless($request->user()?->canApproveCashSessions(), 403);

        $data = $request->validate([
            'closing_counted' => ['nullable', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $service->confirmClose($session, [
                'closed_by' => (string) $request->user()->id,
                'closing_counted' => $data['closing_counted'] ?? null,
                'closing_notes' => $data['closing_notes'] ?? null,
            ]);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        DB::afterCommit(function () use ($session): void {
            SendCashSessionClosedReportJob::dispatch((string) $session->id);
        });

        return back()->with('success', 'Session de caisse clôturée.');
    }

    public function reject(
        Request $request,
        CashRegisterSession $session,
        CashRegisterSessionService $service,
    ): RedirectResponse {
        abort_unless($request->user()?->canApproveCashSessions(), 403);

        try {
            $service->rejectClose($session, (string) $request->user()->id);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Demande de fermeture refusée — la session continue.');
    }

    public function unlock(
        Request $request,
        CashRegisterSessionService $service,
    ): RedirectResponse {
        abort_unless($request->user()?->canApproveCashSessions(), 403);

        $data = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'business_date' => ['nullable', 'date'],
        ]);

        $cashier = User::query()->findOrFail($data['user_id']);
        abort_unless((string) $cashier->tenant_id === (string) $request->user()->tenant_id, 403);

        $service->unlockDay($cashier, $request->user(), $data['business_date'] ?? null);

        return back()->with('success', 'La caisse peut de nouveau être ouverte aujourd’hui.');
    }
}
