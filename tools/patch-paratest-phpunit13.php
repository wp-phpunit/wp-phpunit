<?php

declare(strict_types=1);

if (2 !== $argc) {
    throw new InvalidArgumentException('Expected the path to ParaTest WrapperWorker.php.');
}

$path = $argv[1];
if (! is_file($path)) {
    throw new RuntimeException('ParaTest WrapperWorker.php was not found: ' . $path);
}

$contents = file_get_contents($path);
if (false === $contents) {
    throw new RuntimeException('Could not read ParaTest WrapperWorker.php: ' . $path);
}

$old = '$phpunitArguments[] = \'--do-not-cache-result\';';
$new = '$phpunitArguments[] = \'--do-not-record-test-run-history\';';

if (1 !== substr_count($contents, $old)) {
    throw new RuntimeException('Unexpected ParaTest cache-history argument implementation.');
}

$contents = str_replace($old, $new, $contents);
if (false === file_put_contents($path, $contents)) {
    throw new RuntimeException('Could not patch ParaTest WrapperWorker.php: ' . $path);
}
