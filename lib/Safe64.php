<?php

namespace Lum\Encode;

/**
 * A URL-safe variant of BASE64.
 */
class Safe64
{
  public static function encode ($data, $useTildes=false)
  {
    $base64 = base64_encode($data);
    if ($useTildes)
    {
      $base64 = strtr($base64, '+/=', '-_~');
    }
    else
    {
      $base64 = strtr($base64, '+/', '-_');
      $base64 = str_replace('=', '', $base64);
    }
    return $base64;    
  }

  public static function decode ($string)
  {
    $base64 = strtr($string, '-_~', '+/=');
    $base64 .= substr("===", ((strlen($base64)+3)%4));
    return base64_decode($base64);
  }

}