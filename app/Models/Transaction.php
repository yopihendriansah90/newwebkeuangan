<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Transaction extends Model { protected $fillable = ['user_id','category_id','type','transaction_date','description','amount','receipt_path']; protected $casts = ['transaction_date' => 'date','amount' => 'decimal:2']; public function category() { return $this->belongsTo(Category::class); } }
