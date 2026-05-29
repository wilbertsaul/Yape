<?php
$installed = json_decode(file_get_contents('vendor/composer/installed.json'), true);
$packages = isset($installed['packages']) ? $installed['packages'] : $installed;
echo "Packages found: " . count($packages) . "\n";

$files = [];
foreach ($packages as $i => $pkg) {
    $dir = 'vendor/' . $pkg['name'];
    $autoload = $pkg['autoload'] ?? [];
    
    if (isset($autoload['files'])) {
        foreach ((array)$autoload['files'] as $file) {
            $files[] = $dir . '/' . $file;
            echo "$i: " . $dir . '/' . $file . "\n";
        }
    }
}
echo "Total files: " . count($files) . "\n";
