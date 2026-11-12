<?php
declare(strict_types=1);

namespace CookieScript\Banner\Model\Config\Source;

use CookieScript\Banner\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * "Setup" dropdown: the two ways of getting a banner.
 */
class Mode implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::MODE_WITH_ACCOUNT, 'label' => __('I have a CookieScript account')],
            ['value' => Config::MODE_WITHOUT_ACCOUNT, 'label' => __("I don't have a CookieScript account (scan my website)")],
        ];
    }
}
