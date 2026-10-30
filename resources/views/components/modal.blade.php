@props(['id', 'title', 'size' => 'max-w-lg'])

<dialog id="{{ $id }}" {{ $attributes->class(['m-auto w-[calc(100%-2rem)] rounded-xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-900/50', $size]) }}>
    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
        <h2 class="text-base font-semibold">{{ $title }}</h2>
        <button type="button" data-close class="cursor-pointer rounded-md px-2 py-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Đóng">✕</button>
    </div>
    <div class="max-h-[78vh] overflow-y-auto p-5">
        @if ($errors->any() && old('_form') === $id)
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif
        {{ $slot }}
    </div>
</dialog>
