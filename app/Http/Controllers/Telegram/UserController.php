<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Models\UserTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    protected function promptForField(string $field): string
    {
        return "";
    }

    public function start($data)
    {
        $message = "👋 *Hello!*
I'm your personal assistant for self-development and motivation!

💡 *How can I help you?*
🔹 I'll give you *interesting tasks* on self-development, sports and other habits.
🔹 Track your progress and keep *detailed statistics*.
🔹 Support you and ensure that you meet your goals! 🚀

🎯 *Commands to help you get started:*
✅ *Get a task:* `/give_task`
📊 *View statistics:* `/statistics`
✔️ *Complete the current task:* `/completed_task`
📌 *View your task list:* `/show_task`

Ready to improve yourself? Let's start right now! 💪";



        User::register($data);
        $response = $this->handleRequest($data, $message);

        return response()->json(['status' => $response['status']]);
    }

    public function statistics($data)
    {
        return;
        if ($this->countCompletedTask($data) === 0) {
            $message = "📊 *Your stats are empty...*
            You haven't completed any tasks yet. But that's easy to fix! 🚀

            💡 *Try starting right now!*
            Get your first task with the command:`/give_task`";
        } else {
            $completedTasks = $this->countCompletedTask($data);
            $averageTime = $this->averageExecutionTimeTask($data);
            $topCategory = $this->greaterStatus($data);

            $performanceMessage = $completedTasks > 5
                ? "Great job! You're already making good progress towards your goal, keep up the good work! 💪🔥"
                : "Not enough yet... Try to devote more time to tasks, and you will succeed! 🚀";

            $message = "📊 *Your statistic*\n\n" .
                "✅ *Most popular category:* $topCategory\n\n" .
                "⏳ *Average task completion time:* $averageTime\n\n" .
                "🏆 *Completed task:* $completedTasks\n\n" .
                "$performanceMessage\n\n" .
                "Keep completing the tasks and you will see your progress! 💡\n\n" .
                "To get a new task, use the command: `/give_task`";
        }

        $this->handleRequest($data, $message);
    }

    protected function averageExecutionTimeTask($data)
    {
        $user = User::where('telegram_id', $data['from']['id'])->first();
        $userTask = UserTask::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('status', 'Completed')
            ->get();

        $difference = 0;

        if (count($userTask) > 0) {
            foreach ($userTask as $task) {
                $completedAt = Carbon::parse($task->completed_at);
                $createdAt = Carbon::parse($task->created_at);

                $difference = $createdAt->diffInMinutes($completedAt);
            }

            $averageTime = $difference / count($userTask);
        } else {
            return null;
        }

        Log::info($averageTime < 1
            ? ($averageTime * 60) . " seconds"
            : ($averageTime < 960
                ? "$averageTime minutes"
                : ($averageTime / 60) . " hours"
            ));
        return $averageTime < 1
            ? ($averageTime * 60) . " seconds"
            : ($averageTime < 960
                ? "$averageTime minutes"
                : ($averageTime / 60) . " hours"
            );
    }

    protected function countCompletedTask($data)
    {
        $user = User::where('telegram_id', $data['from']['id'])->first();
        $userTask = UserTask::where('user_id', $user->id)
            ->where('status', 'сompleted')
            ->get();
        return count($userTask);
    }

    protected function greaterStatus($data)
    {
        return Task::select('category')
            ->withCount(['userTask' => function ($query) use ($data) {
                $query->whereNotNull('completed_at');
                $query->where('user_id', $data['from']['id']);
            }])
            ->orderByDesc('user_task_count')
            ->first()?->category;
    }
}
