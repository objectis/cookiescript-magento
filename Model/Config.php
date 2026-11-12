<?php
declare(strict_types=1);

namespace CookieScript\Banner\Model;

use CookieScript\Banner\Model\ConsentMode\Sanitizer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads the module's settings from Stores > Configuration > CookieScript.
 *
 * All getters take an optional store view ID; without it the current store view is used.
 */
class Config
{
    public const LOCATION_HEAD = 'head';
    public const LOCATION_BODY = 'body';

    public const POSITION_FIRST = 'first';
    public const POSITION_LAST = 'last';

    private const PATH_ENABLED = 'cookiescript/banner/enabled';
    private const PATH_SOURCE_URL = 'cookiescript/banner/source_url';
    private const PATH_LOCATION = 'cookiescript/banner/location';
    private const PATH_POSITION = 'cookiescript/banner/position';

    private const PATH_CONSENT_MODE_ENABLED = 'cookiescript/google_consent_mode/enabled';
    private const PATH_CONSENT_MODE_PREFIX = 'cookiescript/google_consent_mode/';
    private const PATH_CONSENT_MODE_REGIONAL = 'cookiescript/google_consent_mode/regional';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $json
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * The banner link from the CookieScript account (Installation tab).
     */
    public function getSourceUrl(?int $storeId = null): string
    {
        return trim((string) $this->getValue(self::PATH_SOURCE_URL, $storeId));
    }

    /**
     * @return string LOCATION_HEAD or LOCATION_BODY.
     */
    public function getLocation(?int $storeId = null): string
    {
        $location = (string) $this->getValue(self::PATH_LOCATION, $storeId);

        return $location === self::LOCATION_BODY ? self::LOCATION_BODY : self::LOCATION_HEAD;
    }

    /**
     * @return string POSITION_FIRST or POSITION_LAST.
     */
    public function getPosition(?int $storeId = null): string
    {
        $position = (string) $this->getValue(self::PATH_POSITION, $storeId);

        return $position === self::POSITION_LAST ? self::POSITION_LAST : self::POSITION_FIRST;
    }

    public function isConsentModeEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::PATH_CONSENT_MODE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Google Consent Mode defaults that apply to every visitor.
     *
     * @return array<string, mixed> Seven consent values plus "wait_for_update".
     */
    public function getConsentModeGlobalSettings(?int $storeId = null): array
    {
        $settings = [];
        foreach ([...Sanitizer::CONSENT_KEYS, 'wait_for_update'] as $key) {
            $settings[$key] = $this->getValue(self::PATH_CONSENT_MODE_PREFIX . $key, $storeId);
        }

        return Sanitizer::sanitizeBlock($settings);
    }

    /**
     * Google Consent Mode defaults for specific regions.
     *
     * @return array<int, array<string, mixed>> Each entry has "region_code", the consent values and "wait_for_update".
     */
    public function getConsentModeRegionalSettings(?int $storeId = null): array
    {
        $stored = $this->getValue(self::PATH_CONSENT_MODE_REGIONAL, $storeId);
        if (!is_string($stored) || $stored === '') {
            return [];
        }

        try {
            $rows = $this->json->unserialize($stored);
        } catch (\InvalidArgumentException) {
            return [];
        }

        $regions = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $region = Sanitizer::sanitizeBlock($row, true);
            if ($region['region_code'] !== '') {
                $regions[] = $region;
            }
        }

        return $regions;
    }

    private function getValue(string $path, ?int $storeId): mixed
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
