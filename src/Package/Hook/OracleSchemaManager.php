<?php

namespace Doctrine\Composer\Package\Hook;

use Composer\Installer\PackageEvent;
use Doctrine\Composer\Package\AbstractHook;
use Doctrine\Composer\CodeModifier;

final class OracleSchemaManager extends AbstractHook
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
        
        $this->print('Reintroducing comments in `OracleSchemaManager.php`.');
        $codeModifier = new CodeModifier($this->getBundleDir() . '/src/Schema/OracleSchemaManager.php', $this->getAuthor());
        $codeModifier->appendToLine("extractDoctrineComments", $search, $block);
    }
}