<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transcription extends Model
{
    protected $guarded = [];

    public function isFinished(): bool
    {
        return in_array($this->status, ['done', 'failed'], true);
    }
}
