#!/usr/bin/env bash
set -euo pipefail

# Build only in this repository. Nothing in this script changes the NAS.
if [[ ${OSTYPE:-} != linux* ]]; then
  echo 'A Linux build environment is required (WSL or a Linux container/VM).' >&2
  exit 1
fi
root_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
kernel_release=6.18.38-Unraid
kernel_sha256=b336c66bf1d7ee2cedba88e8be2124b2256c94476b1d1c944518ce8d6bcf37da
led_commit=c830a2293cf5c67c58e5a98ca339b089b2b13fc3
archive="$root_dir/sources/linux-$kernel_release.tar.xz"
kernel_dir="$root_dir/work/linux-$kernel_release"
led_dir="$root_dir/sources/ugreen_leds_controller"
dist_dir="$root_dir/dist/$kernel_release"

for tool in sha256sum git tar make gcc g++ modinfo; do
  command -v "$tool" >/dev/null || { echo "Missing build tool: $tool" >&2; exit 1; }
done
[[ $(uname -m) == x86_64 ]] || {
  echo 'This build requires x86_64 Linux.' >&2; exit 1;
}
[[ -f "$archive" ]] || { echo "Missing $archive" >&2; exit 1; }
[[ -d "$led_dir/.git" ]] || { echo "Missing pinned LED source" >&2; exit 1; }
printf '%s  %s\n' "$kernel_sha256" "$archive" | sha256sum --check --status || {
  echo 'Kernel archive checksum mismatch' >&2; exit 1;
}
[[ $(git -C "$led_dir" rev-parse HEAD) == "$led_commit" ]] || {
  echo 'LED source commit mismatch' >&2; exit 1;
}
if ! git -C "$led_dir" diff --quiet -- kmod cli || \
   ! git -C "$led_dir" diff --cached --quiet -- kmod cli; then
  echo 'LED module or CLI source was modified' >&2; exit 1;
fi

if [[ ! -f "$kernel_dir/Makefile" ]]; then
  mkdir -p "$kernel_dir"
  tar -xJf "$archive" -C "$kernel_dir"
fi
[[ $(make -s -C "$kernel_dir" kernelrelease) == "$kernel_release" ]] || {
  echo 'Kernel source release does not match target' >&2; exit 1;
}
[[ -s "$kernel_dir/Module.symvers" ]] || {
  echo 'Kernel source lacks Module.symvers' >&2; exit 1;
}

# The stock config disables these in-tree drivers. Build them as loadable modules.
"$kernel_dir/scripts/config" --file "$kernel_dir/.config" \
  --module CONFIG_I2C_DESIGNWARE_CORE --module CONFIG_I2C_DESIGNWARE_PLATFORM
make -C "$kernel_dir" olddefconfig
grep -qx 'CONFIG_I2C_DESIGNWARE_CORE=m' "$kernel_dir/.config"
grep -qx 'CONFIG_I2C_DESIGNWARE_PLATFORM=m' "$kernel_dir/.config"
make -C "$kernel_dir" modules_prepare
jobs=${JOBS:-$(nproc)}
make -C "$kernel_dir" -j"$jobs" M=drivers/i2c/busses \
  CONFIG_I2C_DESIGNWARE_CORE=m CONFIG_I2C_DESIGNWARE_PLATFORM=m modules
make -C "$kernel_dir" -j"$jobs" M="$led_dir/kmod" modules
make -C "$led_dir/cli" -j"$jobs" ugreen_leds_cli

mkdir -p "$dist_dir"
for module in \
  "$kernel_dir/drivers/i2c/busses/i2c-designware-core.ko" \
  "$kernel_dir/drivers/i2c/busses/i2c-designware-platform.ko" \
  "$led_dir/kmod/led-ugreen.ko"; do
  [[ -s "$module" ]] || { echo "Missing built module: $module" >&2; exit 1; }
  vermagic=$(modinfo -F vermagic "$module")
  [[ $vermagic == "$kernel_release "* ]] || {
    echo "Wrong vermagic for $module: $vermagic" >&2; exit 1;
  }
  cp "$module" "$dist_dir/"
done
cp "$led_dir/cli/ugreen_leds_cli" "$dist_dir/"
(
  cd "$dist_dir"
  sha256sum *.ko ugreen_leds_cli > SHA256SUMS
  printf 'kernel=%s\nled_source=%s\nkernel_archive_sha256=%s\n' \
    "$kernel_release" "$led_commit" "$kernel_sha256" > BUILD_INFO
)
echo "Build complete: $dist_dir"
