@extends('layouts.app')
@section('title', 'YouTube → Text')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-lg font-semibold">Lịch sử theo video</h1>
    <button type="button" class="btn-primary" data-open="transcription-create">+ Import link YouTube</button>
</div>

<div data-live-list="{{ route('transcriptions.list') }}">
    @include('transcriptions._list')
</div>

{{-- Popup: import link YouTube --}}
<x-modal id="transcription-create" title="Import link YouTube">
    @php $mine = old('_form') === 'transcription-create'; @endphp
    <form method="POST" action="{{ route('transcriptions.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="transcription-create">
        <div>
            <label class="label" for="url">Link video</label>
            <input class="input" id="url" name="url" type="url" required value="{{ $mine ? old('url') : '' }}" placeholder="https://www.youtube.com/watch?v=...">
        </div>
        <div>
            <label class="label" for="language">Ngôn ngữ trong video</label>
            <select class="input" id="language" name="language">
                <option value="vi" @selected(! $mine || old('language', 'vi') === 'vi')>Tiếng Việt</option>
                <option value="en" @selected($mine && old('language') === 'en')>Tiếng Anh</option>
            </select>
        </div>
        <div>
            <label class="label" for="method">Cách lấy text</label>
            <select class="input" id="method" name="method">
                <option value="auto" @selected(! $mine || old('method') === 'auto')>Tự động: ưu tiên phụ đề, không có thì nhận dạng giọng nói</option>
                <option value="whisper" @selected($mine && old('method') === 'whisper')>Luôn nhận dạng giọng nói (Whisper, chậm hơn nhưng có dấu câu)</option>
            </select>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn-ghost" data-close>Huỷ</button>
            <button class="btn-primary">Chuyển thành text</button>
        </div>
    </form>
</x-modal>
@endsection
