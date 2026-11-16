<?php
declare(strict_types=1);

namespace CookieScript\Banner\Model\ConsentMode;

use Magento\Framework\Serialize\Serializer\Json;

/**
 * Builds the small inline script that sets Google Consent Mode defaults.
 *
 * It must run before the CookieScript banner script, so the banner can update the consent
 * state once the visitor makes a choice.
 */
class ScriptBuilder
{
    public function __construct(
        private readonly Json $json
    ) {
    }

    /**
     * @param array<string, mixed> $global Settings that apply everywhere.
     * @param array<int, array<string, mixed>> $regional Settings that apply to specific regions only.
     */
    public function build(array $global, array $regional): string
    {
        $lines = [
            'window.dataLayer = window.dataLayer || [];',
            'window.gtag = window.gtag || function(){dataLayer.push(arguments);};',
        ];

        // Regional defaults first: Google picks the most specific "default" that matches the visitor.
        foreach ($regional as $region) {
            $regionCodes = $this->splitRegionCodes((string) ($region['region_code'] ?? ''));
            if ($regionCodes === []) {
                continue;
            }

            $lines[] = $this->buildConsentDefault($region, $regionCodes);
        }

        $lines[] = $this->buildConsentDefault($global);
        $lines[] = "gtag('set','developer_id.dMmY1Mm',true);";
        $lines[] = "gtag('set','ads_data_redaction',true);";

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $settings
     * @param string[]|null $regionCodes Leave null for the global default.
     */
    private function buildConsentDefault(array $settings, ?array $regionCodes = null): string
    {
        $data = [];
        foreach (Sanitizer::CONSENT_KEYS as $key) {
            $data[$key] = Sanitizer::consentValue($settings[$key] ?? 'denied');
        }

        $waitForUpdate = $settings['wait_for_update'] ?? 0;
        $data['wait_for_update'] = Sanitizer::isValidWaitForUpdate($waitForUpdate)
            ? (int) $waitForUpdate
            : Sanitizer::DEFAULT_WAIT_FOR_UPDATE;

        if ($regionCodes !== null) {
            $data['region'] = $regionCodes;
        }

        return sprintf("gtag('consent','default',%s);", $this->json->serialize($data));
    }

    /**
     * "DE, FR" becomes ["DE", "FR"].
     *
     * @return string[]
     */
    private function splitRegionCodes(string $regionCodes): array
    {
        $codes = array_map('trim', explode(',', $regionCodes));

        return array_values(array_filter($codes, static fn (string $code): bool => $code !== ''));
    }
}
