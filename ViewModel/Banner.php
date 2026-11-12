<?php
declare(strict_types=1);

namespace CookieScript\Banner\ViewModel;

use CookieScript\Banner\Model\Config;
use CookieScript\Banner\Model\ConsentMode\ScriptBuilder;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

/**
 * Decides what the storefront prints for the cookie banner (see view/frontend/templates/banner.phtml).
 *
 * The layout places four copies of the banner template (head / body, first / last).
 * Each copy asks shouldRender(); only the one matching the admin setting prints anything.
 */
class Banner implements ArgumentInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ScriptBuilder $consentModeScriptBuilder,
        private readonly SecureHtmlRenderer $secureRenderer
    ) {
    }

    /**
     * @param string $location Config::LOCATION_HEAD or Config::LOCATION_BODY
     * @param string $position Config::POSITION_FIRST or Config::POSITION_LAST
     */
    public function shouldRender(string $location, string $position): bool
    {
        return $this->config->isEnabled()
            && $this->getSourceUrl() !== ''
            && $location === $this->config->getLocation()
            && $position === $this->config->getPosition();
    }

    /**
     * The banner link from the CookieScript account.
     */
    public function getSourceUrl(): string
    {
        return $this->config->getSourceUrl();
    }

    /**
     * The Google Consent Mode defaults script, or '' when Google Consent Mode is off.
     */
    public function getConsentModeScript(): string
    {
        if (!$this->config->isConsentModeEnabled()) {
            return '';
        }

        return $this->consentModeScriptBuilder->build(
            $this->config->getConsentModeGlobalSettings(),
            $this->config->getConsentModeRegionalSettings()
        );
    }

    /**
     * Prints JavaScript as an inline <script> tag.
     *
     * The secure renderer registers the script with Magento's Content Security Policy, so it also
     * runs when CSP is enforced. A literal "</script" inside the code would end the tag early,
     * so it is written as "<\/script" (the same thing to JavaScript).
     */
    public function renderInlineScript(string $javascript): string
    {
        $safeJavascript = (string) preg_replace('#</(script)#i', '<\\/$1', $javascript);

        return $this->secureRenderer->renderTag('script', ['type' => 'text/javascript'], $safeJavascript, false);
    }
}
