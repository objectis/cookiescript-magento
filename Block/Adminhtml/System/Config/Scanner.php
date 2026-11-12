<?php
declare(strict_types=1);

namespace CookieScript\Banner\Block\Adminhtml\System\Config;

use CookieScript\Banner\Model\Config\Source\BannerLanguage;
use CookieScript\Banner\Model\ScanStorage;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;

/**
 * The "Website scanner" part of the settings page, for people without a CookieScript account:
 * a scan button, the cookie report and the "Update banner script" button.
 *
 * It replaces the normal "label + input" row of the settings page with a full-width panel
 * (see scanner.phtml). The buttons are wired up by view/adminhtml/web/js/scanner.js.
 */
class Scanner extends Template implements RendererInterface
{
    /**
     * @var string
     */
    protected $_template = 'CookieScript_Banner::system/config/scanner.phtml';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $ownData = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $effectiveData = null;

    public function __construct(
        Context $context,
        private readonly ScanStorage $scanStorage,
        private readonly BannerLanguage $bannerLanguage,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Renders the panel as a full-width row of the settings table.
     *
     * @inheritDoc
     */
    public function render(AbstractElement $element)
    {
        return sprintf(
            '<tr id="row_%s"><td colspan="5">%s</td></tr>',
            $element->getHtmlId(),
            $this->toHtml()
        );
    }

    /**
     * The scope being configured: "default", "websites" or "stores".
     */
    public function getScope(): string
    {
        return $this->resolveScope()[0];
    }

    public function getScopeId(): int
    {
        return $this->resolveScope()[1];
    }

    /**
     * Human readable name of the scope that owns the scan data in use.
     */
    public function getEffectiveScopeLabel(): string
    {
        return match ($this->getEffectiveData()['scope']) {
            ScopeInterface::SCOPE_STORES => (string) __('this store view'),
            ScopeInterface::SCOPE_WEBSITES => (string) __('the website'),
            default => (string) __('Default Config'),
        };
    }

    /**
     * True when the report shown here was made for another scope (a parent of this one).
     */
    public function isInherited(): bool
    {
        $effective = $this->getEffectiveData();

        return $effective['script_content'] !== ''
            && ($effective['scope'] !== $this->getScope() || $effective['scope_id'] !== $this->getScopeId());
    }

    public function hasBannerScript(): bool
    {
        return $this->getEffectiveData()['script_content'] !== '';
    }

    public function isScanning(): bool
    {
        return $this->getOwnData()['scan_status'] === ScanStorage::STATUS_SCANNING;
    }

    /**
     * The scanned cookies, grouped by category.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCookieGroups(): array
    {
        return $this->scanStorage->getCookieGroups($this->getEffectiveData());
    }

    public function getPolicyUrl(): string
    {
        return $this->getOwnData()['policy_url'] ?: $this->getEffectiveData()['policy_url'];
    }

    public function getLanguage(): string
    {
        return $this->getOwnData()['language'] ?: $this->getEffectiveData()['language'];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getLanguageOptions(): array
    {
        return $this->bannerLanguage->toOptionArray();
    }

    /**
     * Settings handed to scanner.js (as JSON for the x-magento-init script).
     */
    public function getScriptConfigJson(): string
    {
        return $this->json->serialize([
            'checkUrl' => $this->getUrl('cookiescript/scan/check'),
            'startUrl' => $this->getUrl('cookiescript/scan/start'),
            'statusUrl' => $this->getUrl('cookiescript/scan/status'),
            'updateUrl' => $this->getUrl('cookiescript/scan/update'),
            'formKey' => $this->getFormKey(),
            'scope' => $this->getScope(),
            'scopeId' => $this->getScopeId(),
            'scanning' => $this->isScanning(),
            'hasReport' => $this->getCookieGroups() !== [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getOwnData(): array
    {
        return $this->ownData ??= $this->scanStorage->loadExact($this->getScope(), $this->getScopeId());
    }

    /**
     * @return array<string, mixed>
     */
    private function getEffectiveData(): array
    {
        return $this->effectiveData ??= $this->scanStorage->loadEffective($this->getScope(), $this->getScopeId());
    }

    /**
     * Reads the scope from the settings page address (.../section/cookiescript/store/1/).
     *
     * @return array{0: string, 1: int}
     */
    private function resolveScope(): array
    {
        $storeId = (int) $this->getRequest()->getParam('store', 0);
        if ($storeId > 0) {
            return [ScopeInterface::SCOPE_STORES, $storeId];
        }

        $websiteId = (int) $this->getRequest()->getParam('website', 0);
        if ($websiteId > 0) {
            return [ScopeInterface::SCOPE_WEBSITES, $websiteId];
        }

        return [ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0];
    }
}
