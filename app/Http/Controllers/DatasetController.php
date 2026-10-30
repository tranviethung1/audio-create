<?php

namespace App\Http\Controllers;

use App\Jobs\BuildDatasetJob;
use App\Models\DatasetClip;
use App\Models\Voice;
use App\Services\TtsClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use ZipArchive;

/**
 * Bộ dữ liệu fine-tune LoRA cho VieNeu: cắt clip 3–15s từ audio mẫu, gỡ băng, cho rà soát text, xuất zip.
 */
class DatasetController extends Controller
{
    public function show(Voice $voice): View
    {
        $clips = $voice->datasetClips()->get();
        $usable = $clips->filter->isUsable();

        return view('datasets.show', [
            'voice' => $voice,
            'clips' => $clips,
            'usableCount' => $usable->count(),
            'usableSeconds' => $usable->sum('duration'),
        ]);
    }

    public function build(Request $request, Voice $voice): RedirectResponse
    {
        $data = $request->validate(['language' => 'required|in:vi,en']);

        if (! $voice->samples()->exists()) {
            return back()->withErrors(['dataset' => 'Giọng này chưa có audio mẫu.']);
        }
        if ($voice->dataset_status === 'processing') {
            return back()->withErrors(['dataset' => 'Đang xử lý, vui lòng đợi.']);
        }

        $voice->update(['dataset_status' => 'processing', 'dataset_error' => null]);
        BuildDatasetJob::dispatch($voice->id, $data['language']);

        return redirect()->route('datasets.show', $voice)->with('status', 'Đang cắt clip và gỡ băng, có thể mất vài phút…');
    }

    public function update(Request $request, Voice $voice): RedirectResponse
    {
        $texts = (array) $request->input('text', []);
        $included = (array) $request->input('included', []);

        foreach ($voice->datasetClips as $clip) {
            $clip->update([
                'text' => $this->cleanText($texts[$clip->id] ?? $clip->text),
                'included' => isset($included[$clip->id]),
            ]);
        }

        return back()->with('status', 'Đã lưu bộ dữ liệu.');
    }

    public function audio(DatasetClip $clip)
    {
        abort_unless(Storage::exists($clip->file), 404);

        return response()->file(Storage::path($clip->file), ['Content-Type' => 'audio/wav']);
    }

    /** Xuất zip đúng định dạng của VieNeu `finetune/prepare_dataset.py`: metadata.csv + raw_audio/. */
    public function export(Voice $voice, TtsClient $tts)
    {
        $clips = $voice->datasetClips()->get()->filter->isUsable()->values();
        if ($clips->isEmpty()) {
            return back()->withErrors(['dataset' => 'Chưa có clip nào dùng được (cần bật, có text, dài 3–15 giây).']);
        }

        $slug = Str::slug($voice->name) ?: 'voice';
        $dir = storage_path('app/private/lanhvcl/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $zipPath = "{$dir}/{$slug}-".bin2hex(random_bytes(4)).'.zip';

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->withErrors(['dataset' => 'Không tạo được file zip.']);
        }

        $lines = ['file_name|text'];
        foreach ($clips as $i => $clip) {
            $name = sprintf('%s_%03d.wav', $slug, $i + 1);
            $zip->addFile(Storage::path($clip->file), "dataset/raw_audio/{$name}");
            $lines[] = $name.'|'.$this->cleanText($clip->text);
        }
        $zip->addFromString('dataset/metadata.csv', implode("\n", $lines)."\n");

        // Clip tham chiếu 3–8s của đúng người nói này (cho make_voice.py)
        $hasRef = false;
        $primary = $voice->primarySample ?? $voice->samples()->first();
        if ($primary) {
            try {
                $refPath = "lanhvcl/cache/clips/ds-{$voice->id}-ref.wav";
                $tts->clip($primary->path, $refPath, $primary->clip_start ?? 0.0, min(8.0, $primary->clip_len ?? 8.0));
                $zip->addFile(Storage::path($refPath), 'ref.wav');
                $hasRef = true;
            } catch (\Throwable) {
                // không bắt buộc: người dùng tự cắt clip 3–8s
            }
        }

        $total = $clips->sum('duration');
        $zip->addFromString('HUONG_DAN.md', $this->guide($voice->name, $clips->count(), $total, $hasRef));
        $zip->close();

        return response()->download($zipPath, "{$slug}-finetune-dataset.zip")->deleteFileAfterSend(true);
    }

    /** metadata.csv ngăn cột bằng "|" và mỗi clip một dòng: bỏ "|" và xuống dòng khỏi text. */
    private function cleanText(?string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace('|', ' ', (string) $text)));
    }

    private function guide(string $voiceName, int $clipCount, float $seconds, bool $hasRef): string
    {
        $minutes = number_format($seconds / 60, 1);
        $ref = $hasRef ? 'ref.wav' : 'đường/dẫn/tới/clip-3-8-giây.wav';
        $name = str_replace('"', '', $voiceName);

        return <<<MD
# Fine-tune VieNeu-TTS v3 Turbo (LoRA) — giọng "{$name}"

Bộ dữ liệu: **{$clipCount} clip, khoảng {$minutes} phút** (1 người nói).
Tài liệu gốc: https://github.com/pnnbao97/VieNeu-TTS/tree/main/finetune

> Khuyến nghị của tác giả: 10–30 phút audio sạch (100–300 câu khác nhau). Ít hơn thì dễ overfit
> (học thuộc thay vì học giọng) — hãy theo dõi eval loss và chọn checkpoint tốt nhất.

## Yêu cầu
- Máy có **GPU NVIDIA (CUDA)**, khoảng 6 GB VRAM với cấu hình mặc định (Google Colab T4 là đủ).
- Python + [uv](https://docs.astral.sh/uv/).

## Các bước

```bash
git clone https://github.com/pnnbao97/VieNeu-TTS.git
cd VieNeu-TTS
uv sync --extra finetune

# 1) Giải nén file zip này, chép thư mục dataset/ vào finetune/dataset/
#    finetune/dataset/metadata.csv  và  finetune/dataset/raw_audio/*.wav

# 2) Chuẩn bị dữ liệu (chạy được trên CPU) -> finetune/dataset/train.parquet
uv run python finetune/prepare_dataset.py --dataset-dir finetune/dataset

# 3) Train LoRA (cần GPU).
#    Dữ liệu ít nên dùng batch nhỏ, nhiều epoch, giữ eval và lưu checkpoint thường xuyên.
#    Đây là gợi ý điểm khởi đầu, chưa được kiểm chứng — hãy điều chỉnh theo eval loss.
uv run python finetune/train_lora.py --data finetune/dataset/train.parquet --run my_voice --merge \
    --epochs 20 --batch-size 2 --grad-accum 2 \
    --eval-ratio 0.1 --eval-every 10 --save-every 10

# 4) Đóng gói giọng (speaker embedding) từ clip 3-8s của đúng người nói
uv run python finetune/make_voice.py --audio {$ref} --name "{$name}" \
    --description "Giọng fine-tune" --gender female \
    --out finetune/output/my_voice/merged        # đổi --gender male nếu là giọng nam
```

Theo dõi log train: `acc_cb0` nên ở mức 0.2–0.4 ngay từ bước đầu. Nếu gần 0 thì text không khớp audio
— kiểm tra lại cột text trong metadata.csv.

## Dùng model sau khi train

```python
from vieneu import Vieneu
tts = Vieneu(mode="v3turbo", backbone_repo="finetune/output/my_voice/merged")
audio = tts.infer("Xin chào, đây là giọng đã fine-tune.", voice="{$name}")
tts.save(audio, "out.wav")
```

Lưu ý: model đã fine-tune **chỉ chạy trên GPU (PyTorch)**; đường CPU/ONNX của VieNeu chưa nhận model fine-tune.

## Quy cách dữ liệu (đã được app chuẩn bị sẵn)
- `metadata.csv`: mỗi dòng `file_name|text`, tên file nằm trong `raw_audio/`.
- Mỗi clip 3–15 giây, đúng một người nói, text khớp từng chữ (kể cả dấu câu).
MD;
    }
}
