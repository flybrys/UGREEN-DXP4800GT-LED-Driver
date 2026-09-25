#!/usr/bin/env bash
set -euo pipefail

root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
version=2026.09.25
kernel=6.18.38-Unraid
name=ugreen-dxp4800gt-leds
stage=$root/build/stage
package=$root/artifacts/$name-$version-x86_64-1.txz

[[ $(uname -r) == "$kernel" ]] || { echo "Build this package on $kernel" >&2; exit 1; }
[[ $(cat /sys/class/dmi/id/product_name) == 'DXP4800 GT' ]] || { echo 'DXP4800 GT required' >&2; exit 1; }
command -v makepkg >/dev/null

rm -rf -- "$stage"
mkdir -p "$stage/usr/local/sbin" "$stage/lib/modules/$kernel/extra/$name" "$stage/install"
install -m 755 "$root/src/ugreen-gt-leds" "$stage/usr/local/sbin/ugreen-gt-leds"
for module in i2c-designware-core i2c-designware-platform led-ugreen; do
  install -m 644 "$root/dist/$kernel/$module.ko" "$stage/lib/modules/$kernel/extra/$name/$module.ko"
done
cat > "$stage/install/slack-desc" <<EOF
$name: $name (UGREEN DXP4800 GT front-panel LED support)
$name:
$name: Matching I2C and LED modules for Unraid kernel $kernel.
$name: See https://github.com/flybrys/unraid-ugreen-dxp4800gt-leds
EOF
mkdir -p "$root/artifacts"
(cd "$stage" && makepkg -l y -c n "$package")
(cd "$root/artifacts" && sha256sum "${package##*/}" > "${package##*/}.sha256")
(cd "$root/artifacts" && md5sum "${package##*/}" > "${package##*/}.md5")
printf 'Created %s\n' "$package"
