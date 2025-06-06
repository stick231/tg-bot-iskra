<?php

namespace App\Console;

use App\Console\Commands\SetTelegramCommands;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected $commands = [
        SetTelegramCommands::class,
    ];


    protected function schedule(Schedule $schedule)
    {
        
        // $schedule->command('inspire')->daily();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
    }
}
