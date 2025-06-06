<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\UserTask;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    public function giveTask($data)
    {
        $user = User::where('telegram_id', $data['from']['id'])->first();
        $userTaskIds = UserTask::where('user_id', $user->id)->pluck('task_id')->toArray();
        $randomTask = Task::whereNotIn('id', $userTaskIds)->inRandomOrder()->first();

        if (!$randomTask) {
            $message = "🎉 *Congratulations!* You have completed all available tasks. 
Expect new tasks or try to complete something else on your own! 🚀";
        } else {
            if ($this->getActiveTask($user)) {
                $message = "📌 *Your current task:* 
                *" . $this->getActiveTask($user)->task->title . "*";
            } else {
                UserTask::create([
                    'task_id' => $randomTask->id,
                    'user_id' => $user->id,
                    'status' => 'In work'
                ]);
        
                $message = "🔥 *Your new task:*  
                *" . $randomTask->title . "*  
                Do it and don't forget to finish it with the command `/completed_task`!";
            }
        }
        
        $payload = [
            'chat_id' => $data['chat']['id'],
            'text' => $message,
            'parse_mode' => 'Markdown',
        ];
        
        if (!empty($randomtask)) {
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅', 'callback_data' => 'confirm_action']
                    ]
                ]
            ]);
        }
        
        Http::post("https://api.telegram.org/bot" . env('TG_TOKEN') . "/sendMessage", $payload);

        return response()->json(['text' => $message]);
    }
    
    protected function getActiveTask($user)
    {
        return UserTask::where('user_id', $user->id)
            ->where('status', 'In work')
            ->with('task')
            ->first();
    }

    public function showTask($data)
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
    

    public function changeTask()
    {

    }

    public function completedTask($data, $callbackQuery = false)
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
