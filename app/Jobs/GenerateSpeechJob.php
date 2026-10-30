<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Services\TtsClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateSpeechJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $generationId) {}

    public function handle(TtsClient $tts): void
    {
        $gen = Generation::find($this->generationId);
        if (! $gen) {
            return;
        }

        $gen->update(['status' => 'processing', 'error' => null]);

        try {
            $refAudio = null;
            $refOptions = [];
            if ($gen->voice_id) {
                $sample = $gen->voice?->primarySample ?? $gen->voice?->samples()->first();
                if (! $sample) {
                    throw new \RuntimeException('Giọng này chưa có file audio mẫu');
                }
                $refAudio = $sample->path;
                if ($sample->clip_start !== null) {
                    $refOptions = ['ref_start' => $sample->clip_start, 'ref_seconds' => $sample->clip_len ?? 8.0];
                }
            }

            $outPath = "lanhvcl/outputs/{$gen->id}.wav";
            $result = $tts->synthesize($gen->text, $outPath, $gen->preset_voice, $refAudio, array_merge($gen->settings ?? [], $refOptions));

            $gen->update([
                'status' => 'done',
                'path' => $result['out_path'],
                'duration' => $result['duration'],
            ]);
        } catch (\Throwable $e) {
            Storage::delete("lanhvcl/outputs/{$gen->id}.wav");
            $gen->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Generation::whereKey($this->generationId)->update(['status' => 'failed', 'error' => $e->getMessage()]);
    }
}
