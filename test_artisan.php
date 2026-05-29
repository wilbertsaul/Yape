<?php
$_SERVER['argv'] = ['artisan', '--version'];
$_SERVER['argc'] = 2;
ob_start();
require __DIR__ . '/artisan';
$output = ob_get_clean();
echo $output;
