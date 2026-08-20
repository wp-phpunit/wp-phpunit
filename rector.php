<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/includes',
        __DIR__ . '/__loaded.php',
        __DIR__ . '/wp-tests-config.php',
    ])
    ->withPhpSets(php85: true);
