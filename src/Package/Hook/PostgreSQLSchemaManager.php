<?php

namespace Doctrine\Composer\Package\Hook;

use Composer\Installer\PackageEvent;
use Doctrine\Composer\Package\AbstractHook;
use Doctrine\Composer\CodeModifier;

/**
 *
 */
final class PostgreSQLSchemaManager extends AbstractHook
{
    public function getPackageName(): string
    {
        return 'doctrine/dbal';
    }

    public function getPackageRequirements(): string
    {
        return "*";
    }

    public function onPackageChange(PackageEvent $event)
    {
        $search = '$type = $this->platform->getDoctrineTypeMapping($dbType);';
        $block  = '$type = $this->extractDoctrineTypeFromComment($tableColumn[\'comment\'] ?? null, $type);';
        
        $this->print('Reintroducing comments in `PostgreSQLSchemaManager.php`.');
        $codeModifier = new CodeModifier($this->getBundleDir() . '/src/Schema/PostgreSQLSchemaManager.php', $this->getAuthor());
        $codeModifier->appendToLine("extractDoctrineComments", $search, $block);
    }
}