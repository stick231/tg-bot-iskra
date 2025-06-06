<?php

use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook', [TelegramController::class, 'handle']);