<?php
declare(strict_types=1);

namespace CookieScript\Banner\Model\Config\Source;

use CookieScript\Banner\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Where in the page the banner script is placed.
 */
class Location implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::LOCATION_HEAD, 'label' => __('Header (inside <head>)')],
            ['value' => Config::LOCATION_BODY, 'label' => __('Body (right after <body> or right before </body>)')],
        ];
    }
}
