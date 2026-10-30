<?php

namespace App\Jobs;

use App\Models\DatasetClip;
use App\Models\Voice;
use App\Services\TtsClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Cắt các audio mẫu của một giọng thành clip 3–15s và gỡ băng bằng Whisper,
 * tạo bộ dữ liệu để fine-tune LoRA (người dùng sẽ rà soát text trước khi xuất).
 */
class BuildDatasetJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 1;

    public function __construct(public int $voiceId, public string $language = 'vi') {}

    public function handle(TtsClient $tts): void
    {
        $voice = Voice::find($this->voiceId);
        if (! $voice) {
            return;
        }

        $voice->update(['dataset_status' => 'processing', 'dataset_error' => null]);

        try {
            // Làm lại từ đầu: bỏ clip cũ
            $voice->datasetClips()->delete();
            Storage::deleteDirectory("lanhvcl/datasets/{$voice->id}");

            $position = 0;
            foreach ($voice->samples as $sample) {
                $result = $tts->datasetPrepare($sample->path, "lanhvcl/datasets/{$voice->id}/{$sample->id}", $this->language);

                foreach ($result['clips'] as $clip) {
                    DatasetClip::create([
                        'voice_id' => $voice->id,
                        'voice_sample_id' => $sample->id,
                        'file' => $clip['file'],
                        'start' => $clip['start'],
                        'duration' => $clip['duration'],
                        'text' => $clip['text'],
                        'position' => ++$position,
                    ]);
                }
            }

            if ($position === 0) {
                throw new \RuntimeException('Không cắt được clip nào (audio quá ngắn hoặc không có lời nói).');
            }

            $voice->update(['dataset_status' => 'ready']);
        } catch (\Throwable $e) {
            $voice->update(['dataset_status' => 'failed', 'dataset_error' => $e->getMessage()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Voice::whereKey($this->voiceId)->update(['dataset_status' => 'failed', 'dataset_error' => $e->getMessage()]);
    }
}
