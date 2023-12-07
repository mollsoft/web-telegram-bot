<?php

namespace Mollsoft\WebTelegramBot;

use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Mollsoft\WebTelegramBot\Models\TelegraphChat;
use Mollsoft\WebTelegramBot\DTO\HTMLToTelegraphDTO;
use Mollsoft\WebTelegramBot\Enums\MessageDirection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Mollsoft\WebTelegramBot\Models\TelegraphSnapshot;

class Render
{
    protected Request $request;
    protected Response|RedirectResponse $response;
    protected TelegraphChat $chat;
    protected bool $isLive;
    protected Screen $screen;
    protected bool $deleteRemain;
    protected Collection $pendingMessages;
    protected Collection $displayedMessages;

    public function send(Request $request, Response|RedirectResponse $response): void
    {
        $this->request = $request;
        $this->response = $response;
        $this->chat = $request->chat();
        $this->isLive = !!$request->isLive();
        $this->screen = new Screen($this->chat);
        $this->deleteRemain = false;

        $this->saveCookies();

        if ($response instanceof RedirectResponse) {
            $this->redirect();
        } else {
            $this->render();
        }
    }

    protected function saveCookies(): void
    {
        $cookies = json_decode(
            $this->request->chat()->storage()->get('cookies', '[]'),
            true
        );
        foreach ($this->response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        $this->request->chat()->storage()->set('cookies', json_encode(array_filter($cookies)));
    }

    protected function redirect(): void
    {
        $this->request->chat()->storage()->set('referer', $this->request->chat()->storage()->get('url'));
        $this->request->chat()->storage()->set('url', $this->response->getTargetUrl());

        /** @var ProcessRunner $runner */
        $runner = app(ProcessRunner::class, [
            'bot' => $this->request->bot(),
            'chat' => $this->request->chat(),
        ]);
        $runner->run('GET');
    }

    protected function render(): void
    {
        $this
            ->initPendingMessages()
            ->snapshot($this->pendingMessages)
            ->clearInboxMessages()
            ->initDisplayedMessages()
            ->eachLines()
            ->clearInboxMessages(true);

        $this->chat->update([
            'display_at' => Date::now(),
            'displayed' => true,
        ]);
    }

    protected function initPendingMessages(): static
    {
        $this->pendingMessages = App::make(HTMLToTelegraph::class, [
            'request' => $this->request,
            'response' => $this->response,
        ])->run();

        return $this;
    }

    protected function snapshot(Collection $messages): static
    {
        $snapshotData = $messages->pluck('body')->all();
        $snapshotChecksum = md5(json_encode($snapshotData));

        if ($this->request->isLive()) {
            $previous = $this->request
                ->chat()
                ->snapshots()
                ->whereVisitId($this->request->visit()->id)
                ->whereDirection(MessageDirection::OUT)
                ->orderByDesc('id')
                ->first();
            if ($previous) {
                $previous->update([
                    'data' => $snapshotData,
                    'checksum' => $snapshotChecksum,
                ]);
                return $this;
            }
        }

        TelegraphSnapshot::create([
            'chat_id' => $this->request->chat()->id,
            'visit_id' => $this->request->visit()->id,
            'direction' => MessageDirection::OUT,
            'data' => $snapshotData,
            'checksum' => $snapshotChecksum
        ]);

        return $this;
    }

    /**
     * Метод удаляет все входящие сообщения из диалога.
     * PS: при условии что есть хотя бы 1 исходящее и не Live режим.
     */
    protected function clearInboxMessages(bool $force = false): static
    {
        $hasScreenOutMessages = $this->screen->messages()->where('direction', '=', 'out')->count() > 0;
        if (($hasScreenOutMessages && !$this->isLive) || $force) {
            $this->screen->messages()->where('direction', '=', 'in')->filter()->each(
                fn(ScreenMessage $item) => $item->delete()
            );
            $this->screen->defrag();
        }

        return $this;
    }

    protected function initDisplayedMessages(): static
    {
        $this->displayedMessages = $this->screen->messages();

        return $this;
    }

    protected function eachLines(): static
    {
        $iMax = max($this->pendingMessages->count(), $this->displayedMessages->count());
        for ($i = 0; $i < $iMax; $i++) {
            $displayed = $this->displayedMessages->get($i);
            $pending = $this->pendingMessages->get($i);

            $this->renderLine($i, $displayed, $pending);
        }

        return $this;
    }

    protected function renderLine(
        int $index,
        ?ScreenMessage $displayedMessage,
        ?HTMLToTelegraphDTO $pendingMessage
    ): void {
        if ($displayedMessage && $this->deleteRemain) {
            $displayedMessage->delete();
            $displayedMessage = null;
        }

        if ($displayedMessage && $pendingMessage) {
            if ($displayedMessage->direction === 'out' && $displayedMessage->checksum === $pendingMessage->checksum) {
                return;
            }
            $displayedHasProhibited = count(
                array_filter(
                    array_keys(
                        $displayedMessage->body
                    ),
                    fn($item) => !in_array($item, ['message', 'keyboard'])
                )
            ) > 0 || $displayedMessage->direction === 'in';
            $pendingHasProhibited = count(
                array_filter(
                    array_keys(
                        $pendingMessage->body,
                    ),
                    fn($item) => !in_array($item, ['message', 'keyboard'])
                )
            ) > 0;
            if (!$displayedHasProhibited && !$pendingHasProhibited) {
                $message = $this->request->chat()
                    ->edit($displayedMessage->id)
                    ->message($pendingMessage->body['message'] ?? '')
                    ->keyboard(Keyboard::fromArray($pendingMessage->body['keyboard'] ?? []))
                    ->send();
                if (!$message->json('ok')) {
                    App::abort(500, $message->json('description'));
                }

                $displayedMessage->body = $pendingMessage->body;
                $displayedMessage->checksum = $pendingMessage->checksum;
                $displayedMessage->save();
            } else {
                if ($index !== 0 || $this->screen->count() > 1) {
                    $displayedMessage->delete();
                    $this->deleteRemain = true;
                }

                $message = $pendingMessage->telegraph->send();
                if (!$message->json('ok')) {
                    App::abort(500, $message->json('description'));
                }
                $messageId = $message->telegraphMessageId();

                if ($pendingMessage->fileCacheKey &&
                    (
                        ($fileId = $message->json('result.video.file_id'))
                        ||
                        ($fileId = $message->json('result.audio.file_id'))
                        ||
                        ($fileId = $message->json('result.photo.file_id'))
                    )
                ) {
                    Cache::set($pendingMessage->fileCacheKey, $fileId, 3600);
                }

                $this->screen->push(
                    new ScreenMessage(
                        id: $messageId,
                        direction: MessageDirection::OUT->value,
                        body: $pendingMessage->body,
                        checksum: $pendingMessage->checksum,
                    )
                );
            }
        } elseif ($displayedMessage) {
            $displayedMessage->delete();
        } elseif ($pendingMessage) {
            $message = $pendingMessage->telegraph->send();
            if (!$message->json('ok')) {
                App::abort(500, $message->json('description'));
            }
            $messageId = $message->telegraphMessageId();

            if ($pendingMessage->fileCacheKey &&
                (
                    ($fileId = $message->json('result.video.file_id'))
                    ||
                    ($fileId = $message->json('result.audio.file_id'))
                    ||
                    ($fileId = $message->json('result.photo.file_id'))
                )
            ) {
                Cache::set($pendingMessage->fileCacheKey, $fileId, 3600);
            }

            $this->screen->push(
                new ScreenMessage(
                    id: $messageId,
                    direction: MessageDirection::OUT->value,
                    body: $pendingMessage->body,
                    checksum: $pendingMessage->checksum,
                )
            );
        }
    }
}
