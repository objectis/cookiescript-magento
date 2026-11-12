<?php
declare(strict_types=1);

namespace CookieScript\Banner\Controller\Adminhtml\Scan;

use CookieScript\Banner\Model\ScanService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Shared plumbing of the scanner's AJAX actions: finds which configuration scope the admin is
 * working in, runs the action, and answers with JSON:
 *
 *   {"success": true, ...}  or  {"success": false, "message": "..."}
 */
abstract class AbstractScan extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'CookieScript_Banner::config';

    public function __construct(
        Context $context,
        protected readonly JsonFactory $jsonFactory,
        protected readonly ScanService $scanService,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Does the work of one action.
     *
     * @return array<string, mixed> Extra data for the JSON answer.
     * @throws LocalizedException To show a message to the admin.
     */
    abstract protected function process(string $scope, int $scopeId): array;

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        try {
            [$scope, $scopeId] = $this->resolveScope();
            $result->setData(['success' => true] + $this->process($scope, $scopeId));
        } catch (LocalizedException $exception) {
            $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            $this->logger->error('CookieScript scanner: ' . $exception->getMessage(), ['exception' => $exception]);
            $result->setData([
                'success' => false,
                'message' => (string) __('Something went wrong. Please check the Magento logs and try again.'),
            ]);
        }

        return $result;
    }

    /**
     * The scope the admin is configuring: "default" (all stores), a website, or a store view.
     *
     * @return array{0: string, 1: int}
     * @throws LocalizedException
     */
    private function resolveScope(): array
    {
        $scope = (string) $this->getRequest()->getParam('scope', ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $scopeId = (int) $this->getRequest()->getParam('scope_id', 0);

        try {
            switch ($scope) {
                case ScopeInterface::SCOPE_STORES:
                    $this->storeManager->getStore($scopeId);
                    return [$scope, $scopeId];
                case ScopeInterface::SCOPE_WEBSITES:
                    $this->storeManager->getWebsite($scopeId);
                    return [$scope, $scopeId];
                default:
                    return [ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0];
            }
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('The selected store scope does not exist.'));
        }
    }
}
