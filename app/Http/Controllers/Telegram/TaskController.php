<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\UserTask;
use App\Models\User;
use App\Models\UserState;
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
            'status' => 'Choise status task for search',
            'callback_data' => 'task 1.... task 2....'
            // default     => 'Enter value:',
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
            return true;
        }
        $state->state = null;
        $state->waiting_for = null;
        $state->trigger_command = null;
        $state->save();
        return false;
    }

    protected function getActiveTask($user)
    {
        return Task::where('owner_id', $user->telegram_id)
            ->where('status', 'in_progress')
            ->get();
    }

    public function show_tasks($data, $page = '1') //rewrite with parsing
    {
        $state = UserState::firstOrCreate(['telegram_id' => $data['from']['id']]);
        // if($state->data !== [] || isset($state->data['status'])){
            $data['param'] = ['reply_markup' =>  ['inline_keyboard' => [
            [
                'text'          => '⬅️ Back',
                'callback_data' => 'tasks_list:' . intval($page) - 1, //if $page !== 1
            ],
            [
                'text'          => '➡️ Next',
                'callback_data' => 'tasks_list:' . intval($page) + 1, //if $page have in db
            ],
            ]]
        ];
        $user = User::where('telegram_id', $data['from']['id'])->first();

        $activeTasks = $this->getActiveTask($user);

        $data['message_send'] = '';
        foreach($activeTasks as $task){
            $data['message_send'] .= 'task: ' . $task->title;
        }
        // }
        // }
        // if($state->state !== 'wait'){
        $this->process($data, __FUNCTION__, ['status', 'callback_data']);
        // }

        $completedTaskIds = Task::where('status', 'completed') // limit 
            ->where('owner_id', $user->telegram_id)
            ->get();
        // showComplitedTask
        // or active ... or in_progress ... or complited

        $task = Task::orderBy('created_at', 'desc')
            ->forPage(intval($page), 6)
            ->get();
        // 
        // $message = 'task 1, task 2....';

        // $param = ['reply_markup' => ['inline_keyboard' => $keyboard]];
        // $response = $this->handleRequest($data, $message, $param);

        // if($response['ok'] === true){
            // $state->update([
            // 'telegram_id' => $data['from']['id'],
            // 'data' => [], 'waiting_for' => 'callback_data', 'trigger_command' => __FUNCTION__, 'state' => 'wait'
            // ]);
        // }
    }

    //      $user = User::where('telegram_id', $data['from']['id'])->first();

    // $activeTaskTitle = $this->getActiveTask($user)?->task?->title ?? 'There is no active task 😔';

    // $completedTaskIds = UserTask::where('status', 'Completed')
    //     ->where('user_id', $user->id)
    //     ->pluck('task_id');

    // $completedTasks = Task::whereIn('id', $completedTaskIds)->get();

    // $message = "📌 *Your current task:*\n";
    // $message .= $activeTaskTitle . "\n\n";

    // if ($completedTasks->isNotEmpty()) {
    //     $message .= "✅ *Your successfully tasks:*\n";
    //     foreach ($completedTasks as $task) {
    //         $message .= "▪️ " . $task->title . "\n";
    //     }
    // } else {
    //     $message .= "❌ You haven't completed any tasks yet. Start right now! 💪";
    // }

    // Http::post("https://api.telegram.org/bot" . env('TG_TOKEN') . "/sendMessage", [
    //     'chat_id' => $data['chat']['id'],
    //     'text' => $message,
    //     'parse_mode' => 'Markdown',
    // ]);

    // return response()->json(['text' => $message]);

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
