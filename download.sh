#!/bin/bash

if [ -z "$1" ]; then
    echo "Error: Missing YouTube video ID."
    echo "Usage: $0 <video_id> [output_prefix]"
    exit 1
fi

VIDEO_ID="$1"
# output_prefix is an absolute path (without extension) supplied by the caller,
# e.g. /path/to/temp/<videoId>_<taskId>. Fall back to ./temp/<videoId> if omitted
# so the script still works when run manually.
OUT_PREFIX="${2:-temp/${VIDEO_ID}}"
URL="https://www.youtube.com/watch?v=${VIDEO_ID}"

# Make sure the directory for the output prefix exists
OUT_DIR="$(dirname "$OUT_PREFIX")"
mkdir -p "$OUT_DIR"

yt-dlp -f "bestvideo[height<=240][ext=mp4]+bestaudio[ext=m4a]/best[height<=240][ext=mp4]" \
       --merge-output-format mp4 \
       --newline \
       -o "${OUT_PREFIX}.%(ext)s" \
       "$URL"
