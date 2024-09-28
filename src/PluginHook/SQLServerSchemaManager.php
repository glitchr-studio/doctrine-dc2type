<?php

namespace Doctrine\Composer\PluginHook;

use Composer\Installer\PackageEvent;

/**
 *
 */
final class SQLServerSchemaManager extends Common\AbstractPluginHook
{
    public function getPackageName(): string
    {
        return 'doctrine/dbal';
    }

    public function onPackageChange(PackageEvent $event)
    {
        $search = '$type = $this->platform->getDoctrineTypeMapping($dbType);';
        $block  = '$type = $this->extractDoctrineTypeFromComment($tableColumn[\'comment\'] ?? null, $type);';
        
        $this->Print('Updating "SQLServerSchemaManager.php" file.');
        $codeModifier = new \CodeModifier($this->getBundleDir() . '/src/Schema/SQLServerSchemaManager.php', $this->getAuthor());
        $codeModifier->appendToLine("comment", $search, $block);
    }
}