<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\UserTask;
use App\Models\User;
use App\Models\UserState;
use App\Services\TelegramServices;
use Illuminate\Support\Facades\Http;
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
            'status' => 'Choice status task for search',
            // default     => 'test',
        };
    }

    public function add_task($data)
    {
        return $this->process($data, __FUNCTION__);
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
                'one_time_keyboard' => false, // если true — исчезает после нажатия
                ])
            ];
        };

        return $this->process($data, __FUNCTION__, ['status', 'callback_data']);
    }

    public function completed_task($data, $callbackQuery = false) //rewrite with parsing
    {
        return;
        $user = User::where("telegram_id", $data['from']['id'])->first();

        $userTask = $this->getActiveTask($user);

        if ($userTask) {
            $userTask->update(['status' => 'Completed', 'completed_at' => now()]);
            $task = Task::find($userTask->task_id);
            $message = "✅ *Task completed!*
            Great job! You completed the task: _{$task->title}_
            Keep up the good work and take on a new task with the command `/give_task`!";
        } else {
            $message = "⚠️ *Error:* You don't have an active task.
            Request a new task with the command `/give_task`!";
        }

        $chatId = !$callbackQuery ? $data['chat']['id'] : $data['message']['chat']['id'];

        Http::post("https://api.telegram.org/bot" . env('TG_TOKEN') . "/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);

        return response()->json(['text' => $message], 200);
    }
}
