<?php

namespace Mollsoft\WebTelegramBot\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegraphChat extends \DefStudio\Telegraph\Models\TelegraphChat
{
    protected $fillable = [
        'chat_id',
        'name',
        'username',
        'first_name',
        'last_name',
        'bio',
        'info_at',
        'display_at',
        'displayed',
    ];

    protected $casts = [
        'info_at' => 'datetime',
        'display_at' => 'datetime',
        'displayed' => 'boolean',
    ];

    public function visits(): HasMany
    {
        return $this->hasMany(TelegraphVisit::class, 'chat_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(TelegraphSnapshot::class, 'chat_id');
    }
}
