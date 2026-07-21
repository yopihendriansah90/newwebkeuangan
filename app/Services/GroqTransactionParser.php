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
        $schema = ['type'=>'object','properties'=>[
            'intent'=>['type'=>'string','enum'=>['create_transaction','query','unknown']],
            'type'=>['type'=>'string','enum'=>['income','expense','unknown']],
            'description'=>['type'=>'string'], 'amount'=>['type'=>'number'],
            'date_expression'=>['type'=>'string'], 'category'=>['type'=>'string'],
            'confidence'=>['type'=>'number'], 'missing_fields'=>['type'=>'array','items'=>['type'=>'string']],
        ],'required'=>['intent','type','description','amount','date_expression','category','confidence','missing_fields'],'additionalProperties'=>false];
        $response = Http::timeout(30)->withToken($apiKey)->post('https://api.groq.com/openai/v1/chat/completions', [
            'model'=>$model, 'temperature'=>0, 'response_format'=>['type'=>'json_schema','json_schema'=>['name'=>'transaction_parser','strict'=>true,'schema'=>$schema]],
            'messages'=>[
                ['role'=>'system','content'=>'Kamu adalah parser transaksi keuangan pribadi berbahasa Indonesia. Jangan menyimpan data dan jangan memberi penjelasan di luar JSON. Pahami typo ringan. Jenis income untuk uang masuk dan expense untuk uang keluar. Nominal harus angka Rupiah tanpa titik atau simbol. Jika tanggal hanya berupa angka hari, gunakan bulan aktif dan tahun aktif. Hari ini adalah '.$today.'. Bulan aktif adalah '.$activeMonth.' dan tahun aktif adalah '.$activeYear.'. Jika bukan transaksi, gunakan intent query atau unknown. Jika data penting tidak jelas, masukkan ke missing_fields.'],
                ['role'=>'user','content'=>$text],
            ],
        ]);
        if (!$response->successful()) throw new RuntimeException($response->json('error.message','Groq gagal memproses pesan.'));
        $content = $response->json('choices.0.message.content');
        $parsed = is_string($content) ? json_decode($content, true) : $content;
        if (!is_array($parsed) || json_last_error() !== JSON_ERROR_NONE) throw new RuntimeException('Respons Groq bukan JSON yang valid.');
        return array_merge(['intent'=>'unknown','type'=>'unknown','description'=>'','amount'=>0,'date_expression'=>'','category'=>'','confidence'=>0,'missing_fields'=>[]], $parsed);
    }
}
