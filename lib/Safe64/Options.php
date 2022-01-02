<?php

namespace Lum\Encode\Safe64;

use \Lum\Exception;
use \Lum\Encode\Safe64 as S64;

// Internal class not needed for outside use.
class Options
{
  use HasFormatType;

  protected int $version = 0;

  protected Format $format;
  protected Type   $type;

  protected int    $offset = 0;
  protected string $string = '';

  public function __construct(Format $format, Type $type)
  {
    $this->format = $format;
    $this->type   = $type;
  }

  public function setString(string $string, int $offset): static
  {
    $this->string = $string;
    $this->offset = $offset;
    return $this;
  }

  public function getString(bool $full=false): string
  {
    if ($full || $this->offset === 0)
    {
      return $this->string;
    }
    else
    {
      return substr($this->string, $this->offset);
    }
  }

  public function setVersion (int|string $ver): static
  {
    if (is_string($ver))
    {
      $ver = intval($ver, 16);
    }

    if ($ver < 0) throw new Exception("Cannot set version lower than 0");
    if ($ver > 255) throw new Exception("Cannot set version higher than 255");

    $this->version = $ver;
    return $this;
  }

  public function getVersion(bool $wantStr=false): int|string
  {
    if ($wantStr)
    {
      return S64::hex($this->version, S64::H_V_L);
    }
    else
    {
      return $this->version;
    }
  }

  public function getHeader(bool $full=false): string
  {
    if (empty($this->string) || $this->offset === 0)
    { // No current string, or found header. Generate one.
      return Header::build($this->format, $this->type, $this->version, $full);
    }
    else
    { // Extract the header from the string.
      return substr($this->string, 0, $this->offset);
    }
  }

}