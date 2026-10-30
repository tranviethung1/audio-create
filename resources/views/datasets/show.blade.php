@extends('layouts.app')
@section('title', 'Bộ dữ liệu fine-tune · '.$voice->name)

@php
    $processing = $voice->dataset_status === 'processing';
    $minutes = $usableSeconds / 60;
@endphp

@section('content')
<a href="{{ route('voices.index') }}" class="mb-3 inline-block text-sm text-slate-500 hover:text-slate-800">← Quay lại danh sách giọng</a>

<div class="mb-4 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-lg font-semibold">Bộ dữ liệu fine-tune: {{ $voice->name }}</h1>
        <p class="mt-1 max-w-2xl text-sm text-slate-500">
            Cắt audio mẫu thành các clip 3–15 giây và gỡ băng bằng Whisper. Hãy nghe và <b>sửa lại text cho khớp từng chữ</b>
            (kể cả dấu câu), bỏ chọn clip nhiễu hoặc nhiều người nói, rồi xuất zip để train LoRA trên máy có GPU.
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        @if ($clips->isNotEmpty() && ! $processing)
            <a class="btn-primary" href="{{ route('datasets.export', $voice) }}">Tải zip để fine-tune</a>
        @endif
        <button type="button" class="btn-ghost" data-open="dataset-build" @disabled($processing)>
            {{ $clips->isEmpty() ? 'Tạo bộ dữ liệu' : 'Tạo lại từ đầu' }}
        </button>
    </div>
</div>

@if ($errors->has('dataset'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ $errors->first('dataset') }}</div>
@endif

@if ($processing)
    <div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Đang cắt clip và gỡ băng (Whisper trên CPU, khoảng vài phút cho 5 phút audio). Trang sẽ tự cập nhật khi xong…
    </div>
    <script>setTimeout(() => location.reload(), 5000);</script>
@elseif ($voice->dataset_status === 'failed')
    <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">Lỗi: {{ $voice->dataset_error }}</div>
@endif

@if ($clips->isNotEmpty())
    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <div class="card !p-4"><div class="text-xs uppercase text-slate-500">Clip dùng được</div><div class="text-2xl font-semibold">{{ $usableCount }} <span class="text-sm font-normal text-slate-400">/ {{ $clips->count() }}</span></div></div>
        <div class="card !p-4"><div class="text-xs uppercase text-slate-500">Tổng thời lượng</div><div class="text-2xl font-semibold">{{ number_format($minutes, 1) }} phút</div></div>
        <div class="card !p-4"><div class="text-xs uppercase text-slate-500">Khuyến nghị của VieNeu</div><div class="text-2xl font-semibold">10–30 phút</div></div>
    </div>

    @if ($minutes < 10)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
            Dữ liệu mới có {{ number_format($minutes, 1) }} phút, ít hơn mức khuyến nghị (10–30 phút, 100–300 câu). Vẫn train được
            nhưng dễ bị overfit; hãy theo dõi eval loss. Thêm audio mẫu của cùng người nói rồi “Tạo lại từ đầu” để có thêm dữ liệu.
        </div>
    @endif

    <form method="POST" action="{{ route('datasets.update', $voice) }}">
        @csrf @method('PUT')
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full min-w-[820px] text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-3 font-medium">#</th>
                        <th class="px-3 py-3 font-medium">Nghe</th>
                        <th class="px-3 py-3 font-medium">Độ dài</th>
                        <th class="w-full px-3 py-3 font-medium">Text (sửa cho khớp lời nói)</th>
                        <th class="px-3 py-3 text-center font-medium">Dùng</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($clips as $clip)
                        @php
                            $badLen = $clip->duration < \App\Models\DatasetClip::MIN_SEC || $clip->duration > \App\Models\DatasetClip::MAX_SEC;
                        @endphp
                        <tr class="align-top {{ $clip->included ? '' : 'opacity-50' }}">
                            <td class="px-3 py-3 text-slate-400">{{ $loop->iteration }}</td>
                            <td class="px-3 py-3"><audio controls preload="none" class="h-8 w-44" src="{{ route('datasets.audio', $clip) }}"></audio></td>
                            <td class="whitespace-nowrap px-3 py-3 {{ $badLen ? 'text-red-600' : 'text-slate-600' }}" @if ($badLen) title="Ngoài khoảng 3–15 giây nên sẽ không được xuất" @endif>{{ number_format($clip->duration, 1) }}s</td>
                            <td class="px-3 py-3">
                                <textarea class="input" rows="3" name="text[{{ $clip->id }}]">{{ $clip->text }}</textarea>
                                @if (trim((string) $clip->text) === '')
                                    <p class="mt-1 text-xs text-red-600">Chưa có text — clip này sẽ không được xuất.</p>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">
                                <input type="checkbox" name="included[{{ $clip->id }}]" value="1" @checked($clip->included)>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="sticky bottom-0 mt-4 flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-50/95 py-3">
            <span class="text-xs text-slate-500">Nhớ lưu trước khi tải zip.</span>
            <button class="btn-primary">Lưu thay đổi</button>
        </div>
    </form>
@elseif (! $processing)
    <div class="card text-sm text-slate-500">
        Chưa có bộ dữ liệu. Bấm “Tạo bộ dữ liệu” để cắt {{ $voice->samples()->count() }} audio mẫu của giọng này thành các clip và gỡ băng.
    </div>
@endif

<x-modal id="dataset-build" title="Tạo bộ dữ liệu fine-tune">
    <form method="POST" action="{{ route('datasets.build', $voice) }}" class="space-y-4">
        @csrf
        <p class="text-sm text-slate-600">
            Dùng toàn bộ audio mẫu của giọng này ({{ $voice->samples()->count() }} file). Việc gỡ băng chạy trên CPU nên
            mất khoảng vài phút cho mỗi 5 phút audio.
        </p>
        @if ($clips->isNotEmpty())
            <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Tạo lại sẽ <b>xoá các clip hiện có và mọi chỉnh sửa text</b>.</p>
        @endif
        <div>
            <label class="label" for="language">Ngôn ngữ trong audio</label>
            <select class="input" id="language" name="language">
                <option value="vi">Tiếng Việt</option>
                <option value="en">Tiếng Anh</option>
            </select>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn-ghost" data-close>Huỷ</button>
            <button class="btn-primary">Bắt đầu</button>
        </div>
    </form>
</x-modal>
@endsection
