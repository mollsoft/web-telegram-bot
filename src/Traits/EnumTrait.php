<?php

namespace Mollsoft\WebTelegramBot\Traits;

trait EnumTrait
{
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
