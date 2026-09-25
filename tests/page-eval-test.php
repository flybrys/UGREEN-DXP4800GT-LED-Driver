<?php
// SPDX-License-Identifier: MIT
// Run on Unraid after installing the package.
$path = '/usr/local/emhttp/plugins/UGREEN-DXP4800GT-LED-Driver/UGREEN-DXP4800GT-LED-Driver.page';
$plugin = '/boot/config/plugins/UGREEN-DXP4800GT-LED-Driver.plg';
$manifest = file_get_contents($plugin);
if ($manifest === false ||
    !str_contains($manifest, 'launch="Settings/UGREEN-DXP4800GT-LED-Driver"')) {
    throw new RuntimeException('Plugin icon must launch the settings page.');
}
$page = file_get_contents($path);
if ($page === false || !str_contains($page, "\n---\n") ||
    !str_contains($page, 'Markdown="false"')) {
    throw new RuntimeException('Installed Unraid page is missing or malformed.');
}
$body = explode("\n---\n", $page, 2)[1];
$var = ['csrf_token' => 'test-token'];
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
eval('?>' . $body);
$html = ob_get_clean();
if (substr_count($html, 'type="color"') !== 5 ||
    !str_contains($html, 'name="DISK_ACTIVITY_STYLE"') ||
    !str_contains($html, 'value="test-token"')) {
    throw new RuntimeException('Installed settings page did not render its controls.');
}
echo "Installed Unraid page evaluation passed.\n";
