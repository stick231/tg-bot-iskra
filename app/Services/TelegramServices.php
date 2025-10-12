<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TelegramServices{
    protected $arrayVal = [
        'In Progress' => 'in_progress',
        'Completed' => 'completed'
    ];

    private $keyboardValArr = [
         'show_tasks' => ['textBack' => '⬅️ Back', 'textNext' => '➡️ Next', 'callback_data' => 'tasks_list']
    ];

    public function paginateTaskShow($data, $page, $state)
    {
        $user = User::where('telegram_id', $data['from']['id'])->first();
        $page = max(1, (int)$page);

        $status = isset($data['text']) && isset($this->arrayVal[$data['text']]) ? $this->arrayVal[$data['text']] : $this->arrayVal[$state->data['status']];

        $tasks = Task::where('owner_id', $user->telegram_id)
                     ->where('status', $status)
                     ->orderBy($status === 'in_progress' ? 'remind_at' : 'completed_at', 'desc')
                     ->paginate(6, ['*'], 'page', $page);

                     
        $message = $this->messageShowTask($tasks, $page, $status);
        $param = $this->dashboardTaskShow($tasks, $state, $page);
        
        $data['param'] = $param;
        $data['message_response'] = $message;
        return $data; 
    }
    protected function messageShowTask($tasks, $page, $status)
    {
        $message = "*📋 Your " . ucfirst(array_search($status, $this->arrayVal)) . " tasks (page {$page}):*\n";

        $todayTasks = [];
        $yesterdayTasks = [];
        $tomorrowTasks = [];
        $otherTasks = [];

        foreach ($tasks as $task) {
            if ($status === 'completed') {
                if ($task->completed_at?->isToday()) {
                    $todayTasks[] = $task;
                } elseif ($task->completed_at?->isYesterday()) {
                    $yesterdayTasks[] = $task;
                } else {
                    $otherTasks[] = $task;
                }
            } elseif ($status === 'in_progress') {
                if ($task->remind_at?->isToday()) {
                    $todayTasks[] = $task;
                } elseif ($task->remind_at?->isTomorrow()) {
                    $tomorrowTasks[] = $task;
                } else {
                    $otherTasks[] = $task;
                }
            }
        }

        if ($status === 'completed') {
            if ($todayTasks) {
                $message .= "\n*✅ Completed Today:*\n";
                foreach ($todayTasks as $task) {
                    $message .= "• {$task->title}\n";
                }
            }

            if ($yesterdayTasks) {
                $message .= "\n*📅 Completed Yesterday:*\n";
                foreach ($yesterdayTasks as $task) {
                    $message .= "• {$task->title}\n";
                }
            }

            if ($otherTasks) {
                $message .= "\n*🗓 Earlier Tasks:*\n";
                foreach ($otherTasks as $task) {
                    $formattedDate = $task->completed_at?->format('M d, H:i');
                    $message .= "• {$task->title} _(⏰ {$formattedDate})_\n";
                }
            }

        } elseif ($status === 'in_progress') {
            if ($todayTasks) {
                $message .= "\n*🕓 Due Today:*\n";
                foreach ($todayTasks as $task) {
                    $formattedTime = $task->remind_at?->format('H:i');
                    $message .= "• {$task->title} _(⏰ {$formattedTime})_\n";
                }
            }

            if ($tomorrowTasks) {
                $message .= "\n*📅 Due Tomorrow:*\n";
                foreach ($tomorrowTasks as $task) {
                    $formattedTime = $task->remind_at?->format('H:i');
                    $message .= "• {$task->title} _(⏰ {$formattedTime})_\n";
                }
            }

            if ($otherTasks) {
                $message .= "\n*🗓 Upcoming Tasks:*\n";
                foreach ($otherTasks as $task) {
                    $formattedDate = $task->remind_at?->format('M d, H:i');
                    $message .= "• {$task->title} _(⏰ {$formattedDate})_\n";
                }
            }
        }

        return $message ?: '*No tasks to display.*';
    }

    protected function dashboardTaskShow($tasks, $state, $page)
    {
        $triggerCommand = $state->trigger_command;
        $keyboard = [];
        $keyboardVal = $this->keyboardValArr[$triggerCommand];

        if ($tasks->currentPage() > 1) {
            $keyboard[] = [
                'text' => $keyboardVal['textBack'],
                'callback_data' => $keyboardVal['callback_data'] . ':' . ($page - 1)
            ];
        }
        if ($tasks->hasMorePages()) {
            $keyboard[] = [
                'text' => $keyboardVal['textNext'],
                'callback_data' => $keyboardVal['callback_data'] . ':' . ($page + 1)
            ];
        }

        $param = [];
        if (!empty($keyboard) && $state->state === 'wait') {
            $param['reply_markup'] = ['inline_keyboard' => [$keyboard]];
        }
        return $param;
    }

    public function paginateCompleteTask($data, $page, $state)
    {
        $user = User::where('telegram_id', $data['from']['id'])->first();
        $page = max(1, (int)$page);

        $status = 'in_progress';

        $tasks = Task::where('owner_id', $user->telegram_id)
                     ->where('status', $status)
                     ->orderBy('remind_at', 'desc')
                     ->paginate(6, ['*'], 'page', $page);

        $keyboard = [];

        $row = [];
        foreach ($tasks as $task) {
            $row[] = [
                'text' => $task->title,
                'callback_data' => 'TaskComplete:' . $task->id
            ];

            if (count($row) === 2) { 
                $keyboard[] = $row;
                $row = [];
            }
        }

        if (!empty($row)) {
            $keyboard[] = $row;
        }

        if ($tasks->currentPage() > 1) {
            $keyboard[] = [[
                'text' => '⬅️ Back',
                'callback_data' => 'tasks_list:' . ($page - 1)
            ]];
        }if ($tasks->hasMorePages()) {
            $keyboard[] = [[
                'text' => '➡️ Next',
                'callback_data' => 'tasks_list:' . ($page + 1)
            ]];
        }

        $param = [];
        if (!empty($keyboard) && $state->state === 'wait') {
            $param['reply_markup'] = ['inline_keyboard' => $keyboard];
        }

        $data['param'] = $param;
        $data['message_response'] = '*📋 Pending Tasks*

Choose a task from the list below to update its status to *completed*.'; // add page count or date range tasks
        return $data; 
    }

    public function parseFlexibleDateTime(string $input): ?string
    {
        $now = Carbon::now();

        $min = 0;
        $hour = 0;
        $day = $now->day;
        $month = $now->month;
        $year = $now->year;

        $parts = preg_split('/[\s\.:\/-]+/', trim($input));

        $parts = array_filter($parts, fn($p) => $p !== '');

        if (empty($parts) || count($parts) < 2 || count($parts) > 5) {
            throw new \Exception("Invalid date/time format");
        }

        try {
            switch (count($parts)) {
                case 2:
                    [$hour, $min] = array_map('intval', $parts);
                    break;
                case 3:
                    [$day, $hour, $min] = array_map('intval', $parts);
                    break;
                case 4:
                    [$month, $day, $hour, $min] = array_map('intval', $parts);
                    break;
                case 5:
                    [$year, $month, $day, $hour, $min] = array_map('intval', $parts);
                    break;
            }

            if ($hour < 0 || $hour > 23 || $min < 0 || $min > 59) {
                throw new \Exception("Invalid time values");
            }
            if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
                throw new \Exception("Invalid date values");
            }

            $date = Carbon::create($year, $month, $day, $hour, $min, 0);

            if (!$date || $date->isPast()) {
                throw new \Exception("Failed to create date");
            }

            return $date->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            throw new \Exception("Invalid date/time format");
        }
    }

}