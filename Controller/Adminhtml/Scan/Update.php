<?php
declare(strict_types=1);

namespace CookieScript\Banner\Controller\Adminhtml\Scan;

/**
 * "Update banner script": downloads a fresh copy of the banner script of the last scan.
 * Allowed once every 24 hours.
 */
class Update extends AbstractScan
{
    /**
     * @inheritDoc
     */
    protected function process(string $scope, int $scopeId): array
    {
        $this->scanService->updateScript($scope, $scopeId);

        return ['message' => (string) __('The banner script has been updated.')];
    }
}
