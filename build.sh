#!/usr/bin/env bash
# ساخت بسته‌های انتشار ترانه‌یار
set -euo pipefail
cd "$(dirname "$0")"

rm -rf dist
mkdir -p dist

# بسته افزونه (همراه با hostdl داخلی برای راحتی نصب)
zip -r -q dist/taranehyar.zip taranehyar -x "*.DS_Store" -x "**/.git/**"

# بسته جداگانه هاست دانلود
( cd taranehyar && zip -r -q ../dist/taranehyar-hostdl.zip hostdl -x "*.DS_Store" )

echo "ساخته شد:"
ls -lh dist/
