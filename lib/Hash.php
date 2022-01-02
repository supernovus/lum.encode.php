<?php

namespace Lum\Encode;

/**
 * A very simplistic wrapper for the PHP Hash library.
 *
 * Usage:
 *
 *  $hash = new \Lum\Encode\Hash('sha256');
 *  $hash->update('Hello');
 *  $hash->update('World');
 *  $bitstr = $hash->final();
 *
 */
class Hash
{
  /**
   * The internal hash object returned from PHP's hash_init().
   */
  protected $hash;

  /**
   * Create a new Hash object.
   *
   * @param mixed  $algorithm  If a string, this is the hashing algorithm.
   *                           If a HashContext object, make a copy of it.
   * @param int   $flags       (Optional) hash() flags.
   * @param mixed $key         (Optional) hash() key.
   * @param array $options     (Optional) hash() options.
   */
  public function __construct (mixed $algorithm, 
    int $flags=0, 
    string $key='', 
    array $options=[])
  {
    if (is_string($algorithm))
    {
      $this->hash = hash_init($algorithm, $flags, $key, $options);
    }
    elseif ($algorithm instanceof \HashContext)
    {
      $this->hash = hash_copy($algorithm);
    }
    else
    {
      throw new \Exception("Lum\Encode\Hash must be passed a string or HashContext object");
    }
  }

  /**
   * Create a copy of this Hash object with it's own hash context.
   */
  public function copy ()
  {
    return new static($this->hash);
  }

  /**
   * Call a hash function.
   *
   * Basically any method not locally defined in this class will call:
   *
   *  hash_{function}($hash, $arg1, ...);
   *
   */
  public function __call ($name, $arguments)
  {
    $func = "hash_$name";
    if (function_exists($func))
    {
      array_unshift($arguments, $this->hash);
      return call_user_func_array($func, $arguments);
    }
    else
    {
      throw new \Exception("No such method '$name' in Hash class.");
    }
  }

  /**
   * Return a base64 encoded string. This finalizes the hash object.
   */
  public function base64 (): string
  {
    $binary = $this->final(True);
    return base64_encode($binary);
  }

  /**
   * Return a Safe64 encoded string. This finalizes the hash object.
   */
  public function safe64 (bool $withHeader=false, array $opts=[]): string
  {
    $binary = $this->final(True);
    if ($withHeader)
    {
      return Safe64::encodeData($binary, $opts);
    }
    else
    {
      $ut = (isset($opts['useTildes'])) ? $opts['useTildes'] : false;
      return Safe64::encodeStr($binary, $ut);
    }
  }

  /**
   * Return a base91 encoded string. This finalizes the hash object.
   */
  public function base91 (): string
  {
    $binary = $this->final(True);
    return Base91::encode($binary);
  }

  /**
   * Return a Hex string. This finalizes the hash object.
   */
  public function __toString ()
  {
    return $this->final();
  }

} // end of class Lum\Encode\Hash

