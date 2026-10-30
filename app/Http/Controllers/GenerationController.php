<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSpeechJob;
use App\Models\Generation;
use App\Models\Transcription;
use App\Models\Voice;
use App\Services\TtsClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GenerationController extends Controller
{
    public function index(Request $request, TtsClient $tts): View
    {
        $from = $request->filled('from') ? Transcription::find($request->query('from')) : null;
        $again = $request->filled('again') ? Generation::find($request->query('again')) : null;

        return view('generations.index', [
            'voices' => Voice::whereNotNull('primary_sample_id')->orderBy('name')->get(),
            'presets' => $tts->presetVoices(),
            'generations' => Generation::latest()->get(),
            'prefillText' => $again?->text ?? $from?->text,
            'prefillTitle' => $again?->title ?? $from?->title,
            'prefillVoice' => $again ? ($again->voice_id ? 'voice:'.$again->voice_id : 'preset:'.$again->preset_voice) : null,
            'prefillSettings' => $again?->settings ?? [],
        ]);
    }

    public function list(): View
    {
        return view('generations._list', ['generations' => Generation::latest()->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:150',
            'text' => 'required|string|max:50000',
            'voice' => 'required|string',
            'split' => 'nullable|boolean',
            'temperature' => 'nullable|numeric|min:0.1|max:1.5',
            'silence_p' => 'nullable|numeric|min:0|max:1',
            'max_chars' => 'nullable|integer|min:60|max:512',
            'denoise' => 'nullable|boolean',
        ], ['voice.required' => 'Hãy chọn giọng đọc.']);

        $settings = array_filter([
            'temperature' => isset($data['temperature']) ? (float) $data['temperature'] : null,
            'silence_p' => isset($data['silence_p']) ? (float) $data['silence_p'] : null,
            'max_chars' => isset($data['max_chars']) ? (int) $data['max_chars'] : null,
            'denoise' => $request->boolean('denoise'),
        ], fn ($v) => $v !== null);

        [$voiceId, $preset, $label] = $this->resolveVoice($data['voice']);

        $chunks = ! empty($data['split'])
            ? array_values(array_filter(array_map('trim', preg_split('/\R{2,}/u', $data['text']))))
            : [trim($data['text'])];

        $baseTitle = trim($data['title'] ?? '') ?: Str::limit(Str::squish($chunks[0]), 60);

        foreach ($chunks as $i => $chunk) {
            $gen = Generation::create([
                'title' => count($chunks) > 1 ? $baseTitle.' – '.($i + 1) : $baseTitle,
                'text' => $chunk,
                'voice_id' => $voiceId,
                'preset_voice' => $preset,
                'voice_label' => $label,
                'settings' => $settings,
            ]);
            GenerateSpeechJob::dispatch($gen->id);
        }

        return redirect()->route('generations.index')
            ->with('status', 'Đã thêm '.count($chunks).' đoạn vào hàng đợi tạo audio.');
    }

    public function show(Generation $generation)
    {
        return response()->json([
            'title' => $generation->title,
            'text' => $generation->text,
            'again_url' => route('generations.index', ['again' => $generation->id]),
        ]);
    }

    public function audio(Generation $generation)
    {
        abort_unless($generation->path && Storage::exists($generation->path), 404);

        return response()->file(Storage::path($generation->path), ['Content-Type' => 'audio/wav']);
    }

    public function download(Generation $generation)
    {
        abort_unless($generation->path && Storage::exists($generation->path), 404);

        return Storage::download($generation->path, (Str::slug($generation->title) ?: "audio-{$generation->id}").'.wav');
    }

    public function retry(Generation $generation): RedirectResponse
    {
        $generation->update(['status' => 'pending', 'error' => null]);
        GenerateSpeechJob::dispatch($generation->id);

        return back()->with('status', 'Đã đưa lại vào hàng đợi.');
    }

    public function destroy(Generation $generation): RedirectResponse
    {
        if ($generation->path) {
            Storage::delete($generation->path);
        }
        $generation->delete();

        return back()->with('status', 'Đã xoá audio.');
    }

    /** @return array{0: ?int, 1: ?string, 2: string} */
    private function resolveVoice(string $value): array
    {
        if (str_starts_with($value, 'voice:')) {
            $voice = Voice::findOrFail((int) substr($value, 6));

            return [$voice->id, null, $voice->name];
        }

        $preset = substr($value, str_starts_with($value, 'preset:') ? 7 : 0);

        return [null, $preset, $preset];
    }
}
