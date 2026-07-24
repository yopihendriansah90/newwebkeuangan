<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\PendingTransaction;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TelegramReportService
{
    public function __construct(private FinancialAnalysisService $analysis) {}

    public function respond(Wallet $wallet, string $input, Carbon $now): ?string
    {
        // Saat ada field transaksi yang sedang diedit, jangan anggap pesan
        // seperti "pemasukan" atau "kemarin" sebagai permintaan laporan.
        if ($this->hasPendingEdit()) return null;
        $text = mb_strtolower(trim($input));
        if (!$this->isReportRequest($text)) return null;

        [$from, $to, $period] = $this->period($text, $now);

        if (str_contains($text, 'analisa') || str_contains($text, 'insight') || str_contains($text, 'kondisi keuangan')) {
            return $this->analysisText($wallet, $from, $to, $period);
        }

        if (preg_match('/^(\/saldo|saldo|sisa uang|saldo sekarang)/', $text)) {
            return $this->summary($wallet, $from, $to, $period, false);
        }
        if (str_contains($text, 'total pemasukan') || str_contains($text, 'pemasukan') && !preg_match('/\d+(?:[.,]\d+)?\s*(k|rb|ribu|jt|juta)?\b/', $text) || preg_match('/^\/pemasukan/', $text)) {
            return $this->total($wallet, $from, $to, $period, 'income', 'Pemasukan');
        }
        if (str_contains($text, 'total pengeluaran') || str_contains($text, 'pengeluaran') && !preg_match('/\d+(?:[.,]\d+)?\s*(k|rb|ribu|jt|juta)?\b/', $text) || preg_match('/^\/pengeluaran/', $text)) {
            return $this->total($wallet, $from, $to, $period, 'expense', 'Pengeluaran');
        }

        if (str_contains($text, 'bandingkan') || str_contains($text, 'dibandingkan') || str_contains($text, 'naik berapa') || str_contains($text, 'turun berapa')) {
            return $this->compare($wallet, $now);
        }

        if (str_contains($text, 'terbaru') || str_contains($text, 'terakhir') || str_contains($text, 'paling baru') || preg_match('/^\/transaksi/', $text)) {
            return $this->latest($wallet, $from, $to, $period);
        }

        if (preg_match('/^(\/cari|cari|tampilkan transaksi dengan|pernah bayar)\s+(.+)/', $text, $match)) {
            return $this->search($wallet, trim($match[2]), $from, $to, $period);
        }

        if (str_contains($text, 'kategori') || str_contains($text, 'paling banyak') || str_contains($text, 'terbesar')) {
            return $this->byCategory($wallet, $from, $to, $period, str_contains($text, 'pemasukan'));
        }

        return $this->summary($wallet, $from, $to, $period, true);
    }

    private function hasPendingEdit(): bool
    {
        $chatId = (string) request()->input('message.chat.id', '');
        if ($chatId === '') return false;

        return PendingTransaction::where('chat_id', $chatId)
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->contains(fn (PendingTransaction $pending): bool => $pending->state() === 'editing');
    }

    private function isReportRequest(string $text): bool
    {
        if (preg_match('/^\/(saldo|laporan|analisa|ringkasan|transaksi|pengeluaran|pemasukan|cari)(?:@\w+)?(?:\s|$)/', $text)) return true;
        if (preg_match('/\b(analisa|insight|kondisi keuangan|saldo|sisa uang|laporan|ringkasan|pemasukan|pengeluaran|total pemasukan|total pengeluaran|transaksi terbaru|transaksi terakhir|paling banyak|bandingkan|dibandingkan|bulan ini|bulan lalu|minggu ini|hari ini|pengeluaran terbesar|pemasukan terbesar|cari transaksi|pernah bayar)\b/', $text)) {
            return !preg_match('/\d+(?:[.,]\d+)?\s*(k|rb|ribu|jt|juta)?\b/', $text);
        }
        return false;
    }

    private function period(string $text, Carbon $now): array
    {
        if (str_contains($text, 'kemarin')) {
            $day = $now->copy()->subDay();
            return [$day->copy()->startOfDay(), $day->copy()->endOfDay(), 'Kemarin'];
        }
        if (str_contains($text, 'hari ini')) return [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'Hari ini'];
        if (str_contains($text, 'minggu ini')) return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek(), 'Minggu ini'];
        if (str_contains($text, 'bulan lalu')) {
            $month = $now->copy()->subMonthNoOverflow();
            return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth(), 'Bulan lalu'];
        }
        if (preg_match('/\b(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)(?:\s+(20\d{2}))?/', $text, $match)) {
            $months = ['januari'=>1,'februari'=>2,'maret'=>3,'april'=>4,'mei'=>5,'juni'=>6,'juli'=>7,'agustus'=>8,'september'=>9,'oktober'=>10,'november'=>11,'desember'=>12];
            $month = Carbon::create((int)($match[2] ?? $now->year), $months[$match[1]], 1);
            return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth(), $month->translatedFormat('F Y')];
        }
        return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'Bulan ini'];
    }

    private function query(Wallet $wallet, Carbon $from, Carbon $to)
    {
        return Transaction::with('category')->where('wallet_id', $wallet->id)->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()]);
    }

    private function summary(Wallet $wallet, Carbon $from, Carbon $to, string $period, bool $withLatest): string
    {
        $rows = $this->query($wallet, $from, $to)->get();
        $income = (float) $rows->where('type', 'income')->sum('amount');
        $expense = (float) $rows->where('type', 'expense')->sum('amount');
        $result = "Ringkasan {$period}\n\nPemasukan: Rp ". $this->money($income) ."\nPengeluaran: Rp ". $this->money($expense) ."\nSisa: Rp ". $this->money($income - $expense);
        if ($withLatest && $rows->isNotEmpty()) $result .= "\n\nTransaksi: {$rows->count()} catatan";
        return $result;
    }

    private function total(Wallet $wallet, Carbon $from, Carbon $to, string $period, string $type, string $label): string
    {
        $total = (float) $this->query($wallet, $from, $to)->where('type', $type)->sum('amount');
        return "Total {$label} {$period}: Rp ".$this->money($total);
    }

    private function analysisText(Wallet $wallet, Carbon $from, Carbon $to, string $period): string
    {
        $data = $this->analysis->analyze($wallet, $from, $to);
        $result = "Analisa {$period}\n\nPemasukan: Rp ".$this->money($data['income'])."\nPengeluaran: Rp ".$this->money($data['expense'])."\nSisa saldo: Rp ".$this->money($data['balance'])."\nJumlah transaksi: {$data['count']}\nRata-rata pengeluaran/hari: Rp ".$this->money($data['average_daily_expense']);
        if ($data['largest_category']) $result .= "\n\nKategori terbesar: {$data['largest_category']} (Rp ".$this->money($data['largest_category_amount']).')';
        if ($data['largest_transaction']) $result .= "\nTransaksi terbesar: {$data['largest_transaction']->description} (Rp ".$this->money($data['largest_transaction']->amount).')';
        return $result;
    }

    private function latest(Wallet $wallet, Carbon $from, Carbon $to, string $period): string
    {
        $rows = $this->query($wallet, $from, $to)->latest('transaction_date')->latest('id')->limit(5)->get();
        if ($rows->isEmpty()) return "Belum ada transaksi untuk {$period}.";
        $lines = ["Transaksi terbaru ({$period})"];
        foreach ($rows as $row) $lines[] = $this->line($row);
        return implode("\n", $lines);
    }

    private function byCategory(Wallet $wallet, Carbon $from, Carbon $to, string $incomeMode): string
    {
        $type = $incomeMode ? 'income' : 'expense';
        $rows = $this->query($wallet, $from, $to)->where('type', $type)->get()->groupBy(fn ($row) => $row->category?->name ?? 'Tanpa kategori');
        if ($rows->isEmpty()) return 'Belum ada data kategori untuk periode tersebut.';
        $lines = ['Ringkasan kategori '.($incomeMode ? 'pemasukan' : 'pengeluaran')];
        foreach ($rows->sortByDesc(fn (Collection $items) => $items->sum('amount'))->take(10) as $name => $items) $lines[] = "• {$name}: Rp ".$this->money($items->sum('amount'));
        return implode("\n", $lines);
    }

    private function search(Wallet $wallet, string $keyword, Carbon $from, Carbon $to, string $period): string
    {
        $keyword = trim(preg_replace('/\b(bulan ini|bulan lalu|hari ini|minggu ini)\b/', '', $keyword));
        $rows = $this->query($wallet, $from, $to)->where(function ($query) use ($keyword) { $query->where('description', 'like', '%'.$keyword.'%')->orWhereHas('category', fn ($category) => $category->where('name', 'like', '%'.$keyword.'%')); })->latest('transaction_date')->limit(10)->get();
        if ($rows->isEmpty()) return "Tidak menemukan transaksi dengan kata kunci '{$keyword}' untuk {$period}.";
        $lines = ["Hasil pencarian '{$keyword}' ({$period})"];
        foreach ($rows as $row) $lines[] = $this->line($row);
        return implode("\n", $lines);
    }

    private function compare(Wallet $wallet, Carbon $now): string
    {
        $current = $now->copy()->startOfMonth(); $previous = $current->copy()->subMonthNoOverflow();
        $currentRows = $this->query($wallet, $current, $current->copy()->endOfMonth())->get();
        $previousRows = $this->query($wallet, $previous, $previous->copy()->endOfMonth())->get();
        $currentExpense = (float)$currentRows->where('type','expense')->sum('amount'); $previousExpense = (float)$previousRows->where('type','expense')->sum('amount');
        $difference = $currentExpense - $previousExpense; $direction = $difference > 0 ? 'lebih besar' : ($difference < 0 ? 'lebih kecil' : 'sama');
        return "Perbandingan pengeluaran\n\nBulan ini: Rp ".$this->money($currentExpense)."\nBulan lalu: Rp ".$this->money($previousExpense)."\n\nPengeluaran bulan ini {$direction} Rp ".$this->money(abs($difference)).'.';
    }

    private function line(Transaction $row): string
    {
        $sign = $row->type === 'income' ? '+' : '-'; $date = $row->transaction_date->format('d/m/Y');
        return "• {$date} {$row->description} ({$row->category?->name}) {$sign} Rp ".$this->money($row->amount);
    }

    private function money(float|int|string $amount): string { return number_format((float)$amount, 0, ',', '.'); }
}
