<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Services\FinancialAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalysisController extends Controller
{
    public function index(Request $request, FinancialAnalysisService $analysis)
    {
        $wallet = Wallet::shared() ?? abort(500, 'Wallet utama belum tersedia.');
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);
        $from = Carbon::create($year, $month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $data = $analysis->analyze($wallet, $from, $to);
        return view('analysis', compact('data', 'month', 'year'));
    }
}
