# lum.encode.php

## Summary

A set of libraries for encoding certain kinds of data in PHP.

## Classes

| Class                   | Description                                       |
| ----------------------- | ------------------------------------------------- |
| Lum\Encode\Base91       | A simple `Base91` encoder/decoder.                |
| Lum\Encode\Hash         | A OO wrapper around the php hash functions.       |
| Lum\Encode\Safe64       | Store serialized data as a URL-friendly string.   |
| Lum\Encode\Util         | Misc utility functions.                           |

#### UBJSON

The `UBJSON` Draft-9 implementation that used to be a part of this library has
been removed, but a brand new Draft-12 implementation is now included via the
[lum-ubjson](https://github.com/supernovus/lum.ubjson.php) package, which is
included as one of the dependencies of this library. While the new library has
a new public API, it also offers enough of the old public API to be a drop-in
replacement.

#### Safe64

While `Safe64` was originally just a URL-safe variant of `Base64`, the data
extension added in `v3` makes it into a simple way to store simple objects
as URL-safe strings using an underlying serialization format (`JSON`, `UBJSON`,
or PHP's own `Serialize` format are currently supported.)

A pure [Javascript implementation](https://github.com/supernovus/lum.encode.js) 
that works in browsers or in a CommonJS environment is also available.

## Official URLs

This library can be found in two places:

 * [Github](https://github.com/supernovus/lum.encode.php)
 * [Packageist](https://packagist.org/packages/lum/lum-encode)

## Author

Timothy Totten

## License

[MIT](https://spdx.org/licenses/MIT.html)
