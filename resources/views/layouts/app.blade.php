<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'LanhVCL') · LanhVCL</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    // Popup cần mở sẵn: server chỉ định rõ, hoặc form vừa gửi bị lỗi validate, hoặc trang yêu cầu (prefill)
    $openModal = session('open') ?: ($errors->any() ? old('_form') : null) ?: View::getSection('open_modal');
@endphp
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased" @if ($openModal) data-open-modal="{{ $openModal }}" @endif>
    @php
        $nav = [
            ['generations.index', 'generations*', 'Tạo audio'],
            ['voices.index', 'voices*', 'Giọng đọc'],
            ['transcriptions.index', 'youtube*', 'YouTube → Text'],
        ];
    @endphp
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center gap-8 px-4 py-3">
            <a href="{{ route('generations.index') }}" class="text-lg font-bold text-indigo-600">LanhVCL</a>
            <nav class="flex gap-1">
                @foreach ($nav as [$route, $pattern, $label])
                    <a href="{{ route($route) }}"
                       class="rounded-lg px-3 py-1.5 text-sm font-medium {{ request()->is($pattern) ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        {{-- Lỗi của form trong popup hiển thị ngay trong popup; chỉ hiện ở đây khi không thuộc popup nào --}}
        @if ($errors->any() && ! old('_form'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
