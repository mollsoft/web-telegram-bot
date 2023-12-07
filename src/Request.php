<?php

namespace Mollsoft\WebTelegramBot;

use Illuminate\Support\Facades\Date;
use Mollsoft\WebTelegramBot\Models\TelegraphBot;
use Mollsoft\WebTelegramBot\Models\TelegraphChat;
use DefStudio\Telegraph\DTO\CallbackQuery;
use DefStudio\Telegraph\DTO\InlineQuery;
use DefStudio\Telegraph\DTO\Message;
use Mollsoft\WebTelegramBot\Models\TelegraphVisit;

class Request extends \Illuminate\Http\Request
{
    protected ?TelegraphBot $bot = null;
    protected ?TelegraphChat $chat = null;
    protected ?Message $message = null;
    protected ?CallbackQuery $callbackQuery = null;
    protected ?InlineQuery $inlineQuery = null;
    protected ?TelegraphVisit $visit = null;
    protected ?bool $isLive = null;

    public function bot(): TelegraphBot
    {
        return $this->bot;
    }

    public function chat(): TelegraphChat
    {
        return $this->chat;
    }

    public function message(): ?Message
    {
        return $this->message;
    }

    public function callbackQuery(): ?CallbackQuery
    {
        return $this->callbackQuery;
    }

    public function inlineQuery(): ?InlineQuery
    {
        return $this->inlineQuery;
    }

    public function isLive(): ?bool
    {
        return $this->isLive;
    }

    public function setBot(TelegraphBot $bot): static
    {
        $this->bot = $bot;

        return $this;
    }

    public function setChat(TelegraphChat $chat): static
    {
        $this->chat = $chat;

        return $this;
    }

    public function setMessage(?Message $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function setCallbackQuery(?CallbackQuery $callbackQuery): static
    {
        $this->callbackQuery = $callbackQuery;

        return $this;
    }

    public function setInlineQuery(?InlineQuery $inlineQuery): static
    {
        $this->inlineQuery = $inlineQuery;

        return $this;
    }

    public function setIsLive(?bool $isLive): static
    {
        $this->isLive = $isLive;

        return $this;
    }

    public static function createFromInput(array $input): static
    {
        /** @var TelegraphBot $bot */
        if (!($bot = $input['bot'] ?? null)) {
            throw new \Exception('Bot is required in payload.');
        }

        /** @var TelegraphChat $chat */
        if (!($chat = $input['chat'] ?? null)) {
            throw new \Exception('Chat is required in payload.');
        }

        /** @var Message $message */
        $message = $input['message'] ?? null;

        /** @var CallbackQuery $callbackQuery */
        $callbackQuery = $input['callbackQuery'] ?? null;

        /** @var InlineQuery $inlineQuery */
        $inlineQuery = $input['inlineQuery'] ?? null;

        $isLive = isset($input['isLive']) ? boolval($input['isLive']) : null;

        $request = self::create(
            $input['uri'] ?? '/telegraph',
            $input['method'] ?? 'GET',
            $input['parameters'] ?? [],
            $input['cookies'] ?? [],
            [],
            [],
            json_encode([
                'bot' => $bot->only(['id', 'name']),
                'chat' => $chat->only(['id', 'chat_id', 'name']),
                'message' => $message?->toArray(),
                'callbackQuery' => $callbackQuery?->toArray(),
                'inlineQuery' => $inlineQuery?->toArray(),
                'isLive' => $isLive
            ])
        );

        $cookieString = '';
        foreach ($input['cookies'] ?? [] as $key => $value) {
            $cookieString .= $key.'='.$value.'; ';
        }
        $request->headers->set('cookie', rtrim($cookieString, "; "));
        $request->headers->set('content-type', 'application/json');
        if (isset($input['referer'])) {
            $request->headers->set('referer', $input['referer']);
        }

        return $request
            ->setBot($bot)
            ->setChat($chat)
            ->setMessage($message)
            ->setCallbackQuery($callbackQuery)
            ->setInlineQuery($inlineQuery)
            ->setIsLive($isLive);
    }

    public function requestURI()
    {
        $parsedUrl = parse_url($this->fullUrl());

        $path = $parsedUrl['path'] ?? '';
        $path = ltrim($path, '/');
        if (strpos($path, "telegraph") === 0) {
            $path = substr($path, strlen("telegraph"));
            $path = ltrim($path, '/');
            $path = '/'.$path;
        }

        $query = $parsedUrl['query'] ?? '';

        return $path.($query ? '?'.$query : '');
    }

    public function visit(): TelegraphVisit
    {
        if ($this->visit === null) {
            $this->chat->update([
                'display_at' => Date::now(),
                'displayed' => true,
            ]);

            $requestURI = $this->requestURI();
            $this->visit = $this->chat->visits()->whereCurrent(true)->first();

            if (!$this->visit || $this->visit->request_uri !== $requestURI) {
                $this->chat
                    ->visits()
                    ->whereCurrent(true)
                    ->update(['current' => false]);
                $this->visit = $this->chat->visits()->create([
                    'parent_id' => $this->visit?->id,
                    'request_uri' => $requestURI,
                    'current' => true,
                    'visit_at' => Date::now(),
                ]);
            } elseif ($this->isLive()) {
                $this->visit->touch();
            } else {
                $this->visit->update([
                    'visit_at' => Date::now(),
                ]);
            }
        }

        return $this->visit;
    }

    public function live(int $period, int $timeout)
    {
        $this->visit()
            ->update([
                'live_period' => $period,
                'live_timeout' => $timeout,
            ]);
    }
}
