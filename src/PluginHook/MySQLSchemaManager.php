<?php

namespace Doctrine\Composer\PluginHook;

use Composer\Installer\PackageEvent;

/**
 *
 */
final class MySQLSchemaManager extends Common\AbstractPluginHook
{
    public function getPackageName(): string
    {
        return 'doctrine/dbal';
    }

    public function onPackageChange(PackageEvent $event)
    {
        $block = '$type = $this->extractDoctrineTypeFromComment($tableColumn[\'comment\'] ?? null, $type);';

        $this->Print('Updating "MySQLSchemaManager.php" file.');
        file_append_block('$type = $this->platform->getDoctrineTypeMapping($dbType);', $block, $this->getBundleDir() . '/src/Schema/MySQLSchemaManager.php');
    }
}