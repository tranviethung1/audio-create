<?php

namespace App\Jobs;

use App\Models\Transcription;
use App\Services\TtsClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TranscribeYoutubeJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 1;

    public function __construct(public int $transcriptionId) {}

    public function handle(TtsClient $tts): void
    {
        $t = Transcription::find($this->transcriptionId);
        if (! $t) {
            return;
        }

        $t->update(['status' => 'processing', 'error' => null]);

        try {
            $result = $tts->youtubeTranscribe($t->url, $t->method, $t->language);
            $t->update([
                'status' => 'done',
                'text' => $result['text'],
                'source' => $result['source'],
            ]);
        } catch (\Throwable $e) {
            $t->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Transcription::whereKey($this->transcriptionId)->update(['status' => 'failed', 'error' => $e->getMessage()]);
    }
}
