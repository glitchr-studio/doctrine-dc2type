<?php

namespace Doctrine\Composer\PluginHook;

use Composer\Installer\PackageEvent;

/**
 *
 */
final class PostgreSQLSchemaManager extends Common\AbstractPluginHook
{
    public function getPackageName(): string
    {
        return 'doctrine/dbal';
    }

    public function onPackageChange(PackageEvent $event)
    {
        $block = '$type = $this->extractDoctrineTypeFromComment($tableColumn[\'comment\'] ?? null, $type);';

        $this->Print('Updating "PostgreSQLSchemaManager.php" file.');
        file_append_block('$type = $this->platform->getDoctrineTypeMapping($dbType);', $block, $this->getBundleDir() . '/src/Schema/PostgreSQLSchemaManager.php');
    }
}