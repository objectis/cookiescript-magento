<?php
declare(strict_types=1);

namespace CookieScript\Banner\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

/**
 * Runs on "bin/magento module:uninstall --remove-data CookieScript_Banner":
 * removes the saved settings.
 */
class Uninstall implements UninstallInterface
{
    /**
     * @inheritDoc
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        $setup->startSetup();
        $setup->getConnection()->delete($setup->getTable('core_config_data'), ['path LIKE ?' => 'cookiescript/%']);
        $setup->endSetup();
    }
}
