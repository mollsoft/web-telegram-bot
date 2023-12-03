<?php

namespace Mollsoft\WebTelegramBot\Commands;

use Mollsoft\WebTelegramBot\WebhookHandler;
use DefStudio\Telegraph\DTO\TelegramUpdate;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mollsoft\WebTelegramBot\Models\TelegraphBot;

class PollingCommand extends Command
{
    protected $signature = 'telegraph:polling {bot} {--debug}';

    protected $description = 'Command description';

    protected ?TelegraphBot $bot;
    protected float $started;

    public function handle(): void
    {
        $this->started = microtime(true);

        $botId = $this->argument('bot');

        $botClass = config('telegraph.models.bot');
        $this->bot = $botClass::find($botId);
        if (!$this->bot) {
            $this->error("Telegraph Bot #".$botId.' not found!');
            return;
        }

        if ($this->hasOption('debug')) {
            $this->start();
        } else {
            $this->info('Start Atomic Lock');

            try {
                Cache::lock(__CLASS__, 60)->block(20, fn() => $this->start());
            } catch (LockTimeoutException) {
                $this->error('Timeout Atomic Lock');
            }
        }
    }

    protected function start(): void
    {
        $this->info('Started polling for Telegraph Bot #'.$this->bot->name);

        /** @var WebhookHandler $webhookHandler */
        $webhookHandler = app(config('telegraph.webhook_handler'));

        $offset = null;
        do {
            $updates = [];

            $this->info('Wait updates...');

            try {
                $updates = $this->bot->updates(10, $offset === null ? null : $offset + 1);
            } catch (\Exception $e) {
                Log::error('[Telegram Polling] GetUpdates Exception: '.$e->getMessage(), [
                    'bot' => $this->bot,
                    'exception' => $e,
                ]);

                $this->error('Get Updates Exception: '.$e->getMessage());
                sleep(10);
            }

            if (count($updates)) {
                $this->info('Received '.count($updates).' updates');
            }

            /** @var TelegramUpdate $update */
            foreach ($updates as $update) {
                $offset = $offset === null ? $update->id() : max($offset, $update->id());

                try {
                    $webhookHandler->handleUpdate($this->bot, $update);
                } catch (\Exception $e) {
                    Log::error('[Telegram Polling] Webhook Handler: '.$e->getMessage(), [
                        'bot' => $this->bot,
                        'exception' => $e,
                    ]);

                    $this->error('Webhook Handler Exception '.get_class($e).': '.$e->getMessage());
                }
            }
        } while ($this->hasOption('debug') || (microtime(true) - $this->started < 50));
    }
}
