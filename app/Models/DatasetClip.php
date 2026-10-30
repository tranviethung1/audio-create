<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetClip extends Model
{
    /** Giới hạn độ dài clip của VieNeu prepare_dataset.py (giây). */
    public const MIN_SEC = 3.0;

    public const MAX_SEC = 15.0;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['included' => 'boolean'];
    }

    public function voice(): BelongsTo
    {
        return $this->belongsTo(Voice::class);
    }

    /** Clip này có dùng được để train không (đã bật, có text, độ dài hợp lệ). */
    public function isUsable(): bool
    {
        return $this->included
            && trim((string) $this->text) !== ''
            && $this->duration >= self::MIN_SEC
            && $this->duration <= self::MAX_SEC;
    }
}
