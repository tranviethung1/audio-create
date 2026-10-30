# LanhVCL

Laravel + [VieNeu-TTS](https://github.com/pnnbao97/VieNeu-TTS) (tiếng Việt, 48 kHz, chạy CPU).

| Trang | Đường dẫn | Chức năng |
|---|---|---|
| Tạo audio | `/generations` | Nhập text, chọn giọng, tạo audio (hàng đợi), nghe lại / tải về / xoá. Có tuỳ chọn tách theo đoạn. |
| Giọng đọc | `/voices` | Thêm giọng mới + upload audio mẫu, chọn mẫu chính, xoá. Xem danh sách giọng có sẵn của VieNeu. |
| YouTube → Text | `/youtube` | Dán link YouTube → text (ưu tiên phụ đề, không có thì Whisper). Lưu lịch sử theo tên video, sửa text, gửi sang trang tạo audio. |

## Kiến trúc

- **Laravel** (host): giao diện, DB, queue (`database`).
- **PostgreSQL** (Docker, cổng `54329`).
- **`tts-service/`** (Docker, cổng `8100`): FastAPI bọc VieNeu, yt-dlp, faster-whisper, ffmpeg.
  Chia sẻ thư mục `storage/app/private` với Laravel tại `/data`.

## Chạy

```bash
cp .env.example .env && php artisan key:generate   # hoặc thêm các biến cuối .env.example vào .env hiện có
npm install && npm run build
docker compose build                                # lần đầu
./start.sh                                          # http://127.0.0.1:8088
```

Lần đầu tạo audio, VieNeu tải model (~vài trăm MB); lần đầu dùng Whisper tải model `small`.
Model được cache ở `tts-service/.cache`.

## Lưu ý về “training”

VieNeu v3 **clone giọng theo mẫu (zero-shot)**: không fine-tune. File audio upload là mẫu tham chiếu;
mẫu chính (3–8 giây, sạch, 1 người nói) được dùng khi tạo audio. Fine-tune thật cần GPU và quy trình riêng.
