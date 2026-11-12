<?php
declare(strict_types=1);

namespace CookieScript\Banner\Model\Config\Source;

use CookieScript\Banner\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Whether the script goes first or last inside the chosen location.
 */
class Position implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::POSITION_FIRST, 'label' => __('First')],
            ['value' => Config::POSITION_LAST, 'label' => __('Last')],
        ];
    }
}
