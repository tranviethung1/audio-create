@props(['id', 'action', 'noun' => 'mục'])

{{-- Form rỗng: các checkbox trong bảng gắn vào đây bằng thuộc tính form="{{ $id }}" (tránh form lồng nhau) --}}
<form id="{{ $id }}" method="POST" action="{{ $action }}" data-bulk-confirm="Xoá {n} {{ $noun }} đã chọn? Không thể hoàn tác.">
    @csrf @method('DELETE')
</form>
<div data-bulk-bar="{{ $id }}" hidden class="mb-2 flex flex-wrap items-center gap-3 rounded-lg bg-indigo-50 px-3 py-2 text-sm text-indigo-800">
    <span>Đã chọn <b data-bulk-count>0</b> {{ $noun }}</span>
    <button type="submit" form="{{ $id }}" class="btn-danger btn-sm">Xoá đã chọn</button>
    <button type="button" class="btn-ghost btn-sm" data-bulk-clear="{{ $id }}">Bỏ chọn</button>
</div>
