@php
    $badges = [
        'pending' => ['Đang chờ', 'bg-slate-100 text-slate-700'],
        'processing' => ['Đang xử lý…', 'bg-amber-100 text-amber-800'],
        'done' => ['Hoàn tất', 'bg-emerald-100 text-emerald-800'],
        'failed' => ['Lỗi', 'bg-red-100 text-red-700'],
    ];
@endphp

<x-bulk-bar id="bulk-transcriptions" :action="route('transcriptions.bulk-destroy')" noun="video" />

<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full min-w-[880px] text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="w-8 px-3 py-3"><input type="checkbox" form="bulk-transcriptions" data-bulk-all="bulk-transcriptions" aria-label="Chọn tất cả" class="size-4 cursor-pointer"></th>
                <th class="px-3 py-3 font-medium">Video</th>
                <th class="px-3 py-3 font-medium">Kênh</th>
                <th class="px-3 py-3 font-medium">Thời lượng</th>
                <th class="px-3 py-3 font-medium">Nguồn text</th>
                <th class="px-3 py-3 font-medium">Trạng thái</th>
                <th class="px-3 py-3 font-medium">Ngày import</th>
                <th class="px-3 py-3 text-right font-medium">Thao tác</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($transcriptions as $t)
                <tr class="align-middle hover:bg-slate-50/60" @if (! $t->isFinished()) data-pending="1" @endif>
                    <td class="px-3 py-3"><input type="checkbox" form="bulk-transcriptions" name="ids[]" value="{{ $t->id }}" data-bulk-item="bulk-transcriptions" aria-label="Chọn" class="size-4 cursor-pointer"></td>
                    <td class="max-w-sm px-3 py-3">
                        <div class="flex items-center gap-3">
                            @if ($t->thumbnail)
                                <img src="{{ $t->thumbnail }}" alt="" class="h-12 w-20 shrink-0 rounded object-cover" loading="lazy" referrerpolicy="no-referrer">
                            @endif
                            <a href="{{ route('transcriptions.show', $t) }}" class="line-clamp-2 font-medium text-indigo-700 hover:underline" title="{{ $t->title }}">{{ $t->title }}</a>
                        </div>
                    </td>
                    <td class="max-w-[10rem] truncate px-3 py-3 text-slate-600" title="{{ $t->channel }}">{{ $t->channel ?: '—' }}</td>
                    <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $t->duration ? gmdate($t->duration >= 3600 ? 'G:i:s' : 'i:s', $t->duration) : '—' }}</td>
                    <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $t->source ? ($t->source === 'subtitle' ? 'Phụ đề' : 'Whisper') : '—' }}</td>
                    <td class="px-3 py-3">
                        <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium {{ $badges[$t->status][1] ?? '' }}">{{ $badges[$t->status][0] ?? $t->status }}</span>
                        @if ($t->status === 'failed')
                            <div class="mt-1 max-w-[16rem] text-xs text-red-600" title="{{ $t->error }}">{{ \Illuminate\Support\Str::limit($t->error, 90) }}</div>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ $t->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-3">
                        <div class="flex justify-end gap-2">
                            <a class="btn-ghost btn-sm" href="{{ route('transcriptions.show', $t) }}">Xem / sửa</a>
                            @if ($t->status === 'done')
                                <a class="btn-primary btn-sm" href="{{ route('generations.index', ['from' => $t->id]) }}">Tạo audio</a>
                            @endif
                            @if ($t->status === 'failed')
                                <form method="POST" action="{{ route('transcriptions.retry', $t) }}">@csrf <button class="btn-ghost btn-sm">Thử lại</button></form>
                            @endif
                            <form method="POST" action="{{ route('transcriptions.destroy', $t) }}" data-confirm="Xoá &quot;{{ $t->title }}&quot;?">
                                @csrf @method('DELETE')
                                <button class="btn-danger btn-sm">Xoá</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">Chưa có video nào được import. Bấm “+ Import link YouTube” để bắt đầu.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
