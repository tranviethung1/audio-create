@extends('layouts.app')
@section('title', 'Tạo audio')

@php
    $selected = old('voice', request('voice', $prefillVoice ?? ($voices->isNotEmpty() ? 'voice:'.$voices->first()->id : ($presets[0]['id'] ?? ''))));
    // Mở sẵn popup khi đi từ trang khác sang với giọng / text / cài đặt được điền sẵn
    $wantsCreate = request()->hasAny(['voice', 'from', 'again']);
@endphp
@if ($wantsCreate)
    @section('open_modal', 'generation-create')
@endif

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-lg font-semibold">Audio đã tạo</h1>
    <button type="button" class="btn-primary" data-open="generation-create">+ Tạo audio mới</button>
</div>

<div data-live-list="{{ route('generations.list') }}">
    @include('generations._list')
</div>

{{-- Popup: tạo audio --}}
<x-modal id="generation-create" title="Tạo audio từ text" size="max-w-2xl">
    @php
        $mine = old('_form') === 'generation-create';
        $st = $mine ? [
            'temperature' => old('temperature'), 'silence_p' => old('silence_p'),
            'max_chars' => old('max_chars'), 'denoise' => old('denoise', 1),
        ] : $prefillSettings;
        $temp = $st['temperature'] ?? 0.8;
        $sil = $st['silence_p'] ?? 0.15;
        $maxc = $st['max_chars'] ?? 256;
        $den = (bool) ($st['denoise'] ?? true);
    @endphp
    <form method="POST" action="{{ route('generations.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="generation-create">

        <div>
            <label class="label" for="voice">Giọng đọc</label>
            <select class="input" id="voice" name="voice" required>
                @if ($voices->isNotEmpty())
                    <optgroup label="Giọng của bạn">
                        @foreach ($voices as $v)
                            <option value="voice:{{ $v->id }}" @selected($selected === 'voice:'.$v->id)>{{ $v->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
                @if (count($presets))
                    <optgroup label="Giọng có sẵn (VieNeu)">
                        @foreach ($presets as $p)
                            <option value="preset:{{ $p['id'] }}" @selected($selected === 'preset:'.$p['id'])>{{ $p['label'] }}</option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
            @if ($voices->isEmpty() && ! count($presets))
                <p class="mt-1 text-xs text-amber-700">Chưa có giọng nào. Chạy TTS service hoặc thêm giọng ở trang “Giọng đọc”.</p>
            @endif
        </div>

        <div>
            <label class="label" for="title">Tiêu đề (không bắt buộc)</label>
            <input class="input" id="title" name="title" value="{{ $mine ? old('title') : $prefillTitle }}" maxlength="150" placeholder="Để trống sẽ lấy đoạn đầu của text">
        </div>

        <div>
            <label class="label" for="text">Nội dung</label>
            <textarea class="input" id="text" name="text" rows="9" required maxlength="50000" placeholder="Nhập hoặc dán văn bản tiếng Việt...">{{ $mine ? old('text') : $prefillText }}</textarea>
            <p class="mt-1 text-xs text-slate-500">Có thể chèn cảm xúc thử nghiệm: [cười] [thở dài] [hắng giọng].</p>
        </div>

        <details class="rounded-lg border border-slate-200 p-3" @if ($prefillSettings) open @endif>
            <summary class="cursor-pointer text-sm font-medium text-slate-700">Tinh chỉnh ngữ điệu</summary>
            <div class="mt-3 space-y-4">
                <div>
                    <label class="label flex justify-between" for="temperature">
                        <span>Độ biểu cảm (temperature)</span><output id="o-temperature" class="font-mono text-slate-500">{{ $temp }}</output>
                    </label>
                    <input type="range" id="temperature" name="temperature" min="0.3" max="1.3" step="0.05" value="{{ $temp }}" class="w-full" oninput="document.getElementById('o-temperature').value=this.value">
                    <p class="text-xs text-slate-500">Thấp: đều, ổn định, hơi đơn điệu. Cao: nhấn nhá nhiều hơn nhưng dễ sai/lạc. Mặc định 0.8.</p>
                </div>
                <div>
                    <label class="label flex justify-between" for="silence_p">
                        <span>Độ nghỉ giữa các đoạn (silence)</span><output id="o-silence_p" class="font-mono text-slate-500">{{ $sil }}</output>
                    </label>
                    <input type="range" id="silence_p" name="silence_p" min="0" max="0.6" step="0.05" value="{{ $sil }}" class="w-full" oninput="document.getElementById('o-silence_p').value=this.value">
                    <p class="text-xs text-slate-500">Tăng nếu giọng đọc dồn dập, không có quãng nghỉ. Mặc định 0.15.</p>
                </div>
                <div>
                    <label class="label flex justify-between" for="max_chars">
                        <span>Độ dài mỗi đoạn khi chia câu (ký tự)</span><output id="o-max_chars" class="font-mono text-slate-500">{{ $maxc }}</output>
                    </label>
                    <input type="range" id="max_chars" name="max_chars" min="96" max="400" step="8" value="{{ $maxc }}" class="w-full" oninput="document.getElementById('o-max_chars').value=this.value">
                    <p class="text-xs text-slate-500">Text dài được chia thành các đoạn rồi ghép lại. Giảm xuống nếu ngữ điệu bị trôi ở câu dài. Mặc định 256.</p>
                </div>
                <label class="flex items-start gap-2 text-sm">
                    <input type="hidden" name="denoise" value="0">
                    <input type="checkbox" name="denoise" value="1" class="mt-0.5" @checked($den)>
                    <span>Khử nhiễu file audio mẫu (chỉ áp dụng cho giọng của bạn). Tắt nếu mẫu đã sạch.</span>
                </label>
            </div>
        </details>

        <label class="flex items-start gap-2 text-sm">
            <input type="checkbox" name="split" value="1" class="mt-0.5" @checked($mine && old('split'))>
            <span>Tách mỗi đoạn (cách nhau bằng dòng trống) thành một file audio riêng</span>
        </label>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn-ghost" data-close>Huỷ</button>
            <button class="btn-primary">Tạo audio</button>
        </div>
    </form>
</x-modal>

{{-- Popup: xem nội dung text của một audio --}}
<x-modal id="text-view" title="Nội dung" size="max-w-2xl">
    <h3 class="mb-2 text-sm font-semibold" data-slot="title"></h3>
    <p class="max-h-[50vh] overflow-y-auto whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm leading-relaxed" data-slot="body"></p>
    <div class="mt-4 flex justify-end gap-2">
        <button type="button" class="btn-ghost" data-close>Đóng</button>
        <a class="btn-primary" data-slot="again" href="#" hidden>Tạo lại với cài đặt khác</a>
    </div>
</x-modal>
@endsection
