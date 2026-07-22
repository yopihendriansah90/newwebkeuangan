<?php
namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GroqTransactionParser
{
    public function parse(string $text, string $today, int $activeMonth, int $activeYear): array
    {
        $text = str_ireplace(['jajajan', 'jajananan'], ['jajan', 'jajanan'], $text);
        $apiKey = SystemSetting::read('groq_api_key', config('services.groq.api_key'));
        if (!$apiKey) throw new RuntimeException('API key Groq belum dikonfigurasi.');

        $model = SystemSetting::read('groq_model', config('services.groq.model', 'llama-3.3-70b-versatile'));
        $response = Http::timeout(30)->withToken($apiKey)->post('https://api.groq.com/openai/v1/chat/completions', [
            // json_schema tidak didukung oleh semua model Groq. json_object
            // lebih kompatibel, sementara struktur detail tetap diarahkan lewat prompt.
            'model'=>$model, 'temperature'=>0, 'response_format'=>['type'=>'json_object'],
            'messages'=>[
                ['role'=>'system','content'=>'Kamu adalah parser transaksi keuangan pribadi berbahasa Indonesia. Balas HANYA satu JSON valid tanpa markdown dengan field persis: intent, type, description, amount, date_expression, category, confidence, missing_fields. intent harus create_transaction jika user mencatat uang, query jika bertanya, atau unknown. type harus income untuk uang masuk dan expense untuk uang keluar. Kata pemasukan: gaji, bonus, thr, terima, dapat, masuk, bayaran, freelance, penjualan. Kata pengeluaran: makan, jajan, beli, bayar, belanja, bensin, ongkos, pulsa, listrik, susu, sewa, cicilan. Kata transfer saja ambigu: type harus unknown dan missing_fields berisi type. Nominal harus berupa angka Rupiah, misalnya 10K menjadi 10000, 1,5 juta menjadi 1500000, 500.000 menjadi 500000. Pahami typo ringan seperti jajajan menjadi jajan. Deskripsi adalah inti kegiatan yang dibeli atau diterima; pada "jajajan susu pagi tadi 5k", description harus "susu" dan category harus "Makanan" atau "Minuman". Abaikan waktu seperti pagi, siang, sore, malam jika hanya menjelaskan waktu transaksi. Tanggal hanya angka berarti tanggal pada bulan aktif dan tahun aktif. Dukung hari ini, tadi, pagi tadi, tadi pagi, siang tadi, kemarin, tanggal 12, tanggal 5 bulan lalu, 12/09/2026, dan nama bulan Indonesia. Semua frasa yang mengandung "tadi" berarti hari ini, meskipun ada pagi/siang/sore/malam. Hari ini adalah '.$today.'. Bulan aktif adalah '.$activeMonth.' dan tahun aktif adalah '.$activeYear.'. Contoh JSON untuk "makan 10k": {"intent":"create_transaction","type":"expense","description":"makan","amount":10000,"date_expression":"hari ini","category":"Makanan","confidence":0.98,"missing_fields":[]}. Contoh JSON untuk "jajajan susu pagi tadi 5k": {"intent":"create_transaction","type":"expense","description":"susu","amount":5000,"date_expression":"hari ini","category":"Makanan","confidence":0.95,"missing_fields":[]}. Jika informasi penting tidak jelas, masukkan nama field-nya ke missing_fields.'],
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
        $parsed['description'] = str_ireplace(['jajajan', 'jajananan'], ['jajan', 'jajanan'], (string) $parsed['description']);
        $parsed['amount'] ??= $parsed['nominal'] ?? 0;
        $parsed['date_expression'] ??= $parsed['tanggal'] ?? '';
        $parsed['category'] ??= $parsed['kategori'] ?? '';
        $typeText = mb_strtolower((string) ($parsed['type'] ?? $parsed['jenis_transaksi'] ?? ''));
        if (in_array($typeText, ['pemasukan','masuk','income'], true)) $parsed['type'] = 'income';
        if (in_array($typeText, ['pengeluaran','keluar','expense'], true)) $parsed['type'] = 'expense';
        if (($parsed['type'] ?? 'unknown') === 'unknown' && in_array($parsed['intent'] ?? '', ['income', 'expense'], true)) {
            $parsed['type'] = $parsed['intent'];
            $parsed['intent'] = 'create_transaction';
        }
        return array_merge(['intent'=>'unknown','type'=>'unknown','description'=>'','amount'=>0,'date_expression'=>'','category'=>'','confidence'=>0,'missing_fields'=>[]], $parsed);
    }
}
