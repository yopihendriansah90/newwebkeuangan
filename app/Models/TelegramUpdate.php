<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TelegramUpdate extends Model { protected $fillable = ['update_id','chat_id','processed_at']; protected $casts = ['processed_at'=>'datetime']; }
