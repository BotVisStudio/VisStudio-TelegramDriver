<?php

namespace VisStudio\Drivers\Telegram\Providers;

use Illuminate\Support\ServiceProvider;
use VisStudio\Drivers\Telegram\TelegramDriver;
use VisStudio\Drivers\DriverManager;

class TelegramServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadDrivers();

        $this->publishes([
            __DIR__ . '/../../config/telegram.php' => config_path('visStudio/telegram.php')
        ]);
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/telegram.php', 'visStudio.telegram'
        );
    }

    public function loadDrivers()
    {
        DriverManager::loadDriver(TelegramDriver::class);
    }
}