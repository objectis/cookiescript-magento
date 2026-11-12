<?php
declare(strict_types=1);

namespace CookieScript\Banner\Observer;

use CookieScript\Banner\Model\CacheInvalidator;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * After the CookieScript settings are saved, the cached storefront pages still contain the old banner.
 */
class InvalidatePageCache implements ObserverInterface
{
    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $this->cacheInvalidator->invalidatePageCache();
    }
}
