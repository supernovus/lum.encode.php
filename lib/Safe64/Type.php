<?php

namespace Lum\Encode\Safe64;

/**
 * The return type when decoding complex structures.
 * 
 * If `Format::SERIAL` is used, then only `Type::String` will have any
 * affect on its output. The other two will be ignored.
 *
 * If `Format::NONE` is used, then `Type::String` is automatically used.
 *
 */
enum Type: int
{
  /**
   * Don't deserialize data at all. Return a string.
   *
   * int value: `0`
   */
  case String = 0;

  /**
   * Return deserialized data as a PHP array.
   *
   * int value: `1`
   */
  case Array = 1;

  /**
   * Return deserialized data as a stdClass object.
   *
   * int value: `2`
   */
  case Object = 2;

}

