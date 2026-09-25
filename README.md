# UGREEN DXP4800 GT front-panel LEDs for Unraid

This plugin enables the front-panel LED MCU on the **DXP4800 GT** running **Unraid 7.3.2, kernel 6.18.38-Unraid**. The module binaries are tied to that exact kernel; other Unraid kernels need a rebuild and a new release.

## LED behavior

| LED | Behavior |
| --- | --- |
| Power | Steady white while running; white blinking during orderly Unraid shutdown. |
| Network | White when an HTTPS check to `unraid.net` succeeds; orange when it fails or the link is down. The Linux `netdev` trigger flashes on receive and transmit activity when the link is up. |
| Disk 1–4 | White activity flash on reads or writes; white breathing while a drive reports standby; orange slow flash when SMART explicitly reports failure. Empty bays are off. |

The [UGREEN LED guide](https://ai.ugreen.com/blogs/knowledge/ugreen-nas-led-indicators-meaning) defines these colors and patterns. The network interface is selected from the default route (`br0` on a typical Unraid bridge setup). The service makes one HTTPS HEAD request to `unraid.net` every 60 seconds; the destination and method are configurable. This check proves that site is reachable, not that every internet service works. Bays map to ATA ports 1–4 rather than disk serial numbers. Optional settings are in [`src/settings.example.cfg`](src/settings.example.cfg).

Unraid does not expose a reliable general system-error or system-sleep state to this service, so it leaves the power LED white in those cases. SMART may not flag every disk problem; orange is shown only for an explicit SMART health failure. A removed drive is shown as an empty bay, not as a failed drive. The shutdown blink uses Unraid's `/boot/config/stop` hook. The plugin adds one marked line to that script and removes its line on uninstall.

## Install

1. Remove the older `ugreenleds-driver` plugin in Unraid's Plugins page. It provides an incompatible module and service. Reboot if its removal asks for one.
2. In Plugins → Install Plugin, enter:

   `https://raw.githubusercontent.com/flybrys/unraid-ugreen-dxp4800gt-leds/main/ugreen-dxp4800gt-leds.plg`

The plugin checks the DMI model and exact kernel before installing. It downloads two SHA-256-pinned release packages: the LED driver/service and i2c-tools 4.3. It installs the matching modules, starts the service, and starts again at each Unraid boot. To customize colors, connectivity checking, or ports, copy `/boot/config/plugins/ugreen-dxp4800gt-leds/settings.example.cfg` to `settings.cfg` and restart with `ugreen-gt-leds stop; ugreen-gt-leds start`.

Check `ugreen-gt-leds status` and `/var/log/ugreen-gt-leds.log` on the NAS. Remove the plugin through Unraid's Plugins page. The removal stops the service, unbinds the LED MCU, unloads its modules, and removes its package. It leaves the shared i2c-tools package installed in the current session.

## Why these modules

The stock Unraid kernel configuration omits the Synopsys DesignWare I²C controller modules needed by this model. The GT's LED MCU identifies as `0xc5b2` at I²C address `0x3a` on a DesignWare bus and requires the upstream LED driver's `smbus-block` write protocol. The service loads the two DesignWare modules, probes only DesignWare buses for that chip ID, and binds `led-ugreen` with `write_protocol=smbus-block`.

The driver and service were tested on a DXP4800 GT: the MCU chip ID was read, sysfs LED entries appeared, the power LED changed color, and a controlled drive read produced an activity event. The revised service selected white for a reachable site, orange for an intentionally unreachable site, white breathing for a standby drive, and the activity trigger for an active drive. The physical front panel was not observed during the activity test.

## Rebuild and provenance

[`build/build-modules.sh`](build/build-modules.sh) builds the modules on x86_64 Linux with GCC 14.2.0, Make, Git, kmod, and the exact Unraid kernel source tree. Put these inputs under `sources/` before running it:

- `linux-6.18.38-Unraid.tar.xz` from [ich777's Unraid kernel release](https://github.com/ich777/unraid_kernel/releases/tag/6.18.38-Unraid), SHA-256 `b336c66bf1d7ee2cedba88e8be2124b2256c94476b1d1c944518ce8d6bcf37da`.
- `ugreen_leds_controller` cloned from [miskcoo's repository](https://github.com/miskcoo/ugreen_leds_controller) at commit `c830a2293cf5c67c58e5a98ca339b089b2b13fc3` (`v0.4-beta`).

The script checks both pinned inputs and writes the binaries under `dist/6.18.38-Unraid/`. [`build/package.sh`](build/package.sh) creates the Unraid package from those binaries on the target Unraid kernel. The [i2c-tools project](https://git.kernel.org/pub/scm/utils/i2c-tools/i2c-tools.git/) supplies the separately packaged i2c-tools 4.3 utility. The release asset is the package already distributed by the previous Unraid UGREEN plugin, with SHA-256 pinned in the PLG.

This service and its packaging scripts are MIT licensed. The upstream LED controller is MIT licensed; the Linux I²C modules and i2c-tools follow their upstream licenses. The published binaries contain no configuration, disk data, serial numbers, credentials, or machine identifiers.
