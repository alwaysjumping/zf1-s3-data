# PHP UTF-8 String Ellipsis Guide

## 1. Problem

When shortening a UTF-8 string in PHP, using normal `strlen()` and
`substr()` can break multibyte characters.

For example:

``` php
$text = 'Hello 世界, this is UTF-8 text.';

echo substr($text, 0, 10);
```

Functions such as `strlen()` and `substr()` operate on **bytes**, not
UTF-8 characters.

A UTF-8 character can use more than one byte, so `substr()` can cut a
character in the middle and produce broken or invalid text.

------------------------------------------------------------------------

## 2. Recommended Solution: `mb_strlen()` and `mb_substr()`

PHP's `mbstring` extension provides multibyte-safe string functions.

``` php
function ellipsis(string $text, int $length = 30): string
{
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }

    return mb_substr(
        $text,
        0,
        $length - 3,
        'UTF-8'
    ) . '...';
}
```

Usage:

``` php
$text = 'Hello 世界, this is a UTF-8 string example.';

echo ellipsis($text, 30);
```

The result will contain at most 30 characters, including the three dots.

The calculation is:

``` text
27 characters
+ 3 dots
----------------
30 characters
```

------------------------------------------------------------------------

## 3. Why `mb_substr()` Solves the Problem

Normal `substr()` works with bytes:

``` php
substr($text, 0, 30);
```

`mb_substr()` understands the character encoding:

``` php
mb_substr($text, 0, 30, 'UTF-8');
```

Therefore, it does not normally cut a UTF-8 multibyte character in the
middle.

Similarly:

``` php
strlen($text);
```

returns the number of bytes, while:

``` php
mb_strlen($text, 'UTF-8');
```

returns the number of characters.

------------------------------------------------------------------------

## 4. Simple Version

If the `...` does not need to be included in the 30-character limit:

``` php
function ellipsis(string $text, int $length = 30): string
{
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }

    return mb_substr(
        $text,
        0,
        $length,
        'UTF-8'
    ) . '...';
}
```

In this version, a 30-character substring plus `...` can produce a
result of 33 characters.

------------------------------------------------------------------------

## 5. Using the Unicode Ellipsis Character

Instead of three ASCII dots:

``` text
...
```

you can use the single Unicode ellipsis character:

``` text
…
```

Example:

``` php
function ellipsis(string $text, int $length = 30): string
{
    $suffix = '…';

    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }

    return mb_substr(
        $text,
        0,
        $length - 1,
        'UTF-8'
    ) . $suffix;
}
```

This gives:

``` text
29 characters + … = 30 characters
```

------------------------------------------------------------------------

## 6. `mb_strimwidth()`

PHP also provides `mb_strimwidth()`:

``` php
$result = mb_strimwidth(
    $text,
    0,
    30,
    '...',
    'UTF-8'
);
```

This function is useful when the goal is closer to limiting the
**display width** rather than simply counting Unicode characters.

For example, CJK characters can occupy more display width than ordinary
ASCII characters.

### Character-count approach

Use:

``` php
mb_strlen()
mb_substr()
```

when the requirement means:

> Keep at most 30 characters.

### Display-width approach

Use:

``` php
mb_strimwidth()
```

when the requirement means:

> Shorten the text according to an approximate terminal/display width.

For ordinary application logic where the rule is specifically "30
characters", `mb_strlen()` + `mb_substr()` is usually clearer.

------------------------------------------------------------------------

## 7. Recommended Helper for PHP 7.4

For a reusable PHP 7.4 helper:

``` php
function ellipsis(
    string $text,
    int $length = 30,
    string $suffix = '...'
): string {
    if ($length <= 0) {
        return '';
    }

    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }

    $suffixLength = mb_strlen($suffix, 'UTF-8');

    if ($suffixLength >= $length) {
        return mb_substr(
            $suffix,
            0,
            $length,
            'UTF-8'
        );
    }

    return mb_substr(
        $text,
        0,
        $length - $suffixLength,
        'UTF-8'
    ) . $suffix;
}
```

Examples:

``` php
echo ellipsis(
    'Hello 世界, this is a long UTF-8 string.',
    30
);
```

Custom suffix:

``` php
echo ellipsis(
    'Hello 世界, this is a long UTF-8 string.',
    30,
    '…'
);
```

This version:

-   Supports UTF-8.
-   Does not split normal UTF-8 multibyte characters.
-   Keeps the total character count within the requested length.
-   Supports either `...` or `…`.
-   Works with PHP 7.4 when the `mbstring` extension is enabled.

------------------------------------------------------------------------

## 8. Check the `mbstring` Extension

You can check whether `mbstring` is enabled:

``` php
if (extension_loaded('mbstring')) {
    echo 'mbstring is enabled';
}
```

Or from the command line:

``` bash
php -m
```

Look for:

``` text
mbstring
```

You can also check:

``` bash
php -i | grep mbstring
```

on Linux.

------------------------------------------------------------------------

## 9. Important Note About Unicode Grapheme Clusters

`mb_substr()` protects UTF-8 multibyte characters, which solves the
common broken-character problem.

However, some visually single symbols can consist of multiple Unicode
code points, such as certain emoji, emoji with skin-tone modifiers,
flags, and characters combined with accent marks.

For ordinary multilingual text, `mb_substr()` is normally sufficient.

If an application must preserve complete user-perceived Unicode
characters such as complex emoji, PHP's grapheme functions from the
`intl` extension can be considered.

------------------------------------------------------------------------

## 10. Final Recommendation

For a PHP 7.4 application where the requirement is "shorten this UTF-8
text to at most 30 characters", use:

``` php
function ellipsis(string $text, int $length = 30): string
{
    $suffix = '...';

    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }

    return mb_substr(
        $text,
        0,
        $length - mb_strlen($suffix, 'UTF-8'),
        'UTF-8'
    ) . $suffix;
}
```

This is simple, readable, and safe for normal UTF-8 multibyte text.
