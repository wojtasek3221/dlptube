#!/bin/bash

# Check if input file and output file are provided
if [ $# -lt 3 ]; then
    echo "Usage: $0 [-l | -m | -h | -s] <input_file> <output_file>"
    exit 1
fi

# Default settings
vbr="64k"
abr="24k"
audio_only=false

# Parse command line flags
while getopts "lmhsu" opt; do
    case $opt in
        l)
            vbr="32k"
            abr="12k"
            ;;
        m)
            vbr="64k"
            abr="24k"
            ;;
        h)
            vbr="128k"
            abr="48k"
            ;;

        u)
        vbr="2000k"
        abr="128k"
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
    # -progress pipe:1 prints machine-readable key=value progress lines to stdout;
    # everything else (codec info, warnings) still goes to the log file via stderr.
    ffmpeg -y -i "$INPUT_FILE" \
        -vn \
        -acodec libmp3lame -b:a 128k \
        -progress pipe:1 -nostats \
        "$OUTPUT_FILE" 2>> "$log_file"

else
    echo "Converting: '$INPUT_FILE' -> '$OUTPUT_FILE'"
    echo "Bitrates -> Video: $vbr | Audio: $abr"
    echo "Logging output to: $log_file"

    # Execute FFmpeg for 3GP conversion
ffmpeg -y -i "$INPUT_FILE" \
    -vcodec mpeg4 -vf "scale=320:240:force_original_aspect_ratio=decrease,pad=320:240:(ow-iw)/2:(oh-ih)/2" \
    -r 15 -b:v "$vbr" \
    -acodec libopencore_amrnb -ar 8000 -ac 1 -b:a 12.2k \
    -progress pipe:1 -nostats \
    "$OUTPUT_FILE" 2>> "$log_file"
fi

if [ $? -eq 0 ]; then
    echo "Success: $OUTPUT_FILE"
    exit 0
else
    echo "Failed. Check '$log_file' for details."
    exit 1
fi
