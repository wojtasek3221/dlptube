#!/bin/bash
# Check if input file and output file are provided
if [ $# -lt 3 ]; then
    echo "Usage: $0 [-l | -m | -h | -s] <input_file> <output_file>"
    exit 1
fi
# Default settings
vbr="64k"
abr="24k"
fps="15"
audio_only=false
# Parse command line flags
while getopts "lmhs" opt; do
    case $opt in
        l)
            vbr="32k"
            abr="12k"
            fps="15"
            ;;
        m)
            vbr="64k"
            abr="24k"
            fps="15"
            ;;
        h)
            vbr="128k"
            abr="48k"
            fps="15"
            ;;
        s)
            audio_only=true
            ;;
        *)
            echo "Usage: $0 [-l | -m | -h | -s] <input_file> <output_file>"
            exit 1
            ;;
    esac
done
# Shift to get input and output files
shift $((OPTIND - 1))
INPUT_FILE="$1"
OUTPUT_FILE="$2"
# Check if input file exists
if [ ! -f "$INPUT_FILE" ]; then
    echo "Error: Input file '$INPUT_FILE' not found."
    exit 1
fi
# Check if ffmpeg is installed
if ! command -v ffmpeg &> /dev/null; then
    echo "Error: ffmpeg could not be found."
    exit 1
fi
# Create output directory if it doesn't exist
OUTPUT_DIR="$(dirname "$OUTPUT_FILE")"
mkdir -p "$OUTPUT_DIR"
# Log file
log_file="${OUTPUT_FILE%.*}_ffmpeg.log"
if [ "$audio_only" = true ]; then
    echo "Converting: '$INPUT_FILE' -> '$OUTPUT_FILE'"
    echo "Settings -> Audio Only (MP3): 128kbps"
    echo "Logging output to: $log_file"
    # Execute FFmpeg for MP3 extraction
    ffmpeg -y -i "$INPUT_FILE" \
        -vn \
        -acodec libmp3lame -b:a 128k \
        "$OUTPUT_FILE" >> "$log_file" 2>&1
else
    echo "Converting: '$INPUT_FILE' -> '$OUTPUT_FILE'"
    echo "Bitrates -> Video: $vbr | Audio: $abr | FPS: $fps"
    echo "Logging output to: $log_file"
    # Execute FFmpeg for 3GP conversion
    ffmpeg -y -i "$INPUT_FILE" \
        -vcodec h263 -vf "scale=128:96:force_original_aspect_ratio=decrease,pad=128:96:(ow-iw)/2:(oh-ih)/2" \
        -r "$fps" -b:v "$vbr" \
        -acodec aac -ar 16000 -ac 1 -b:a "$abr" \
        "$OUTPUT_FILE" >> "$log_file" 2>&1
fi
if [ $? -eq 0 ]; then
    echo "Success: $OUTPUT_FILE"
    exit 0
else
    echo "Failed. Check '$log_file' for details."
    exit 1
fi
