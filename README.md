# UGREEN DXP4800GT LED Driver

Front panel power, LAN, and drive LEDs for the **UGREEN NASync DXP4800 GT** running Unraid. The plugin loads the matching LED and I²C drivers and starts its monitor automatically.

**Supported and tested only on Unraid 7.3.2 with kernel 6.18.38-Unraid and a DXP4800 GT.** The installer checks the exact model and kernel before loading its modules. Unraid 7.3.3, 7.3.4, and 7.3.5 use different kernels and are not supported by the current package. Installing the `.plg` directly does not bypass that check; each kernel needs matching modules and a tested release. See [compatibility issue #3](https://github.com/flybrys/UGREEN-DXP4800GT-LED-Driver/issues/3).

## Install with Community Apps

On a supported Unraid 7.3.2 system, search **Apps** for **UGREEN DXP4800GT LED Driver** and select **Install**. The plugin is [listed in Community Apps](https://ca.unraid.net/apps/ugreen-dxp4800gt-led-driver-1ezhaxq0gvnyj6). If Apps is unavailable on that system, use **Plugins → Install Plugin** with this URL:

`https://raw.githubusercontent.com/flybrys/UGREEN-DXP4800GT-LED-Driver/main/UGREEN-DXP4800GT-LED-Driver.plg`

Updates and removal are managed from Unraid's **Plugins** page. After installation, open **Settings → UGREEN DXP4800 GT LEDs** to change colours and behaviour with a colour picker. The defaults require no configuration.

## What the lights mean

| Light | Behavior |
| --- | --- |
| Power | Solid white while Unraid is running; white blink during an orderly shutdown. |
| LAN | Solid white when the internet check succeeds; solid orange when the link or check fails. Flashes in the current color during sustained data transfer. Background packets leave it solid. |
| Drive bays 1–4 | Solid white while active but idle, with a brief off pulse for each burst of disk reads or writes; white breathing while a drive reports standby; slow orange flash for an explicit SMART health failure. Empty bays are off. |

The LAN colours and activity signal follow [UGREEN's LED guide](https://ai.ugreen.com/blogs/knowledge/ugreen-nas-led-indicators-meaning). The guide describes flashing for disk reads and breathing for sleep; the solid-between-I/O disk style is the default requested for this plugin. The LAN check tries `https://unraid.net/` and then `https://ai.ugreen.com/` every 60 seconds. Success means at least one site is reachable. The flash threshold is 64 KiB of network traffic per 0.5 second sample, so ordinary background traffic does not keep the light blinking.

The drive LEDs map to the four SATA bays. Disk activity comes from the kernel's per-device block request events, with closely spaced requests grouped into a brief pulse. The settings page also offers a dark-while-idle style. If the event monitor cannot start, the plugin falls back to its earlier 0.5-second disk counter polling. A missing drive is shown as an empty bay. Orange requires an explicit SMART failure result; it does not replace Unraid's drive health monitoring. The power LED stays white for system conditions that this plugin cannot reliably identify, such as general system faults or sleep.

Standby breathing uses the LED controller's smooth hardware fade. Bays enter that mode independently, so sleeping LEDs can be slightly out of phase or appear to chase one another. No phase-synchronization change is included in this release.

## Help and settings

If a light does not behave as expected, check `ugreen-gt-leds status` and `/var/log/ugreen-gt-leds.log` in the Unraid terminal. Include your Unraid version, kernel (`uname -r`), and a description of the light behavior in a [GitHub issue](https://github.com/flybrys/UGREEN-DXP4800GT-LED-Driver/issues). Remove serial numbers, IP addresses, and other private details before posting logs.

The settings page writes `/boot/config/plugins/UGREEN-DXP4800GT-LED-Driver/settings.cfg` on the NAS and restarts the monitor. Advanced users can still edit that file directly; [`src/settings.example.cfg`](src/settings.example.cfg) lists the available keys.

The plugin's own code and repository documentation are [MIT licensed](LICENSE). The bundled kernel modules and i2c-tools have separate upstream licenses; see [third-party notices](THIRD_PARTY.md). Build inputs and release steps for maintainers are in [DEVELOPMENT.md](DEVELOPMENT.md).
