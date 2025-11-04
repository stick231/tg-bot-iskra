<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

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
                     ->orderBy($status === 'in_progress' ? 'start_at' : 'completed_at', 'desc')
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
        $futureTasks = [];
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
                if (!$task->start_at) {
                    $otherTasks[] = $task;
                } elseif ($task->start_at->isToday()) {
                    $todayTasks[] = $task;
                } elseif ($task->start_at->isTomorrow()) {
                    $tomorrowTasks[] = $task;
                } elseif ($task->start_at->isFuture()) {
                    $futureTasks[] = $task;
                } else {
                    $otherTasks[] = $task;
                }
            }
        }

        if ($status === 'completed') {
            if ($todayTasks) {
                $message .= "\n*✅ Completed Today:*\n";
                foreach ($todayTasks as $task) {
                    $message .= "• {$this->escapeMarkdownV2($task->title)}\n";
                }
            }

            if ($yesterdayTasks) {
                $message .= "\n*📅 Completed Yesterday:*\n";
                foreach ($yesterdayTasks as $task) {
                    $message .= "• {$this->escapeMarkdownV2($task->title)}\n";
                }
            }

            if ($otherTasks) {
                $message .= "\n*🗓 Earlier Tasks:*\n";
                foreach ($otherTasks as $task) {
                    $formattedDate = $task->completed_at?->format('M d, H:i');
                    $message .= "• {$this->escapeMarkdownV2($task->title)} _(⏰ {$formattedDate})_\n";
                }
            }

        } elseif ($status === 'in_progress') {
            if ($todayTasks) {
                $message .= "\n*🕓 Due Today:*\n";
                foreach ($todayTasks as $task) {
                    $formattedTimeStart = $task->start_at?->format('H:i');
                    $formattedTimeEnd = $task->end_at?->format('H:i');

                    $message .= "• {$this->escapeMarkdownV2($task->title)} _(⏰ {$formattedTimeStart}" 
                                . ($formattedTimeEnd ? " - {$formattedTimeEnd}" : "") 
                                . ")_\n";
                }
            }

            if ($tomorrowTasks) {
                $message .= "\n*📅 Due Tomorrow:*\n";
                foreach ($tomorrowTasks as $task) {
                    $formattedTimeStart = $task->start_at?->format('H:i');
                    $formattedTimeEnd = $task->end_at?->format('H:i');
                                    
                    $message .= "• {$this->escapeMarkdownV2($task->title)} _(⏰ {$formattedTimeStart}" 
                                . ($formattedTimeEnd ? " - {$formattedTimeEnd}" : "") 
                                . ")_\n";
                }
            }

            if ($futureTasks) {
                $message .= "\n*🗓 Upcoming Tasks:*\n";
                foreach ($futureTasks as $task) {
                    $formattedTimeStart = $task->start_at?->format('M d, H:i');
                    $formattedTimeEnd = $task->end_at?->format('M d, H:i');
                                    
                    $message .= "• {$this->escapeMarkdownV2($task->title)} _(⏰ {$formattedTimeStart}" 
                                . ($formattedTimeEnd ? " - {$formattedTimeEnd}" : "") 
                                . ")_\n";
                }
            }

            if ($otherTasks) {
                $message .= "\n*⚠️ Overdue / No date:*\n";
                foreach ($otherTasks as $task) {
                    $formattedDateStart = $task->start_at?->format('M d, H:i');
                    $formattedDateEndAt = $task->end_at?->format('M d, H:i');
                    $datePart = $formattedDateStart ? " _(⏰ {$formattedDateStart})_" : '';
                    $message .= "• {$this->escapeMarkdownV2($task->title)} " . $datePart 
                                . ($formattedDateEndAt ? " - {$formattedDateEndAt}" : "") 
                                . ")_\n";
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
                     ->orderBy('start_at', 'desc')
                     ->paginate(6, ['*'], 'page', $page);

        $keyboard = [];

        $row = [];
        foreach ($tasks as $task) {
            $row[] = [
                'text' => $this->escapeMarkdownV2($task->title),
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
        $data['message_response'] = "*📋 Ожидающие задачи*

Выберите задачу из списка ниже, чтобы отметить её как *выполненную* и отслеживать свой прогресс! ✅";
        return $data; 
    }

    public function parseFlexibleDateTime(string $input)
    {
        $inputParts = preg_split('/\s*[-to—]\s*/i', trim($input));


        $partsStart = preg_split('/[\s\.:\/]+/', trim($inputParts[0] ?? null));
        $partsEnd = preg_split('/[\s\.:\/]+/', trim($inputParts[1] ?? null));


        $partsStart = array_filter($partsStart, fn($p) => $p !== '');
        $partsEnd = array_filter($partsEnd, fn($p) => $p !== '');

        $startAt = $this->formatingDate($partsStart);
        $end_at = $partsEnd ? $this->formatingDate($partsEnd) : null;

        return [$startAt, $end_at];
    }

    public function formatingDate($parts){
        $now = Carbon::now();

        $min = 0;
        $hour = 0;
        $day = $now->day;
        $month = $now->month;
        $year = $now->year;

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

            if (empty($parts) || count($parts) < 2 || count($parts) > 5) {
                throw new \Exception("Invalid date/time format");
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


    public function escapeMarkdownV2(string $text): string
    {
        $chars = ['\\','_','*','[',']','(',')','~','`','>','#','+','-','=','|','{','}','.','!'];

        foreach($chars as $ch){
            $text = str_replace($ch, '\\' . $ch, $text);
        }
        return $text;
    }

    public function getTaskReminders()
    {
        $targetTime = Carbon::now()->copy()->addMinutes(30);

        $tasks = Task::whereBetween('start_at', 
            [
                $targetTime->copy()->subMinute(),
                $targetTime->copy()->addMinute()
            ],
        )->get();
        
        return $tasks;
    }
}