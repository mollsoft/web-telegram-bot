<?php

namespace Mollsoft\WebTelegramBot\Middleware;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Mollsoft\WebTelegramBot\Models\TelegraphChat;
use Closure;
use Illuminate\Support\Facades\Cache;
use Mollsoft\WebTelegramBot\Enums\MessageDirection;
use Mollsoft\WebTelegramBot\Models\TelegraphSnapshot;
use Mollsoft\WebTelegramBot\Models\TelegraphVisit;
use Mollsoft\WebTelegramBot\Request;
use Mollsoft\WebTelegramBot\Screen;
use Mollsoft\WebTelegramBot\ScreenMessage;

class TelegraphMiddleware
{
    protected ?Request $request = null;
    protected ?TelegraphChat $chat = null;
    protected ?TelegraphVisit $visit = null;
    protected ?Screen $screen = null;

    public function handle(Request $request, Closure $next)
    {
        $this->request = $request;
        $this->chat = $request->chat();
        $this->visit = $request->visit();
        $this->screen = new Screen($this->chat);

        $this->snapshot();

        if (($message = $request->message())) {
            $hasScreen = $this->screen->count() > 0;

            foreach( config('telegraph.home_message_prefix', ['/start', '🏘']) as $prefix ) {
                if (mb_strpos($message->text(), $prefix) !== false) {
                    $this->screen->clear();
                }
            }

            $this->screen->push(
                new ScreenMessage(
                    id: $message->id(),
                    direction: MessageDirection::IN->value,
                    body: [
                        'message' => $message->text(),
                    ],
                    checksum: md5(
                        json_encode([
                            'message' => $message->text(),
                        ])
                    ),
                )
            );

            if( mb_strpos($message->text(), '/start') !== false && !$hasScreen ) {
                return redirect()->refresh();
            }

            foreach( config('telegraph.home_message_prefix', ['/start', '🏘']) as $prefix ) {
                if (mb_strpos($message->text(), $prefix) !== false) {
                    $request->session()->flush();
                    return redirect()->to('/telegraph');
                }
            }

            foreach( config('telegraph.back_message_prefix', ['⬅️', '🔙']) as $prefix ) {
                if (mb_strpos($message->text(), $prefix) !== false) {
                    return $this->back();
                }
            }
        }

        if (($callbackQuery = $request->callbackQuery())) {
            if ($callbackQuery->data()->get('redirect') === 'back') {
                return $this->back();
            }

            if ($callbackQuery->data()->get('action') === 'link') {
                $href = $callbackQuery->data()->get('href');

                if (Str::isUuid($href)) {
                    $href = Cache::get($href);
                }

                return redirect($href);
            }
        }

        return $next($request);
    }

    protected function snapshot(): bool
    {
        if ($this->request->isLive()) {
            return false;
        }

        $snapshotData = [];
        if (($message = $this->request->message())) {
            $snapshotData['message'] = $message->text();
        }
        if (($callbackQuery = $this->request->callbackQuery())) {
            $snapshotData['callback-query'] = $callbackQuery->data()->all();
        }
        $snapshotChecksum = md5(json_encode($snapshotData));

        if (count($snapshotData) > 0) {
            $this->chat->snapshots()->create([
                'visit_id' => $this->visit->id,
                'direction' => MessageDirection::IN,
                'data' => $snapshotData,
                'checksum' => $snapshotChecksum
            ]);
        }

        return true;
    }

    protected function back(): RedirectResponse
    {
        if ($this->visit->parent) {
            $this->visit->update(['current' => false]);
            $this->visit->parent->update(['current' => true]);

            return redirect('/telegraph'.$this->visit->parent->request_uri);
        }

        return redirect('/telegraph');
    }
}
