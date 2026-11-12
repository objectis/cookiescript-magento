<?php
declare(strict_types=1);

namespace CookieScript\Banner\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Saves and loads the results of the website scan (table cookiescript_scan_data).
 *
 * There is one row per configuration scope: "default" (all stores), a website, or a store view.
 * Like Magento's own settings, a store view uses its website's row, and a website uses the default row,
 * when it has no scan of its own.
 */
class ScanStorage
{
    public const STATUS_IDLE = 'idle';
    public const STATUS_SCANNING = 'scanning';

    private const TABLE = 'cookiescript_scan_data';

    /**
     * What a scope without a saved row looks like.
     */
    private const EMPTY_ROW = [
        'secret' => '',
        'policy_url' => '',
        'language' => '',
        'scan_status' => self::STATUS_IDLE,
        'script_content' => '',
        'cookies_json' => '',
        'scanned_at' => 0,
        'script_updated_at' => 0,
    ];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly StoreManagerInterface $storeManager,
        private readonly Json $json
    ) {
    }

    /**
     * The row saved for exactly this scope, or an empty row when there is none.
     *
     * @return array<string, mixed> Always has "scope" and "scope_id" plus every column of EMPTY_ROW.
     */
    public function loadExact(string $scope, int $scopeId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE))
            ->where('scope = ?', $scope)
            ->where('scope_id = ?', $scopeId);

        $row = $connection->fetchRow($select) ?: [];

        return $this->normalize($row, $scope, $scopeId);
    }

    /**
     * The row that is actually in use for this scope: the closest one (own scope, then website,
     * then default) that has a banner script. If none has, the empty row of this scope.
     *
     * @return array<string, mixed>
     */
    public function loadEffective(string $scope, int $scopeId): array
    {
        foreach ($this->getScopeChain($scope, $scopeId) as [$chainScope, $chainScopeId]) {
            $row = $this->loadExact($chainScope, $chainScopeId);
            if ($row['script_content'] !== '') {
                return $row;
            }
        }

        return $this->loadExact($scope, $scopeId);
    }

    /**
     * The banner script a store view should print on the storefront ('' when there is none).
     */
    public function getScriptContentForStore(int $storeId): string
    {
        return $this->loadEffective(ScopeInterface::SCOPE_STORES, $storeId)['script_content'];
    }

    /**
     * Creates or updates the row of a scope. Only the columns you pass are changed.
     *
     * @param array<string, mixed> $data Any of the keys of EMPTY_ROW.
     */
    public function save(string $scope, int $scopeId, array $data): void
    {
        $changes = array_intersect_key($data, self::EMPTY_ROW);
        if ($changes === []) {
            return;
        }

        $this->resource->getConnection()->insertOnDuplicate(
            $this->resource->getTableName(self::TABLE),
            ['scope' => $scope, 'scope_id' => $scopeId] + $changes,
            array_keys($changes)
        );
    }

    /**
     * The scanned cookies of a row, grouped by category.
     *
     * @param array<string, mixed> $row
     * @return array<int, array<string, mixed>> Each group has "type_name" and a "cookies" list.
     */
    public function getCookieGroups(array $row): array
    {
        if ($row['cookies_json'] === '') {
            return [];
        }

        try {
            $groups = $this->json->unserialize($row['cookies_json']);
        } catch (\InvalidArgumentException) {
            return [];
        }

        return is_array($groups) ? $groups : [];
    }

    /**
     * Scopes to look at, from the most specific to the default one.
     *
     * @return array<int, array{0: string, 1: int}>
     */
    private function getScopeChain(string $scope, int $scopeId): array
    {
        $chain = [];

        if ($scope === ScopeInterface::SCOPE_STORES) {
            $chain[] = [ScopeInterface::SCOPE_STORES, $scopeId];
            $scope = ScopeInterface::SCOPE_WEBSITES;
            $scopeId = (int) $this->storeManager->getStore($scopeId)->getWebsiteId();
        }

        if ($scope === ScopeInterface::SCOPE_WEBSITES) {
            $chain[] = [ScopeInterface::SCOPE_WEBSITES, $scopeId];
        }

        $chain[] = [ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0];

        return $chain;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize(array $row, string $scope, int $scopeId): array
    {
        $row = array_merge(self::EMPTY_ROW, array_intersect_key($row, self::EMPTY_ROW));

        // mediumtext columns come back as null when empty
        foreach (['secret', 'policy_url', 'language', 'scan_status', 'script_content', 'cookies_json'] as $textColumn) {
            $row[$textColumn] = (string) $row[$textColumn];
        }
        foreach (['scanned_at', 'script_updated_at'] as $timeColumn) {
            $row[$timeColumn] = (int) $row[$timeColumn];
        }

        return ['scope' => $scope, 'scope_id' => $scopeId] + $row;
    }
}
