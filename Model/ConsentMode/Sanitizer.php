<?php
declare(strict_types=1);

namespace CookieScript\Banner\Model\ConsentMode;

/**
 * Validates and cleans Google Consent Mode settings.
 *
 * Used twice: when the admin saves the settings (reject bad input) and again when the
 * script is printed on the storefront (never trust what is stored).
 */
final class Sanitizer
{
    /**
     * The seven consent types Google Consent Mode v2 knows. Each one is "granted" or "denied".
     */
    public const CONSENT_KEYS = [
        'ad_storage',
        'analytics_storage',
        'ad_user_data',
        'ad_personalization',
        'functionality_storage',
        'personalization_storage',
        'security_storage',
    ];

    public const DEFAULT_WAIT_FOR_UPDATE = 500;

    /**
     * One or more codes such as "US", "US-CA" or "DE, FR", separated by commas.
     */
    private const REGION_CODES_PATTERN = '/^\s*([a-z]{2}(-[a-z0-9]{1,3})?\s*)(,\s*[a-z]{2}(-[a-z0-9]{1,3})?\s*)*$/i';

    /**
     * Returns "granted" only for exactly "granted", otherwise "denied".
     *
     * @param mixed $value
     */
    public static function consentValue(mixed $value): string
    {
        return $value === 'granted' ? 'granted' : 'denied';
    }

    /**
     * @param string $regionCodes For example "US-CA" or "DE, FR".
     */
    public static function isValidRegionCodes(string $regionCodes): bool
    {
        return preg_match(self::REGION_CODES_PATTERN, trim($regionCodes)) === 1;
    }

    /**
     * True for whole numbers greater than zero ("500", 500), false for "", "0", "-5", "1.5", "abc".
     *
     * @param mixed $value
     */
    public static function isPositiveInteger(mixed $value): bool
    {
        return is_scalar($value) && ctype_digit((string) $value) && (int) $value > 0;
    }

    /**
     * Cleans one block of settings (the global one, or one region).
     *
     * - consent values are limited to "granted" / "denied"
     * - wait_for_update becomes a positive integer (500 when missing or invalid)
     * - unknown keys are dropped
     *
     * @param array<string, mixed> $settings
     * @param bool $isRegion Keep the "region_code" as well.
     * @return array<string, mixed>
     */
    public static function sanitizeBlock(array $settings, bool $isRegion = false): array
    {
        $clean = [];

        if ($isRegion) {
            $clean['region_code'] = trim((string) ($settings['region_code'] ?? ''));
        }

        foreach (self::CONSENT_KEYS as $key) {
            $clean[$key] = self::consentValue($settings[$key] ?? 'denied');
        }

        $waitForUpdate = $settings['wait_for_update'] ?? 0;
        $clean['wait_for_update'] = self::isPositiveInteger($waitForUpdate)
            ? (int) $waitForUpdate
            : self::DEFAULT_WAIT_FOR_UPDATE;

        return $clean;
    }
}
