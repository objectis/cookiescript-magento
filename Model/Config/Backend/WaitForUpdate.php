<?php
declare(strict_types=1);

namespace CookieScript\Banner\Model\Config\Backend;

use CookieScript\Banner\Model\ConsentMode\Sanitizer;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * "Wait for update" must be a whole number of milliseconds greater than zero.
 */
class WaitForUpdate extends Value
{
    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = trim((string) $this->getValue());

        if (!Sanitizer::isPositiveInteger($value)) {
            throw new LocalizedException(__(
                'Google Consent Mode "Wait for update" must be a whole number greater than zero. No changes were saved.'
            ));
        }

        $this->setValue($value);

        return parent::beforeSave();
    }
}
