<?php
declare(strict_types=1);

namespace CookieScript\Banner\Controller\Adminhtml\Scan;

use CookieScript\Banner\Model\Config\Source\BannerLanguage;
use CookieScript\Banner\Model\ScanService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Starts scanning the website, using the cookie policy page and banner language the admin entered.
 */
class Start extends AbstractScan
{
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        ScanService $scanService,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        private readonly BannerLanguage $bannerLanguage
    ) {
        parent::__construct($context, $jsonFactory, $scanService, $storeManager, $logger);
    }

    /**
     * @inheritDoc
     */
    protected function process(string $scope, int $scopeId): array
    {
        $policyUrl = trim((string) $this->getRequest()->getParam('policy_url'));
        $language = trim((string) $this->getRequest()->getParam('language'));

        if ($policyUrl !== '' && !$this->isHttpUrl($policyUrl)) {
            throw new LocalizedException(__('Please enter a valid cookie policy URL that starts with http:// or https://.'));
        }

        if ($language !== '' && !in_array($language, $this->bannerLanguage->getCodes(), true)) {
            throw new LocalizedException(__('Please select a banner language from the list.'));
        }

        $this->scanService->start($scope, $scopeId, $policyUrl, $language);

        return [];
    }

    private function isHttpUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && preg_match('#^https?://#i', $url) === 1;
    }
}
