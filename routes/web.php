<?php
use App\Http\Controllers\AuthController; use App\Http\Controllers\FinanceController; use Illuminate\Support\Facades\Route;
Route::get('/', fn()=>auth()->check()?redirect('/dashboard'):view('auth.login'));
Route::middleware('guest')->group(function(){ Route::get('/login',[AuthController::class,'show'])->name('login'); Route::post('/login',[AuthController::class,'login']); });
Route::middleware('auth')->group(function(){
 Route::post('/logout',[AuthController::class,'logout'])->name('logout'); Route::get('/dashboard',[FinanceController::class,'index'])->name('dashboard'); Route::post('/transactions',[FinanceController::class,'store'])->name('transactions.store'); Route::delete('/transactions/{transaction}',[FinanceController::class,'destroy'])->name('transactions.destroy');
 Route::get('/categories',[FinanceController::class,'categories'])->name('categories'); Route::post('/categories',[FinanceController::class,'categoryStore'])->name('categories.store'); Route::patch('/categories/{category}',[FinanceController::class,'categoryUpdate'])->name('categories.update'); Route::delete('/categories/{category}',[FinanceController::class,'categoryDestroy'])->name('categories.destroy');
 Route::get('/profile',[FinanceController::class,'profile'])->name('profile'); Route::patch('/profile',[FinanceController::class,'profileUpdate'])->name('profile.update');
});
