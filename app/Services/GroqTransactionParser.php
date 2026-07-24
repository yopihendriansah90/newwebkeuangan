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
        $parsed = $this->decodeJsonResponse($content, 'Respons Groq bukan JSON yang valid.');
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

    public function parseImage(string $imageDataUrl, string $today, int $activeMonth, int $activeYear): array
    {
        $apiKey = SystemSetting::read('groq_api_key', config('services.groq.api_key'));
        if (!$apiKey) throw new RuntimeException('API key Groq belum dikonfigurasi.');

        $model = SystemSetting::read('groq_vision_model', config('services.groq.vision_model'));
        if ($model === 'meta-llama/llama-4-scout-17b-16e-instruct') $model = 'qwen/qwen3.6-27b';
        $response = Http::timeout(60)->withToken($apiKey)->post('https://api.groq.com/openai/v1/chat/completions', [
            'model' => $model,
            'temperature' => 0,
            'max_completion_tokens' => 1024,
            'response_format' => ['type' => 'json_object'],
            'messages' => [[
                'role' => 'system',
                'content' => 'Kamu adalah pembaca nota pembayaran dan parser transaksi keuangan Indonesia. Balas HANYA JSON valid tanpa markdown dengan field persis: intent, type, description, amount, date_expression, category, confidence, missing_fields. Baca tulisan cetak maupun tulisan tangan dengan hati-hati. Selalu anggap nota sebagai pengeluaran, kecuali jelas merupakan bukti pemasukan. amount adalah total pembayaran dalam Rupiah sebagai angka. Jika tanggal nota terbaca gunakan tanggal tersebut; jika tidak, gunakan hari ini. description ringkas dan informatif, misalnya "Belanja di Shinta Mart". category boleh Makanan, Belanja, Tagihan, Transportasi, atau kosong jika tidak yakin. Hari ini adalah '.$today.'. Bulan aktif adalah '.$activeMonth.' dan tahun aktif adalah '.$activeYear.'. Jika total tidak terbaca, amount harus 0 dan missing_fields berisi amount.',
            ], [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Baca nota ini dan siapkan transaksi untuk dikonfirmasi.'],
                    ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl]],
                ],
            ]],
        ]);
        if (!$response->successful()) throw new RuntimeException($response->json('error.message', 'Groq gagal membaca gambar nota.'));
        $content = $response->json('choices.0.message.content');
        $parsed = $this->decodeJsonResponse($content, 'Hasil pembacaan nota tidak valid.');
        $parsed['type'] = 'expense';
        $parsed['intent'] = 'create_transaction';
        $parsed['description'] ??= $parsed['keterangan'] ?? 'Pembayaran dari nota';
        $parsed['amount'] ??= $parsed['nominal'] ?? 0;
        $parsed['date_expression'] ??= $parsed['tanggal'] ?? 'hari ini';
        $parsed['category'] ??= $parsed['kategori'] ?? '';
        $parsed['missing_fields'] ??= [];
        return array_merge(['intent'=>'create_transaction','type'=>'expense','description'=>'Pembayaran dari nota','amount'=>0,'date_expression'=>'hari ini','category'=>'','confidence'=>0,'missing_fields'=>[]], $parsed);
    }

    private function decodeJsonResponse(mixed $content, string $error): array
    {
        if (is_array($content)) return $content;
        if (!is_string($content) || trim($content) === '') throw new RuntimeException($error);

        $json = trim($content);
        $json = preg_replace('/^```(?:json)?\s*/i', '', $json) ?? $json;
        $json = preg_replace('/\s*```$/', '', $json) ?? $json;

        // Toleransi jika model masih menambahkan kalimat pembuka/penutup.
        $start = strpos($json, '{');
        $end = strrpos($json, '}');
        if ($start !== false && $end !== false && $end >= $start) {
            $json = substr($json, $start, $end - $start + 1);
        }

        $parsed = json_decode($json, true);
        if (!is_array($parsed) || json_last_error() !== JSON_ERROR_NONE) throw new RuntimeException($error);
        return $parsed;
    }
}
