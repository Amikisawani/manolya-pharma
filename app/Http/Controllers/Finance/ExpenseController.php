<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('expenses.view'), 403);

        $expenses = Expense::query()
            ->with('recorder:id,name')
            ->orderByDesc('spent_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Finance/Index', [
            'expenses' => $expenses,
            'tab' => 'expenses',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('expenses.create'), 403);

        $data = $request->validate([
            'category' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_code' => ['required', 'string', 'size:3'],
            'spent_at' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        Expense::query()->create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Dépense enregistrée.');
    }
}
