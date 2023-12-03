<?php

namespace Mollsoft\WebTelegramBot;

class ScreenMessage implements \JsonSerializable
{
    protected \Redis $redis;
    protected string $redisKey;
    protected int $redisIndex;
    protected ?Screen $screen = null;

    public function __construct(
        public readonly int|string $id,
        public readonly string $direction,
        public array $body,
        public string $checksum,
        public bool $deleted = false
    ) {
    }

    public function setRedis(\Redis $redis, string $redisKey, int $redisIndex): self
    {
        $this->redis = $redis;
        $this->redisKey = $redisKey;
        $this->redisIndex = $redisIndex;

        return $this;
    }

    public function setScreen(Screen $screen): self
    {
        $this->screen = $screen;

        return $this;
    }

    public function save(): self
    {
        $this->redis->lSet($this->redisKey, $this->redisIndex, json_encode($this));

        return $this;
    }

    public function delete(): void
    {
        if ($this->screen) {
            $this->screen->delete($this);
        }

        $this->deleted = true;

        $this->save();
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'body' => $this->body,
            'checksum' => $this->checksum,
            'deleted' => $this->deleted,
        ];
    }

    public static function fromJson(
        string $json,
        \Redis $redis,
        string $redisKey,
        int $redisIndex,
        Screen $screen
    ): ?self {
        $json = @json_decode($json, true);
        if (!$json) {
            return null;
        }

        $object = new self(
            id: $json['id'],
            direction: $json['direction'],
            body: $json['body'],
            checksum: $json['checksum'],
            deleted: $json['deleted'],
        );

        return $object
            ->setRedis($redis, $redisKey, $redisIndex)
            ->setScreen($screen);
    }
}
