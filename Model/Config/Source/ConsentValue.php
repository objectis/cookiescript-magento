<?php
declare(strict_types=1);

namespace CookieScript\Banner\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * The two values Google Consent Mode accepts for each consent type.
 *
 * "Denied" comes first on purpose: it is the privacy-safe choice, and a new row in the
 * regional settings table starts with the first option.
 */
class ConsentValue implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'denied', 'label' => __('Denied')],
            ['value' => 'granted', 'label' => __('Granted')],
        ];
    }
}
