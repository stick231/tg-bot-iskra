<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\UserState;
use App\Services\TelegramServices;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    protected array $stateFields = ['title', 'category', 'datetime'];

    protected function promptForField(string $field): string
    {
        return match ($field) {
            'title'     => '📋 Enter your task title:',
            'category'  => '🏷 Enter a category to help you track tasks more easily:',
            'datetime'  => '⏰ Enter start and/or end date and time. 
Examples: 
- Only start: 14:30 | 15 14:30 | 2025.11.20 14:30
- Start and end: 2025.11.20 14:30 - 2025.11.20 16:00',
            'status'    => 'Choice status task for search',
            // 'callback_data' => '',   
        };
    }

    public function add_task($data, TelegramServices $telegramServices)
    {
        // return;
        $state = UserState::firstOrCreate(
            ['telegram_id' => $data['from']['id']],
            ['data' => [], 'waiting_for' => null, 'trigger_command' => null]
        );

        if($state->waiting_for == 'datetime'){
            try{
                $data['datetimeValue'] = $telegramServices->parseFlexibleDateTime($data['text']);
                Log::info($data['text']);
            } catch(\Exception $e){
                Log::error($e);
                return $this->handleRequest($data, 'Incorrect date/time format or this date/time is past. Try, for example: 14:30 or 15.10 16:00');
            }
        }
        
        return $this->process($data, $state, __FUNCTION__);
    }

    protected function onStateComplete(UserState $state)
    {
        if(isset($state->data['title'])){
            Task::create($state->data);
            $state->state = null;
            $state->waiting_for = null;
            $state->trigger_command = null;
            $state->save();
            return true;
        }
        return false;
    }

    protected function getActiveTask($user)
    {
        return Task::where('owner_id', $user->telegram_id)
            ->where('status', 'in_progress')
            ->get();
    }

    public function show_tasks($data, TelegramServices $telegramServices)
    {
        $state = UserState::firstOrCreate(
            ['telegram_id' => $data['from']['id']],
            ['data' => [], 'waiting_for' => null, 'trigger_command' => null]
        );

        $page = isset($data['data']) ? explode(':', $data['data'])[1] : 1;

        $data = $state->waiting_for === 'callback_data' ? $telegramServices->paginateTaskShow($data, $page, $state) : $data;
        if($state->waiting_for === null){ // change if
            $data['param'] = [
                'reply_markup' => json_encode([
                    'keyboard' => [
                        [['text' => 'In Progress'], ['text' => 'Completed']],
                    ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true, 
                ])
            ];
        };

        return $this->process($data, $state, __FUNCTION__, ['status', 'callback_data']);
    }

    public function complete_task($data, TelegramServices $telegramServices) 
    {
        if(isset($data['data']) && explode(':', $data['data'])[0] == 'TaskComplete'){   
            $idTask = explode(':', $data['data'])[1];

            $task = Task::find($idTask);

            if (!$task) {
                return [
                    'message_response' => '❌ Задача не найдена.'
                ];
            }
            $task->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);
            $task->save();
            

            return $this->handleRequest($data, '✅Task "' . $telegramServices->escapeMarkdownV2($task->title) . '" is marked as completed.');
        }

        $state = UserState::firstOrCreate(
            ['telegram_id' => $data['from']['id']],
            ['data' => [], 'waiting_for' => null, 'trigger_command' => null]
        );
        
        $state->waiting_for = 'callback_data';
        $state->trigger_command = 'complete_task';
        $state->state = 'wait';
        $state->save();

        $page = isset($data['data']) ? explode(':', $data['data'])[1] : 1;


        $data = $telegramServices->paginateCompleteTask($data, $page, $state);

        return $this->process($data, $state, __FUNCTION__);
    }
}
