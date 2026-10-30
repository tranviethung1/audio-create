"""LanhVCL TTS service: VieNeu (text -> speech), yt-dlp + faster-whisper (YouTube -> text)."""
import json
import os
import re
import shutil
import subprocess
import tempfile
import threading
from pathlib import Path

import soundfile as sf
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

DATA_DIR = Path(os.environ.get("DATA_DIR", "/data")).resolve()
WHISPER_MODEL = os.environ.get("WHISPER_MODEL", "small")
VIENEU_PRECISION = os.environ.get("VIENEU_PRECISION", "")  # "" = fp32 | "int8"

app = FastAPI(title="LanhVCL TTS service")

_tts = None
_tts_lock = threading.Lock()  # VieNeu không thread-safe -> tuần tự hoá
_whisper_models: dict = {}
_whisper_lock = threading.Lock()
DATASET_WHISPER_MODEL = os.environ.get("WHISPER_DATASET_MODEL", "medium")


def safe_path(rel: str) -> Path:
    """Chỉ cho phép truy cập file trong DATA_DIR."""
    p = (DATA_DIR / rel).resolve()
    if DATA_DIR not in p.parents and p != DATA_DIR:
        raise HTTPException(400, "Đường dẫn không hợp lệ")
    return p


def get_tts():
    global _tts
    if _tts is None:
        from vieneu import Vieneu

        kwargs = {"precision": VIENEU_PRECISION} if VIENEU_PRECISION else {}
        _tts = Vieneu(**kwargs)
    return _tts


def get_whisper(name: str | None = None):
    """Whisper theo tên model (cache theo tên). Mặc định dùng WHISPER_MODEL."""
    name = name or WHISPER_MODEL
    if name not in _whisper_models:
        from faster_whisper import WhisperModel

        _whisper_models[name] = WhisperModel(name, device="auto", compute_type="int8")
    return _whisper_models[name]


def to_wav(src: Path, dst: Path, start: float | None = None, length: float | None = None) -> None:
    """Chuẩn hoá mọi định dạng (mp3/m4a/webm/...) sang wav mono 48kHz (giữ chất lượng cho clone).

    Có thể cắt đoạn [start, start+length] ngay lúc chuyển đổi.
    """
    cmd = ["ffmpeg", "-y", "-loglevel", "error"]
    if start:
        cmd += ["-ss", str(start)]
    cmd += ["-i", str(src)]
    if length:
        cmd += ["-t", str(length)]
    cmd += ["-ac", "1", "-ar", "48000", str(dst)]
    subprocess.run(cmd, check=True)


# --------------------------------------------------------------------------- health / voices


@app.get("/health")
def health():
    return {"ok": True, "data_dir": str(DATA_DIR)}


@app.get("/voices")
def voices():
    with _tts_lock:
        items = get_tts().list_preset_voices()
    return [{"label": label, "id": vid} for label, vid in items]


# --------------------------------------------------------------------------- audio info


class AudioInfoRequest(BaseModel):
    path: str


@app.post("/audio/info")
def audio_info(req: AudioInfoRequest):
    """Kiểm tra file audio đọc được và lấy thời lượng (qua ffprobe, hỗ trợ mọi định dạng)."""
    src = safe_path(req.path)
    if not src.exists():
        raise HTTPException(404, "Không tìm thấy file")
    try:
        out = subprocess.run(
            ["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", str(src)],
            check=True,
            capture_output=True,
            text=True,
        ).stdout.strip()
        return {"duration": round(float(out), 2)}
    except (subprocess.CalledProcessError, ValueError) as e:
        raise HTTPException(422, "File không phải audio hợp lệ") from e


# --------------------------------------------------------------------------- chọn đoạn mẫu


class BestSegmentRequest(BaseModel):
    path: str
    seconds: float = 8.0  # VieNeu chỉ dùng tối đa ~8 giây làm mẫu


@app.post("/audio/best-segment")
def best_segment(req: BestSegmentRequest):
    """Tìm đoạn ~8 giây "giọng nói đều, liền mạch" nhất trong file dài để làm mẫu clone.

    VieNeu chỉ lấy 8 giây đầu của file mẫu; nếu file dài, phần đầu thường là mở đầu/nhạc/ngắt nghỉ
    nên ngữ điệu bị lệch. Ở đây chấm điểm các cửa sổ bắt đầu ngay tại đầu một câu/cụm nói.
    """
    import numpy as np

    src = safe_path(req.path)
    if not src.exists():
        raise HTTPException(404, "Không tìm thấy file")

    tmp = Path(tempfile.mkdtemp(prefix="seg_"))
    try:
        wav16 = tmp / "a.wav"
        subprocess.run(
            ["ffmpeg", "-y", "-loglevel", "error", "-i", str(src), "-ac", "1", "-ar", "16000", str(wav16)],
            check=True,
        )
        y, sr = sf.read(str(wav16), dtype="float32")
    except subprocess.CalledProcessError as e:
        raise HTTPException(422, "Không đọc được file audio") from e
    finally:
        shutil.rmtree(tmp, ignore_errors=True)

    total = len(y) / sr
    win = min(req.seconds, total)
    if total <= req.seconds + 0.5:
        return {"start": 0.0, "length": round(total, 2), "total": round(total, 2)}

    hop = int(0.02 * sr)
    frame = int(0.04 * sr)
    n = max(1, (len(y) - frame) // hop)
    idx = np.arange(frame)[None, :] + hop * np.arange(n)[:, None]
    rms = np.sqrt((y[idx] ** 2).mean(axis=1) + 1e-12)
    db = 20 * np.log10(rms + 1e-8)

    noise, peak = np.percentile(db, 10), np.percentile(db, 95)
    thr = noise + 0.30 * (peak - noise)
    speech = db > thr
    # lấp các khe hở < 0.12s trong lời nói (giữa các âm tiết)
    gap = int(0.12 / 0.02)
    sp = speech.copy()
    i = 0
    while i < n:
        if not sp[i]:
            j = i
            while j < n and not sp[j]:
                j += 1
            if 0 < i and j < n and (j - i) <= gap:
                sp[i:j] = True
            i = j
        else:
            i += 1

    fps = 1 / 0.02
    wf = int(win * fps)
    best = (-1e9, 0, wf)
    # ứng viên: ngay tại chỗ bắt đầu một cụm nói sau quãng nghỉ >= 0.25s (hoặc đầu file)
    quiet = int(0.25 * fps)
    onsets = [0] + [k for k in range(quiet, n - 1) if sp[k] and not sp[k - quiet : k].any()]
    for k in onsets:
        if k + wf > n:
            break
        seg = sp[k : k + wf]
        ratio = seg.mean()
        # quãng nghỉ dài nhất trong cửa sổ
        longest = cur = 0
        for v in seg:
            cur = 0 if v else cur + 1
            longest = max(longest, cur)
        long_pause = longest / fps
        lvl = db[k : k + wf][seg]
        spread = float(lvl.std()) if lvl.size else 99.0  # giọng đều thì độ lệch thấp
        loud_ok = 1.0 if lvl.size and np.percentile(lvl, 90) < -1.5 else 0.0  # tránh méo/clip
        score = ratio - 0.35 * max(0.0, long_pause - 0.5) - 0.02 * spread + 0.05 * loud_ok
        if score > best[0]:
            best = (score, k, wf)

    _, k, wf = best
    start = k / fps
    length = win
    # kết thúc ở quãng nghỉ gần nhất (trong 1.5s cuối cửa sổ) để không cụt giữa chữ
    end_f = k + wf
    for e in range(end_f, max(k + int(5 * fps), end_f - int(1.5 * fps)), -1):
        if e + 5 < n and not sp[e : e + 5].any():
            length = (e - k) / fps
            break
    return {"start": round(start, 2), "length": round(length, 2), "total": round(total, 2)}


class DatasetPrepareRequest(BaseModel):
    path: str  # file audio nguồn (tương đối so với DATA_DIR)
    out_dir: str  # thư mục ghi các clip wav (tương đối so với DATA_DIR)
    language: str = "vi"
    min_sec: float = 3.0
    max_sec: float = 15.0
    target_sec: float = 11.0  # gộp các cụm nói liền kề tới khoảng độ dài này
    whisper_model: str | None = None  # mặc định WHISPER_DATASET_MODEL (medium): chính xác hơn "small"


@app.post("/dataset/prepare")
def dataset_prepare(req: DatasetPrepareRequest):
    """Cắt file dài thành các clip 3–15s theo khoảng lặng và gỡ băng từng clip bằng Whisper.

    Dùng để chuẩn bị bộ dữ liệu fine-tune LoRA của VieNeu (mỗi clip kèm text tương ứng).
    """
    import numpy as np

    src = safe_path(req.path)
    out_dir = safe_path(req.out_dir)
    if not src.exists():
        raise HTTPException(404, "Không tìm thấy file")
    out_dir.mkdir(parents=True, exist_ok=True)

    tmp = Path(tempfile.mkdtemp(prefix="ds_"))
    try:
        wav16 = tmp / "a.wav"
        try:
            subprocess.run(
                ["ffmpeg", "-y", "-loglevel", "error", "-i", str(src), "-ac", "1", "-ar", "16000", str(wav16)],
                check=True,
            )
        except subprocess.CalledProcessError as e:
            raise HTTPException(422, "Không đọc được file audio") from e
        y, sr = sf.read(str(wav16), dtype="float32")

        fps = 50  # khung 20ms
        hop, frame = int(0.02 * sr), int(0.04 * sr)
        n = max(1, (len(y) - frame) // hop)
        idx = np.arange(frame)[None, :] + hop * np.arange(n)[:, None]
        db = 20 * np.log10(np.sqrt((y[idx] ** 2).mean(axis=1) + 1e-12) + 1e-8)
        noise, peak = np.percentile(db, 10), np.percentile(db, 95)
        speech = db > noise + 0.30 * (peak - noise)

        # 1) cụm nói: tách tại khoảng lặng >= 0.3s
        min_sil = int(0.3 * fps)
        segs, start, sil, last = [], None, 0, 0
        for i, v in enumerate(speech):
            if v:
                if start is None:
                    start = i
                sil, last = 0, i
            elif start is not None:
                sil += 1
                if sil >= min_sil:
                    segs.append((start, last))
                    start = None
        if start is not None:
            segs.append((start, last))

        # 2) cụm quá dài (không có khoảng lặng) -> cắt tại chỗ yên tĩnh nhất
        max_f = int(req.max_sec * fps)
        split = []
        for s, e in segs:
            while e - s > max_f:
                lo, hi = s + int(0.6 * max_f), s + max_f
                cut = lo + int(np.argmin(db[lo:hi]))
                split.append((s, cut))
                s = cut + 1
            split.append((s, e))

        # 3) gộp các cụm liền kề thành clip dài ~target_sec (không vượt max_sec)
        target_f = int(req.target_sec * fps)
        clips, cs, ce = [], None, None
        for s, e in split:
            if cs is None:
                cs, ce = s, e
            elif (e - cs) <= target_f:
                ce = e
            else:
                clips.append((cs, ce))
                cs, ce = s, e
        if cs is not None:
            clips.append((cs, ce))

        pad = int(0.15 * fps)  # chừa chút khoảng lặng hai đầu cho tự nhiên
        total = len(y) / sr
        result = []
        whisper_model = get_whisper(req.whisper_model or DATASET_WHISPER_MODEL)
        for s, e in clips:
            t0 = max(0.0, (s - pad) / fps)
            t1 = min(total, (e + pad) / fps)
            dur = t1 - t0
            if dur < req.min_sec or dur > req.max_sec:
                continue
            name = f"{len(result) + 1:03d}.wav"
            subprocess.run(
                ["ffmpeg", "-y", "-loglevel", "error", "-ss", f"{t0:.3f}", "-t", f"{dur:.3f}", "-i", str(src),
                 "-ac", "1", "-ar", "48000", str(out_dir / name)],
                check=True,
            )
            clip_audio = y[int(t0 * sr) : int(t1 * sr)]
            with _whisper_lock:
                segments, _ = whisper_model.transcribe(
                    clip_audio, language=req.language, beam_size=5, condition_on_previous_text=False
                )
                text = " ".join(s_.text.strip() for s_ in segments).strip()
            result.append({"file": f"{req.out_dir.rstrip('/')}/{name}", "start": round(t0, 2),
                           "duration": round(dur, 2), "text": text})
    finally:
        shutil.rmtree(tmp, ignore_errors=True)

    return {"clips": result, "source_seconds": round(total, 1)}


class ClipRequest(BaseModel):
    path: str
    out_path: str
    start: float = 0.0
    length: float = 8.0


@app.post("/audio/clip")
def audio_clip(req: ClipRequest):
    """Cắt đoạn [start, start+length] ra file wav (để nghe thử đoạn dùng làm mẫu)."""
    src, out = safe_path(req.path), safe_path(req.out_path)
    if not src.exists():
        raise HTTPException(404, "Không tìm thấy file")
    out.parent.mkdir(parents=True, exist_ok=True)
    try:
        to_wav(src, out, req.start, req.length)
    except subprocess.CalledProcessError as e:
        raise HTTPException(422, "Không cắt được đoạn audio") from e
    return {"duration": round(sf.info(str(out)).duration, 2)}


# --------------------------------------------------------------------------- synthesize


class SynthesizeRequest(BaseModel):
    text: str
    out_path: str  # tương đối so với DATA_DIR
    voice: str | None = None  # tên giọng có sẵn (preset)
    ref_audio: str | None = None  # file mẫu (clone giọng), tương đối so với DATA_DIR
    ref_start: float | None = None  # chỉ dùng đoạn [ref_start, ref_start+ref_seconds] của file mẫu
    ref_seconds: float | None = None
    denoise: bool = True
    # Tinh chỉnh ngữ điệu (None = dùng mặc định của VieNeu)
    temperature: float | None = None  # thấp = đều/ổn định, cao = biểu cảm/ngẫu nhiên hơn
    top_p: float | None = None
    top_k: int | None = None
    repetition_penalty: float | None = None
    silence_p: float | None = None  # mức nghỉ giữa các đoạn
    max_chars: int | None = None  # độ dài tối đa mỗi đoạn khi chia câu


@app.post("/synthesize")
def synthesize(req: SynthesizeRequest):
    text = req.text.strip()
    if not text:
        raise HTTPException(422, "Text rỗng")

    out = safe_path(req.out_path)
    out.parent.mkdir(parents=True, exist_ok=True)

    opts = {
        k: v
        for k, v in {
            "temperature": req.temperature,
            "top_p": req.top_p,
            "top_k": req.top_k,
            "repetition_penalty": req.repetition_penalty,
            "silence_p": req.silence_p,
            "max_chars": req.max_chars,
        }.items()
        if v is not None
    }

    tmp_dir = Path(tempfile.mkdtemp(prefix="synth_"))
    try:
        with _tts_lock:
            tts = get_tts()
            if req.ref_audio:
                ref_src = safe_path(req.ref_audio)
                if not ref_src.exists():
                    raise HTTPException(404, "Không tìm thấy file audio mẫu")
                ref_wav = tmp_dir / "ref.wav"
                to_wav(ref_src, ref_wav, req.ref_start, req.ref_seconds)
                audio = tts.infer(text, ref_audio=str(ref_wav), denoise=req.denoise, **opts)
            elif req.voice:
                audio = tts.infer(text, voice=req.voice, **opts)
            else:
                audio = tts.infer(text, **opts)
            tts.save(audio, str(out))
    except HTTPException:
        raise
    except Exception as e:  # noqa: BLE001
        raise HTTPException(500, f"Lỗi tạo audio: {e}") from e
    finally:
        shutil.rmtree(tmp_dir, ignore_errors=True)

    info = sf.info(str(out))
    return {"out_path": req.out_path, "duration": round(info.duration, 2), "sample_rate": info.samplerate}


# --------------------------------------------------------------------------- youtube


class YoutubeRequest(BaseModel):
    url: str
    method: str = "auto"  # auto = phụ đề trước, không có thì Whisper | whisper = luôn dùng Whisper
    language: str = "vi"


def _ydl_base() -> dict:
    return {"quiet": True, "no_warnings": True, "noplaylist": True}


@app.post("/youtube/info")
def youtube_info(req: YoutubeRequest):
    import yt_dlp

    try:
        with yt_dlp.YoutubeDL({**_ydl_base(), "skip_download": True}) as ydl:
            info = ydl.extract_info(req.url, download=False)
    except Exception as e:  # noqa: BLE001
        raise HTTPException(400, f"Không đọc được video: {e}") from e
    return {
        "video_id": info.get("id"),
        "title": info.get("title"),
        "channel": info.get("channel") or info.get("uploader"),
        "duration": info.get("duration"),
        "thumbnail": info.get("thumbnail"),
    }


class YoutubeAudioRequest(BaseModel):
    url: str
    out_path: str  # tương đối so với DATA_DIR, nên là .wav
    start: float | None = None  # giây
    end: float | None = None  # giây


MAX_FULL_VIDEO_SECONDS = 900  # không chọn đoạn thì chỉ nhận video <= 15 phút


@app.post("/youtube/audio")
def youtube_audio(req: YoutubeAudioRequest):
    """Tải audio từ YouTube, cắt đoạn [start, end] (nếu có), lưu wav mono 48kHz làm audio mẫu."""
    import yt_dlp

    if req.start is not None and req.start < 0:
        raise HTTPException(422, "Thời điểm bắt đầu không hợp lệ")
    if req.start is not None and req.end is not None and req.end <= req.start:
        raise HTTPException(422, "Thời điểm kết thúc phải lớn hơn thời điểm bắt đầu")

    out = safe_path(req.out_path)
    out.parent.mkdir(parents=True, exist_ok=True)
    workdir = Path(tempfile.mkdtemp(prefix="ytaudio_"))
    try:
        try:
            with yt_dlp.YoutubeDL(
                {**_ydl_base(), "format": "bestaudio/best", "outtmpl": str(workdir / "audio.%(ext)s")}
            ) as ydl:
                info = ydl.extract_info(req.url, download=False)
                total = info.get("duration") or 0
                if req.start is None and req.end is None and total > MAX_FULL_VIDEO_SECONDS:
                    raise HTTPException(
                        422,
                        f"Video dài {int(total // 60)} phút. Hãy nhập đoạn cần lấy (từ giây – đến giây), "
                        "mẫu giọng chỉ cần 3–8 giây.",
                    )
                if req.start is not None and total and req.start >= total:
                    raise HTTPException(422, f"Thời điểm bắt đầu vượt quá độ dài video ({int(total)} giây)")
                ydl.process_ie_result(info, download=True)
        except HTTPException:
            raise
        except Exception as e:  # noqa: BLE001
            raise HTTPException(400, f"Không tải được audio từ video: {e}") from e

        src = next(workdir.glob("audio.*"))
        cmd = ["ffmpeg", "-y", "-loglevel", "error"]
        if req.start:
            cmd += ["-ss", str(req.start)]
        cmd += ["-i", str(src)]
        if req.end is not None:
            cmd += ["-t", str(req.end - (req.start or 0))]
        cmd += ["-vn", "-ac", "1", "-ar", "48000", str(out)]
        try:
            subprocess.run(cmd, check=True, capture_output=True, text=True)
        except subprocess.CalledProcessError as e:
            raise HTTPException(500, f"Lỗi cắt audio: {e.stderr.strip()[:300]}") from e
    finally:
        shutil.rmtree(workdir, ignore_errors=True)

    duration = sf.info(str(out)).duration
    if duration < 0.5:
        out.unlink(missing_ok=True)
        raise HTTPException(422, "Đoạn audio quá ngắn hoặc nằm ngoài độ dài video")
    return {"out_path": req.out_path, "duration": round(duration, 2), "title": info.get("title")}


def _subtitle_text(url: str, lang: str, workdir: Path) -> str | None:
    import yt_dlp

    opts = {
        **_ydl_base(),
        "skip_download": True,
        "writesubtitles": True,
        "writeautomaticsub": True,
        "subtitleslangs": [lang, f"{lang}-*"],
        "subtitlesformat": "json3",
        "outtmpl": str(workdir / "sub.%(ext)s"),
    }
    with yt_dlp.YoutubeDL(opts) as ydl:
        ydl.extract_info(url, download=True)

    files = sorted(workdir.glob("sub.*.json3"))
    if not files:
        return None
    data = json.loads(files[0].read_text(encoding="utf-8"))
    parts = []
    for ev in data.get("events", []):
        for seg in ev.get("segs") or []:
            parts.append(seg.get("utf8", ""))
        parts.append(" ")
    text = re.sub(r"\s+", " ", "".join(parts)).strip()
    return text or None


def _whisper_text(url: str, lang: str, workdir: Path) -> str:
    import yt_dlp

    opts = {
        **_ydl_base(),
        "format": "bestaudio/best",
        "outtmpl": str(workdir / "audio.%(ext)s"),
    }
    with yt_dlp.YoutubeDL(opts) as ydl:
        ydl.extract_info(url, download=True)
    src = next(workdir.glob("audio.*"))

    # Tự giải mã bằng ffmpeg (16kHz mono) thay vì để faster-whisper dùng PyAV (hay lệch phiên bản)
    wav = workdir / "audio16k.wav"
    subprocess.run(
        ["ffmpeg", "-y", "-loglevel", "error", "-i", str(src), "-ac", "1", "-ar", "16000", str(wav)],
        check=True,
    )
    samples, _sr = sf.read(str(wav), dtype="float32")

    with _whisper_lock:
        model = get_whisper()
        segments, _ = model.transcribe(samples, language=lang, vad_filter=True)
        lines = [s.text.strip() for s in segments if s.text.strip()]
    return "\n".join(lines)


@app.post("/youtube/transcribe")
def youtube_transcribe(req: YoutubeRequest):
    workdir = Path(tempfile.mkdtemp(prefix="yt_"))
    try:
        text, source = None, None
        if req.method == "auto":
            try:
                text = _subtitle_text(req.url, req.language, workdir)
                source = "subtitle" if text else None
            except Exception:  # noqa: BLE001 - rơi xuống Whisper
                text = None
        if not text:
            text = _whisper_text(req.url, req.language, workdir)
            source = "whisper"
    except HTTPException:
        raise
    except Exception as e:  # noqa: BLE001
        raise HTTPException(500, f"Lỗi chuyển video thành text: {e}") from e
    finally:
        shutil.rmtree(workdir, ignore_errors=True)

    if not text.strip():
        raise HTTPException(422, "Không trích xuất được nội dung từ video")
    return {"text": text, "source": source}
