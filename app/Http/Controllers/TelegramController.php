<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    public function handle(Request $request){
        $data = $request->all();

        if (isset($data['callback_query'])) {
            $callbackData = $data['callback_query']['data'];
    
             if ($callbackData === 'confirm_action') {
                app('App\Http\Controllers\Telegram\TaskController')->completedTask($data['callback_query'], true);
            }
    
            return response()->json(['status' => 'ok']); 
        }
        
        $text = $data['message']['text'] ?? '';

        return match ($text) {
            '/start' => app('App\Http\Controllers\Telegram\UserController')->start($data['message']),
            '/statistics' => app('App\Http\Controllers\Telegram\UserController')->statistics($data['message']),
            '/give_task' => app('App\Http\Controllers\Telegram\TaskController')->giveTask($data['message']),
            '/show_task' => app('App\Http\Controllers\Telegram\TaskController')->showTask($data['message']),
            '/changeTask' => app('App\Http\Controllers\Telegram\TaskController')->changeTask($data['message']),
            '/completed_task' => app('App\Http\Controllers\Telegram\TaskController')->completedTask($data['message']),
            '/customTask' => app('App\Http\Controllers\Telegram\TaskController')->customTask($data['message']),
            default => Http::post("https://api.telegram.org/bot" . env('TG_TOKEN') . "/sendMessage", ['chat_id' => $data['message']['chat']['id'],'text' => "Sorry, but i don't know such a command."]),
        };
    }
}
