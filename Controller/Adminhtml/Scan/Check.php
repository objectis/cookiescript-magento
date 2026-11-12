<?php
declare(strict_types=1);

namespace CookieScript\Banner\Controller\Adminhtml\Scan;

/**
 * Asks the CookieScript scan service whether this site can be scanned right now.
 * Answers {"success": true, "error": ""}; "error" is filled when the service says no.
 */
class Check extends AbstractScan
{
    /**
     * @inheritDoc
     */
    protected function process(string $scope, int $scopeId): array
    {
        return $this->scanService->checkScanner($scope, $scopeId);
    }
}
