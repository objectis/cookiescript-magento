<?php
declare(strict_types=1);

namespace CookieScript\Banner\Block\Adminhtml\Form\Field;

use CookieScript\Banner\Model\Config\Source\ConsentValue;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

/**
 * A "Granted / Denied" dropdown, used as a column of the regional settings table.
 */
class ConsentValueColumn extends Select
{
    public function __construct(
        Context $context,
        private readonly ConsentValue $consentValueSource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Called by the dynamic rows table to set the form field name.
     */
    public function setInputName(string $value): self
    {
        return $this->setName($value);
    }

    /**
     * Called by the dynamic rows table to set the form field ID.
     */
    public function setInputId(string $value): self
    {
        return $this->setId($value);
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->consentValueSource->toOptionArray());
        }

        return parent::_toHtml();
    }
}
