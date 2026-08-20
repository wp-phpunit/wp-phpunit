<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
	->in(__DIR__ . '/includes')
	->append([
		__DIR__ . '/__loaded.php',
		__DIR__ . '/rector.php',
		__DIR__ . '/wp-tests-config.php',
	]);

return (new Config())
	->setRiskyAllowed(true)
	->setRules([
		'@PER-CS3.0' => true,
		'array_syntax' => ['syntax' => 'short'],
		'fully_qualified_strict_types' => true,
		'native_function_invocation' => ['include' => ['@compiler_optimized']],
		'no_unused_imports' => true,
		'ordered_imports' => true,
		'strict_comparison' => true,
		'strict_param' => true,
	])
	->setFinder($finder);

