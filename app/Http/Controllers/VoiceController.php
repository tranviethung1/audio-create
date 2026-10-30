<?php

namespace App\Http\Controllers;

use App\Models\Voice;
use App\Models\VoiceSample;
use App\Services\TtsClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VoiceController extends Controller
{
    public function index(TtsClient $tts): View
    {
        return view('voices.index', [
            'voices' => Voice::with(['samples', 'primarySample'])->latest()->get(),
            'presets' => $tts->presetVoices(),
        ]);
    }

    public function store(Request $request, TtsClient $tts): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            ...$this->sampleRules(),
        ], $this->sampleMessages());

        [$files, $ytUrl, $start, $end] = $this->sampleInput($request);

        $voice = Voice::create(['name' => $data['name'], 'description' => $data['description'] ?? null]);

        try {
            $this->addSamples($voice, $files, $tts);
            if ($ytUrl) {
                $this->addYoutubeSample($voice, $ytUrl, $start, $end, $tts);
            }
        } catch (\Throwable $e) {
            Storage::deleteDirectory("lanhvcl/samples/{$voice->id}");
            $voice->delete();

            return back()->withInput()->withErrors(['samples' => $e->getMessage()]);
        }

        return redirect()->route('voices.index')->with('status', "Đã tạo giọng \"{$voice->name}\".");
    }

    public function update(Request $request, Voice $voice): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'primary_sample_id' => 'nullable|integer',
        ]);

        if (! empty($data['primary_sample_id']) && ! $voice->samples()->whereKey($data['primary_sample_id'])->exists()) {
            unset($data['primary_sample_id']);
        }

        $voice->update($data);

        return back()->with('status', 'Đã cập nhật giọng.')->with('open', "voice-manage-{$voice->id}");
    }

    public function destroy(Voice $voice): RedirectResponse
    {
        Storage::deleteDirectory("lanhvcl/samples/{$voice->id}");
        $voice->delete();

        return redirect()->route('voices.index')->with('status', 'Đã xoá giọng.');
    }

    public function storeSamples(Request $request, Voice $voice, TtsClient $tts): RedirectResponse
    {
        $request->validate($this->sampleRules(), $this->sampleMessages());

        [$files, $ytUrl, $start, $end] = $this->sampleInput($request);

        try {
            $this->addSamples($voice, $files, $tts);
            if ($ytUrl) {
                $this->addYoutubeSample($voice, $ytUrl, $start, $end, $tts);
            }
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['samples' => $e->getMessage()]);
        }

        return back()->with('status', 'Đã thêm audio mẫu.')->with('open', "voice-manage-{$voice->id}");
    }

    public function destroySample(VoiceSample $sample): RedirectResponse
    {
        $voice = $sample->voice;
        $this->forgetClips($sample);
        Storage::delete($sample->path);
        $sample->delete();

        if ($voice->primary_sample_id === $sample->id) {
            $voice->update(['primary_sample_id' => $voice->samples()->value('id')]);
        }

        return back()->with('status', 'Đã xoá audio mẫu.')->with('open', "voice-manage-{$voice->id}");
    }

    /** Đặt tay đoạn dùng để clone: bắt đầu từ giây X, dài tối đa 8 giây. */
    public function updateClip(Request $request, VoiceSample $sample): RedirectResponse
    {
        $data = $request->validate(['clip_start' => 'required|string|max:12']);
        $start = $this->parseTime($data['clip_start'], 'clip_start', 'Thời điểm bắt đầu');

        $max = max(0.0, ($sample->duration ?? 0) - 1.0);
        $start = min($start, $max);

        $this->forgetClips($sample);
        $sample->update(['clip_start' => $start, 'clip_len' => min(8.0, max(1.0, ($sample->duration ?? 8.0) - $start))]);

        return back()->with('status', 'Đã đặt đoạn dùng để clone.')->with('open', "voice-manage-{$sample->voice_id}");
    }

    /** Tự chọn đoạn tốt nhất của file. */
    public function autoClip(VoiceSample $sample, TtsClient $tts): RedirectResponse
    {
        try {
            $this->applyBestSegment($sample, $tts);
        } catch (\Throwable $e) {
            return back()->withErrors(['samples' => $e->getMessage()])
                ->with('open', "voice-manage-{$sample->voice_id}");
        }

        return back()->with('status', 'Đã tự chọn đoạn tốt nhất để clone.')->with('open', "voice-manage-{$sample->voice_id}");
    }

    /** Phát đoạn đang dùng để clone (tạo file nghe thử khi cần). */
    public function sampleClip(VoiceSample $sample, TtsClient $tts)
    {
        abort_unless(Storage::exists($sample->path), 404);
        if ($sample->clip_start === null) {
            return response()->file(Storage::path($sample->path));
        }

        $out = sprintf('lanhvcl/cache/clips/%d-%d.wav', $sample->id, (int) round($sample->clip_start * 100));
        if (! Storage::exists($out)) {
            $tts->clip($sample->path, $out, $sample->clip_start, $sample->clip_len ?? 8.0);
        }

        return response()->file(Storage::path($out));
    }

    public function sampleAudio(VoiceSample $sample)
    {
        abort_unless(Storage::exists($sample->path), 404);

        return response()->file(Storage::path($sample->path));
    }

    /** Với file dài hơn đoạn mẫu cần dùng: tự chọn đoạn tốt nhất; file ngắn: dùng cả file. */
    private function applyBestSegment(VoiceSample $sample, TtsClient $tts): void
    {
        if (($sample->duration ?? 0) <= 8.5) {
            $this->forgetClips($sample);
            $sample->update(['clip_start' => null, 'clip_len' => null]);

            return;
        }

        $seg = $tts->bestSegment($sample->path);
        $this->forgetClips($sample);
        $sample->update(['clip_start' => $seg['start'], 'clip_len' => $seg['length']]);
    }

    private function forgetClips(VoiceSample $sample): void
    {
        foreach (Storage::files('lanhvcl/cache/clips') as $file) {
            if (str_starts_with(basename($file), $sample->id.'-')) {
                Storage::delete($file);
            }
        }
    }

    /** Quy tắc validate chung: file upload và/hoặc link YouTube (cần ít nhất một trong hai). */
    private function sampleRules(): array
    {
        return [
            'samples' => 'nullable|array',
            'samples.*' => 'file|mimetypes:audio/*,video/webm,video/mp4,application/ogg|max:204800',
            'youtube_url' => ['nullable', 'url', 'regex:~^https?://(www\.|m\.|music\.)?(youtube\.com|youtu\.be)/~i'],
            'yt_start' => 'nullable|string|max:12',
            'yt_end' => 'nullable|string|max:12',
        ];
    }

    private function sampleMessages(): array
    {
        return [
            'samples.*.mimetypes' => 'File phải là audio (wav, mp3, m4a, ogg, flac...).',
            'samples.*.uploaded' => 'Upload thất bại: file quá lớn (tối đa 200MB) hoặc bị gián đoạn.',
            'samples.*.max' => 'Mỗi file audio tối đa 200MB.',
            'youtube_url.url' => 'Link YouTube không hợp lệ.',
            'youtube_url.regex' => 'Link phải là link YouTube.',
        ];
    }

    /** @return array{0: \Illuminate\Http\UploadedFile[], 1: ?string, 2: ?float, 3: ?float} */
    private function sampleInput(Request $request): array
    {
        $files = array_filter($request->file('samples', []) ?? []);
        $ytUrl = trim((string) $request->input('youtube_url')) ?: null;

        if (! $files && ! $ytUrl) {
            throw ValidationException::withMessages(['samples' => 'Cần chọn file audio hoặc nhập link YouTube.']);
        }

        $start = $this->parseTime($request->input('yt_start'), 'yt_start', 'Thời điểm bắt đầu');
        $end = $this->parseTime($request->input('yt_end'), 'yt_end', 'Thời điểm kết thúc');
        if ($start !== null && $end !== null && $end <= $start) {
            throw ValidationException::withMessages(['yt_end' => 'Thời điểm kết thúc phải lớn hơn thời điểm bắt đầu.']);
        }

        return [$files, $ytUrl, $start, $end];
    }

    /** Nhận "90", "1:30", "01:02:03", "1:30.5" -> số giây. */
    private function parseTime(?string $value, string $field, string $label): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (! preg_match('/^\d+(?::\d{1,2}){0,2}(?:\.\d+)?$/', $value)) {
            throw ValidationException::withMessages([$field => "$label không hợp lệ (dùng giây hoặc mm:ss, vd 90 hoặc 1:30)."]);
        }

        $seconds = 0.0;
        foreach (explode(':', $value) as $part) {
            $seconds = $seconds * 60 + (float) $part;
        }

        return $seconds;
    }

    private function addYoutubeSample(Voice $voice, string $url, ?float $start, ?float $end, TtsClient $tts): void
    {
        $path = "lanhvcl/samples/{$voice->id}/".bin2hex(random_bytes(8)).'.wav';

        try {
            $info = $tts->youtubeAudio($url, $path, $start, $end);
        } catch (\Throwable $e) {
            Storage::delete($path);
            throw new \RuntimeException($e->getMessage());
        }

        $range = ($start !== null || $end !== null)
            ? ' ['.$this->fmtTime($start ?? 0).'–'.($end !== null ? $this->fmtTime($end) : 'hết').']'
            : '';

        $sample = $voice->samples()->create([
            'original_name' => 'YouTube: '.($info['title'] ?? $url).$range,
            'path' => $path,
            'duration' => $info['duration'] ?? null,
        ]);

        $this->tryAutoClip($sample, $tts);

        if (! $voice->primary_sample_id) {
            $voice->update(['primary_sample_id' => $sample->id]);
        }
    }

    private function fmtTime(float $seconds): string
    {
        return gmdate($seconds >= 3600 ? 'G:i:s' : 'i:s', (int) $seconds);
    }

    /** @param  \Illuminate\Http\UploadedFile[]  $files */
    private function addSamples(Voice $voice, array $files, TtsClient $tts): void
    {
        foreach ($files as $file) {
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
            $path = $file->storeAs("lanhvcl/samples/{$voice->id}", bin2hex(random_bytes(8)).".{$ext}");

            try {
                $info = $tts->audioInfo($path);
            } catch (\Throwable $e) {
                Storage::delete($path);
                throw new \RuntimeException("File \"{$file->getClientOriginalName()}\" không hợp lệ: ".$e->getMessage());
            }

            $sample = $voice->samples()->create([
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'duration' => $info['duration'] ?? null,
            ]);

            $this->tryAutoClip($sample, $tts);

            if (! $voice->primary_sample_id) {
                $voice->update(['primary_sample_id' => $sample->id]);
            }
        }
    }

    /** Chọn đoạn mẫu tự động nhưng không làm hỏng việc thêm mẫu nếu bước này lỗi. */
    private function tryAutoClip(VoiceSample $sample, TtsClient $tts): void
    {
        try {
            $this->applyBestSegment($sample, $tts);
        } catch (\Throwable) {
            // bỏ qua: người dùng vẫn có thể bấm "Tự chọn đoạn" sau
        }
    }
}
