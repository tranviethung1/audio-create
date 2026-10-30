{{-- Các ô nhập audio mẫu từ link YouTube. Biến: $idPrefix (để id không trùng giữa các popup), $keep (giữ lại giá trị cũ khi form lỗi) --}}
<div class="space-y-2">
    <div>
        <label class="label" for="{{ $idPrefix }}-yt-url">Link YouTube</label>
        <input class="input" id="{{ $idPrefix }}-yt-url" name="youtube_url" type="url" value="{{ $keep ? old('youtube_url') : '' }}" placeholder="https://www.youtube.com/watch?v=...">
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="label" for="{{ $idPrefix }}-yt-start">Từ (giây hoặc mm:ss)</label>
            <input class="input" id="{{ $idPrefix }}-yt-start" name="yt_start" value="{{ $keep ? old('yt_start') : '' }}" placeholder="VD: 1:30">
        </div>
        <div>
            <label class="label" for="{{ $idPrefix }}-yt-end">Đến (giây hoặc mm:ss)</label>
            <input class="input" id="{{ $idPrefix }}-yt-end" name="yt_end" value="{{ $keep ? old('yt_end') : '' }}" placeholder="VD: 1:38">
        </div>
    </div>
    <p class="text-xs text-slate-500">
        Nên chọn một đoạn 5–8 giây chỉ có một người nói, không nhạc nền, đọc tự nhiên.
        Để trống “Từ/Đến” sẽ lấy cả video (chỉ nhận video ≤ 15 phút).
    </p>
</div>
