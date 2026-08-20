<?php

/**
 * Unit Tests: JsonSerializable_Object
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 5.3.0
 */

class JsonSerializable_Object implements JsonSerializable
{
    public function __construct(private $data) {}

    #[ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->data;
    }
}
