<?php

namespace Mollsoft\WebTelegramBot;

use Mollsoft\WebTelegramBot\Commands\LiveCommand;
use Mollsoft\WebTelegramBot\Commands\PollingCommand;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class WebTelegramBotServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('web-telegram-bot')
            ->hasConfigFile('telegraph')
            ->hasCommand(PollingCommand::class)
            ->hasCommand(LiveCommand::class)
            ->hasMigration('create_telegraph_bots_table')
            ->hasMigration('create_telegraph_chats_table')
            ->hasMigration('create_telegraph_visits_table')
            ->hasMigration('create_telegraph_snapshots_table')
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->startWith(function (InstallCommand $command) {
                        $command->comment('Publishing telegraph application...');

                        $command->callSilently("vendor:publish", [
                            '--tag' => "telegraph-application",
                        ]);

                        $command->comment('Publishing telegraph app with example...');

                        $command->callSilently("vendor:publish", [
                            '--tag' => "telegraph-app",
                        ]);

                        $command->comment('Publishing telegraph routes...');

                        $command->callSilently("vendor:publish", [
                            '--tag' => "telegraph-routes",
                        ]);

                        $command->comment('Publishing telegraph bootstrap...');

                        $command->callSilently("vendor:publish", [
                            '--tag' => "telegraph-bootstrap",
                        ]);

                        $command->comment('Publishing telegraph views...');

                        $command->callSilently("vendor:publish", [
                            '--tag' => "telegraph-views",
                        ]);
                    });
            });
    }

    public function boot()
    {
        parent::boot();

        $this->publishes([
            __DIR__.'/../app/Telegram/Kernel.stub' => base_path('app/Telegram/Kernel.php'),
            __DIR__.'/../app/Telegram/Middleware/ExampleMiddleware.stub' => base_path(
                'app/Telegram/Middleware/ExampleMiddleware.php'
            ),
            __DIR__.'/../app/Telegram/Controllers/ExampleController.stub' => base_path(
                'app/Telegram/Controllers/ExampleController.php'
            ),
            __DIR__.'/../app/Telegram/Forms/ExampleForm.stub' => base_path(
                'app/Telegram/Forms/ExampleForm.php'
            )
        ], 'telegraph-app');

        $this->publishes([
            __DIR__.'/../application/telegraph' => base_path('telegraph'),
        ], 'telegraph-application');

        $this->publishes([
            __DIR__.'/../routes/telegraph.stub' => base_path('routes/telegraph.php'),
        ], 'telegraph-routes');

        $this->publishes([
            __DIR__.'/../bootstrap/telegraph.stub' => base_path('bootstrap/telegraph.php'),
        ], 'telegraph-bootstrap');

        $this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views'),
        ], 'telegraph-views');

        return $this;
    }
}
