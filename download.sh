#!/bin/bash

if [ -z "$1" ]; then
    echo "Error: Missing YouTube video ID."
    echo "Usage: $0 <video_id>"
    exit 1
fi

VIDEO_ID="$1"
URL="https://www.youtube.com/watch?v=${VIDEO_ID}"

mkdir -p downloads

yt-dlp -f "bestvideo[height<=240][ext=mp4]+bestaudio[ext=m4a]/best[height<=240][ext=mp4]" \
       --merge-output-format mp4 \
       -o "temp/%(id)s.%(ext)s" \
       "$URL"

