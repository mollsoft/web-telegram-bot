<?php

namespace Mollsoft\WebTelegramBot\Commands;

use Mollsoft\WebTelegramBot\Models\TelegraphVisit;
use Mollsoft\WebTelegramBot\ProcessRunner;
use Mollsoft\WebTelegramBot\WebhookHandler;
use DefStudio\Telegraph\DTO\TelegramUpdate;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mollsoft\WebTelegramBot\Models\TelegraphBot;

class LiveCommand extends Command
{
    protected $signature = 'telegraph:live {--debug}';

    protected $description = 'Telegraph Live Observer';

    protected int $frequency;
    protected float $started;

    public function handle(): void
    {
        $this->frequency = (int)config('telegraph.live_frequency', 10);
        $this->started = microtime(true);

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
        $this->info('Started Live Observer');

        do {
            TelegraphVisit::query()
                ->with('chat.bot')
                ->whereCurrent(true)
                ->whereNotNull('live_period')
                ->whereNotNull('live_timeout')
                ->whereRaw('visit_at BETWEEN DATE_SUB(NOW(), INTERVAL live_timeout SECOND) AND NOW()')
                ->whereRaw('updated_at < DATE_SUB(NOW(), INTERVAL live_period SECOND)')
                ->each(function (TelegraphVisit $visit) {
                    $this->info('Live for @'.$visit->chat->name.' bot @'.$visit->chat->bot->name);

                    $runner = app(ProcessRunner::class, [
                        'bot' => $visit->chat->bot,
                        'chat' => $visit->chat
                    ]);
                    $runner->run('GET', [
                        'isLive' => true,
                    ]);
                });

            $this->info('Pause '.$this->frequency.' seconds...');
            sleep($this->frequency);
        } while ($this->hasOption('debug') || (microtime(true) - $this->started < 50));
    }
}
