<?php
namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class TransactionInputNormalizer
{
    public function amount(mixed $value): int
    {
        $text = strtolower(trim((string) $value));
        if ($text === '') throw new InvalidArgumentException('Nominal belum ditemukan.');
        $text = str_replace(['setengah juta','1/2 juta','sejuta'], ['0.5jt','0.5jt','1jt'], $text);
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(k|rb|ribu|jt|juta)?\s*$/i', $text, $match) !== 1) throw new InvalidArgumentException('Nominal tidak dikenali.');
        $rawNumber = $match[1]; $suffix = strtolower($match[2] ?? '');
        $number = $suffix === '' ? (float) preg_replace('/[^0-9]/', '', $rawNumber) : (float) str_replace(',', '.', $rawNumber);
        $multiplier = in_array($suffix, ['k','rb','ribu'], true) ? 1000 : (in_array($suffix, ['jt','juta'], true) ? 1000000 : 1);
        $amount = (int) round($number * $multiplier);
        if ($amount < 1) throw new InvalidArgumentException('Nominal harus lebih besar dari nol.');
        return $amount;
    }

    public function date(?string $expression, Carbon $now, int $activeMonth, int $activeYear): string
    {
        $value = strtolower(trim((string) $expression));
        $value = str_replace(['januari','februari','maret','april','mei','juni','juli','agustus','september','oktober','november','desember'], ['january','february','march','april','may','june','july','august','september','october','november','december'], $value);
        $value = preg_replace_callback('/\b(jan|feb|mar|apr|jun|jul|agu|ags|sep|okt|nov|des)\b/i', static fn (array $match): string => ['jan'=>'january','feb'=>'february','mar'=>'march','apr'=>'april','jun'=>'june','jul'=>'july','agu'=>'august','ags'=>'august','sep'=>'september','okt'=>'october','nov'=>'november','des'=>'december'][strtolower($match[1])] ?? $match[1], $value) ?? $value;
        $value = preg_replace('/^(tanggal|tgl)\s+/i', '', $value);
        if ($value === '' || in_array($value, ['hari ini','hariini','today'], true)) return $now->toDateString();
        if (in_array($value, ['tadi','sekarang'], true) || str_contains($value, 'tadi')) return $now->toDateString();
        if (in_array($value, ['kemarin','kemaren','yesterday'], true)) return $now->copy()->subDay()->toDateString();
        if ($value === 'besok') return $now->copy()->addDay()->toDateString();
        if (preg_match('/^(\d+)\s*hari\s*(yang\s*)?lalu$/', $value, $match)) return $now->copy()->subDays((int) $match[1])->toDateString();
        if (preg_match('/^(\d{1,2})\s+bulan\s+lalu$/', $value, $match)) { $date=$now->copy()->startOfMonth()->subMonthNoOverflow(); $day=(int)$match[1]; if (checkdate($date->month,$day,$date->year)) return $date->setDay($day)->toDateString(); }
        foreach (['Y-m-d','d/m/Y','d-m-Y','d.m.Y'] as $format) { try { return Carbon::createFromFormat($format, $value)->toDateString(); } catch (\Throwable) {} }
        if (preg_match('/^(\d{1,2})\s+(january|february|march|april|may|june|july|august|september|october|november|december)(?:\s+(\d{4}))?$/i', $value, $match)) {
            $year = (int) ($match[3] ?? $activeYear);
            try { return Carbon::parse($match[1].' '.$match[2].' '.$year)->toDateString(); } catch (\Throwable) {}
        }
        foreach (['d/m','d-m','d.m'] as $format) { try { return Carbon::createFromFormat($format, $value)->setYear($activeYear)->toDateString(); } catch (\Throwable) {} }
        if (preg_match('/^\d{1,2}$/', $value)) { $day=(int)$value; if (checkdate($activeMonth,$day,$activeYear)) return Carbon::create($activeYear,$activeMonth,$day)->toDateString(); }
        throw new InvalidArgumentException('Tanggal tidak dikenali.');
    }
}
