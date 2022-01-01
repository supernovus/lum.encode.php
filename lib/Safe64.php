<?php

namespace Lum\Encode;

use Lum\Exception;
use Lum\Encode\Safe64\{Type, Format, Options, HasFormatType};

/**
 * Safe64 version 3.
 *
 * This is a URL-safe variant of Base64, with a bunch of extras.
 *
 * Replaces `+` with `-`, and `/` with `_`.
 * By default it also strips any `=` characters from the end of the string.
 *
 * It can also encode PHP arrays and object using a few serialization formats.
 *
 *   - JSON       (default, simplest, fastest)
 *   - Serialize  (most complex, largest; for advanced objects)
 *   - UBJSON     (a compact binary format, slowest; for special cases)
 *
 * This is the third major version of the library, and breaks compatibility.
 * While previous versions used all static methods, this version has enough
 * options that using an instance makes more sense now.
 */
class Safe64
{
  use HasFormatType;

  /**
   * The current version of Safe64.
   *
   * Version history:
   *
   *  1. First version of Safe64, always replaced `=` with `~`.
   *     Data could only be stored in JSON or Serialize format.
   *
   *  2. Second version strips `=` characters by default and uses a new
   *     algorithm to re-add them when decoding the strings.
   *     It also added UBJSON to the supported serialization formats.
   *
   *  3. Third version adds a new recommended header to the output string,
   *     which enables format and type auto-detection, and adds the version
   *     number to the header so future versions can be easier to detect.
   *
   *     Version 3 header format is: SVvvFfTt
   *
   *       - vv = version (two digit int, mandatory)
   *       - f  = format  (int, optional if `Format::NONE`)
   *       - t  = type    (int, optional if `Type::String` or `Format::SERIAL`)
   *
   *     Example: SV03F1T1 (ver = 3, format = JSON, type = Array).
   *
   */
  const VERSION = 3;

  const O_F  = 'format';
  const O_T  = 'type';

  const O_UT = 'useTildes';
  const O_AH = 'addHeader';
  const O_FH = 'fullHeader';
  const O_FT = 'forceType';

  const M_UT = 'setUseTildes';
  const M_AH = 'setAddHeader';
  const M_FH = 'setFullHeader';
  const M_FT = 'setForceType';

  const H_V  = 'SV';
  const H_F  = 'F';
  const H_T  = 'T';

  const H_V_L = 2;
  const H_F_L = 1;
  const H_T_L = 1;

  protected Format $format     = Format::JSON;
  protected Type   $type       = Type::Array;
  protected bool   $useTildes  = false;
  protected bool   $addHeader  = true;
  protected bool   $fullHeader = false;
  protected bool   $forceType  = false;

  /**
   * Build a new Safe64 transcoder instance.
   *
   * @param array $opts  (Optional) Named options:
   *
   *  'format'      (Format|int) Serialization format for objects/arrays.
   *                See {@see \Lum\Encode\Safe64\Format} for a list.
   *                Default: `Format::JSON`
   *  'type'        Return type for complex data. 
   *                See {@see \Lum\Encode\Safe64\Type} for a list.
   *                Default: `Type::Array`
   *  'useTildes'   (bool) Replace `=` with `~`; for legacy code only.
   *                Default: `false`
   *  'addHeader'   (bool) Add a V3 header to encoded strings.
   *                Default: `true`
   *  'fullHeader'  (bool) Always include format and type header fields.
   *                Otherwise they're skipped in certain circumstances.
   *                Default: `false`
   *  'forceType'   (bool) When decoding, our `type` overrides the _header_.
   *                Some caveats apply, see `Type` for more details.
   *                Default: `false`
   *
   */
  public function __construct ($opts=[])
  {
    if (isset($opts[self::O_F]) 
      && (is_int($opts[self::O_F]) || $opts[self::O_F] instanceof Format))
    {
      $this->setFormat($opts[self::O_F]);
    }

    if (isset($opts[self::O_T])
      && (is_int($opts[self::O_T]) || $opts[self::O_T] instanceof Type))
    {
      $this->setType($opts[self::O_T]);
    }

    $boolOpts =
    [ // A map of option name to setter method.
      self::O_UT => self::M_UT,
      self::O_AH => self::M_AH,
      self::O_FH => self::M_FH,
      self::O_FT => self::M_FT,
    ];

    foreach ($boolOpts as $opt => $meth)
    { // If the option is found, call the setter method.
      if (isset($opts[$opt]) && is_bool($opts[$opt]))
      {
        $this->$meth($opts[$opt]);
      }
    }
  }

  public function setUseTildes(bool $use): static
  {
    $this->useTildes = $use;
    return $this;
  }

  public function getUseTildes(): bool
  {
    return $this->useTildes;
  }

  public function setAddHeader(bool $add): static
  {
    $this->addHeader = $add;
    return $this;
  }

  public function getAddHeader(): bool
  {
    return $this->addHeader;
  }

  public function setFullHeader(bool $full): static
  {
    $this->fullHeader = $full;
    return $this;
  }

  public function getFullHeader(): bool
  {
    return $this->fullHeader;
  }

  public function setForceType(bool $force): static
  {
    $this->forceType = $force;
    return $this;
  }

  public function getForceType(): bool
  {
    return $this->forceType;
  }

  /**
   * Encode a string into Safe64 format.
   *
   * Unless you have a specific reason to call this directly, you should 
   * just use the `encode()` method instead.
   *
   * @param string $data Data to encode.
   *
   *   May be any string encoding, or raw binary data in a PHP string.
   *
   * @param bool $addHeader (Optional) Add a V3 header to the string.
   * 
   *   Only if the `addHeader` property is also `true`. This is not really
   *   meant for end-user calls, but is used by `encode()` when passing
   *   strings to this method.
   *
   * @return string  The encoded string.
   */
  public function encodeString (string $data, bool $addHeader=false): string
  {
    $base64 = base64_encode($data);
    if ($this->useTildes)
    {
      $base64 = strtr($base64, '+/=', '-_~');
    }
    else
    {
      $base64 = strtr($base64, '+/', '-_');
      $base64 = str_replace('=', '', $base64);
    }

    if ($addHeader && $this->addHeader)
    { // We want to add the header, even though it's a string.
      $header = $this->make_header(Format::NONE, Type::String);
      $base64 = $header.$base64;
    }

    return $base64;    
  }

  protected static function hex (int $number, int $len): string
  {
    return str_pad(dechex($number), $len, '0', STR_PAD_LEFT);
  }

  // Internal method to add a header to a string.
  protected function make_header (Format $format, Type $type, 
    ?int $ver=null): string
  {
    if (!isset($ver)) $ver = self::VERSION;

    $h  = self::H_V;
    $h .= self::hex($ver, self::H_V_L);
    if ($this->fullHeader || $format !== Format::NONE)
    { // Include a format header field.
      $f = $format->value;
      $h .= self::H_F;
      $h .= self::hex($f, self::H_F_L);
      if ($this->fullHeader 
        || ($type !== Type::String && $format !== Format::SERIAL))
      { // Include a type header field.
        $t = $type->value;
        $h .= self::H_T;
        $h .= self::hex($t, self::H_T_L);
      }
    }

    return $h;
  }

  // Internal method to look for a header, and determine format, etc.
  protected function parse_header (string $s): Options
  {
    $opts = new Options($this->format, $this->type);
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
      elseif ($this->format !== Format::NONE)
      { // The only format we skip the format header on is NONE.
        $opts->setFormat(Format::NONE);
      }
    }

    // Okay, set the string, and if applicable, offset.
    $opts->setString($s, $o);

    return $opts;
  }

  /**
   * Decode a Safe64 string back to a non-encoded string.
   *
   * Unless you have a specific reason to call this directly, you should 
   * just use the `decode()` method instead.
   *
   * @param string $string  The Safe64-encoded string.
   *
   *   It does not matter if the `$useTildes` format was used or not,
   *   as this will handle both versions of Safe64 automatically.
   *
   * @return string 
   */
  public function decodeString (Options|string $input): string
  {
    if (is_string($input))
    { // Make sure we strip any header that might be on the string.
      $input = $this->parse_header($input);
    }

    $string = $input->getString();
    $base64 = strtr($string, '-_~', '+/=');
    $base64 .= substr("===", ((strlen($base64)+3)%4));

    return base64_decode($base64, $strict);
  }

  /**
   * Transform data into a Safe64 string.
   *
   * @param mixed $input  The data to serialize and encode.
   *
   *   Generally only three formats are expected here:
   *
   *   - An object, which will be serialized, then encoded.
   *   - An array, which will be serialized, then encoded.
   *   - A string, which will be encoded directly with no serialization.
   *
   *   If the `addHeader` property is `true` (the default), then a header
   *   will always be added to every Safe64 string returned from this method.
   *
   * @return string The encoded string, with applicable header prepended.
   */
  public function encode (mixed $data): string
  {
    if (is_string($data))
    { // It's a string already, sending it to encodeString();
      return $this->encodeString($data, true);
    }

    if ($this->format === Format::JSON)
    {
      $encoded = json_encode($data);
    }
    elseif ($this->format === Format::SERIAL)
    {
      $encoded = serialize($data);
    }
    elseif ($this->format === Format::UBJSON)
    {
      $encoded = UBJSON::encode($data);
    }
    elseif ($this->format === Format::NONE)
    { // This is awkward and probably not super useful.
      $encoded = (string)$data;
    }
    else
    { // How did you get here?
      throw new Exception("encode() impossible format?");
    }

    if ($this->addHeader)
    { // Add a header.
      $header = $this->make_header($this->format, $this->type);
      $encoded = $header.$encoded;
    }

    return $this->encodeString($encoded, false);
  }

  /** 
   * Decode a Safe64-encoded object or array.
   *
   * @param string $input  The Safe64 string to decode.
   *
   * @return mixed  The decoded data.
   */
  public function decode (string $string): mixed
  {
    $opts = $this->parse_header($string);
    $decoded = $this->decodeString($opts);

    $format = $opts->getFormat();
    $type   = $opts->getType();

    if ($format === Format::NONE || $type === Type::String)
    { // We're done here. Returning the string.
      return $decoded;
    }
    elseif ($format === Format::JSON)
    {
      if ($type === Type::Array)
        $assoc = true;
      elseif ($type === Type::Object)
        $assoc = false;
      else
        throw new Exception("decode() impossible type for json");

      return json_decode($decoded, $assoc);
    }
    elseif ($format === Format::SERIAL)
    { // No types apply to serialized data.
      return unserialize($decoded);
    }
    elseif ($format === Format::UBJSON)
    {
      if ($type === Type::Array)
        $utype = UBJSON::TYPE_ARRAY;
      elseif ($type === Type::Object)
        $utype = UBJSON::TYPE_OBJECT;
      else
        throw new Exception("decode() impossible type for ubjson");

      return UBJSON::decode($decoded, $utype);
    }
    else
    {
      throw new Exception("decode() impossible format");
    }
  }

  public static function encodeData (mixed $data, array $opts=[]): string
  {
    $s64 = new static($opts);
    return $s64->encode($data);
  }

  public static function decodeData (string $string, array $opts=[]): mixed
  {
    $s64 = new static($opts);
    return $s64->decode($string);
  }

}