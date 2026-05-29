<?php
$baseDir = __DIR__;
$vendorDir = $baseDir . '/vendor';
$composerDir = $vendorDir . '/composer';

$installed = json_decode(file_get_contents($composerDir . '/installed.json'), true);
$packages = $installed['packages'] ?? $installed;

function resolveInstallPath($baseDir, $package) {
    $relPath = $package['install-path'] ?? '../' . $package['name'];
    $path = $baseDir . '/vendor/composer/' . $relPath;
    $real = realpath($path);
    return $real !== false ? str_replace('\\', '/', $real) : str_replace('\\', '/', $path);
}

$psr4 = [];

// Root project
$rootConfig = json_decode(file_get_contents($baseDir . '/composer.json'), true);
if (isset($rootConfig['autoload']['psr-4'])) {
    foreach ($rootConfig['autoload']['psr-4'] as $prefix => $dirs) {
        foreach ((array) $dirs as $dir) {
            $psr4[$prefix][] = str_replace('\\', '/', $baseDir) . '/' . $dir;
        }
    }
}

// Packages
foreach ($packages as $package) {
    if (!isset($package['autoload']['psr-4'])) continue;
    $installPath = resolveInstallPath($baseDir, $package);
    foreach ($package['autoload']['psr-4'] as $prefix => $dirs) {
        foreach ((array) $dirs as $dir) {
            $psr4[$prefix][] = $installPath . '/' . $dir;
        }
    }
}

uksort($psr4, function ($a, $b) { return strlen($b) - strlen($a); });

// Write PSR-4 file with SEQUENTIAL numeric keys
$content = "<?php\n\nreturn [\n";
foreach ($psr4 as $prefix => $dirs) {
    $content .= "    '" . addslashes($prefix) . "' => array (\n";
    $i = 0;
    foreach ($dirs as $dir) {
        $content .= "  $i => '" . addslashes($dir) . "',\n";
        $i++;
    }
    $content .= "),\n";
}
$content .= "];\n";

file_put_contents($composerDir . '/autoload_psr4.php', $content);
echo "Regenerated autoload_psr4.php with " . count($psr4) . " namespaces\n";
