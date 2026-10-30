<?php

namespace App\Http\Controllers;

use App\Jobs\TranscribeYoutubeJob;
use App\Models\Transcription;
use App\Services\TtsClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TranscriptionController extends Controller
{
    public function index(): View
    {
        return view('transcriptions.index', ['transcriptions' => Transcription::latest()->get()]);
    }

    public function list(): View
    {
        return view('transcriptions._list', ['transcriptions' => Transcription::latest()->get()]);
    }

    public function store(Request $request, TtsClient $tts): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'regex:~^https?://(www\.|m\.|music\.)?(youtube\.com|youtu\.be)/~i'],
            'method' => 'required|in:auto,whisper',
            'language' => 'required|in:vi,en',
        ], [
            'url.regex' => 'Link phải là link YouTube.',
            'url.url' => 'Link không hợp lệ.',
        ]);

        try {
            $info = $tts->youtubeInfo($data['url']);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['url' => $e->getMessage()]);
        }

        $t = Transcription::create([
            'url' => $data['url'],
            'method' => $data['method'],
            'language' => $data['language'],
            'video_id' => $info['video_id'] ?? null,
            'title' => $info['title'] ?: $data['url'],
            'channel' => $info['channel'] ?? null,
            'thumbnail' => $info['thumbnail'] ?? null,
            'duration' => $info['duration'] ?? null,
        ]);
        TranscribeYoutubeJob::dispatch($t->id);

        return redirect()->route('transcriptions.show', $t)->with('status', 'Đang chuyển video thành text...');
    }

    public function show(Transcription $transcription): View
    {
        return view('transcriptions.show', ['t' => $transcription]);
    }

    public function update(Request $request, Transcription $transcription): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'text' => 'nullable|string',
        ]);
        $transcription->update($data);

        return back()->with('status', 'Đã lưu.');
    }

    public function retry(Transcription $transcription): RedirectResponse
    {
        $transcription->update(['status' => 'pending', 'error' => null]);
        TranscribeYoutubeJob::dispatch($transcription->id);

        return back()->with('status', 'Đã đưa lại vào hàng đợi.');
    }

    public function destroy(Transcription $transcription): RedirectResponse
    {
        $transcription->delete();

        return redirect()->route('transcriptions.index')->with('status', 'Đã xoá.');
    }
}
