<?php

namespace Mollsoft\WebTelegramBot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mollsoft\WebTelegramBot\Enums\MessageDirection;

class TelegraphSnapshot extends Model
{
    protected $fillable = [
        'chat_id',
        'visit_id',
        'direction',
        'data',
        'checksum'
    ];

    protected $casts = [
        'direction' => MessageDirection::class,
        'data' => 'json',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(config('telegraph.models.chat'));
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(TelegraphVisit::class);
    }
}
