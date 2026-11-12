<?php
declare(strict_types=1);

namespace CookieScript\Banner\Controller\Adminhtml\Scan;

/**
 * Asks how the scan is going. The admin page calls this every few seconds.
 *
 * Answers {"success": true, "status": "progress" | "analyze" | "finish"}; once the status is
 * "finish" the answer also contains the cookie report ("groups").
 */
class Status extends AbstractScan
{
    /**
     * @inheritDoc
     */
    protected function process(string $scope, int $scopeId): array
    {
        return $this->scanService->pollStatus($scope, $scopeId);
    }
}
