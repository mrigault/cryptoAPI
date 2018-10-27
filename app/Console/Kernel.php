<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
 {
   /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\UpdateCoinPrices::class,
        \App\Console\Commands\UpdateExchangeCoinlist::class,
        \App\Console\Commands\UpdateAllHistory::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // update coin prices
        for($x = 1; $x <= 10; $x++) $schedule->command('update:CoinPrices ' . $x)->everyMinute();

        // update coinlists for active exchanges
        $schedule->command('update:ExchangeCoinlist')->hourly();
        
    }
}
