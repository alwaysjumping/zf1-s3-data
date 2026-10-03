# Base Encoding Guide for PHP 7.4

## Overview

This guide summarizes Base10, Base16, Base36, Base62, Base64, and
Base64URL and provides a reusable PHP 7.4 utility.

  Representation   Alphabet            Typical use
  ---------------- ------------------- --------------------------------
  Base10           `0-9`               Decimal integers
  Base16           `0-9a-f`            Hex, hashes, binary display
  Base36           `0-9a-z`            Lowercase compact IDs
  Base62           `0-9a-zA-Z`         Short alphanumeric numeric IDs
  Base64           `A-Z a-z 0-9 + /`   Binary-to-text
  Base64URL        `A-Z a-z 0-9 - _`   URL-safe binary-to-text, JWT

> Encoding is not encryption.

## What Does "Base" Mean?

A **base** (also called a **radix**) tells us how many different symbols
are available to represent a number.

For example, the decimal system we normally use is **Base10** because it
has 10 symbols:

``` text
0 1 2 3 4 5 6 7 8 9
```

Binary is **Base2** because it has only two symbols:

``` text
0 1
```

Hexadecimal is **Base16** because it has 16 symbols:

``` text
0 1 2 3 4 5 6 7 8 9 A B C D E F
```

The same numerical value can therefore be written differently depending
on the base.

For example, decimal `255` can be represented as:

``` text
Base2  (binary):       11111111
Base8  (octal):             377
Base10 (decimal):           255
Base16 (hexadecimal):        FF
```

These are not different values. They are different **representations of
the same value**.

### Place Values

The meaning of each digit depends on its position and the base.

For Base10:

``` text
123
```

means:

``` text
1 × 10² + 2 × 10¹ + 3 × 10⁰

= 100 + 20 + 3
= 123
```

For Base2:

``` text
1011
```

means:

``` text
1 × 2³ + 0 × 2² + 1 × 2¹ + 1 × 2⁰

= 8 + 0 + 2 + 1
= 11
```

Therefore:

``` text
1011 (Base2) = 11 (Base10)
```

For hexadecimal:

``` text
FF
```

where `F` represents decimal `15`:

``` text
F × 16¹ + F × 16⁰

= 15 × 16 + 15 × 1
= 240 + 15
= 255
```

Therefore:

``` text
FF (Base16) = 255 (Base10)
```

### Why Use Different Bases?

Different bases are useful for different purposes:

``` text
Base2   → computers and binary data
Base10  → normal human-readable numbers
Base16  → hexadecimal, memory values, hashes, binary display
Base36  → compact lowercase alphanumeric identifiers
Base62  → shorter alphanumeric numeric identifiers
Base64  → representing arbitrary binary data as text
```

As the base increases, more symbols are available for each position, so
a numerical value can often be represented with fewer characters.

For example:

``` text
Same integer: 123456789

Base10: 123456789
Base16: 75bcd15
Base36: 21i3v9
Base62: 8m0Kx
```

> Base64 is commonly discussed alongside these bases, but standard
> Base64 is primarily a **binary-to-text encoding scheme**, not simply
> an integer numeral system like Base10, Base16, Base36, or Base62.

## Base10

Base10 is ordinary decimal notation:

``` text
0123456789
```

``` php
$number = 123456789;
echo $number;
```

Output:

``` text
123456789
```

## Base16

Base16 (hexadecimal) uses:

``` text
0123456789abcdef
```

Example:

``` text
Decimal: 255
Binary:  11111111
Hex:     ff
```

PHP:

``` php
echo dechex(255);       // ff
echo hexdec('ff');      // 255
echo bin2hex('Hello');  // 48656c6c6f
echo hex2bin('48656c6c6f'); // Hello
```

## Base36

Base36 uses only digits and lowercase letters:

``` text
0123456789abcdefghijklmnopqrstuvwxyz
```

This is useful when values must contain only `0-9` and `a-z`.

``` php
echo base_convert('123456789', 10, 36);
// 21i3v9

echo base_convert('21i3v9', 36, 10);
// 123456789
```

PHP `base_convert()` supports bases 2 through 36.

## Base62

A useful Base62 alphabet is:

``` text
0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ
```

Base62 is useful for compact numeric identifiers. It is case-sensitive,
so `a` and `A` are different.

Using the alphabet above:

``` text
Base10: 123456789
Base62: 8m0Kx
```

Base62 does not have one universally standardized alphabet ordering, so
encoder and decoder must use the same alphabet.

PHP `base_convert()` does not support Base62.

## Base64

Standard Base64 uses 64 symbols and optional `=` padding.

``` php
$encoded = base64_encode('Hello');
echo $encoded;
// SGVsbG8=

echo base64_decode($encoded, true);
// Hello
```

Base64 is useful for binary-to-text transport in APIs, JSON, email, and
data URLs.

Base64 is not encryption.

## Base64URL

Base64URL changes standard Base64 characters:

``` text
+ -> -
/ -> _
```

and commonly removes trailing `=` padding.

It is suitable for URLs and is used in JWT representations.

## Encoding vs Hashing vs Encryption

``` text
Encoding             Hashing             Encryption
--------             -------             ----------
Base16               SHA-256             AES
Base36               SHA-512             asymmetric crypto
Base62               Argon2
Base64
Base64URL

Reversible           Normally one-way    Reversible with key
```

Encoding a secret with Base64, hex, or Base62 does not protect it.

## Reusable `S3_BaseEncoder` for PHP 7.4

``` php
<?php

class S3_BaseEncoder
{
    const BASE16_ALPHABET = '0123456789abcdef';
    const BASE36_ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyz';
    const BASE62_ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public static function encodeBase10($number)
    {
        self::validateNumber($number);
        return (string) $number;
    }

    public static function decodeBase10($value)
    {
        return self::decodeInteger($value, '0123456789');
    }

    public static function encodeBase16($number)
    {
        return self::encodeInteger($number, self::BASE16_ALPHABET);
    }

    public static function decodeBase16($value)
    {
        return self::decodeInteger($value, self::BASE16_ALPHABET);
    }

    public static function encodeBase36($number)
    {
        return self::encodeInteger($number, self::BASE36_ALPHABET);
    }

    public static function decodeBase36($value)
    {
        return self::decodeInteger($value, self::BASE36_ALPHABET);
    }

    public static function encodeBase62($number)
    {
        return self::encodeInteger($number, self::BASE62_ALPHABET);
    }

    public static function decodeBase62($value)
    {
        return self::decodeInteger($value, self::BASE62_ALPHABET);
    }

    public static function encodeBase64($data)
    {
        return base64_encode($data);
    }

    public static function decodeBase64($value)
    {
        $result = base64_decode($value, true);

        if ($result === false) {
            throw new InvalidArgumentException('Invalid Base64 value.');
        }

        return $result;
    }

    public static function encodeBase64Url($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function decodeBase64Url($value)
    {
        if (!is_string($value) ||
            !preg_match('/^[A-Za-z0-9_-]*$/', $value)) {
            throw new InvalidArgumentException('Invalid Base64URL value.');
        }

        $remainder = strlen($value) % 4;

        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $result = base64_decode(strtr($value, '-_', '+/'), true);

        if ($result === false) {
            throw new InvalidArgumentException('Invalid Base64URL value.');
        }

        return $result;
    }

    private static function encodeInteger($number, $alphabet)
    {
        self::validateNumber($number);

        $base = strlen($alphabet);

        if ($number === 0) {
            return $alphabet[0];
        }

        $result = '';

        while ($number > 0) {
            $remainder = $number % $base;
            $result = $alphabet[$remainder] . $result;
            $number = intdiv($number, $base);
        }

        return $result;
    }

    private static function decodeInteger($value, $alphabet)
    {
        self::validateEncodedValue($value);

        $base = strlen($alphabet);
        $result = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $position = strpos($alphabet, $value[$i]);

            if ($position === false) {
                throw new InvalidArgumentException('Invalid encoded value.');
            }

            if ($result > intdiv(PHP_INT_MAX - $position, $base)) {
                throw new OverflowException(
                    'Decoded value exceeds PHP_INT_MAX.'
                );
            }

            $result = ($result * $base) + $position;
        }

        return $result;
    }

    private static function validateNumber($number)
    {
        if (!is_int($number) || $number < 0) {
            throw new InvalidArgumentException(
                'Number must be a non-negative integer.'
            );
        }
    }

    private static function validateEncodedValue($value)
    {
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException(
                'Encoded value must be a non-empty string.'
            );
        }
    }
}
```

## Integer Examples

``` php
$number = 123456789;

echo S3_BaseEncoder::encodeBase10($number); // 123456789
echo S3_BaseEncoder::encodeBase16($number); // 75bcd15
echo S3_BaseEncoder::encodeBase36($number); // 21i3v9
echo S3_BaseEncoder::encodeBase62($number); // 8m0Kx
```

Decode:

``` php
echo S3_BaseEncoder::decodeBase16('75bcd15'); // 123456789
echo S3_BaseEncoder::decodeBase36('21i3v9');  // 123456789
echo S3_BaseEncoder::decodeBase62('8m0Kx');   // 123456789
```

## Base64 Example

``` php
$text = 'Hello World';

$encoded = S3_BaseEncoder::encodeBase64($text);
echo $encoded;
// SGVsbG8gV29ybGQ=

echo S3_BaseEncoder::decodeBase64($encoded);
// Hello World
```

## Base64URL Example

``` php
$text = 'Hello World';

$encoded = S3_BaseEncoder::encodeBase64Url($text);
echo $encoded;
// SGVsbG8gV29ybGQ

echo S3_BaseEncoder::decodeBase64Url($encoded);
// Hello World
```

## Choosing a Representation

  Requirement                       Recommended choice
  --------------------------------- --------------------
  Decimal integer                   Base10
  Hex representation                Base16
  Only `0-9a-z`                     Base36
  Compact alphanumeric numeric ID   Base62
  Arbitrary binary/text to text     Base64
  URL-safe binary/text              Base64URL
  JWT representation                Base64URL
  Human-readable hash display       Base16

## Security Warning for Public IDs

Converting a sequential database ID to Base62 does not make it secret:

``` text
123456789
    |
    | Base62 encode
    v
8m0Kx
    |
    | Base62 decode
    v
123456789
```

If an application needs an unpredictable public identifier, generate a
cryptographically random identifier instead of merely converting an
auto-increment ID to another base.

## PHP Integer Limitation

The integer conversion functions above operate within PHP's native
integer range (`PHP_INT_MAX`). The decoder includes overflow checking.

For values larger than PHP's native integer range, use an
arbitrary-precision implementation instead of allowing PHP to convert
the value to floating point.

## Recommended Architecture

``` text
                    S3_BaseEncoder
                          |
             +------------+------------+
             |                         |
             v                         v
      Integer Conversion        Binary/Text Encoding
             |                         |
      +------+------+             +----+----+
      |      |      |             |         |
    Base10 Base16 Base36        Base64   Base64URL
                    |
                  Base62
```

Keep integer base conversion separate conceptually from Base64/Base64URL
binary-to-text encoding.
