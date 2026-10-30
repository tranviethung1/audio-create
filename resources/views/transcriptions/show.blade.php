@extends('layouts.app')
@section('title', $t->title)

@section('content')
<a href="{{ route('transcriptions.index') }}" class="mb-3 inline-block text-sm text-slate-500 hover:text-slate-800">← Quay lại danh sách</a>

<div class="card space-y-4">
    @if (! $t->isFinished())
        <div class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Đang chuyển video thành text, trang sẽ tự cập nhật khi xong…
        </div>
        <script>setTimeout(() => location.reload(), 4000);</script>
    @endif

    <div class="flex gap-4">
        @if ($t->thumbnail)
            <img src="{{ $t->thumbnail }}" alt="" class="hidden h-24 w-40 rounded-md object-cover sm:block" referrerpolicy="no-referrer">
        @endif
        <div class="min-w-0 text-sm text-slate-500">
            <a href="{{ $t->url }}" target="_blank" rel="noopener" class="break-all text-indigo-600 hover:underline">{{ $t->url }}</a>
            <div class="mt-1">
                {{ $t->channel }}
                @if ($t->source) · nguồn: {{ $t->source === 'subtitle' ? 'phụ đề YouTube' : 'Whisper' }} @endif
            </div>
        </div>
    </div>

    @if ($t->status === 'failed')
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $t->error }}
            <form method="POST" action="{{ route('transcriptions.retry', $t) }}" class="mt-2">@csrf <button class="btn-ghost btn-sm">Thử lại</button></form>
        </div>
    @endif

    <form method="POST" action="{{ route('transcriptions.update', $t) }}" class="space-y-3">
        @csrf @method('PUT')
        <div>
            <label class="label" for="title">Tiêu đề</label>
            <input class="input" id="title" name="title" value="{{ old('title', $t->title) }}" required maxlength="255">
        </div>
        <div>
            <label class="label" for="text">Nội dung text</label>
            <textarea class="input font-mono" id="text" name="text" rows="18" @disabled(! $t->isFinished())>{{ old('text', $t->text) }}</textarea>
        </div>
        <div class="flex flex-wrap gap-2">
            <button class="btn-primary">Lưu</button>
            @if ($t->status === 'done')
                <a class="btn-ghost" href="{{ route('generations.index', ['from' => $t->id]) }}">Tạo audio từ text này</a>
            @endif
        </div>
    </form>
</div>
@endsection
