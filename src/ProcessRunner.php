<?php

namespace Mollsoft\WebTelegramBot;

use Mollsoft\WebTelegramBot\Models\TelegraphBot;
use Mollsoft\WebTelegramBot\Models\TelegraphChat;
use Illuminate\Support\Facades\Process;

class ProcessRunner
{
    public function __construct(
        protected readonly TelegraphBot $bot,
        protected readonly TelegraphChat $chat,
    ) {
    }

    public function run(string $method, array $data = []): void
    {
        $referer = $this->chat->storage()->get('referer');
        $URL = $this->chat->storage()->get('url', '/telegraph');
        $cookies = json_decode($this->chat->storage()->get('cookies', '[]'), true);

        $parseURL = parse_url($URL);
        parse_str($parseURL['query'] ?? '', $parameters);

        $uri = ($parseURL['path'] ?? '/telegraph');
        if ($parseURL['query'] ?? null) {
            $uri .= '?'.$parseURL['query'];
        }

        $input = [
            'uri' => $uri,
            'method' => $method,
            'parameters' => $parameters,
            'bot' => $this->bot,
            'chat' => $this->chat,
            'cookies' => $cookies,
            'referer' => $referer,
            ...$data
        ];

        Process::input(serialize($input))
            ->run('php '.base_path('telegraph'), function (string $type, string $content) {
                if ($type === 'out') {
                    echo $content;
                }
            });
    }
}
