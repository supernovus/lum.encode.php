<?php

namespace Lum\Encode\Safe64;

use \Lum\Encode\Safe64 as S64;

class Header
{
  const H_V  = 'SV';
  const H_F  = 'F';
  const H_T  = 'T';

  const H_V_L = 2;
  const H_F_L = 1;
  const H_T_L = 1;

  /**
   * Format an integer as a hex number of a set length, padded with zeros.
   */
  public static function hex (int $number, int $len): string
  {
    return str_pad(dechex($number), $len, '0', STR_PAD_LEFT);
  }

  /**
   * Build a V3 header which can be prepended to an encoded string.
   */
  public static function build (
    Format $format, 
    Type $type, 
    int $ver=null, 
    bool $full=false): string
  {
    if (!isset($ver)) $ver = S64::VERSION;

    $h  = self::H_V;
    $h .= self::hex($ver, self::H_V_L);
    if ($full || $format !== Format::NONE)
    { // Include a format header field.
      $f = $format->value;
      $h .= self::H_F;
      $h .= self::hex($f, self::H_F_L);
      if ($full || ($type !== Type::String && $format !== Format::SERIAL))
      { // Include a type header field.
        $t = $type->value;
        $h .= self::H_T;
        $h .= self::hex($t, self::H_T_L);
      }
    }

    return $h;
  }

  /**
   * Parse a string looking for a V3 header, and return an Options instance.
   */
  public static function parse(string $s, ?Options $opts=null): Options
  {
    if (!isset($opts))
    { // No Options instance passed, create one.
      $opts = new Options(Format::NONE, Type::String);
    }

    $o   = 0;
    $l = strlen(self::H_V);
    if (substr($s, $o, $l) === self::H_V)
    { // The first two characters match. Continue parsing the header.
      $o = $l;
      $l = self::H_V_L;
      $ver = substr($s, $o, $l);
      $opts->setVersion($ver);
      $o += $l;
      $l = strlen(self::H_F);
      if (substr($s, $o, $l) === self::H_F)
      { // The format tag was found.
        $o += $l;
        $l = self::H_F_L;
        $fmt = intval(substr($s, $o, $l), 16);
        $opts->setFormat($fmt);
        $o += $l;
        $l = strlen(self::H_T);
        if (substr($s, $o, $l) === self::H_T)
        { // The type tag was found.
          $o += $l;
          $l = self::H_F_T;
          $type = intval(substr($s, $o, $l), 16);
          $opts->setType($type);
          $o += $l;
        }
      }
      else
      { // The only format we skip the format header on is NONE.
        $opts->setFormat(Format::NONE);
      }
    }

    // Okay, set the string, and if applicable, offset.
    $opts->setString($s, $o);

    return $opts;
  }

}