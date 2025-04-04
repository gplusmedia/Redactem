<?php

declare(strict_types=1);

namespace Gbhorwood\Redactem;

/**
 * MIT License
 *
 * Copyright (c) 2019 grant horwood
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
class Redact
{
    /**
     * Keys of password values
     * @var Array<String>
     */
    private static array $passwordKeys = [
        'password',
        'repeat_password',
        'password_repeat',
        'psswd',
        'repeat_passwd',
        'passwd_repeat',
        'pass',
        'repeat_pass',
        'pass_repeat',
        'pwd',
        'repeat_pwd',
        'pwd_repeat',
    ];

    // phpcs:ignore Generic.Files.LineLength.TooLong
    private static string $ccRegex = '#(^4[0-9]{12}(?:[0-9]{3})?$)|(^(?:5[1-5][0-9]{2}|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|27[01][0-9]|2720)[0-9]{12}$)|(3[47][0-9]{13})|(^3(?:0[0-5]|[68][0-9])[0-9]{11}$)|(^6(?:011|5[0-9]{2})[0-9]{12}$)|(^(?:2131|1800|35\d{3})\d{11}$)#';

    /**
     * Redact passwords by key from $json with optional $text
     * @param  ?string $json The json to redact
     * @param  ?string $text The optional string to replace password values
     * @return ?string
     */
    public static function passwords(?string $json, ?string $text = null): ?string
    {
        // function to test case insensitive key equivalence for redaction
        $shouldRedact = self::matchPasswordFunction();

        // function to create redaction text, either passed $text or default
        $redactionText = is_null($text) ? self::defaultRedactionText() : fn($v) => $text;

        return self::redact($json, $shouldRedact, $redactionText);
    }

    /**
     * Redact credit cards by value from $json with optional $text
     * @param  ?string $json The json to redact
     * @param  ?string $text The optional string to replace password values
     * @return ?string
     */
    public static function creditcards(?string $json, ?string $text = null): ?string
    {
        // function to test if value is credit card by regex
        $shouldRedact = self::matchCreditcardFunction();

        // function to create redaction text, either passed $text or default
        $redactionText = is_null($text) ? self::lengthAwareRedactionText() : fn($v) => $text;

        return self::redact($json, $shouldRedact, $redactionText);
    }

    /**
     * Redact emails by value from $json with optional $text
     * @param  ?string $json The json to redact
     * @param  ?string $text The optional string to replace password values
     * @return ?string
     */
    public static function emails(?string $json, ?string $text = null): ?string
    {
        // function to test if value is email
        $shouldRedact = self::matchEmailFunction();

        // function to create redaction text, either passed $text or default
        $redactionText = is_null($text) ? self::emailRedactionText() : fn($v) => $text;

        return self::redact($json, $shouldRedact, $redactionText);
    }

    /**
     * Redact values by key $key from $json with optional $text.
     * Matching is case insensitive by default, set $caseSensitive to true to enable sensitivity.
     * @param  ?string $json The json to redact
     * @param string $key The key of the values to redact
     * @param bool $caseSensitive Set to true to enable case-sensitive matches. Case-insensitive by default.
     * @param  ?string $text The optional string to replace values
     * @return ?string
     */
    public static function byKey(?string $json, string $key, bool $caseSensitive = false, ?string $text = null): ?string
    {
        // function to test key equivalence
        $shouldRedact = $caseSensitive ?
            fn($k, $v) => ($k === $key) :
            fn($k, $v) => (strtolower($k) === strtolower($key));

        // function to create redaction text, either passed $text or default
        $redactionText = is_null($text) ? self::defaultRedactionText() : fn($v) => $text;

        return self::redact($json, $shouldRedact, $redactionText);
    }

    /**
     * Redact values by keys in $keys from $json with optional $text.
     * Matching is case insensitive by default, set $caseSensitive to true to enable sensitivity.
     * @param  ?string $json The json to redact
     * @param Array<string> $keys The key of the values to redact
     * @param bool $caseSensitive Set to true to enable case-sensitive matches. Case-insensitive by default.
     * @param  ?string $text The optional string to replace values
     * @return ?string
     */
    public static function byKeys(
        ?string $json,
        array $keys,
        bool $caseSensitive = false,
        ?string $text = null
    ): ?string {
        // function to test key equivalence
        $shouldRedact = $caseSensitive ?
            fn($k, $v) => in_array(trim((string)$k), array_filter($keys)) :
            fn($k, $v) => in_array(
                strtolower(trim((string)$k)),
                array_filter(array_map('trim', array_map('strtolower', $keys)))
            );

        // function to create redaction text, either passed $text or default
        $redactionText = is_null($text) ? self::defaultRedactionText() : fn($v) => $text;

        return self::redact($json, $shouldRedact, $redactionText);
    }

    /**
     * Redact values by values that match the regex $regex from $json with optional $text.
     * @param  ?string $json The json to redact
     * @param string $regex The regular expression
     * @param  ?string $text The optional string to replace values
     * @return ?string
     */
    public static function byRegex(?string $json, string $regex, ?string $text = null): ?string
    {
        // function to test key equivalence
        $shouldRedact = fn($k, $v) => (bool)preg_match($regex, (string)$v);

        // function to create redaction text, either passed $text or default
        $redactionText = is_null($text) ? self::defaultRedactionText() : fn($v) => $text;

        return self::redact($json, $shouldRedact, $redactionText);
    }

    /**
     * Get the function that tests if key is designated as a password key
     * @return callable
     */
    public static function matchPasswordFunction(): callable
    {
        return fn($k, $v) => (bool)in_array(
            strtolower(trim((string)$k)),
            array_map('trim', array_map('strtolower', self::$passwordKeys))
        );
    }

    /**
     * Get the function that tests if value is a credit card number
     * @return callable
     */
    public static function matchCreditcardFunction(): callable
    {
        return fn($k, $v) => (bool)preg_match(self::$ccRegex, trim(str_replace([' ', '-'], '', $v)));
    }

    /**
     * Get the function that tests if value is an email address
     * @return callable
     */
    public static function matchEmailFunction(): callable
    {
        return fn($k, $v) => (bool)filter_var(trim((string)$v), FILTER_VALIDATE_EMAIL);
    }

    /**
     * Redact each key/value pair in $json that evalues to true by $shouldRedact replacing
     * the value with the result of $redactionText.
     * @param  ?string $json The json string to redact
     * @param callable $shouldRedact A function accepting args key and value and returning a bool
     * @param  ?callable $redactionText A function accepting arg value and returning a string
     * @return ?string
     */
    public static function redact(?string $json, callable $shouldRedact, ?callable $redactionText = null): ?string
    {
        // assign default redaction text function if none passed
        $redactionText = $redactionText ?? self::defaultRedactionText();

        /**
         * Accepts the key and value of a key/value pair and returns the new value.
         * Value is redacted with $redactionText if $shouldRedact evaluates to true.
         */
        $getVal = function ($k, $v) use ($shouldRedact, $redactionText) {
            return $shouldRedact($k, $v) ? $redactionText($v) : $v;
        };

        /**
         * Recurse across $json, evaluating each key/value pair with $shouldRedact and
         * replacing the value with $redactionText if true by applying $getVal.
         */
        $traverse = function (array $json) use (&$traverse, $getVal) {
            foreach ($json as $k => $v) {
                // handle string of json: decode and recurse
                if (is_string($v) && self::isJson($v)) {
                    $json[$k] = json_encode($traverse(json_decode($json[$k], true)));
                } elseif (is_scalar($v) || is_null($v)) {
                    // handle any other string or number or null: get value and assign
                    $json[$k] = $getVal($k, $json[$k]);
                } else {
                    // handle any array or object: decode and recurse
                    $json[$k] = $traverse($json[$k]);
                }
            }
            return $json;
        };

        /**
         * Return json with redactions if string is valid json
         */
        return self::isJson($json) ? (string)json_encode($traverse(json_decode($json, true))) : $json;
    }

    /**
     * Returns a function that accepts a value and returns a redaction text for it.
     * Five characters, $char[0] or default asterisk
     * @param string $char Optional argument for character to use in redaction text
     * @return callable
     */
    public static function defaultRedactionText(string $char = '*'): callable
    {
        return fn($v) => join(array_fill(0, 5, $char[0]));
    }

    /**
     * Returns a function that accepts a value and returns a redaction text for it.
     * String of chars, $char[0] or default asterisk, the length of the passed value
     * @param string $char Optional argument for character to use in redaction text
     * @return callable
     */
    public static function lengthAwareRedactionText(string $char = '*'): callable
    {
        return fn($v) => join(array_fill(0, strlen($v), $char[0]));
    }

    /**
     * Returns a function that accepts a value and returns a redaction text for it.
     * Partial redaction of email address with $char[0] or default asterisk
     * @param string $char Optional argument for character to use in redaction text
     * @return callable
     */
    public static function emailRedactionText(string $char = '*'): callable
    {
        return function (string $v) use ($char) {
            $domain = substr($v, strrpos($v, '@') + 1);
            $tld = substr($domain, strpos($domain, '.') + 1);
            $sld = substr($domain, 0, (int)strpos($domain, '.'));
            $user = substr($v, 0, (int)strrpos($v, '@'));
            $displaySld = strlen($sld) > 4
                ? substr($sld, 0, 2)
                . join(array_fill(0, strlen($sld) - 4, $char[0]))
                . substr($sld, -2)
                : substr($sld, 0, 1)
                . join(array_fill(0, strlen($sld) - 1, $char[0]));
            $displayUser = strlen($user) > 4
                ? substr($user, 0, 2)
                . join(
                    array_fill(0, strlen($user) - 4, $char[0])
                ) . substr($user, -2)
                : substr($user, 0, 1)
                . join(array_fill(0, strlen($user) - 1, $char[0]));
            return sprintf(
                '%s@%s.%s',
                $displayUser,
                $displaySld,
                $tld
            );
        };
    }

    /**
     * Evaluates if the string $json is valid json
     * @param  ?string $json
     * @return bool
     */
    private static function isJson(?string $json): bool
    {
        return is_null($json) ? false : is_array(json_decode($json, true));
    }
}
