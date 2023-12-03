<?php

namespace Mollsoft\WebTelegramBot\Enums;

use Mollsoft\WebTelegramBot\Traits\EnumTrait;

enum MessageDirection: string
{
    use EnumTrait;

    case IN = 'in';
    case OUT = 'out';
}
