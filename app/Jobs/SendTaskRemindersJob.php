<?php

namespace App\Jobs;

use App\Services\TelegramServices;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTaskRemindersJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(TelegramServices $telegramServices): void
    {
        Log::info('test');
        $tasks = $telegramServices->getTaskReminders();
        Log::info($tasks);
        foreach($tasks as $task){
            $message = 'Напоминание, не забудьте про задачу: ' . $task->title; 

            Http::post("https://api.telegram.org/bot" . env('TG_TOKEN') . "/sendMessage", array_merge([
                'chat_id' => $task->owner_id->chat_id,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]))->json();
        }
    }
}
