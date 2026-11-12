<?php
declare(strict_types=1);

namespace CookieScript\Banner\Model\Config\Backend;

use CookieScript\Banner\Model\ConsentMode\Sanitizer;
use Magento\Config\Model\Config\Backend\Serialized\ArraySerialized;
use Magento\Framework\Exception\LocalizedException;

/**
 * Validates and cleans the "Regional settings" rows before they are stored.
 *
 * The parent class turns the cleaned array into JSON for the database.
 */
class RegionalSettings extends ArraySerialized
{
    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $rows = $this->getValue();
        $cleanRows = [];

        foreach (is_array($rows) ? $rows : [] as $rowId => $row) {
            if ($rowId === '__empty' || !is_array($row)) {
                continue;
            }

            $regionCode = trim((string) ($row['region_code'] ?? ''));
            if ($regionCode === '') {
                continue; // an unfilled row is simply dropped
            }

            if (!Sanitizer::isValidRegionCodes($regionCode)) {
                throw new LocalizedException(__(
                    'A region code is not valid. Use ISO codes such as "US" or "US-CA", separated by commas. No changes were saved.'
                ));
            }

            // Left empty means "use the default" (500 ms); anything else must be a valid number.
            $waitForUpdate = trim((string) ($row['wait_for_update'] ?? ''));
            if ($waitForUpdate !== '' && !Sanitizer::isPositiveInteger($waitForUpdate)) {
                throw new LocalizedException(__(
                    'Regional "Wait for update" must be a whole number greater than zero. No changes were saved.'
                ));
            }

            $cleanRows[$rowId] = Sanitizer::sanitizeBlock($row, true);
        }

        $this->setValue($cleanRows);

        return parent::beforeSave();
    }
}
