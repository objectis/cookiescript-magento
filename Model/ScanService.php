<?php
declare(strict_types=1);

namespace CookieScript\Banner\Model;

use CookieScript\Banner\Model\Api\Client;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The "I don't have a CookieScript account" flow:
 *
 *   1. checkScanner()  - can this site be scanned right now?
 *   2. start()         - begin a scan
 *   3. pollStatus()    - called every few seconds until the scan is finished; then the cookie report
 *                        and the banner script are saved
 *   4. updateScript()  - later: fetch a fresh copy of the banner script (once every 24 hours)
 *
 * Each step works on one configuration scope (default / website / store view).
 */
class ScanService
{
    private const UPDATE_INTERVAL_SECONDS = 24 * 60 * 60;

    private const API_STATUS_PROGRESS = 'progress';
    private const API_STATUS_ANALYZE = 'analyze';
    private const API_STATUS_FINISH = 'finish';

    public function __construct(
        private readonly Client $api,
        private readonly ScanStorage $storage,
        private readonly StoreManagerInterface $storeManager,
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly Json $json
    ) {
    }

    /**
     * @return array{error: string} Empty "error" when the site can be scanned.
     * @throws LocalizedException
     */
    public function checkScanner(string $scope, int $scopeId): array
    {
        $response = $this->api->check($this->getSecret($scope, $scopeId));

        return ['error' => (string) ($response['error'] ?? '')];
    }

    /**
     * @throws LocalizedException
     */
    public function start(string $scope, int $scopeId, string $policyUrl, string $language): void
    {
        $secret = $this->getSecret($scope, $scopeId);

        $this->storage->save($scope, $scopeId, [
            'policy_url' => $policyUrl,
            'language' => $language,
            'scan_status' => ScanStorage::STATUS_SCANNING,
        ]);

        try {
            $response = $this->api->start([
                'wp_id' => $secret,
                'url' => $this->getSiteUrl($scope, $scopeId),
                'privacy_policy_url' => $policyUrl !== '' ? $policyUrl : null,
                'lang' => $language !== '' ? $language : null,
            ]);
        } catch (LocalizedException $exception) {
            $this->markIdle($scope, $scopeId);
            throw $exception;
        }

        if (!empty($response['error'])) {
            $this->markIdle($scope, $scopeId);
            throw new LocalizedException(__('%1', (string) $response['error']));
        }
    }

    /**
     * Asks how the scan is going. When it is finished, saves the results.
     *
     * @return array{status: string, groups?: array<int, array<string, mixed>>}
     * @throws LocalizedException
     */
    public function pollStatus(string $scope, int $scopeId): array
    {
        $secret = $this->getSecret($scope, $scopeId);

        try {
            $data = $this->api->info($secret);
        } catch (LocalizedException $exception) {
            $this->markIdle($scope, $scopeId);
            throw $exception;
        }

        if (!empty($data['error'])) {
            $this->markIdle($scope, $scopeId);
            throw new LocalizedException(__('%1', (string) $data['error']));
        }

        $status = (string) ($data['status'] ?? '');

        if ($status === self::API_STATUS_PROGRESS || $status === self::API_STATUS_ANALYZE) {
            return ['status' => $status];
        }

        if ($status === self::API_STATUS_FINISH) {
            return $this->finishScan($scope, $scopeId, $data);
        }

        $this->markIdle($scope, $scopeId);
        throw new LocalizedException(__('The scan ended unexpectedly. Please try again.'));
    }

    /**
     * Downloads a fresh copy of the banner script of the last scan.
     *
     * @throws LocalizedException
     */
    public function updateScript(string $scope, int $scopeId): void
    {
        // The scan may belong to the website or the default scope; that is where the update goes.
        $row = $this->storage->loadEffective($scope, $scopeId);
        if ($row['script_content'] === '' || $row['secret'] === '') {
            throw new LocalizedException(__('There is no banner script yet. Scan your website first.'));
        }

        $retryAfter = $row['script_updated_at'] + self::UPDATE_INTERVAL_SECONDS - time();
        if ($retryAfter > 0) {
            throw new LocalizedException(__(
                'The banner script can be updated once every 24 hours. Try again in %1.',
                $this->formatDuration($retryAfter)
            ));
        }

        $data = $this->api->info($row['secret']);
        if (!empty($data['error'])) {
            throw new LocalizedException(__('%1', (string) $data['error']));
        }

        $script = $this->api->downloadScript((string) ($data['script_url'] ?? ''));

        $this->storage->save($row['scope'], $row['scope_id'], [
            'script_content' => $script,
            'script_updated_at' => time(),
        ]);
        $this->cacheInvalidator->invalidatePageCache();
    }

    /**
     * @param array<string, mixed> $data The finished answer of the scan service.
     * @return array{status: string, groups: array<int, array<string, mixed>>}
     * @throws LocalizedException
     */
    private function finishScan(string $scope, int $scopeId, array $data): array
    {
        $groups = is_array($data['grouped_cookies'] ?? null) ? $data['grouped_cookies'] : [];

        $this->storage->save($scope, $scopeId, [
            'cookies_json' => $this->json->serialize($groups),
            'scanned_at' => time(),
            'scan_status' => ScanStorage::STATUS_IDLE,
        ]);

        // The report is saved even if this fails; "Update banner script" can retry the download.
        $script = $this->api->downloadScript((string) ($data['script_url'] ?? ''));

        $this->storage->save($scope, $scopeId, ['script_content' => $script]);
        $this->cacheInvalidator->invalidatePageCache();

        return ['status' => self::API_STATUS_FINISH, 'groups' => $groups];
    }

    /**
     * The random site identifier of a scope; created on first use.
     */
    private function getSecret(string $scope, int $scopeId): string
    {
        $secret = $this->storage->loadExact($scope, $scopeId)['secret'];

        if ($secret === '') {
            $secret = bin2hex(random_bytes(16));
            $this->storage->save($scope, $scopeId, ['secret' => $secret]);
        }

        return $secret;
    }

    private function markIdle(string $scope, int $scopeId): void
    {
        $this->storage->save($scope, $scopeId, ['scan_status' => ScanStorage::STATUS_IDLE]);
    }

    /**
     * The address of the storefront that gets scanned for this scope.
     */
    private function getSiteUrl(string $scope, int $scopeId): string
    {
        /** @var Store $store */
        $store = match ($scope) {
            ScopeInterface::SCOPE_STORES => $this->storeManager->getStore($scopeId),
            ScopeInterface::SCOPE_WEBSITES => $this->storeManager->getWebsite($scopeId)->getDefaultStore(),
            default => $this->storeManager->getDefaultStoreView(),
        };

        return rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_WEB), '/');
    }

    /**
     * 90 seconds -> "2m", 3 hours 5 minutes -> "3h 5m".
     */
    private function formatDuration(int $seconds): string
    {
        $minutes = (int) ceil($seconds / 60);
        if ($minutes < 60) {
            return $minutes . 'm';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest > 0 ? $hours . 'h ' . $rest . 'm' : $hours . 'h';
    }
}
