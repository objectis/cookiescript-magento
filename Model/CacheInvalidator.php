<?php
declare(strict_types=1);

namespace CookieScript\Banner\Model;

use Magento\Framework\App\Cache\TypeListInterface;

/**
 * The banner is printed into the pages, so the full page cache must be refreshed whenever
 * the banner settings or the banner script change.
 */
class CacheInvalidator
{
    private const FULL_PAGE_CACHE = 'full_page';

    public function __construct(
        private readonly TypeListInterface $cacheTypeList
    ) {
    }

    /**
     * Removes the cached storefront pages, so the next visit shows the new banner settings.
     *
     * Only marking the cache as "invalid" is not enough: Magento keeps serving the old pages
     * until someone refreshes the cache by hand, and the banner would seem to do nothing.
     */
    public function invalidatePageCache(): void
    {
        $this->cacheTypeList->cleanType(self::FULL_PAGE_CACHE);
    }
}
