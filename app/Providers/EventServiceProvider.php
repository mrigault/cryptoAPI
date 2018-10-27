<?php

namespace App\Providers;

use Laravel\Lumen\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'App\Events\ReportError' => [
            'App\Listeners\MakeErrorReport'
        ],
        'App\Events\TempError' => [
            'App\Listeners\MakeTempError'
        ]
    ];
}
