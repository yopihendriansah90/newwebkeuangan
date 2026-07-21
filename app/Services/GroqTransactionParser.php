<?php
namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GroqTransactionParser
{
    public function parse(string $text, string $today, int $activeMonth, int $activeYear): array
    {
        $apiKey = SystemSetting::read('groq_api_key', config('services.groq.api_key'));
        if (!$apiKey) throw new RuntimeException('API key Groq belum dikonfigurasi.');

        $model = SystemSetting::read('groq_model', config('services.groq.model', 'llama-3.3-70b-versatile'));
        $response = Http::timeout(30)->withToken($apiKey)->post('https://api.groq.com/openai/v1/chat/completions', [
            // json_schema tidak didukung oleh semua model Groq. json_object
            // lebih kompatibel, sementara struktur detail tetap diarahkan lewat prompt.
            'model'=>$model, 'temperature'=>0, 'response_format'=>['type'=>'json_object'],
            'messages'=>[
                ['role'=>'system','content'=>'Kamu adalah parser transaksi keuangan pribadi berbahasa Indonesia. Jangan menyimpan data dan jangan memberi penjelasan di luar JSON. Pahami typo ringan. Jenis income untuk uang masuk dan expense untuk uang keluar. Nominal harus angka Rupiah tanpa titik atau simbol. Jika tanggal hanya berupa angka hari, gunakan bulan aktif dan tahun aktif. Hari ini adalah '.$today.'. Bulan aktif adalah '.$activeMonth.' dan tahun aktif adalah '.$activeYear.'. Jika bukan transaksi, gunakan intent query atau unknown. Jika data penting tidak jelas, masukkan ke missing_fields.'],
                ['role'=>'user','content'=>$text],
            ],
        ]);
        if (!$response->successful()) throw new RuntimeException($response->json('error.message','Groq gagal memproses pesan.'));
        $content = $response->json('choices.0.message.content');
        $parsed = is_string($content) ? json_decode($content, true) : $content;
        if (!is_array($parsed) || json_last_error() !== JSON_ERROR_NONE) throw new RuntimeException('Respons Groq bukan JSON yang valid.');
        // Beberapa model mengembalikan label berbahasa Indonesia meskipun
        // prompt meminta nama field internal berbahasa Inggris.
        $parsed['description'] ??= $parsed['jenis'] ?? $parsed['keterangan'] ?? '';
        $parsed['amount'] ??= $parsed['nominal'] ?? 0;
        $parsed['date_expression'] ??= $parsed['tanggal'] ?? '';
        $parsed['category'] ??= $parsed['kategori'] ?? '';
        if (($parsed['type'] ?? 'unknown') === 'unknown' && in_array($parsed['intent'] ?? '', ['income', 'expense'], true)) {
            $parsed['type'] = $parsed['intent'];
            $parsed['intent'] = 'create_transaction';
        }
        return array_merge(['intent'=>'unknown','type'=>'unknown','description'=>'','amount'=>0,'date_expression'=>'','category'=>'','confidence'=>0,'missing_fields'=>[]], $parsed);
    }
}
