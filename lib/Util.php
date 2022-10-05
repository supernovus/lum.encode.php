<?php

namespace Lum\Encode;

class Util
{
  const B_P = '\\{';
  const B_S = '}';

  /**
   * Make a binary string printable.
   *
   * Replaces any non-printable characters with their hex representations,
   * with a prefix and suffix for easier identification.
   *
   * This is meant more for diagnostics output than serialization.
   *
   * @param string $bin The binary string.
   * @param string $prefix Prefix to add to escaped binary characters
   * (Optional, default: `\\{`);
   * @param string $suffix Suffix to add to escaped binary characters
   * (Optional, default: `}`);
   * @return string The escaped string.
   */
  static function encbin(string $bin, 
    string $prefix=self::B_P, 
    string $suffix=self::B_S): string
  {
    $re = "/[[:^print:]]/";
    $cb = function($m) use ($prefix,$suffix)
    {
      return ($prefix.bin2hex($m[0]).$suffix);
    };
    return preg_replace_callback($re, $cb, $bin);
  }

  /**
   * Rebuild a binary string encoded with `encbin`.
   *
   * This is very rough, and not recommended.
   * It's included for completionist reasons only.
   *
   * @param string $encbin The encoded string.
   * @param string $prefix Must be the same as encoded with.
   * @param string $suffix Must be the same as encoded with.
   * @return string The binary string (hopefully.)
   */
  static function unencbin(string $encbin,
    string $prefix=self::B_P, 
    string $suffix=self::B_S): string
  {
    $ob = quotemeta($prefix);
    $cb = quotemeta($suffix);
    $re = "/${ob}([0..9a..f]+)${cb}/";
    $cb = function($m)
    {
      return hex2bin($m[1]);
    };
    return preg_replace_callback($re, $cb, $bin);
  }

}
