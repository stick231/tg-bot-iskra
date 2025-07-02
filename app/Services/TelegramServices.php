<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;

class TelegramServices{
    protected $arrayVal = [
        'In Progress' => 'in_progress',
        'Completed' => 'complited'
    ];

    public function paginateTaskShow($data, $page, $state)
    {
        $user = User::where('telegram_id', $data['from']['id'])->first();
        $page = max(1, (int)$page);

        $status = isset($data['text']) && isset($this->arrayVal[$data['text']]) ? $this->arrayVal[$data['text']] : $this->arrayVal[$state->data['status']];

        $tasks = Task::where('owner_id', $user->telegram_id)
                     ->where('status', $status)//add maybe category, return all or val
                     ->orderBy('created_at', 'desc')
                     ->paginate(6, ['*'], 'page', $page);

        $message = "📋 Your tasks (page $page):\n";
        foreach ($tasks as $task) {
            $message .= "\n- {$task->title} [{$task->status}]";
        }

        $keyboard = [];
        if ($tasks->currentPage() > 1) {
            $keyboard[] = [
                'text' => '⬅️ Back',
                'callback_data' => 'tasks_list:' . ($page - 1)
            ];
        }
        if ($tasks->hasMorePages()) {
            $keyboard[] = [
                'text' => '➡️ Next',
                'callback_data' => 'tasks_list:' . ($page + 1)
            ];
        }

        $param = [];
        if (!empty($keyboard) && $state->state === 'wait') {
            $param['reply_markup'] = ['inline_keyboard' => [$keyboard]];
        }
        $data['param'] = $param;
        $data['message_response'] = $message;
        return $data; 
    } // rewrite for all paginate command, 

}