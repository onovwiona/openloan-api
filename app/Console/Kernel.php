<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The paths where Artisan commands should be automatically discovered.
     *
     * @var array<int, string>
     */
    protected $commandPaths = [
        __DIR__ . '/Commands',
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('loans:accrue-interest')
            ->daily()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('loans:auto-charge')
            ->daily()
            ->withoutOverlapping()
            ->onOneServer();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
