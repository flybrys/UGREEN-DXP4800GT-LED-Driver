# Maintainer notes

## Scope and versions

The `.plg` and release package target DXP4800 GT on Unraid 7.3.2, kernel `6.18.38-Unraid`. A new kernel release needs modules built from that exact Unraid kernel tree, a new package, checksums, and a revised compatibility check in the `.plg`. Do not reuse the current modules on another kernel.

## Build inputs

[`build/build-modules.sh`](build/build-modules.sh) expects:

- `sources/linux-6.18.38-Unraid.tar.xz` from the [Unraid kernel release](https://github.com/ich777/unraid_kernel/releases/tag/6.18.38-Unraid), SHA-256 `b336c66bf1d7ee2cedba88e8be2124b2256c94476b1d1c944518ce8d6bcf37da`.
- `sources/ugreen_leds_controller` cloned from [miskcoo](https://github.com/miskcoo/ugreen_leds_controller) at commit `c830a2293cf5c67c58e5a98ca339b089b2b13fc3` (`v0.4-beta`).

Run the script on x86_64 Linux with GCC 14.2.0, Make, Git, kmod, bc, flex, bison, libssl development headers, libelf development headers, and the matching kernel source. It verifies the source inputs, applies [`patches/led-ugreen-hardening.patch`](patches/led-ugreen-hardening.patch), and writes modules and `BUILD_INFO` under `dist/6.18.38-Unraid/`. The script only changes its local build tree. Use a clean checkout of the pinned LED source for each build.

[`build/build-activity.sh`](build/build-activity.sh) builds the small static x86_64 helper that translates per-device `block_rq_issue` events from an isolated tracefs instance into LED pulses. It needs GCC and libc development headers. [`build/package.sh`](build/package.sh) runs on the target Unraid kernel with `makepkg` available. It packages the monitor, activity helper, and three matching modules into `artifacts/`, then writes SHA-256 and MD5 files. Update the version in the package script and `.plg` together. Recalculate both SHA-256 values for the package and inline settings in the `.plg`, upload the package to a matching GitHub release, and verify installation and removal on the supported machine.

The stock Unraid kernel configuration does not supply the DesignWare I²C controller modules needed by the GT. The LED MCU identifies as `0xc5b2` at address `0x3a` on a DesignWare bus and uses the LED driver's `smbus-block` write protocol. The monitor probes only matching buses and checks the model before binding the driver.

The i2c-tools 4.3 package is pinned separately in the `.plg`; its upstream source and licensing are documented in [THIRD_PARTY.md](THIRD_PARTY.md). Keep third-party source and license references current when changing package inputs.

## Community Apps

The installer is [`UGREEN-DXP4800GT-LED-Driver.plg`](UGREEN-DXP4800GT-LED-Driver.plg). The Community Apps entry is [`plugins/UGREEN-DXP4800GT-LED-Driver.xml`](plugins/UGREEN-DXP4800GT-LED-Driver.xml), and repository metadata is [`ca_profile.xml`](ca_profile.xml). The `<Name>` in the Community Apps entry controls the readable listing title. Check XML parsing and all public URLs before running Community Apps Validate and Scan.
