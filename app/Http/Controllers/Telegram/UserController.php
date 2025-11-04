<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    protected function promptForField(string $field): string
    {
        return "";
    }

    public function start($data)
    {
        $message = "📋 *Task Tracker Bot*

👋 *Привет!*  
Я — твой личный помощник для управления задачами и контроля прогресса.

💡 *Что я умею:*  
🔹 Помогаю создавать и организовывать задачи.  
🔹 Напоминаю о дедлайнах и невыполненных делах.  
🔹 Отслеживаю выполненные задачи и показываю статистику.  
🔹 Помогаю держать фокус и не терять мотивацию! 🚀

🎯 *Основные команды:*  
➕ *Добавить задачу:* `/add_task`  
📋 *Список задач:* `/show_tasks`  
✔️ *Отметить выполненной:* `/completed_task`  
📊 *Статистика и прогресс:* `/statistics`

Готов навести порядок в делах?  
*Начнём прямо сейчас!* 💪";



        User::register($data);
        $response = $this->handleRequest($data, $message);

        return response()->json(['status' => $response['status']]);
    }

    public function statistics($data)
    {
        $completedTasks = $this->countCompletedTask($data);

        if ($completedTasks === 0) {
            $message = "📊 *Ваша статистика пуста...*\n" .
                "Вы ещё не выполнили ни одной задачи, но это легко исправить! 🚀\n\n" .
                "💡 *Начните формировать полезные привычки прямо сейчас!*\n" .
                "Создайте первую задачу с помощью команды: `/give_task`";
        } else {
            $averageTime = $this->averageExecutionTimeTask($data);
            $topCategory = $this->greaterStatus($data);
        
            $performanceMessage = $completedTasks > 5
                ? "🔥 *Отличная работа!* Вы уже делаете хорошие шаги к своим целям — продолжайте в том же духе!"
                : "🚀 Пока что мало выполненных задач... Постарайтесь выполнить несколько, и вы увидите прогресс!";
        
            $message = "📊 *Ваша статистика*\n\n" .
                "✅ *Самая популярная категория:* {$topCategory}\n" .
                "⏳ *Среднее время выполнения задачи:* {$averageTime}\n" .
                "🏆 *Выполнено задач:* {$completedTasks}\n\n" .
                "{$performanceMessage}\n\n" .
                "💡 Продолжайте выполнять задачи и следите за своим прогрессом!\n\n" .
                "Чтобы получить новую задачу, используйте команду: `/give_task`";
        }
        
        $this->handleRequest($data, $message);
    }

    protected function averageExecutionTimeTask($data)
    {
        $user = User::where('telegram_id', $data['from']['id'])->first();
        if (!$user) return "User not found";

        $userTasks = Task::where('owner_id', $user->telegram_id)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->get();

        if ($userTasks->isEmpty()) {
            return "No completed tasks yet";
        }

        $totalDifference = 0;

        foreach ($userTasks as $task) {
            $createdAt = Carbon::parse($task->created_at);
            $completedAt = Carbon::parse($task->completed_at);
            $totalDifference += $createdAt->diffInMinutes($completedAt);
        }

        $averageMinutes = $totalDifference / $userTasks->count();

        return $this->formatTime($averageMinutes);
    }

    protected function formatTime(float $minutes): string
    {
        if ($minutes < 1) {
            return round($minutes * 60) . " seconds";
        } elseif ($minutes < 60) {
            return round($minutes) . " minutes";
        } elseif ($minutes < 1440) {
            $hours = floor($minutes / 60);
            $mins = $minutes % 60;
            return $mins > 0
                ? "{$hours} h " . round($mins) . " min"
                : "{$hours} h";
        } else {
            $days = floor($minutes / 1440);
            $hours = round(($minutes % 1440) / 60);
            return $hours > 0
                ? "{$days} d {$hours} h"
                : "{$days} d";
        }
    }

    protected function countCompletedTask($data): int
    {
        $user = User::where('telegram_id', $data['from']['id'])->first();
        $userTasks = Task::where('owner_id', $user->telegram_id)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->get();
        return count($userTasks);
    }

    protected function greaterStatus($data): ?string
    {
        $user = User::where('telegram_id', $data['from']['id'])->first();
        if (!$user) return null;

        return Task::select('category')
            ->where('owner_id', $user->telegram_id)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->groupBy('category')
            ->orderByRaw('COUNT(*) DESC')
            ->first()?->category ?? 'Unknown';
    }
}
