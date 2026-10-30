@extends('layouts.app')
@section('title', 'Giọng đọc')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-lg font-semibold">Giọng đọc <span class="text-sm font-normal text-slate-500">({{ $voices->count() }})</span></h1>
    <div class="flex gap-2">
        <button type="button" class="btn-ghost" data-open="presets-modal">Giọng có sẵn ({{ count($presets) }})</button>
        <button type="button" class="btn-primary" data-open="voice-create">+ Thêm giọng</button>
    </div>
</div>

<x-bulk-bar id="bulk-voices" :action="route('voices.bulk-destroy')" noun="giọng" />

<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full min-w-[760px] text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="w-8 px-3 py-3"><input type="checkbox" form="bulk-voices" data-bulk-all="bulk-voices" aria-label="Chọn tất cả" class="size-4 cursor-pointer"></th>
                <th class="px-3 py-3 font-medium">Tên giọng</th>
                <th class="px-3 py-3 font-medium">Ghi chú</th>
                <th class="px-3 py-3 text-center font-medium">Số mẫu</th>
                <th class="px-3 py-3 font-medium">Nghe mẫu chính</th>
                <th class="px-3 py-3 font-medium">Ngày tạo</th>
                <th class="px-3 py-3 text-right font-medium">Thao tác</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($voices as $voice)
                <tr class="hover:bg-slate-50/60">
                    <td class="px-3 py-3"><input type="checkbox" form="bulk-voices" name="ids[]" value="{{ $voice->id }}" data-bulk-item="bulk-voices" aria-label="Chọn" class="size-4 cursor-pointer"></td>
                    <td class="px-3 py-3 font-medium">{{ $voice->name }}</td>
                    <td class="max-w-xs truncate px-3 py-3 text-slate-500" title="{{ $voice->description }}">{{ $voice->description ?: '—' }}</td>
                    <td class="px-3 py-3 text-center">{{ $voice->samples->count() }}</td>
                    <td class="px-3 py-3">
                        @if ($voice->primarySample)
                            <audio controls preload="none" class="h-8 w-44" src="{{ route('voices.samples.audio', $voice->primarySample) }}"></audio>
                        @else
                            <span class="text-slate-400">Chưa có mẫu</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ $voice->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-3">
                        <div class="flex justify-end gap-2">
                            <a class="btn-primary btn-sm" href="{{ route('generations.index', ['voice' => 'voice:'.$voice->id]) }}">Dùng giọng</a>
                            <button type="button" class="btn-ghost btn-sm" data-open="voice-manage-{{ $voice->id }}">Quản lý</button>
                            <a class="btn-ghost btn-sm" href="{{ route('datasets.show', $voice) }}" title="Cắt clip + gỡ băng + xuất zip để fine-tune LoRA">Fine-tune</a>
                            <form method="POST" action="{{ route('voices.destroy', $voice) }}" data-confirm="Xoá giọng &quot;{{ $voice->name }}&quot; cùng toàn bộ audio mẫu?">
                                @csrf @method('DELETE')
                                <button class="btn-danger btn-sm">Xoá</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Chưa có giọng nào. Bấm “+ Thêm giọng” để tạo giọng đầu tiên.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Popup: thêm giọng --}}
<x-modal id="voice-create" title="Thêm giọng mới">
    <form method="POST" action="{{ route('voices.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="voice-create">
        <div>
            <label class="label" for="name">Tên giọng</label>
            <input class="input" id="name" name="name" value="{{ old('_form') === 'voice-create' ? old('name') : '' }}" required maxlength="100" placeholder="VD: Giọng của Lành">
        </div>
        <div>
            <label class="label" for="description">Ghi chú</label>
            <textarea class="input" id="description" name="description" rows="2" maxlength="500">{{ old('_form') === 'voice-create' ? old('description') : '' }}</textarea>
        </div>
        <p class="text-xs text-slate-500">Cần ít nhất một audio mẫu: upload file, hoặc lấy từ link YouTube (hoặc cả hai).</p>
        <div>
            <label class="label" for="samples">Upload file audio mẫu (data training)</label>
            <input class="input" type="file" id="samples" name="samples[]" accept="audio/*" multiple>
            <p class="mt-1 text-xs text-slate-500">
                wav/mp3/m4a/ogg/flac, tối đa 200MB mỗi file. VieNeu clone giọng từ đoạn mẫu 3–8 giây,
                sạch tiếng ồn, chỉ 1 người nói. Có thể thêm nhiều file và chọn 1 file làm mẫu chính.
            </p>
        </div>
        <div class="rounded-lg border border-slate-200 p-3">
            <div class="mb-2 text-sm font-medium text-slate-700">Hoặc lấy từ YouTube</div>
            @include('voices._youtube-fields', ['idPrefix' => 'create', 'keep' => old('_form') === 'voice-create'])
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn-ghost" data-close>Huỷ</button>
            <button class="btn-primary">Tạo giọng</button>
        </div>
    </form>
</x-modal>

{{-- Popup: giọng có sẵn --}}
<x-modal id="presets-modal" title="Giọng có sẵn của VieNeu" size="max-w-xl">
    @if (count($presets))
        <p class="mb-3 text-xs text-slate-500">Dùng được ngay ở trang “Tạo audio”, không cần audio mẫu.</p>
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                <tr><th class="py-2 font-medium">Giọng</th><th class="py-2 text-right font-medium"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($presets as $p)
                    <tr>
                        <td class="py-2">{{ $p['label'] }}</td>
                        <td class="py-2 text-right">
                            <a class="btn-ghost btn-sm" href="{{ route('generations.index', ['voice' => 'preset:'.$p['id']]) }}">Dùng giọng</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="text-sm text-amber-700">Chưa lấy được danh sách. Kiểm tra TTS service đang chạy (docker compose up -d).</p>
    @endif
</x-modal>

{{-- Popup: quản lý từng giọng (thông tin + audio mẫu) --}}
@foreach ($voices as $voice)
    <x-modal id="voice-manage-{{ $voice->id }}" :title="'Quản lý giọng: '.$voice->name" size="max-w-5xl">
        <form method="POST" action="{{ route('voices.update', $voice) }}" class="grid gap-3 sm:grid-cols-2">
            @csrf @method('PUT')
            <input type="hidden" name="_form" value="voice-manage-{{ $voice->id }}">
            <div>
                <label class="label">Tên giọng</label>
                <input class="input" name="name" value="{{ $voice->name }}" required maxlength="100">
            </div>
            <div>
                <label class="label">Ghi chú</label>
                <input class="input" name="description" value="{{ $voice->description }}" maxlength="500">
            </div>
            <div class="sm:col-span-2">
                <button class="btn-ghost btn-sm">Lưu thông tin</button>
            </div>
        </form>

        <div class="mb-2 mt-5 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-700">Audio mẫu ({{ $voice->samples->count() }})</h3>
            <a class="btn-ghost btn-sm" href="{{ route('datasets.show', $voice) }}">Chuẩn bị dữ liệu fine-tune →</a>
        </div>
        <x-bulk-bar :id="'bulk-samples-'.$voice->id" :action="route('voices.samples.bulk-destroy', $voice)" noun="audio mẫu" />
        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full min-w-[780px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-8 px-3 py-2"><input type="checkbox" form="bulk-samples-{{ $voice->id }}" data-bulk-all="bulk-samples-{{ $voice->id }}" aria-label="Chọn tất cả" class="size-4 cursor-pointer"></th>
                        <th class="px-3 py-2 font-medium">File</th>
                        <th class="px-3 py-2 font-medium">Thời lượng</th>
                        <th class="px-3 py-2 font-medium">Nghe cả file</th>
                        <th class="px-3 py-2 font-medium">Đoạn dùng để clone</th>
                        <th class="px-3 py-2 text-right font-medium">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($voice->samples as $sample)
                        <tr>
                            <td class="px-3 py-2"><input type="checkbox" form="bulk-samples-{{ $voice->id }}" name="ids[]" value="{{ $sample->id }}" data-bulk-item="bulk-samples-{{ $voice->id }}" aria-label="Chọn" class="size-4 cursor-pointer"></td>
                            <td class="px-3 py-2">
                                <div class="max-w-[14rem] truncate" title="{{ $sample->original_name }}">{{ $sample->original_name }}</div>
                                @if ($voice->primary_sample_id === $sample->id)
                                    <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-xs font-medium text-indigo-700">Mẫu chính</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-slate-500">{{ $sample->duration ? number_format($sample->duration, 1).' giây' : '—' }}</td>
                            <td class="px-3 py-2"><audio controls preload="none" class="h-8 w-44" src="{{ route('voices.samples.audio', $sample) }}"></audio></td>
                            <td class="px-3 py-2">
                                @if (($sample->duration ?? 0) <= 8.5)
                                    <span class="text-xs text-slate-500">Dùng cả file (≤ 8 giây)</span>
                                @else
                                    <div class="mb-1 text-xs text-slate-600">
                                        @if ($sample->clip_start !== null)
                                            Từ <b>{{ number_format($sample->clip_start, 1) }}s</b>, dài {{ number_format($sample->clip_len ?? 8, 1) }}s
                                        @else
                                            <span class="text-amber-700">Đang dùng 8 giây đầu file</span>
                                        @endif
                                    </div>
                                    <audio controls preload="none" class="mb-1 h-8 w-44" src="{{ route('voices.samples.clip', $sample) }}"></audio>
                                    <div class="flex items-center gap-1">
                                        <form method="POST" action="{{ route('voices.samples.clip.update', $sample) }}" class="flex items-center gap-1">
                                            @csrf @method('PUT')
                                            <input class="input !w-20 !px-2 !py-1 text-xs" name="clip_start" value="{{ $sample->clip_start !== null ? round($sample->clip_start, 1) : '' }}" placeholder="giây" title="Bắt đầu từ giây (hoặc mm:ss)" required>
                                            <button class="btn-ghost btn-sm">Đặt</button>
                                        </form>
                                        <form method="POST" action="{{ route('voices.samples.clip.auto', $sample) }}" data-busy-form>
                                            @csrf
                                            <button class="btn-ghost btn-sm" data-busy="Đang phân tích…" title="Tự tìm đoạn giọng đều, liền mạch nhất">Tự chọn</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex justify-end gap-2">
                                    @if ($voice->primary_sample_id !== $sample->id)
                                        <form method="POST" action="{{ route('voices.update', $voice) }}">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="name" value="{{ $voice->name }}">
                                            <input type="hidden" name="description" value="{{ $voice->description }}">
                                            <input type="hidden" name="primary_sample_id" value="{{ $sample->id }}">
                                            <button class="btn-ghost btn-sm">Đặt làm mẫu chính</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('voices.samples.destroy', $sample) }}" data-confirm="Xoá audio mẫu này?">
                                        @csrf @method('DELETE')
                                        <button class="btn-danger btn-sm">Xoá</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">Chưa có audio mẫu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5 space-y-4">
            <form method="POST" action="{{ route('voices.samples.store', $voice) }}" enctype="multipart/form-data" class="space-y-2">
                @csrf
                <input type="hidden" name="_form" value="voice-manage-{{ $voice->id }}">
                <div class="text-sm font-semibold text-slate-700">Thêm từ file</div>
                <div class="flex flex-wrap items-center gap-2">
                    <input class="input !w-auto flex-1" type="file" name="samples[]" accept="audio/*" multiple required>
                    <button class="btn-primary btn-sm">Thêm audio mẫu</button>
                </div>
            </form>

            <form method="POST" action="{{ route('voices.samples.store', $voice) }}" class="space-y-2 rounded-lg border border-slate-200 p-3">
                @csrf
                <input type="hidden" name="_form" value="voice-manage-{{ $voice->id }}">
                <div class="text-sm font-semibold text-slate-700">Thêm từ link YouTube</div>
                @include('voices._youtube-fields', ['idPrefix' => 'manage-'.$voice->id, 'keep' => old('_form') === 'voice-manage-'.$voice->id])
                <button class="btn-primary btn-sm" data-busy="Đang tải audio từ YouTube…">Lấy audio từ YouTube</button>
            </form>
        </div>
    </x-modal>
@endforeach
@endsection
