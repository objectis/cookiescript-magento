<?php
declare(strict_types=1);

namespace CookieScript\Banner\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Only banner links that really come from CookieScript are accepted, because the link is
 * printed into every page of the storefront as a script address.
 */
class SourceUrl extends Value
{
    /**
     * https://{cdn|eu|ca|ca-eu|geo}.cookie-script.com/{s|staging}/<32 hex characters>.js,
     * optionally followed by ?region=... / ?country=... / ?state=...
     */
    private const PATTERN = '/^https:\/\/(cdn|eu|ca|ca-eu|geo)\.cookie-script\.com\/(s|staging)\/[0-9a-f]{32}\.js([\?\&](region|country|state)=[a-z\-]*)*$/i';

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = trim((string) $this->getValue());

        if ($value !== '' && preg_match(self::PATTERN, $value) !== 1) {
            throw new LocalizedException(__(
                'The CookieScript source link is not valid. It should look like '
                . 'https://cdn.cookie-script.com/s/0123456789abcdef0123456789abcdef.js. No changes were saved.'
            ));
        }

        $this->setValue($value);

        return parent::beforeSave();
    }
}
