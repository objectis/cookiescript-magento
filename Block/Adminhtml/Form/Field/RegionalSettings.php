<?php
declare(strict_types=1);

namespace CookieScript\Banner\Block\Adminhtml\Form\Field;

use CookieScript\Banner\Model\ConsentMode\Sanitizer;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

/**
 * The "Regional settings" table in the Google Consent Mode section: one row per region,
 * with the region code, the seven consent values and "wait for update".
 */
class RegionalSettings extends AbstractFieldArray
{
    /**
     * One dropdown renderer per column (Magento ties the "selected" marker of a dropdown
     * to the renderer's name and ID, so columns cannot share one).
     *
     * @var array<string, ConsentValueColumn>
     */
    private array $consentValueRenderers = [];

    /**
     * Defines the table columns.
     *
     * @throws LocalizedException
     */
    protected function _prepareToRender(): void
    {
        $this->addColumn('region_code', [
            'label' => __('Region code'),
            'class' => 'required-entry input-text',
            'style' => 'width: 90px',
        ]);

        foreach ($this->getConsentColumnLabels() as $key => $label) {
            $this->addColumn($key, [
                'label' => $label,
                'renderer' => $this->getConsentValueRenderer($key),
            ]);
        }

        $this->addColumn('wait_for_update', [
            'label' => __('Wait for update (ms)'),
            'class' => 'validate-digits validate-greater-than-zero input-text',
            'style' => 'width: 70px',
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = (string) __('Add region');
    }

    /**
     * Pre-selects the saved "Denied / Granted" value of each dropdown in an existing row.
     *
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $selectedOptions = [];

        foreach (array_keys($this->getConsentColumnLabels()) as $key) {
            $value = $row->getData($key);
            if ($value !== null) {
                $optionHash = $this->getConsentValueRenderer($key)->calcOptionHash($value);
                $selectedOptions['option_' . $optionHash] = 'selected="selected"';
            }
        }

        $row->setData('option_extra_attrs', $selectedOptions);
    }

    /**
     * @return array<string, \Magento\Framework\Phrase> Column name => column title.
     */
    private function getConsentColumnLabels(): array
    {
        $labels = [
            'ad_storage' => __('Advertisement'),
            'analytics_storage' => __('Analytics'),
            'ad_user_data' => __('Ad user data'),
            'ad_personalization' => __('Ad personalization'),
            'functionality_storage' => __('Functional'),
            'personalization_storage' => __('Personalization'),
            'security_storage' => __('Security'),
        ];

        // Keep the columns in step with the consent types the sanitizer knows.
        return array_intersect_key($labels, array_flip(Sanitizer::CONSENT_KEYS));
    }

    /**
     * @throws LocalizedException
     */
    private function getConsentValueRenderer(string $columnName): ConsentValueColumn
    {
        if (!isset($this->consentValueRenderers[$columnName])) {
            $this->consentValueRenderers[$columnName] = $this->getLayout()->createBlock(
                ConsentValueColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->consentValueRenderers[$columnName];
    }
}
