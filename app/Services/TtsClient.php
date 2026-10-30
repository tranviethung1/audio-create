<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client gọi TTS service (Python: VieNeu + yt-dlp + faster-whisper).
 * Mọi đường dẫn file tính theo disk `local` (storage/app/private), service mount cùng thư mục tại /data.
 */
class TtsClient
{
    private function http(int $timeout): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.tts.url'), '/'))
            ->timeout($timeout)
            ->acceptJson();
    }

    private function unwrap($response): array
    {
        if ($response->failed()) {
            $detail = $response->json('detail') ?? $response->body();
            throw new RuntimeException(is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE));
        }

        return $response->json();
    }

    public function isUp(): bool
    {
        try {
            return $this->http(3)->get('/health')->ok();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Danh sách giọng có sẵn của VieNeu: [['label' => 'Hải Đăng', 'id' => '...'], ...] */
    public function presetVoices(): array
    {
        $cached = Cache::get('tts.preset_voices');
        if ($cached) {
            return $cached;
        }

        try {
            $voices = $this->unwrap($this->http(300)->get('/voices'));
        } catch (\Throwable) {
            return [];
        }

        Cache::put('tts.preset_voices', $voices, now()->addDay());

        return $voices;
    }

    public function audioInfo(string $path): array
    {
        return $this->unwrap($this->http(60)->post('/audio/info', ['path' => $path]));
    }

    /** Đoạn ~8 giây tốt nhất để làm mẫu clone: ['start', 'length', 'total']. */
    public function bestSegment(string $path, float $seconds = 8.0): array
    {
        return $this->unwrap($this->http(300)->post('/audio/best-segment', ['path' => $path, 'seconds' => $seconds]));
    }

    /** Cắt file dài thành clip 3–15s và gỡ băng từng clip (Whisper): ['clips' => [file,start,duration,text]]. */
    public function datasetPrepare(string $path, string $outDir, string $language = 'vi'): array
    {
        return $this->unwrap($this->http(7200)->post('/dataset/prepare', [
            'path' => $path, 'out_dir' => $outDir, 'language' => $language,
        ]));
    }

    /** Cắt đoạn audio ra file wav mới (để nghe thử). */
    public function clip(string $path, string $outPath, float $start, float $length): array
    {
        return $this->unwrap($this->http(120)->post('/audio/clip', [
            'path' => $path, 'out_path' => $outPath, 'start' => $start, 'length' => $length,
        ]));
    }

    public function synthesize(string $text, string $outPath, ?string $presetVoice, ?string $refAudio, array $options = []): array
    {
        return $this->unwrap($this->http(3600)->post('/synthesize', array_filter([
            'text' => $text,
            'out_path' => $outPath,
            'voice' => $presetVoice,
            'ref_audio' => $refAudio,
            ...$options,
        ], fn ($v) => $v !== null)));
    }

    /** Tải audio YouTube (cắt đoạn start–end giây nếu có) vào $outPath, trả về ['duration', 'title']. */
    public function youtubeAudio(string $url, string $outPath, ?float $start = null, ?float $end = null): array
    {
        return $this->unwrap($this->http(900)->post('/youtube/audio', array_filter([
            'url' => $url,
            'out_path' => $outPath,
            'start' => $start,
            'end' => $end,
        ], fn ($v) => $v !== null)));
    }

    public function youtubeInfo(string $url): array
    {
        return $this->unwrap($this->http(60)->post('/youtube/info', ['url' => $url]));
    }

    public function youtubeTranscribe(string $url, string $method = 'auto', string $language = 'vi'): array
    {
        return $this->unwrap($this->http(7200)->post('/youtube/transcribe', [
            'url' => $url,
            'method' => $method,
            'language' => $language,
        ]));
    }
}
