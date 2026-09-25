<?php
// SPDX-License-Identifier: MIT
require_once __DIR__ . '/../src/web/settings-lib.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

[$values, $errors] = ugreen_gt_validate(ugreen_gt_defaults());
check($errors === [], 'Default settings must validate.');
check($values['DISK_ACTIVITY_STYLE'] === 'solid', 'Default disk activity style must be solid.');

$file = tempnam(sys_get_temp_dir(), 'ugreen-settings-test-');
check($file !== false, 'A temporary settings file is required.');
try {
    check(ugreen_gt_write_atomic($file, ugreen_gt_render_settings($values)), 'Settings must save.');
    check(ugreen_gt_read_settings($file) === $values, 'Saved settings must load without changes.');
    exec('bash -n ' . escapeshellarg($file) . ' 2>&1', $syntaxOutput, $syntaxStatus);
    check($syntaxStatus === 0, 'Saved settings must be valid Bash syntax.');
} finally {
    @unlink($file);
}

$custom = ugreen_gt_defaults();
$custom['POWER_COLOR'] = '#1245ab';
$custom['DISK_ACTIVITY_STYLE'] = 'dark';
$custom['DISK_ATA_PORTS'] = '4, 3, 2, 1';
[$normalized, $errors] = ugreen_gt_validate($custom);
check($errors === [], 'Valid custom values must validate.');
check($normalized['DISK_ATA_PORTS'] === '4 3 2 1', 'ATA ports must be normalized.');
check(str_contains(ugreen_gt_render_settings($normalized), "POWER_COLOR='18 69 171'\n"),
    'Picker colour must become MCU RGB values.');

$bad = $custom;
$bad['CONNECTIVITY_URL'] = "https://example.com/'\nPOWER_COLOR=0 0 0";
$bad['DISK_ATA_PORTS'] = '01 1 2 3';
$bad['DISK_PULSE_MS'] = '99999';
[, $errors] = ugreen_gt_validate($bad);
check(isset($errors['CONNECTIVITY_URL'], $errors['DISK_ATA_PORTS'], $errors['DISK_PULSE_MS']),
    'Invalid and injected settings must be rejected.');

$_SERVER['REQUEST_METHOD'] = 'GET';
$var = ['csrf_token' => 'test-token'];
ob_start();
require __DIR__ . '/../src/web/settings.php';
$html = ob_get_clean();
check(substr_count($html, 'type="color"') === 5, 'Five native colour pickers must render.');
check(str_contains($html, 'name="DISK_ACTIVITY_STYLE"'), 'Activity style selector must render.');
check(str_contains($html, 'value="test-token"'), 'Unraid CSRF token must be included.');

echo "Settings validation, rendering, and reload passed.\n";
