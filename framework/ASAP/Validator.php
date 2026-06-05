<?php

declare(strict_types=1);

namespace ASAP;

/**
 * PUBLIC LEGACY-ALIGNED VALIDATOR
 *
 * Role:
 *   Preserve the original ASAP validator domain with explicit PHP 8 methods.
 *
 * Responsibility:
 *   Provide deterministic scalar validations.
 *
 * Contract:
 *   Validation returns booleans only. It does not render errors or mutate data.
 *
 * Since:
 *   P112D4C
 *
 * Legacy compatibility:
 *   P112O restores the safe legacy public validator method surface.
 */
final class Validator
{
    /** @return string[] */
    public static function getMessages(): array
    {
        return [];
    }

    public static function notEmpty(mixed $value): bool
    {
        return trim((string) $value) !== '';
    }

    public static function email(mixed $value): bool
    {
        return self::isEmail($value);
    }

    public static function integer(mixed $value): bool
    {
        return self::isInt($value);
    }

    public static function isEmail(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isInt(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        return is_string($value) && preg_match('/^-?\d+$/', $value) === 1;
    }

    public static function isUnsignedInt(mixed $value): bool
    {
        if (is_int($value)) {
            return $value >= 0;
        }

        return is_string($value) && preg_match('/^\d+$/', $value) === 1;
    }

    public static function isNullOrUnsignedInt(mixed $value): bool
    {
        return $value === null || $value === '' || self::isUnsignedInt($value);
    }

    public static function isFloat(mixed $value): bool
    {
        if (is_float($value) || is_int($value)) {
            return true;
        }

        return is_string($value) && filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }

    public static function isUnsignedFloat(mixed $value): bool
    {
        return self::isFloat($value) && (float) $value >= 0.0;
    }

    public static function isOptFloat(mixed $value): bool
    {
        return $value === null || $value === '' || self::isFloat($value);
    }

    public static function isBool(mixed $value): bool
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true);
    }

    public static function isBoolean(mixed $value): bool
    {
        return self::isBool($value);
    }

    public static function is_true(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    public static function is_false(mixed $value): bool
    {
        return $value === false || $value === 0 || $value === '0' || $value === 'false';
    }

    public static function isString(mixed $value): bool
    {
        return is_string($value);
    }

    public static function isDate(mixed $value): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        $timestamp = strtotime($value);

        return $timestamp !== false;
    }

    public static function isBirthDate(mixed $value): bool
    {
        if (!self::isDate($value)) {
            return false;
        }

        return strtotime((string) $value) <= time();
    }

    public static function isMd5(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{32}$/i', $value) === 1;
    }

    public static function isSha1(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{40}$/i', $value) === 1;
    }

    public static function isUrl(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    public static function isAbsoluteUrl(mixed $value): bool
    {
        if (!self::isUrl($value)) {
            return false;
        }

        $parts = parse_url((string) $value);

        return is_array($parts) && isset($parts['scheme'], $parts['host']);
    }

    public static function isProtocol(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-z][a-z0-9+.-]*$/i', $value) === 1;
    }

    public static function isColor(mixed $value): bool
    {
        return is_string($value) && preg_match('/^(#[0-9a-f]{3}([0-9a-f]{3})?|[a-z][a-z0-9_-]*)$/i', $value) === 1;
    }

    public static function isEan13(mixed $value): bool
    {
        if (!is_string($value) && !is_int($value)) {
            return false;
        }

        $code = (string) $value;

        if (preg_match('/^\d{13}$/', $code) !== 1) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += ((int) $code[$i]) * ($i % 2 === 0 ? 1 : 3);
        }

        $check = (10 - ($sum % 10)) % 10;

        return $check === (int) $code[12];
    }

    public static function isFileName(mixed $value): bool
    {
        return is_string($value)
            && trim($value) !== ''
            && !str_contains($value, '/')
            && !str_contains($value, '\\')
            && preg_match('/[<>:"|?*]/', $value) !== 1;
    }

    public static function isIcoFile(mixed $value): bool
    {
        return is_string($value) && self::isFileName(basename($value)) && preg_match('/\.ico$/i', $value) === 1;
    }

    public static function isImgFile(mixed $value): bool
    {
        return is_string($value) && self::isFileName(basename($value)) && preg_match('/\.(png|jpe?g|gif|webp|svg)$/i', $value) === 1;
    }

    public static function isLanguageIsoCode(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $value) === 1;
    }

    public static function isGenderIsoCode(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-z0-9_-]{1,16}$/i', $value) === 1;
    }

    public static function isGenderName(mixed $value): bool
    {
        return self::isGenericName($value);
    }

    public static function isName(mixed $value): bool
    {
        return is_string($value) && preg_match("~^[\p{L}\p{M}][\p{L}\p{M}\s'.-]{0,127}$~u", $value) === 1;
    }

    public static function isGenericName(mixed $value): bool
    {
        return is_string($value) && preg_match("~^[\p{L}\p{N}\p{M}][\p{L}\p{N}\p{M}\s'.:,;_()/-]{0,191}$~u", $value) === 1;
    }

    public static function isCityName(mixed $value): bool
    {
        return self::isGenericName($value);
    }

    public static function isCountryName(mixed $value): bool
    {
        return self::isGenericName($value);
    }

    public static function isStateIsoCode(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-z0-9_-]{1,16}$/i', $value) === 1;
    }

    public static function isAddress(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '' && preg_match('/[<>]/', $value) !== 1;
    }

    public static function isPostCode(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-z0-9][a-z0-9\s-]{1,15}$/i', $value) === 1;
    }

    public static function isPhoneNumber(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\+?[0-9][0-9\s().-]{3,31}$/', $value) === 1;
    }

    public static function isMailName(mixed $value): bool
    {
        return self::isGenericName($value);
    }

    public static function isMailSubject(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '' && preg_match('/[\r\n]/', $value) !== 1;
    }

    public static function isLinkRewrite(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-z0-9][a-z0-9_-]*$/', $value) === 1;
    }

    public static function isSubDomainName(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $value) === 1;
    }

    public static function isCleanHtml(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/<\s*(script|iframe|object|embed)\b/i', $value) !== 1
            && preg_match('/\son[a-z]+\s*=/i', $value) !== 1;
    }

    public static function isLoadedObject(mixed $value): bool
    {
        if (!is_object($value)) {
            return false;
        }

        return isset($value->id) ? self::isUnsignedInt($value->id) && (int) $value->id > 0 : true;
    }

    public static function isValidSearch(mixed $value): bool
    {
        return is_string($value) && preg_match('/[<>]/', $value) !== 1;
    }

    public static function isValide(mixed $value = true): bool
    {
        return (bool) $value;
    }
}
