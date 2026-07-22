<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\TelegramConnection;
use App\Models\TelegramPairingCode;
use App\Models\Transaction;
use App\Models\Wallet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    private function wallet(): Wallet
    {
        return Wallet::shared() ?? abort(500, 'Wallet utama belum tersedia.');
    }

    private function ledgerContext(Request $request): array
    {
        $wallet = $this->wallet();
        $month = max(1, min(12, $request->integer('month', now()->month)));
        $year = max(2000, min(2100, $request->integer('year', now()->year)));
        $search = trim((string) $request->input('search', ''));
        $period = Carbon::create($year, $month, 1);

        $query = Transaction::with('category')
            ->where('wallet_id', $wallet->id)
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('description', 'like', '%'.$search.'%')
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', '%'.$search.'%'));
            });
        }

        $transactions = (clone $query)->oldest('transaction_date')->oldest('id')->get();
        $previous = $period->copy()->subMonth();
        $previousIncome = Transaction::where('wallet_id', $wallet->id)->whereYear('transaction_date', $previous->year)->whereMonth('transaction_date', $previous->month)->where('type', 'income')->sum('amount');
        $previousExpense = Transaction::where('wallet_id', $wallet->id)->whereYear('transaction_date', $previous->year)->whereMonth('transaction_date', $previous->month)->where('type', 'expense')->sum('amount');
        $carryForward = $search === '' ? max(0, (float) $previousIncome - (float) $previousExpense) : 0;
        $income = (float) $transactions->where('type', 'income')->sum('amount');
        $expense = (float) $transactions->where('type', 'expense')->sum('amount');

        return compact('wallet', 'month', 'year', 'search', 'period', 'transactions', 'carryForward', 'income', 'expense') + ['ledgerIncome' => $income + $carryForward];
    }

    public function index(Request $request)
    {
        $data = $this->ledgerContext($request);
        $transactions = $data['transactions'];
        if ($request->input('view', 'modern') !== 'ledger') $transactions = $transactions->sortByDesc(fn ($transaction) => [$transaction->transaction_date->timestamp, $transaction->id])->values();
        return view('dashboard', $data + compact('transactions'));
    }

    public function exportLedger(Request $request)
    {
        $data = $this->ledgerContext($request);
        $filename = 'buku-kas-'.$data['period']->format('Y-m').'.csv';
        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "Catatan Keuangan Bulan : ".$data['period']->translatedFormat('F Y')."\n\n");
            fputcsv($out, ['No', 'Tanggal', 'Keterangan', 'Debit', 'Kredit'], ';');
            $number = 1;
            if ($data['carryForward'] > 0) fputcsv($out, [$number++, $data['period']->format('d/m/Y'), 'Saldo awal bulan', number_format($data['carryForward'], 0, ',', '.'), ''], ';');
            foreach ($data['transactions'] as $transaction) fputcsv($out, [$number++, $transaction->transaction_date->format('d/m/Y'), $transaction->description, $transaction->type === 'income' ? number_format($transaction->amount, 0, ',', '.') : '', $transaction->type === 'expense' ? number_format($transaction->amount, 0, ',', '.') : ''], ';');
            fputcsv($out, ['', '', 'Total', number_format($data['ledgerIncome'], 0, ',', '.'), number_format($data['expense'], 0, ',', '.')], ';');
            fputcsv($out, ['', '', 'Sisa Uang', number_format($data['ledgerIncome'] - $data['expense'], 0, ',', '.'), ''], ';');
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportLedgerPdf(Request $request)
    {
        $data = $this->ledgerContext($request);
        return Pdf::loadView('exports.ledger-pdf', $data)->setPaper('a4', 'portrait')->download('buku-kas-'.$data['period']->format('Y-m').'.pdf');
    }

    public function store(Request $request)
    {
        $wallet = $this->wallet();
        $request->merge(['amount' => preg_replace('/\D+/', '', (string) $request->input('amount'))]);
        $data = $request->validate(['type' => ['required', Rule::in(['income', 'expense'])], 'category_id' => 'required|exists:categories,id', 'transaction_date' => 'required|date', 'description' => 'required|max:255', 'amount' => 'required|numeric|min:1', 'receipt' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $category = Category::where('id', $data['category_id'])->where('wallet_id', $wallet->id)->where('type', $data['type'])->where('is_active', true)->firstOrFail();
        $data['user_id'] = auth()->id(); $data['wallet_id'] = $wallet->id;
        if ($request->hasFile('receipt')) $data['receipt_path'] = $request->file('receipt')->store('receipts', 'public');
        unset($data['receipt']); Transaction::create($data);
        return back()->with('success', 'Transaksi berhasil ditambahkan.');
    }

    public function destroy(Transaction $transaction) { if ($transaction->receipt_path) Storage::disk('public')->delete($transaction->receipt_path); $transaction->delete(); return back()->with('success', 'Transaksi dihapus.'); }
    public function categories() { $categories = Category::where('wallet_id', $this->wallet()->id)->orderBy('type')->orderBy('name')->get(); return view('categories', compact('categories')); }
    public function members() { $wallet = $this->wallet(); $members = $wallet->members()->orderBy('name')->get(); return view('members', compact('wallet', 'members')); }
    public function memberStore(Request $request) { $wallet = $this->wallet(); $data = $request->validate(['name' => 'required|max:100', 'username' => ['required', 'alpha_dash', 'max:50', Rule::unique('users')], 'password' => 'required|min:6|confirmed']); $member = \App\Models\User::create(['name' => $data['name'], 'username' => $data['username'], 'email' => $data['username'].'@keuangan.local', 'password' => $data['password']]); if (!$wallet->members()->where('user_id', $member->id)->exists()) $wallet->members()->attach($member->id, ['role' => 'member']); return back()->with('success', 'Akun anggota berhasil dibuat.'); }
    public function memberDestroy(\App\Models\User $user) { $wallet = $this->wallet(); abort_if($user->id === auth()->id(), 422, 'Akun yang sedang digunakan tidak dapat dilepas.'); $wallet->members()->detach($user->id); return back()->with('success', 'Anggota dihapus dari daftar. Akses login tetap menggunakan wallet global.'); }
    public function telegram() { $wallet = $this->wallet(); $connections = TelegramConnection::with('user')->where('wallet_id', $wallet->id)->where('is_active', true)->latest()->get(); return view('telegram', compact('wallet', 'connections')); }
    public function telegramPairingCode() { $wallet = $this->wallet(); TelegramPairingCode::where('wallet_id', $wallet->id)->where('user_id', auth()->id())->whereNull('used_at')->update(['used_at' => now()]); $code = strtoupper(Str::random(8)); TelegramPairingCode::create(['wallet_id' => $wallet->id, 'user_id' => auth()->id(), 'code' => $code, 'expires_at' => now()->addMinutes(10)]); return back()->with('pairing_code', $code)->with('success', 'Kode pairing dibuat dan berlaku selama 10 menit.'); }
    public function telegramDisconnect(TelegramConnection $connection) { $connection->update(['is_active' => false]); return back()->with('success', 'Telegram berhasil diputuskan.'); }
    public function categoryStore(Request $request) { $data = $request->validate(['name' => 'required|max:80', 'type' => ['required', Rule::in(['income', 'expense'])]]); Category::create([...$data, 'user_id' => auth()->id(), 'wallet_id' => $this->wallet()->id]); return back()->with('success', 'Kategori ditambahkan.'); }
    public function categoryUpdate(Request $request, Category $category) { $data = $request->validate(['name' => 'required|max:80']); $category->update($data); return back()->with('success', 'Kategori diperbarui.'); }
    public function categoryDestroy(Category $category) { if ($category->transactions()->exists()) $category->update(['is_active' => false]); else $category->delete(); return back()->with('success', $category->is_active ? 'Kategori dihapus.' : 'Kategori dinonaktifkan karena sudah dipakai.'); }
    public function profile() { return view('profile'); }
    public function profileUpdate(Request $request) { $data = $request->validate(['username' => ['required', 'alpha_dash', Rule::unique('users')->ignore(auth()->id())], 'password' => 'nullable|min:6|confirmed']); auth()->user()->update(array_filter($data)); return back()->with('success', 'Profil berhasil diperbarui.'); }
}
