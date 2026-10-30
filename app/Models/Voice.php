<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voice extends Model
{
    protected $guarded = [];

    public function samples(): HasMany
    {
        return $this->hasMany(VoiceSample::class)->orderBy('id');
    }

    public function primarySample(): BelongsTo
    {
        return $this->belongsTo(VoiceSample::class, 'primary_sample_id');
    }

    public function datasetClips(): HasMany
    {
        return $this->hasMany(DatasetClip::class)->orderBy('position');
    }

    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }
}
