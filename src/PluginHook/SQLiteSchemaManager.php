<?php

namespace Doctrine\Composer\PluginHook;

use Composer\Installer\PackageEvent;
use Doctrine\Composer\PluginHook\AbstractPluginHook;

/**
 *
 */
final class SQLiteSchemaManagerPluginHook extends AbstractPluginHook
{
    public function getPackageName(): string
    {
        return 'doctrine/orm';
    }

    public function onPackageEvent(PackageEvent $event)
    {
        file_replace(
            '$this->_metadataCache[$relation[\'targetEntity\']]',
            '$this->_metadataCache[$relation[\'targetEntity\']] ?? $this->_metadataCache[str_replace("App\\\\", "Base\\\\", $relation[\'targetEntity\'])]',
            $this->getBundleDir() . '/src/Internal/Hydration/ObjectHydrator.php'
        );
        $this->Print('Updated "ObjectHydrator.php" file. Add metadata cache fallback for base component');

        file_replace('private ', 'protected ', $this->getBundleDir() . '/src/Query/SqlWalker.php');
        $this->Print('Updated "SqlWalker.php" file. Turn `private` elements into `protected` elements');

        file_replace('private ', 'protected ', $this->getBundleDir() . '/src/Mapping//ClassMetadataFactory.php');
        $this->Print('Updated "ClassMetadataFactory.php" file. Turn `private` elements into `protected` elements');
    }
}
