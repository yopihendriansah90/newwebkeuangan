<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Wallet;
use Carbon\Carbon;

class FinancialAnalysisService
{
    public function analyze(Wallet $wallet, Carbon $from, Carbon $to): array
    {
        $rows = Transaction::with('category')
            ->where('wallet_id', $wallet->id)
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $income = (float) $rows->where('type', 'income')->sum('amount');
        $expense = (float) $rows->where('type', 'expense')->sum('amount');
        $expenseRows = $rows->where('type', 'expense');
        $categoryTotals = $expenseRows->groupBy(fn ($row) => $row->category?->name ?? 'Tanpa kategori')
            ->map(fn ($items) => (float) $items->sum('amount'))->sortDesc();
        $largest = $rows->sortByDesc('amount')->first();

        return [
            'from' => $from,
            'to' => $to,
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'count' => $rows->count(),
            'average_daily_expense' => $expense / max(1, $from->diffInDays($to) + 1),
            'largest_category' => $categoryTotals->keys()->first(),
            'largest_category_amount' => $categoryTotals->first() ?? 0,
            'categories' => $categoryTotals,
            'largest_transaction' => $largest,
        ];
    }
}
