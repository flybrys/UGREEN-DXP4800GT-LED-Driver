# Third-party components

The repository's MIT license covers the monitor, packaging, Community Apps metadata, and documentation written here. Release packages also contain these components:

| Component | Source | License |
| --- | --- | --- |
| `led-ugreen.ko` | [UGREEN LED controller, commit `c830a229`](https://github.com/miskcoo/ugreen_leds_controller/tree/c830a2293cf5c67c58e5a98ca339b089b2b13fc3), plus [`patches/led-ugreen-hardening.patch`](patches/led-ugreen-hardening.patch) | The module source declares `SPDX-License-Identifier: GPL-2.0-only` and `MODULE_LICENSE("GPL v2")`. The upstream repository's other files carry their own notices. |
| `i2c-designware-core.ko`, `i2c-designware-platform.ko` | [Unraid 6.18.38 kernel source release](https://github.com/ich777/unraid_kernel/releases/tag/6.18.38-Unraid), SHA-256 `b336c66bf1d7ee2cedba88e8be2124b2256c94476b1d1c944518ce8d6bcf37da` | Their DesignWare source files declare `GPL-2.0-or-later`. |
| i2c-tools 4.3 release package | [i2c-tools upstream](https://git.kernel.org/pub/scm/utils/i2c-tools/i2c-tools.git/) | Upstream GPL/LGPL notices vary by file; see the [4.3 source](https://cdn.kernel.org/pub/software/utils/i2c-tools/i2c-tools-4.3.tar.xz) and its `COPYING` and `LICENSE` files. |

The third-party package and modules are distributed under their own terms. The source links, pinned versions, and local patch above identify the corresponding build inputs. No personal NAS configuration or identifiers are included in the release packages.
