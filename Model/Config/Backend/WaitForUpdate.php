<?php
declare(strict_types=1);

namespace CookieScript\Banner\Model\Config\Backend;

use CookieScript\Banner\Model\ConsentMode\Sanitizer;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * "Wait for update" must be a whole number of milliseconds, from 1 to 60000.
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

        if (!Sanitizer::isValidWaitForUpdate($value)) {
            throw new LocalizedException(__(
                'Google Consent Mode "Wait for update" must be a whole number from 1 to 60000. No changes were saved.'
            ));
        }

        $this->setValue($value);

        return parent::beforeSave();
    }
}
