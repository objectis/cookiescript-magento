<?php
declare(strict_types=1);

namespace CookieScript\Banner\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Languages CookieScript can generate a banner in (same list as the WordPress plugin).
 */
class BannerLanguage implements OptionSourceInterface
{
    private const LANGUAGES = [
        'ar' => 'Arabic',
        'eu' => 'Basque',
        'bg' => 'Bulgarian',
        'ca' => 'Catalan',
        'hr' => 'Croatian',
        'cs' => 'Czech',
        'da' => 'Danish',
        'nl' => 'Dutch',
        'en' => 'English',
        'et' => 'Estonian',
        'fi' => 'Finnish',
        'fr' => 'French',
        'de' => 'German',
        'el' => 'Greek',
        'hi' => 'Hindi',
        'hu' => 'Hungarian',
        'ga' => 'Irish',
        'it' => 'Italian',
        'lv' => 'Latvian',
        'lt' => 'Lithuanian',
        'mt' => 'Maltese',
        'no' => 'Norwegian',
        'pl' => 'Polish',
        'pt' => 'Portuguese',
        'ro' => 'Romanian',
        'ru' => 'Russian',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'es' => 'Spanish',
        'sv' => 'Swedish',
        'th' => 'Thai',
        'tr' => 'Turkish',
        'uk' => 'Ukrainian',
        'vi' => 'Vietnamese',
    ];

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::LANGUAGES as $code => $label) {
            $options[] = ['value' => $code, 'label' => $label];
        }

        return $options;
    }

    /**
     * @return string[] All valid language codes.
     */
    public function getCodes(): array
    {
        return array_keys(self::LANGUAGES);
    }
}
