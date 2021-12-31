<?php

namespace Lum\Encode\Safe64;

/**
 * Represent the valid formats that Safe64 can serialize data with.
 */
enum Format: int
{
  /**
   * No serialization.
   *
   * This is used automatically when encoding a string or binary data.
   * If applied to an object or array it will cast it as a string, so if
   * the object has a `__toString()` method, that will be called.
   *
   * Automatically assumes `Type::String` when decoding.
   *
   * int value: `0`
   */
  case NONE = 0;

  /**
   * Use JSON.
   *
   * JSON is the simplest format. It's decently compact, and quite fast.
   * It's the default format when encoding arrays or objects.
   *
   * int value: `1`
   */
  case JSON   = 1;

  /**
   * Use the PHP Serialize format.
   *
   * This is the most complex format, and as such will have the largest
   * serialized output. It can hold much more complex data structures than
   * JSON or UBJSON, so its available for advanced uses.
   *
   * int value: `2`
   */
  case SERIAL = 2;

  /**
   * Use UBJSON.
   *
   * UBJSON is a binary JSON-compatible serialization format.
   * It's the slowest of all the options, but there may be times when the
   * binary format is exactly what you're looking for.
   *
   * int value: `3`
   */
  case UBJSON = 3;

}
