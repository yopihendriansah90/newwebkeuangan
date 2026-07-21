<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Support\Facades\Crypt;
class SystemSetting extends Model { protected $fillable=['key','value']; public static function read(string $key, ?string $fallback=null): ?string { $value=static::where('key',$key)->value('value'); if(!$value)return $fallback; try{return Crypt::decryptString($value);}catch(\Throwable){return $fallback;} } public static function write(string $key,string $value): void { static::updateOrCreate(['key'=>$key],['value'=>Crypt::encryptString($value)]); } }
