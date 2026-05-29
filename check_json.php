<?php
$json = json_decode(file_get_contents('vendor/composer/installed.json'), true);
echo isset($json['packages']) ? 'has packages key' : 'flat array';
echo "\n";
echo "Total packages: " . count(isset($json['packages']) ? $json['packages'] : $json) . "\n";
