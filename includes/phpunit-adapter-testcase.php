<?php

use PHPUnit\Framework\TestCase;

/**
 * PHPUnit adapter layer.
 *
 * Connects PHPUnit 13 lifecycle methods to the WordPress test-library hooks.
 * WordPress test cases override the snake_case hooks while PHPUnit itself only
 * interacts with its native public lifecycle API.
 */
abstract class PHPUnit_Adapter_TestCase extends TestCase
{
    final public static function setUpBeforeClass(): void
    {
        static::set_up_before_class();
    }

    public static function set_up_before_class() {}

    final public static function tearDownAfterClass(): void
    {
        static::tear_down_after_class();
    }

    public static function tear_down_after_class() {}

    final protected function setUp(): void
    {
        $this->set_up();
    }

    public function set_up() {}

    final protected function assertPreConditions(): void
    {
        $this->assert_pre_conditions();
    }

    protected function assert_pre_conditions() {}

    final protected function assertPostConditions(): void
    {
        $this->assert_post_conditions();
    }

    protected function assert_post_conditions() {}

    final protected function tearDown(): void
    {
        $this->tear_down();
    }

    public function tear_down() {}
}
