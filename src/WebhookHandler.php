<?php

namespace Mollsoft\WebTelegramBot;

use Carbon\Carbon;
use DefStudio\Telegraph\DTO\CallbackQuery;
use DefStudio\Telegraph\DTO\Message;
use Illuminate\Http\Request;
use Mollsoft\WebTelegramBot\Models\TelegraphBot;
use DefStudio\Telegraph\DTO\InlineQuery;
use DefStudio\Telegraph\DTO\TelegramUpdate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

class WebhookHandler extends \DefStudio\Telegraph\Handlers\WebhookHandler
{
    protected function setupChat(): void
    {
        parent::setupChat();

        if( !$this->chat->info_at || $this->chat->info_at->lte(Carbon::now()->subSeconds(config('telegraph.chat_info_frequency', 3600))) ) {
            $chatInfo = $this->chat->info();
            $this->chat->update([
                'first_name' => $chatInfo['first_name'] ?? null,
                'last_name' => $chatInfo['last_name'] ?? null,
                'username' => $chatInfo['username'] ?? null,
                'bio' => $chatInfo['bio'] ?? null,
                'info_at' => Carbon::now(),
            ]);
        }
    }

    public function handle(Request $request, \DefStudio\Telegraph\Models\TelegraphBot $bot): void
    {
        $this->bot = $bot;

        $this->request = $request;

        if ($this->request->has('message')) {
            /* @phpstan-ignore-next-line */
            $this->message = Message::fromArray($this->request->input('message'));
            $this->handleMessage();

            return;
        }

        if ($this->request->has('edited_message')) {
            /* @phpstan-ignore-next-line */
            $this->message = Message::fromArray($this->request->input('edited_message'));
            $this->handleMessage();

            return;
        }

        if ($this->request->has('channel_post')) {
            /* @phpstan-ignore-next-line */
            $this->message = Message::fromArray($this->request->input('channel_post'));
            $this->handleMessage();

            return;
        }


        if ($this->request->has('callback_query')) {
            /* @phpstan-ignore-next-line */
            $this->callbackQuery = CallbackQuery::fromArray($this->request->input('callback_query'));
            $this->handleCallbackQuery();
        }

        if ($this->request->has('inline_query')) {
            /* @phpstan-ignore-next-line */
            $this->handleInlineQuery(InlineQuery::fromArray($this->request->input('inline_query')));
        }
    }

    protected function handleUnknownCommand(Stringable $text): void
    {
        $this->handleChatMessage($text);
    }

    public function handleChatMessage(Stringable $text): void
    {
        $this->runApp('GET', [
            'message' => $this->message,
        ]);
    }

    protected function handleInlineQuery(InlineQuery $inlineQuery): void
    {
        $this->runApp('GET', [
            'inlineQuery' => $inlineQuery,
        ]);
    }

    protected function handleCallbackQuery(): void
    {
        $this->extractCallbackQueryData();

        if (config('telegraph.debug_mode')) {
            Log::debug('Telegraph webhook callback', $this->data->toArray());
        }

        $this->runApp('POST', [
            'callbackQuery' => $this->callbackQuery,
        ]);
    }

    public function handleUpdate(TelegraphBot $bot, TelegramUpdate $update): void
    {
        $this->bot = $bot;

        $this->message = $update->message();
        $this->callbackQuery = $update->callbackQuery();

        if ($update->message()) {
            $this->handleMessage();
        }

        if ($update->callbackQuery()) {
            $this->handleCallbackQuery();
        }

        if ($update->inlineQuery()) {
            $this->handleInlineQuery($update->inlineQuery());
        }
    }

    private function handleMessage(): void
    {
        $this->extractMessageData();

        if (config('telegraph.debug_mode')) {
            Log::debug('Telegraph webhook message', $this->data->toArray());
        }

        $text = Str::of($this->message?->text() ?? '');

        if ($text->startsWith('/')) {
            $this->handleCommand($text);

            return;
        }


        if ($this->message?->newChatMembers()->isNotEmpty()) {
            foreach ($this->message->newChatMembers() as $member) {
                $this->handleChatMemberJoined($member);
            }

            return;
        }

        if ($this->message?->leftChatMember() !== null) {
            $this->handleChatMemberLeft($this->message->leftChatMember());

            return;
        }

        $this->handleChatMessage($text);
    }

    private function handleCommand(Stringable $text): void
    {
        $this->handleChatMessage($text);
    }

    protected function runApp(string $method, array $data): void
    {
        /** @var ProcessRunner $runner */
        $runner = app(ProcessRunner::class, [
            'bot' => $this->bot,
            'chat' => $this->chat
        ]);
        $runner->run($method, $data);
    }
}
