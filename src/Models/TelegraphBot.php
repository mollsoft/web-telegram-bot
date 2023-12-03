<?php

namespace Mollsoft\WebTelegramBot\Models;

use DefStudio\Telegraph\DTO\TelegramUpdate;
use DefStudio\Telegraph\Exceptions\TelegramUpdatesException;
use DefStudio\Telegraph\Facades\Telegraph as TelegraphFacade;
use Illuminate\Support\Collection;

class TelegraphBot extends \DefStudio\Telegraph\Models\TelegraphBot
{
    public function updates(int $timeout = 0, int $offset = null): Collection
    {
        $request = TelegraphFacade::bot($this)
            ->botUpdates()
            ->withData('timeout', $timeout);

        if ($offset !== null) {
            $request = $request->withData('offset', $offset);
        }

        $response = $request->send();

        if ($response->telegraphError()) {
            if (!$response->successful()) {
                throw TelegramUpdatesException::pollingError($this, $response->reason());
            }

            if ($response->json('error_code') == 409) {
                throw TelegramUpdatesException::webhookExist($this);
            }

            /* @phpstan-ignore-next-line */
            throw TelegramUpdatesException::pollingError($this, $response->json('description'));
        }

        /* @phpstan-ignore-next-line */
        return collect($response->json('result'))->map(fn(array $update) => TelegramUpdate::fromArray($update));
    }
}
