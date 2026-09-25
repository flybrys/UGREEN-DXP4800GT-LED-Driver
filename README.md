# UGREEN DXP4800 GT front-panel LEDs for Unraid

This plugin enables the front-panel LED MCU on the **DXP4800 GT** running **Unraid 7.3.2, kernel 6.18.38-Unraid**. The module binaries are tied to that exact kernel; other Unraid kernels need a rebuild and a new release.

## LED behavior

| LED | Behavior |
| --- | --- |
| Power | Steady white while the service runs. |
| Network | Red if the link is down; green at 100 Mb/s, blue at 1 Gb/s, yellow at 2.5 Gb/s, white at 10 Gb/s, amber at other speeds. The Linux `netdev` trigger shows link, receive, and transmit activity. |
| Disk 1–4 | White for populated SATA bays. The Linux `oneshot` trigger flashes on reads or writes. Empty bays are off; a drive that disappears while the service runs turns red. |

The network interface is selected from the default route (`br0` on a typical Unraid bridge setup). Bays map to ATA ports 1–4 rather than disk serial numbers. Optional settings are in [`src/settings.example.cfg`](src/settings.example.cfg).

## Install

1. Remove the older `ugreenleds-driver` plugin in Unraid's Plugins page. It provides an incompatible module and service. Reboot if its removal asks for one.
2. In Plugins → Install Plugin, enter:

   `https://raw.githubusercontent.com/flybrys/unraid-ugreen-dxp4800gt-leds/main/ugreen-dxp4800gt-leds.plg`

The plugin checks the DMI model and exact kernel before installing. It downloads two SHA-256-pinned release packages: the LED driver/service and i2c-tools 4.3. It installs the matching modules, starts the service, and starts again at each Unraid boot. To customize colors or ports, copy `/boot/config/plugins/ugreen-dxp4800gt-leds/settings.example.cfg` to `settings.cfg` and restart with `ugreen-gt-leds stop; ugreen-gt-leds start`.

Check `ugreen-gt-leds status` and `/var/log/ugreen-gt-leds.log` on the NAS. Remove the plugin through Unraid's Plugins page. The removal stops the service, unbinds the LED MCU, unloads its modules, and removes its package. It leaves the shared i2c-tools package installed in the current session.

## Why these modules

The stock Unraid kernel configuration omits the Synopsys DesignWare I²C controller modules needed by this model. The GT's LED MCU identifies as `0xc5b2` at I²C address `0x3a` on a DesignWare bus and requires the upstream LED driver's `smbus-block` write protocol. The service loads the two DesignWare modules, probes only DesignWare buses for that chip ID, and binds `led-ugreen` with `write_protocol=smbus-block`.

The driver and service were tested on a DXP4800 GT: the MCU chip ID was read, sysfs LED entries appeared, the power LED changed color, the network trigger selected a 1 Gb/s link, and a controlled SSD read produced a bay 4 activity event. The physical front panel was not observed during that activity test.

## Rebuild and provenance

[`build/build-modules.sh`](build/build-modules.sh) builds the modules on x86_64 Linux with GCC 14.2.0, Make, Git, kmod, and the exact Unraid kernel source tree. Put these inputs under `sources/` before running it:

- `linux-6.18.38-Unraid.tar.xz` from [ich777's Unraid kernel release](https://github.com/ich777/unraid_kernel/releases/tag/6.18.38-Unraid), SHA-256 `b336c66bf1d7ee2cedba88e8be2124b2256c94476b1d1c944518ce8d6bcf37da`.
- `ugreen_leds_controller` cloned from [miskcoo's repository](https://github.com/miskcoo/ugreen_leds_controller) at commit `c830a2293cf5c67c58e5a98ca339b089b2b13fc3` (`v0.4-beta`).

The script checks both pinned inputs and writes the binaries under `dist/6.18.38-Unraid/`. [`build/package.sh`](build/package.sh) creates the Unraid package from those binaries on the target Unraid kernel. The [i2c-tools project](https://git.kernel.org/pub/scm/utils/i2c-tools/i2c-tools.git/) supplies the separately packaged i2c-tools 4.3 utility. The release asset is the package already distributed by the previous Unraid UGREEN plugin, with SHA-256 pinned in the PLG.

This service and its packaging scripts are MIT licensed. The upstream LED controller is MIT licensed; the Linux I²C modules and i2c-tools follow their upstream licenses. The published binaries contain no configuration, disk data, serial numbers, credentials, or machine identifiers.
