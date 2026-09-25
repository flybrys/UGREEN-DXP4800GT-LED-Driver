<?php
// SPDX-License-Identifier: MIT

function ugreen_gt_defaults(): array
{
    return [
        'POWER_COLOR' => '#ffffff',
        'POWER_BRIGHTNESS' => '180',
        'NETWORK_INTERFACE' => 'auto',
        'NETWORK_BRIGHTNESS' => '180',
        'NETWORK_COLOR_ONLINE' => '#ffffff',
        'NETWORK_COLOR_OFFLINE' => '#ffa500',
        'CONNECTIVITY_METHOD' => 'https',
        'CONNECTIVITY_URL' => 'https://unraid.net/',
        'CONNECTIVITY_FALLBACK_URL' => 'https://ai.ugreen.com/',
        'CONNECTIVITY_INTERVAL' => '60',
        'NETWORK_ACTIVITY_BYTES' => '65536',
        'DISK_COLOR' => '#ffffff',
        'DISK_COLOR_FAILED' => '#ffa500',
        'DISK_BRIGHTNESS' => '180',
        'DISK_ACTIVITY_STYLE' => 'solid',
        'DISK_PULSE_MS' => '80',
        'DISK_ATA_PORTS' => '1 2 3 4',
        'POLL_INTERVAL' => '0.5',
        'REFRESH_INTERVAL' => '15',
        'DISK_STATUS_INTERVAL' => '30',
    ];
}

function ugreen_gt_color_to_hex(string $value): ?string
{
    if (!preg_match('/^(\d{1,3}) (\d{1,3}) (\d{1,3})$/', $value, $parts)) {
        return null;
    }
    foreach (array_slice($parts, 1) as $part) {
        if ((int)$part > 255) {
            return null;
        }
    }
    return sprintf('#%02x%02x%02x', (int)$parts[1], (int)$parts[2], (int)$parts[3]);
}

function ugreen_gt_color_to_rgb(string $value): string
{
    return hexdec(substr($value, 1, 2)) . ' ' .
        hexdec(substr($value, 3, 2)) . ' ' .
        hexdec(substr($value, 5, 2));
}

function ugreen_gt_read_settings(string $path): array
{
    $values = ugreen_gt_defaults();
    if (!is_file($path)) {
        return $values;
    }
    $contents = file_get_contents($path);
    if ($contents === false) {
        return $values;
    }
    foreach (preg_split('/\r?\n/', $contents) as $line) {
        if (!preg_match('/^([A-Z_]+)=(.*)$/', trim($line), $parts)) {
            continue;
        }
        $key = $parts[1];
        if (!array_key_exists($key, $values)) {
            continue;
        }
        $value = trim($parts[2]);
        if (strlen($value) >= 2 && $value[0] === "'" && substr($value, -1) === "'") {
            $value = substr($value, 1, -1);
        }
        if ($key === 'DISK_ATA_PORTS') {
            $value = trim($value, '()');
        }
        if (str_ends_with($key, '_COLOR') || str_starts_with($key, 'NETWORK_COLOR_') || $key === 'DISK_COLOR_FAILED') {
            $value = ugreen_gt_color_to_hex($value) ?? $values[$key];
        }
        $values[$key] = $value;
    }
    return $values;
}

function ugreen_gt_validate(array $input): array
{
    $values = [];
    $errors = [];
    $defaults = ugreen_gt_defaults();
    $integerRanges = [
        'POWER_BRIGHTNESS' => [1, 255],
        'NETWORK_BRIGHTNESS' => [1, 255],
        'CONNECTIVITY_INTERVAL' => [10, 3600],
        'NETWORK_ACTIVITY_BYTES' => [1, 1000000000],
        'DISK_BRIGHTNESS' => [1, 255],
        'DISK_PULSE_MS' => [30, 1000],
        'REFRESH_INTERVAL' => [1, 3600],
        'DISK_STATUS_INTERVAL' => [10, 3600],
    ];
    foreach ($defaults as $key => $default) {
        $raw = $input[$key] ?? null;
        if (!is_string($raw)) {
            $errors[$key] = 'A value is required.';
            $values[$key] = $default;
            continue;
        }
        $value = trim($raw);
        $values[$key] = $value;
        if (str_contains($key, 'COLOR')) {
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $errors[$key] = 'Choose a valid six-digit colour.';
            } else {
                $values[$key] = strtolower($value);
            }
        } elseif (isset($integerRanges[$key])) {
            [$min, $max] = $integerRanges[$key];
            if (!preg_match('/^\d{1,10}$/', $value) || (int)$value < $min || (int)$value > $max) {
                $errors[$key] = "Enter a whole number from $min to $max.";
            } else {
                $values[$key] = (string)(int)$value;
            }
        } elseif ($key === 'NETWORK_INTERFACE') {
            if ($value !== 'auto' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,14}$/', $value)) {
                $errors[$key] = 'Use auto or a Linux interface name of at most 15 characters.';
            }
        } elseif ($key === 'CONNECTIVITY_METHOD') {
            if (!in_array($value, ['https', 'gateway', 'none'], true)) {
                $errors[$key] = 'Choose a connectivity method.';
            }
        } elseif ($key === 'DISK_ACTIVITY_STYLE') {
            if (!in_array($value, ['solid', 'dark'], true)) {
                $errors[$key] = 'Choose a disk activity style.';
            }
        } elseif ($key === 'CONNECTIVITY_URL' || $key === 'CONNECTIVITY_FALLBACK_URL') {
            $parts = parse_url($value);
            if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' ||
                empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) ||
                !filter_var($value, FILTER_VALIDATE_URL) || preg_match('/[\x00-\x20\x7f\'\\\\]/', $value) ||
                strlen($value) > 255) {
                $errors[$key] = 'Enter an HTTPS URL without credentials or spaces.';
            }
        } elseif ($key === 'DISK_ATA_PORTS') {
            $ports = preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
            if (count($ports) !== 4) {
                $errors[$key] = 'Enter four distinct ATA port numbers.';
            } else {
                foreach ($ports as $port) {
                    if (!preg_match('/^\d{1,3}$/', $port) || (int)$port < 1 || (int)$port > 255) {
                        $errors[$key] = 'ATA ports must be distinct numbers from 1 to 255.';
                        break;
                    }
                }
                if (!isset($errors[$key])) {
                    $ports = array_map('intval', $ports);
                    if (count(array_unique($ports)) !== 4) {
                        $errors[$key] = 'Enter four distinct ATA port numbers.';
                    } else {
                        $values[$key] = implode(' ', $ports);
                    }
                }
            }
        } elseif ($key === 'POLL_INTERVAL') {
            if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value) || (float)$value < 0.1 || (float)$value > 5) {
                $errors[$key] = 'Enter a number from 0.1 to 5 seconds.';
            }
        }
    }
    return [$values, $errors];
}

function ugreen_gt_render_settings(array $values): string
{
    $lines = ['# UGREEN DXP4800 GT LED settings. Generated by the plugin settings page.'];
    foreach (ugreen_gt_defaults() as $key => $_) {
        $value = $values[$key];
        if (str_contains($key, 'COLOR')) {
            $value = "'" . ugreen_gt_color_to_rgb($value) . "'";
        }
        if ($key === 'DISK_ATA_PORTS') {
            $value = "($value)";
        } elseif ($key === 'NETWORK_INTERFACE' || str_starts_with($key, 'CONNECTIVITY_') ||
                  $key === 'DISK_ACTIVITY_STYLE') {
            $value = "'$value'";
        }
        $lines[] = "$key=$value";
    }
    return implode("\n", $lines) . "\n";
}

function ugreen_gt_write_atomic(string $path, string $contents): bool
{
    $temp = tempnam(dirname($path), '.settings-');
    if ($temp === false) {
        return false;
    }
    $ok = file_put_contents($temp, $contents, LOCK_EX) === strlen($contents) &&
        rename($temp, $path);
    if (!$ok) {
        @unlink($temp);
    }
    return $ok;
}
