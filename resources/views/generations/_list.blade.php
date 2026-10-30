@php
    $badges = [
        'pending' => ['Đang chờ', 'bg-slate-100 text-slate-700'],
        'processing' => ['Đang tạo…', 'bg-amber-100 text-amber-800'],
        'done' => ['Hoàn tất', 'bg-emerald-100 text-emerald-800'],
        'failed' => ['Lỗi', 'bg-red-100 text-red-700'],
    ];
@endphp

<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full min-w-[880px] text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-3 py-3 font-medium">Tiêu đề</th>
                <th class="px-3 py-3 font-medium">Giọng</th>
                <th class="px-3 py-3 font-medium">Trạng thái</th>
                <th class="px-3 py-3 font-medium">Thời lượng</th>
                <th class="px-3 py-3 font-medium">Nghe</th>
                <th class="px-3 py-3 font-medium">Ngày tạo</th>
                <th class="px-3 py-3 text-right font-medium">Thao tác</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($generations as $g)
                <tr class="align-middle hover:bg-slate-50/60" @if (! $g->isFinished()) data-pending="1" @endif>
                    <td class="max-w-xs px-3 py-3">
                        <div class="truncate font-medium" title="{{ $g->title }}">{{ $g->title }}</div>
                        <button type="button" class="mt-0.5 text-xs text-indigo-600 hover:underline"
                                data-view-text="{{ route('generations.show', $g) }}" data-title="{{ $g->title }}">Xem nội dung</button>
                        @if (! empty($g->settings))
                            <div class="mt-0.5 text-xs text-slate-400">
                                biểu cảm {{ $g->settings['temperature'] ?? 0.8 }} · nghỉ {{ $g->settings['silence_p'] ?? 0.15 }} · đoạn {{ $g->settings['max_chars'] ?? 256 }}
                            </div>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-3">{{ $g->voice_label }}</td>
                    <td class="px-3 py-3">
                        <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium {{ $badges[$g->status][1] ?? '' }}">{{ $badges[$g->status][0] ?? $g->status }}</span>
                        @if ($g->status === 'failed')
                            <div class="mt-1 max-w-[16rem] text-xs text-red-600" title="{{ $g->error }}">{{ \Illuminate\Support\Str::limit($g->error, 90) }}</div>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $g->duration ? number_format($g->duration, 1).'s' : '—' }}</td>
                    <td class="px-3 py-3">
                        @if ($g->status === 'done')
                            <audio controls preload="none" class="h-8 w-44" src="{{ route('generations.audio', $g) }}"></audio>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ $g->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-3">
                        <div class="flex justify-end gap-2">
                            @if ($g->status === 'done')
                                <a class="btn-ghost btn-sm" href="{{ route('generations.download', $g) }}">Tải về</a>
                            @endif
                            @if ($g->status === 'failed')
                                <form method="POST" action="{{ route('generations.retry', $g) }}">@csrf <button class="btn-ghost btn-sm">Thử lại</button></form>
                            @endif
                            <a class="btn-ghost btn-sm" href="{{ route('generations.index', ['again' => $g->id]) }}" title="Điền lại text, giọng và cài đặt vào form để thử cài đặt khác">Tạo lại</a>
                            <form method="POST" action="{{ route('generations.destroy', $g) }}" data-confirm="Xoá audio này?">
                                @csrf @method('DELETE')
                                <button class="btn-danger btn-sm">Xoá</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Chưa có audio nào. Bấm “+ Tạo audio mới” để bắt đầu.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
