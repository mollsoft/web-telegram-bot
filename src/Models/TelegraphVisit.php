<?php

namespace Mollsoft\WebTelegramBot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegraphVisit extends Model
{
    protected $fillable = [
        'parent_id',
        'chat_id',
        'request_uri',
        'live_period',
        'live_timeout',
        'current',
        'visit_at',
    ];

    protected $casts = [
        'live_period' => 'integer',
        'live_timeout' => 'integer',
        'current' => 'boolean',
        'visit_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TelegraphVisit::class, 'parent_id');
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(config('telegraph.models.chat'));
    }
}
