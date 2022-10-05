<?php

namespace Lum\Encode;

use Lum\Exception;
use Lum\Encode\Safe64\{Type, Format, Options, Header, HasFormatType};

/**
 * Safe64 version 3.
 *
 * This is a URL-safe variant of Base64, with the Data extension, and a 
 * bunch of extras thrown in for good measure.
 *
 * Replaces `+` with `-`, and `/` with `_`.
 * By default it also strips any `=` characters from the end of the string.
 *
 * Using the Data extension, it can also encode PHP arrays and object using 
 * a few serialization formats.
 *
 *   - JSON       (default, simplest, fastest)
 *   - Serialize  (most complex, largest; for advanced objects)
 *   - UBJSON     (a compact binary format, slowest; for special cases)
 *
 * This is the third major version of the library, and breaks compatibility.
 *
 * While previous versions used all static methods, this version has enough
 * options that using an instance makes more sense now.
 *
 * The Data extension is not fully supported in different language bindings.
 * Particularly the Serialize format is *ONLY* supported in PHP, and the JSON
 * format is the only one currently supported in Javascript and Kotlin/JVM.
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
   *     Examples: 
   *
   *     - `SV03F1T1` (ver = 3, format = JSON,   type = Array)
   *     - `SV03F2`   (ver = 3, format = SERIAL, type = *)
   *     - `SV03`     (ver = 3, format = NONE,   type = String)
   *
   */
  const VERSION = 3;

  const O_F  = 'format';
  const O_T  = 'type';

  const O_BOOL_PRE = 'set';
  const O_BOOL_MAP = 
  [
    'useTildes', 'addHeader', 'fullHeader', 'forceType', 'strict', 'encodeStr',
  ];

  protected Format $format     = Format::JSON;
  protected Type   $type       = Type::Array;

  protected bool   $useTildes  = false;
  protected bool   $addHeader  = true;
  protected bool   $fullHeader = false;
  protected bool   $forceType  = false;
  protected bool   $strict     = false;
  protected bool   $encodeStr  = false;

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
   *  'encodeStr'   (bool) If this is `true` we will encode strings passed to
   *                the `encode()` method using the current format.
   *                If it is `false` we assume strings passed are already
   *                in the current format.
   *                Default: `false`
   *  'strict'      (bool) Whether to use strict base64 decoding.
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

    $prefix = static::O_BOOL_PRE;

    foreach (static::O_BOOL_MAP as $opt => $meth)
    { 
      if (is_numeric($opt))
      { // A flat list item, we have special logic for this.
        $opt = $meth;
        $meth = $prefix.ucfirst($opt);
      }

      if (isset($opts[$opt]) && is_bool($opts[$opt]))
      { // Option was found and was a boolean, pass it to the method.
        $this->$meth($opts[$opt]);
      }
    }

  } // __construct()

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

  public function setStrict(bool $strict): static
  {
    $this->strict = $strict;
    return $this;
  }

  public function getStrict(): bool
  {
    return $this->strict;
  }

  public function setEncodeStr(bool $encode): static
  {
    $this->encodeStr = $encode;
    return $this;
  }

  public function getEncodeStr(): bool
  {
    return $this->encodeStr;
  }

  // Internal method to look for a header, and determine format, etc.
  protected function parse_header (string $s): Options
  {
    $opts = new Options($this->format, $this->type);
    return Header::parse($s, $opts);
  }

  /**
   * Static utility function to simply strip a V3 header off.
   *
   * @param string $safe64  The input string.
   *
   * @return string  The raw Safe64 string with no V3 header.
   */
  public static function stripHeader (string $safe64)
  {
    $opts = Header::parse($safe64);
    return $opts->getString();
  }

  /**
   * Static function to convert Base64 into *raw* Safe64 (no headers.)
   *
   * @param string $base64     The Base64 string to convert.
   * @param bool   $useTildes  (Optional) Convert `=` into `~` (old V1 format.)
   *                           Default: `false`
   *
   * @return string  The Safe64 string.
   */
  public static function fromBase64 (string $base64, bool $useTildes=false)
  {
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

  /**
   * Static function to convert *raw* Safe64 into Base64.
   *
   * @param string $safe64  The Safe64 string to convert.
   *
   *   Does not matter if the `useTildes` mode was in use or not, this
   *   handles both forms automatically.
   *
   * @return string  The Base64 string.
   */
  public static function toBase64 (string $safe64)
  {
    $base64 = strtr($safe64, '-_~', '+/=');
    $base64 .= substr("===", ((strlen($base64)+3)%4));
    return $base64;
  }

  /**
   * Encode an arbitrary string to *raw* Safe64 format (no headers).
   *
   * @param string $data       The data string we are encoding.
   * @param bool   $useTildes  (Optional) Passed to `fromBase64()`
   *
   * @return string  The Safe64 string.
   */
  public static function encodeStr (string $data, bool $useTildes=false)
    : string
  {
    $base64 = base64_encode($data);
    return static::fromBase64($base64, $useTildes);
  }

  /**
   * Decode a *raw* Safe64 string into an arbitrary data string again.
   *
   * @param string $data    The Safe64 string to decode.
   * @param bool   $strict  (Optional) Use strict-mode when decoding.
   *
   * @return string|false
   *
   *   If `$strict` is `false`, this will always return a string.
   *   If `$strict` is `true`, this will return `false` if invalid characters
   *   are found in the input string.
   *
   */
  public static function decodeStr (string $data, bool $strict=false)
    : string|false
  {
    $base64 = static::toBase64($data);
    return base64_decode($base64, $strict);
  }

  // Internal method to encode a string and add a header.
  protected function encode_string (string $data): string
  {
    $safe64 = static::encodeStr($data, $this->useTildes);
    if ($this->addHeader)
    { // Add a header.
      $header 
      = Header::build(
        $this->format, 
        $this->type, 
        static::VERSION, 
        $this->fullHeader
      );
      $safe64 = $header.$safe64;
    }
    return $safe64;    
  }

  // Internal method to parse a header from a string and decode the string.
  protected function decode_string (Options|string $input): string
  {
    if (is_string($input))
    { // Make sure we strip any header that might be on the string.
      $input = $this->parse_header($input);
    }
    $decoded = static::decodeStr($input->getString(), $this->strict);
    if (!is_string($decoded))
    {
      throw new Exception("Invalid characters in encoded string");
    }
    return $decoded;
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
    if (is_string($data) && !$this->encodeStr)
    { // It's a string already, sending it directly to encode_string();
      return $this->encode_string($data);
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

    return $this->encode_string($encoded);    
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
    $decoded = $this->decode_string($opts);

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