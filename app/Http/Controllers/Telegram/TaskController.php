<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\UserTask;
use App\Models\User;
use App\Models\UserState;
use App\Services\TaskServives;
use App\Services\TelegramServices;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{

    protected array $stateFields = ['title', 'category', 'remind_at'];

    protected function promptForField(string $field): string
    {
        return match ($field) {
            'title'     => '📋 Enter your task title:',
            'category'  => '🏷 Enter a category to help you track tasks more easily:',
            'remind_at' => '⏰ Enter reminder date and time (e.g. 2025‑06‑15 14:00):',
            'status'    => 'Choice status task for search'
        };
    }

    public function add_task($data)
    {
        $state = UserState::firstOrCreate(
            ['telegram_id' => $data['from']['id']],
            ['data' => [], 'waiting_for' => null, 'trigger_command' => null]
        );

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
                'resize_keyboard' => true,  // клавиатура подгоняется под экран
                'one_time_keyboard' => true, // если true — исчезает после нажатия
                ])
            ];
        };

        return $this->process($data, $state, __FUNCTION__, ['status', 'callback_data']);
    }

    public function complete_task($data, TelegramServices $telegramServices) //rewrite with parsing
    {
        if(isset($data['data'])){
            $idTask = explode(':', $data['data'])[1];

            $task = Task::find($idTask);

            if (!$task) {
                return [
                    'message_response' => '❌ Задача не найдена.'
                ];
            }

            $task->update([
                'status' => 'completed',
                'completed_at' => now() // если есть поле даты завершения
            ]);

            $this->handleRequest($data, '✅Task "' . $task->title . '" is marked as completed.');
            return;
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
