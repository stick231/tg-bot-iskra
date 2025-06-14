<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramCommands extends Command
{
    protected $signature = 'telegram:set-commands';
    protected $description = 'Sets the main command for the Telegram bot';

    public function handle()
    {
        $commands = [
            ['command' => 'start', 'description' => 'Start bot'],
            ['command' => 'statistics', 'description' => 'Your statistics'],
            ['command' => 'add_task', 'description' => 'Add task'],
            ['command' => 'show_task', 'description' => 'View your tasks'],
            ['command' => 'completed_task', 'description' => 'You complete task!']
        ];
        
        $response = Http::post("https://api.telegram.org/bot" . env('TG_TOKEN') . "/setMyCommands", [
            'commands' => $commands
        ]);
        
        if ($response->successful()) {
            $this->info('Commands successfully created!');
        } else {
            $this->error('Error installing command:' . $response->body());
        }
    }
}
