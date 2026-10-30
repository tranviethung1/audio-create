FROM python:3.12-slim

ENV PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    HF_HOME=/cache/hf \
    HF_HUB_DISABLE_SYMLINKS=1 \
    HF_HUB_DISABLE_SYMLINKS_WARNING=1 \
    HOME=/cache/home

RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg git curl ca-certificates libsndfile1 espeak-ng \
    && rm -rf /var/lib/apt/lists/*

# Deno là JS runtime yt-dlp dùng để giải mã chữ ký YouTube
RUN curl -fsSL https://github.com/denoland/deno/releases/latest/download/deno-x86_64-unknown-linux-gnu.zip -o /tmp/deno.zip \
    && python -c "import zipfile;zipfile.ZipFile('/tmp/deno.zip').extractall('/usr/local/bin')" \
    && chmod +x /usr/local/bin/deno && rm /tmp/deno.zip

WORKDIR /app
COPY requirements.txt .
RUN pip install -r requirements.txt

COPY app ./app

EXPOSE 8100
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8100"]
