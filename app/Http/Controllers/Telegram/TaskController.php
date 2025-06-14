<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\UserTask;
use App\Models\User;
use App\Models\UserState;
use Illuminate\Support\Facades\Http;

class TaskController extends Controller
{

    protected array $stateFields = ['title', 'category', 'remind_at'];

    protected function promptForField(string $field): string
    {
        return match ($field) {
            'title'     => '📋 Enter your task title:',
            'category'  => '🏷 Enter a category to help you track tasks more easily:',
            'remind_at' => '⏰ Enter reminder date and time (e.g. 2025‑06‑15 14:00):',
            default     => 'Enter value:',
        };
    }

    public function add_task($data)
    {
        return $this->process($data, __FUNCTION__);
    }

    protected function onStateComplete(UserState $state): void
    {
        Task::create($state->data);
        $state->state = null;
        $state->waiting_for = null;
        $state->trigger_command = null;
        $state->save();
    }
    
    protected function getActiveTask($user)
    {
        return UserTask::where('user_id', $user->id)
            ->where('status', 'In work')
            ->with('task')
            ->get();
    }

    public function show_task($data) //rewrite with parsing
    {
        $user = User::where('telegram_id', $data['from']['id'])->first();
    
        $activeTaskTitle = $this->getActiveTask($user)?->task?->title ?? 'There is no active task 😔';
        
        $completedTaskIds = UserTask::where('status', 'Completed')
            ->where('user_id', $user->id)
            ->pluck('task_id');
    
        $completedTasks = Task::whereIn('id', $completedTaskIds)->get();
    
        $message = "📌 *Your current task:*\n";
        $message .= $activeTaskTitle . "\n\n";
    
        if ($completedTasks->isNotEmpty()) {
            $message .= "✅ *Your successfully tasks:*\n";
            foreach ($completedTasks as $task) {
                $message .= "▪️ " . $task->title . "\n";
            }
        } else {
            $message .= "❌ You haven't completed any tasks yet. Start right now! 💪";
        }

        Http::post("https://api.telegram.org/bot" . env('TG_TOKEN') . "/sendMessage", [
            'chat_id' => $data['chat']['id'],
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);
    
        return response()->json(['text' => $message]);
    }
    
    public function completed_task($data, $callbackQuery = false)//rewrite with parsing
    {
        $user = User::where("telegram_id", $data['from']['id'])->first();

        $userTask = $this->getActiveTask($user);

        if($userTask){
            $userTask->update(['status' => 'Completed', 'completed_at' => now()]);
            $task = Task::find($userTask->task_id);
            $message = "✅ *Task completed!*  
            Great job! You completed the task: _{$task->title}_  
            Keep up the good work and take on a new task with the command `/give_task`!";
        }else{
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
