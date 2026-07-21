<?php
use App\Http\Controllers\AuthController; use App\Http\Controllers\FinanceController; use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\SettingsController;
Route::post('/telegram/webhook',[TelegramWebhookController::class,'handle'])->name('telegram.webhook');
Route::get('/', fn()=>auth()->check()?redirect('/dashboard'):view('auth.login'));
Route::middleware('guest')->group(function(){ Route::get('/login',[AuthController::class,'show'])->name('login'); Route::post('/login',[AuthController::class,'login']); });
Route::middleware('auth')->group(function(){
 Route::post('/logout',[AuthController::class,'logout'])->name('logout'); Route::get('/dashboard',[FinanceController::class,'index'])->name('dashboard'); Route::post('/transactions',[FinanceController::class,'store'])->name('transactions.store'); Route::delete('/transactions/{transaction}',[FinanceController::class,'destroy'])->name('transactions.destroy');
 Route::get('/categories',[FinanceController::class,'categories'])->name('categories'); Route::post('/categories',[FinanceController::class,'categoryStore'])->name('categories.store'); Route::patch('/categories/{category}',[FinanceController::class,'categoryUpdate'])->name('categories.update'); Route::delete('/categories/{category}',[FinanceController::class,'categoryDestroy'])->name('categories.destroy');
 Route::get('/members',[FinanceController::class,'members'])->name('members'); Route::post('/members',[FinanceController::class,'memberStore'])->name('members.store'); Route::delete('/members/{user}',[FinanceController::class,'memberDestroy'])->name('members.destroy');
 Route::get('/telegram',[FinanceController::class,'telegram'])->name('telegram'); Route::post('/telegram/pairing-code',[FinanceController::class,'telegramPairingCode'])->name('telegram.pairing-code'); Route::delete('/telegram/{connection}',[FinanceController::class,'telegramDisconnect'])->name('telegram.disconnect');
 Route::get('/settings',[SettingsController::class,'index'])->name('settings'); Route::post('/settings/credentials',[SettingsController::class,'saveCredentials'])->name('settings.credentials'); Route::post('/settings/telegram/sync',[SettingsController::class,'syncWebhook'])->name('settings.telegram.sync'); Route::post('/settings/telegram/remove-webhook',[SettingsController::class,'removeWebhook'])->name('settings.telegram.remove'); Route::post('/settings/telegram/test',[SettingsController::class,'testTelegram'])->name('settings.telegram.test'); Route::post('/settings/groq/test',[SettingsController::class,'testGroq'])->name('settings.groq.test');
 Route::get('/profile',[FinanceController::class,'profile'])->name('profile'); Route::patch('/profile',[FinanceController::class,'profileUpdate'])->name('profile.update');
});
