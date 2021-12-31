<?php

namespace Lum\Encode\Safe64;

// Internal trait.
trait HasFormatType
{
  public function setFormat(Format|int $format): static
  {
    if (is_int($format))
    {
      $this->format = Format::from($format);
    }
    else
    {
      $this->format = $format;
    }
    return $this;
  }

  public function getFormat(): Format
  {
    return $this->format;
  }

  public function setType(Type|int $type): static
  {
    if (is_int($type))
    {
      $this->type = Type::from($type);
    }
    else
    {
      $this->type = $type;
    }
    return $this;
  }

  public function getType(): Type
  {
    return $this->type;
  }

}
