#!/usr/bin/env bash
set -euo pipefail

root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
[[ $(uname -m) == x86_64 ]] || { echo 'x86_64 build host required' >&2; exit 1; }
command -v gcc >/dev/null || { echo 'GCC is required' >&2; exit 1; }
mkdir -p "$root/dist/6.18.38-Unraid"
gcc -std=gnu11 -O2 -Wall -Wextra -Werror -static \
  "$root/src/ugreen-gt-disk-activity.c" \
  -o "$root/dist/6.18.38-Unraid/ugreen-gt-disk-activity"
sha256sum "$root/dist/6.18.38-Unraid/ugreen-gt-disk-activity"
