<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PHPUnit_Adapter_TestCase::class)]
final class PHPUnitAdapterTest extends TestCase
{
    #[Test]
    public function itRunsWordPressLifecycleHooksThroughPHPUnit13(): void
    {
        LifecycleProbeTestCase::resetEvents();

        LifecycleProbeTestCase::setUpBeforeClass();
        $probe = new LifecycleProbeTestCase('probe');
        $probe->runBare();
        LifecycleProbeTestCase::tearDownAfterClass();

        self::assertSame(
            ['before-class', 'set-up', 'pre-conditions', 'test', 'post-conditions', 'tear-down', 'after-class'],
            LifecycleProbeTestCase::events(),
        );
    }

    #[Test]
    public function hookCallbacksRemainRemovable(): void
    {
        $files = [
            'includes/abstract-testcase.php',
            'includes/testcase-ajax.php',
            'includes/testcase-rest-post-type-controller.php',
            'includes/wp-profiler.php',
        ];

        foreach ($files as $file) {
            $source = file_get_contents(dirname(__DIR__) . '/' . $file);

            self::assertIsString($source);
            self::assertDoesNotMatchRegularExpression(
                '/\bremove_(?:filter|action)\([^;\n]*->\w+\(\.\.\.\)/',
                $source,
                $file . ' must use a stable callback representation for removable WordPress hooks.',
            );
        }
    }
}

final class LifecycleProbeTestCase extends PHPUnit_Adapter_TestCase
{
    /** @var list<string> */
    private static array $events = [];

    public static function resetEvents(): void
    {
        self::$events = [];
    }

    /** @return list<string> */
    public static function events(): array
    {
        return self::$events;
    }

    public static function set_up_before_class()
    {
        self::$events[] = 'before-class';
    }

    public static function tear_down_after_class()
    {
        self::$events[] = 'after-class';
    }

    public function set_up()
    {
        self::$events[] = 'set-up';
    }

    public function tear_down()
    {
        self::$events[] = 'tear-down';
    }

    protected function assert_pre_conditions()
    {
        self::$events[] = 'pre-conditions';
    }

    protected function assert_post_conditions()
    {
        self::$events[] = 'post-conditions';
    }

    public function probe(): void
    {
        self::$events[] = 'test';
        self::assertTrue(true);
    }
}
