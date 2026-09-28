<?php
function encodeString($text)
{
    return bin2hex($text);
}

function decodeString($encoded)
{
    return hex2bin($encoded);
}

class Base62
{
    private const ALPHABET =
        '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * Encode arbitrary binary data to Base62.
     */
    public static function encode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $bytes = array_values(unpack('C*', $data));

        // Preserve leading zero bytes.
        $leadingZeros = 0;

        foreach ($bytes as $byte) {
            if ($byte !== 0) {
                break;
            }

            $leadingZeros++;
        }

        $bytes = array_slice($bytes, $leadingZeros);

        $result = '';

        while (!empty($bytes)) {
            $quotient = [];
            $remainder = 0;

            foreach ($bytes as $byte) {
                $value = ($remainder * 256) + $byte;

                $digit = intdiv($value, 62);
                $remainder = $value % 62;

                if (!empty($quotient) || $digit !== 0) {
                    $quotient[] = $digit;
                }
            }

            $result = self::ALPHABET[$remainder] . $result;
            $bytes = $quotient;
        }

        // Character "0" represents leading zero bytes.
        return str_repeat('0', $leadingZeros) . $result;
    }

    /**
     * Decode Base62 back to the original binary data.
     */
    public static function decode(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $leadingZeros = 0;
        $length = strlen($text);

        while (
            $leadingZeros < $length &&
            $text[$leadingZeros] === '0'
        ) {
            $leadingZeros++;
        }

        $text = substr($text, $leadingZeros);

        $bytes = [];

        if ($text !== '') {
            $bytes = [0];

            for ($i = 0, $len = strlen($text); $i < $len; $i++) {
                $value = strpos(self::ALPHABET, $text[$i]);

                if ($value === false) {
                    throw new InvalidArgumentException(
                        'Invalid Base62 character.'
                    );
                }

                $carry = $value;

                for ($j = count($bytes) - 1; $j >= 0; $j--) {
                    $n = ($bytes[$j] * 62) + $carry;

                    $bytes[$j] = $n & 0xff;
                    $carry = intdiv($n, 256);
                }

                while ($carry > 0) {
                    array_unshift(
                        $bytes,
                        $carry & 0xff
                    );

                    $carry = intdiv($carry, 256);
                }
            }
        }

        $result = str_repeat("\x00", $leadingZeros);

        if (!empty($bytes)) {
            $result .= pack('C*', ...$bytes);
        }

        return $result;
    }
}

class Base36
{
    private static $alphabet = '0123456789abcdefghijklmnopqrstuvwxyz';

    public static function encode($data)
    {
        if ($data === '') {
            return '';
        }

        $bytes = array_values(unpack('C*', $data));
        $result = '';

        while (!empty($bytes)) {
            $quotient = array();
            $remainder = 0;

            foreach ($bytes as $byte) {
                $value = ($remainder * 256) + $byte;
                $digit = intdiv($value, 36);
                $remainder = $value % 36;

                if (!empty($quotient) || $digit !== 0) {
                    $quotient[] = $digit;
                }
            }

            $result = self::$alphabet[$remainder] . $result;
            $bytes = $quotient;
        }

        return $result;
    }

    public static function decode($text)
    {
        if ($text === '') {
            return '';
        }

        if (!preg_match('/^[0-9a-z]+$/', $text)) {
            throw new InvalidArgumentException('Invalid Base36 string.');
        }

        $bytes = array(0);

        for ($i = 0, $len = strlen($text); $i < $len; $i++) {
            $value = strpos(self::$alphabet, $text[$i]);

            $carry = $value;

            for ($j = count($bytes) - 1; $j >= 0; $j--) {
                $n = ($bytes[$j] * 36) + $carry;
                $bytes[$j] = $n & 0xff;
                $carry = intdiv($n, 256);
            }

            while ($carry > 0) {
                array_unshift($bytes, $carry & 0xff);
                $carry = intdiv($carry, 256);
            }
        }

        return pack('C*', ...$bytes);
    }
}


$original = 'Hello World! 123Hello World! 123Hello World! 123Hello World! 123';

$encoded = base64_encode($original);
$decoded = base64_decode($encoded);

echo $encoded . PHP_EOL;
echo $decoded . PHP_EOL;

$encoded = Base62::encode($original);
$decoded = Base62::decode($encoded);

echo $encoded . PHP_EOL;
echo $decoded . PHP_EOL;

$encoded = Base36::encode($original);
$decoded = Base36::decode($encoded);

echo $encoded . PHP_EOL;
echo $decoded . PHP_EOL;

$encoded = encodeString($original);
$decoded = decodeString($encoded);

echo $encoded . PHP_EOL;
echo $decoded . PHP_EOL;
