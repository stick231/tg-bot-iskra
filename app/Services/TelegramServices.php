<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;

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
                     ->where('status', $status)//add maybe category, return all or val
                     ->orderBy('created_at', 'desc')
                     ->paginate(6, ['*'], 'page', $page);

        $message = "*📋 Your tasks (page {$page}):*\n";// rewrite dynamic message
            

        foreach ($tasks as $task) {  // rewrite
            // Заголовок задачи жирным и категория курсивом
            $message .= "\n• *{$task->title}* _(Category: {$task->category})_";
        }

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
        $data['param'] = $param;
        $data['message_response'] = $message;
        return $data; 
    } // rewrite for all paginate command, 

    public function paginateCompleteTask($data, $page, $state)
    {
        $user = User::where('telegram_id', $data['from']['id'])->first();
        $page = max(1, (int)$page);

        $status = 'in_progress';

        $tasks = Task::where('owner_id', $user->telegram_id)
                     ->where('status', $status)//add maybe category, return all or val
                     ->orderBy('created_at', 'desc')
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
        $data['message_response'] = 'Task work';
        return $data; 
    }

}