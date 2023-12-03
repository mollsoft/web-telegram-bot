<?php

namespace Mollsoft\WebTelegramBot\DTO;

use DefStudio\Telegraph\Telegraph;

readonly class HTMLToTelegraphDTO
{
    public function __construct(
        public array $body,
        public Telegraph $telegraph,
        public string $checksum,
        public ?string $fileCacheKey,
    ) {
    }
}
