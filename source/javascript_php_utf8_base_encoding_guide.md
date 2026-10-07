# UTF-8 Base Encoding Library --- JavaScript and PHP 7.4

## Requirements

This library supports:

-   Base10
-   Base16 (Hex)
-   Base36
-   Base62
-   Base64
-   Base64URL
-   UTF-8 text
-   JavaScript ES5 syntax for Chrome 49+ and Firefox 52+
-   PHP 7.4
-   JavaScript encode -\> PHP decode
-   PHP encode -\> JavaScript decode

The shared encoding model is:

``` text
UTF-8 text -> UTF-8 bytes -> Base encoding
```

Base36 alphabet:

``` text
0123456789abcdefghijklmnopqrstuvwxyz
```

Base62 alphabet:

``` text
0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ
```

> Base62 does not have one universal alphabet. JavaScript and PHP must
> use exactly the same alphabet.

## What Does "Base" Mean?

In a number system or encoding, **base** means how many different symbols are available to represent a value. It is also called the **radix**.

The decimal system that we use every day is **Base10** because it has 10 digits:

```text
0 1 2 3 4 5 6 7 8 9
```

When a position runs out of available digits, another position is added. For example:

```text
9   -> 10
99  -> 100
```

The same idea applies to other bases.

| Base | Symbols | Number of symbols |
|---|---|---:|
| Base2 | `0-1` | 2 |
| Base10 | `0-9` | 10 |
| Base16 | `0-9`, `a-f` | 16 |
| Base36 | `0-9`, `a-z` | 36 |
| Base62 | `0-9`, `a-z`, `A-Z` | 62 |

For example, the decimal value `15` can be represented differently depending on the base:

```text
Decimal value: 15

Base2  = 1111
Base10 = 15
Base16 = f
Base36 = f
Base62 = f
```

The decimal value `35` provides another useful example:

```text
Decimal value: 35

Base10 = 35
Base16 = 23
Base36 = z
Base62 = z
```

And decimal `36` becomes:

```text
Base10 = 36
Base36 = 10
Base62 = A
```

In this project's Base62 alphabet, lowercase `z` has value 35 and uppercase `A` has value 36 because the alphabet is:

```text
0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ
```

### Why Use a Higher Base?

A higher base can represent the same numeric value with fewer characters because more symbols are available for each position. For example, a large value represented using only decimal digits may become shorter when represented with Base36 or Base62.

This is one reason Base36 and Base62 are useful for compact identifiers, codes, and URL-friendly values.

### How This Applies to UTF-8 Text

For text such as:

```text
Hello World! 你好世界 こんにちは 😀 Café résumé
```

the library does not directly treat the characters as a Base62 number. It first converts the text into its UTF-8 byte sequence:

```text
Text
  |
  v
UTF-8 bytes
  |
  v
Base10 / Base16 / Base36 / Base62 / Base64 / Base64URL
```

The decoder reverses this process:

```text
Encoded value
  |
  v
UTF-8 bytes
  |
  v
Original text
```

This shared byte-level rule is what allows JavaScript and PHP to encode and decode the same UTF-8 content consistently.

### Base64 Is Slightly Different

Base64 also uses a 64-symbol alphabet, but it is normally defined as a binary-to-text encoding that processes binary data in groups of bits rather than simply displaying one large integer in radix 64. Base64URL is a URL-safe variation of Base64.

Therefore, Base10/Base16/Base36/Base62 in this library use arbitrary-base conversion, while Base64/Base64URL use the standard Base64 encoding rules.

---

## JavaScript: `BaseEncoding.js`

``` javascript
var BaseEncoding = (function () {
    'use strict';

    var BASE10 = '0123456789';
    var BASE36 = '0123456789abcdefghijklmnopqrstuvwxyz';
    var BASE62 = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    function utf8Encode(str) {
        var bytes = [];
        var i, code, next;

        for (i = 0; i < str.length; i++) {
            code = str.charCodeAt(i);

            if (code >= 0xD800 && code <= 0xDBFF && i + 1 < str.length) {
                next = str.charCodeAt(i + 1);

                if (next >= 0xDC00 && next <= 0xDFFF) {
                    code = 0x10000 +
                        ((code - 0xD800) << 10) +
                        (next - 0xDC00);
                    i++;
                }
            }

            if (code <= 0x7F) {
                bytes.push(code);
            } else if (code <= 0x7FF) {
                bytes.push(0xC0 | (code >> 6));
                bytes.push(0x80 | (code & 0x3F));
            } else if (code <= 0xFFFF) {
                bytes.push(0xE0 | (code >> 12));
                bytes.push(0x80 | ((code >> 6) & 0x3F));
                bytes.push(0x80 | (code & 0x3F));
            } else {
                bytes.push(0xF0 | (code >> 18));
                bytes.push(0x80 | ((code >> 12) & 0x3F));
                bytes.push(0x80 | ((code >> 6) & 0x3F));
                bytes.push(0x80 | (code & 0x3F));
            }
        }

        return bytes;
    }

    function utf8Decode(bytes) {
        var result = '';
        var i = 0;
        var b1, b2, b3, b4, code;

        while (i < bytes.length) {
            b1 = bytes[i++];

            if (b1 < 0x80) {
                code = b1;
            } else if ((b1 & 0xE0) === 0xC0) {
                b2 = bytes[i++];
                code = ((b1 & 0x1F) << 6) | (b2 & 0x3F);
            } else if ((b1 & 0xF0) === 0xE0) {
                b2 = bytes[i++];
                b3 = bytes[i++];
                code = ((b1 & 0x0F) << 12) |
                    ((b2 & 0x3F) << 6) |
                    (b3 & 0x3F);
            } else {
                b2 = bytes[i++];
                b3 = bytes[i++];
                b4 = bytes[i++];
                code = ((b1 & 0x07) << 18) |
                    ((b2 & 0x3F) << 12) |
                    ((b3 & 0x3F) << 6) |
                    (b4 & 0x3F);
            }

            if (code <= 0xFFFF) {
                result += String.fromCharCode(code);
            } else {
                code -= 0x10000;
                result += String.fromCharCode(0xD800 + (code >> 10));
                result += String.fromCharCode(0xDC00 + (code & 0x3FF));
            }
        }

        return result;
    }

    function encodeBytes(bytes, alphabet) {
        if (bytes.length === 0) {
            return '';
        }

        var base = alphabet.length;
        var zeroCount = 0;
        var digits = [0];
        var i, j, carry;
        var result = '';

        while (zeroCount < bytes.length && bytes[zeroCount] === 0) {
            zeroCount++;
        }

        for (i = zeroCount; i < bytes.length; i++) {
            carry = bytes[i];

            for (j = 0; j < digits.length; j++) {
                carry += digits[j] * 256;
                digits[j] = carry % base;
                carry = Math.floor(carry / base);
            }

            while (carry > 0) {
                digits.push(carry % base);
                carry = Math.floor(carry / base);
            }
        }

        for (i = 0; i < zeroCount; i++) {
            result += alphabet.charAt(0);
        }

        if (zeroCount === bytes.length) {
            return result;
        }

        for (i = digits.length - 1; i >= 0; i--) {
            result += alphabet.charAt(digits[i]);
        }

        return result;
    }

    function decodeBytes(str, alphabet) {
        if (str.length === 0) {
            return [];
        }

        var base = alphabet.length;
        var map = {};
        var zeroCount = 0;
        var bytes = [0];
        var result = [];
        var i, j, character, carry;

        for (i = 0; i < alphabet.length; i++) {
            map[alphabet.charAt(i)] = i;
        }

        while (
            zeroCount < str.length &&
            str.charAt(zeroCount) === alphabet.charAt(0)
        ) {
            zeroCount++;
        }

        for (i = zeroCount; i < str.length; i++) {
            character = str.charAt(i);

            if (typeof map[character] === 'undefined') {
                throw new Error('Invalid encoded character: ' + character);
            }

            carry = map[character];

            for (j = 0; j < bytes.length; j++) {
                carry += bytes[j] * base;
                bytes[j] = carry & 0xFF;
                carry = Math.floor(carry / 256);
            }

            while (carry > 0) {
                bytes.push(carry & 0xFF);
                carry = Math.floor(carry / 256);
            }
        }

        for (i = 0; i < zeroCount; i++) {
            result.push(0);
        }

        if (zeroCount === str.length) {
            return result;
        }

        for (i = bytes.length - 1; i >= 0; i--) {
            result.push(bytes[i]);
        }

        return result;
    }

    function base10Encode(str) {
        return encodeBytes(utf8Encode(str), BASE10);
    }

    function base10Decode(str) {
        return utf8Decode(decodeBytes(str, BASE10));
    }

    function base16Encode(str) {
        var bytes = utf8Encode(str);
        var result = '';
        var i, value;

        for (i = 0; i < bytes.length; i++) {
            value = bytes[i].toString(16);
            if (value.length < 2) {
                value = '0' + value;
            }
            result += value;
        }

        return result;
    }

    function base16Decode(str) {
        var bytes = [];
        var i;

        if (str.length % 2 !== 0 || !/^[0-9a-fA-F]*$/.test(str)) {
            throw new Error('Invalid Base16 string.');
        }

        for (i = 0; i < str.length; i += 2) {
            bytes.push(parseInt(str.substr(i, 2), 16));
        }

        return utf8Decode(bytes);
    }

    function base36Encode(str) {
        return encodeBytes(utf8Encode(str), BASE36);
    }

    function base36Decode(str) {
        return utf8Decode(decodeBytes(str, BASE36));
    }

    function base62Encode(str) {
        return encodeBytes(utf8Encode(str), BASE62);
    }

    function base62Decode(str) {
        return utf8Decode(decodeBytes(str, BASE62));
    }

    function bytesToBinaryString(bytes) {
        var result = '';
        var i;

        for (i = 0; i < bytes.length; i++) {
            result += String.fromCharCode(bytes[i]);
        }

        return result;
    }

    function binaryStringToBytes(str) {
        var bytes = [];
        var i;

        for (i = 0; i < str.length; i++) {
            bytes.push(str.charCodeAt(i));
        }

        return bytes;
    }

    function base64Encode(str) {
        return btoa(bytesToBinaryString(utf8Encode(str)));
    }

    function base64Decode(str) {
        return utf8Decode(binaryStringToBytes(atob(str)));
    }

    function base64UrlEncode(str) {
        return base64Encode(str)
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=+$/g, '');
    }

    function base64UrlDecode(str) {
        var base64 = str
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        while (base64.length % 4 !== 0) {
            base64 += '=';
        }

        return base64Decode(base64);
    }

    return {
        base10Encode: base10Encode,
        base10Decode: base10Decode,
        base16Encode: base16Encode,
        base16Decode: base16Decode,
        base36Encode: base36Encode,
        base36Decode: base36Decode,
        base62Encode: base62Encode,
        base62Decode: base62Decode,
        base64Encode: base64Encode,
        base64Decode: base64Decode,
        base64UrlEncode: base64UrlEncode,
        base64UrlDecode: base64UrlDecode
    };
})();
```

## PHP 7.4: `BaseEncoding.php`

``` php
<?php

class BaseEncoding
{
    private const BASE10 = '0123456789';
    private const BASE36 = '0123456789abcdefghijklmnopqrstuvwxyz';
    private const BASE62 = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private static function stringToBytes(string $value): array
    {
        $bytes = [];
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $bytes[] = ord($value[$i]);
        }

        return $bytes;
    }

    private static function bytesToString(array $bytes): string
    {
        $result = '';

        foreach ($bytes as $byte) {
            $result .= chr($byte);
        }

        return $result;
    }

    private static function encodeBytes(array $bytes, string $alphabet): string
    {
        if (count($bytes) === 0) {
            return '';
        }

        $base = strlen($alphabet);
        $zeroCount = 0;
        $byteCount = count($bytes);

        while ($zeroCount < $byteCount && $bytes[$zeroCount] === 0) {
            $zeroCount++;
        }

        $digits = [0];

        for ($i = $zeroCount; $i < $byteCount; $i++) {
            $carry = $bytes[$i];
            $digitCount = count($digits);

            for ($j = 0; $j < $digitCount; $j++) {
                $carry += $digits[$j] * 256;
                $digits[$j] = $carry % $base;
                $carry = intdiv($carry, $base);
            }

            while ($carry > 0) {
                $digits[] = $carry % $base;
                $carry = intdiv($carry, $base);
            }
        }

        $result = str_repeat($alphabet[0], $zeroCount);

        if ($zeroCount === $byteCount) {
            return $result;
        }

        for ($i = count($digits) - 1; $i >= 0; $i--) {
            $result .= $alphabet[$digits[$i]];
        }

        return $result;
    }

    private static function decodeBytes(string $value, string $alphabet): array
    {
        if ($value === '') {
            return [];
        }

        $base = strlen($alphabet);
        $map = [];

        for ($i = 0; $i < $base; $i++) {
            $map[$alphabet[$i]] = $i;
        }

        $zeroCount = 0;
        $length = strlen($value);

        while ($zeroCount < $length && $value[$zeroCount] === $alphabet[0]) {
            $zeroCount++;
        }

        $bytes = [0];

        for ($i = $zeroCount; $i < $length; $i++) {
            $character = $value[$i];

            if (!array_key_exists($character, $map)) {
                throw new InvalidArgumentException(
                    'Invalid encoded character: ' . $character
                );
            }

            $carry = $map[$character];
            $byteCount = count($bytes);

            for ($j = 0; $j < $byteCount; $j++) {
                $carry += $bytes[$j] * $base;
                $bytes[$j] = $carry & 0xFF;
                $carry = intdiv($carry, 256);
            }

            while ($carry > 0) {
                $bytes[] = $carry & 0xFF;
                $carry = intdiv($carry, 256);
            }
        }

        $result = array_fill(0, $zeroCount, 0);

        if ($zeroCount === $length) {
            return $result;
        }

        for ($i = count($bytes) - 1; $i >= 0; $i--) {
            $result[] = $bytes[$i];
        }

        return $result;
    }

    public static function base10Encode(string $value): string
    {
        return self::encodeBytes(self::stringToBytes($value), self::BASE10);
    }

    public static function base10Decode(string $value): string
    {
        return self::bytesToString(self::decodeBytes($value, self::BASE10));
    }

    public static function base16Encode(string $value): string
    {
        return bin2hex($value);
    }

    public static function base16Decode(string $value): string
    {
        if (strlen($value) % 2 !== 0 || !ctype_xdigit($value)) {
            throw new InvalidArgumentException('Invalid Base16 string.');
        }

        $result = hex2bin($value);

        if ($result === false) {
            throw new InvalidArgumentException('Invalid Base16 string.');
        }

        return $result;
    }

    public static function base36Encode(string $value): string
    {
        return self::encodeBytes(self::stringToBytes($value), self::BASE36);
    }

    public static function base36Decode(string $value): string
    {
        return self::bytesToString(self::decodeBytes($value, self::BASE36));
    }

    public static function base62Encode(string $value): string
    {
        return self::encodeBytes(self::stringToBytes($value), self::BASE62);
    }

    public static function base62Decode(string $value): string
    {
        return self::bytesToString(self::decodeBytes($value, self::BASE62));
    }

    public static function base64Encode(string $value): string
    {
        return base64_encode($value);
    }

    public static function base64Decode(string $value): string
    {
        $result = base64_decode($value, true);

        if ($result === false) {
            throw new InvalidArgumentException('Invalid Base64 string.');
        }

        return $result;
    }

    public static function base64UrlEncode(string $value): string
    {
        return rtrim(
            strtr(base64_encode($value), '+/', '-_'),
            '='
        );
    }

    public static function base64UrlDecode(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]*$/', $value)) {
            throw new InvalidArgumentException('Invalid Base64URL string.');
        }

        $base64 = strtr($value, '-_', '+/');
        $remainder = strlen($base64) % 4;

        if ($remainder !== 0) {
            $base64 .= str_repeat('=', 4 - $remainder);
        }

        $result = base64_decode($base64, true);

        if ($result === false) {
            throw new InvalidArgumentException('Invalid Base64URL string.');
        }

        return $result;
    }
}
```

## Test Data

Use a UTF-8 test string such as:

``` text
Hello World! 你好世界 こんにちは 😀 Café résumé
```

### JavaScript test

``` javascript
var text = 'Hello World! 你好世界 こんにちは 😀 Café résumé';

var encoded = BaseEncoding.base62Encode(text);
var decoded = BaseEncoding.base62Decode(encoded);

console.log(encoded);
console.log(decoded);
console.log(decoded === text);
```

Expected:

``` text
true
```

### PHP test

``` php
<?php

require_once 'BaseEncoding.php';

$text = 'Hello World! 你好世界 こんにちは 😀 Café résumé';

$encoded = BaseEncoding::base62Encode($text);
$decoded = BaseEncoding::base62Decode($encoded);

echo $encoded . PHP_EOL;
echo $decoded . PHP_EOL;
echo ($decoded === $text ? 'true' : 'false') . PHP_EOL;
```

## Cross-Language Usage

JavaScript to PHP:

``` text
JavaScript UTF-8 text
        |
        v
base62Encode()
        |
        v
encoded string
        |
        | AJAX / HTTP / JSON
        v
PHP base62Decode()
        |
        v
original UTF-8 text
```

PHP to JavaScript works in the opposite direction.

The same rule applies to Base10, Base16, Base36, Base62, Base64, and
Base64URL.

## Old Browser Compatibility

The JavaScript implementation deliberately avoids:

-   `let`
-   `const`
-   arrow functions
-   `BigInt`
-   `TextEncoder`
-   `TextDecoder`
-   `async` / `await`
-   `padStart()`
-   `Array.from()`
-   `Object.entries()`

It uses ES5-era functionality such as `var`, normal functions, arrays,
loops, `charCodeAt()`, `String.fromCharCode()`, `btoa()`, and `atob()`.

This is intended for the project's Chrome 49 and Firefox 52
compatibility requirement.

## Choosing an Encoding

  Encoding    Alphabet / Format     Typical purpose
  ----------- --------------------- -----------------------------------------
  Base10      `0-9`                 Decimal-only representation
  Base16      `0-9`, `a-f`          Hexadecimal/debug/binary representation
  Base36      `0-9`, `a-z`          Compact lowercase alphanumeric values
  Base62      `0-9`, `a-z`, `A-Z`   Compact alphanumeric IDs/values
  Base64      Standard Base64       General binary/text transport
  Base64URL   URL-safe Base64       URLs and token-like transport

## Security Warning

Base encoding is **not encryption**.

Anyone who receives a Base10, Base16, Base36, Base62, Base64, or
Base64URL value can decode it if they know the encoding.

Do not use these encodings to protect passwords, secrets, confidential
files, or sensitive application data. Use HTTPS for transport security
and proper cryptography when confidentiality is required.

## Summary

The JavaScript and PHP libraries form one shared encoding specification:

``` text
                 UTF-8 text
                     |
                     v
                 UTF-8 bytes
                     |
       +-------------+-------------+
       |             |             |
       v             v             v
    Base10 ...     Base62       Base64URL
       |             |             |
       +-------------+-------------+
                     |
                     v
          JavaScript <-> PHP 7.4
```

Keep the Base36 and Base62 alphabets identical in both implementations.
This guarantees that the same UTF-8 byte sequence is converted
consistently on the browser and server sides.
