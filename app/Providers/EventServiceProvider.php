<?php

namespace App\Providers;

use App\Events\Expenses;
use App\Events\PirepFiled;
use App\Events\ProfileUpdated;
use App\Events\UserStatsChanged;
use App\Listeners\AwardHandler;
use App\Listeners\BidEventHandler;
use App\Listeners\ExpenseListener;
use App\Listeners\FinanceEventHandler;
use App\Listeners\PirepEventsHandler;
use App\Listeners\UserStateListener;
use App\Notifications\NotificationEventsHandler;
use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\Events\UpdateSucceeded;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Expenses::class => [
            ExpenseListener::class,
        ],

        PirepFiled::class => [
            UserStateListener::class,
        ],

        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        UserStatsChanged::class => [

        ],

        UpdateAvailable::class => [],
        UpdateSucceeded::class => [],
        ProfileUpdated::class  => [],        
        // Log messages out to the console if running there
        'Illuminate\Log\Events\MessageLogged' => [
            'App\Listeners\MessageLoggedListener',
        ],
    ];

    protected $subscribe = [
        BidEventHandler::class,
        FinanceEventHandler::class,
        NotificationEventsHandler::class,
        AwardHandler::class,
        PirepEventsHandler::class,
    ];
}
