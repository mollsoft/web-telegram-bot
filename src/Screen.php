<?php

namespace Mollsoft\WebTelegramBot;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Mollsoft\WebTelegramBot\Models\TelegraphChat;

class Screen
{
    protected readonly string $redisKey;
    protected readonly \Redis $redis;

    public function __construct(protected readonly TelegraphChat $chat)
    {
        $this->redisKey = 'telegraph_screen_'.$chat->chat_id.'_'.$chat->telegraph_bot_id;
        $this->redis = Redis::client();
    }

    public function count(): int
    {
        return (int)$this->redis->lLen($this->redisKey);
    }

    /**
     * @return Collection<ScreenMessage|null>
     */
    public function messages(): Collection
    {
        $items = $this->redis->lRange($this->redisKey, 0, -1);
        foreach ($items as $i => $item) {
            $items[$i] = ScreenMessage::fromJson($item, $this->redis, $this->redisKey, $i, $this);
        }
        return collect($items);
    }

    public function get(int $index): ?ScreenMessage
    {
        $item = $this->redis->lIndex($this->redisKey, $index);
        if ($item) {
            return ScreenMessage::fromJson($item, $this->redis, $this->redisKey, $index, $this);
        }

        return null;
    }

    public function push(ScreenMessage $screenMessage)
    {
        $length = $this->redis->rPush($this->redisKey, json_encode($screenMessage));
        $index = $length - 1;
        $screenMessage
            ->setRedis($this->redis, $this->redisKey, $index)
            ->setScreen($this);

        return $index;
    }

    public function defrag(): void
    {
        $items = $this->messages();
        $this->truncate();
        $items->each(function (?ScreenMessage $item) {
            if ($item && !$item->deleted) {
                $this->redis->rPush($this->redisKey, json_encode($item));
            }
        });
    }

    public function truncate(): void
    {
        $this->redis->del($this->redisKey);
    }

    public function delete(ScreenMessage $message): bool
    {
        try {
            $this->chat->deleteMessage($message->id)->send();
        } catch (\Exception) {
            return false;
        }

        return true;
    }

    public function clear(): void
    {
        $this->messages()->filter()->each(fn(ScreenMessage $item) => $item->delete());
        $this->defrag();
    }
}
