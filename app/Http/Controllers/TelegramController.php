<?php

namespace App\Http\Controllers;

use App\Models\UserState;
use App\Services\TelegramCommandLoader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    protected function promptForField(string $field):string 
    {
        return "";
    }

    protected array $commands = [];
    
    public function __construct(TelegramCommandLoader $loader)
    {
        $this->commands = $loader->load();
    }

    public function handle(Request $request){
        $data = $request->all();
        
        if(isset($data['my_chat_member'])){
            return;
        }
        
        $text = $data['edited_message']['text'] ?? $data['message']['text'] ?? '';
        $dataMessage = $data['message'] ?? $data['edited_message'] ?? $data['callback_query'] ?? null; 

        $userState = UserState::firstOrCreate(['telegram_id' => $dataMessage['from']['id']]);
        
        if (isset($this->commands[$text])) {
            // input commands auth check, redirect to command /start
            $userState->update(['state' => null, 'trigger_command' => null, 'waiting_for' => null, 'data'=> []]);
            return $this->dispatchCommand($text, $dataMessage);
        } elseif(isset($userState) || $userState->state === 'wait'){
            $this->dispatchCommand("/" . $userState->trigger_command, $dataMessage);
        }
        //check state and wait message
        
    }
    
    protected function dispatchCommand(string $command, $data)
    {
        if ($command === '/') {
            return '';
        }
    
        $method = $this->commands[$command] ?? null;
    
        if ($method === null) {
            return '';
        }
    
        $controller = app()->make($method);
    
        $methodName = ltrim($command, '/');
    
        return $controller->{$methodName}($data);
    }
}
